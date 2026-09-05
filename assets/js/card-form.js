// assets/js/card-form.js

/**
 * Карточный конструктор - управление превью карты в реальном времени
 */
class CardFormPreview {
    constructor() {
        console.log('🏗️ CardFormPreview инициализирован');

        this.elements = this.getElements();

        // Проверяем, что элементы найдены
        if (!this.elements.cardForm) {
            console.warn('⚠️ Форма не найдена, пропускаем инициализацию');
            return;
        }

        console.log('📋 Элементы формы:');
        Object.keys(this.elements).forEach(key => {
            console.log(`  ${key}: ${!!this.elements[key]}`);
        });

        // Проверяем конкретные поля
        const fields = ['name', 'mana', 'description', 'type', 'race', 'rarity', 'attack', 'health', 'image'];
        const missing = fields.filter(f => !this.elements[f]);

        if (missing.length > 0) {
            console.warn(`⚠️ Не найдены поля: ${missing.join(', ')}`);
        }

        this.initRarityBorder();
        this.initEventListeners();
        this.initAbilities();

        console.log('✅ CardFormPreview готов');
    }

    /**
     * Получение всех DOM элементов
     */
    getElements() {
        return {
            // Форма
            cardForm: document.getElementById('cardForm'),

            // Инпуты (используем ID из шаблона)
            name: document.getElementById('inputName'),
            mana: document.getElementById('inputMana'),
            description: document.getElementById('inputDescription'),
            type: document.getElementById('inputType'),
            race: document.getElementById('inputRace'),
            rarity: document.getElementById('inputRarity'),
            attack: document.getElementById('inputAttack'),
            health: document.getElementById('inputHealth'),
            image: document.getElementById('inputImage'),

            // Превью
            previewName: document.getElementById('previewName'),
            previewMana: document.getElementById('previewMana'),
            previewDescription: document.getElementById('previewDescription'),
            previewType: document.getElementById('previewType'),
            previewRace: document.getElementById('previewRace'),
            previewRarity: document.getElementById('previewRarity'),
            previewAttack: document.getElementById('previewAttack'),
            previewHealth: document.getElementById('previewHealth'),
            previewManaStat: document.getElementById('previewManaStat'),
            previewImage: document.getElementById('previewImage'),
            previewPlaceholder: document.getElementById('previewPlaceholder'),
            previewPlaceholderName: document.getElementById('previewPlaceholderName'),
            cardPreview: document.getElementById('cardPreview'),
            uploadLabel: document.getElementById('uploadLabel')
        };
    }

    /**
     * Инициализация рамки редкости
     */
    initRarityBorder() {
        const { rarity, cardPreview, previewRarity } = this.elements;

        if (rarity && cardPreview && previewRarity) {
            const selected = rarity.options[rarity.selectedIndex];
            if (selected) {
                const slug = this.getRaritySlug(selected);
                cardPreview.className = 'card-preview rarity-' + slug;
                previewRarity.className = 'card-preview-rarity rarity-' + slug;
                console.log('🎨 Установлена редкость:', slug);
            }
        }
    }

