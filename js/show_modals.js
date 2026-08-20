document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modal) {
        if (modal.id && window.location.href.indexOf('#' + modal.id) !== -1) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
});
