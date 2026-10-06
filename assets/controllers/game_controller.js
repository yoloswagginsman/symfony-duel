import { Controller } from '@hotwired/stimulus';

/*
 * Поле партии. Состояние — из API (/api/games/{id}), обновления — из Mercure.
 * Правил здесь нет: ход отправляется на сервер, недопустимый вернётся ошибкой 422 — показываем её.
 *
 * Сервер присылает шаги (steps): события одного действия и поле после него. Шаги проигрываются
 * по очереди: что разыграно, стрелка «кто кого атакует», урон, гибель — и только потом следующий.
 *
 * Ход кликами или перетаскиванием (то же самое):
 *   карта в руке → (существо: свободная клетка) → (если нужна цель: цель или «Без цели»)
 *   своё существо → существо противника / фортификация противника / свободная точка
 *
 * Время хода считает сервер; здесь — только отсчёт. Дошёл до нуля — запрашиваем партию,
 * сервер завершит ход и разошлёт событие.
 */

// Способности, которым при розыгрыше нужна цель (или «без цели»)
const TARGETED_ABILITIES = ['Damage', 'Dispel', 'Equip_Attack', 'Equip_Health'];

const KEYWORD_ICONS = { taunt: '🛡', charge: '⚡', flying: '🕊', cover: '🌫', hostile: '☠' };

// Что делает заклинание или предмет — на лицевой стороне карты.
// Значения по умолчанию — как DEFAULT_* в классах способностей (src/Game/Ability)
const EFFECT_BADGES = {
    Damage: { fallback: 1, text: (value) => `⚔ ${value}` },
    AoE_Damage: { fallback: 1, text: (value) => `⚔ ${value} всем` },
    Heal: { fallback: 2, text: (value) => `✚ ${value}` },
    Repair: { fallback: 2, text: (value) => `🏰 +${value}` },
    Equip_Attack: { fallback: 1, text: (value) => `+${value} ⚔` },
    Equip_Health: { fallback: 1, text: (value) => `+${value} ❤` },
};

// Пояснения ключевых слов, полученных не своей способностью (например, Укрытие от Тумана)
const KEYWORD_HINTS = {
    taunt: 'Провокация: пока она на поле, атаковать можно только её.',
    charge: 'Рывок: атакует в ход выхода на поле.',
    flying: 'Полёт: атаковать может только летающее существо.',
    cover: 'Укрытие (туман): существо нельзя атаковать.',
    hostile: 'Враждебный ландшафт: ложится на половину соперника.',
};

// Переподключение к хабу, если поток оборвался
const RECONNECT_DELAY_MS = 3000;

// Темп показа шагов (мс). Ход соперника — медленно, чтобы успеть понять; свой — быстрее
const BEAT = { reveal: 1000, arrow: 700, dying: 450, settle: 450 };
const PACE = { opponent: 1, me: 0.4 };
const FLOAT_MS = 1200;
const CAPTURE_MS = 1100;

// Добор: карты, взятые разом (стартовая рука), летят по очереди
const DRAW_STAGGER_MS = 110;

// Рука: карты всегда немного перекрываются (доля ширины карты), при нехватке места — сильнее
const HAND_MIN_OVERLAP = 0.14;

// Веер руки: наклон и опускание на каждую карту от центра
const FAN_DEGREES = 4;
const FAN_DROP_PX = 6;
const BANNER_MS = 1300;

export default class extends Controller {
    static targets = [
        'status', 'timer', 'banner', 'opponent', 'points', 'me', 'hand', 'hint', 'noTarget',
        'endTurn', 'surrender', 'skip', 'log', 'toast', 'arrows', 'reveal',
        'opponentHero', 'meHero', 'detail', 'result', 'resultTitle', 'resultReason', 'resultStats',
    ];

    static values = {
        gameUrl: String,
        tokenUrl: String,
        // vendorCode => картинка, описание, раса, тип, редкость (то, чего нет в состоянии партии)
        cardInfo: Object,
        // slug способности => пояснение из контента
        abilityInfo: Object,
    };

    connect() {
        this.token = null;
        this.game = null;
        this.state = null;
        this.selection = null;
        this.cards = {};
        this.version = 0;
        this.clockOffset = 0;
        this.refreshing = false;
        this.queue = Promise.resolve();
        this.playing = false;
        this.skipping = false;
        this.wakeUp = null;
        this.resultShown = false;
        this.surrendered = null;
        this.stream = new AbortController();

        this.element.addEventListener('click', (event) => this.pickFrom(event));
        this.element.addEventListener('dragstart', (event) => this.onDragStart(event));
        this.element.addEventListener('dragover', (event) => this.onDragOver(event));
        this.element.addEventListener('drop', (event) => this.onDrop(event));
        // Отпустили мимо цели — выбор снимается, подсветка гаснет
        this.element.addEventListener('dragend', () => {
            this.dragging = false;
            if (this.selection && !this.playing) {
                this.selection = null;
                this.render();
            }
        });
        this.element.addEventListener('pointermove', (event) => this.aim(event));
        this.element.addEventListener('pointerover', (event) => this.showDetail(event));
        this.element.addEventListener('pointerout', (event) => this.hideDetail(event));
        this.timerInterval = setInterval(() => this.tick(), 1000);
        this.onResize = () => this.fitHand();
        window.addEventListener('resize', this.onResize);
        this.load();
    }

    disconnect() {
        this.stream.abort();
        clearInterval(this.timerInterval);
        window.removeEventListener('resize', this.onResize);
    }

    // ─── Данные ──────────────────────────────────────────

    async load() {
        try {
            await this.receive(await this.api('GET', this.gameUrlValue));
            this.subscribe();
        } catch (error) {
            this.statusTarget.textContent = error.message;
        }
    }

    /**
     * Новое состояние партии (ответ API или Mercure) — в очередь показа. Старее уже принятого — пропускаем:
     * своё действие приходит и ответом, и событием Mercure.
     */
    receive(data) {
        if (data.game.version <= this.version) {
            return this.queue;
        }
        this.version = data.game.version;
        this.queue = this.queue.then(() => this.show(data)).catch((error) => console.error(error));

        return this.queue;
    }

    async show(data) {
        this.game = data.game;
        this.clockOffset = new Date(data.game.serverTime).getTime() - Date.now();
        const steps = data.steps || [];

        // Первая загрузка (поля ещё нет) — просто показать; иначе — проиграть шаги
        const played = this.state !== null && steps.length > 0;
        if (played) {
            this.playing = true;
            this.skipping = false;
            this.selection = null;
            this.clearArrows();
            this.render();

            for (const step of steps) {
                await this.playStep(step);
            }
            this.playing = false;
        }

        const firstLoad = this.state === null;
        this.state = data.state;
        this.selection = null;
        if (this.state) {
            this.collectCards(this.state);
        }
        // Первая загрузка (в том числе после F5) — журнал из истории ходов с сервера
        if (firstLoad && this.state && data.history) {
            this.logTarget.replaceChildren();
            data.history.forEach((move) => this.logStep(move, move.actor));
        }
        // После шагов поле уже в итоговом виде; повторная перерисовка оборвала бы анимации (добор, урон)
        if (!played) {
            this.clearArrows();
            this.render();
        } else {
            this.renderControls();
        }

        if (this.state && this.state.winner !== null && !this.resultShown) {
            this.showResult();
        }
    }

