<?php

require_once '../../includes/modal_header.php';

$canned_response_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT canned_response_name, canned_response_body, canned_response_category_id
    FROM canned_responses WHERE canned_response_id = $canned_response_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);

$canned_response_name = escapeHtml($row['canned_response_name']);
$canned_response_category_id = intval($row['canned_response_category_id']);

// Into a textarea, so escaped rather than purified - it is markup being edited, not rendered
$canned_response_body = escapeHtml($row['canned_response_body']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-comment-dots me-2"></i>Editing Canned Response: <strong><?= $canned_response_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="canned_response_id" value="<?= $canned_response_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment-dots"></i></span>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?= $canned_response_name ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Ticket Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select select2" name="category">
                    <option value="0" <?php if ($canned_response_category_id == 0) { echo "selected"; } ?>>- All ticket categories -</option>
                    <?php

                    $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
                    while ($category_row = mysqli_fetch_assoc($sql_categories)) {
                        $category_id_select = intval($category_row['category_id']);
                        $category_name_select = escapeHtml($category_row['category_name']); ?>
                        <option value="<?= $category_id_select ?>" <?php if ($canned_response_category_id == $category_id_select) { echo "selected"; } ?>><?= $category_name_select ?></option>

                    <?php } ?>
                </select>
            </div>
            <small class="text-secondary">Leave this on all categories for a response that should be offered on every ticket.</small>
        </div>

        <div class="mb-3">
            <label>Response</label>
            <textarea class="form-control tinymceTicket" name="body"><?= $canned_response_body ?></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_canned_response" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
