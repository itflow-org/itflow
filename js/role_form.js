// New Role and Edit Role (admin/modals/role/role_form.php). An admin role
// opens everything, so its levels are hidden while Admin is on. Picking a
// module's level ticks off what that level lets its agents do - each level
// takes in the ones before it. The server checks it all again.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    document.querySelectorAll('[data-role-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const admin = form.querySelector('[data-role-admin]');
        const levels = form.querySelector('[data-role-levels]');
        const note = form.querySelector('[data-role-admin-note]');

        function showLevels() {
            levels.hidden = admin.checked;
            note.hidden = !admin.checked;
        }
        admin.addEventListener('change', showLevels);

        form.querySelectorAll('[data-role-module]').forEach(function (module) {
            module.addEventListener('change', function (e) {
                if (e.target.type !== 'radio') {
                    return;
                }
                const level = parseInt(e.target.value, 10);
                module.querySelectorAll('[data-role-level]').forEach(function (line) {
                    const on = parseInt(line.dataset.roleLevel, 10) <= level;
                    line.classList.toggle('is-off', !on);
                    line.querySelector('i').className = 'fas fa-fw me-1 ' + (on ? 'fa-check text-success' : 'fa-times');
                });
            });
        });
    });

})();
