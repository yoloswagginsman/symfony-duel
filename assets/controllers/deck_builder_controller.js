import { Controller } from '@hotwired/stimulus';

/*
 * Конструктор колоды: клик по карте — +1 копия, в списке колоды — «−»/«+».
 * Подсказывает лимиты (размер колоды, копии, легендарные), но правила проверяет сервер при сохранении.
 * Выбор уходит в форму скрытыми полями cards[<id карты>] = <копий>.
 */
export default class extends Controller {
    static targets = ['card', 'search', 'race', 'type', 'total', 'curve', 'list', 'inputs', 'save'];

    static values = {
        quantities: Object,
        deckSize: Number,
        maxCopies: Number,
        maxLegendary: Number,
    };

    connect() {
        // Карты пула — из разметки (data-*): id => описание
        this.cards = {};
        for (const element of this.cardTargets) {
            this.cards[element.dataset.cardId] = {
                element,
                id: element.dataset.cardId,
                name: element.dataset.name,
                mana: Number(element.dataset.mana),
                legendary: element.dataset.legendary === '1',
            };
        }

        this.quantities = {};
        for (const [id, quantity] of Object.entries(this.quantitiesValue)) {
            if (this.cards[id]) {
                this.quantities[id] = quantity;
            }
        }
        this.render();
    }

    add(event) {
        const id = event.currentTarget.dataset.cardId;
        const card = this.cards[id];
        const quantity = this.quantities[id] || 0;

        if (quantity >= this.limit(card) || this.total() >= this.deckSizeValue) {
            this.flash(card.element);
            return;
        }

        this.quantities[id] = quantity + 1;
        this.render();
    }

    remove(event) {
        const id = event.currentTarget.dataset.cardId;
        this.quantities[id] -= 1;
        if (this.quantities[id] <= 0) {
            delete this.quantities[id];
        }
        this.render();
    }

    filter() {
        const search = this.searchTarget.value.trim().toLowerCase();
        const race = this.raceTarget.value;
        const type = this.typeTarget.value;

        for (const element of this.cardTargets) {
            element.hidden = (search && !element.dataset.name.toLowerCase().includes(search))
                || (race && element.dataset.race !== race)
                || (type && element.dataset.type !== type);
        }
    }

    render() {
        const total = this.total();
        this.totalTarget.textContent = total;
        this.totalTarget.classList.toggle('is-complete', total === this.deckSizeValue);

        // Счётчик копий на картах пула
        for (const card of Object.values(this.cards)) {
            const quantity = this.quantities[card.id] || 0;
            card.element.querySelector('[data-count]').textContent = quantity ? `×${quantity}` : '';
            card.element.classList.toggle('is-selected', quantity > 0);
            card.element.classList.toggle('is-disabled', quantity >= this.limit(card));
        }

        this.renderList();
        this.renderCurve();
        this.renderInputs();
    }

    renderList() {
        const rows = this.selected().map((card) => {
            const row = document.createElement('li');
            row.className = 'builder-row';

            const mana = document.createElement('span');
            mana.className = 'builder-row-mana';
            mana.textContent = card.mana;

            const name = document.createElement('span');
            name.className = 'builder-row-name';
            name.textContent = card.name;

            const minus = this.button('−', 'remove', card.id, `Убрать «${card.name}»`);
            const count = document.createElement('b');
            count.textContent = `×${this.quantities[card.id]}`;
            const plus = this.button('+', 'add', card.id, `Добавить «${card.name}»`);

            row.append(mana, name, minus, count, plus);
            return row;
        });

        this.listTarget.replaceChildren(...rows);
    }

    /**
     * Кривая маны: сколько карт каждой стоимости (7+ — вместе).
     */
    renderCurve() {
        const buckets = Array(8).fill(0);
        for (const card of this.selected()) {
            buckets[Math.min(card.mana, 7)] += this.quantities[card.id];
        }
        const highest = Math.max(1, ...buckets);

        const bars = buckets.map((count, mana) => {
            const bar = document.createElement('div');
            bar.className = 'builder-curve-bar';
            bar.style.setProperty('--height', `${(count / highest) * 100}%`);
            bar.title = `${mana === 7 ? '7+' : mana} маны: ${count}`;
            bar.dataset.label = mana === 7 ? '7+' : mana;
            bar.dataset.count = count || '';
            return bar;
        });
        this.curveTarget.replaceChildren(...bars);
    }

    renderInputs() {
        const inputs = Object.entries(this.quantities).map(([id, quantity]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `cards[${id}]`;
            input.value = quantity;
            return input;
        });
        this.inputsTarget.replaceChildren(...inputs);
    }

    selected() {
        return Object.keys(this.quantities)
            .map((id) => this.cards[id])
            .sort((a, b) => a.mana - b.mana || a.name.localeCompare(b.name, 'ru'));
    }

    total() {
        return Object.values(this.quantities).reduce((sum, quantity) => sum + quantity, 0);
    }

    limit(card) {
        return card.legendary ? this.maxLegendaryValue : this.maxCopiesValue;
    }

    button(text, action, cardId, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'builder-row-button';
        button.textContent = text;
        button.dataset.action = `deck-builder#${action}`;
        button.dataset.cardId = cardId;
        button.setAttribute('aria-label', label);
        return button;
    }

    flash(element) {
        element.classList.remove('is-denied');
        void element.offsetWidth;
        element.classList.add('is-denied');
    }
}
