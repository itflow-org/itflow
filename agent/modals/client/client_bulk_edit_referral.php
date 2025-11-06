<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-link mr-2"></i>Set Referral for <strong><?= $count ?></strong> Clients</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="client_ids[]" value="<?= $id ?>"><?php } ?>
    
    <div class="modal-body">

        <div class="form-group">
            <label>Referral</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                </div>
                <select class="form-control select2" name="bulk_referral">
                    <option value="">- Select a Referral -</option>
                    <?php
                    $referral_sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_array($referral_sql)) {
                        $referral = nullable_htmlentities($row['category_name']); ?>
                        <option><?php echo $referral; ?></option>
                    <?php } ?>

                </select>
                <div class="input-group-append">
                    <button class="btn btn-secondary ajax-modal" type="button"
                        data-modal-url="/admin/modals/category/category_add.php?category=Referral">
                        <i class="fas fa-fw fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_client_referral" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
