const ADMIN_ERROR_CLASS = 'admin-field-error';

function getFieldMessage(field) {
    if (field.hasAttribute('data-error-message')) {
        return field.getAttribute('data-error-message');
    }

    const type = field.type;
    if (field.required && (field.tagName === 'SELECT' || field.tagName === 'TEXTAREA' || type === 'text' || type === 'email' || type === 'number' || type === 'tel')) {
        return 'Ce champ est obligatoire.';
    }

    if (type === 'email') {
        return 'Veuillez saisir une adresse e-mail valide.';
    }

    if (type === 'number') {
        return 'Veuillez saisir une valeur numérique valide.';
    }

    return 'Cette valeur est invalide.';
}

function ensureErrorNode(field) {
    let node = field.parentElement.querySelector('.' + ADMIN_ERROR_CLASS);
    if (!node) {
        node = document.createElement('div');
        node.className = ADMIN_ERROR_CLASS;
        field.parentElement.appendChild(node);
    }
    return node;
}

function clearFieldError(field) {
    field.setAttribute('aria-invalid', 'false');
    field.classList.remove('is-invalid');
    const errorNode = field.parentElement?.querySelector('.' + ADMIN_ERROR_CLASS);
    if (errorNode) {
        errorNode.remove();
    }
}

function setFieldError(field, message) {
    field.setAttribute('aria-invalid', 'true');
    field.classList.add('is-invalid');
    const errorNode = ensureErrorNode(field);
    errorNode.textContent = message;
}

function validateField(field) {
    const value = field.value?.trim?.() ?? '';

    if (field.disabled || field.hidden) {
        clearFieldError(field);
        return true;
    }

    if (field.required && value === '') {
        setFieldError(field, getFieldMessage(field));
        return false;
    }

    if (field.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        setFieldError(field, 'Veuillez saisir une adresse e-mail valide.');
        return false;
    }

    if (field.type === 'number' && value && Number.isNaN(Number(value))) {
        setFieldError(field, 'Veuillez saisir une valeur numérique valide.');
        return false;
    }

    if (field.min && value && Number(value) < Number(field.min)) {
        setFieldError(field, `La valeur doit être supérieure ou égale à ${field.min}.`);
        return false;
    }

    if (field.max && value && Number(value) > Number(field.max)) {
        setFieldError(field, `La valeur doit être inférieure ou égale à ${field.max}.`);
        return false;
    }

    if (field.getAttribute('maxlength') && value.length > Number(field.getAttribute('maxlength'))) {
        setFieldError(field, `Le texte ne doit pas dépasser ${field.getAttribute('maxlength')} caractères.`);
        return false;
    }

    clearFieldError(field);
    return true;
}

function bindValidation(form) {
    const fields = form.querySelectorAll('input, select, textarea');

    fields.forEach((field) => {
        const hasCustomValidation = field.hasAttribute('data-no-admin-validation');
        if (hasCustomValidation) {
            return;
        }

        field.addEventListener('blur', () => validateField(field));
        field.addEventListener('input', () => {
            if (field.classList.contains('is-invalid')) {
                validateField(field);
            }
        });
    });

    form.addEventListener('submit', (event) => {
        let valid = true;
        fields.forEach((field) => {
            if (!validateField(field)) {
                valid = false;
            }
        });

        if (!valid) {
            event.preventDefault();
            event.stopPropagation();
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }
    });
}

function ensureAdminActionModal() {
    let modal = document.getElementById('admin-action-confirmation-modal');
    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.id = 'admin-action-confirmation-modal';
    modal.className = 'modal fade';
    modal.setAttribute('tabindex', '-1');
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content admin-action-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer cette action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" data-admin-confirm-accept>Confirmer</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    return modal;
}

function bindAdminActionModal(button) {
    if (button.dataset.adminConfirmBound === 'true') {
        return;
    }

    button.dataset.adminConfirmBound = 'true';
    button.addEventListener('click', (event) => {
        const modal = ensureAdminActionModal();
        const modalTitle = modal.querySelector('.modal-title');
        const modalBody = modal.querySelector('.modal-body');
        const confirmButton = modal.querySelector('[data-admin-confirm-accept]');
        const actionTitle = button.dataset.adminConfirmTitle || 'Confirmer cette action';
        const actionMessage = button.dataset.adminConfirmMessage || 'Cette action est irréversible.';
        const confirmLabel = button.dataset.adminConfirmLabel || 'Confirmer';

        event.preventDefault();
        modalTitle.textContent = actionTitle;
        modalBody.textContent = actionMessage;
        confirmButton.textContent = confirmLabel;

        const modalInstance = bootstrap?.Modal ? bootstrap.Modal.getOrCreateInstance(modal) : null;
        confirmButton.onclick = () => {
            if (button instanceof HTMLAnchorElement && button.href) {
                window.location.href = button.href;
                return;
            }

            const form = button.closest('form');
            if (form) {
                form.submit();
                return;
            }

            if (button.type === 'submit') {
                button.form?.requestSubmit();
                return;
            }

            if (button.dataset.adminConfirmUrl) {
                window.location.href = button.dataset.adminConfirmUrl;
                return;
            }
        };

        if (modalInstance) {
            modalInstance.show();
        } else {
            modal.classList.add('show');
            modal.style.display = 'block';
        }
    });
}

function initializeAdminConfirmModals() {
    document.querySelectorAll('[data-admin-confirm]').forEach((button) => bindAdminActionModal(button));
}

function initializeAdminValidation() {
    const forms = document.querySelectorAll('body.ea form');
    forms.forEach((form) => {
        if (!form.hasAttribute('data-admin-validated')) {
            form.setAttribute('data-admin-validated', 'true');
            form.setAttribute('novalidate', 'novalidate');
            bindValidation(form);
        }
    });

    initializeAdminConfirmModals();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAdminValidation);
} else {
    initializeAdminValidation();
}
