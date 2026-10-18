<?php
/*
 * A document template's fields - shared by its New and Edit windows
 * (document_template_add.php, document_template_edit.php), which set
 * $template (documentTemplateRow(), or [] for a new one) before including it.
 * Posts what documentTemplateFields() reads (functions/document_template.php).
 * With an AI model set up for documentation, it can be drafted from a prompt.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$t = $template;
$ai_model = getAiModel('Documentation');

?>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
            <input type="text" class="form-control" name="name" placeholder="What it is - Network Overview" maxlength="200" value="<?= escapeHtml($t['document_template_name'] ?? '') ?>" required autofocus>
        </div>
    </div>
    <div class="col-lg-7">
        <label class="form-label">Summary</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" placeholder="One line on what it covers - shown when an agent picks a template" maxlength="500" value="<?= escapeHtml($t['document_template_description'] ?? '') ?>">
        </div>
    </div>
</div>

<?php if ($ai_model) { ?>
    <div class="mb-3">
        <label class="form-label" for="documentTemplatePrompt">Draft it with AI</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-magic"></i></span>
            <input type="text" class="form-control" id="documentTemplatePrompt" placeholder="What it is for - a network troubleshooting guide for junior technicians" data-document-template-prompt>
            <button class="btn btn-info" type="button" data-document-template-generate><i class="fa fa-fw fa-magic me-1"></i>Draft</button>
        </div>
        <small class="text-secondary">Replaces what is in the editor below.</small>
    </div>
<?php } ?>

<div class="mb-2">
    <label class="form-label" for="documentTemplateContent">What it says</label>
    <textarea class="form-control tinymce" id="documentTemplateContent" name="content"><?= escapeHtml($t['document_template_content'] ?? '') ?></textarea>
</div>
<div class="small text-secondary">Words in square brackets - <span class="font-monospace">[Client]</span>, <span class="font-monospace">[Date]</span> - are left in the document for whoever writes it to fill in. Pasted images are kept with the template and copied into each document made from it.</div>

<?php if ($ai_model) { ?>
<script>
    itflowBindOnce('itflowDocumentTemplateDraft', 'click', '[data-document-template-generate]', function () {
        var button = this;
        var prompt = document.querySelector('[data-document-template-prompt]');
        if (!prompt || prompt.value.trim() === '') {
            if (prompt) {
                prompt.focus();
            }
            return;
        }
        var label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Drafting...';
        fetch('/agent/ajax.php?ai_create_document_template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ prompt: prompt.value.trim() }).toString()
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                var editor = window.tinymce ? tinymce.get('documentTemplateContent') : null;
                if (editor) {
                    editor.setContent(html);
                } else {
                    document.getElementById('documentTemplateContent').value = html;
                }
            })
            .catch(function () {
                itflowToast('Could not draft it - try again.', 'error');
            })
            .finally(function () {
                button.disabled = false;
                button.innerHTML = label;
            });
    });
</script>
<?php } ?>
