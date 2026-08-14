<?php

require_once '../../../includes/modal_header.php';

$expense_ids = array_map('intval', $_GET['expense_ids'] ?? []);

$count = count($expense_ids);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user me-2"></i>Set Client: <strong><?= $count ?></strong> Expense<?= $count == 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">

    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($expense_ids as $expense_id) { ?> <input type="hidden" name="expense_ids[]" value="<?= $expense_id ?>"><?php } ?>

    <div class="modal-body">


        <div class="mb-3">
            <label>Client</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-control select2" name="bulk_client_id">
                    <option value="0">- No Client -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $client_id = intval($row['client_id']);
                        $client_name = escapeHtml($row['client_name']);
                        ?>
                        <option value="<?= $client_id ?>"><?= $client_name ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_expense_client" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
