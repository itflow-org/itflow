<?php

require_once '../../../includes/modal_header_new.php';

$invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM invoices LEFT JOIN clients ON invoice_client_id = client_id WHERE invoice_id = $invoice_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$client_name = nullable_htmlentities($row['client_name']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy mr-2"></i>Copying invoice: <strong><?php echo "$invoice_prefix$invoice_number"; ?></strong> - <?php echo $client_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
    
    <div class="modal-body">

        <div class="form-group">
            <label>Invoice Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>" required>
            </div>
        </div>
        
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_invoice_copy" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer_new.php';
