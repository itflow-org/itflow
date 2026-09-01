<?php

require_once '../../../includes/modal_header.php';

$contact_ids = array_map('intval', $_GET['contact_ids'] ?? []);

$count = count($contact_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-users me-2"></i>Set Department / Group for <strong><?= $count ?></strong> Contacts</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($contact_ids as $contact_id) { ?><input type="hidden" name="contact_ids[]" value="<?= $contact_id ?>"><?php } ?>
    <div class="modal-body">

        <label>Department / Group</label>
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <input type="text" class="form-control" name="bulk_department" placeholder="Department or group" maxlength="200">
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_contact_department" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set Department</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
