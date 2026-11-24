const galleryReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const initializedArtworkGalleries = new WeakSet();
const artworkGalleryControls = new WeakMap();
const galleryTransitionDuration = 650;

document.addEventListener('keydown', (event) => {
    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
        return;
    }

    const target = event.target;
    if (
        target instanceof HTMLInputElement
        || target instanceof HTMLTextAreaElement
        || target instanceof HTMLSelectElement
        || (target instanceof HTMLElement && target.isContentEditable)
    ) {
        return;
    }

    const activeElement = document.activeElement;
    const activeGallery = Array.from(document.querySelectorAll('[data-artwork-gallery]')).find((gallery) => (
        gallery.contains(activeElement)
        || activeElement === document.body
        || activeElement === document.documentElement
    ));
    if (!activeGallery) {
        return;
    }

    const controls = artworkGalleryControls.get(activeGallery);
    if (!controls || controls.isLightboxOpen()) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        controls.showPrevious();
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        controls.showNext();
    }
});

const initializeArtworkGalleries = () => {
    document.querySelectorAll('[data-artwork-gallery]').forEach((gallery) => {
        if (initializedArtworkGalleries.has(gallery)) {
            return;
        }

        const stage = gallery.querySelector('[data-gallery-stage]');
        const mainImage = gallery.querySelector('[data-gallery-main]');
        const incomingImage = gallery.querySelector('[data-gallery-incoming]');
        const autoplayRegion = gallery.parentElement ?? gallery;
        const thumbnailContainer = gallery.parentElement?.querySelector('.gallery-thumbnails');
        const thumbnails = Array.from(thumbnailContainer?.querySelectorAll('[data-gallery-thumb]') ?? []);
        const lightbox = gallery.querySelector('[data-gallery-lightbox]');
        const lightboxImage = lightbox?.querySelector('[data-gallery-lightbox-image]');

        if (
            !(stage instanceof HTMLDivElement)
            || !(mainImage instanceof HTMLImageElement)
            || !(incomingImage instanceof HTMLImageElement)
            || thumbnails.length === 0
        ) {
            return;
        }

        initializedArtworkGalleries.add(gallery);

        const imageSource = (image) => image.dataset.gallerySrc || image.currentSrc || image.src;
        const primaryIndex = Number.parseInt(mainImage.dataset.galleryIndex ?? '', 10);
        const activeThumbnailIndex = thumbnails.findIndex((thumbnail) => thumbnail.getAttribute('aria-pressed') === 'true');
        let displayedIndex = Number.isInteger(primaryIndex) && primaryIndex >= 0 && primaryIndex < thumbnails.length
            ? primaryIndex
            : Math.max(0, activeThumbnailIndex);
        let selectedIndex = displayedIndex;
        const progressFill = gallery.querySelector('[data-gallery-progress-fill]');
        const autoplayInterval = 4000;
        let autoplayRemaining = autoplayInterval;
        let autoplayStartedAt = 0;
        let autoplayTimer = null;
        let progressAnimation = null;
        let requestId = 0;
        let transitionTimer;
        let closeTimer;
        let suppressClickUntil = 0;

        const syncAutoplay = () => {
            const shouldRun = thumbnails.length > 1
                && !galleryReducedMotion.matches
                && document.visibilityState === 'visible'
                && !autoplayRegion.matches(':hover')
                && !autoplayRegion.contains(document.activeElement);

            if (!shouldRun) {
                if (autoplayTimer !== null) {
                    window.clearTimeout(autoplayTimer);
                    autoplayTimer = null;
                    autoplayRemaining = Math.max(0, autoplayRemaining - (performance.now() - autoplayStartedAt));
                }
                progressAnimation?.pause();
                return;
            }

            if (autoplayTimer !== null) {
                return;
            }

            autoplayStartedAt = performance.now();
            if (progressFill instanceof HTMLElement) {
                if (progressAnimation && progressAnimation.playState === 'paused') {
                    progressAnimation.play();
                } else {
                    progressAnimation?.cancel();
                    progressAnimation = progressFill.animate(
                        [{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }],
                        { duration: autoplayRemaining, easing: 'linear', fill: 'forwards' },
                    );
                }
            }
            autoplayTimer = window.setTimeout(() => {
                autoplayTimer = null;
                autoplayRemaining = autoplayInterval;
                progressAnimation?.cancel();
                progressAnimation = null;
                showNext();
            }, autoplayRemaining);
        };

        const resetAutoplay = () => {
            if (autoplayTimer !== null) {
                window.clearTimeout(autoplayTimer);
                autoplayTimer = null;
            }
            progressAnimation?.cancel();
            progressAnimation = null;
            autoplayRemaining = autoplayInterval;
            syncAutoplay();
        };

        const setSelectedIndex = (index) => {
            selectedIndex = index;
            thumbnails.forEach((thumbnail, thumbnailIndex) => {
                thumbnail.setAttribute('aria-pressed', thumbnailIndex === index ? 'true' : 'false');
            });
            gallery.querySelectorAll('[data-gallery-page]').forEach((pageButton) => {
                pageButton.setAttribute('aria-pressed', Number(pageButton.dataset.galleryPage) === index ? 'true' : 'false');
            });
        };

        const updateLightboxImage = (source, alt, direction) => {
            if (!(lightboxImage instanceof HTMLImageElement)) {
                return;
            }

            lightboxImage.classList.remove('gallery-transition-next', 'gallery-transition-previous');
            void lightboxImage.offsetWidth;
            lightboxImage.src = source;
            lightboxImage.alt = alt;

            if (!galleryReducedMotion.matches) {
                requestAnimationFrame(() => {
                    lightboxImage.classList.add(direction > 0 ? 'gallery-transition-next' : 'gallery-transition-previous');
                    window.setTimeout(() => {
                        lightboxImage.classList.remove('gallery-transition-next', 'gallery-transition-previous');
                    }, galleryTransitionDuration);
                });
            }
        };

        const finishTransition = () => {
            window.clearTimeout(transitionTimer);
            if (!incomingImage.hasAttribute('src') || !incomingImage.getAttribute('src')) {
                return;
            }

            const finishedIndex = Number.parseInt(incomingImage.dataset.galleryIndex ?? '', 10);
            mainImage.src = incomingImage.src;
            mainImage.alt = incomingImage.alt;
            mainImage.dataset.galleryIndex = String(finishedIndex);
            mainImage.setAttribute('aria-label', `Agrandir l’image : ${incomingImage.alt}`);
            displayedIndex = finishedIndex;
            stage.classList.remove('gallery-transition-next', 'gallery-transition-previous');
            incomingImage.classList.remove('gallery-image-from-previous');
            incomingImage.removeAttribute('src');
            delete incomingImage.dataset.galleryIndex;
            incomingImage.alt = '';
        };

        const preloadImage = (source) => new Promise((resolve, reject) => {
            const preloadedImage = new Image();
            preloadedImage.onload = async () => {
                try {
                    if (typeof preloadedImage.decode === 'function') {
                        // Wait for decoding before revealing the incoming image.
                        await preloadedImage.decode();
                    }
                    resolve(preloadedImage);
                } catch (error) {
                    reject(error);
                }
            };
            preloadedImage.onerror = () => reject(new Error(`Impossible de charger l’image de la galerie : ${source}`));
            preloadedImage.src = source;
        });

        const preloadNextImage = () => {
            if (thumbnails.length < 2) {
                return;
            }

            const nextThumbnail = thumbnails[(displayedIndex + 1) % thumbnails.length];
            const preloadedImage = new Image();
            preloadedImage.src = imageSource(nextThumbnail);
        };

        const getDirection = (targetIndex) => {
            const forwardDistance = (targetIndex - selectedIndex + thumbnails.length) % thumbnails.length;
            const backwardDistance = (selectedIndex - targetIndex + thumbnails.length) % thumbnails.length;
            return forwardDistance <= backwardDistance ? 1 : -1;
        };

        const showImage = async (requestedIndex, requestedDirection = null) => {
            const targetIndex = (requestedIndex + thumbnails.length) % thumbnails.length;
            resetAutoplay();
            if (targetIndex === selectedIndex) {
                return;
            }

            if (incomingImage.hasAttribute('src') && incomingImage.getAttribute('src')) {
                finishTransition();
            }

            const direction = requestedDirection ?? getDirection(targetIndex);
            const targetThumbnail = thumbnails[targetIndex];
            const source = imageSource(targetThumbnail);
            const alt = targetThumbnail.alt;
            const currentRequestId = ++requestId;

            setSelectedIndex(targetIndex);

            let preloadedImage;
            try {
                preloadedImage = await preloadImage(source);
            } catch (error) {
                if (currentRequestId === requestId) {
                    if (incomingImage.hasAttribute('src') && incomingImage.getAttribute('src')) {
                        finishTransition();
                    }
                    setSelectedIndex(displayedIndex);
                    console.error(error);
                }
                return;
            }

            if (currentRequestId !== requestId) {
                return;
            }

            if (displayedIndex === targetIndex) {
                setSelectedIndex(displayedIndex);
                return;
            }

            if (galleryReducedMotion.matches) {
                mainImage.src = preloadedImage.src;
                mainImage.alt = alt;
                mainImage.dataset.galleryIndex = String(targetIndex);
                mainImage.setAttribute('aria-label', `Agrandir l’image : ${alt}`);
                displayedIndex = targetIndex;
                updateLightboxImage(preloadedImage.src, alt, direction);
                preloadNextImage();
                return;
            }

            if (incomingImage.hasAttribute('src') && incomingImage.getAttribute('src')) {
                finishTransition();
            }

            incomingImage.src = preloadedImage.src;
            incomingImage.alt = alt;
            incomingImage.dataset.galleryIndex = String(targetIndex);
            incomingImage.classList.toggle('gallery-image-from-previous', direction < 0);
            stage.classList.remove('gallery-transition-next', 'gallery-transition-previous');
            void incomingImage.offsetWidth;
            stage.classList.add(direction > 0 ? 'gallery-transition-next' : 'gallery-transition-previous');
            updateLightboxImage(preloadedImage.src, alt, direction);

            window.clearTimeout(transitionTimer);
            transitionTimer = window.setTimeout(() => {
                finishTransition();
                preloadNextImage();
            }, galleryTransitionDuration);
        };

        const showPrevious = () => showImage(selectedIndex - 1, -1);
        const showNext = () => showImage((selectedIndex + 1) % thumbnails.length, 1);

        thumbnails.forEach((thumbnail, index) => {
            thumbnail.dataset.gallerySrc = thumbnail.getAttribute('src') ?? '';
            thumbnail.addEventListener('click', () => showImage(index));
            thumbnail.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    showImage(index);
                }
            });
        });

        gallery.querySelector('[data-gallery-previous]')?.addEventListener('click', showPrevious);
        gallery.querySelector('[data-gallery-next]')?.addEventListener('click', showNext);
        gallery.querySelectorAll('[data-gallery-page]').forEach((pageButton) => {
            pageButton.addEventListener('click', () => {
                const targetIndex = Number(pageButton.dataset.galleryPage);
                showImage(targetIndex);
            });
        });

        const closeLightbox = () => {
            if (!(lightbox instanceof HTMLDialogElement) || !lightbox.open) {
                return;
            }

            lightbox.classList.add('gallery-lightbox-closing');
            window.clearTimeout(closeTimer);
            closeTimer = window.setTimeout(() => {
                lightbox.classList.remove('gallery-lightbox-closing');
                lightbox.close();
            }, galleryReducedMotion.matches ? 0 : 250);
        };

        const openLightbox = () => {
            if (!(lightbox instanceof HTMLDialogElement) || lightbox.open) {
                return;
            }

            resetAutoplay();
            if (incomingImage.hasAttribute('src') && incomingImage.getAttribute('src')) {
                finishTransition();
            }
            lightbox.classList.remove('gallery-lightbox-closing');
            if (lightboxImage instanceof HTMLImageElement) {
                lightboxImage.src = imageSource(mainImage);
                lightboxImage.alt = mainImage.alt;
            }
            lightbox.showModal();
            lightbox.querySelector('[data-gallery-close]')?.focus();
        };

        mainImage.addEventListener('click', (event) => {
            if (performance.now() < suppressClickUntil) {
                event.preventDefault();
                return;
            }
            openLightbox();
        });
        mainImage.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openLightbox();
            }
        });

        if (lightbox instanceof HTMLDialogElement) {
            lightbox.querySelector('[data-gallery-close]')?.addEventListener('click', closeLightbox);
            lightbox.querySelector('[data-lightbox-previous]')?.addEventListener('click', showPrevious);
            lightbox.querySelector('[data-lightbox-next]')?.addEventListener('click', showNext);

            lightbox.addEventListener('click', (event) => {
                if (event.target === lightbox) {
                    closeLightbox();
                }
            });

            lightbox.addEventListener('cancel', (event) => {
                event.preventDefault();
                closeLightbox();
            });

            lightbox.addEventListener('close', () => {
                window.clearTimeout(closeTimer);
                lightbox.classList.remove('gallery-lightbox-closing');
                mainImage.focus();
            });
        }

        let touchStartX = null;
        let touchPointerId = null;
        const attachSwipe = (element) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            element.addEventListener('pointerdown', (event) => {
                if (event.pointerType === 'touch') {
                    touchStartX = event.clientX;
                    touchPointerId = event.pointerId;
                }
            });

            element.addEventListener('pointerup', (event) => {
                if (event.pointerType !== 'touch' || event.pointerId !== touchPointerId || touchStartX === null) {
                    return;
                }

                const distance = event.clientX - touchStartX;
                touchStartX = null;
                touchPointerId = null;
                if (Math.abs(distance) >= 45) {
                    suppressClickUntil = performance.now() + 500;
                    distance > 0 ? showPrevious() : showNext();
                }
            });

            element.addEventListener('pointercancel', () => {
                touchStartX = null;
                touchPointerId = null;
            });
        };

        attachSwipe(stage);
        attachSwipe(lightboxImage);

        autoplayRegion.addEventListener('pointerenter', syncAutoplay);
        autoplayRegion.addEventListener('pointerleave', syncAutoplay);
        autoplayRegion.addEventListener('focusin', syncAutoplay);
        autoplayRegion.addEventListener('focusout', (event) => {
            if (!autoplayRegion.contains(event.relatedTarget)) {
                syncAutoplay();
            }
        });
        document.addEventListener('visibilitychange', syncAutoplay);
        galleryReducedMotion.addEventListener('change', () => {
            resetAutoplay();
        });

        artworkGalleryControls.set(gallery, {
            showPrevious,
            showNext,
            isLightboxOpen: () => lightbox instanceof HTMLDialogElement && lightbox.open,
        });
        setSelectedIndex(displayedIndex);
        preloadNextImage();
        syncAutoplay();
    });
};