    /**
     * Инициализация событий
     */
    initEventListeners() {
        const {
            name, mana, description, type, race, rarity,
            attack, health, image,
            previewName, previewMana, previewDescription,
            previewType, previewRace, previewRarity,
            previewAttack, previewHealth, previewManaStat,
            previewPlaceholderName, cardPreview,
            uploadLabel, previewImage, previewPlaceholder
        } = this.elements;

        // Название
        if (name && previewName) {
            console.log('✅ Привязано событие: Название');
            name.addEventListener('input', () => {
                const val = name.value || 'Название карты';
                previewName.textContent = val;
                if (previewPlaceholderName) previewPlaceholderName.textContent = val;
            });
        }

        // Мана
        if (mana && previewMana) {
            console.log('✅ Привязано событие: Мана');
            mana.addEventListener('input', () => {
                const val = mana.value || 0;
                previewMana.textContent = '✦ ' + val;
                if (previewManaStat) previewManaStat.textContent = val;
            });
        }

        // Описание
        if (description && previewDescription) {
            console.log('✅ Привязано событие: Описание');
            description.addEventListener('input', () => {
                previewDescription.textContent = description.value || 'Нет описания';
            });
        }

        // Тип
        if (type && previewType) {
            console.log('✅ Привязано событие: Тип');
            type.addEventListener('change', () => {
                const val = type.options[type.selectedIndex]?.text || '—';
                previewType.innerHTML = '<strong>Тип:</strong> ' + val;
            });
        }

        // Раса
        if (race && previewRace) {
            console.log('✅ Привязано событие: Раса');
            race.addEventListener('change', () => {
                const val = race.options[race.selectedIndex]?.text || '—';
                previewRace.innerHTML = '<strong>Раса:</strong> ' + val;
            });
        }

        // Редкость
        if (rarity && previewRarity && cardPreview) {
            console.log('✅ Привязано событие: Редкость');
            rarity.addEventListener('change', () => {
                const selected = rarity.options[rarity.selectedIndex];
                const slug = this.getRaritySlug(selected);
                const name = selected.text || 'Нет';

                cardPreview.className = 'card-preview rarity-' + slug;
                previewRarity.className = 'card-preview-rarity rarity-' + slug;
                previewRarity.textContent = name;
            });
        }

        // Атака
        if (attack && previewAttack) {
            console.log('✅ Привязано событие: Атака');
            attack.addEventListener('input', () => {
                previewAttack.textContent = attack.value || '—';
            });
        }

        // Здоровье
        if (health && previewHealth) {
            console.log('✅ Привязано событие: Здоровье');
            health.addEventListener('input', () => {
                previewHealth.textContent = health.value || '—';
            });
        }

        // Изображение
        if (image) {
            console.log('✅ Привязано событие: Изображение');
            image.addEventListener('change', () => {
                if (image.files && image.files[0]) {
                    if (uploadLabel) {
                        uploadLabel.textContent = '📄 ' + image.files[0].name;
                    }

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        if (previewImage) {
                            previewImage.src = e.target.result;
                            previewImage.style.display = 'block';
                            if (previewPlaceholder) previewPlaceholder.style.display = 'none';
                        }
                    };
                    reader.readAsDataURL(image.files[0]);
                } else {
                    if (uploadLabel) {
                        uploadLabel.textContent = '📁 Выберите файл';
                    }
                }
            });
        }
    }

    /**
     * Получение slug редкости
     */
    getRaritySlug(option) {
        if (option.dataset.slug) {
            return option.dataset.slug;
        }
        const name = option.text.toLowerCase().trim();
        const map = {
            'обычный': 'common',
            'common': 'common',
            'необычный': 'uncommon',
            'uncommon': 'uncommon',
            'редкий': 'rare',
            'rare': 'rare',
            'эпический': 'epic',
            'epic': 'epic',
            'легендарный': 'legendary',
            'legendary': 'legendary'
        };
        return map[name] || 'common';
    }

    /**
     * Инициализация способностей
     */
    initAbilities() {
        const checkboxes = document.querySelectorAll('.ability-input');
        console.log(`⚡ Способностей найдено: ${checkboxes.length}`);

        checkboxes.forEach((checkbox) => {
            const container = checkbox.closest('.ability-checkbox');
            const valueInput = container?.querySelector('.ability-value-input');

            if (valueInput) {
                valueInput.disabled = !checkbox.checked;

                checkbox.addEventListener('change', () => {
                    valueInput.disabled = !checkbox.checked;
                    if (!checkbox.checked) {
                        valueInput.value = '';
                    } else {
                        valueInput.focus();
                    }
                });
            }
        });
    }
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Поиск формы...');
    const form = document.getElementById('cardForm');

    if (form) {
        console.log('✅ Форма найдена, инициализация CardFormPreview');
        new CardFormPreview();
    } else {
        console.warn('⚠️ Форма #cardForm не найдена');
    }
});