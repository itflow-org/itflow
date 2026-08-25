/**
 * Confirmation dialogs, on SweetAlert2.
 *
 * Delegated on document rather than bound to the links present at page load, so
 * that confirm-link also works on markup injected by an ajax modal.
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
    const link = e.target.closest('a.confirm-link');
    if (!link || typeof Swal === 'undefined') {
        return;
    }
    e.preventDefault();

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
        if (result.isConfirmed) {
            window.location.href = link.getAttribute('href');
        }
    });
});
