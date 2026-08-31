<?php

require_once '../../includes/modal_header.php';

$tag_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT tag_color, tag_icon, tag_name, tag_type FROM tags WHERE tag_id = $tag_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$tag_name = escapeHtml($row['tag_name']);
$tag_type = intval($row['tag_type']);
$tag_color = escapeHtml($row['tag_color']);
$tag_icon = escapeHtml($row['tag_icon']);

if ($tag_type == 1) {
    $tag_type_display = "Client";
} elseif ( $tag_type == 2) {
    $tag_type_display = "Location";
} elseif ( $tag_type == 3) {
    $tag_type_display = "Contact";
} elseif ( $tag_type == 4) {
    $tag_type_display = "Credential";
 } elseif ( $tag_type == 5) {
    $tag_type_display = "Asset";
} else {
    $tag_type_display = "Unknown";
}

ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-tag me-2"></i><?= $tag_type_display ?> Tag: <strong><?= $tag_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="tag_id" value="<?= $tag_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?= $tag_name ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                <input type="color" class="form-control form-control-color" name="color" value="<?= $tag_color ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Icon</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
                <input type="text" class="form-control" name="icon" placeholder="Icon ex handshake" maxlength="200" value="<?= $tag_icon ?>">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_tag" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
