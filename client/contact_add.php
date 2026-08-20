<?php
/*
 * Client Portal
 * Contact management for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

enforceContactCan('contacts');

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
        <form action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <input type="text" class="form-control" name="contact_name" placeholder="Name" required maxlength="200">
                </div>
            </div>

            <div class="mb-3">
                <label>Email <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    <input type="email" class="form-control" name="contact_email" placeholder="Email" required maxlength="200">
                </div>
            </div>

            <label>Roles:</label>
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="contactBillingCheckbox" name="contact_billing" value="1">
                            <label class="form-check-label" for="contactBillingCheckbox">Billing</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="contactTechnicalCheckbox" name="contact_technical" value="1">
                            <label class="form-check-label" for="contactTechnicalCheckbox">Technical</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label>Portal authentication</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                    <select class="form-select select2 authMethod" name="contact_auth_method">
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
require_once "includes/footer.php";
