<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-briefcase mr-2"></i>Set Industry for <strong><?= $count ?></strong> Client(s)</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="client_ids[]" value="<?= $id ?>"><?php } ?>

    <div class="modal-body">

        <div class="form-group">
            <label>Industry</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                </div>
                <input type="text" class="form-control" name="bulk_industry" placeholder="Enter an Industry" maxlength="200" required>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_client_industry" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
