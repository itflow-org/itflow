/*
 * Credentials - the password, copy and 2FA buttons functions/credential.php
 * draws wherever a credential is listed (the credentials list, assets,
 * contacts, vendors, licenses, the quick looks). Bound once on document, so
 * they work in ajax modals too.
 *
 * Nothing secret is in the page until it is asked for: the eye and the copy
 * button fetch the password from agent/ajax.php (get_credential_via_id), the
 * 2FA button fetches the current code (get_totp_token_via_id) - both check the
 * module and the client and log the view.
 */

(function () {

    // How long a shown password stays on screen, and how many codes in a row the 2FA button follows on to
    var SHOW_FOR = 30000;
    var OTP_ROUNDS = 6;

    function fetchCredential(id, done) {
        itflowGet('/agent/ajax.php', { get_credential_via_id: 'true', credential_id: id }, function (data) {
            var credential = null;
            try {
                credential = JSON.parse(data);
            } catch (e) {}
            if (credential) {
                done(credential);
            }
        });
    }

    function hidePassword(box) {
        var value = box.querySelector('.itflow-credential-pw-value');
        var mask = box.querySelector('.itflow-credential-pw-mask');
        var button = box.querySelector('.itflow-credential-reveal');
        clearTimeout(box.itflowHide);
        value.textContent = '';
        value.hidden = true;
        mask.hidden = false;
        if (button) {
            button.querySelector('i').className = 'fas fa-eye';
            button.setAttribute('aria-pressed', 'false');
            button.title = 'Show password';
        }
    }

    // The eye: show the password for a while, or hide it again
    itflowBindOnce('itflowCredentialReveal', 'click', '.itflow-credential-reveal', function (e) {
        e.preventDefault();
        var button = this;
        var box = button.closest('.itflow-credential-pw');
        if (!box) {
            return;
        }
        var value = box.querySelector('.itflow-credential-pw-value');
        if (!value.hidden) {
            hidePassword(box);
            return;
        }
        fetchCredential(box.dataset.credentialId, function (credential) {
            value.textContent = credential.password || '';
            value.hidden = false;
            box.querySelector('.itflow-credential-pw-mask').hidden = true;
            button.querySelector('i').className = 'fas fa-eye-slash';
            button.setAttribute('aria-pressed', 'true');
            button.title = 'Hide password';
            clearTimeout(box.itflowHide);
            box.itflowHide = setTimeout(function () {
                hidePassword(box);
            }, SHOW_FOR);
        });
    });

    // Copy: fetch the password and copy it, without showing it
    itflowBindOnce('itflowCredentialCopy', 'click', '.itflow-credential-copy', function (e) {
        e.preventDefault();
        var button = this;
        var box = button.closest('[data-credential-id]');
        if (!box) {
            return;
        }
        fetchCredential(box.dataset.credentialId, function (credential) {
            itflowCopyText(credential.password || '', button).then(
                function () { flashTooltip(button, 'Copied!'); },
                function () { flashTooltip(button, 'Failed!'); }
            );
        });
    });

    function resetOtp(button) {
        clearInterval(button.itflowTimer);
        delete button.dataset.code;
        button.classList.remove('is-live', 'is-ending');
        button.querySelector('.itflow-credential-otp-code').textContent = '2FA';
        button.querySelector('.itflow-credential-otp-time').textContent = '';
        button.title = 'Show the 2FA code';
    }

    // 2FA: the first click fetches the code and counts it down, following on to
    // the next one for a few minutes; clicking it while it shows copies it
    itflowBindOnce('itflowCredentialOtp', 'click', '.itflow-credential-otp', function (e) {
        e.preventDefault();
        var button = this;
        if (button.dataset.code) {
            itflowCopyText(button.dataset.code, button).then(
                function () { flashTooltip(button, 'Copied!'); },
                function () { flashTooltip(button, 'Failed!'); }
            );
            return;
        }
        var codeEl = button.querySelector('.itflow-credential-otp-code');
        var timeEl = button.querySelector('.itflow-credential-otp-time');
        var rounds = 0;
        var last = 31;

        function load() {
            itflowGet('/agent/ajax.php', { get_totp_token_via_id: 'true', credential_id: button.dataset.credentialOtp }, function (data) {
                var code = '';
                try {
                    code = String(JSON.parse(data) || '');
                } catch (err) {}
                if (!/^[0-9]{6,8}$/.test(code)) {
                    resetOtp(button);
                    codeEl.textContent = 'No code';
                    return;
                }
                button.dataset.code = code;
                codeEl.textContent = code.length === 6 ? code.slice(0, 3) + ' ' + code.slice(3) : code;
                button.classList.add('is-live');
                button.title = 'Click to copy the code';
            });
        }

        function tick() {
            var left = 30 - Math.floor(Date.now() / 1000) % 30;
            timeEl.textContent = left + 's';
            button.classList.toggle('is-ending', left <= 5);
            // A new period - the next code, for a few rounds, then back to the button
            if (left > last) {
                rounds++;
                if (rounds >= OTP_ROUNDS || !document.body.contains(button)) {
                    resetOtp(button);
                    return;
                }
                load();
            }
            last = left;
        }

        clearInterval(button.itflowTimer);
        load();
        tick();
        button.itflowTimer = setInterval(tick, 1000);
    });

})();