const initializedArtworkCards = new WeakSet();
const cardHoverMedia = window.matchMedia('(hover: hover) and (pointer: fine)');
const cardReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const initializeArtworkCards = () => {
    document.querySelectorAll('[data-gallery-card]').forEach((card) => {
        if (!(card instanceof HTMLElement) || initializedArtworkCards.has(card)) {
            return;
        }

        const mainImage = card.querySelector('[data-gallery-card-main]');
        const incomingImage = card.querySelector('[data-gallery-card-incoming]');
        const rawImages = card.dataset.galleryCardImages;
        if (!(mainImage instanceof HTMLImageElement) || !(incomingImage instanceof HTMLImageElement) || !rawImages) {
            return;
        }

        let sources;
        try {
            sources = JSON.parse(rawImages);
        } catch (error) {
            console.error('Les sources d’images de cette carte sont invalides.', error);
            return;
        }
        if (!Array.isArray(sources) || sources.length < 1 || sources.length > 5
            || sources.some((image) => typeof image?.src !== 'string' || typeof image?.alt !== 'string')) {
            console.error('Une carte galerie doit contenir entre une et cinq sources d’images valides.');
            return;
        }

        initializedArtworkCards.add(card);
        const segments = Array.from(card.querySelectorAll('[data-gallery-card-segment]'));
        const interval = Math.max(300, Number.parseInt(card.dataset.galleryCardInterval ?? '', 10) || 1200);
        const loadedSources = new Map();
        const failedSources = new Set();
        let currentIndex = 0;
        let requestId = 0;
        let intervalTimer = null;
        let transitionTimer = null;
        let hovered = false;
        let visible = false;
        let touchStartX = null;

        const preloadSources = () => {
            // Card alternatives stay out of the network until hover or viewport entry.
            sources.forEach((image) => {
                if (loadedSources.has(image.src) || failedSources.has(image.src)) {
                    return;
                }

                const promise = new Promise((resolve, reject) => {
                    const preload = new Image();
                    preload.onload = async () => {
                        try {
                            if (typeof preload.decode === 'function') {
                                await preload.decode();
                            }
                            resolve(true);
                        } catch (error) {
                            reject(error);
                        }
                    };
                    preload.onerror = () => reject(new Error(`Impossible de précharger l’image de la carte : ${image.src}`));
                    preload.src = image.src;
                }).catch((error) => {
                    console.error(error);
                    loadedSources.delete(image.src);
                    failedSources.add(image.src);
                    return false;
                });
                loadedSources.set(image.src, promise);
            });
        };

        const selectSegment = (index) => {
            segments.forEach((segment, segmentIndex) => {
                segment.classList.toggle('gallery-card-segment-active', segmentIndex === index);
            });
        };

        const showCardImage = async (index) => {
            const targetIndex = (index + sources.length) % sources.length;
            if (targetIndex === currentIndex || sources.length < 2) {
                return;
            }

            const target = sources[targetIndex];
            const thisRequest = ++requestId;
            try {
                if (loadedSources.has(target.src)) {
                    if (!await loadedSources.get(target.src)) {
                        return;
                    }
                } else {
                    preloadSources();
                    if (!await loadedSources.get(target.src)) {
                        return;
                    }
                }
            } catch {
                return;
            }
            if (thisRequest !== requestId) {
                return;
            }

            window.clearTimeout(transitionTimer);
            incomingImage.src = target.src;
            incomingImage.alt = target.alt;
            selectSegment(targetIndex);
            currentIndex = targetIndex;
            card.classList.add('gallery-card-transition');
            transitionTimer = window.setTimeout(() => {
                mainImage.src = target.src;
                mainImage.alt = target.alt;
                incomingImage.removeAttribute('src');
                incomingImage.alt = '';
                card.classList.remove('gallery-card-transition');
            }, cardReducedMotion.matches ? 0 : 700);
        };

        const showNextCardImage = () => {
            let nextIndex = (currentIndex + 1) % sources.length;
            let checkedSources = 0;
            while (checkedSources < sources.length && failedSources.has(sources[nextIndex].src)) {
                nextIndex = (nextIndex + 1) % sources.length;
                checkedSources += 1;
            }
            if (checkedSources === sources.length) {
                return;
            }

            return showCardImage(nextIndex);
        };

        const stopAutoplay = () => {
            window.clearInterval(intervalTimer);
            intervalTimer = null;
        };

        const syncCardAutoplay = () => {
            const active = cardHoverMedia.matches ? hovered : visible;
            if (!active || cardReducedMotion.matches || document.visibilityState !== 'visible' || sources.length < 2) {
                stopAutoplay();
                return;
            }
            if (intervalTimer !== null) {
                return;
            }

            preloadSources();
            showNextCardImage();
            intervalTimer = window.setInterval(showNextCardImage, interval);
        };

        card.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse' && cardHoverMedia.matches) {
                hovered = true;
                preloadSources();
                syncCardAutoplay();
            }
        });
        card.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') {
                hovered = false;
                stopAutoplay();
                showCardImage(0);
            }
        });
        card.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'touch') {
                touchStartX = event.clientX;
                preloadSources();
            }
        });
        card.addEventListener('pointerup', (event) => {
            if (event.pointerType !== 'touch' || touchStartX === null || sources.length < 2) {
                return;
            }
            const distance = event.clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(distance) >= 45) {
                event.preventDefault();
                showCardImage(currentIndex + (distance < 0 ? 1 : -1));
            }
        });
        card.addEventListener('pointercancel', () => {
            touchStartX = null;
        });

        if (typeof IntersectionObserver === 'function') {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.target !== card) {
                        return;
                    }
                    visible = entry.isIntersecting;
                    if (visible && !cardHoverMedia.matches) {
                        preloadSources();
                    }
                    syncCardAutoplay();
                });
            }, { threshold: 0.5 });
            observer.observe(card);
        }

        document.addEventListener('visibilitychange', syncCardAutoplay);
        cardReducedMotion.addEventListener('change', syncCardAutoplay);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializeArtworkGalleries();
        initializeArtworkCards();
    }, { once: true });
} else {
    initializeArtworkGalleries();
    initializeArtworkCards();
}

document.addEventListener('turbo:load', () => {
    initializeArtworkGalleries();
    initializeArtworkCards();
});

const artworkCardObserver = new MutationObserver(() => initializeArtworkCards());
artworkCardObserver.observe(document.documentElement, { childList: true, subtree: true });
