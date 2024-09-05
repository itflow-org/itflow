<?php
/*
 * Client Portal
 * Contact management for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self' fonts.googleapis.com fonts.gstatic.com");

require_once "inc_portal.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

//Initialize the HTML Purifier to prevent XSS
require_once "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

// Check for a contact ID
if (!isset($_GET['id']) && !intval($_GET['id'])) {
    header("Location: contacts.php");
    exit();
}

$contact_id = intval($_GET['id']);

$sql_contact = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_primary, contact_technical, contact_billing, contact_auth_method FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $session_client_id AND contacts.contact_archived_at IS NULL LIMIT 1");

$row = mysqli_fetch_array($sql_contact);

if ($row) {
    $contact_id = intval($row['contact_id']);
    $contact_name = nullable_htmlentities($row['contact_name']);
    $contact_email = nullable_htmlentities($row['contact_email']);
    $contact_primary = intval($row['contact_primary']);
    $contact_technical = intval($row['contact_technical']);
    $contact_billing = intval($row['contact_billing']);
} else {
    header("Location: portal_post.php?logout");
    exit();
}

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="index.php">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="contacts.php">Contacts</a>
        </li>
        <li class="breadcrumb-item active">Edit Contact</li>
    </ol>

    <div class="col-md-6">
        <form action="portal_post.php" method="post">
            <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
            <!-- Prevent undefined checkbox errors on submit -->
            <input type="hidden" name="contact_billing" value="0">
            <input type="hidden" name="contact_technical" value="0">

            <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="contact_name" value="<?php echo nullable_htmlentities($contact_name) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="text" class="form-control" name="contact_email" value="<?php echo nullable_htmlentities($contact_email) ?>" required>
                </div>
            </div>

            <label>Roles:</label>
            <div class="form-row">
                <div class="col-md-4">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="contactBillingCheckbox" name="contact_billing" value="1" <?php if ($contact_billing == 1) { echo "checked"; } ?>>
                            <label class="custom-control-label" for="contactBillingCheckbox">Billing</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="contactTechnicalCheckbox" name="contact_technical" value="1" <?php if ($contact_technical == 1) { echo "checked"; } ?>>
                            <label class="custom-control-label" for="contactTechnicalCheckbox">Technical</label>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($contact_primary) { echo "<i>Cannot edit the primary contact</i>"; } else { ?>
                <button class="btn btn-primary" name="edit_contact">Save</button>
            <?php } ?>
        </form>
    </div>


<?php
require_once "portal_footer.php";
