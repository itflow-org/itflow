<?php

/*
 * Export canned responses to a file - every current shared one, or the ones
 * picked, the archived ones if asked, and their files unless left out - to
 * import into another ITFlow (export_canned_responses,
 * functions/canned_response_transfer.php).
 */

require_once '../../includes/modal_header.php';

$sql = mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name, canned_response_archived_at FROM canned_responses
    WHERE canned_response_user_id = 0 ORDER BY canned_response_archived_at IS NOT NULL, canned_response_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-download me-2"></i>Export canned responses</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file to import into another ITFlow, or keep. Each response goes with what it says, the ticket categories it is offered on and what it sets up on the reply. Agents' own stay here.</p>

        <div class="mb-3">
            <label class="form-label">Canned responses</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-comment-dots"></i></span>
                <select class="form-select select2" name="canned_response_ids[]" multiple data-placeholder="Every current one">
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['canned_response_id']) ?>"><?= escapeHtml($row['canned_response_name']) ?><?= $row['canned_response_archived_at'] ? ' (archived)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Leave it empty for every current one.</div>
        </div>

        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" name="archived" value="1" id="exportCannedArchived">
            <label class="form-check-label" for="exportCannedArchived">With the archived ones too</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="files" value="1" id="exportCannedFiles" checked>
            <label class="form-check-label" for="exportCannedFiles">With the files that go with them <span class="small text-secondary">- each up to <?= intval(CANNED_RESPONSE_TRANSFER_FILE_MAX / 1048576) ?> MB</span></label>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="export_canned_responses" class="btn btn-primary text-bold"><i class="fa fa-download me-2"></i>Download</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
