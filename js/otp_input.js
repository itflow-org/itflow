/*
 * A 6-digit code in six boxes - signing in with MFA, setting it up, and share
 * codes (otpCodeInput() in functions/user.php draws the field).
 *
 * The one real field stays, see-through, over the boxes: it is what the form
 * sends, what phones and password managers fill in, and what a screen reader
 * reads. The boxes only show its digits, with the next one to type marked.
 * Typing always adds at the end and Backspace takes the last one off; a pasted
 * code ("123 456" too) fills them all. With data-otp-submit the form is sent
 * once the sixth digit is in, as if its first button were pressed.
 *
 * Fields on the page are done on load; window.itflowOtp(input) does one added
 * later. Setting the field's value from script redraws the boxes, and the
 * is-invalid class on it turns them red until the next digit.
 */
(function () {
    'use strict';

    const native = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

    function init(input) {
        if (!input || input.dataset.otpReady) {
            return;
        }
        input.dataset.otpReady = '1';

        const length = Number(input.getAttribute('maxlength')) || 6;
        const wrap = input.parentNode;
        const boxes = [];
        for (let i = 0; i < length; i++) {
            if (length === 6 && i === 3) {
                const sep = document.createElement('span');
                sep.className = 'itflow-otp-sep';
                sep.setAttribute('aria-hidden', 'true');
                wrap.append(sep);
            }
            const box = document.createElement('span');
            box.className = 'itflow-otp-slot';
            box.setAttribute('aria-hidden', 'true');
            wrap.append(box);
            boxes.push(box);
        }
        wrap.classList.add('is-ready');

        let sent = '';

        function digits(text) {
            return String(text).replace(/\D/g, '').slice(0, length);
        }

        function value() {
            return native.get.call(input);
        }

        // All of it selected - typing replaces the code
        function allSelected() {
            const n = value().length;
            return n > 0 && input.selectionStart === 0 && input.selectionEnd === n;
        }

        function render() {
            const text = value();
            const focused = document.activeElement === input;
            const all = focused && allSelected();
            const at = Math.min(text.length, length - 1);
            boxes.forEach(function (box, i) {
                box.textContent = text.charAt(i);
                box.classList.toggle('is-filled', i < text.length);
                box.classList.toggle('is-active', focused && (all || i === at));
                box.classList.toggle('is-caret', focused && !all && i === text.length);
            });
            wrap.classList.toggle('is-invalid', input.classList.contains('is-invalid'));
        }

        // The caret stays at the end, unless it's all selected
        function toEnd() {
            const n = value().length;
            if (document.activeElement === input && !allSelected() && (input.selectionStart !== n || input.selectionEnd !== n)) {
                input.setSelectionRange(n, n);
            }
            render();
        }

        function submit() {
            const form = input.form;
            const text = value();
            if (!('otpSubmit' in input.dataset) || !form || text.length !== length || text === sent || !form.checkValidity()) {
                return;
            }
            sent = text;
            const button = form.querySelector('button[type=submit], input[type=submit]');
            if (form.requestSubmit) {
                form.requestSubmit(button || undefined);
            } else if (button) {
                button.click();
            } else {
                form.submit();
            }
        }

        // A value set from script redraws the boxes
        Object.defineProperty(input, 'value', {
            configurable: true,
            get: value,
            set: function (text) {
                native.set.call(input, text);
                if (value().length < length) {
                    sent = '';
                }
                render();
            }
        });

        input.addEventListener('input', function () {
            const clean = digits(value());
            if (clean !== value()) {
                native.set.call(input, clean);
            }
            if (clean.length < length) {
                sent = '';
            }
            input.classList.remove('is-invalid');
            toEnd();
            submit();
        });

        input.addEventListener('paste', function (e) {
            const pasted = digits((e.clipboardData || window.clipboardData).getData('text'));
            e.preventDefault();
            if (!pasted) {
                return;
            }
            native.set.call(input, pasted.length >= length || allSelected() ? pasted : digits(value() + pasted));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        ['focus', 'click', 'keyup', 'select'].forEach(function (type) {
            input.addEventListener(type, toEnd);
        });
        input.addEventListener('blur', render);

        new MutationObserver(render).observe(input, { attributes: true, attributeFilter: ['class'] });

        render();
    }

    window.itflowOtp = init;

    function initAll() {
        document.querySelectorAll('input[data-otp]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
