const getFavoriteToastRegion = () => {
    let region = document.querySelector('[data-favorite-toast-region]');
    if (region) {
        return region;
    }

    region = document.createElement('div');
    region.className = 'favorite-toast-region';
    region.dataset.favoriteToastRegion = '';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    document.body.append(region);

    return region;
};

const showFavoriteToast = (message, loginUrl = null, isError = false) => {
    const region = getFavoriteToastRegion();
    const toast = document.createElement('div');
    toast.className = 'favorite-toast';
    toast.setAttribute('role', isError ? 'alert' : 'status');
    toast.append(document.createTextNode(message));

    if (loginUrl) {
        const loginLink = document.createElement('a');
        loginLink.href = loginUrl;
        loginLink.textContent = 'Se connecter';
        toast.append(loginLink);
    }

    region.append(toast);
    window.setTimeout(() => toast.remove(), 4500);
};

const getFavoriteButton = (target) => (
    target instanceof Element ? target.closest('[data-favorite-toggle]') : null
);

const updateFavoriteCount = (count) => {
    document.querySelectorAll('[data-favorites-count]').forEach((counter) => {
        counter.textContent = String(count);
    });
};

const handleFavoriteClick = async (event) => {
    const button = getFavoriteButton(event.target);
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (button.dataset.authenticated !== 'true') {
        showFavoriteToast(
            'Connectez-vous pour ajouter cette œuvre à vos favoris.',
            button.dataset.loginUrl,
        );
        return;
    }

    if (button.disabled) {
        return;
    }

    button.disabled = true;
    try {
        const response = await fetch(button.dataset.favoriteUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({ _token: button.dataset.favoriteToken }),
        });

        if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
            showFavoriteToast(
                'Votre session a expiré. Connectez-vous pour gérer vos favoris.',
                button.dataset.loginUrl,
                true,
            );
            return;
        }

        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.error || 'Impossible de mettre à jour vos favoris.');
        }

        button.dataset.favorited = result.favorite ? 'true' : 'false';
        button.classList.toggle('is-favorite', result.favorite);
        button.setAttribute('aria-pressed', result.favorite ? 'true' : 'false');
        const label = result.favorite ? 'Retirer des favoris' : 'Ajouter aux favoris';
        button.setAttribute('aria-label', label);
        const icon = button.querySelector('.favorite-button-icon');
        if (icon) {
            icon.textContent = result.favorite ? '♥' : '♡';
        }
        const screenReaderLabel = button.querySelector('[data-favorite-label]');
        if (screenReaderLabel) {
            screenReaderLabel.textContent = label;
        }
        updateFavoriteCount(result.count);
        showFavoriteToast(result.message);

        const favoritesContainer = button.closest('#favorite-artworks');
        if (favoritesContainer && !result.favorite) {
            button.closest('.art-card')?.remove();
            if (!favoritesContainer.querySelector('.art-card')) {
                favoritesContainer.querySelector('[data-favorites-empty]')?.removeAttribute('hidden');
            }
        }
    } catch (error) {
        console.error('La mise à jour du favori a échoué.', error);
        showFavoriteToast(
            error instanceof Error ? error.message : 'Impossible de mettre à jour vos favoris.',
            null,
            true,
        );
    } finally {
        button.disabled = false;
    }
};

['pointerdown', 'pointerup'].forEach((eventName) => {
    document.addEventListener(eventName, (event) => {
        if (getFavoriteButton(event.target)) {
            event.stopPropagation();
        }
    }, true);
});

document.addEventListener('click', (event) => {
    if (getFavoriteButton(event.target)) {
        void handleFavoriteClick(event);
    }
}, true);
