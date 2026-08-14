<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$quote_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT client_id, client_name, quote_number, quote_prefix FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$quote_prefix = escapeHtml($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$client_id = intval($row['client_id']);
$client_name = escapeHtml($row['client_name']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy me-2"></i>Copying quote: <strong><?= "$quote_prefix$quote_number" ?></strong> - <?= $client_name ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
    <div class="modal-body">
        <?php if (isset($_GET['client_id'])) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <?php } else { ?>
        <div class="mb-3">
            <label>Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <select class="form-select select2" name="client_id" required>
                    <?php
                        $sql_client_select = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_client_select)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name_select = escapeHtml($row['client_name']);
                    ?>
                        <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <?php } ?>

        <div class="mb-3">
            <label>Set Date for New Quote <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date("Y-m-d") ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Expire <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="expire" min="<?= date("Y-m-d") ?>" max="2999-12-31" value="<?= date("Y-m-d", strtotime("+30 days")) ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_quote_copy" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>


<?php

require_once '../../../includes/modal_footer.php';
