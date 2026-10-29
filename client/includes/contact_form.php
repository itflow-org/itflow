<?php
/*
 * Client Portal
 * The contact form - adding a person, or the primary contact changing one -
 * for client/contact.php, which sets $new, $contact (null when new), $value
 * (what to show in each field) and $methods (portalSignInMethods()).
 * Posts portal_contact_save to post.php.
 */

isset($value) || die("Direct file access is not allowed");

$phone_input = $value('contact_phone') !== '' ? formatPhoneNumber($value('contact_phone'), $value('contact_phone_country_code'), false) : '';
$mobile_input = $value('contact_mobile') !== '' ? formatPhoneNumber($value('contact_mobile'), $value('contact_mobile_country_code'), false) : '';
?>

<form class="itflow-portal-panel" action="post.php" method="post" autocomplete="off" data-portal-once>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contact_id" value="<?= intval($contact['contact_id'] ?? 0) ?>">

    <div class="itflow-portal-panel-head">
        <h2><i class="fas fa-address-card"></i>Details</h2>
    </div>
    <div class="itflow-portal-panel-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="contactName">Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <input type="text" class="form-control" id="contactName" name="contact_name" value="<?= escapeHtml($value('contact_name')) ?>" required maxlength="200"<?= $new ? ' autofocus' : '' ?>>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="contactTitle">Job title</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                    <input type="text" class="form-control" id="contactTitle" name="contact_title" value="<?= escapeHtml($value('contact_title')) ?>" maxlength="200">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="contactEmail">Email <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" id="contactEmail" name="contact_email" value="<?= escapeHtml($value('contact_email')) ?>" required maxlength="200">
            </div>
            <?php if (!$new && !empty($contact['user_id'])) { ?><div class="form-text">It is also the address they sign in with.</div><?php } ?>
        </div>

        <div class="row">
            <div class="col-sm-8 mb-3">
                <label class="form-label" for="contactPhone">Phone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                    <input type="hidden" name="contact_phone_country_code" value="<?= escapeHtml($value('contact_phone_country_code')) ?>">
                    <input type="tel" class="form-control" id="contactPhone" name="contact_phone" value="<?= escapeHtml($phone_input) ?>" maxlength="200" data-itflow-phone="contact_phone_country_code">
                </div>
            </div>
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="contactExtension">Extension</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                    <input type="text" class="form-control" id="contactExtension" name="contact_extension" inputmode="numeric" maxlength="20" value="<?= escapeHtml($value('contact_extension')) ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-8 mb-3">
                <label class="form-label" for="contactMobile">Mobile</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                    <input type="hidden" name="contact_mobile_country_code" value="<?= escapeHtml($value('contact_mobile_country_code')) ?>">
                    <input type="tel" class="form-control" id="contactMobile" name="contact_mobile" value="<?= escapeHtml($mobile_input) ?>" maxlength="200" data-itflow-phone="contact_mobile_country_code">
                </div>
            </div>
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="contactDepartment">Department</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-sitemap"></i></span>
                    <input type="text" class="form-control" id="contactDepartment" name="contact_department" value="<?= escapeHtml($value('contact_department')) ?>" maxlength="200">
                </div>
            </div>
        </div>

        <fieldset class="mb-3">
            <legend class="form-label fs-6">Roles</legend>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="contactBilling" name="contact_billing" value="1"<?= intval($value('contact_billing', '0')) ? ' checked' : '' ?>>
                <label class="form-check-label" for="contactBilling">Billing <span class="d-block small text-body-secondary">Sees and pays invoices, quotes and statements, and gets the billing emails</span></label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="contactTechnical" name="contact_technical" value="1"<?= intval($value('contact_technical', '0')) ? ' checked' : '' ?>>
                <label class="form-check-label" for="contactTechnical">Technical <span class="d-block small text-body-secondary">Sees every ticket at <?= escapeHtml($session_client_name) ?>, and the devices, licenses and documents</span></label>
            </div>
        </fieldset>

        <?php if ($new) { ?>
            <fieldset class="itflow-portal-note d-block mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="contactInvite" name="invite" value="1"<?= !isset($form) || !empty($form['invite']) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="contactInvite">Give them portal access and email an invite <span class="d-block small text-body-secondary">So they can raise and follow tickets here themselves</span></label>
                </div>
                <?php if (count($methods) > 1) { ?>
                    <div class="input-group mt-2">
                        <span class="input-group-text"><i class="fa fa-fw fa-sign-in-alt"></i></span>
                        <select class="form-select" name="method" aria-label="How they sign in">
                            <?php foreach ($methods as $key => $label) { ?>
                                <option value="<?= $key ?>"<?= ($form['method'] ?? '') === $key ? ' selected' : '' ?>><?= escapeHtml($label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
            </fieldset>
        <?php } ?>

        <button type="submit" class="btn btn-primary" name="portal_contact_save"><i class="fas fa-check me-2"></i><?= $new ? 'Add them' : 'Save' ?></button>
    </div>
</form>
