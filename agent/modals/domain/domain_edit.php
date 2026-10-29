<?php

/*
 * Edit a domain - its name, what it is for, the star, the expiry and
 * auto-renew, the registrar and hosts, tags and notes. Saving doesn't look
 * the domain up again unless its name changed; Look up now does that.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$domain_id = intval($_GET['id'] ?? 0);
$domain = domainRow($domain_id);
if (!$domain) {
    echo json_encode(['error' => 'That domain no longer exists.']);
    exit;
}
$client_id = intval($domain['domain_client_id']);
enforceClientAccess();

$domain_form_mode = 'edit';
$domain_name = escapeHtml($domain['domain_name']);
$domain_description = escapeHtml($domain['domain_description']);
$domain_expire = escapeHtml($domain['domain_expire']);
$domain_notes = escapeHtml($domain['domain_notes']);
$domain_favorite = intval($domain['domain_favorite']);
$domain_auto_renew = intval($domain['domain_auto_renew']);
$domain_tag_ids = array_map('intval', array_column(tagsForRecord('domain', $domain_id), 'tag_id'));
$domain_vendor_ids = [];
foreach (DOMAIN_ROLES as $role => $info) {
    $domain_vendor_ids[$role] = intval($domain[$info['column']]);
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-globe me-2"></i>Editing domain: <strong><?= $domain_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="domain_id" value="<?= $domain_id ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">
        <?php require 'domain_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_domain" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
