<?php

/*
 * Link one of the client's credentials to an asset. A credential sits on one
 * asset, so picking one that is on another asset moves it here - the picker
 * says where each one is now. Ones whose asset was deleted count as free.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);
enforceUserPermission('module_credential', 2);

$asset_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_client_id, asset_name FROM assets WHERE asset_id = $asset_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That asset no longer exists.']);
    exit;
}
$asset_name = escapeHtml($row['asset_name']);
$client_id = intval($row['asset_client_id']);

enforceClientAccess();

$choices = credentialRows("credential_client_id = $client_id AND credential_asset_id <> $asset_id");
// Free ones first, then the ones that would move from another asset
uasort($choices, function ($a, $b) {
    return [empty($a['asset_id']) ? 0 : 1, strtolower($a['credential_name'])] <=> [empty($b['asset_id']) ? 0 : 1, strtolower($b['credential_name'])];
});

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-key me-2"></i>Link a credential to <strong><?= $asset_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <div class="modal-body">
        <?php if ($choices) { ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <select class="form-select select2" name="credential_id" required data-placeholder="Pick a credential">
                    <option value=""></option>
                    <?php foreach ($choices as $credential_id => $choice) {
                        $where_now = !empty($choice['asset_id']) ? 'On ' . $choice['asset_name'] . ' - moves here' : ($choice['credential_description'] ?: $choice['credential_uri']); ?>
                        <option value="<?= $credential_id ?>"<?= selectOptionCredential($choice, $where_now) ?>><?= escapeHtml($choice['credential_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">A credential belongs to one asset - one already on another asset moves here.</div>
        <?php } else { ?>
            <p class="text-body-secondary mb-0">This client has no other credentials to link - make a new one instead.</p>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($choices) { ?>
            <button type="submit" name="link_asset_to_credential" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
