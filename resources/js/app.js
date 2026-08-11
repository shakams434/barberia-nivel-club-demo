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
    confirmDialog?.querySelector('[data-confirm-submit]')?.addEventListener('click', () => {
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
        const messageTitle = qs('[data-template-message-title]', builder);
        const messageHelp = qs('[data-template-message-help]', builder);
        const variables = qsa('[data-template-variable]', builder);
        const update = () => {
            let body = select?.selectedOptions?.[0]?.dataset.body || 'Selecciona una plantilla para ver el mensaje.';
            const expected = Number(select?.selectedOptions?.[0]?.dataset.variables || 0);
            let samples = [];
            try { samples = JSON.parse(select?.selectedOptions?.[0]?.dataset.samples || '[]'); } catch { samples = []; }
            const hasTemplate = Boolean(select?.value);
            if (messageTitle) messageTitle.textContent = hasTemplate && expected === 0 ? 'Mensaje listo para revisar' : 'Completa los datos del mensaje';
            if (messageHelp) messageHelp.textContent = hasTemplate && expected === 0 ? 'Esta plantilla usa texto fijo y no necesita datos adicionales.' : 'Verás exactamente dónde aparece cada valor.';
            variables.forEach((input, index) => {
                const field = input.closest('[data-variable-field]');
                const label = field ? qs('[data-variable-label]', field) : null;
                const active = index < expected;
                input.disabled = !active;
                field?.classList.toggle('hidden', !active);
                input.placeholder = samples[index] ? `Ejemplo: ${samples[index]}` : `Valor para {{${index + 1}}}`;
                if (label) label.textContent = `Dato ${index + 1} · reemplaza {{${index + 1}}}`;
                body = body.replaceAll(`{{${index + 1}}}`, input.value || `{{${index + 1}}}`);
            });
            if (preview) preview.textContent = body;
        };
        select?.addEventListener('change', update);
        variables.forEach((input) => input.addEventListener('input', update));
        update();
    });

    qsa('[data-campaign-audience]').forEach((audience) => {
        const modes = qsa('[data-audience-mode]', audience);
        if (modes.length === 0) {
            const legacyChecks = qsa('input[type="checkbox"][name="customer_ids[]"]', audience);
            const legacyCount = qs('[data-audience-count]', audience);
            const legacyRefresh = () => { if (legacyCount) legacyCount.textContent = legacyChecks.filter((item) => item.checked).length; };
            qsa('[data-audience-action]', audience).forEach((button) => button.addEventListener('click', () => {
                const checked = button.dataset.audienceAction === 'all';
                legacyChecks.forEach((item) => { item.checked = checked; });
                legacyRefresh();
            }));
            legacyChecks.forEach((item) => item.addEventListener('change', legacyRefresh));
            legacyRefresh();
            return;
        }
        const panels = qsa('[data-audience-panel]', audience);
        const customers = qsa('[data-audience-customer]', audience);
        const checks = qsa('[data-select-customer]', audience);
        const selectionCount = qs('[data-selection-count]', audience);
        const filteredCount = qs('[data-filtered-count]', audience);
        const search = qs('[data-audience-search]', audience);
        const filters = qsa('[data-audience-filter]', audience);

        const refreshSelection = () => {
            if (selectionCount) selectionCount.textContent = checks.filter((item) => item.checked).length;
        };

        const refreshSearch = () => {
            const term = (search?.value || '').trim().toLocaleLowerCase();
            customers.forEach((customer) => customer.classList.toggle('hidden', term !== '' && !customer.dataset.search.includes(term)));
        };

        const refreshFilters = () => {
            const value = (name) => qs(`[name="${name}"]`, audience)?.value || '';
            const gender = value('gender');
            const tier = value('tier_id');
            const service = value('service_id');
            const inactive = Number(value('inactive_days') || 0);
            const minLevel = Number(value('min_level') || 0);
            const maxLevel = Number(value('max_level') || 0);
            const reward = qs('[name="reward_pending"]', audience)?.checked || false;
            const matches = customers.filter((customer) => (
                (!gender || customer.dataset.gender === gender)
                && (!tier || customer.dataset.tier === tier)
                && (!service || customer.dataset.services.includes(`,${service},`))
                && (!inactive || Number(customer.dataset.inactive) >= inactive)
                && (!minLevel || Number(customer.dataset.level) >= minLevel)
                && (!maxLevel || Number(customer.dataset.level) <= maxLevel)
                && (!reward || customer.dataset.reward === '1')
            ));
            if (filteredCount) filteredCount.textContent = matches.length;
        };

        const refreshMode = () => {
            const mode = modes.find((item) => item.checked)?.value || 'filter';
            panels.forEach((panel) => {
                const active = panel.dataset.audiencePanel === mode;
                panel.classList.toggle('hidden', !active);
                qsa('input, select', panel).forEach((control) => { control.disabled = !active; });
            });
            if (mode === 'selection') refreshSelection(); else refreshFilters();
        };

        qsa('[data-audience-action]', audience).forEach((button) => button.addEventListener('click', () => {
            const checked = button.dataset.audienceAction === 'all';
            checks.forEach((item) => { item.checked = checked; });
            refreshSelection();
        }));
        modes.forEach((mode) => mode.addEventListener('change', refreshMode));
        checks.forEach((item) => item.addEventListener('change', refreshSelection));
        filters.forEach((filter) => filter.addEventListener('input', refreshFilters));
        filters.forEach((filter) => filter.addEventListener('change', refreshFilters));
        search?.addEventListener('input', refreshSearch);
        refreshMode();
        refreshSearch();
    });

    qsa('[data-template-editor]').forEach((editor) => {
        const body = qs('[data-template-body]', editor);
        const preview = qs('[data-template-editor-preview]', editor);
        const sampleFields = qsa('[data-template-sample-field]', editor);
        const technical = qs('[data-template-technical]', editor);
        const display = qs('[data-template-display]', editor);

        const refresh = () => {
            const text = body?.value || 'Escribe el mensaje para ver la vista previa.';
            const matches = [...text.matchAll(/\{\{(\d+)\}\}/g)].map((match) => Number(match[1]));
            const count = matches.length ? Math.max(...matches) : 0;
            sampleFields.forEach((field, index) => {
                const active = index < count;
                field.classList.toggle('hidden', !active);
                qs('input', field).disabled = !active;
            });
            let rendered = text;
            sampleFields.forEach((field, index) => {
                const sample = qs('input', field)?.value || `{{${index + 1}}}`;
                rendered = rendered.replaceAll(`{{${index + 1}}}`, sample);
            });
            if (preview) preview.textContent = rendered;
        };

        display?.addEventListener('input', () => {
            if (!technical || technical.dataset.touched === 'true') return;
            technical.value = display.value.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
        });
        technical?.addEventListener('input', () => { technical.dataset.touched = 'true'; });
        body?.addEventListener('input', refresh);
        sampleFields.forEach((field) => qs('input', field)?.addEventListener('input', refresh));
        qsa('[data-insert-variable]', editor).forEach((button) => button.addEventListener('click', () => {
            if (!body) return;
            const next = Math.max(0, ...[...body.value.matchAll(/\{\{(\d+)\}\}/g)].map((match) => Number(match[1]))) + 1;
            const token = `{{${next}}}`;
            body.setRangeText(token, body.selectionStart, body.selectionEnd, 'end');
            body.focus();
            refresh();
        }));
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
