<?php

require_once '../../../includes/modal_header.php';

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT contact_client_id, contact_name FROM contacts
    WHERE contact_id = $contact_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$contact_name = escapeHtml($row['contact_name']);
$client_id = intval($row['contact_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-stream me-2"></i>Link Service to <strong><?= $contact_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-stream"></i></span>
                <select class="form-select select2" name="service_id">
                    <option value="">- Select a Service -</option>
                    <?php
                    $sql_services_select = mysqli_query($mysqli, "
                        SELECT services.service_id, services.service_name
                        FROM services
                        LEFT JOIN service_contacts
                            ON services.service_id = service_contacts.service_id
                            AND service_contacts.contact_id = $contact_id
                        WHERE services.service_client_id = $client_id
                        AND service_contacts.contact_id IS NULL
                        ORDER BY services.service_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_services_select)) {
                        $service_id = intval($row['service_id']);
                        $service_name = escapeHtml($row['service_name']);
                        ?>
                        <option value="<?= $service_id ?>"><?= $service_name ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_service_to_contact" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
