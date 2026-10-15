<?php

/*
 * Link one of the client's credentials to a contact - whose login it is. A
 * credential belongs to one contact, so picking one that is someone else's
 * moves it - the picker says whose each one is now.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);
enforceUserPermission('module_credential', 2);

$contact_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_client_id, contact_name FROM contacts WHERE contact_id = $contact_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That contact no longer exists.']);
    exit;
}
$contact_name = escapeHtml($row['contact_name']);
$client_id = intval($row['contact_client_id']);

enforceClientAccess();

$choices = credentialRows("credential_client_id = $client_id AND credential_contact_id <> $contact_id");
// Nobody's first, then the ones that would move from someone else
uasort($choices, function ($a, $b) {
    return [empty($a['contact_id']) ? 0 : 1, strtolower($a['credential_name'])] <=> [empty($b['contact_id']) ? 0 : 1, strtolower($b['credential_name'])];
});

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-key me-2"></i>Link a credential to <strong><?= $contact_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
    <div class="modal-body">
        <?php if ($choices) { ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <select class="form-select select2" name="credential_id" required data-placeholder="Pick a credential">
                    <option value=""></option>
                    <?php foreach ($choices as $credential_id => $choice) {
                        $whose_now = !empty($choice['contact_id']) ? $choice['contact_name'] . "'s - moves to " . html_entity_decode($contact_name, ENT_QUOTES, 'UTF-8') : ($choice['credential_description'] ?: $choice['credential_uri']); ?>
                        <option value="<?= $credential_id ?>"<?= selectOptionCredential($choice, $whose_now) ?>><?= escapeHtml($choice['credential_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">A credential belongs to one person - one that is someone else's moves to <?= $contact_name ?>.</div>
        <?php } else { ?>
            <p class="text-body-secondary mb-0">This client has no other credentials to link - make a new one instead.</p>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($choices) { ?>
            <button type="submit" name="link_contact_to_credential" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
