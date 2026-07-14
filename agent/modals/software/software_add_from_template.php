<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube mr-2"></i>New License from Template</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php if ($client_id) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <?php } else { ?>

            <div class="form-group">
                <label>Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <select class="form-control select2" name="client_id" required>
                        <option value="">- Select Client -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name_select = escapeHtml($row['client_name']); ?>
                            <option value="<?= $client_id_select ?>"><?= $client_name_select ?></option>

                        <?php } ?>
                    </select>
                </div>
            </div>

        <?php } ?>

        <label>Template</label>
        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                </div>
                <select class="form-control" name="software_template_id" required>
                    <option value="">- Select Template -</option>
                    <?php
                    $sql_software_templates = mysqli_query($mysqli, "SELECT * FROM software_templates WHERE software_template_archived_at IS NULL ORDER BY software_template_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_software_templates)) {
                        $software_template_id = intval($row['software_template_id']);
                        $software_template_name = escapeHtml($row['software_template_name']);

                        ?>
                        <option value="<?php echo $software_template_id ?>"><?php echo $software_template_name; ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_software_from_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
