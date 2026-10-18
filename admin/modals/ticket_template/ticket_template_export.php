<?php

/*
 * Export ticket templates to a file - every current one, or the ones picked,
 * and the archived ones if asked - to import into another ITFlow
 * (export_ticket_templates, functions/ticket_template_transfer.php).
 */

require_once '../../includes/modal_header.php';

$sql = mysqli_query($mysqli, "SELECT ticket_template_id, ticket_template_name, ticket_template_archived_at FROM ticket_templates ORDER BY ticket_template_archived_at IS NOT NULL, ticket_template_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-download me-2"></i>Export ticket templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file to import into another ITFlow, or keep. Each template goes with its tasks, fill-ins, request form, category and tags - not the agent it goes to or the clients its request form is for.</p>

        <div class="mb-3">
            <label class="form-label">Templates</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                <select class="form-select select2" name="ticket_template_ids[]" multiple data-placeholder="Every current one">
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['ticket_template_id']) ?>"><?= escapeHtml($row['ticket_template_name']) ?><?= $row['ticket_template_archived_at'] ? ' (archived)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Leave it empty for every current one.</div>
        </div>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="archived" value="1" id="exportArchived">
            <label class="form-check-label" for="exportArchived">With the archived ones too</label>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="export_ticket_templates" class="btn btn-primary text-bold"><i class="fa fa-download me-2"></i>Download</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
