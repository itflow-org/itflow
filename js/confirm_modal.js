/**
 * Confirmation dialogs, on SweetAlert2.
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
 * the delete links read as dangerous without touching any of them. With no
 * data-confirm-button, the confirm button says what the link says - Delete,
 * Archive, Remove - when that is a word or three, and Yes otherwise.
 *
 * itflowConfirm({title, text, button, icon, danger}) asks the same question
 * from script and resolves true when it is confirmed.
 */
function itflowConfirm(options) {
    options = options || {};
    if (typeof Swal === 'undefined') {
        return Promise.resolve(window.confirm(options.title || 'Are you sure?'));
    }
    return Swal.fire({
        // SweetAlert2 has no idea about Bootstrap's mode - it keys its own dark
        // palette off the `theme` option and defaults to 'light', so the dialog
        // stayed white on a dark page. Read at fire time, not at load.
        theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
        title: options.title || 'Are you sure?',
        text: options.text || '',
        icon: options.icon || (options.danger ? 'warning' : 'question'),
        showCancelButton: true,
        confirmButtonText: options.button || 'Yes',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: !!options.danger,
        buttonsStyling: false,
        customClass: {
            confirmButton: options.danger ? 'btn btn-danger mx-1' : 'btn btn-primary mx-1',
            cancelButton: 'btn btn-secondary mx-1'
        }
    }).then(function (result) {
        return !!result.isConfirmed;
    });
}

// What the link itself says, for the confirm button - only when it is short
function itflowConfirmLabel(link) {
    const said = (link.textContent || '').replace(/\s+/g, ' ').trim() || link.getAttribute('title') || link.getAttribute('aria-label') || '';
    return said && said.length <= 24 && said.split(' ').length <= 3 ? said : '';
}

document.addEventListener('click', function (e) {
    const link = e.target.closest('a.confirm-link, button.confirm-link');
    if (!link || typeof Swal === 'undefined') {
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

    itflowConfirm({
        title: link.dataset.confirmTitle,
        text: link.dataset.confirmText,
        button: link.dataset.confirmButton || itflowConfirmLabel(link),
        icon: link.dataset.confirmIcon,
        danger: destructive
    }).then(function (confirmed) {
        if (!confirmed) {
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
