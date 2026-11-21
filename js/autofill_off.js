/*
 * Keeps the browser's autofill and password managers out of ITFlow's forms.
 * What agents type here is about clients, their people and their logins - not
 * about the agent - so Chrome or Edge offering the agent's own name, email,
 * phone or address, or Bitwarden, 1Password, LastPass or Dashlane offering the
 * agent's ITFlow login, only fills in the wrong thing.
 *
 * Each field gets:
 * - an autocomplete value no browser knows, so Chromium browsers offer nothing.
 *   They ignore autocomplete="off" for names and addresses, but not this.
 * - new-password on a password field instead, so the saved ITFlow login isn't
 *   offered.
 * - each password manager's own attribute for leaving a field alone, which also
 *   stops them offering to save what's typed.
 *
 * Left as they are:
 * - fields asking for the agent's own password or code (current-password,
 *   username, one-time-code);
 * - fields with another real autocomplete value someone chose on purpose;
 * - anything inside [data-autofill] - the agent's own profile and password
 *   change.
 *
 * Fields on the page are done straight away, and any added later (windows,
 * quick add) as they arrive - and again if a script sets autocomplete="off"
 * on one afterwards. Agent and admin pages only (includes/footer.php).
 */
(function () {
    'use strict';

    const SELECTOR = 'input, textarea, select';
    const SKIP_TYPES = ['hidden', 'checkbox', 'radio', 'file', 'submit', 'button', 'reset', 'image', 'range', 'color'];
    const NONE = 'itflow-none';

    function guard(field) {
        if ((field.tagName === 'INPUT' && SKIP_TYPES.indexOf(field.type) !== -1)
            || field.closest('[data-autofill]')) {
            return;
        }
        const current = (field.getAttribute('autocomplete') || '').trim().toLowerCase();
        if (current !== '' && current !== 'off' && current !== 'new-password') {
            return;
        }
        const value = field.type === 'password' || current === 'new-password' ? 'new-password' : NONE;
        if (current !== value) {
            field.setAttribute('autocomplete', value);
        }
        if (field.hasAttribute('data-autofill-off')) {
            return;
        }
        field.setAttribute('data-1p-ignore', '');
        field.setAttribute('data-lpignore', 'true');
        field.setAttribute('data-bwignore', 'true');
        field.setAttribute('data-form-type', 'other');
        field.setAttribute('data-autofill-off', '');
    }

    function guardWithin(node) {
        if (node.nodeType !== 1) {
            return;
        }
        if (node.matches(SELECTOR)) {
            guard(node);
        }
        node.querySelectorAll(SELECTOR).forEach(guard);
    }

    guardWithin(document.documentElement);

    // New fields, and a field's script setting autocomplete="off" on it again (js/autocomplete.js)
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'attributes') {
                if (mutation.target.hasAttribute('data-autofill-off')) {
                    guard(mutation.target);
                }
                return;
            }
            mutation.addedNodes.forEach(guardWithin);
        });
    }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['autocomplete'] });
})();
