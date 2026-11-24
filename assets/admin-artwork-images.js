const maximumImages = 5;
const initializedCollections = new WeakSet();

const initializeArtworkImageCollections = () => {
    document.querySelectorAll('[data-artwork-image-collection]').forEach((collectionRow) => {
        if (initializedCollections.has(collectionRow)) {
            return;
        }

        const collection = collectionRow.querySelector('.artwork-images-list');
        const addButton = collectionRow.querySelector('[data-artwork-image-add]');
        if (!(collection instanceof HTMLDivElement)) {
            return;
        }
        if (!(addButton instanceof HTMLButtonElement)) {
            return;
        }
        initializedCollections.add(collectionRow);

        const refreshCollection = () => {
            const items = Array.from(collection.querySelectorAll('[data-artwork-image-item]'));
            items.forEach((item, index) => {
                const orderInput = item.querySelector('[data-artwork-image-order]');
                const upButton = item.querySelector('[data-artwork-image-up]');
                const downButton = item.querySelector('[data-artwork-image-down]');
                if (orderInput instanceof HTMLInputElement) {
                    orderInput.value = String(index);
                }
                if (upButton instanceof HTMLButtonElement) {
                    upButton.disabled = index === 0;
                }
                if (downButton instanceof HTMLButtonElement) {
                    downButton.disabled = index === items.length - 1;
                }
            });

            addButton.disabled = items.length >= maximumImages;
        };

        const attachItemEvents = (item) => {
            const fileInput = item.querySelector('[data-artwork-image-file]');
            const preview = item.querySelector('.artwork-image-preview');
            const primaryInput = item.querySelector('[data-artwork-image-primary]');

            if (fileInput instanceof HTMLInputElement && preview instanceof HTMLImageElement) {
                fileInput.addEventListener('change', () => {
                    const file = fileInput.files?.[0];
                    if (!file) {
                        return;
                    }

                    preview.src = URL.createObjectURL(file);
                    preview.alt = file.name;
                    preview.hidden = false;
                });
            }

            if (primaryInput instanceof HTMLInputElement) {
                primaryInput.addEventListener('change', () => {
                    if (!primaryInput.checked) {
                        return;
                    }

                    collection.querySelectorAll('[data-artwork-image-primary]').forEach((otherInput) => {
                        if (otherInput !== primaryInput && otherInput instanceof HTMLInputElement) {
                            otherInput.checked = false;
                        }
                    });
                });
            }

            item.querySelector('[data-artwork-image-remove]')?.addEventListener('click', () => {
                item.remove();
                refreshCollection();
            });

            item.querySelector('[data-artwork-image-up]')?.addEventListener('click', () => {
                const previous = item.previousElementSibling;
                if (previous) {
                    collection.insertBefore(item, previous);
                    refreshCollection();
                }
            });

            item.querySelector('[data-artwork-image-down]')?.addEventListener('click', () => {
                const next = item.nextElementSibling;
                if (next) {
                    collection.insertBefore(next, item);
                    refreshCollection();
                }
            });
        };

        collection.querySelectorAll('[data-artwork-image-item]').forEach(attachItemEvents);
        const primaryInputs = Array.from(collection.querySelectorAll('[data-artwork-image-primary]'));
        const selectedPrimary = primaryInputs.find((input) => input instanceof HTMLInputElement && input.checked) ?? primaryInputs[0];
        if (selectedPrimary instanceof HTMLInputElement) {
            primaryInputs.forEach((input) => {
                if (input instanceof HTMLInputElement) {
                    input.checked = input === selectedPrimary;
                }
            });
        }

        addButton.addEventListener('click', () => {
            const items = collection.querySelectorAll('[data-artwork-image-item]');
            if (items.length >= maximumImages) {
                return;
            }

            const index = Number.parseInt(collection.dataset.index ?? '0', 10);
            const prototype = collectionRow.dataset.prototype;
            if (!prototype || !Number.isFinite(index)) {
                throw new Error('Impossible de créer un champ image : prototype de formulaire absent ou invalide.');
            }

            const prototypeTemplate = document.createElement('template');
            prototypeTemplate.innerHTML = prototype.replaceAll('__name__', String(index));
            const item = prototypeTemplate.content.firstElementChild;
            if (!(item instanceof HTMLDivElement) || !item.matches('[data-artwork-image-item]')) {
                throw new Error('Le prototype du formulaire ne contient pas une entrée image valide.');
            }
            collection.dataset.index = String(index + 1);

            const primaryInput = item.querySelector('[data-artwork-image-primary]');
            if (primaryInput instanceof HTMLInputElement && items.length === 0) {
                primaryInput.checked = true;
            }

            collection.append(item);
            attachItemEvents(item);
            refreshCollection();
        });

        refreshCollection();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeArtworkImageCollections, { once: true });
} else {
    initializeArtworkImageCollections();
}

document.addEventListener('turbo:load', initializeArtworkImageCollections);
