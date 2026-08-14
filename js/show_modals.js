$(document).ready(function () {
    $('.modal').each(function () {
        const modalId = `#${$(this).attr('id')}`;
        if (window.location.href.indexOf(modalId) !== -1) {
            bootstrap.Modal.getOrCreateInstance($(modalId)[0]).show();
        }
    });
});