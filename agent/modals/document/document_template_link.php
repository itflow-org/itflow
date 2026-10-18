<?php

/*
 * The template a document counts as made from - for one written by hand
 * before the template, or made from another, so it counts towards who has one
 * and is reviewed as the template says. Saved by set_document_template
 * (agent/post/document.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_name, document_template_id FROM documents WHERE document_id = $document_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();
$current_id = intval($row['document_template_id']);

// The current templates, and the one it is from even if archived since
$templates = [];
$sql = mysqli_query($mysqli, "SELECT document_template_id, document_template_name, document_template_description, document_template_archived_at, document_template_required, document_template_review_months
    FROM document_templates WHERE document_template_archived_at IS NULL OR document_template_id = $current_id ORDER BY document_template_name ASC");
while ($template = mysqli_fetch_assoc($sql)) {
    $templates[intval($template['document_template_id'])] = $template;
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-puzzle-piece me-2"></i>Template for <strong><?= escapeHtml($row['document_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">

        <label class="form-label">Counts as made from</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-puzzle-piece"></i></span>
            <select class="form-select select2" name="document_template_id">
                <option value="0">- Not from a template -</option>
                <?php foreach ($templates as $template_id => $template) {
                    $about = array_filter([
                        $template['document_template_required'] ? 'required' : '',
                        $template['document_template_review_months'] ? 'reviewed ' . lcfirst(DOCUMENT_TEMPLATE_REVIEW_MONTHS[intval($template['document_template_review_months'])] ?? '') : '',
                        $template['document_template_archived_at'] ? 'archived' : '',
                    ]);
                    ?>
                    <option value="<?= $template_id ?>" <?= $template_id === $current_id ? 'selected' : '' ?> data-description="<?= escapeHtml(trim($template['document_template_description'] . ($about ? ' · ' . implode(', ', $about) : ''), ' ·')) ?>"><?= escapeHtml($template['document_template_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">What it says stays as it is - it counts towards the clients that should have one, and is reviewed as the template says.</div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="set_document_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
