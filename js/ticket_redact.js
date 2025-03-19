// Redact the selected text in TinyMCE
function redactSelectedText() {
    const editor = tinymce.get('tinymceTicketRedact');  // Get TinyMCE editor instance
    const selectedText = editor.selection.getContent();  // Get selected content

    if (selectedText) {
        // Wrap the selected text with a redacted span
        const redactedNode = `<strong><span style="color: #e03e2d;">[REDACTED]</span></strong>`;

        // Replace the selected text with the redacted span
        editor.selection.setContent(redactedNode);
    } else {
        alert('Please select some text to redact.');
    }
}
