/*
 * The document template window (admin/modals/document_template/document_template_form.php):
 * a placeholder under the editor goes in where the cursor is, and a table of the
 * client's records goes in on a line of its own. The window is an ajax modal that
 * runs this each time it opens, so the listener is bound once.
 */
itflowBindOnce('itflowDocumentTemplateWord', 'click', '[data-document-template-word]', function () {
    const word = '[' + this.dataset.documentTemplateWord + ']';
    const table = this.hasAttribute('data-document-template-table');
    const editor = window.tinymce ? tinymce.get('documentTemplateContent') : null;
    if (editor) {
        editor.insertContent(table ? '<p>' + word + '</p>' : word);
        editor.focus();
        return;
    }
    const textarea = document.getElementById('documentTemplateContent');
    if (textarea) {
        const at = textarea.selectionStart ?? textarea.value.length;
        const text = table ? '<p>' + word + '</p>' : word;
        textarea.value = textarea.value.slice(0, at) + text + textarea.value.slice(textarea.selectionEnd ?? at);
        textarea.focus();
    }
});
