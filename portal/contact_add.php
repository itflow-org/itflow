<?php
/*
 * Client Portal
 * Contact management for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self'");

require_once "inc_portal.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
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
        <li class="breadcrumb-item active">Add Contact</li>
    </ol>

    <div class="col-md-6">
        <form action="portal_post.php" method="post">
            <!-- Prevent undefined checkbox errors on submit -->
            <input type="hidden" name="contact_billing" value="0">
            <input type="hidden" name="contact_technical" value="0">

            <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="contact_name" placeholder="Name" required maxlength="200">
                </div>
            </div>

            <div class="form-group">
                <label>Email <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="contact_email" placeholder="Email" required maxlength="200">
                </div>
            </div>

            <label>Roles:</label>
            <div class="form-row">
                <div class="col-md-4">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="contactBillingCheckbox" name="contact_billing" value="1">
                            <label class="custom-control-label" for="contactBillingCheckbox">Billing</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="contactTechnicalCheckbox" name="contact_technical" value="1">
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
                        <option value="local">Local (Email and password)</option>
                        <?php if (!empty($config_azure_client_id)) { ?>
                            <option value="azure">Azure (Microsoft 365)</option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary" name="add_contact">Add</button>
        </form>
    </div>


<?php
require_once "portal_footer.php";
