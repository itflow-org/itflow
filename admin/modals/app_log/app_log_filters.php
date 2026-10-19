<?php

/*
 * The app log filters, for the export and clear windows - search, level,
 * category and dates, filled in from what the list shows. Expects
 * $q_filter, $view_filter, $category_filter, $date_from_filter and
 * $date_to_filter, and $views (appLogViews()).
 */

isset($mysqli, $views) || die("Direct file access is not allowed");

$filter_categories = appLogCategories();

?>

<div class="mb-3">
    <label class="form-label">Search</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
        <input type="text" class="form-control" name="q" value="<?= escapeHtml($q_filter) ?>" placeholder="The category or what it says">
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label">Level</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-signal"></i></span>
            <select class="form-select select2" name="view">
                <?php foreach ($views as $key => $definition) { ?>
                    <option value="<?= $key ?>" <?= $view_filter === $key ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . $definition['icon'], 'description' => $definition['title']]) ?>><?= $key === '' ? 'Every level' : escapeHtml($definition['label']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Category</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
            <select class="form-select select2" name="category">
                <option value="">- Any category -</option>
                <?php foreach ($filter_categories as $category => $count) { ?>
                    <option value="<?= escapeHtml($category) ?>" <?= $category_filter === (string) $category ? 'selected' : '' ?><?= selectOptionData(['icon' => appLogCategoryIcon($category), 'description' => number_format($count) . ' entr' . ($count === 1 ? 'y' : 'ies')]) ?>><?= escapeHtml($category) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">From</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">To</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
        </div>
    </div>
</div>
