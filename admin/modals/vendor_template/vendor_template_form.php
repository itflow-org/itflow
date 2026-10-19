<?php

/*
 * A vendor template's fields - for vendor_template_add.php and
 * vendor_template_edit.php, which set $vt (vendorTemplateRowFields() of the
 * template, or blanks when new), $vt_id (0 when new) and, editing, $vt_vendors
 * (how many vendors were made from it). The same tabs as a vendor's own
 * window - Details, Support, Account and Notes - as a vendor made from it
 * gets every one of them. Editing, the fields can be changed on the vendors
 * made from it too. Posts to admin/post.php (admin/post/vendor_template.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_id = 'vendorTemplateForm' . ($vt_id ?: 'New');
$value = function ($key) use ($vt) {
    return escapeHtml((string) ($vt[$key] ?? ''));
};
$phone_value = ($vt['phone'] ?? '') !== '' ? escapeHtml(formatPhoneNumber($vt['phone'], $vt['phone_country_code'] ?? '', false)) : '';

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-building me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Support"><i class="fas fa-fw fa-headset me-2 d-none d-lg-inline"></i>Support</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Account"><i class="fas fa-fw fa-id-card me-2 d-none d-lg-inline"></i>Account</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- Who they are -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Details">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Comcast Business, Dell, Microsoft" maxlength="200" value="<?= $value('name') ?>" required <?= $vt_id ? '' : 'autofocus' ?>>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">What they are</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Business internet and phone, servers and workstations" maxlength="200" value="<?= $value('description') ?>">
            </div>
        </div>
    </div>
    <div class="form-text mt-3">A vendor added to a client from it starts with everything here - their own account number and PIN go on after.</div>
</div>

<!-- How to reach support -->
<div class="tab-pane fade" id="<?= $form_id ?>Support">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Support phone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                <input type="hidden" name="phone_country_code" value="<?= $value('phone_country_code') ?>">
                <input type="tel" class="form-control" name="phone" value="<?= $phone_value ?>" placeholder="Phone number" maxlength="200" data-itflow-phone="phone_country_code">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Extension / option</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                <input type="text" class="form-control" name="extension" value="<?= $value('extension') ?>" placeholder="ext." maxlength="200" inputmode="numeric">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Support hours</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <input type="text" class="form-control" name="hours" placeholder="24/7, Mon-Fri 8-6 ET" maxlength="200" value="<?= $value('hours') ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">SLA</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                <input type="text" class="form-control" name="sla" placeholder="4 hour response, next business day" maxlength="200" value="<?= $value('sla') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Support email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" name="email" placeholder="support@vendor.com" maxlength="200" value="<?= $value('email') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Support site or portal</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                <input type="text" class="form-control" name="website" placeholder="support.vendor.com" maxlength="200" value="<?= $value('website') ?>">
            </div>
        </div>
    </div>
</div>

<!-- What they ask for, and who looks after us -->
<div class="tab-pane fade" id="<?= $form_id ?>Account">
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label">Account number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                <input type="text" class="form-control" name="account_number" placeholder="Usually each client's own - leave blank" maxlength="200" value="<?= $value('account_number') ?>">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">PIN / passcode</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <input type="text" class="form-control" name="code" placeholder="Usually each client's own" maxlength="200" value="<?= $value('code') ?>" autocomplete="off">
            </div>
        </div>
        <div class="col-12">
            <div class="form-text mt-0">Fill these in only when every client uses the same one - your own partner or reseller account, say.</div>
        </div>
        <div class="col-12">
            <label class="form-label">Account manager</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                <input type="text" class="form-control" name="contact_name" placeholder="Your rep there, when every client has the same one" maxlength="200" value="<?= $value('contact_name') ?>">
            </div>
        </div>
    </div>
</div>

<!-- Everything else -->
<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <label class="form-label">Notes</label>
    <textarea class="form-control" rows="10" name="notes" placeholder="Escalation path, what they need before they will help, menu options, how to open a case online - what every client's vendor should say"><?= $value('notes') ?></textarea>
</div>

</div>

<?php if ($vt_id && !empty($vt_vendors)) { ?>
    <!-- The vendors made from it, changed too - the fields ticked -->
    <div class="border rounded p-3 mt-3 bg-body-tertiary" data-vendor-template-push>
        <div class="fw-semibold mb-1"><i class="fas fa-fw fa-sync-alt text-secondary me-1"></i>Change them on the <?= $vt_vendors ?> vendor<?= $vt_vendors === 1 ? '' : 's' ?> made from it too</div>
        <div class="small text-body-secondary mb-2">Ticked fields are set to what is here on each one - what a client's vendor says now is replaced.</div>
        <div class="row row-cols-2 row-cols-md-3 g-1 small">
            <?php foreach (vendorTemplateFieldLabels() as $key => $field) { ?>
                <div class="col">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="push[]" value="<?= $key ?>" id="<?= $form_id ?>Push<?= $key ?>">
                        <label class="form-check-label" for="<?= $form_id ?>Push<?= $key ?>"><i class="fas fa-fw <?= $field['icon'] ?> text-secondary me-1"></i><?= $field['label'] ?></label>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<script>
    itflowReady(function () {
        var form = document.getElementById(<?= json_encode($form_id) ?>).closest('form');

        // A required field on a tab that isn't showing: show that tab and point at it
        var reported = false;
        form.addEventListener('invalid', function (e) {
            var pane = e.target.closest('.tab-pane');
            var tab = pane && form.querySelector('[data-bs-toggle="pill"][href="#' + pane.id + '"]');
            if (!tab || pane.classList.contains('active') || reported) {
                return;
            }
            reported = true;
            tab.addEventListener('shown.bs.tab', function () {
                reported = false;
                e.target.reportValidity();
            }, { once: true });
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }, true);
    });
</script>
