/*
 * The setup wizard (setup/index.php, setup/steps/) - what its forms do in the
 * browser before they are sent. The server checks everything again
 * (setup/setup_forms.php); this says so sooner, beside the field.
 *
 *   form[data-setup-form]       checked on submit - each field that isn't
 *                               right says why under it, and the first gets
 *                               the focus
 *   [data-setup-same-as=id]     has to match that field (password again)
 *   [data-setup-image=kinds]    a picture of those kinds, up to
 *                               data-setup-image-max bytes, shown in
 *                               [data-setup-preview=its id]
 *   [data-setup-browser-timezone]  starts on the browser's timezone
 *   form[data-setup-region]     shows money and a date as they will look
 *   [data-setup-reveal=id]      a switch that shows that element while on
 *   form[data-restore-form]     a backup dropped or picked, sent with its progress
 *
 * And every setup form shows it is working once sent, and is sent once.
 */
(function () {
    'use strict';

    function selectValue(select, value) {
        if (select.itflowSelect) {
            select.itflowSelect.setValue(value);
        } else {
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // Where a field says what is wrong with it - made the first time, after its input group
    function feedbackFor(field) {
        var anchor = field.closest('.input-group') || field;
        var next = anchor.nextElementSibling;
        if (next && next.matches('[data-setup-feedback]')) {
            return next;
        }
        var feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.setAttribute('data-setup-feedback', field.id || field.name);
        anchor.insertAdjacentElement('afterend', feedback);
        return feedback;
    }

    // What the server said about a field, under it on the page as it came back
    function serverErrorFor(field) {
        var col = field.closest('[class*="col"]');
        return col ? col.querySelector('[data-field-error="' + field.name + '"]') : null;
    }

    function showField(field, message) {
        field.classList.toggle('is-invalid', message !== '');
        var feedback = feedbackFor(field);
        feedback.textContent = message;
        feedback.classList.toggle('d-block', message !== '');
        var server = serverErrorFor(field);
        if (server && message === '') {
            server.classList.remove('d-block');
            server.hidden = true;
        }
    }

    function checkSameAs(field) {
        var other = document.getElementById(field.dataset.setupSameAs);
        field.setCustomValidity(other && field.value !== '' && field.value !== other.value ? 'The two passwords are not the same.' : '');
    }

    function checkImage(input) {
        var file = input.files && input.files[0];
        var message = '';
        if (file) {
            var accepted = (input.getAttribute('accept') || '').split(',').map(function (type) { return type.trim(); });
            if (accepted.length && accepted.indexOf(file.type) === -1) {
                message = "That isn't a " + input.dataset.setupImage + ' picture.';
            } else if (file.size > Number(input.dataset.setupImageMax || 0)) {
                message = 'That picture is too large - up to ' + Math.round(Number(input.dataset.setupImageMax) / 1048576) + ' MB.';
            }
        }
        input.setCustomValidity(message);
        var preview = document.querySelector('[data-setup-preview="' + input.id + '"]');
        if (preview) {
            if (preview.dataset.url) {
                URL.revokeObjectURL(preview.dataset.url);
                delete preview.dataset.url;
            }
            var img = preview.querySelector('img');
            if (file && !message) {
                if (!img) {
                    img = document.createElement('img');
                    img.alt = '';
                    preview.appendChild(img);
                }
                preview.dataset.url = URL.createObjectURL(file);
                img.src = preview.dataset.url;
                preview.classList.add('has-image');
            } else {
                if (img) {
                    img.remove();
                }
                preview.classList.remove('has-image');
            }
        }
        return message;
    }

    function initForm(form) {
        form.querySelectorAll('[data-setup-same-as]').forEach(function (field) {
            var other = document.getElementById(field.dataset.setupSameAs);
            [field, other].forEach(function (el) {
                if (el) {
                    el.addEventListener('input', function () {
                        checkSameAs(field);
                        if (field.classList.contains('is-invalid')) {
                            showField(field, field.validationMessage);
                        }
                    });
                }
            });
        });

        form.querySelectorAll('[data-setup-image]').forEach(function (input) {
            input.addEventListener('change', function () {
                showField(input, checkImage(input));
            });
        });

        // A field put right stops saying it is wrong
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            ['input', 'change'].forEach(function (type) {
                field.addEventListener(type, function () {
                    if (field.classList.contains('is-invalid') && field.checkValidity()) {
                        showField(field, '');
                    }
                });
            });
        });

        form.addEventListener('submit', function (e) {
            var first = null;
            form.querySelectorAll('[data-setup-same-as]').forEach(checkSameAs);
            form.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (!field.willValidate || field.type === 'hidden') {
                    return;
                }
                var ok = field.checkValidity();
                if (ok && !field.classList.contains('is-invalid')) {
                    return;
                }
                showField(field, ok ? '' : field.validationMessage);
                if (!ok && !first) {
                    first = field;
                }
            });
            if (first) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (first.itflowSelect && first.itflowSelect.control) {
                    first.itflowSelect.control.focus();
                } else {
                    first.focus();
                }
            }
        });
    }

    // Sent once, and showing it is on its way - creating the tables or restoring takes a while
    function initBusy(form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) {
                return;
            }
            if (form.dataset.setupSent) {
                e.preventDefault();
                return;
            }
            form.dataset.setupSent = '1';
            var button = e.submitter || form.querySelector('[type="submit"]');
            if (button) {
                button.classList.add('disabled');
                button.setAttribute('aria-disabled', 'true');
                button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
            }
        });
    }

    function initRegion(form) {
        var locale = form.querySelector('[name="locale"]');
        var currency = form.querySelector('[name="currency_code"]');
        var timezone = form.querySelector('[name="timezone"]');
        var sample = form.querySelector('[data-setup-sample]');

        if (timezone && timezone.hasAttribute('data-setup-browser-timezone')) {
            var browserZone = '';
            try {
                browserZone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            } catch (err) {
                browserZone = '';
            }
            var has = Array.prototype.some.call(timezone.options, function (option) { return option.value === browserZone; });
            if (browserZone && has) {
                selectValue(timezone, browserZone);
                var col = timezone.closest('[class*="col"]');
                var hint = col.querySelector('[data-setup-suggested="timezone"]');
                if (!hint) {
                    hint = document.createElement('div');
                    hint.className = 'form-text';
                    hint.setAttribute('data-setup-suggested', 'timezone');
                    col.appendChild(hint);
                }
                hint.textContent = "Your browser's timezone.";
            }
        }

        function draw() {
            var tag = (locale.value || '').replace(/_/g, '-');
            if (!sample || !tag || !currency.value) {
                if (sample) {
                    sample.hidden = true;
                }
                return;
            }
            try {
                sample.querySelector('[data-setup-sample-money]').textContent = new Intl.NumberFormat(tag, { style: 'currency', currency: currency.value }).format(1234.56);
                sample.querySelector('[data-setup-sample-date]').textContent = new Intl.DateTimeFormat(tag, { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone.value || undefined }).format(new Date());
                sample.hidden = false;
            } catch (err) {
                sample.hidden = true;
            }
        }
        [locale, currency, timezone].forEach(function (select) {
            select.addEventListener('change', draw);
        });
        draw();
    }

    // A switch that shows what goes with it - the comment sent with sharing
    function initReveal(toggle) {
        var target = document.getElementById(toggle.dataset.setupReveal);
        if (!target) {
            return;
        }
        var sync = function () {
            target.hidden = !toggle.checked;
        };
        toggle.addEventListener('change', sync);
        sync();
    }

    function formatBytes(bytes) {
        var units = ['bytes', 'KB', 'MB', 'GB'];
        var i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return (i === 0 ? bytes : bytes.toFixed(bytes < 10 ? 1 : 0)) + ' ' + units[i];
    }

    /*
     * Restore a backup - dropped or picked, checked against what this server
     * takes, then sent with how far it has got shown. The server answers in
     * JSON (setupGo() in setup/index.php) with where to go next.
     */
    function initRestore(form) {
        var input = form.querySelector('input[name="backup_zip"]');
        var drop = form.querySelector('[data-setup-drop]');
        var tooBig = form.querySelector('[data-restore-too-big]');
        var progress = form.querySelector('[data-restore-progress]');
        var failed = form.querySelector('[data-restore-failed]');
        if (!input || !drop || !progress) {
            return;
        }
        var name = drop.querySelector('[data-setup-drop-name]');
        var size = drop.querySelector('[data-setup-drop-size]');
        var nameText = name.textContent;
        var sizeText = size.textContent;
        var max = Number(input.dataset.setupMaxBytes || 0);
        var bar = progress.querySelector('.progress-bar');
        var label = progress.querySelector('[data-restore-progress-label]');
        var amount = progress.querySelector('[data-restore-progress-amount]');

        function chosen() {
            var file = input.files && input.files[0];
            var message = '';
            if (file && !/\.zip$/i.test(file.name)) {
                message = 'Only .zip backup archives can be restored.';
            }
            var big = !!file && message === '' && max > 0 && file.size > max;
            if (big) {
                message = 'Larger than this server takes through a browser - restore it from the command line.';
            }
            input.setCustomValidity(message);
            tooBig.hidden = !big;
            drop.classList.toggle('has-file', !!file);
            name.textContent = file ? file.name : nameText;
            size.textContent = file ? formatBytes(file.size) : sizeText;
            showField(input, message);
        }
        input.addEventListener('change', chosen);

        ['dragenter', 'dragover'].forEach(function (type) {
            drop.addEventListener(type, function (e) {
                e.preventDefault();
                drop.classList.add('is-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            drop.addEventListener(type, function (e) {
                e.preventDefault();
                drop.classList.remove('is-over');
            });
        });
        drop.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                chosen();
            }
        });

        function fail(message) {
            progress.hidden = true;
            failed.textContent = message;
            failed.hidden = false;
            delete form.dataset.setupSent;
            form.querySelectorAll('[aria-disabled="true"]').forEach(function (button) {
                button.classList.remove('disabled');
                button.removeAttribute('aria-disabled');
                var spinner = button.querySelector('.spinner-border');
                if (spinner) {
                    spinner.remove();
                }
            });
        }

        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented || !window.FormData || !window.XMLHttpRequest) {
                return;
            }
            e.preventDefault();

            var data = new FormData(form);
            data.append('restore', '1');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.getAttribute('action') || window.location.href);
            xhr.setRequestHeader('X-Setup-Upload', '1');

            failed.hidden = true;
            progress.hidden = false;
            label.textContent = 'Sending the backup';
            bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
            bar.style.width = '0%';

            xhr.upload.addEventListener('progress', function (ev) {
                if (ev.lengthComputable) {
                    var percent = Math.round(ev.loaded * 100 / ev.total);
                    bar.style.width = percent + '%';
                    bar.parentNode.setAttribute('aria-valuenow', percent);
                    amount.textContent = formatBytes(ev.loaded) + ' of ' + formatBytes(ev.total);
                }
            });
            xhr.upload.addEventListener('load', function () {
                label.textContent = 'Restoring - the database, then the files';
                amount.textContent = '';
                bar.style.width = '100%';
                bar.classList.add('progress-bar-striped', 'progress-bar-animated');
            });
            xhr.addEventListener('load', function () {
                var next = '';
                try {
                    next = JSON.parse(xhr.responseText).location || '';
                } catch (err) {
                    next = '';
                }
                if (next) {
                    window.location.href = next;
                    return;
                }
                fail('The restore stopped with an error' + (xhr.status ? ' (' + xhr.status + ')' : '') + " - the web server's error log says why. Restoring from the command line has no time or size limit.");
            });
            xhr.addEventListener('error', function () {
                fail('The upload was cut off. Check the connection - or restore from the command line, which has no size limit.');
            });
            xhr.send(data);
        });
    }

    function init() {
        document.querySelectorAll('[data-setup-reveal]').forEach(initReveal);
        document.querySelectorAll('form[data-setup-form]').forEach(initForm);
        document.querySelectorAll('.itflow-setup form').forEach(initBusy);
        document.querySelectorAll('form[data-setup-region]').forEach(initRegion);
        document.querySelectorAll('form[data-restore-form]').forEach(initRestore);
    }

    // Back to a page the browser kept as it was left - sent and spinning - ready to send again
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) {
            return;
        }
        document.querySelectorAll('.itflow-setup form[data-setup-sent]').forEach(function (form) {
            delete form.dataset.setupSent;
            form.querySelectorAll('[aria-disabled="true"]').forEach(function (button) {
                button.classList.remove('disabled');
                button.removeAttribute('aria-disabled');
                var spinner = button.querySelector('.spinner-border');
                if (spinner) {
                    spinner.remove();
                }
            });
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
