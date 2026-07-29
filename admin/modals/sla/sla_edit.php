<?php
require_once '../../includes/modal_header.php';

$sla_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM slas WHERE sla_id = $sla_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$sla_name = escapeHtml($row['sla_name']);
$sla_description = escapeHtml($row['sla_description']);
$sla_response_minutes = intval($row['sla_response_minutes']);
$sla_resolution_minutes = intval($row['sla_resolution_minutes']);

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stopwatch mr-2"></i>Editing SLA: <strong><?= $sla_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="sla_id" value="<?= $sla_id ?>">

    <div class="modal-body">
        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                </div>
                <input type="text" class="form-control" name="name" value="<?= $sla_name ?>" maxlength="200" required>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                </div>
                <input type="text" class="form-control" name="description" value="<?= $sla_description ?>" maxlength="500">
            </div>
        </div>

        <div class="form-group">
            <label>Response target (business minutes) <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-reply"></i></span>
                </div>
                <input type="number" class="form-control" name="response_minutes" min="1" value="<?= $sla_response_minutes ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Resolution target (business minutes)</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-flag-checkered"></i></span>
                </div>
                <input type="number" class="form-control" name="resolution_minutes" min="1" value="<?= $sla_resolution_minutes ?: '' ?>" placeholder="Leave empty for response-only">
            </div>
            <small class="text-muted">Saving recalculates targets on open tickets using this SLA.</small>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_sla" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
