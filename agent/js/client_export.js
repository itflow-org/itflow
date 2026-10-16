// Export a client's data (agent/modals/client/client_export_data.php).
//
// Each form: All / None for the sections; the passwords switch only shows
// while Credentials is ticked, and the password pair only while the switch
// is on (disabled otherwise, so a hidden field never blocks the submit).
// A download does not leave the page, so the button shows it is working
// until the server's itflow_download cookie says the file is on its way.
//
// Wrapped in an IIFE because the modal can be opened again in one page load.

(function () {

    const root = document.getElementById('clientExport');
    if (!root) {
        return;
    }

    function cookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    root.querySelectorAll('form[data-client-export]').forEach(function (form) {
        const credentials = form.querySelector('[data-client-export-credentials]');
        const secretsRow = form.querySelector('[data-client-export-secrets-row]');
        const secrets = form.querySelector('[data-client-export-secrets]');
        const passwordBox = form.querySelector('[data-client-export-password]');
        const passwords = passwordBox ? passwordBox.querySelectorAll('input[type=password]') : [];
        const submit = form.querySelector('[data-client-export-submit]');

        function sync() {
            const canSecrets = !!(credentials && credentials.checked);
            if (secretsRow) {
                secretsRow.hidden = !canSecrets;
            }
            const wantSecrets = canSecrets && secrets && secrets.checked;
            if (passwordBox) {
                passwordBox.hidden = !wantSecrets;
            }
            passwords.forEach(function (field) {
                field.disabled = !wantSecrets;
                field.required = wantSecrets;
            });
        }

        form.addEventListener('change', sync);
        sync();

        form.querySelectorAll('[data-client-export-all], [data-client-export-none]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const on = link.hasAttribute('data-client-export-all');
                form.querySelectorAll('input[name="sections[]"]').forEach(function (box) {
                    box.checked = on;
                });
                sync();
            });
        });

        // The two passwords have to match before anything is sent
        if (passwords.length === 2) {
            passwords[1].addEventListener('input', function () {
                passwords[1].setCustomValidity(passwords[1].value === passwords[0].value ? '' : 'The passwords do not match');
            });
            passwords[0].addEventListener('input', function () {
                if (passwords[1].value !== '') {
                    passwords[1].setCustomValidity(passwords[1].value === passwords[0].value ? '' : 'The passwords do not match');
                }
            });
        }

        form.addEventListener('submit', function () {
            const token = Math.random().toString(36).slice(2) + Date.now().toString(36);
            form.querySelector('input[name="download_token"]').value = token;
            const label = submit.innerHTML;
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Preparing...';

            // A disabled button is left out of the post - carry its name across
            let marker = form.querySelector('input[data-client-export-trigger]');
            if (!marker) {
                marker = document.createElement('input');
                marker.type = 'hidden';
                marker.setAttribute('data-client-export-trigger', '');
                form.appendChild(marker);
            }
            marker.name = submit.name;
            marker.value = submit.value;

            const started = Date.now();
            const timer = setInterval(function () {
                // Done when the file arrives - or give up after ten minutes and let them try again
                if (cookie('itflow_download') === token || Date.now() - started > 600000) {
                    clearInterval(timer);
                    submit.disabled = false;
                    submit.innerHTML = label;
                }
            }, 500);
        });
    });

})();
