<?php

/*
 * Share a credential - a link that opens it once, or until it expires, for
 * someone outside (a client's new starter, a vendor's engineer). The same
 * share links as the page share modal (agent/modals/share_modal.php), made
 * through agent/ajax.php share_generate_link, as an ajax modal so it opens
 * from anywhere the credential does.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential');
enforceUserPermission('module_support', 2);

$credential_id = intval($_GET['id'] ?? 0);
$credential = credentialRow($credential_id);
if (!$credential) {
    echo json_encode(['error' => 'That credential no longer exists.']);
    exit;
}
$client_id = intval($credential['credential_client_id']);
enforceClientAccess();

// Who it might go to - the client's people with an email, or anyone typed in
$recipients = [];
$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_title, contact_photo, contact_client_id FROM contacts
    WHERE contact_client_id = $client_id AND contact_email <> '' AND contact_archived_at IS NULL ORDER BY contact_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $recipients[] = $row;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-share-alt me-2"></i>Share <strong><?= escapeHtml($credential['credential_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form data-credential-share autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="id" value="<?= $credential_id ?>">
    <div class="modal-body">
        <div data-share-form>
            <div class="mb-3">
                <label class="form-label">Send it to</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    <select class="form-select select2" name="contact_email" data-placeholder="Pick a contact or type an email - or leave it and copy the link" data-tags="true">
                        <option value=""></option>
                        <?php foreach ($recipients as $recipient) { ?>
                            <option value="<?= escapeHtml($recipient['contact_email']) ?>"<?= selectOptionContact($recipient['contact_client_id'], $recipient['contact_name'], $recipient['contact_photo'], $recipient['contact_email'], $recipient['contact_id']) ?>><?= escapeHtml($recipient['contact_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">They get an email with the link. Leave it empty to just copy the link.</div>
            </div>
            <label class="form-label">Works for</label>
            <div class="btn-group w-100 mb-3" role="group">
                <?php foreach ([1 => '1 hour', 24 => '1 day', 168 => '1 week', 730 => '1 month'] as $hours => $label) { ?>
                    <input class="btn-check" id="credentialShare<?= $credential_id ?>_<?= $hours ?>" type="radio" name="expires" value="<?= $hours ?>" <?= $hours === 24 ? 'checked' : '' ?>>
                    <label class="btn btn-outline-secondary" for="credentialShare<?= $credential_id ?>_<?= $hours ?>"><?= $label ?></label>
                <?php } ?>
            </div>
            <div class="form-check form-switch mb-3">
                <input type="checkbox" class="form-check-input" name="views" value="1" id="credentialShareOnce<?= $credential_id ?>" checked>
                <label class="form-check-label" for="credentialShareOnce<?= $credential_id ?>">Gone after it is opened once</label>
            </div>
            <label class="form-label">Note for them</label>
            <textarea class="form-control" rows="3" name="note" maxlength="255" placeholder="Shown with the login - what it is for, and to change it once they are in"></textarea>
        </div>
        <div data-share-done hidden>
            <p class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>The link is made, and copied.</p>
            <div class="input-group">
                <input type="text" class="form-control" readonly data-share-link onclick="this.select();">
                <button class="btn btn-outline-secondary" type="button" data-share-copy title="Copy link"><i class="far fa-fw fa-copy"></i></button>
            </div>
            <div class="form-text">Turn it off any time from the credential's quick look.</div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary text-bold" data-share-make><i class="fas fa-link me-2"></i>Make the link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
    </div>
</form>

<script>
    itflowReady(function () {
        var form = document.querySelector('form[data-credential-share]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var output = form.querySelector('[data-share-link]');
        var copy = form.querySelector('[data-share-copy]');
        function copyLink() {
            itflowCopyText(output.value, copy).then(
                function () { flashTooltip(copy, 'Copied!'); },
                function () {}
            );
        }
        copy.addEventListener('click', copyLink);
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var make = form.querySelector('[data-share-make]');
            make.disabled = true;
            itflowGet('ajax.php', {
                share_generate_link: 'true',
                csrf_token: form.csrf_token.value,
                client_id: form.client_id.value,
                type: 'Credential',
                id: form.querySelector('input[name="id"]').value,
                note: form.note.value,
                views: form.views.checked ? 1 : 0,
                expires: form.querySelector('input[name="expires"]:checked').value,
                contact_email: form.contact_email.value
            }, function (data) {
                output.value = JSON.parse(data);
                form.querySelector('[data-share-form]').hidden = true;
                form.querySelector('[data-share-done]').hidden = false;
                make.hidden = true;
                copyLink();
            }, function () {
                make.disabled = false;
            });
        });
    });
</script>

<?php
require_once '../../../includes/modal_footer.php';
