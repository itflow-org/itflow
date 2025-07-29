<?php

require_once '../../includes/modal_header.php';

$payment_method_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM payment_methods WHERE payment_method_id = $payment_method_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$payment_method_id = intval($row['payment_method_id']);
$payment_method_name = nullable_htmlentities($row['payment_method_name']);
$payment_method_description = nullable_htmlentities($row['payment_method_description']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Editing: <strong><?php echo $payment_method_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

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

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_payment_method" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../includes/modal_footer.php';
