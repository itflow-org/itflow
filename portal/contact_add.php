<?php
/*
* Client Portal
* Contact management for PTC / technical contacts
*/

header("Content-Security-Policy: default-src 'self'");

require_once "inc_portal.php";

// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="index.php"><?php echo lang('home'); ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="contacts.php"><?php echo lang('contacts'); ?></a>
        </li>
        <li class="breadcrumb-item active"><?php echo lang('add_contact'); ?></li>
    </ol>

    <div class="col-md-6">
        <form action="portal_post.php" method="post">
            <input type="hidden" name="contact_billing" value="0">
            <input type="hidden" name="contact_technical" value="0">

            <div class="form-group">
                <label><?php echo lang('name'); ?> <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="contact_name" placeholder="<?php echo lang('name'); ?>" required maxlength="200">
                </div>
            </div>

            <div class="form-group">
                <label><?php echo lang('email'); ?> <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="contact_email" placeholder="<?php echo lang('email'); ?>" required maxlength="200">
                </div>
            </div>

            <label><?php echo lang('roles'); ?>:</label>
            <div class="form-row">
                <div class="col-md-4">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="contactBillingCheckbox" name="contact_billing" value="1">
                            <label class="custom-control-label" for="contactBillingCheckbox"><?php echo lang('billing'); ?></label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="contactTechnicalCheckbox" name="contact_technical" value="1">
                            <label class="custom-control-label" for="contactTechnicalCheckbox"><?php echo lang('technical'); ?></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label><?php echo lang('portal_authentication'); ?></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                    </div>
                    <select class="form-control select2 authMethod" name="contact_auth_method">
                        <option value="">- <?php echo lang('no_portal_access'); ?> -</option>
                        <option value="local"><?php echo lang('local_auth_method'); ?></option>
                        <?php if (!empty($config_azure_client_id)) { ?>
                            <option value="azure"><?php echo lang('azure_auth_method'); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary" name="add_contact"><?php echo lang('add'); ?></button>
        </form>
    </div>

<?php
require_once "portal_footer.php";
