const initGlobalFilters = () => {
    const backdrop = document.getElementById('global-filter-backdrop');
    const openButton = document.getElementById('open-global-filters');
    const closeButton = document.getElementById('close-global-filters');

    if (!backdrop || !openButton || !closeButton || backdrop.dataset.initialized === 'true') {
        return;
    }
    backdrop.dataset.initialized = 'true';

    const close = () => {
        backdrop.classList.remove('is-open');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('filter-drawer-open');
    };

    openButton.addEventListener('click', () => {
        backdrop.classList.add('is-open');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.classList.add('filter-drawer-open');
        document.getElementById('global-filter-q')?.focus();
    });
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) {
            close();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && backdrop.classList.contains('is-open')) {
            close();
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGlobalFilters, { once: true });
} else {
    initGlobalFilters();
}
document.addEventListener('turbo:load', initGlobalFilters);