    async act(action) {
        try {
            await this.receive(await this.api('POST', `${this.gameUrlValue}/actions`, action));
        } catch (error) {
            this.selection = null;
            this.clearArrows();
            this.render();
            this.toast(error.message);
            // Ход мог перейти (например, вышло время) — берём свежее состояние
            this.refresh();
        }
    }

    async refresh() {
        if (this.refreshing) {
            return;
        }
        this.refreshing = true;
        try {
            await this.receive(await this.api('GET', this.gameUrlValue));
        } catch (error) {
            this.toast(error.message);
        } finally {
            this.refreshing = false;
        }
    }

    /**
     * Запрос к API с JWT. Токен берём у сайта (сессия) и обновляем, если истёк.
     */
    async api(method, url, body = null, retry = true) {
        if (!this.token) {
            const response = await fetch(this.tokenUrlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('Не удалось получить токен — войдите заново.');
            }
            this.token = (await response.json()).token;
        }

        const response = await fetch(url, {
            method,
            headers: {
                Authorization: `Bearer ${this.token}`,
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : null,
        });

        if (response.status === 401 && retry) {
            this.token = null;
            return this.api(method, url, body, false);
        }

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const violations = data.violations ? Object.values(data.violations).join(' ') : '';
            throw new Error(violations || data.error || `Ошибка ${response.status}`);
        }

        return data;
    }

    /**
     * Mercure: EventSource не умеет заголовок Authorization, поэтому поток SSE читаем через fetch.
     */
    async subscribe() {
        while (!this.stream.signal.aborted) {
            try {
                const subscription = await this.api('GET', `${this.gameUrlValue}/subscription`);
                const url = `${subscription.hub}?match=${encodeURIComponent(subscription.topic)}`;
                const response = await fetch(url, {
                    headers: { Authorization: `Bearer ${subscription.token}`, Accept: 'text/event-stream' },
                    signal: this.stream.signal,
                });
                if (response.ok) {
                    await this.readEvents(response.body);
                }
            } catch (error) {
                if (this.stream.signal.aborted) {
                    return;
                }
            }
            await new Promise((resolve) => setTimeout(resolve, RECONNECT_DELAY_MS));
        }
    }

