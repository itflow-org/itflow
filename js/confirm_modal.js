/**
 * Dialogs - "Are you sure?" and the odd message - on a Bootstrap modal.
 *
 * itflowDialog(options) shows one and returns a Promise of
 * {isConfirmed, isDenied, isDismissed, value}. Loaded by includes/footer.php
 * (agent, admin and guest pages), client/includes/footer.php, Get started and
 * client onboarding. Options, all optional:
 *   title               heading                       (default: "Are you sure?")
 *   text                body text
 *   icon                warning|error|question|info|success
 *   confirmButtonText   (default: "Yes")
 *   cancelButtonText    (default: "Cancel")
 *   showCancelButton    false for a message with just the one button
 *   denyButtonText      a third, "no but" button - its answer is isDenied
 *   danger              a red confirm button
 *   focusCancel         start on Cancel rather than the confirm button
 *   returnFocus         false to leave focus where the caller puts it
 *   input: 'radio'      with inputOptions {value: label} and inputValue - the
 *                       one picked comes back as value
 * Escape, the backdrop and Cancel all answer isDismissed. Every piece of text
 * is set as text, never as HTML.
 *
 * Also the confirm-link dialogs below.
 */
function itflowDialog(options) {
    options = options || {};
    return new Promise(function (resolve) {
        const icons = {
            warning: 'fas fa-exclamation-triangle text-warning',
            error: 'far fa-times-circle text-danger',
            question: 'far fa-question-circle text-info',
            info: 'fas fa-info-circle text-info',
            success: 'far fa-check-circle text-success'
        };
        const id = 'itflowDialog' + Date.now();
        const modal = document.createElement('div');
        modal.className = 'modal fade itflow-dialog';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', id);
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
            + '<div class="modal-body text-center"></div><div class="modal-footer"></div></div></div>';
        const body = modal.querySelector('.modal-body');
        const footer = modal.querySelector('.modal-footer');

        if (icons[options.icon]) {
            const icon = document.createElement('i');
            icon.className = 'itflow-dialog-icon ' + icons[options.icon];
            icon.setAttribute('aria-hidden', 'true');
            body.appendChild(icon);
        }
        const title = document.createElement('h5');
        title.className = 'modal-title';
        title.id = id;
        title.textContent = options.title || 'Are you sure?';
        body.appendChild(title);
        if (options.text) {
            const text = document.createElement('p');
            text.className = 'itflow-dialog-text';
            text.textContent = options.text;
            body.appendChild(text);
        }

        const radios = [];
        if (options.input === 'radio' && options.inputOptions) {
            const group = document.createElement('div');
            group.className = 'itflow-dialog-radios';
            group.setAttribute('role', 'radiogroup');
            Object.keys(options.inputOptions).forEach(function (value, i) {
                const wrap = document.createElement('div');
                wrap.className = 'form-check form-check-inline';
                const radio = document.createElement('input');
                radio.className = 'form-check-input';
                radio.type = 'radio';
                radio.name = id + 'Radio';
                radio.id = id + 'Radio' + i;
                radio.value = value;
                radio.checked = String(options.inputValue) === value;
                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor = radio.id;
                label.textContent = options.inputOptions[value];
                wrap.append(radio, label);
                group.appendChild(wrap);
                radios.push(radio);
            });
            body.appendChild(group);
        }

        let result = {isConfirmed: false, isDenied: false, isDismissed: true};
        const instance = new bootstrap.Modal(modal);
        const button = function (label, className, answer) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn ' + className;
            b.textContent = label;
            b.addEventListener('click', function () {
                if (answer) {
                    result = answer();
                }
                instance.hide();
            });
            footer.appendChild(b);
            return b;
        };
        const picked = function () {
            const on = radios.filter(function (r) { return r.checked; })[0];
            return on ? on.value : undefined;
        };
        const cancel = options.showCancelButton === false ? null : button(options.cancelButtonText || 'Cancel', 'btn-secondary', null);
        if (options.denyButtonText) {
            button(options.denyButtonText, 'btn-outline-secondary', function () {
                return {isConfirmed: false, isDenied: true, isDismissed: false, value: picked()};
            });
        }
        const confirm = button(options.confirmButtonText || 'Yes', options.danger ? 'btn-danger' : 'btn-primary', function () {
            return {isConfirmed: true, isDenied: false, isDismissed: false, value: picked()};
        });

        // autofocus also tells js/app.js's modal focus handler to leave it be
        const first = options.focusCancel && cancel ? cancel : confirm;
        first.setAttribute('autofocus', '');
        const previous = document.activeElement;
        modal.addEventListener('shown.bs.modal', function () {
            first.focus();
        });
        modal.addEventListener('hidden.bs.modal', function () {
            instance.dispose();
            modal.remove();
            // Opened over another modal: Bootstrap takes modal-open off the
            // page as this one closes, though the one below is still open
            if (document.querySelector('.modal.show')) {
                document.body.classList.add('modal-open');
            }
            if (options.returnFocus !== false && previous && previous.isConnected && typeof previous.focus === 'function') {
                previous.focus({preventScroll: true});
            }
            resolve(result);
        });

        document.body.appendChild(modal);
        instance.show();
        // Its backdrop goes over any modal already open (css/itflow_custom.css
        // puts the dialog itself above both)
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length) {
            backdrops[backdrops.length - 1].classList.add('itflow-dialog-backdrop');
        }
    });
}

