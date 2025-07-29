<?php

require_once '../../includes/modal_header.php';

$account_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $account_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$account_name = nullable_htmlentities($row['account_name']);
$account_notes = nullable_htmlentities($row['account_notes']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank mr-2"></i>Editing account: <strong><?php echo $account_name; ?></strong></h5>
    <button type="button" class="close text-light" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="account_id" value="<?php echo $account_id; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <div class="form-group">
            <label>Account Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                </div>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $account_name; ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Notes</label>
            <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"><?php echo $account_notes; ?></textarea>
        </div>
    
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_account" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../includes/modal_footer.php';
