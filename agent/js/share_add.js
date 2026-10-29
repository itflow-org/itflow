// Share something with someone outside (agent/modals/share/share_add.php).
//
// The passphrase box shows for a passphrase, the code note for an emailed
// code; "Email them the link" only works once the recipient is an email
// address. Making the link posts to ajax.php share_create, then the window
// turns into the result: the link (copied straight away), the passphrase to
// pass on another way, and a QR code. The link is shown once - its secret is
// not kept anywhere to show again.
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

    function protection() {
        const picked = form.querySelector('input[name="protect"]:checked');
        return picked ? picked.value : '';
    }

    function sync() {
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

        const data = { share_create: 1 };
        new FormData(form).forEach(function (value, key) {
            data[key] = value;
        });

        itflowPost('ajax.php', data, function (text) {
            make.disabled = false;
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
