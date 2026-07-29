const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];

const openDialog = (dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) return;
    dialog.showModal();
    requestAnimationFrame(() => qs('input:not([type="hidden"]), select, textarea, button', dialog)?.focus());
};

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.dataset.noLock === 'true') return;

    const submitter = event.submitter;
    if (submitter instanceof HTMLButtonElement) {
        submitter.disabled = true;
        submitter.setAttribute('aria-busy', 'true');
        const busyText = submitter.dataset.busyText;
        if (busyText) submitter.innerHTML = `<span class="spinner" aria-hidden="true"></span>${busyText}`;
    }
});

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-dialog]');
    if (opener) {
        event.preventDefault();
        openDialog(document.getElementById(opener.dataset.openDialog));
        return;
    }

    const closer = event.target.closest('[data-close-dialog]');
    if (closer) {
        event.preventDefault();
        closer.closest('dialog')?.close();
        return;
    }

    const reveal = event.target.closest('[data-reveal]');
    if (reveal) {
        const target = document.getElementById(reveal.dataset.reveal);
        if (target) {
            const hidden = target.dataset.hidden === 'true';
            target.textContent = hidden ? target.dataset.value : target.dataset.mask;
            target.dataset.hidden = hidden ? 'false' : 'true';
            reveal.textContent = hidden ? 'Ocultar' : 'Mostrar';
            reveal.setAttribute('aria-expanded', hidden ? 'true' : 'false');
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    qsa('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    qsa('[data-toast]').forEach((toast, index) => {
        const close = () => {
            toast.classList.add('toast-leave');
            setTimeout(() => toast.remove(), 220);
        };
        qs('[data-toast-close]', toast)?.addEventListener('click', close);
        setTimeout(close, 5200 + index * 350);
    });

    const confirmDialog = qs('#confirm-dialog');
    let pendingForm = null;
    qsa('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') return;
            event.preventDefault();
            pendingForm = form;
            qs('[data-confirm-title]', confirmDialog).textContent = form.dataset.confirmTitle || 'Confirmar acción';
            qs('[data-confirm-message]', confirmDialog).textContent = form.dataset.confirm;
            const confirmButton = qs('[data-confirm-submit]', confirmDialog);
            confirmButton.textContent = form.dataset.confirmButton || 'Confirmar';
            confirmButton.className = `btn ${form.dataset.confirmTone === 'danger' ? 'btn-danger' : 'btn-primary'}`;
            openDialog(confirmDialog);
        });
    });
    qs('[data-confirm-submit]', confirmDialog)?.addEventListener('click', () => {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = 'true';
        confirmDialog.close();
        pendingForm.requestSubmit();
    });

    qsa('form[data-auto-submit]').forEach((form) => {
        let timer;
        qsa('select', form).forEach((control) => control.addEventListener('change', () => form.requestSubmit()));
        qsa('input[type="search"]', form).forEach((control) => control.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.requestSubmit(), 450);
        }));
    });

    qsa('[data-selection-form]').forEach((form) => {
        const rows = qsa('[data-select-row]', form);
        const all = qs('[data-select-page]', form);
        const bar = qs('[data-selection-bar]');
        const count = qs('[data-selection-count]');
        const submit = qs('[data-selection-submit]');
        const filtered = qs('[data-select-filtered]', form);
        const visibleRows = () => rows.filter((row) => row.offsetParent !== null);

        const refresh = () => {
            const activeRows = visibleRows();
            const selected = activeRows.filter((row) => row.checked).length;
            if (all) {
                all.checked = selected === activeRows.length && activeRows.length > 0;
                all.indeterminate = selected > 0 && selected < activeRows.length;
            }
            if (count) count.textContent = filtered?.checked ? `${filtered.dataset.total} filtrados` : `${selected} seleccionados`;
            if (submit) submit.disabled = !filtered?.checked && selected === 0;
            bar?.classList.toggle('selection-bar-visible', selected > 0 || filtered?.checked);
        };
        all?.addEventListener('change', () => {
            visibleRows().forEach((row) => { row.checked = all.checked; });
            refresh();
        });
        rows.forEach((row) => row.addEventListener('change', refresh));
        filtered?.addEventListener('change', () => {
            if (filtered.checked) rows.forEach((row) => { row.checked = false; });
            refresh();
        });
        refresh();
    });

    qsa('[data-template-builder]').forEach((builder) => {
        const select = qs('[data-template-select]', builder);
        const preview = qs('[data-template-preview]', builder);
        const variables = qsa('[data-template-variable]', builder);
        const update = () => {
            let body = select?.selectedOptions?.[0]?.dataset.body || 'Selecciona una plantilla para ver el mensaje.';
            const expected = Number(select?.selectedOptions?.[0]?.dataset.variables || 0);
            variables.forEach((input, index) => {
                const field = input.closest('[data-variable-field]');
                const active = index < expected;
                input.disabled = !active;
                field?.classList.toggle('hidden', !active);
                body = body.replaceAll(`{{${index + 1}}}`, input.value || `{{${index + 1}}}`);
            });
            if (preview) preview.textContent = body;
        };
        select?.addEventListener('change', update);
        variables.forEach((input) => input.addEventListener('input', update));
        update();
    });

    qsa('[data-campaign-audience]').forEach((audience) => {
        const checks = qsa('input[type="checkbox"][name="customer_ids[]"]', audience);
        const count = qs('[data-audience-count]', audience);
        const refresh = () => {
            if (count) count.textContent = checks.filter((item) => item.checked).length;
        };
        qsa('[data-audience-action]', audience).forEach((button) => button.addEventListener('click', () => {
            const checked = button.dataset.audienceAction === 'all';
            checks.forEach((item) => { item.checked = checked; });
            refresh();
        }));
        checks.forEach((item) => item.addEventListener('change', refresh));
        refresh();
    });

    const section = document.getElementById('register-visit');
    const shortcut = document.getElementById('mobile-register-link');
    if (section && shortcut && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            shortcut.hidden = entry.isIntersecting;
        }, { threshold: 0.15 }).observe(section);
    }

    document.addEventListener('keydown', (event) => {
        if ((event.altKey && event.key.toLowerCase() === 'a') || (event.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName))) {
            event.preventDefault();
            openDialog(document.getElementById('quick-action-dialog'));
        }
        if (event.key === 'Escape') {
            qsa('dialog[open]').forEach((dialog) => dialog.close());
        }
    });
});
