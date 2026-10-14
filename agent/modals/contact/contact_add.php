<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Opened from an "Add Primary Contact" link, so the Primary switch starts on.
// A default, not a lock - it is still a normal switch.
$contact_primary_default = !empty($_GET['primary']);

// A contact's phone belongs to the CLIENT's country, not the company's. The
// client's country lives on its primary location; falls through to the
// company default when the client has none.
$client_phone_iso2 = '';
if ($client_id) {
    $row_client_country = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT location_country FROM locations
        WHERE location_client_id = $client_id AND location_primary = 1 LIMIT 1"));
    if ($row_client_country) {
        $client_phone_iso2 = $country_iso2_array[$row_client_country['location_country']] ?? '';
    }
}

$contact_form_mode = 'add';
$contact_id = 0;
$contact_name = $contact_title = $contact_department = $contact_email = $contact_pin = $contact_notes = $contact_photo = '';
$contact_phone = $contact_phone_country_code = $contact_extension = $contact_mobile = $contact_mobile_country_code = '';
$contact_primary = $contact_important = $contact_billing = $contact_technical = 0;
$contact_location_id = 0;
$auth_method = '';
$contact_tag_id_array = [];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-plus me-2"></i>New Contact</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off"
      data-itflow-phone-country="<?= escapeHtml($client_phone_iso2) ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php require 'contact_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_contact" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
