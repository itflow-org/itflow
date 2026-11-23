<?php

/*
 * Admin > Phone Systems > a system's domains - link one to a client, or to another one. The
 * client it looks like is picked to start with. Saved by link_phone_domain (admin/post/phone.php),
 * which matches its calls again.
 */

require_once '../../includes/modal_header.php';

$row = phoneDomainRow($_GET['id'] ?? 0);
if (!$row) {
    echo json_encode(['error' => 'That domain is gone.']);
    exit;
}
$phone_domain_id = intval($row['phone_domain_id']);
$current = intval($row['phone_domain_client_id']);
$picked = $current ?: (phoneDomainSuggestions(intval($row['phone_system_id']))[$phone_domain_id] ?? 0);
$own = intval($config_internal_client_id ?? 0);

$clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE (client_archived_at IS NULL OR client_id = $current)" . clientNotLeadSql($current) . " ORDER BY client_name ASC");

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-link me-2"></i>Link <strong class="font-monospace"><?= escapeHtml($row['phone_domain_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="phone_domain_id" value="<?= $phone_domain_id ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">Calls on a client's domain are that client's, with the contacts matched among theirs. Link your own company's domain to <?= $own ? '<strong>' . escapeHtml((string) getFieldById('clients', $own, 'client_name')) . '</strong>, your own client record' : 'your own client record (Admin &gt; Settings &gt; Company)' ?>: its calls go to whoever is calling, from any client. Its calls so far are matched again.</p>

        <label class="form-label">ITFlow client <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
            <select class="form-select select2" name="client_id" required>
                <option value="">- Select a client -</option>
                <?php while ($client = mysqli_fetch_assoc($clients)) {
                    $client_id = intval($client['client_id']); ?>
                    <option value="<?= $client_id ?>" <?= $client_id === $picked ? 'selected' : '' ?><?= selectOptionClient($client_id, $client['client_name'], $client['client_abbreviation'] ?? '') ?>><?= escapeHtml($client['client_name']) ?><?= $client_id === $own ? ' (your company)' : '' ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_phone_domain" class="btn btn-primary fw-bold"><i class="fas fa-link me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>
<?php
require_once '../../../includes/modal_footer.php';
