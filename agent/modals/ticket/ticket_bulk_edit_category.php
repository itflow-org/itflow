<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_ids = array_map('intval', $_GET['ticket_ids'] ?? []);

$count = count($ticket_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-layer-group me-2"></i>Set Category for <strong><?= $count ?></strong> ticket<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($ticket_ids as $ticket_id) { ?><input type="hidden" name="ticket_ids[]" value="<?= $ticket_id ?>"><?php } ?>

    <div class="modal-body">



        <div class="mb-3">
            <label class="form-label">Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-layer-group"></i></span>
                <select class="form-select select2" name="bulk_category">
                    <option value="0">- Not categorized -</option>
                    <?= categoryOptionsHtml('Ticket') ?>
                </select>
                <?= categoryAddButtonHtml('Ticket') ?>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_ticket_category" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
