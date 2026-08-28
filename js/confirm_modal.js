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
 * A link whose classes mark it destructive (text-danger) defaults to a red
 * confirm button and a warning icon, so the 92 delete links read as dangerous
 * without touching any of them.
 */
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

    const destructive = link.classList.contains('text-danger');

    Swal.fire({
        // SweetAlert2 has no idea about Bootstrap's mode - it keys its own dark
        // palette off the `theme` option and defaults to 'light', so the dialog
        // stayed white on a dark page. Read at fire time, not at load.
        theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
        title: link.dataset.confirmTitle || 'Are you sure?',
        text: link.dataset.confirmText || '',
        icon: link.dataset.confirmIcon || (destructive ? 'warning' : 'question'),
        showCancelButton: true,
        confirmButtonText: link.dataset.confirmButton || 'Yes',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: destructive,
        buttonsStyling: false,
        customClass: {
            confirmButton: destructive ? 'btn btn-danger mx-1' : 'btn btn-primary mx-1',
            cancelButton: 'btn btn-secondary mx-1'
        }
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
