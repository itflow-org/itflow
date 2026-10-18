<?php

/*
 * Edit an API key - its name, the day it stops working and the agent it
 * acts as. The key itself can't be shown or changed: make a new one.
 */

require_once '../../includes/modal_header.php';

$row = apiKeyRow($_GET['id'] ?? 0);

ob_start();

if (!$row) { ?>
    <div class="modal-body text-center text-secondary py-5">That key is gone - it may have been deleted.</div>
<?php } else {
    $api_key_id = intval($row['api_key_id']);
    $role = roleRow($row['user_role_id'] ?? 0); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-key me-2"></i>API Key: <strong><?= escapeHtml($row['api_key_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="api_key_id" value="<?= $api_key_id ?>">
    <div class="modal-body">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <?= apiKeyStatePill($row, $role) ?>
            <span class="small text-body-secondary"><span class="font-monospace">••••<?= escapeHtml(substr((string) $row['api_key_secret'], -4)) ?></span> · last used <?= $row['api_key_last_used_at'] ? escapeHtml(timeAgo($row['api_key_last_used_at'])) : 'never' ?></span>
        </div>

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                <input type="text" class="form-control" name="name" placeholder="What uses it" maxlength="255" value="<?= escapeHtml($row['api_key_name']) ?>" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Expires <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?= escapeHtml($row['api_key_expire']) ?>" required>
            </div>
            <div class="form-text">It works until the start of that day. A later day brings an expired key back.</div>
        </div>

        <?= apiKeyAgentFieldHtml(isset(apiKeyAgents()[intval($row['api_key_user_id'])]) ? $row['api_key_user_id'] : 0) ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_api_key" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
