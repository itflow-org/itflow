<?php

/*
 * Onboarding - IT basics: the client's domains, looked up as they are added
 * (registrar, expiry, DNS) with their website certificates, and vendors and
 * licenses from your templates - the ones every client should have, or
 * clients with its tags, ticked already (client_onboarding_it). Domains and
 * licenses are for agents with Support at Modify.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_support = lookupUserPermission('module_support') >= 2;
$co_domains = [];
if ($co_support) {
    $co_sql = mysqli_query($mysqli, "SELECT domain_id, domain_name, domain_expire, domain_description FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL ORDER BY domain_name ASC");
    while ($co_domain = mysqli_fetch_assoc($co_sql)) {
        $co_domains[] = $co_domain;
    }
}

// Vendor templates - which the client has, and which it should
$co_vendor_templates = [];
$co_sql = mysqli_query($mysqli, "SELECT vendor_template_id, vendor_template_name, vendor_template_description,
        (SELECT COUNT(*) FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL
            AND (vendors.vendor_template_id = vendor_templates.vendor_template_id OR LOWER(vendor_name) = LOWER(vendor_template_name))) AS has_it
    FROM vendor_templates WHERE vendor_template_archived_at IS NULL ORDER BY vendor_template_name ASC");
while ($co_template = mysqli_fetch_assoc($co_sql)) {
    $co_vendor_templates[intval($co_template['vendor_template_id'])] = $co_template;
}
$co_vendor_should = vendorTemplatesMissingFor($client_id);

$co_license_templates = [];
$co_license_should = [];
if ($co_support) {
    $co_sql = mysqli_query($mysqli, "SELECT software_template_id, software_template_name, software_template_type, software_template_license_type, vendor_template_name,
            (SELECT COUNT(*) FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL AND software.software_template_id = software_templates.software_template_id) AS has_it
        FROM software_templates LEFT JOIN vendor_templates ON vendor_templates.vendor_template_id = software_template_vendor_template_id AND vendor_template_archived_at IS NULL
        WHERE software_template_archived_at IS NULL ORDER BY software_template_name ASC");
    while ($co_template = mysqli_fetch_assoc($co_sql)) {
        $co_license_templates[intval($co_template['software_template_id'])] = $co_template;
    }
    $co_license_should = softwareTemplatesMissingFor($client_id);
}

$co_ticked = function ($name, $id, $should) use ($settings_kept) {
    if (!empty($settings_kept)) {
        return in_array((string) $id, array_map('strval', (array) ($_SESSION['co_kept_it'][$name] ?? [])), true);
    }
    return $should;
};

?>
<form action="post.php" method="post" autocomplete="off" data-gs-form data-co-form="it">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <?php if ($co_support) { ?>
        <h2 class="h6 fw-semibold"><i class="fas fa-fw fa-globe text-body-secondary me-1"></i>Domains</h2>
        <?php if ($co_domains) { ?>
            <ul class="list-unstyled itflow-gs-people mb-3" data-co-domains="<?= count($co_domains) ?>">
                <?php foreach ($co_domains as $co_domain) { ?>
                    <li data-co-domain="<?= intval($co_domain['domain_id']) ?>">
                        <span class="itflow-gs-person-initials" aria-hidden="true"><i class="fas fa-globe"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-semibold text-truncate"><?= escapeHtml($co_domain['domain_name']) ?></div>
                            <div class="small text-body-secondary text-truncate"><?= escapeHtml(trim((string) $co_domain['domain_description']) !== '' ? $co_domain['domain_description'] : 'Domain') ?>
                                <?= !empty($co_domain['domain_expire']) ? ' - renews ' . escapeHtml(date('M j, Y', strtotime($co_domain['domain_expire']))) : '' ?></div>
                        </div>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
        <div class="mb-4">
            <?= settingsTextarea('domains', '', ['id' => 'coDomains', 'label' => $co_domains ? 'Add more' : 'Their domains', 'rows' => 3, 'placeholder' => "example.com\nexample-mail.com",
                'help' => 'One to a line. Each is looked up as it is added - its registrar, renewal date and DNS - and its website certificate added, to watch for renewals.']) ?>
        </div>
    <?php } ?>

    <h2 class="h6 fw-semibold"><i class="fas fa-fw fa-building text-body-secondary me-1"></i>Vendors</h2>
    <?php if ($co_vendor_templates) {
        $co_listed = array_filter($co_vendor_templates, function ($t) use ($co_vendor_should) {
            return intval($t['has_it']) > 0 || isset($co_vendor_should[intval($t['vendor_template_id'])]);
        });
        $co_others = array_diff_key($co_vendor_templates, $co_listed); ?>
        <?php if ($co_listed) { ?>
            <div class="list-group mb-2" data-co-vendor-templates>
                <?php foreach ($co_listed as $co_id => $co_template) {
                    $co_has = intval($co_template['has_it']) > 0; ?>
                    <label class="list-group-item d-flex align-items-center gap-3<?= $co_has ? ' text-body-secondary' : '' ?>" for="coVendor<?= $co_id ?>" data-co-vendor-template="<?= $co_id ?>">
                        <input class="form-check-input flex-shrink-0 m-0" type="checkbox" name="vendor_templates[]" value="<?= $co_id ?>" id="coVendor<?= $co_id ?>"<?= $co_has ? ' checked disabled' : ($co_ticked('vendor_templates', $co_id, true) ? ' checked' : '') ?>>
                        <?= vendorTemplateTile($co_template['vendor_template_name'], 'sm') ?>
                        <span class="min-w-0 flex-grow-1">
                            <span class="fw-semibold d-block text-truncate"><?= escapeHtml($co_template['vendor_template_name']) ?></span>
                            <?php if (trim((string) $co_template['vendor_template_description']) !== '') { ?><span class="small text-body-secondary d-block text-truncate"><?= escapeHtml($co_template['vendor_template_description']) ?></span><?php } ?>
                        </span>
                        <?php if ($co_has) { ?>
                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal">They have it</span>
                        <?php } else { ?>
                            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal" title="Admin &gt; Vendor Templates says clients like this should have it">Should have</span>
                        <?php } ?>
                    </label>
                <?php } ?>
            </div>
        <?php } ?>
        <?php if ($co_others) { ?>
            <div class="itflow-settings-field mb-4">
                <label class="form-label" for="coVendorOthers"><?= $co_listed ? 'And others' : 'From your templates' ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" id="coVendorOthers" name="vendor_templates[]" multiple data-placeholder="Their ISP, software makers, suppliers...">
                        <?php foreach ($co_others as $co_id => $co_template) { ?>
                            <option value="<?= $co_id ?>"<?= $co_ticked('vendor_templates', $co_id, false) ? ' selected' : '' ?><?= selectOptionVendor($co_template['vendor_template_name'], (string) $co_template['vendor_template_description']) ?>><?= escapeHtml($co_template['vendor_template_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } else { ?>
            <div class="mb-4"></div>
        <?php } ?>
    <?php } else { ?>
        <p class="small text-body-secondary mb-4">No vendor templates yet - the ISPs, software makers and suppliers your clients use, set up once in Admin &gt; Vendor Templates. Add the client's own on its Vendors.</p>
    <?php } ?>

    <?php if ($co_support) { ?>
        <h2 class="h6 fw-semibold"><i class="fas fa-fw fa-key text-body-secondary me-1"></i>Licenses</h2>
        <?php if ($co_license_templates) {
            $co_listed = array_filter($co_license_templates, function ($t) use ($co_license_should) {
                return intval($t['has_it']) > 0 || isset($co_license_should[intval($t['software_template_id'])]);
            });
            $co_others = array_diff_key($co_license_templates, $co_listed); ?>
            <?php if ($co_listed) { ?>
                <div class="list-group mb-2" data-co-license-templates>
                    <?php foreach ($co_listed as $co_id => $co_template) {
                        $co_has = intval($co_template['has_it']) > 0; ?>
                        <label class="list-group-item d-flex align-items-center gap-3<?= $co_has ? ' text-body-secondary' : '' ?>" for="coLicense<?= $co_id ?>" data-co-license-template="<?= $co_id ?>">
                            <input class="form-check-input flex-shrink-0 m-0" type="checkbox" name="license_templates[]" value="<?= $co_id ?>" id="coLicense<?= $co_id ?>"<?= $co_has ? ' checked disabled' : ($co_ticked('license_templates', $co_id, true) ? ' checked' : '') ?>>
                            <span class="min-w-0 flex-grow-1">
                                <span class="fw-semibold d-block text-truncate"><?= escapeHtml($co_template['software_template_name']) ?></span>
                                <span class="small text-body-secondary d-block text-truncate"><?= escapeHtml(implode(' - ', array_filter([$co_template['software_template_type'], $co_template['software_template_license_type'], $co_template['vendor_template_name'] ? 'from ' . $co_template['vendor_template_name'] : '']))) ?></span>
                            </span>
                            <?php if ($co_has) { ?>
                                <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal">They have it</span>
                            <?php } else { ?>
                                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal" title="Admin &gt; License Templates says clients like this should have it">Should have</span>
                            <?php } ?>
                        </label>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($co_others) { ?>
                <div class="itflow-settings-field mb-2">
                    <label class="form-label" for="coLicenseOthers"><?= $co_listed ? 'And others' : 'From your templates' ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        <select class="form-select select2" id="coLicenseOthers" name="license_templates[]" multiple data-placeholder="Microsoft 365, antivirus, backup...">
                            <?php foreach ($co_others as $co_id => $co_template) { ?>
                                <option value="<?= $co_id ?>"<?= $co_ticked('license_templates', $co_id, false) ? ' selected' : '' ?>><?= escapeHtml($co_template['software_template_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <p class="small text-body-secondary mb-4">Each with the template's seats, renewal and vendor - the vendor added for them too, when they haven't got it. Put devices and users on them from the license afterwards.</p>
        <?php } else { ?>
            <p class="small text-body-secondary mb-4">No license templates yet - Microsoft 365, antivirus and the rest, set up once in Admin &gt; License Templates. Add the client's own on its Licenses.</p>
        <?php } ?>
    <?php } ?>

    <button type="submit" name="client_onboarding_it" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-check me-2"></i>Add them</button>
</form>
<?php unset($_SESSION['co_kept_it']); ?>
