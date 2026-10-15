<?php

/*
 * Bulk - the importance, category and review schedule of the services
 * ticked on the services list. Each field left on "No change" stays as it is.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$service_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['service_ids'] ?? [])))));
$count = count($service_ids);

$categories = SERVICE_CATEGORY_SUGGESTIONS;
$sql = mysqli_query($mysqli, "SELECT DISTINCT service_category FROM services WHERE service_category <> '' " . clientScopeSql('service_client_id') . " ORDER BY service_category LIMIT 50");
while ($row = mysqli_fetch_row($sql)) {
    if (!in_array($row[0], $categories, true)) {
        $categories[] = $row[0];
    }
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit me-2"></i>Change <strong><?= $count ?></strong> service<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($service_ids as $service_id) { ?><input type="hidden" name="service_ids[]" value="<?= $service_id ?>"><?php } ?>

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Importance</label>
            <select class="form-select select2" name="importance">
                <option value="">- No change -</option>
                <?php foreach (SERVICE_IMPORTANCE as $importance) { ?>
                    <option value="<?= $importance ?>"><?= $importance ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <input type="text" class="form-control" name="category" list="serviceBulkCategories" maxlength="50" placeholder="- No change -">
            <datalist id="serviceBulkCategories">
                <?php foreach ($categories as $category) { ?>
                    <option value="<?= escapeHtml($category) ?>"></option>
                <?php } ?>
            </datalist>
        </div>
        <div class="mb-0">
            <label class="form-label">Review</label>
            <select class="form-select select2" name="review_interval">
                <option value="">- No change -</option>
                <?php foreach (SERVICE_REVIEW_INTERVALS as $months => $label) { ?>
                    <option value="<?= $months ?>"><?= $label ?></option>
                <?php } ?>
            </select>
            <div class="form-text">A schedule sets each one's next review that long after its last one - or from today if that has passed.</div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_services" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Change</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
