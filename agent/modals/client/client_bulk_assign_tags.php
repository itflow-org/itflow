<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_ids = array_map('intval', $_GET['client_ids'] ?? []);

$count = count($client_ids);

// Opened from the + beside one client's tags on the clients list: name the
// client, and offer only the tags it doesn't already have
$single_client_id = $count === 1 ? $client_ids[0] : 0;
$existing_tags_query = '';
if ($single_client_id) {
    enforceClientAccess($single_client_id);
    $single_client_name = escapeHtml(getFieldById('clients', $single_client_id, 'client_name'));
    $existing_tags_query = "AND tag_id NOT IN (SELECT tag_id FROM client_tags WHERE client_id = $single_client_id)";
}

ob_start();

?>

<div class="modal-header bg-dark">
    <?php if ($single_client_id) { ?>
        <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-tags me-2"></i>Add Tags to <strong><?= $single_client_name ?></strong></h5>
    <?php } else { ?>
        <h5 class="modal-title"><i class="fa fa-fw fa-tags me-2"></i>Assign Tags for <strong><?= $count ?></strong> Clients</h5>
    <?php } ?>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($client_ids as $client_id) { ?><input type="hidden" name="client_ids[]" value="<?= $client_id ?>"><?php } ?>
    <input type="hidden" name="bulk_remove_tags" value="0">

    <div class="modal-body">

        <?php if (!$single_client_id) { ?>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" name="bulk_remove_tags" value="1">
                <label class="form-check-label text-danger">Remove Existing Tags</label>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label>Tags</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="bulk_tags[]" data-placeholder="Add some tags" multiple>
                    <?php

                    $sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_color, tag_icon FROM tags WHERE tag_type = 1 $existing_tags_query ORDER BY tag_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                        $tag_id_select = intval($row['tag_id']);
                        $tag_name_select = escapeHtml($row['tag_name']);
                        ?>
                        <option value="<?= $tag_id_select ?>"<?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= $tag_name_select ?></option>
                    <?php } ?>

                </select>
            </div>
            <?php if ($single_client_id && mysqli_num_rows($sql_tags_select) === 0) { ?>
                <div class="form-text">This client already has every client tag.</div>
            <?php } ?>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_client_tags" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= $single_client_id ? 'Add Tags' : 'Assign' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
