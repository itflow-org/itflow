// Delegated on document rather than bound to the links present at page load, so
// that confirm-link also works on markup injected by an ajax modal
document.addEventListener('click', function (e) {
    const link = e.target.closest('a.confirm-link');
    if (!link) {
        return;
    }
    e.preventDefault();

    const modalEl = document.getElementById('confirmationModal');
    const confirmBtn = document.getElementById('confirmSubmitBtn');
    if (!modalEl || !confirmBtn) {
        return;
    }

    // Replacing the node drops any handler left over from a previous link,
    // which is what .off('click') used to do here.
    const freshBtn = confirmBtn.cloneNode(true);
    confirmBtn.replaceWith(freshBtn);
    freshBtn.addEventListener('click', function () {
        window.location.href = link.getAttribute('href');
    });

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
});
