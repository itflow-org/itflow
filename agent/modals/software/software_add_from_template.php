<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube me-2"></i>New License from Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php if ($client_id) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <?php } else { ?>

            <div class="mb-3">
                <label>Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <option value="">- Select Client -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
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
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                <select class="form-select" name="software_template_id" required>
                    <option value="">- Select Template -</option>
                    <?php
                    $sql_software_templates = mysqli_query($mysqli, "SELECT software_template_id, software_template_name FROM software_templates WHERE software_template_archived_at IS NULL ORDER BY software_template_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_software_templates)) {
                        $software_template_id = intval($row['software_template_id']);
                        $software_template_name = escapeHtml($row['software_template_name']);

                        ?>
                        <option value="<?= $software_template_id ?>"><?= $software_template_name ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_software_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
