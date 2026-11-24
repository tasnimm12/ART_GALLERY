let initializedWidget = null;
let widgetEventController = null;
let closeTimer;

const initializeAIChatWidget = () => {
    const widget = document.querySelector('[data-ai-chat-widget]');
    if (!widget || widget === initializedWidget) {
        return;
    }

    widgetEventController?.abort();
    window.clearTimeout(closeTimer);
    initializedWidget = widget;
    widget.dataset.aiChatInitialized = 'true';
    widgetEventController = new AbortController();
    const listenerOptions = {signal: widgetEventController.signal};
    const panel = widget.querySelector('[data-ai-chat-panel]');
    const openButtons = document.querySelectorAll('[data-ai-chat-open]');
    const closeButton = widget.querySelector('[data-ai-chat-close]');
    const form = widget.querySelector('[data-ai-chat-form]');
    const input = widget.querySelector('[data-ai-chat-input]');
    const sendButton = widget.querySelector('[data-ai-chat-send]');
    const conversation = widget.querySelector('[data-ai-chat-conversation]');
    const status = widget.querySelector('[data-ai-chat-status]');
    const errorBox = widget.querySelector('[data-ai-chat-error]');
    let lastOpenButton = widget.querySelector('[data-ai-chat-open]');
    let requestInFlight = false;

    const appendMessage = (speaker, message, type) => {
        const row = document.createElement('article');
        row.className = `ai-widget-message ai-widget-message--${type}`;

        const label = document.createElement('span');
        label.className = 'ai-widget-speaker';
        label.textContent = speaker;

        const text = document.createElement('p');
        text.textContent = message;

        row.append(label, text);
        conversation.append(row);
        conversation.scrollTop = conversation.scrollHeight;
    };

    const renderRecommendations = (recommendations) => {
        recommendations.forEach((recommendation) => {
            const card = document.createElement('article');
            card.className = 'ai-widget-recommendation';

            if (typeof recommendation.image === 'string' && recommendation.image !== '') {
                const image = document.createElement('img');
                image.src = recommendation.image;
                image.alt = recommendation.title || 'Œuvre recommandée';
                image.loading = 'lazy';
                card.append(image);
            }

            const details = document.createElement('div');
            details.className = 'ai-widget-recommendation-details';

            const title = document.createElement('strong');
            title.textContent = recommendation.title || 'Œuvre';
            details.append(title);

            if (recommendation.artist) {
                const artist = document.createElement('span');
                artist.textContent = recommendation.artist;
                details.append(artist);
            }

            const link = document.createElement('a');
            link.href = recommendation.url;
            link.textContent = 'Voir l’œuvre';
            details.append(link);
            card.append(details);
            conversation.append(card);
        });

        conversation.scrollTop = conversation.scrollHeight;
    };

    const openPanel = (button) => {
        window.clearTimeout(closeTimer);
        lastOpenButton = button;
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
        openButtons.forEach((openButton) => openButton.setAttribute('aria-expanded', 'true'));
        panel.classList.add('is-open');
        input.focus();
    };

    const closePanel = () => {
        if (panel.hidden || panel.getAttribute('aria-hidden') === 'true') {
            return;
        }

        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        openButtons.forEach((openButton) => openButton.setAttribute('aria-expanded', 'false'));
        window.clearTimeout(closeTimer);
        closeTimer = window.setTimeout(() => {
            if (panel.getAttribute('aria-hidden') === 'true') {
                panel.hidden = true;
                lastOpenButton?.focus();
            }
        }, 180);
    };

    openButtons.forEach((openButton) => openButton.addEventListener('click', () => {
        if (panel.getAttribute('aria-hidden') === 'true') {
            openPanel(openButton);
        } else {
            closePanel();
        }
    }, listenerOptions));

    closeButton.addEventListener('click', closePanel, listenerOptions);

    document.addEventListener('click', (event) => {
        const clickedOpenButton = [...openButtons].some((button) => button.contains(event.target));
        if (!panel.hidden && event.target instanceof Node && !widget.contains(event.target) && !clickedOpenButton) {
            closePanel();
        }
    }, listenerOptions);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            closePanel();
        }

        if (event.key === 'Enter' && !event.shiftKey && event.target === input) {
            event.preventDefault();
            form.requestSubmit();
        }
    }, listenerOptions);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (requestInFlight) {
            return;
        }

        const message = input.value.trim();
        if (message === '') {
            input.focus();
            return;
        }

        requestInFlight = true;
        errorBox.hidden = true;
        errorBox.textContent = '';
        status.textContent = 'L’assistant prépare sa réponse…';
        sendButton.disabled = true;
        input.disabled = true;
        appendMessage('Vous', message, 'user');
        input.value = '';
        const requestPayload = { message };
        if (form.dataset.artworkId) {
            requestPayload.artworkId = Number(form.dataset.artworkId);
        }

        const requestController = new AbortController();
        const timeoutId = window.setTimeout(() => requestController.abort(), 30000);

        try {
            const response = await fetch(form.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(requestPayload),
                signal: requestController.signal,
            });
            let payload;
            try {
                payload = await response.json();
            } catch {
                throw new Error('La réponse de l’assistant est invalide. Vous pouvez réessayer.');
            }

            if (!response.ok) {
                throw new Error(payload.error || 'L’assistant est momentanément indisponible.');
            }

            if (typeof payload.message !== 'string' || payload.message.trim() === '') {
                throw new Error('L’assistant a renvoyé une réponse vide. Vous pouvez réessayer.');
            }

            appendMessage('AI Art Assistant', payload.message, 'assistant');
            if (Array.isArray(payload.recommendations)) {
                renderRecommendations(payload.recommendations);
            }
            status.textContent = '';
        } catch (error) {
            status.textContent = '';
            errorBox.textContent = error instanceof Error
                ? (requestController.signal.aborted
                    ? 'La réponse prend trop de temps. Vérifiez votre connexion et réessayez.'
                    : error.message)
                : 'Une erreur est survenue pendant la demande.';
            errorBox.hidden = false;
        } finally {
            window.clearTimeout(timeoutId);
            requestInFlight = false;
            sendButton.disabled = false;
            input.disabled = false;
            if (!panel.hidden && panel.getAttribute('aria-hidden') === 'false') {
                input.focus();
            }
        }
    }, listenerOptions);
};

const teardownAIChatWidget = () => {
    widgetEventController?.abort();
    widgetEventController = null;
    window.clearTimeout(closeTimer);
    closeTimer = undefined;

    if (initializedWidget) {
        initializedWidget.removeAttribute('data-ai-chat-initialized');
    }
    initializedWidget = null;
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAIChatWidget, {once: true});
} else {
    initializeAIChatWidget();
}

document.addEventListener('turbo:load', initializeAIChatWidget);
document.addEventListener('turbo:before-cache', teardownAIChatWidget);