/**
 * Confirmation dialogs for confirm-link.
 *
 * Delegated on document rather than bound to the elements present at page load,
 * so that confirm-link also works on markup injected by an ajax modal.
 *
 * Works on an <a class="confirm-link"> (navigates on confirm) and on a
 * <button class="confirm-link"> that submits a form (submits on confirm). The
 * button form exists for actions that must not be a GET - Quick Send mails a
 * document to a client, so it posts.
 *
 * Per-link copy comes from data attributes, all optional:
 *   data-confirm-title   heading            (default: "Are you sure?")
 *   data-confirm-text    body               (default: none)
 *   data-confirm-button  confirm label      (default: "Yes")
 *   data-confirm-icon    warning|error|question|info|success
 *
 * A link whose classes mark it destructive (text-danger, btn-danger or
 * btn-outline-danger) defaults to a red confirm button and a warning icon, so
 * the delete links read as dangerous without touching any of them.
 */
document.addEventListener('click', function (e) {
    const link = e.target.closest('a.confirm-link, button.confirm-link');
    if (!link || typeof bootstrap === 'undefined') {
        return;
    }
    e.preventDefault();

    // A submit button contributes its own name/value to the submission, and
    // requestSubmit() preserves that where form.submit() would drop it - which
    // is exactly how the Quick Send buttons carry their invoice or quote id.
    const submitter = link.tagName === 'BUTTON' ? link : null;
    const form = submitter ? (submitter.form || submitter.closest('form')) : null;

    const destructive = ['text-danger', 'btn-danger', 'btn-outline-danger']
        .some(function (cls) { return link.classList.contains(cls); });

    itflowDialog({
        title: link.dataset.confirmTitle || 'Are you sure?',
        text: link.dataset.confirmText || '',
        icon: link.dataset.confirmIcon || (destructive ? 'warning' : 'question'),
        confirmButtonText: link.dataset.confirmButton || 'Yes',
        danger: destructive,
        focusCancel: destructive
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }
        if (form) {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter);
            } else {
                // Fallback for browsers without requestSubmit: replay the
                // button's name/value as a hidden field so the handler still
                // sees which record was picked.
                if (submitter.name) {
                    const carried = document.createElement('input');
                    carried.type = 'hidden';
                    carried.name = submitter.name;
                    carried.value = submitter.value;
                    form.appendChild(carried);
                }
                form.submit();
            }
            return;
        }
        window.location.href = link.getAttribute('href');
    });
});
