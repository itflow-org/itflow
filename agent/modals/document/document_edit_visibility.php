<?php

require_once '../../../includes/modal_header.php';

$document_id = intval($_GET['document_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM documents
    WHERE document_id = $document_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$document_name = escapeHtml($row['document_name']);
$document_client_visible = intval($row['document_client_visible']);
$client_id = intval($row['document_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-handshake mr-2"></i>
        Edit Visibility Status for <strong><?= $document_name ?></strong>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">
        <div class="form-group">
            <label>Visibility</label>
            <p>Should this document be visible in the portal to client contacts with the 'Technical' role?</p>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                </div>
                <select class="form-control" name="document_visible">
                    <option <?php if ($document_client_visible == 1) { echo "selected"; } ?> value="1">Yes</option>
                    <option <?php if ($document_client_visible == 0) { echo "selected"; } ?> value="0">No</option>
                </select>
            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="toggle_document_visibility" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
