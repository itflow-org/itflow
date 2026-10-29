/*
 * The canned response window (includes/canned_response_form.php):
 * a placeholder under the editor goes in where the cursor is. The window is an
 * ajax modal that runs this each time it opens, so the listener is bound once.
 */
itflowBindOnce('itflowCannedPlaceholder', 'click', '[data-canned-placeholder]', function () {
    const text = '[' + this.dataset.cannedPlaceholder + ']';
    const editor = window.tinymce ? tinymce.get('cannedResponseBody') : null;
    if (editor) {
        editor.insertContent(text);
        editor.focus();
        return;
    }
    const textarea = document.getElementById('cannedResponseBody');
    if (textarea) {
        const at = textarea.selectionStart ?? textarea.value.length;
        textarea.value = textarea.value.slice(0, at) + text + textarea.value.slice(textarea.selectionEnd ?? at);
        textarea.focus();
    }
});
