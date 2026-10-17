<?php

/* New Transfer - money moved between our accounts. ?from= and ?to= start it with those accounts. */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = null;
$form_from = intval($_GET['from'] ?? 0);
$form_to = intval($_GET['to'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-exchange-alt me-2"></i>New Transfer</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/transfer_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_transfer" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Transfer</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
