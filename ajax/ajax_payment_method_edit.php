<?php

require_once '../includes/ajax_header.php';

$payment_method_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM payment_methods WHERE payment_method_id = $payment_method_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$payment_method_id = intval($row['payment_method_id']);
$payment_method_name = nullable_htmlentities($row['payment_method_name']);
$payment_method_description = nullable_htmlentities($row['payment_method_description']);
$payment_method_provider_id = intval($row['payment_method_provider_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Editing: <strong><?php echo $payment_method_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                </div>
                <input type="text" class="form-control" name="name" value="<?php echo $payment_method_name; ?>" placeholder="Payment method name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <textarea class="form-control" rows="3" name="description" placeholder="Enter a description..."><?php echo $payment_method_description; ?></textarea>
        </div>

        <div class="form-group">
            <label>Payment Provider</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                </div>
                <select class="form-control select2" name="provider">
                    <option value="">- Select a Payment Provider -</option>
                    <?php
                        $sql_payment_providers = mysqli_query($mysqli, "SELECT * FROM payment_providers");
                        while ($row = mysqli_fetch_array($sql_payment_providers)) {
                            $payment_provider_id_select = intval($row['payment_provider_id']);
                            $payment_provider_name_select = nullable_htmlentities($row['payment_provider_name']);

                        ?>
                        <option <?php if ($payment_method_provider_id == $payment_provider_id_select) { echo "selected"; } ?> value="<?php echo $payment_provider_id_select; ?>"><?php echo $payment_provider_name_select; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_payment_method" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";
