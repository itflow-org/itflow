<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id']);
$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user-check mr-2"></i>Assign Contact to <strong><?= $count ?></strong> Assets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="asset_ids[]" value="<?= $id ?>"><?php } ?>
    <div class="modal-body">

        <div class="form-group">
            <label>Contact</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="bulk_contact_id">
                    <option value="">- Contact -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id ORDER BY contact_name ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                        $contact_id = intval($row['contact_id']);
                        $contact_name = nullable_htmlentities($row['contact_name']);
                        ?>
                        <option value="<?php echo $contact_id; ?>"><?php echo $contact_name; ?></option>

                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_asset_contact" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Assign Contact</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
