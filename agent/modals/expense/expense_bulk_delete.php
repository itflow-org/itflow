<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

// Generate the HTML form content using output buffering.
ob_start();

?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?> <input type="hidden" name="expense_ids[]" value="<?= $id ?>"><?php } ?>
    <div class="modal-body text-center">

        <div class="mb-4" style="text-align: center;">
            <i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
            <h2>Are you really, really, really sure?</h2>
            <h6 class="mb-4 text-secondary">This will permanently delete the selected expense<?= $count == 1 ? '' : 's' ?>. and ALL associated data</b>?<br><br>This process cannot be undone.</h6>
            <button type="button" class="btn btn-outline-secondary btn-lg px-5 mr-4" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-lg px-5">Yes, Delete!</button>
        </div>



        <p class="mb-2">

            This will permanently delete the selected expense<?= $count == 1 ? '' : 's' ?>.
        </p>
        <p class="text-muted small mb-0">
            This action cannot be undone.
        </p>

        <button type="submit" name="bulk_delete_expenses" class="btn btn-danger btn-lg px-5"><i class="fa fa-fw fa-trash mr-2"></i>Delete</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
