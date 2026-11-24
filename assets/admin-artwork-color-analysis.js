const form = document.querySelector('form.artwork-form[data-color-analysis-url]');
const colorSelect = form?.querySelector('select[id$="_colors"]');
const analyzeButton = form?.querySelector('[data-analyze-uploaded-colors]');
const status = form?.querySelector('[data-color-analysis-status]');

if (
    form instanceof HTMLFormElement
    && colorSelect instanceof HTMLSelectElement
    && analyzeButton instanceof HTMLButtonElement
    && status instanceof HTMLElement
) {
    const getSelectedImage = () => {
        const imageCollection = form.querySelector('[data-artwork-image-collection]');
        const primaryInput = imageCollection?.querySelector('[data-artwork-image-primary]:checked');
        const primaryFile = primaryInput
            ?.closest('[data-artwork-image-item]')
            ?.querySelector('[data-artwork-image-file]');

        if (primaryFile instanceof HTMLInputElement && primaryFile.files?.[0]) {
            return primaryFile.files[0];
        }

        const galleryFile = imageCollection?.querySelector('[data-artwork-image-file]');
        if (galleryFile instanceof HTMLInputElement && galleryFile.files?.[0]) {
            return galleryFile.files[0];
        }

        const photoFile = form.querySelector('input[id$="_photo_file"], input[id$="_photo"]');
        return photoFile instanceof HTMLInputElement ? photoFile.files?.[0] ?? null : null;
    };

    form.addEventListener('change', (event) => {
        if (
            event.target instanceof HTMLInputElement
            && event.target.type === 'file'
            && getSelectedImage()
        ) {
            status.textContent = 'Image prête. Cliquez sur « Détecter les couleurs de l’image ».';
        }
    });

    analyzeButton.addEventListener('click', async () => {
        const image = getSelectedImage();
        if (!image) {
            status.textContent = 'Sélectionnez d’abord une image dans la section Média.';
            return;
        }

        const payload = new FormData();
        payload.append('image', image);
        payload.append('_token', form.dataset.colorAnalysisToken ?? '');
        analyzeButton.disabled = true;
        status.textContent = 'Analyse de l’image en cours…';

        try {
            const response = await fetch(form.dataset.colorAnalysisUrl ?? '', {
                method: 'POST',
                headers: {'Accept': 'application/json'},
                body: payload,
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error ?? 'La détection des couleurs a échoué.');
            }
            if (!Array.isArray(result.colors)) {
                throw new Error('La réponse de détection des couleurs est invalide.');
            }

            const colorIds = result.colors.map((color) => String(color.id));
            if (colorSelect.tomselect) {
                colorSelect.tomselect.setValue(colorIds);
            } else {
                Array.from(colorSelect.options).forEach((option) => {
                    option.selected = colorIds.includes(option.value);
                });
                colorSelect.dispatchEvent(new Event('change', {bubbles: true}));
            }

            const colorSummary = result.colors
                .map((color) => `${color.name} (${Math.round(color.percentage)} %)`)
                .join(', ');
            status.textContent = `Couleurs détectées et ajoutées : ${colorSummary}.`;
        } catch (error) {
            status.textContent = error instanceof Error
                ? error.message
                : 'Impossible de contacter le service de détection.';
        } finally {
            analyzeButton.disabled = false;
        }
    });
}
