document.addEventListener('DOMContentLoaded', function () {
    // Add class to tables
    document.querySelectorAll('div.prettyContent table').forEach(function (el) {
        el.classList.add('table');
    });

    // Add img-fluid class to img tags
    document.querySelectorAll('div.prettyContent img').forEach(function (el) {
        el.classList.add('img-fluid');
    });
});
