/*
 * ITFlow - submit a form as soon as one of its controls changes.
 *
 * Markup contract:
 *   <select name="status" data-autosubmit> ... </select>
 *
 * Replaces inline onchange="this.form.submit()". The client portal sends
 * Content-Security-Policy: default-src 'self', which blocks inline event
 * handlers outright, so any page using that pattern had to run without the
 * policy. A bound listener in a same-origin file is allowed.
 *
 * Delegated on document, so it needs no init call and also covers markup added
 * after load. requestSubmit() rather than submit(): it runs the form's own
 * validation and fires the submit event, the same as pressing a submit button.
 */
document.addEventListener('change', function (e) {
    const control = e.target.closest('[data-autosubmit]');
    if (!control || !control.form) {
        return;
    }

    if (typeof control.form.requestSubmit === 'function') {
        control.form.requestSubmit();
    } else {
        control.form.submit();
    }
});
