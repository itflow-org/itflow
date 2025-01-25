<?php
/*
 * Client Portal
 * Contact management for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: post.php?logout");
    exit();
}

// Check for a contact ID
if (!isset($_GET['id']) && !intval($_GET['id'])) {
    header("Location: contacts.php");
    exit();
}

$contact_id = intval($_GET['id']);

$sql_contact = mysqli_query(
    $mysqli, "SELECT contact_id, contact_name, contact_email, contact_primary, contact_technical, contact_billing, user_auth_method
    FROM contacts
    LEFT JOIN users ON user_id = contact_user_id
    WHERE contact_id = $contact_id AND contact_client_id = $session_client_id AND contacts.contact_archived_at IS NULL LIMIT 1"
);

$row = mysqli_fetch_array($sql_contact);

if ($row) {
    $contact_id = intval($row['contact_id']);
    $contact_name = nullable_htmlentities($row['contact_name']);
    $contact_email = nullable_htmlentities($row['contact_email']);
    $contact_primary = intval($row['contact_primary']);
    $contact_technical = intval($row['contact_technical']);
    $contact_billing = intval($row['contact_billing']);
    $contact_auth_method = nullable_htmlentities($row['user_auth_method']);
} else {
    header("Location: post.php?logout");
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
        <form action="post.php" method="post">
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
                    <input type="text" class="form-control" name="contact_name" value="<?php echo nullable_htmlentities($contact_name) ?>" required maxlength="200">
                </div>
            </div>

            <div class="form-group">
                <label>Email <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="contact_email" value="<?php echo nullable_htmlentities($contact_email) ?>" required maxlength="200">
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

            <div class="form-group">
                <label>Portal authentication</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                    </div>
                    <select class="form-control select2 authMethod" name="contact_auth_method">
                        <option value="">- No portal access -</option>
                        <option value="local" <?php if ($contact_auth_method == "local") { echo "selected"; } ?>>Local (Email and password)</option>
                        <?php if (!empty($config_azure_client_id)) { ?>
                            <option value="azure" <?php if ($contact_auth_method == "azure") { echo "selected"; } ?>>Azure (Microsoft 365)</option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <?php if ($contact_primary) { echo "<i>Cannot edit the primary contact</i>"; } else { ?>
                <button class="btn btn-primary" name="edit_contact">Save</button>
            <?php } ?>
        </form>
    </div>


<?php
require_once "includes/footer.php";
