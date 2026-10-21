<?php

/*
 * New document - in the folder it was opened from, or linked to the asset,
 * contact, license, location or vendor it was opened from.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$contact_id = assetClientOwnedId('contact', $_GET['contact_id'] ?? 0, $client_id);
$asset = linkRecord('asset', $_GET['asset_id'] ?? 0);
$asset_id = $asset && intval($asset['client_id']) === $client_id ? intval($asset['id']) : 0;
$software = linkRecord('software', $_GET['software_id'] ?? 0);
$software_id = $software && intval($software['client_id']) === $client_id ? intval($software['id']) : 0;
$location_id = assetClientOwnedId('location', $_GET['location_id'] ?? 0, $client_id);
$vendor_id = assetClientOwnedId('vendor', $_GET['vendor_id'] ?? 0, $client_id);
$service = linkRecord('service', $_GET['service_id'] ?? 0);
$service_id = $service && intval($service['client_id']) === $client_id && !$service['archived_at'] ? intval($service['id']) : 0;
$link_name = $asset_id ? $asset['name'] : ($contact_id ? getFieldById('contacts', $contact_id, 'contact_name') : ($software_id ? $software['name'] : ($location_id ? getFieldById('locations', $location_id, 'location_name') : ($vendor_id ? getFieldById('vendors', $vendor_id, 'vendor_name') : ($service_id ? $service['name'] : '')))));

$document_form_mode = 'add';
$document_name = $document_description = $document_content = '';
$document_folder_id = folderOwnedId($_GET['folder_id'] ?? 0, $client_id);
$document_client_visible = 0;
// Drafted by AI when a model is set up for documentation and the role may use AI
$ai_draft = aiReady('Documentation');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-alt me-2"></i>New document<?php if ($link_name) { ?> <small class="opacity-75 ms-1">for <?= escapeHtml($link_name) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="contact" value="<?= $contact_id ?>">
    <input type="hidden" name="asset" value="<?= $asset_id ?>">
    <input type="hidden" name="software" value="<?= $software_id ?>">
    <input type="hidden" name="location" value="<?= $location_id ?>">
    <input type="hidden" name="vendor" value="<?= $vendor_id ?>">
    <input type="hidden" name="service" value="<?= $service_id ?>">
    <div class="modal-body">
        <?php if ($ai_draft) { ?>
            <div class="mb-3 p-3 border rounded bg-body-tertiary" data-ai-document-draft data-client-id="<?= $client_id ?>">
                <label class="form-label" for="aiDocumentPrompt">Draft it with AI</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-magic"></i></span>
                    <input type="text" class="form-control" id="aiDocumentPrompt" maxlength="500" placeholder="What it is for - a disaster recovery plan for the main office" data-ai-document-prompt>
                    <button class="btn btn-info" type="button" data-ai-document-go><i class="fa fa-fw fa-magic me-1"></i>Draft</button>
                </div>
                <div class="form-check mt-2 mb-0">
                    <input class="form-check-input" type="checkbox" id="aiDocumentUseClient" checked data-ai-document-use-client>
                    <label class="form-check-label small" for="aiDocumentUseClient">Use what ITFlow knows about <?= escapeHtml(getFieldById('clients', $client_id, 'client_name') ?? 'them') ?> - their sites, people, servers, networks and software, never a password or key</label>
                </div>
                <div class="small mt-2 d-none" data-ai-document-said></div>
            </div>
        <?php } ?>
        <?php require 'document_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php if ($ai_draft) { ?>
<script>
// Draft it with AI - its name and summary when they are empty, and what it says, in one step Undo takes back
(function () {
    var box = document.querySelector('[data-ai-document-draft]:not([data-ready])');
    if (!box) {
        return;
    }
    box.setAttribute('data-ready', '1');
    var form = box.closest('form');
    var prompt = box.querySelector('[data-ai-document-prompt]');
    var go = box.querySelector('[data-ai-document-go]');
    var said = box.querySelector('[data-ai-document-said]');
    var textarea = form.querySelector('textarea[name="content"]');

    function say(tone, text) {
        said.className = 'small mt-2 text-' + tone + '-emphasis';
        said.textContent = text;
    }

    function draft() {
        if (prompt.value.trim() === '') {
            prompt.focus();
            return;
        }
        var label = go.innerHTML;
        go.disabled = true;
        go.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Drafting';
        say('body', 'AI is writing it - this can take a minute.');
        fetch('/agent/ajax.php?ai_document_draft', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ client_id: box.getAttribute('data-client-id'), prompt: prompt.value.trim(), use_client: box.querySelector('[data-ai-document-use-client]').checked ? '1' : '' }).toString()
        })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error('');
                });
            })
            .then(function (answer) {
                if (answer.error || !answer.html) {
                    throw new Error(answer.error || '');
                }
                var name = form.querySelector('[name="name"]');
                var description = form.querySelector('[name="description"]');
                if (name.value.trim() === '' && answer.title) {
                    name.value = answer.title;
                }
                if (description.value.trim() === '' && answer.description) {
                    description.value = answer.description;
                }
                var editor = window.tinymce ? tinymce.get().filter(function (ed) { return ed.getElement() === textarea; })[0] : null;
                if (editor) {
                    editor.undoManager.add();
                    editor.undoManager.transact(function () {
                        editor.setContent(answer.html);
                    });
                } else {
                    textarea.value = answer.html;
                }
                say('success', 'Drafted by AI - check it and fill in what is in [square brackets]. Undo in the editor puts back what was there.');
                said.setAttribute('data-ai-document-drafted', '');
            })
            .catch(function (error) {
                say('danger', error && error.message ? error.message : 'It couldn\'t be drafted - try again.');
            })
            .finally(function () {
                go.disabled = false;
                go.innerHTML = label;
            });
    }

    go.addEventListener('click', draft);
    prompt.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            draft();
        }
    });
})();
</script>
<?php } ?>

<?php
require_once '../../../includes/modal_footer.php';