    /**
     * Разбор потока SSE: события разделены пустой строкой, данные — в строках «data: …».
     */
    async readEvents(body) {
        const reader = body.pipeThrough(new TextDecoderStream()).getReader();
        let buffer = '';

        for (;;) {
            const { value, done } = await reader.read();
            if (done) {
                return;
            }

            buffer += value;
            let boundary;
            while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                const block = buffer.slice(0, boundary);
                buffer = buffer.slice(boundary + 2);

                const data = block
                    .split('\n')
                    .filter((line) => line.startsWith('data:'))
                    .map((line) => line.slice(5).replace(/^ /, ''))
                    .join('\n');
                if (data) {
                    this.receive(JSON.parse(data));
                }
            }
        }
    }

    // ─── Пошаговый показ ─────────────────────────────────

    /**
     * Один шаг: на старом поле — что делается (карта соперника, стрелка атаки), погибшие тают,
     * затем новое поле и числа урона.
     */
    async playStep(step) {
        const actor = this.state.activePlayer;
        const mine = actor === this.state.you;
        const pace = this.skipping ? 0 : (mine ? PACE.me : PACE.opponent);
        this.collectCards(step.state);
        this.logStep(step, actor);

        const played = step.events.find((event) => event.type === 'CardPlayed');
        const attack = step.events.find((event) => event.type === 'AttackDeclared');

        if (played && !mine) {
            await this.revealCard(this.cards[played.card], BEAT.reveal * pace);
        }
        if (played?.target) {
            this.drawArrow(this.handOf(actor), this.cardElement(played.target), 'spell');
            await this.sleep(BEAT.arrow * pace);
        }
        if (attack) {
            const target = attack.target !== null
                ? this.cardElement(attack.target)
                : this.fortificationElement(1 - this.cards[attack.attacker].owner);
            this.drawArrow(this.cardElement(attack.attacker), target, 'attack');
            this.pulse(this.cardElement(attack.attacker), 'is-attacking');
            await this.sleep(BEAT.arrow * pace);
        }

        if (this.fadeOutDying(step.events)) {
            await this.sleep(BEAT.dying * pace);
        }

        // Захват точки: запомнить, где существо стояло до перерисовки — оттуда оно и переедет
        const capture = step.events.find((event) => event.type === 'PointCaptured');
        const captureFrom = capture ? this.cardElement(capture.card)?.getBoundingClientRect() : null;

        this.state = step.state;
        this.clearArrows();
        this.render();
        this.animate(step.events);
        if (capture) {
            this.animateCapture(capture, captureFrom);
        }

        const turnStarted = step.events.find((event) => event.type === 'TurnStarted');
        if (turnStarted && turnStarted.player === this.state.you && this.state.winner === null) {
            this.banner('Ваш ход!');
        }
        if (step.events.some((event) => ['CreatureDamaged', 'FortificationDamaged', 'CreatureDied', 'CardPlayed', 'CardDrawn', 'CardCreated', 'PointCaptured'].includes(event.type))) {
            await this.sleep(BEAT.settle * pace);
        }
    }

    skip() {
        this.skipping = true;
        this.wakeUp?.();
    }

    /**
     * Пауза показа; «Пропустить» обрывает её.
     */
    sleep(ms) {
        if (this.skipping || ms <= 0) {
            return Promise.resolve();
        }
        return new Promise((resolve) => {
            const timer = setTimeout(resolve, ms);
            this.wakeUp = () => {
                clearTimeout(timer);
                resolve();
            };
        });
    }

    async revealCard(card, ms) {
        if (!card || ms <= 0) {
            return;
        }
        const caption = this.span('reveal-caption', `${this.playerName(card.owner, true)} разыгрывает`);
        this.revealTarget.replaceChildren(caption, this.renderCard(card));
        this.revealTarget.hidden = false;
        await this.sleep(ms);
        this.revealTarget.hidden = true;
    }

    // ─── Ходы ────────────────────────────────────────────

    pickFrom(event) {
        const element = event.target.closest('[data-pick]');
        if (element) {
            this.pick(element);
            return;
        }

        // Клик по пустому месту поля — передумали: выбор и стрелка прицела снимаются
        if (this.selection && event.target.closest('.board-field, .board-hand')) {
            this.cancel();
        }
    }

    pick(element, { fromDrag = false } = {}) {
        if (!this.isMyTurn()) {
            return;
        }

        const pick = element.dataset.pick;
        const id = element.dataset.id ? Number(element.dataset.id) : null;
        const selection = this.selection;

        if (pick === 'hand') {
            return this.selectHandCard(id, { fromDrag });
        }

        // Выбрана карта, ждущая цель: своё существо — цель (предмет), а не атакующий
        if (selection?.type === 'hand' && this.waitsForTarget() && ['enemy-creature', 'my-creature', 'landscape'].includes(pick)) {
            return this.playSelected(id);
        }

        if (pick === 'my-creature') {
            this.selection = { type: 'attacker', id };
            if (fromDrag) {
                element.classList.add('is-selected');
                return this.updateMode();
            }
            return this.render();
        }

        if (pick === 'my-cell' && selection?.type === 'hand' && selection.card.kind === 'creature') {
            selection.cell = Number(element.dataset.cell);
            return this.needsTarget(selection.card) ? this.render() : this.playSelected(null);
        }

        if (selection?.type === 'attacker') {
            if (pick === 'enemy-creature') {
                return this.act({ type: 'attack', attackerId: selection.id, targetId: id });
            }
            if (pick === 'enemy-fortification') {
                return this.act({ type: 'attack', attackerId: selection.id });
            }
            if (pick === 'point') {
                return this.act({ type: 'capture_point', creatureId: selection.id, point: Number(element.dataset.point) });
            }
        }

        // Клик не по цели (например, по своей пустой клетке при атаке) — выбор снимается
        if (selection) {
            this.cancel();
        }
    }

    /**
     * Заклинание без цели по клику разыгрывается сразу, при перетаскивании — когда карту отпустят.
     * При перетаскивании поле не перерисовываем: браузер прервёт перетаскивание, если элемент исчезнет.
     */
    selectHandCard(id, { fromDrag = false } = {}) {
        const card = this.me().hand.find((handCard) => handCard.id === id);
        if (card.manaCost > this.me().mana) {
            return this.toast(`Не хватает маны: нужно ${card.manaCost}, есть ${this.me().mana}.`);
        }

        this.selection = { type: 'hand', card, cell: null };
        if (fromDrag) {
            this.element.querySelector(`[data-pick="hand"][data-id="${id}"]`)?.classList.add('is-selected');
            return this.updateMode();
        }
        if (card.kind !== 'creature' && !this.needsTarget(card)) {
            return this.playSelected(null);
        }
        this.render();
    }

    playSelected(targetId) {
        const { card, cell } = this.selection;
        this.act({
            type: 'play_card',
            cardId: card.id,
            ...(cell !== null ? { cell } : {}),
            ...(targetId !== null ? { targetId } : {}),
        });
    }

    playWithoutTarget() {
        if (this.selection?.type === 'hand') {
            this.playSelected(null);
        }
    }

    endTurn() {
        this.act({ type: 'end_turn' });
    }

    surrender() {
        if (confirm('Сдаться? Победа достанется сопернику.')) {
            this.act({ type: 'surrender' });
        }
    }

    cancel() {
        this.selection = null;
        this.clearArrows();
        this.render();
    }

    fullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            this.element.requestFullscreen?.();
        }
    }

    needsTarget(card) {
        return card.abilities.some((ability) => TARGETED_ABILITIES.includes(ability.slug));
    }

    /**
     * Выбрана карта, и ей пора выбрать цель (существу — после клетки).
     */
    waitsForTarget() {
        const selection = this.selection;
        return selection?.type === 'hand'
            && this.needsTarget(selection.card)
            && (selection.card.kind !== 'creature' || selection.cell !== null);
    }

    // ─── Перетаскивание: начало — как клик по карте, отпускание — как клик по цели ──

    onDragStart(event) {
        const element = event.target.closest('[draggable="true"][data-pick]');
        if (!element || !this.isMyTurn()) {
            event.preventDefault();
            return;
        }
        this.dragging = true;
        this.hideDetail();
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', element.dataset.id);
        this.pick(element, { fromDrag: true });
    }

    onDragOver(event) {
        if (this.selection && (event.target.closest('[data-pick]') || event.target.closest('.board-side'))) {
            event.preventDefault();
        }
    }

    onDrop(event) {
        event.preventDefault();
        this.dragging = false;

        // Заклинание или ландшафт без цели — разыгрывается, где бы его ни отпустили над полем
        const selection = this.selection;
        if (selection?.type === 'hand' && selection.card.kind !== 'creature' && !this.needsTarget(selection.card)) {
            return this.playSelected(null);
        }

        const element = event.target.closest('[data-pick]');
        if (element && element.dataset.pick !== 'hand') {
            this.pick(element);
        }
    }

    // ─── Прицел: стрелка от выбранного существа к курсору ─

    aim(event) {
        const aiming = this.selection?.type === 'attacker' || this.waitsForTarget();
        this.element.classList.toggle('is-aiming', aiming);
        if (!aiming || this.aimFrame) {
            return;
        }

        this.aimFrame = requestAnimationFrame(() => {
            this.aimFrame = null;
            const source = this.selection?.type === 'attacker'
                ? this.cardElement(this.selection.id)
                : this.element.querySelector('.board-hand .is-selected') || this.handOf(this.state.you);
            const hovered = event.target.closest('[data-pick]:not([data-pick="hand"]):not([data-pick="my-cell"])');
            this.clearArrows();
            this.drawArrow(source, hovered || { x: event.clientX, y: event.clientY }, 'aim');
        });
    }

    // ─── Время хода ──────────────────────────────────────

    /**
     * Раз в секунду: отсчёт по часам сервера. Дошёл до нуля — запросить партию (сервер завершит ход).
     */
    tick() {
        const deadline = this.game?.turnDeadline;
        if (!deadline || !this.state || this.state.winner !== null) {
            this.timerTarget.textContent = '';
            return;
        }

        const left = Math.ceil((new Date(deadline).getTime() - (Date.now() + this.clockOffset)) / 1000);
        const seconds = Math.max(0, left);
        this.timerTarget.textContent = `⏳ ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        this.timerTarget.classList.toggle('is-urgent', seconds <= 15);

        if (left <= 0 && !this.playing) {
            this.refresh();
        }
    }

    // ─── Отрисовка ───────────────────────────────────────

    render() {
        if (!this.state) {
            this.statusTarget.textContent = 'Ждём соперника… Партия начнётся, как только он присоединится.';
            this.endTurnTarget.disabled = true;
            this.surrenderTarget.hidden = true;
            return;
        }

        const me = this.me();
        const opponent = this.opponent();

        this.statusTarget.textContent = this.statusText();
        this.hideDetail();
        this.opponentHeroTarget.replaceChildren(this.renderHero(opponent, false));
        this.meHeroTarget.replaceChildren(this.renderHero(me, true));
        this.opponentHeroTarget.classList.toggle('is-active', this.state.activePlayer === opponent.index);
        this.meHeroTarget.classList.toggle('is-active', this.state.activePlayer === me.index);
        this.opponentTarget.replaceChildren(this.renderSide(opponent, false));
        this.opponentTarget.classList.toggle('is-active', this.state.activePlayer === opponent.index);
        this.pointsTarget.replaceChildren(...this.state.capturePoints.map((point, index) => this.renderPoint(point, index)));
        this.meTarget.replaceChildren(this.renderSide(me, true));
        this.meTarget.classList.toggle('is-active', this.state.activePlayer === me.index);
        this.handTarget.replaceChildren(...me.hand.map((card, index) => this.fan(this.renderCard(card, {
            pick: 'hand',
            selected: this.selection?.type === 'hand' && this.selection.card.id === card.id,
            disabled: !this.isMyTurn() || card.manaCost > me.mana,
            playable: this.isPlayable(card),
        }), index, me.hand.length)));
        this.fitHand();

        this.renderControls();
    }

    /**
     * Панель управления и режим поля — без перерисовки карт (её можно звать, не обрывая анимации).
     */
    renderControls() {
        this.refreshHand();
        this.refreshBoard();
        this.statusTarget.textContent = this.statusText();
        this.hintTarget.textContent = this.hintText();
        // Предмету цель обязательна — «Без цели» не показываем
        this.noTargetTarget.hidden = !this.waitsForTarget() || this.selection.card.kind === 'item';
        this.endTurnTarget.disabled = !this.isMyTurn();
        this.surrenderTarget.hidden = this.state.winner !== null;
        this.skipTarget.hidden = !this.playing;
        this.element.classList.toggle('is-playing', this.playing);
        this.element.classList.toggle('is-aiming', this.selection?.type === 'attacker' || this.waitsForTarget());
        this.updateMode();
        this.tick();
    }

    /**
     * Аура и доступность карт в руке — на тех же элементах (не пересоздавая их: летящая карта долетит).
     */
    refreshHand() {
        const me = this.me();
        for (const element of this.handTarget.children) {
            const card = me.hand.find((handCard) => handCard.id === Number(element.dataset.card));
            if (!card) {
                continue;
            }
            const disabled = !this.isMyTurn() || card.manaCost > me.mana;
            element.classList.toggle('is-playable', this.isPlayable(card));
            element.classList.toggle('is-disabled', disabled);
            element.draggable = !disabled;
        }
    }

    /**
     * Свои существа после показа хода соперника: снова «готово атаковать» (свечение, перетаскивание) —
     * на тех же элементах, без перерисовки.
     */
    refreshBoard() {
        for (const element of this.meTarget.querySelectorAll('[data-pick="my-creature"]')) {
            const card = this.me().board.find((boardCard) => boardCard?.id === Number(element.dataset.card));
            if (!card) {
                continue;
            }
            const exhausted = card.actedThisTurn || (card.summonedThisTurn && !card.keywords.includes('charge'));
            element.classList.toggle('is-ready', !exhausted && this.isMyTurn() && card.attack > 0);
            element.draggable = !exhausted && this.isMyTurn();
        }
    }

    /**
     * Карту можно разыграть прямо сейчас: ваш ход, хватает маны и есть куда (клетка — существу, своё существо — предмету).
     * Остальное (цели, Провокация) проверит сервер.
     */
    isPlayable(card) {
        if (!this.isMyTurn() || card.manaCost > this.me().mana) {
            return false;
        }
        if (card.kind === 'creature') {
            return this.me().board.includes(null);
        }
        if (card.kind === 'item') {
            return this.me().board.some((creature) => creature && !creature.hidden);
        }
        return true;
    }

    /**
     * Режим поля по выбранному: что подсвечивать (CSS, board.css). Работает и при перетаскивании — поле не перерисовывается.
     */
    updateMode() {
        const selection = this.selection;
        let mode = '';
        if (selection?.type === 'attacker') {
            mode = 'attack';
        } else if (selection?.type === 'hand') {
            const card = selection.card;
            if (card.kind === 'creature' && selection.cell === null) {
                mode = 'creature';
            } else if (card.kind === 'item') {
                mode = 'item';
            } else if (this.waitsForTarget()) {
                mode = 'target';
            } else if (card.kind !== 'creature') {
                mode = 'cast';
            }
        }
        if (mode) {
            this.element.dataset.mode = mode;
        } else {
            delete this.element.dataset.mode;
        }
    }

    /**
     * Игрок в боковой панели: кто, чей ход, фортификация (цель атаки), мана кристаллами, рука и колода.
     */
    renderHero(player, isMe) {
        const hero = this.element.ownerDocument.createElement('div');
        hero.className = 'hero-body';

        const label = this.element.ownerDocument.createElement('div');
        label.className = 'side-label';
        label.append(
            this.span('side-label-who', isMe ? 'Вы' : 'Соперник'),
            this.span('side-label-name', this.game.players[player.index]?.nickname || 'соперник'),
        );
        if (this.state.activePlayer === player.index && this.state.winner === null) {
            label.append(this.span('side-label-turn', 'ходит'));
        }

        const mana = this.element.ownerDocument.createElement('div');
        mana.className = 'hero-mana';
        mana.title = `Мана: ${player.mana} из ${player.maxMana}`;
        for (let crystal = 0; crystal < Math.max(player.maxMana, player.mana); crystal++) {
            mana.append(this.span(`mana-crystal${crystal < player.mana ? ' is-full' : ''}${crystal >= player.maxMana ? ' is-bonus' : ''}`, ''));
        }
        mana.append(this.span('hero-mana-text', `${player.mana}/${player.maxMana}`));

        const counts = this.element.ownerDocument.createElement('div');
        counts.className = 'hero-counts';
        const handCount = this.badge(`🂠 ${player.handCount}`, 'Карт в руке');
        handCount.dataset.hand = player.index;
        const deckCount = this.badge(`📚 ${player.deckCount}`, 'Карт в колоде');
        deckCount.dataset.deck = player.index;
        counts.append(handCount, deckCount);

        // В панели — только число; цель атаки — плашка на поле (renderFortification)
        const fortification = this.element.ownerDocument.createElement('div');
        fortification.className = 'hero-fortification';
        fortification.title = 'Фортификация — упала до 0: партия проиграна';
        fortification.append(this.span('fortification-icon', '🏰'), this.span('fortification-value', player.fortification));

        hero.append(label, fortification, mana, counts);
        return hero;
    }

    /**
     * Половина поля: ландшафт и четыре клетки.
     */
    renderSide(player, isMe) {
        const side = this.element.ownerDocument.createElement('div');
        side.className = 'side';

        const landscape = this.element.ownerDocument.createElement('div');
        landscape.className = 'side-landscape';
        landscape.append(player.landscape
            ? this.renderCard(player.landscape, { pick: 'landscape', small: true })
            : this.placeholder('Ландшафта нет'));

        const board = this.element.ownerDocument.createElement('div');
        board.className = 'side-board';
        player.board.forEach((card, cell) => board.append(this.renderCell(card, cell, isMe)));

        side.append(landscape, board, this.renderFortification(player, isMe));
        return side;
    }

    /**
     * Фортификация на поле — по центру края территории: у соперника сверху, у вас снизу.
     * Это цель атаки (и сюда летит урон); в боковой панели — только число.
     */
    renderFortification(player, isMe) {
        const fortification = this.element.ownerDocument.createElement('div');
        fortification.className = `fortification field-fortification field-fortification--${isMe ? 'me' : 'opponent'}`;
        fortification.title = 'Фортификация — главная цель: упала до 0 — партия проиграна';
        fortification.append(this.span('fortification-icon', '❤️'), this.span('fortification-value', player.fortification));
        fortification.dataset.player = player.index;
        if (!isMe) {
            fortification.dataset.pick = 'enemy-fortification';
            fortification.classList.toggle('is-target', this.selection?.type === 'attacker');
        }
        return fortification;
    }

    renderCell(card, cell, isMe) {
        const slot = this.element.ownerDocument.createElement('div');
        slot.className = 'cell';

        if (card && card.hidden) {
            slot.append(this.placeholder('🌫 Туман'));
        } else if (card) {
            const exhausted = card.actedThisTurn || (card.summonedThisTurn && !card.keywords.includes('charge'));
            slot.append(this.renderCard(card, {
                pick: isMe ? 'my-creature' : 'enemy-creature',
                selected: this.selection?.type === 'attacker' && this.selection.id === card.id,
                exhausted: isMe && exhausted,
                ready: isMe && !exhausted && this.isMyTurn() && card.attack > 0,
                target: !isMe && (this.selection?.type === 'attacker' || this.waitsForTarget()),
            }));
        } else if (isMe) {
            slot.dataset.pick = 'my-cell';
            slot.dataset.cell = cell;
            slot.classList.add('cell--empty');
            slot.classList.toggle('is-target', this.selection?.type === 'hand' && this.selection.card.kind === 'creature' && this.selection.cell === null);
        } else {
            slot.classList.add('cell--empty');
        }
        return slot;
    }

    renderPoint(point, index) {
        const element = this.element.ownerDocument.createElement('div');
        element.className = 'point';
        element.append(this.renderCard(point.building, { small: true }));

        if (point.holder) {
            const mine = point.holder.owner === this.state.you;
            element.classList.add(mine ? 'point--mine' : 'point--enemy');
            element.append(this.renderCard(point.holder, {
                pick: mine ? null : 'enemy-creature',
                target: !mine && this.selection?.type === 'attacker',
            }));
        } else {
            const free = this.placeholder('Точка свободна');
            free.dataset.pick = 'point';
            free.dataset.point = index;
            free.classList.toggle('is-target', this.selection?.type === 'attacker');
            element.append(free);
        }
        return element;
    }

    renderCard(card, { pick = null, selected = false, disabled = false, exhausted = false, ready = false, target = false, small = false, playable = false } = {}) {
        const element = this.element.ownerDocument.createElement('button');
        element.type = 'button';
        element.className = `g-card g-card--${card.kind}`;
        element.classList.toggle('is-selected', selected);
        element.classList.toggle('is-disabled', disabled);
        element.classList.toggle('is-exhausted', exhausted);
        element.classList.toggle('is-ready', ready);
        element.classList.toggle('is-playable', playable);
        // Провокация — особая рамка: видно издалека, кого придётся атаковать первым
        // (в руке ключевых слов ещё нет — смотрим способность)
        element.classList.toggle('has-taunt', card.keywords.includes('taunt') || card.abilities.some((ability) => ability.slug === 'Taunt'));
        element.classList.toggle('is-target', target);
        element.classList.toggle('g-card--small', small);
        element.dataset.card = card.id;
        if (pick) {
            element.dataset.pick = pick;
            element.dataset.id = card.id;
            element.draggable = (pick === 'hand' || pick === 'my-creature') && !disabled && !exhausted;
        }

        const info = this.cardInfoValue[card.vendorCode] || {};
        if (info.image) {
            element.style.backgroundImage = `url("${info.image}")`;
        }
        if (info.rarity) {
            element.dataset.rarity = info.rarity;
        }

        // У построек на точках маны нет — их не разыгрывают
        if (card.kind !== 'building') {
            element.append(this.span('g-card-mana', card.manaCost));
        }
        element.append(
            this.span('g-card-name', card.name),
            this.span('g-card-keywords', card.keywords.map((keyword) => KEYWORD_ICONS[keyword] || '').join('')),
        );
        if (card.attack !== null && card.health !== null && card.kind === 'creature') {
            element.append(this.span('g-card-attack', card.attack), this.span('g-card-health', card.health));
        }
        const effect = this.effectText(card);
        if (effect && card.kind !== 'creature') {
            element.append(this.span('g-card-effect', effect));
        }
        if (card.equipment?.length) {
            // Значок кузницы — у существа есть усиление; что именно надето — при наведении
            const equipment = this.span('g-card-equipment', '⚒');
            equipment.title = `Усилено: ${card.equipment.map((item) => item.name).join(', ')}`;
            element.append(equipment);
        }
        if (exhausted) {
            element.append(this.span('g-card-sleep', '💤'));
        }
        return element;
    }

    /**
     * Рука не шире поля: чем больше карт, тем плотнее они лежат (перекрытие — в CSS-переменной).
     */
    fitHand() {
        const cards = this.handTarget.children;
        const field = this.element.querySelector('.board-map');
        if (cards.length < 2 || !field) {
            this.handTarget.style.removeProperty('--hand-overlap');
            return;
        }
        const cardWidth = cards[0].offsetWidth;
        // Крайние карты веера наклонены и выступают — запас на ширину карты
        const available = field.getBoundingClientRect().width - cardWidth;
        const overlap = Math.max(cardWidth * HAND_MIN_OVERLAP, (cards.length * cardWidth - available) / (cards.length - 1));
        this.handTarget.style.setProperty('--hand-overlap', `${Math.min(overlap, cardWidth * 0.8)}px`);
    }

    /**
     * Рука веером: крайние карты наклонены и опущены, как в руке у игрока. Значения — для CSS (board.css).
     */
    fan(element, index, count) {
        const offset = index - (count - 1) / 2;
        element.style.setProperty('--fan-rotate', `${offset * FAN_DEGREES}deg`);
        element.style.setProperty('--fan-drop', `${Math.abs(offset) * FAN_DROP_PX}px`);
        element.style.setProperty('--fan-order', index);
        return element;
    }

    /**
     * Эффект карты коротко: «⚔ 1 всем», «+1 ❤»… — из способностей с числом.
     */
    effectText(card) {
        return card.abilities
            .filter((ability) => EFFECT_BADGES[ability.slug])
            .map((ability) => EFFECT_BADGES[ability.slug].text(ability.value ?? EFFECT_BADGES[ability.slug].fallback))
            .join(' · ');
    }

    // ─── Подробности карты при наведении ─────────────────

    showDetail(event) {
        const element = event.target.closest?.('[data-card]');
        const card = element && this.cards[element.dataset.card];
        if (!card || this.dragging) {
            return;
        }
        if (this.detailFor === element) {
            return;
        }
        this.detailFor = element;

        const info = this.cardInfoValue[card.vendorCode] || {};
        const detail = this.detailTarget;
        detail.replaceChildren();
        detail.dataset.rarity = info.rarity || 'common';

        if (info.image) {
            const image = this.element.ownerDocument.createElement('div');
            image.className = 'card-detail-image';
            image.style.backgroundImage = `url("${info.image}")`;
            detail.append(image);
        }

        detail.append(
            this.span('card-detail-name', card.name),
            this.span('card-detail-kind', [info.type, info.race, info.rarityName].filter(Boolean).join(' · ')),
        );

        const stats = this.element.ownerDocument.createElement('div');
        stats.className = 'card-detail-stats';
        if (card.kind !== 'building') {
            stats.append(this.span('stat stat--mana', `💎 ${card.manaCost}`));
        }
        if (card.attack !== null && card.health !== null) {
            stats.append(
                this.span('stat stat--attack', `⚔ ${card.attack}${card.baseAttack !== null && card.attack !== card.baseAttack ? ` (обычно ${card.baseAttack})` : ''}`),
                this.span('stat stat--health', `❤ ${card.health}${card.baseHealth !== null && card.health !== card.baseHealth ? ` (обычно ${card.baseHealth})` : ''}`),
            );
        }
        const effect = this.effectText(card);
        if (effect && card.kind !== 'creature') {
            stats.append(this.span('stat stat--effect', effect));
        }
        detail.append(stats);

        if (card.equipment?.length) {
            const equipment = this.element.ownerDocument.createElement('div');
            equipment.className = 'card-detail-equipment';
            equipment.append(this.span('card-detail-ability', 'Предметы:'));
            for (const item of card.equipment) {
                const bonus = [item.attack ? `+${item.attack} ⚔` : '', item.health ? `+${item.health} ❤` : ''].filter(Boolean).join(' ');
                equipment.append(this.span('card-detail-equipment-item', `${item.attack > 0 ? '🗡' : '🛡'} ${item.name} (${bonus})`));
            }
            detail.append(equipment);
        }

        const lines = this.element.ownerDocument.createElement('ul');
        lines.className = 'card-detail-abilities';
        for (const ability of card.abilities) {
            const item = this.element.ownerDocument.createElement('li');
            const title = this.span('card-detail-ability', ability.value !== null ? `${ability.slug} ${ability.value}` : ability.slug);
            item.append(title, this.span('card-detail-ability-text', this.abilityInfoValue[ability.slug] || ''));
            lines.append(item);
        }
        for (const keyword of card.keywords) {
            if (KEYWORD_HINTS[keyword] && !card.abilities.some((ability) => ability.slug.toLowerCase() === keyword)) {
                const item = this.element.ownerDocument.createElement('li');
                item.append(this.span('card-detail-ability', `${KEYWORD_ICONS[keyword]} сейчас`), this.span('card-detail-ability-text', KEYWORD_HINTS[keyword]));
                lines.append(item);
            }
        }
        if (lines.children.length > 0) {
            detail.append(lines);
        }

        if (info.description) {
            detail.append(this.span('card-detail-description', info.description));
        }
        const status = this.cardStatus(card);
        if (status) {
            detail.append(this.span('card-detail-status', status));
        }

        detail.hidden = false;
        this.placeDetail(element);
    }

    hideDetail(event = null) {
        if (event && event.relatedTarget && this.detailFor?.contains(event.relatedTarget)) {
            return;
        }
        this.detailFor = null;
        this.detailTarget.hidden = true;
    }

    /**
     * Рядом с картой — справа, если помещается, иначе слева; не выходя за экран.
     */
    placeDetail(element) {
        const rect = element.getBoundingClientRect();
        const detail = this.detailTarget;
        const width = detail.offsetWidth;
        const height = detail.offsetHeight;
        const gap = 14;

        let left = rect.right + gap;
        if (left + width > window.innerWidth - 8) {
            left = rect.left - width - gap;
        }
        const top = Math.min(Math.max(8, rect.top + rect.height / 2 - height / 2), window.innerHeight - height - 8);

        detail.style.left = `${Math.max(8, left)}px`;
        detail.style.top = `${top}px`;
    }

    cardStatus(card) {
        if (!this.state || card.kind !== 'creature' || card.owner !== this.state.you) {
            return null;
        }
        const onBoard = this.me().board.some((boardCard) => boardCard?.id === card.id);
        if (!onBoard) {
            return null;
        }
        if (card.actedThisTurn) {
            return '💤 Уже действовало в этот ход';
        }
        if (card.summonedThisTurn && !card.keywords.includes('charge')) {
            return '💤 Только вышло — атакует со следующего хода';
        }
        return '✔ Готово атаковать';
    }

    // ─── Конец партии ────────────────────────────────────

    showResult() {
        this.resultShown = true;
        const won = this.state.winner === this.state.you;
        const me = this.me();
        const opponent = this.opponent();
        const fallen = (player) => player.graveyard.filter((card) => card.kind === 'creature').length;

        this.resultTarget.classList.toggle('is-victory', won);
        this.resultTitleTarget.textContent = won ? '🏆 Победа!' : 'Поражение';
        this.resultReasonTarget.textContent = this.surrendered !== null
            ? (this.surrendered === this.state.you ? 'Вы сдались.' : `${this.playerName(this.surrendered, true)} сдаётся.`)
            : (won ? 'Фортификация соперника пала.' : 'Ваша фортификация пала.');

        const stats = [
            ['Ходов', this.state.turn],
            ['Ваша фортификация', Math.max(0, me.fortification)],
            ['Фортификация соперника', Math.max(0, opponent.fortification)],
            ['Ваших существ погибло', fallen(me)],
            ['Существ соперника погибло', fallen(opponent)],
        ];
        this.resultStatsTarget.replaceChildren(...stats.flatMap(([label, value]) => {
            const term = this.element.ownerDocument.createElement('dt');
            term.textContent = label;
            const description = this.element.ownerDocument.createElement('dd');
            description.textContent = value;
            return [term, description];
        }));
        this.resultTarget.hidden = false;
    }

    closeResult() {
        this.resultTarget.hidden = true;
    }

    statusText() {
        if (this.state.winner !== null) {
            return this.state.winner === this.state.you ? '🏆 Победа!' : 'Поражение. Фортификация пала.';
        }
        if (this.playing) {
            return `Ход ${this.state.turn}: ${this.playerName(this.state.activePlayer, true)}…`;
        }
        return this.isMyTurn() ? `Ход ${this.state.turn}: ваш ход` : `Ход ${this.state.turn}: ходит ${this.playerName(this.state.activePlayer)}`;
    }

    hintText() {
        const selection = this.selection;
        if (this.playing) {
            return 'Идёт показ хода…';
        }
        if (!this.isMyTurn()) {
            return '';
        }
        if (selection?.type === 'attacker') {
            return 'Цель: существо противника, его фортификация или свободная точка.';
        }
        if (selection?.type === 'hand') {
            if (selection.card.kind === 'creature' && selection.cell === null) {
                return `«${selection.card.name}»: выберите свободную клетку.`;
            }
            if (selection.card.kind === 'item') {
                return `«${selection.card.name}»: выберите своё существо.`;
            }
            return `«${selection.card.name}»: выберите цель или нажмите «Без цели».`;
        }
        return 'Выберите карту в руке или существо, которое светится — оно готово атаковать.';
    }

    // ─── Стрелки и анимации ──────────────────────────────

    /**
     * Стрелка поверх поля: от элемента к элементу (или к точке экрана — для прицела).
     */
    drawArrow(from, to, kind) {
        if (!from || !to) {
            return;
        }
        const box = this.element.getBoundingClientRect();
        const center = (target) => {
            if (target instanceof Element) {
                const rect = target.getBoundingClientRect();
                return { x: rect.left + rect.width / 2 - box.left, y: rect.top + rect.height / 2 - box.top };
            }
            return { x: target.x - box.left, y: target.y - box.top };
        };
        const a = center(from);
        const b = center(to);

        // Дуга: изгиб в сторону от прямой — стрелки не сливаются с рамками клеток
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const bend = 0.18;
        const control = { x: (a.x + b.x) / 2 - dy * bend, y: (a.y + b.y) / 2 + dx * bend };

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M ${a.x} ${a.y} Q ${control.x} ${control.y} ${b.x} ${b.y}`);
        path.setAttribute('class', `arrow arrow--${kind}`);
        path.setAttribute('marker-end', `url(#arrowhead-${kind})`);

        this.arrowsTarget.setAttribute('width', box.width);
        this.arrowsTarget.setAttribute('height', box.height);
        this.arrowsTarget.append(path);
    }

    clearArrows() {
        this.arrowsTarget.querySelectorAll('path.arrow').forEach((path) => path.remove());
    }

    /**
     * Карты, которые погибнут, — тают на текущем поле. true — есть что показать.
     */
    fadeOutDying(events) {
        const dying = events.filter((event) => event.type === 'CreatureDied')
            .map((event) => this.cardElement(event.card))
            .filter(Boolean);
        dying.forEach((element) => element.classList.add('is-dying'));
        return dying.length > 0;
    }

    /**
     * После перерисовки: числа урона и лечения над картами и фортификацией, появление карт.
     */
    animate(events) {
        let drawn = 0;
        for (const event of events) {
            switch (event.type) {
                case 'CardDrawn':
                    this.animateDraw(event.player, event.card, this.element.querySelector(`[data-deck="${event.player}"]`), drawn++);
                    break;
                case 'CardCreated':
                    this.animateDraw(event.player, event.card, this.cardElement(event.source) || this.element.querySelector(`[data-deck="${event.player}"]`), drawn++);
                    break;
                case 'CreatureDamaged': this.floatOn(this.cardElement(event.card), `−${event.amount}`, 'damage'); break;
                case 'CreatureHealed': this.floatOn(this.cardElement(event.card), `+${event.amount}`, 'heal'); break;
                case 'CreatureBuffed': this.floatOn(this.cardElement(event.card), [event.attack ? `+${event.attack}⚔` : '', event.health ? `+${event.health}❤` : ''].join(' ').trim(), 'heal'); break;
                case 'FortificationDamaged': this.floatOn(this.fortificationElement(event.player), `−${event.amount}`, 'damage'); break;
                case 'FortificationRepaired': this.floatOn(this.fortificationElement(event.player), `+${event.amount}`, 'heal'); break;
                case 'CardPlayed': this.pulse(this.cardElement(event.card), 'is-arriving'); break;
                default: break;
            }
        }
    }

    /**
     * Карта прилетает в руку: своя — из колоды (или из Кузницы) на своё место в веере;
     * у соперника — рубашка к счётчику его руки (какая карта — не видно).
     * Только transform и opacity — без перерисовки страницы.
     */
    animateDraw(player, cardId, from, order) {
        if (!from) {
            return;
        }
        const delay = order * DRAW_STAGGER_MS;

        if (player === this.state.you && cardId) {
            const card = this.handTarget.querySelector(`[data-card="${cardId}"]`);
            if (!card) {
                return;
            }
            const source = from.getBoundingClientRect();
            const target = card.getBoundingClientRect();
            card.style.setProperty('--from-x', `${source.left + source.width / 2 - (target.left + target.width / 2)}px`);
            card.style.setProperty('--from-y', `${source.top + source.height / 2 - (target.top + target.height / 2)}px`);
            card.style.setProperty('--draw-delay', `${delay}ms`);
            card.classList.add('is-drawn');
            card.addEventListener('animationend', () => card.classList.remove('is-drawn'), { once: true });
            return;
        }

        // Соперник: рубашка летит от колоды к счётчику руки
        const to = this.element.querySelector(`[data-hand="${player}"]`);
        if (!to) {
            return;
        }
        const source = from.getBoundingClientRect();
        const target = to.getBoundingClientRect();
        const back = this.span('card-back-fly', '');
        back.style.left = `${source.left + source.width / 2}px`;
        back.style.top = `${source.top + source.height / 2}px`;
        back.style.setProperty('--to-x', `${target.left - source.left}px`);
        back.style.setProperty('--to-y', `${target.top - source.top}px`);
        back.style.setProperty('--draw-delay', `${delay}ms`);
        this.element.append(back);
        back.addEventListener('animationend', () => back.remove(), { once: true });
    }

    /**
     * Захват точки: существо переезжает из клетки на точку, на точке — вспышка цвета владельца и флажок.
     */
    animateCapture(event, from) {
        const card = this.cardElement(event.card);
        const point = card?.closest('.point');
        if (!card || !point) {
            return;
        }

        if (from) {
            const to = card.getBoundingClientRect();
            card.style.setProperty('--from-x', `${from.left - to.left}px`);
            card.style.setProperty('--from-y', `${from.top - to.top}px`);
            card.classList.add('is-capturing');
            card.addEventListener('animationend', () => card.classList.remove('is-capturing'), { once: true });
        }

        point.classList.add('is-captured');
        setTimeout(() => point.classList.remove('is-captured'), CAPTURE_MS);
    }

    floatOn(target, text, kind) {
        if (!target) {
            return;
        }
        const number = this.span(`float-number float-number--${kind}`, text);
        target.append(number);
        this.pulse(target, kind === 'damage' ? 'is-hit' : 'is-healed');
        setTimeout(() => number.remove(), FLOAT_MS);
    }

    pulse(target, className) {
        if (target) {
            target.classList.add(className);
            setTimeout(() => target.classList.remove(className), FLOAT_MS);
        }
    }

    banner(text) {
        this.bannerTarget.textContent = text;
        this.bannerTarget.hidden = false;
        this.bannerTarget.classList.remove('is-shown');
        void this.bannerTarget.offsetWidth;
        this.bannerTarget.classList.add('is-shown');
        clearTimeout(this.bannerTimer);
        this.bannerTimer = setTimeout(() => { this.bannerTarget.hidden = true; }, BANNER_MS);
    }

    // ─── Журнал: по ходам, бой — одной строкой ───────────

    /**
     * Карты по id — для журнала и показа (события ссылаются на карты по id).
     */
    collectCards(state) {
        const remember = (card) => {
            if (card && card.id) {
                this.cards[card.id] = card;
            }
        };
        state.capturePoints.forEach((point) => [point.building, point.holder].forEach(remember));
        state.players.forEach((player) => {
            [...(player.hand || []), ...player.board, ...player.graveyard, player.landscape].forEach(remember);
        });
    }

    /**
     * @param actor чей был ход; null — старт партии
     */
    logStep(step, actor) {
        const side = actor === null ? 'turn' : (actor === this.state.you ? 'me' : 'opponent');
        for (const { text, kind } of this.describeStep(step.events)) {
            const item = this.element.ownerDocument.createElement('li');
            item.className = kind === 'turn' ? 'log-turn' : `log-${side}`;
            item.textContent = text;
            this.logTarget.prepend(item);
        }
    }

    describeStep(events) {
        const name = (id) => `«${this.cards[id]?.name || 'карта'}»`;
        const lines = [];
        const damage = events.filter((event) => event.type === 'CreatureDamaged');
        const attack = events.find((event) => event.type === 'AttackDeclared');

        for (const event of events) {
            switch (event.type) {
                case 'TurnStarted':
                    lines.push({ kind: 'turn', text: `— Ход ${event.turn}: ${this.playerName(event.player, true)} —` });
                    break;
                case 'CardPlayed':
                    lines.push({ text: `${this.playerName(this.cards[event.card]?.owner, true)}: ${name(event.card)}${event.target ? ` → ${name(event.target)}` : ''}` });
                    break;
                case 'AttackDeclared': {
                    const hits = damage.map((hit) => `${name(hit.card)} −${hit.amount}`);
                    const face = events.find((item) => item.type === 'FortificationDamaged');
                    lines.push({
                        text: event.target
                            ? `⚔ ${name(event.attacker)} → ${name(event.target)}: ${hits.join(', ')}`
                            : `⚔ ${name(event.attacker)} → 🏰 ${face ? this.playerName(face.player) : ''}: −${face?.amount ?? 0}`,
                    });
                    break;
                }
                case 'CreatureDamaged':
                    if (!attack) {
                        lines.push({ text: `${name(event.card)} −${event.amount}` });
                    }
                    break;
                case 'CreatureHealed': lines.push({ text: `✚ ${name(event.card)} +${event.amount}` }); break;
                case 'CreatureBuffed':
                    lines.push({ text: `✨ ${name(event.card)}${event.attack ? ` +${event.attack} ⚔` : ''}${event.health ? ` +${event.health} ❤` : ''}` });
                    break;
                case 'CardCreated':
                    lines.push({ text: `🔨 ${event.source ? name(event.source) : 'Создана карта'}: ${event.card ? name(event.card) : 'предмет'} → ${this.playerName(event.player)}` });
                    break;
                case 'CreatureDied': lines.push({ text: `💀 ${name(event.card)} погибает` }); break;
                case 'FortificationDamaged':
                    if (!attack) {
                        lines.push({ text: `🏰 Фортификация (${this.playerName(event.player)}) −${event.amount}` });
                    }
                    break;
                case 'FortificationRepaired': lines.push({ text: `🏰 Фортификация (${this.playerName(event.player)}) +${event.amount}` }); break;
                case 'PointCaptured': lines.push({ text: `⚑ ${name(event.card)} занимает точку ${event.point + 1}` }); break;
                case 'LandscapeChanged': lines.push({ text: `🌄 Ландшафт: ${name(event.card)}` }); break;
                case 'LandscapeEnded': lines.push({ text: `🌄 ${name(event.card)} рассеялся` }); break;
                case 'ManaGained': lines.push({ text: `💎 ${this.playerName(event.player, true)}: +${event.amount} маны` }); break;
                case 'PlayerSurrendered':
                    this.surrendered = event.player;
                    lines.push({ text: `🏳 ${this.playerName(event.player, true)}: сдача` });
                    break;
                case 'GameWon': lines.push({ kind: 'turn', text: `🏆 Победа: ${this.playerName(event.winner)}` }); break;
                default: break;
            }
        }
        return lines;
    }

    // ─── Помощники ───────────────────────────────────────

    me() {
        return this.state.players[this.state.you];
    }

    opponent() {
        return this.state.players[1 - this.state.you];
    }

    isMyTurn() {
        return this.state !== null && !this.playing && this.state.winner === null && this.state.activePlayer === this.state.you;
    }

    /**
     * @param capital «Вы» в начале фразы, «вы» — в середине
     */
    playerName(index, capital = false) {
        if (index === this.state?.you) {
            return capital ? 'Вы' : 'вы';
        }
        return this.game?.players[index]?.nickname || 'соперник';
    }

    cardElement(id) {
        return this.element.querySelector(`[data-card="${id}"]`);
    }

    fortificationElement(player) {
        return this.element.querySelector(`.fortification[data-player="${player}"]`);
    }

    /**
     * Откуда «летит» карта игрока: своя рука или половина соперника.
     */
    handOf(player) {
        return player === this.state?.you ? this.handTarget : this.opponentTarget;
    }

    badge(text, title) {
        const element = this.span('badge-stat', text);
        element.title = title;
        return element;
    }

    placeholder(text) {
        return this.span('placeholder', text);
    }

    span(className, text) {
        const element = this.element.ownerDocument.createElement('span');
        element.className = className;
        element.textContent = text;
        return element;
    }

    toast(message) {
        this.toastTarget.textContent = message;
        this.toastTarget.hidden = false;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toastTarget.hidden = true; }, 4000);
    }
}
