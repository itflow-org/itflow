<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$contact_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_billing, contact_client_id, contact_department, contact_email,
    contact_extension, contact_important, contact_location_id, contact_mobile, contact_mobile_country_code, contact_name,
    contact_notes, contact_phone, contact_phone_country_code, contact_photo, contact_pin, contact_primary, contact_technical,
    contact_title, contact_user_id, user_auth_method FROM contacts
    LEFT JOIN users ON user_id = contact_user_id
    WHERE contact_id = $contact_id
    LIMIT 1"
));
if (!$row) {
    echo json_encode(['error' => 'That contact no longer exists.']);
    exit;
}
$client_id = intval($row['contact_client_id']);

enforceClientAccess();

// A contact's phone belongs to the CLIENT's country, not the company's. The
// client's country lives on its primary location; falls through to the
// company default when the client has none.
$client_phone_iso2 = '';
$row_client_country = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT location_country FROM locations
    WHERE location_client_id = $client_id AND location_primary = 1 LIMIT 1"));
if ($row_client_country) {
    $client_phone_iso2 = $country_iso2_array[$row_client_country['location_country']] ?? '';
}

$contact_form_mode = 'edit';
$contact_primary_default = false;
$contact_name = escapeHtml($row['contact_name']);
$contact_title = escapeHtml($row['contact_title']);
$contact_department = escapeHtml($row['contact_department']);
$contact_extension = escapeHtml($row['contact_extension']);
$contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
$contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code'], false));
$contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
$contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code'], false));
$contact_email = escapeHtml($row['contact_email']);
$contact_pin = escapeHtml($row['contact_pin']);
$contact_photo = escapeHtml($row['contact_photo']);
$contact_notes = escapeHtml($row['contact_notes']);
$contact_primary = intval($row['contact_primary']);
$contact_important = intval($row['contact_important']);
$contact_billing = intval($row['contact_billing']);
$contact_technical = intval($row['contact_technical']);
$contact_location_id = intval($row['contact_location_id']);
$auth_method = intval($row['contact_user_id']) ? (string) $row['user_auth_method'] : '';

// Tags
$contact_tag_id_array = [];
$sql_contact_tags = mysqli_query($mysqli, "SELECT tag_id FROM contact_tags WHERE contact_id = $contact_id");
while ($row = mysqli_fetch_assoc($sql_contact_tags)) {
    $contact_tag_id_array[] = intval($row['tag_id']);
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-edit me-2"></i>Editing contact: <strong><?= $contact_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off"
      data-itflow-phone-country="<?= escapeHtml($client_phone_iso2) ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">

    <div class="modal-body">
        <?php require 'contact_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_contact" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
