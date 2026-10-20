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

    function init() {
        document.querySelectorAll('form[data-setup-form]').forEach(initForm);
        document.querySelectorAll('.itflow-setup form').forEach(initBusy);
        document.querySelectorAll('form[data-setup-region]').forEach(initRegion);
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
