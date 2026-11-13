<?php

/*
 * New SLA - its name, what it is for, and how long there is to first reply to
 * a ticket and to resolve it. Nothing gets it until it is the default for a
 * priority (Admin > SLAs) or a contract picks it. The fields are sla_form.php.
 */

require_once '../../includes/modal_header.php';

$sla = null;

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stopwatch me-2"></i>New SLA</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/sla_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_sla" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
