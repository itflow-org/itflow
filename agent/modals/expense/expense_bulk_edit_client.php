<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>Set Client: <strong><?= $count ?></strong> Expense<?= $count == 1 ? '' : 's' ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?> <input type="hidden" name="expense_ids[]" value="<?= $id ?>"><?php } ?>

    <div class="modal-body">


        <div class="form-group">
            <label>Client</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="bulk_client_id">
                    <option value="0">- No Client -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        ?>
                        <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_expense_client" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check mr-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
