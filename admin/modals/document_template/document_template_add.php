<?php

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt me-2"></i>Creating Document Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <input type="text" class="form-control" name="name" placeholder="Template name" maxlength="200">
        </div>

        <div class="mb-3">
            <label>Enter a prompt for the type of IT documentation you want to generate:</label>
            <div class="input-group mb-3">
                <input type="text" class="form-control" id="aiPrompt" placeholder="e.g. 'A network troubleshooting guide for junior technicians'">
                    <button class="btn btn-info" type="button" id="generateAIContent">
                        <i class="fa fa-fw fa-magic me-1"></i>Generate with AI
                    </button>
            </div>
        </div>

        <!-- TinyMCE Content -->
        <div class="mb-3">
            <textarea class="form-control tinymce" name="content"></textarea>
        </div>

        <div class="mb-3">
            <input type="text" class="form-control" name="description" placeholder="Enter a short summary">
        </div>

    </div>

    <div class="modal-footer">

        <button type="submit" name="add_document_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save template</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>

    </div>
</form>

<script>
itflowReady(function () {

    var button = document.getElementById('generateAIContent');
    var promptField = document.getElementById('aiPrompt');
    if (!button || !promptField) {
        return;
    }

    button.addEventListener('click', function () {
        var prompt = promptField.value.trim();
        if (prompt === '') {
            alert('Please enter a prompt.');
            return;
        }

        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

        fetch('/agent/ajax.php?ai_create_document_template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ prompt: prompt }).toString()
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (response) {
                // Assuming you have exactly one TinyMCE instance on the page
                // and it's targeting the .tinymce textarea:
                tinymce.activeEditor.setContent(response);
            })
            .catch(function () {
                alert('Error generating content. Please try again.');
            })
            .finally(function () {
                button.disabled = false;
                button.innerHTML = '<i class="fa fa-fw fa-magic me-1"></i>Generate with AI';
            });
    });
});
</script>

<?php
require_once '../../../includes/modal_footer.php';
