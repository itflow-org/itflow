// A shared link, as the person it was sent to sees it (guest/share.php).
//
// The link's secret is after the # - the browser never sends it to the
// server with the page - or, for a link made before 2.9.0, key= and ek= in
// the query string. Every step posts it to guest/share_ajax.php: what the
// link is, the passphrase or emailed code it needs, then the content once
// Reveal is clicked (a mail filter fetching the link never clicks). The page
// shows one state at a time and fills it in as text; only a document's HTML,
// cleaned on the server, goes in as HTML.

(function () {

    const page = document.querySelector('[data-share-page]');
    if (!page) {
        return;
    }

    const query = new URLSearchParams(window.location.search);
    const id = page.dataset.id;
    const key = window.location.hash.replace(/^#/, '') || query.get('key') || '';
    const ek = query.get('ek') || '';
    const typeIcons = { Credential: 'fa-key', Note: 'fa-user-secret', Document: 'fa-file-alt', File: 'fa-paperclip', Request: 'fa-paper-plane' };
    let link = null;
    let content = null;
    let otpTimer = null;
    let barTimer = null;

    function $(selector, root) {
        return (root || page).querySelector(selector);
    }

    function $$(selector, root) {
        return Array.from((root || page).querySelectorAll(selector));
    }

    function show(state) {
        $$('[data-share-state]').forEach(function (el) {
            el.hidden = el.dataset.shareState !== state;
        });
        // Wider for reading a document
        page.classList.toggle('itflow-share-page-wide', state === 'document');
        const shown = $('[data-share-state="' + state + '"]');
        const first = shown && shown.querySelector('input:not([readonly]):not([type=hidden]), textarea');
        if (first && window.matchMedia('(pointer: fine)').matches) {
            first.focus();
        }
        return shown;
    }

    function post(action, data) {
        const body = Object.assign({ action: action, id: id, key: key, ek: ek }, data || {});
        return fetch('/guest/share_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () {
                return { error: 'Something went wrong - try again in a moment.' };
            });
        }, function () {
            return { error: 'Could not reach the server - check your connection and try again.' };
        });
    }

    function gone(reason) {
        $('[data-share-gone-reason]').textContent = reason || 'It may have expired, been used up or been turned off.';
        stopOtp();
        show('gone');
    }

    function error(state, message) {
        const box = $('[data-share-state="' + state + '"] [data-share-error]');
        if (box) {
            box.textContent = message;
            box.hidden = !message;
        }
    }

    function note(root, text) {
        const box = $('[data-share-note]', root);
        if (box) {
            box.textContent = text || '';
            box.hidden = !text;
        }
    }

    function busy(button, on) {
        if (!button) {
            return;
        }
        if (on) {
            button.dataset.label = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>One moment...';
        } else if (button.dataset.label) {
            button.disabled = false;
            button.innerHTML = button.dataset.label;
        }
    }

    function copy(button, text) {
        const done = function (message) {
            if (typeof flashTooltip === 'function') {
                flashTooltip(button, message);
            }
        };
        const write = typeof itflowCopyText === 'function'
            ? itflowCopyText(text, button)
            : navigator.clipboard.writeText(text);
        write.then(function () { done('Copied!'); }, function () { done('Copy it by hand'); });
    }

    // What the link is, and what it needs - nothing secret yet, and nothing used up
    function open(data) {
        if (data.error) {
            gone(data.error);
            return;
        }
        if (data.state === 'gone') {
            gone(data.reason);
            return;
        }
        link = data;

        if (!data.verified && data.protect === 'passphrase') {
            const form = show('passphrase');
            $('[data-share-intro]', form).textContent = data.intro;
            return;
        }
        if (!data.verified && data.protect === 'code') {
            const form = show('code');
            $('[data-share-intro]', form).textContent = data.intro;
            $('[data-share-masked-email]', form).textContent = data.masked_email;
            return;
        }

        if (data.type === 'Request') {
            const form = show('request');
            $('[data-share-title]', form).textContent = data.title;
            $('[data-share-intro]', form).textContent = data.intro;
            note(form, data.note);
            return;
        }

        const ready = show('ready');
        $('[data-share-type-icon]', ready).className = 'fas ' + (typeIcons[data.type] || 'fa-share-alt');
        $('[data-share-title]', ready).textContent = data.title;
        $('[data-share-intro]', ready).textContent = data.intro;
        $('[data-share-limits]', ready).textContent = data.limits;
        $('[data-share-reveal-label]', ready).textContent = data.type === 'File' ? 'Download' : (data.type === 'Document' ? 'Open the document' : 'Reveal');
        $('[data-share-reveal]', ready).querySelector('i').className = 'fas me-2 ' + (data.type === 'File' ? 'fa-download' : 'fa-eye');
        $('[data-share-reveal-hint]', ready).textContent = data.hint || '';
        note(ready, data.note);
        error('ready', '');
    }

    // The passphrase
    $('[data-share-state="passphrase"]').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.currentTarget;
        const button = form.querySelector('button[type=submit]');
        busy(button, true);
        post('passphrase', { passphrase: form.passphrase.value }).then(function (data) {
            busy(button, false);
            if (data.state === 'gone') {
                gone(data.reason);
            } else if (data.error) {
                error('passphrase', data.error);
                form.passphrase.select();
            } else {
                form.passphrase.value = '';
                open(data);
            }
        });
    });

    // The emailed code
    function sendCode(button) {
        const form = $('[data-share-state="code"]');
        busy(button, true);
        post('send_code').then(function (data) {
            busy(button, false);
            if (data.state === 'gone') {
                gone(data.reason);
                return;
            }
            error('code', data.error || '');
            if (!data.error) {
                $('[data-share-code-sent]', form).hidden = false;
                $('[data-share-send-code]', form).hidden = true;
                $('[data-share-code-entry]', form).hidden = false;
                form.code.focus();
            }
        });
    }
    $('[data-share-send-code]').addEventListener('click', function (e) {
        sendCode(e.currentTarget);
    });
    $('[data-share-resend]').addEventListener('click', function (e) {
        sendCode(e.currentTarget);
    });
    $('[data-share-state="code"]').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.currentTarget;
        const button = form.querySelector('button[type=submit]');
        busy(button, true);
        post('code', { code: form.code.value }).then(function (data) {
            busy(button, false);
            if (data.state === 'gone') {
                gone(data.reason);
            } else if (data.error) {
                error('code', data.error);
                form.code.select();
            } else {
                form.code.value = '';
                open(data);
            }
        });
    });

    // Reveal - this is what uses a view
    $('[data-share-reveal]').addEventListener('click', function (e) {
        const button = e.currentTarget;
        if (link.type === 'File') {
            download(button);
            return;
        }
        busy(button, true);
        post('reveal').then(function (data) {
            busy(button, false);
            if (data.state === 'gone') {
                gone(data.reason);
            } else if (data.error) {
                error('ready', data.error);
            } else {
                reveal(data);
            }
        });
    });

    function reveal(data) {
        content = data;
        if (data.type === 'Credential') {
            const view = show('credential');
            $('[data-share-name]', view).textContent = data.name;
            $('[data-share-uri-row]', view).hidden = !data.uri;
            $('[data-share-uri]', view).value = data.uri || '';
            const open = $('[data-share-uri-open]', view);
            open.hidden = !/^https?:\/\//i.test(data.uri || '');
            open.href = open.hidden ? '#' : data.uri;
            $('[data-share-username]', view).value = data.username;
            $('[data-share-password]', view).value = data.password;
            $('[data-share-otp-row]', view).hidden = !data.has_otp;
            note(view, link.note);
            $('[data-share-after]', view).textContent = data.after;
            if (data.has_otp) {
                refreshOtp();
            }
        } else if (data.type === 'Note') {
            const view = show('note');
            $('[data-share-name]', view).textContent = data.name;
            $('[data-share-text]', view).textContent = data.text;
            note(view, link.note);
            $('[data-share-after]', view).textContent = data.after;
        } else if (data.type === 'Document') {
            const view = show('document');
            $('[data-share-name]', view).textContent = data.name;
            // Cleaned on the server (HTMLPurifier) - the only HTML that goes in as HTML
            $('[data-share-document]', view).innerHTML = data.html;
            note(view, link.note);
            $('[data-share-after]', view).textContent = data.after;
            if (typeof prettyContent === 'function') {
                prettyContent();
            }
        }
    }

    // A file comes down as a download - an error comes back as JSON instead
    function download(button) {
        busy(button, true);
        fetch('/guest/guest_download_file.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({ id: id, key: key })
        }).then(function (res) {
            if ((res.headers.get('Content-Type') || '').indexOf('application/json') === 0) {
                return res.json().then(function (data) {
                    busy(button, false);
                    if (data.state === 'gone') {
                        gone(data.reason);
                    } else {
                        error('ready', data.error || 'The file could not be downloaded.');
                    }
                });
            }
            const after = res.headers.get('X-Share-After') || '';
            return res.blob().then(function (blob) {
                busy(button, false);
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = link.file_name || 'download';
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
                const view = show('downloaded');
                $('[data-share-name]', view).textContent = link.file_name;
                $('[data-share-after]', view).textContent = decodeURIComponent(after);
            });
        }, function () {
            busy(button, false);
            error('ready', 'Could not reach the server - check your connection and try again.');
        });
    }

    // 2FA codes - asked for each time one runs out, while the page stays open
    function stopOtp() {
        clearTimeout(otpTimer);
        clearInterval(barTimer);
    }

    function refreshOtp() {
        stopOtp();
        post('totp').then(function (data) {
            const field = $('[data-share-otp]');
            const bar = $('[data-share-otp-bar]');
            if (!data.code) {
                field.value = data.error ? 'Ended' : '------';
                bar.style.width = '0%';
                return;
            }
            field.value = data.code.slice(0, 3) + ' ' + data.code.slice(3);
            const ends = Date.now() + data.expires_in * 1000;
            const tick = function () {
                const left = Math.max(0, ends - Date.now());
                bar.style.width = (left / 300) + '%';
                bar.classList.toggle('bg-warning', left < 7000);
            };
            tick();
            barTimer = setInterval(tick, 250);
            otpTimer = setTimeout(refreshOtp, data.expires_in * 1000 + 300);
        });
    }

    page.addEventListener('click', function (e) {
        const button = e.target.closest('[data-share-copy]');
        if (!button || !content) {
            return;
        }
        const what = button.dataset.shareCopy;
        const value = what === 'otp' ? $('[data-share-otp]').value.replace(/\s/g, '') : content[what];
        if (value) {
            copy(button, value);
        }
    });

    // Done with it - nobody can open it again
    $$('[data-share-destroy]').forEach(function (button) {
        button.addEventListener('click', function () {
            busy(button, true);
            post('destroy').then(function (data) {
                busy(button, false);
                stopOtp();
                content = null;
                if (data.state === 'destroyed') {
                    show('destroyed');
                } else {
                    gone(data.reason || data.error);
                }
            });
        });
    });

    // A request - send the login back
    $('[data-share-state="request"]').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.currentTarget;
        if (!form.username.value.trim() && !form.password.value) {
            error('request', 'Fill in the username, the password, or both.');
            return;
        }
        const button = form.querySelector('button[type=submit]');
        busy(button, true);
        post('submit', {
            uri: form.uri.value,
            username: form.username.value,
            password: form.password.value,
            otp: form.otp.value,
            note: form.note.value
        }).then(function (data) {
            busy(button, false);
            if (data.state === 'gone') {
                gone(data.reason);
            } else if (data.error) {
                error('request', data.error);
            } else {
                form.reset();
                show('sent');
            }
        });
    });

    if (!id || !key) {
        gone('The link is not complete - copy the whole of it from the email, including everything after the #.');
        return;
    }
    post('open').then(open);

})();
