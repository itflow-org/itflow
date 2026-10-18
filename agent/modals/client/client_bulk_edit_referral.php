<?php

require_once '../../../includes/modal_header.php';

$client_ids = array_map('intval', $_GET['client_ids'] ?? []);

$count = count($client_ids);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-link me-2"></i>Set Referral for <strong><?= $count ?></strong> client<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($client_ids as $client_id) { ?><input type="hidden" name="client_ids[]" value="<?= $client_id ?>"><?php } ?>

    <div class="modal-body">

        <div class="mb-3">
            <label>Referral</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                <select class="form-select select2" name="bulk_referral">
                    <option value="">- Select a Referral -</option>
                    <?= categoryOptionsHtml('Referral') ?>
                </select>
                <?= categoryAddButtonHtml('Referral', 'btn btn-secondary') ?>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_client_referral" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
