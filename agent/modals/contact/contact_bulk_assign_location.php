<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['client_id']);

enforceClientAccess();
$contact_ids = array_map('intval', $_GET['contact_ids'] ?? []);

$count = count($contact_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt me-2"></i>Assign Location to <strong><?= $count ?></strong> Contacts</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($contact_ids as $contact_id) { ?><input type="hidden" name="contact_ids[]" value="<?= $contact_id ?>"><?php } ?>
    <div class="modal-body">

        <div class="mb-3">
            <label>Location</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                <select class="form-control select2" name="bulk_location_id">
                    <option value="">- Select Location -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $location_id = intval($row['location_id']);
                        $location_name = escapeHtml($row['location_name']);
                    ?>
                        <option value="<?= $location_id ?>"><?= $location_name ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_contact_location" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Assign Location</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
