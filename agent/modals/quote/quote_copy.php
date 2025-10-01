<?php

require_once '../../../includes/modal_header.php';

$quote_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$quote_prefix = nullable_htmlentities($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$client_id = intval($row['client_id']);
$client_name = nullable_htmlentities($row['client_name']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy mr-2"></i>Copying quote: <strong><?php echo "$quote_prefix$quote_number"; ?></strong> - <?php echo $client_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">
    <div class="modal-body">
        <?php if (isset($_GET['client_id'])) { ?>
        <input type="hidden" name="client" value="<?php echo $client_id; ?>">
        <?php } else { ?>
        <div class="form-group">
            <label>Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                </div>
                <select class="form-control select2" name="client" required>
                    <?php
                        $sql_client_select = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_array($sql_client_select)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name_select = nullable_htmlentities($row['client_name']);
                    ?>
                        <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo $client_name_select; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <?php } ?>

        <div class="form-group">
            <label>Set Date for New Quote <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Expire <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="expire" min="<?php echo date("Y-m-d"); ?>" max="2999-12-31" value="<?php echo date("Y-m-d", strtotime("+30 days")); ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_quote_copy" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>


<?php

require_once '../../../includes/modal_footer.php';
