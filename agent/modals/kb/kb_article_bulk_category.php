<?php

/*
 * Bulk - put the articles ticked on the Knowledge Base list in a category.
 */

require_once '../../../includes/modal_header.php';

$kb_closed = kbClosedText(2);
if ($kb_closed !== '') {
    exit(json_encode(['error' => $kb_closed]));
}

$kb_article_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['kb_article_ids'] ?? [])))));
$count = count($kb_article_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-list-ul me-2"></i>Put <strong><?= $count ?></strong> article<?= $count === 1 ? '' : 's' ?> in a category</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($kb_article_ids as $kb_article_id) { ?><input type="hidden" name="kb_article_ids[]" value="<?= $kb_article_id ?>"><?php } ?>

    <div class="modal-body">
        <label class="form-label">Category</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-list-ul"></i></span>
            <select class="form-select select2" name="category_id">
                <option value="0">- No category -</option>
                <?php foreach (categoryPicker('kb') as $category_id => $category_row) { ?>
                    <?= categoryOptionHtml($category_row) ?>
                <?php } ?>
            </select>
            <?= categoryAddButtonHtml('kb') ?>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_category_kb_articles" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Set category</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
