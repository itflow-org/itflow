// The placeholders under a contract's terms editor (includes/contract_placeholders.php):
// a click puts the [word] in at the editor's cursor - or at the end of the
// text, when the editor isn't there. Bound once on the document, as a modal
// can load this file more than once.

(function () {
    if (window.itflowContractPlaceholders) {
        return;
    }
    window.itflowContractPlaceholders = true;

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-contract-placeholder]');
        if (!button) {
            return;
        }
        event.preventDefault();
        const word = button.getAttribute('data-contract-placeholder');
        const pane = button.closest('.tab-pane') || button.closest('form') || document;
        const textarea = pane.querySelector('textarea[name="details"]');
        if (!textarea) {
            return;
        }
        const editor = window.tinymce ? tinymce.get().filter(function (ed) { return ed.getElement() === textarea; })[0] : null;
        if (editor) {
            editor.focus();
            editor.insertContent(word);
        } else {
            textarea.value += word;
            textarea.focus();
        }
    });
})();
