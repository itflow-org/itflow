<?php

/*
 * The body of New Article and Edit Article (kb_article_add.php,
 * kb_article_edit.php): title and summary across the top, its category, tags,
 * how often it's reviewed and whether it's published under them, the editor
 * below. Posts what kbArticleFields() reads (functions/kb.php).
 *
 * Expects $kb_form (kbArticleRow(), or the new article's starting values:
 * kb_article_title, kb_article_summary, kb_article_content,
 * kb_article_category_id, kb_article_review_months, kb_article_published_at)
 * and $kb_form_mode ('add' or 'edit').
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$kb_form_category = intval($kb_form['kb_article_category_id'] ?? 0);
$kb_form_review = intval($kb_form['kb_article_review_months'] ?? 0);
$kb_form_published = !empty($kb_form['kb_article_published_at']);

?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label">Title <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-book"></i></span>
            <input type="text" class="form-control" name="title" maxlength="200" value="<?= escapeHtml($kb_form['kb_article_title'] ?? '') ?>" placeholder="e.g. Set up company email on an iPhone" required <?= $kb_form_mode === 'add' ? 'autofocus' : '' ?>>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Summary</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="summary" maxlength="255" value="<?= escapeHtml($kb_form['kb_article_summary'] ?? '') ?>" placeholder="One line on what it helps with">
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Category</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-list-ul"></i></span>
            <select class="form-select select2" name="category_id">
                <option value="0">- No category -</option>
                <?php foreach (categoryPicker('kb', $kb_form_category ? [$kb_form_category] : []) as $category_id => $category_row) { ?>
                    <?= categoryOptionHtml($category_row, intval($category_id) === $kb_form_category) ?>
                <?php } ?>
            </select>
            <?= categoryAddButtonHtml('kb', 'btn btn-secondary') ?>
        </div>
    </div>
    <div class="col-md-4">
        <?= tagFormFieldHtml('kb_article', $kb_form_mode === 'edit' ? tagIdsForRecord('kb_article', $kb_form['kb_article_id']) : [], 'Email, Printers, Microsoft 365') ?>
    </div>
    <div class="col-md-4">
        <label class="form-label">Review</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-calendar-check"></i></span>
            <select class="form-select select2" name="review_months">
                <?php foreach (KB_REVIEW_MONTHS as $months => $label) { ?>
                    <option value="<?= $months ?>"<?= $months === $kb_form_review ? ' selected' : '' ?>><?= $label ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="published" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="published" value="1" id="kbArticleFormPublished" <?= $kb_form_published ? 'checked' : '' ?>>
            <label class="form-check-label" for="kbArticleFormPublished">
                <i class="fas fa-fw fa-check-circle text-body-secondary me-1"></i>Published <span class="text-body-secondary small">- finished and ready to use. Off, it stays a draft.</span>
            </label>
        </div>
    </div>
</div>

<textarea class="form-control tinymce" name="content" aria-label="Content"><?= escapeHtml($kb_form['kb_article_content'] ?? '') ?></textarea>

<?php if ($kb_form_mode === 'edit') { ?>
    <div class="form-text"><i class="fas fa-history me-1"></i>Saving keeps what it says now in the article's versions, so it can be put back.</div>
<?php } ?>
