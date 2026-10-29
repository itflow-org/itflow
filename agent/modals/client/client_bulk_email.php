<?php

require_once '../../../includes/modal_header.php';

$client_ids = array_map('intval', $_GET['client_ids'] ?? []);

$count = count($client_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-envelope-open me-2"></i>Send Emails to <strong><?= $count ?></strong> client<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($client_ids as $client_id) { ?><input type="hidden" name="client_ids[]" value="<?= $client_id ?>"><?php } ?>

    <div class="modal-body">

        <label class="form-label">To their</label>
        <div class="row g-2 mb-1">
            <?php foreach (['primary_contacts' => 'Primary contacts', 'important_contacts' => 'Important contacts', 'billing_contacts' => 'Billing contacts', 'technical_contacts' => 'Technical contacts'] as $field => $label) { ?>
                <div class="col-sm-6 col-md-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="clientBulkEmail<?= $field ?>" name="<?= $field ?>" value="1">
                        <label class="form-check-label" for="clientBulkEmail<?= $field ?>"><?= $label ?></label>
                    </div>
                </div>
            <?php } ?>
        </div>
        <div class="form-text mt-0 mb-3">Leave them all unticked to email every contact. Archived contacts and anyone without an email address are left out, and each address gets it once.</div>

        <?php require __DIR__ . '/../../includes/bulk_email_fields.php'; ?>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_send_client_email" class="btn btn-primary text-bold"<?= emailReady() ? '' : ' disabled' ?>><i class="fas fa-paper-plane me-2"></i>Send</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
