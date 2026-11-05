// Share something with someone outside (agent/modals/share/share_add.php).
//
// The passphrase box shows for a passphrase, the code note for an emailed
// code; "Email them the link" only works once the recipient is an email
// address. Making the link posts to ajax.php share_create, then the window
// turns into the result: the link (copied straight away), the passphrase to
// pass on another way, and a QR code. The link is shown once - its secret is
// not kept anywhere to show again.
//
// For the IT documentation: All / None for its parts, the passwords switch only
// while Credentials is ticked, the PDF's password pair only while that is on -
// and, when the sharing policy wants it for passwords, no "Nothing" to open it.
// Making that link makes the PDF too, so the button says so while it works.
//
// An IIFE wired to the newest window, as the modal can be opened again in
// one page load.

(function () {

    const form = document.querySelector('form[data-share-add]:not([data-wired])');
    if (!form) {
        return;
    }
    form.dataset.wired = '1';

    const formPane = form.querySelector('[data-share-form]');
    const donePane = form.querySelector('[data-share-done]');
    const error = form.querySelector('[data-share-error]');
    const make = form.querySelector('[data-share-make]');
    const again = form.querySelector('[data-share-again]');
    const closeLabel = form.querySelector('[data-share-close]');
    const recipient = form.querySelector('[data-share-recipient]');
    const send = form.querySelector('[data-share-send]');
    const passphraseBox = form.querySelector('[data-share-passphrase]');
    const passphrase = form.querySelector('input[name="passphrase"]');
    const codeNote = form.querySelector('[data-share-code]');
    const noneNote = form.querySelector('[data-share-none]');
    const qr = form.querySelector('[data-share-qr]');
    const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    let result = null;

    // The IT documentation's own choices
    const exportBox = form.querySelector('[data-share-export]');
    const exportCredentials = exportBox ? exportBox.querySelector('[data-client-export-credentials]') : null;
    const exportSecretsRow = form.querySelector('[data-share-export-secrets-row]');
    const exportSecrets = form.querySelector('[data-share-export-secrets]');
    const exportPasswordBox = form.querySelector('[data-share-export-password]');
    const exportPasswords = exportPasswordBox ? exportPasswordBox.querySelectorAll('input[type=password]') : [];
    const protectNone = form.querySelector('input[name="protect"][value=""]');
    const protectNoneLocked = protectNone ? protectNone.disabled : false;

    function syncExport() {
        if (!exportBox) {
            return;
        }
        const canSecrets = !!(exportCredentials && exportCredentials.checked);
        if (exportSecretsRow) {
            exportSecretsRow.hidden = !canSecrets;
        }
        const wantSecrets = canSecrets && exportSecrets && exportSecrets.checked;
        if (exportPasswordBox) {
            exportPasswordBox.hidden = !wantSecrets;
        }
        exportPasswords.forEach(function (field) {
            field.disabled = !wantSecrets;
            field.required = wantSecrets;
        });
        // The policy asks for a passphrase or code on passwords - these are passwords now
        if (protectNone && form.hasAttribute('data-share-protect-secrets')) {
            protectNone.disabled = protectNoneLocked || wantSecrets;
            if (protectNone.disabled && protectNone.checked) {
                form.querySelector('input[name="protect"][value="passphrase"]').checked = true;
            }
        }
    }

    if (exportBox) {
        exportBox.querySelectorAll('[data-share-export-all], [data-share-export-none]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const on = link.hasAttribute('data-share-export-all');
                exportBox.querySelectorAll('input[name="sections[]"]').forEach(function (box) {
                    box.checked = on;
                });
                sync();
            });
        });
        // The two passwords have to match before anything is sent
        if (exportPasswords.length === 2) {
            const match = function () {
                exportPasswords[1].setCustomValidity(exportPasswords[1].value === exportPasswords[0].value ? '' : 'The passwords do not match');
            };
            exportPasswords[0].addEventListener('input', function () {
                if (exportPasswords[1].value !== '') {
                    match();
                }
            });
            exportPasswords[1].addEventListener('input', match);
        }
    }

    function protection() {
        const picked = form.querySelector('input[name="protect"]:checked');
        return picked ? picked.value : '';
    }

    function sync() {
        syncExport();
        const email = isEmail.test((recipient.value || '').trim());
        if (send) {
            send.disabled = !email;
            if (!email) {
                send.checked = false;
            }
        }
        const protect = protection();
        passphraseBox.hidden = protect !== 'passphrase';
        passphrase.required = protect === 'passphrase';
        codeNote.hidden = protect !== 'code';
        if (noneNote) {
            noneNote.hidden = protect !== '';
        }
    }

    // Picking someone from the list is a good sign they should get the email
    recipient.addEventListener('change', function () {
        if (send && isEmail.test((recipient.value || '').trim()) && !send.dataset.touched) {
            send.disabled = false;
            send.checked = true;
        }
        sync();
    });
    if (send) {
        send.addEventListener('change', function () {
            send.dataset.touched = '1';
        });
    }
    form.addEventListener('change', sync);
    form.addEventListener('input', sync);
    sync();

    form.querySelector('[data-share-generate]').addEventListener('click', function () {
        itflowGet('ajax.php', { get_readable_pass: 'true' }, function (data) {
            passphrase.value = JSON.parse(data);
        });
    });

    function copy(button, text) {
        itflowCopyText(text, button).then(
            function () { flashTooltip(button, 'Copied!'); },
            function () { flashTooltip(button, 'Copy it by hand'); }
        );
    }

    form.querySelectorAll('[data-share-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (result) {
                copy(button, button.dataset.shareCopy === 'passphrase' ? result.passphrase : result.url);
            }
        });
    });

    form.querySelector('[data-share-qr-toggle]').addEventListener('click', function () {
        qr.hidden = !qr.hidden;
    });

    function showError(message) {
        error.textContent = message;
        error.hidden = false;
        error.scrollIntoView({ block: 'nearest' });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sync();
        if (!form.reportValidity()) {
            return;
        }
        // A code goes to the recipient, so it needs their address
        if (protection() === 'code' && !isEmail.test((recipient.value || '').trim())) {
            showError('An emailed code goes to the recipient - pick a contact or type their email address.');
            return;
        }
        error.hidden = true;
        make.disabled = true;
        const makeLabel = make.innerHTML;
        if (exportBox) {
            make.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Making the PDF...';
        }

        // Ticked parts go as a list - sections[] is sent as sections[0], sections[1]...
        const data = { share_create: 1 };
        new FormData(form).forEach(function (value, key) {
            if (key.slice(-2) === '[]') {
                key = key.slice(0, -2);
                (data[key] = data[key] || []).push(value);
            } else {
                data[key] = value;
            }
        });

        itflowPost('ajax.php', data, function (text) {
            make.disabled = false;
            make.innerHTML = makeLabel;
            let response;
            try {
                response = JSON.parse(text);
            } catch (err) {
                showError('The link could not be made - try again.');
                return;
            }
            if (response.error) {
                showError(response.error);
                return;
            }
            result = response;

            form.querySelector('[data-share-link]').value = response.url;
            form.querySelector('[data-share-summary]').textContent = response.summary;
            form.querySelector('[data-share-passphrase-done]').hidden = !response.passphrase;
            form.querySelector('[data-share-passphrase-value]').value = response.passphrase || '';
            qr.innerHTML = response.qr || '';
            qr.hidden = true;
            form.querySelector('[data-share-qr-toggle]').hidden = !response.qr;

            formPane.hidden = true;
            donePane.hidden = false;
            make.hidden = true;
            again.hidden = false;
            closeLabel.textContent = 'Done';

            // Straight onto the clipboard - flash on the link's own copy button
            const linkCopy = form.querySelector('[data-share-copy="link"]');
            itflowCopyText(response.url, linkCopy).then(function () {
                form.querySelector('[data-share-copied]').hidden = false;
                flashTooltip(linkCopy, 'Copied!');
            }, function () {});
        }, function () {
            make.disabled = false;
            make.innerHTML = makeLabel;
            showError('The link could not be made - check your connection and try again.');
        });
    });

    // Another link with the same choices - a new secret, and a fresh passphrase
    again.addEventListener('click', function () {
        result = null;
        donePane.hidden = true;
        formPane.hidden = false;
        make.hidden = false;
        again.hidden = true;
        closeLabel.textContent = 'Cancel';
        form.querySelector('[data-share-copied]').hidden = true;
        form.querySelector('[data-share-link]').value = '';
        form.querySelector('[data-share-passphrase-value]').value = '';
        qr.innerHTML = '';
        if (protection() === 'passphrase') {
            form.querySelector('[data-share-generate]').click();
        }
    });

})();
