<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-building me-2"></i>New Vendor from Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <label>Template</label>
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                <select class="form-control" name="vendor_template_id" required>
                    <option value="">- Select Template -</option>
                    <?php
                    $sql_vendor_templates = mysqli_query($mysqli, "SELECT vendor_template_id, vendor_template_name FROM vendor_templates WHERE vendor_template_archived_at IS NULL ORDER BY vendor_template_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_vendor_templates)) {
                        $vendor_template_id = intval($row['vendor_template_id']);
                        $vendor_template_name = escapeHtml($row['vendor_template_name']);

                        ?>
                        <option value="<?= $vendor_template_id ?>"><?= $vendor_template_name ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_vendor_from_template" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create Vendor</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
