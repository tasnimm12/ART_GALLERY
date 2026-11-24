const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

if ('IntersectionObserver' in window && !reducedMotion.matches) {
    const observedElements = new WeakSet();
    const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('anim-visible');
                currentObserver.unobserve(entry.target);
            }
        });
    }, {
        rootMargin: '0px 0px -40px 0px',
        threshold: 0.05,
    });

    const observeRevealElements = () => {
        document.documentElement.classList.add('anim-ready');

        document.querySelectorAll('.anim-reveal').forEach((element) => {
            if (!observedElements.has(element)) {
                observedElements.add(element);
                observer.observe(element);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeRevealElements, { once: true });
    } else {
        observeRevealElements();
    }

    document.addEventListener('aag:content-updated', observeRevealElements);
    document.addEventListener('turbo:load', observeRevealElements);
}
