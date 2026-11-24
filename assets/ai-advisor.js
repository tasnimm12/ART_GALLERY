const advisorRoot = document.querySelector('[data-ai-advisor]');

if (advisorRoot && advisorRoot.dataset.aiAdvisorInitialized !== 'true') {
    advisorRoot.dataset.aiAdvisorInitialized = 'true';

    const form = advisorRoot.querySelector('[data-advisor-form]');
    const input = advisorRoot.querySelector('[data-advisor-input]');
    const submitButton = advisorRoot.querySelector('[data-advisor-submit]');
    const status = advisorRoot.querySelector('[data-advisor-status]');
    const errorBox = advisorRoot.querySelector('[data-advisor-error]');
    const results = advisorRoot.querySelector('[data-advisor-results]');
    const cards = advisorRoot.querySelector('[data-advisor-cards]');
    const count = advisorRoot.querySelector('[data-advisor-count]');
    const empty = advisorRoot.querySelector('[data-advisor-empty]');
    const fallbackNotice = advisorRoot.querySelector('[data-advisor-fallback]');

    const makeElement = (tagName, className, text = null) => {
        const element = document.createElement(tagName);
        if (className) {
            element.className = className;
        }
        if (text !== null) {
            element.textContent = text;
        }
        return element;
    };

    const renderRecommendation = (recommendation) => {
        const card = makeElement('article', 'advisor-card');
        const visual = makeElement('div', 'advisor-card-visual');
        if (typeof recommendation.image === 'string' && recommendation.image !== '') {
            const image = makeElement('img');
            image.src = recommendation.image;
            image.alt = recommendation.title || 'Œuvre recommandée';
            image.loading = 'lazy';
            image.decoding = 'async';
            visual.append(image);
        }

        const favorite = recommendation.favorite;
        if (favorite && typeof favorite.url === 'string') {
            const button = makeElement('button', `favorite-button favorite-button--card${favorite.isFavorite ? ' is-favorite' : ''}`);
            button.type = 'button';
            button.setAttribute('aria-label', favorite.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris');
            button.setAttribute('aria-pressed', favorite.isFavorite ? 'true' : 'false');
            button.dataset.favoriteToggle = '';
            button.dataset.favoriteUrl = favorite.url;
            button.dataset.favoriteToken = favorite.token || '';
            button.dataset.loginUrl = favorite.loginUrl || '';
            button.dataset.favorited = favorite.isFavorite ? 'true' : 'false';
            button.dataset.authenticated = favorite.authenticated ? 'true' : 'false';
            button.append(makeElement('span', 'favorite-button-icon', favorite.isFavorite ? '♥' : '♡'));
            button.append(makeElement('span', 'sr-only', favorite.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'));
            button.lastElementChild.dataset.favoriteLabel = '';
            visual.append(button);
        }

        visual.append(makeElement('span', 'advisor-card-score', `${recommendation.score}% compatible`));
        card.append(visual);

        const body = makeElement('div', 'advisor-card-body');
        if (recommendation.category) {
            body.append(makeElement('div', 'advisor-card-category', recommendation.category));
        }
        body.append(makeElement('h3', 'advisor-card-title', recommendation.title || 'Œuvre'));
        if (recommendation.artist) {
            body.append(makeElement('p', 'advisor-card-artist', recommendation.artist));
        }

        if (Array.isArray(recommendation.colors) && recommendation.colors.length > 0) {
            const palette = makeElement('div', 'advisor-card-palette');
            recommendation.colors.forEach((color) => {
                const chip = makeElement('span', 'advisor-color-chip');
                const swatch = makeElement('i');
                if (typeof color.hex === 'string' && /^#[0-9a-f]{6}$/i.test(color.hex)) {
                    swatch.style.backgroundColor = color.hex;
                }
                swatch.setAttribute('aria-hidden', 'true');
                chip.append(swatch);
                chip.append(document.createTextNode(`${color.name} ${Math.round(color.percentage)}%`));
                palette.append(chip);
            });
            body.append(palette);
        }

        if (Array.isArray(recommendation.reasons) && recommendation.reasons.length > 0) {
            const reasonList = makeElement('ul', 'advisor-card-reasons');
            recommendation.reasons.forEach((reason) => reasonList.append(makeElement('li', '', reason)));
            body.append(reasonList);
        }

        const footer = makeElement('div', 'advisor-card-footer');
        const price = recommendation.price === null
            ? 'Prix sur demande'
            : `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(recommendation.price)} ${recommendation.currency || 'EUR'}`;
        footer.append(makeElement('span', 'advisor-card-price', price));
        if (typeof recommendation.url === 'string') {
            const link = makeElement('a', 'advisor-card-action', 'Voir l’œuvre →');
            link.href = recommendation.url;
            footer.append(link);
        }
        body.append(footer);
        card.append(body);

        return card;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (message === '') {
            input.focus();
            return;
        }

        errorBox.hidden = true;
        errorBox.textContent = '';
        results.hidden = true;
        status.textContent = 'Analyse de votre demande et recherche dans la collection…';
        submitButton.disabled = true;

        try {
            const response = await fetch(advisorRoot.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message }),
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.error || 'La recherche n’a pas pu aboutir.');
            }

            const recommendations = Array.isArray(payload.recommendations) ? payload.recommendations : [];
            cards.replaceChildren(...recommendations.map(renderRecommendation));
            count.textContent = `${recommendations.length} œuvre${recommendations.length > 1 ? 's' : ''}`;
            empty.hidden = recommendations.length > 0;
            fallbackNotice.hidden = payload.source !== 'fallback';
            results.hidden = false;
            status.textContent = recommendations.length > 0
                ? 'Votre sélection personnalisée est prête.'
                : 'Recherche terminée.';
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            status.textContent = '';
            errorBox.textContent = error instanceof Error ? error.message : 'Une erreur est survenue pendant la recherche.';
            errorBox.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });

    advisorRoot.querySelectorAll('[data-advisor-suggestion]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.advisorSuggestion || '';
            input.focus();
        });
    });
}
