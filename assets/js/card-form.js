// assets/js/card-form.js

(function() {
    'use strict';

    function initCardForm() {
        const elements = {
            form: document.getElementById('cardForm'),

            name: document.getElementById('cards_name'),
            mana: document.getElementById('cards_manaCost'),
            description: document.getElementById('cards_description'),
            type: document.getElementById('cards_cardType'),
            race: document.getElementById('cards_race'),
            rarity: document.getElementById('cards_rarity'),
            attack: document.getElementById('cards_attack'),
            health: document.getElementById('cards_health'),
            image: document.getElementById('cards_imageFile'),

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
            uploadLabel: document.getElementById('uploadLabel'),
            previewContainer: document.querySelector('.card-preview-image')
        };

        if (!elements.form) return;

        function getRaritySlug(option) {
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

        // Редкость
        if (elements.rarity && elements.cardPreview && elements.previewRarity) {
            const updateRarity = function() {
                const selected = elements.rarity.options[elements.rarity.selectedIndex];
                if (selected) {
                    const slug = getRaritySlug(selected);
                    elements.cardPreview.className = 'card-preview rarity-' + slug;
                    elements.previewRarity.className = 'card-preview-rarity rarity-' + slug;
                    elements.previewRarity.textContent = selected.text || 'Нет';
                }
            };

            updateRarity();
            elements.rarity.addEventListener('change', updateRarity);
        }

        // Live Preview
        if (elements.name && elements.previewName) {
            elements.name.addEventListener('input', function() {
                const val = this.value || 'Название карты';
                elements.previewName.textContent = val;
                if (elements.previewPlaceholderName) {
                    elements.previewPlaceholderName.textContent = val;
                }
            });
        }

        if (elements.mana && elements.previewMana) {
            elements.mana.addEventListener('input', function() {
                const val = this.value || 0;
                elements.previewMana.textContent = '✦ ' + val;
                if (elements.previewManaStat) {
                    elements.previewManaStat.textContent = val;
                }
            });
        }

        if (elements.description && elements.previewDescription) {
            elements.description.addEventListener('input', function() {
                elements.previewDescription.textContent = this.value || 'Нет описания';
            });
        }

        if (elements.type && elements.previewType) {
            elements.type.addEventListener('change', function() {
                const val = this.options[this.selectedIndex]?.text || '—';
                elements.previewType.innerHTML = '<strong>Тип:</strong> ' + val;
            });
        }

        if (elements.race && elements.previewRace) {
            elements.race.addEventListener('change', function() {
                const val = this.options[this.selectedIndex]?.text || '—';
                elements.previewRace.innerHTML = '<strong>Раса:</strong> ' + val;
            });
        }

        if (elements.attack && elements.previewAttack) {
            elements.attack.addEventListener('input', function() {
                elements.previewAttack.textContent = this.value || '—';
            });
        }

        if (elements.health && elements.previewHealth) {
            elements.health.addEventListener('input', function() {
                elements.previewHealth.textContent = this.value || '—';
            });
        }

        // Загрузка изображения
        if (elements.image) {
            elements.image.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];

                    if (elements.uploadLabel) {
                        elements.uploadLabel.textContent = '📄 ' + file.name;
                    }

                    const reader = new FileReader();
                    reader.onload = function(event) {
                        if (elements.previewImage) {
                            elements.previewImage.src = event.target.result;
                            elements.previewImage.style.display = 'block';
                            if (elements.previewPlaceholder) {
                                elements.previewPlaceholder.style.display = 'none';
                            }
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    if (elements.uploadLabel) {
                        elements.uploadLabel.textContent = '📁 Выберите файл';
                    }
                }
            });
        }

        // Клик по превью
        if (elements.previewContainer && elements.image) {
            elements.previewContainer.addEventListener('click', function(e) {
                if (e.target.closest('.btn') || e.target.closest('a')) return;
                elements.image.click();
            });
            elements.previewContainer.style.cursor = 'pointer';
            elements.previewContainer.title = 'Нажмите чтобы выбрать изображение';
        }

        // Способности
        document.querySelectorAll('.ability-input').forEach(function(checkbox) {
            const container = checkbox.closest('.ability-checkbox');
            const valueInput = container?.querySelector('.ability-value-input');

            if (valueInput) {
                valueInput.disabled = !checkbox.checked;

                checkbox.addEventListener('change', function() {
                    valueInput.disabled = !this.checked;
                    if (!this.checked) {
                        valueInput.value = '';
                    }
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCardForm);
    } else {
        initCardForm();
    }

})();