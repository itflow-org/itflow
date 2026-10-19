<?php

/*
 * One vendor template - what a vendor made from it starts with (its name,
 * what they are, the support line, hours, SLA, support email and site, any
 * account number, PIN and account manager every client shares, and its
 * notes), who made it and last changed it, and its use: the clients with a
 * vendor made from it - each with its own account number and PIN, or not yet
 * - and the license templates that take their vendor from it. It can be
 * added to clients from here. The work is in functions/vendor_template.php.
 */

require_once "includes/inc_all_admin.php";

$template = vendorTemplateInfo($_GET['vendor_template_id'] ?? 0);
if (!$template) {
    echo emptyStateHtml(['thing' => 'vendor templates', 'title' => 'That vendor template is gone', 'text' => 'It may have been deleted.', 'icon' => 'fa-building', 'filtered' => false,
        'actions' => [['label' => 'Vendor Templates', 'href' => 'vendor_templates.php']]]);
    require_once "../includes/footer.php";
    exit();
}

$vendor_template_id = intval($template['vendor_template_id']);
$name = escapeHtml($template['vendor_template_name']);
// Tab title - used raw, the footer encodes it
$page_title = $template['vendor_template_name'];
$archived = !empty($template['vendor_template_archived_at']);
$usage = vendorTemplateUsage([$vendor_template_id])[$vendor_template_id] ?? ['clients' => 0, 'vendors' => 0, 'ours' => 0, 'last' => null];
$csrf = $_SESSION['csrf_token'];

// Its vendors - the current ones for current clients listed, the rest counted
$all_vendors = vendorTemplateVendors($vendor_template_id);
$vendors = array_filter($all_vendors, function ($vendor) {
    return !$vendor['archived'] && !$vendor['client_archived'];
});
$set_up = count(array_filter($vendors, function ($vendor) {
    return $vendor['client_id'] && $vendor['account_number'] !== '';
}));
$client_vendors = count(array_filter($vendors, function ($vendor) {
    return $vendor['client_id'] > 0;
}));
$others = count($all_vendors) - count($vendors);
$license_templates = vendorTemplateLicenseTemplates($vendor_template_id);

$edit_url = "modals/vendor_template/vendor_template_edit.php?id=$vendor_template_id";
$clients_url = "/agent/modals/vendor/vendor_template_clients.php?template_id=$vendor_template_id";

// What a vendor from it starts with - the ones it has
$t = $template;
$website = vendorWebsiteLabel($t['vendor_template_website']);
$facts = array_filter([
    'What they are' => escapeHtml((string) $t['vendor_template_description']),
    'Support line' => vendorTemplatePhoneHtml($t['vendor_template_phone'], $t['vendor_template_phone_country_code'], $t['vendor_template_extension']),
    'Support hours' => escapeHtml((string) $t['vendor_template_hours']),
    'SLA' => escapeHtml((string) $t['vendor_template_sla']),
    'Support email' => trim((string) $t['vendor_template_email']) !== '' ? '<a href="mailto:' . escapeHtml($t['vendor_template_email']) . '">' . escapeHtml($t['vendor_template_email']) . '</a>' : '',
    'Support site' => $website !== '' ? '<a href="' . escapeHtml(vendorWebsiteUrl($t['vendor_template_website'])) . '" target="_blank" rel="noopener noreferrer">' . escapeHtml($website) . '<i class="fas fa-external-link-alt small ms-1"></i></a>' : '',
    'Account number' => escapeHtml(trim((string) $t['vendor_template_account_number'])),
    'PIN' => vendorSecretHtml($t['vendor_template_code']),
    'Account manager' => escapeHtml(trim((string) $t['vendor_template_contact_name'])),
], function ($value) {
    return trim($value) !== '';
});
$icons = [];
foreach (vendorTemplateFieldLabels() as $field) {
    $icons[$field['label']] = $field['icon'];
}

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item"><a href="users.php">Admin</a></li>
    <li class="breadcrumb-item"><a href="vendor_templates.php">Vendor Templates</a></li>
    <li class="breadcrumb-item active"><?= $name ?></li>
</ol>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?= vendorTemplateTile($template['vendor_template_name'], 'lg') ?>
        <div class="min-w-0 flex-grow-1">
            <h1 class="h4 mb-0"><?= $name ?></h1>
            <?php if (trim((string) $template['vendor_template_description']) !== '') { ?>
                <div class="text-secondary"><?= escapeHtml($template['vendor_template_description']) ?></div>
            <?php } ?>
            <?php if ($archived) { ?>
                <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($template['vendor_template_archived_at'])) ?> - not offered for new vendors</div>
            <?php } ?>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <?php if (!$archived) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $clients_url ?>"><i class="fas fa-fw fa-users me-2"></i>Add to clients</button>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="post.php?copy_vendor_template=<?= $vendor_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-copy me-2"></i>Copy</a>
                    <?php if ($archived) { ?>
                        <a class="dropdown-item" href="post.php?restore_vendor_template=<?= $vendor_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                    <?php } else { ?>
                        <a class="dropdown-item" href="post.php?archive_vendor_template=<?= $vendor_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_vendor_template=<?= $vendor_template_id ?>&from_page=1&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="The vendors made from it stay as they are." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-building me-2"></i>What a vendor starts with</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-edit me-1"></i>Edit</button>
            </div>
            <div class="card-body">
                <dl class="row mb-0" data-vendor-template-facts>
                    <dt class="col-sm-3 fw-normal text-secondary"><i class="fas fa-fw fa-building me-1"></i>Name</dt>
                    <dd class="col-sm-9"><?= $name ?></dd>
                    <?php foreach ($facts as $label => $value) { ?>
                        <dt class="col-sm-3 fw-normal text-secondary"><i class="fas fa-fw <?= $icons[$label] ?? 'fa-circle' ?> me-1"></i><?= $label ?></dt>
                        <dd class="col-sm-9"><?= $value ?></dd>
                    <?php } ?>
                    <dt class="col-sm-3 fw-normal text-secondary"><i class="fas fa-fw fa-sticky-note me-1"></i>Notes</dt>
                    <dd class="col-sm-9 mb-0">
                        <?php if (trim((string) $template['vendor_template_notes']) !== '') { ?>
                            <div style="white-space: pre-wrap;"><?= escapeHtml($template['vendor_template_notes']) ?></div>
                        <?php } else { ?>
                            <span class="text-secondary">None - what every client's vendor should say: the escalation path, what they ask for before they help, how to open a case online.</span>
                        <?php } ?>
                    </dd>
                </dl>
                <?php if (!isset($facts['Support line']) && !isset($facts['Support site']) && !isset($facts['Support email'])) { ?>
                    <div class="small text-warning-emphasis mt-3"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>No way to reach their support yet - give it their support line, email or site, so every client's vendor has it.</div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3" id="vendorTemplateVendors">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-users me-2"></i>Clients with it</h3>
                <?php if (!$archived) { ?>
                    <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-url="<?= $clients_url ?>"><i class="fas fa-plus me-1"></i>Add to clients</button>
                <?php } ?>
            </div>
            <?php if ($vendors) { ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr><th class="ps-3">Client</th><th>Their vendor</th><th class="d-none d-sm-table-cell">Account</th><th class="d-none d-md-table-cell">Added</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vendors as $vendor_id => $vendor) {
                            $client_id = $vendor['client_id'];
                            $vendor_url = '/agent/vendor.php?' . ($client_id ? "client_id=$client_id&amp;" : '') . "vendor_id=$vendor_id";
                            ?>
                            <tr data-vendor-id="<?= $vendor_id ?>">
                                <td class="ps-3">
                                    <?php if ($client_id) { ?>
                                        <a class="fw-semibold text-body" href="/agent/vendors.php?client_id=<?= $client_id ?>"><?= escapeHtml($vendor['client_name']) ?></a>
                                    <?php } else { ?>
                                        <span class="text-body-secondary"><i class="fas fa-fw fa-briefcase me-1"></i>Ours</span>
                                    <?php } ?>
                                </td>
                                <td><a class="text-reset" href="<?= $vendor_url ?>"><?= escapeHtml($vendor['vendor_name']) ?></a></td>
                                <td class="d-none d-sm-table-cell small">
                                    <?php if ($vendor['account_number'] !== '') { ?>
                                        <span class="font-monospace"><?= escapeHtml($vendor['account_number']) ?></span>
                                        <?php if ($vendor['code'] !== '') { ?><span class="text-body-secondary ms-1" title="Has a PIN"><i class="fas fa-key"></i></span><?php } ?>
                                    <?php } elseif ($client_id) { ?>
                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal" title="Add the client's account number on their vendor, so support can find them" data-no-account>No account number</span>
                                    <?php } else { ?>
                                        <span class="text-body-secondary">-</span>
                                    <?php } ?>
                                </td>
                                <td class="d-none d-md-table-cell small text-body-secondary text-nowrap" title="<?= escapeHtml($vendor['created_at']) ?>"><?= date('M j, Y', strtotime($vendor['created_at'])) ?></td>
                                <td class="text-end pe-3"><a class="btn btn-sm btn-light" href="<?= $vendor_url ?>" title="Open their vendor"><i class="fas fa-fw fa-arrow-right"></i></a></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($others) { ?>
                    <div class="card-footer small text-secondary">And <?= $others ?> archived, or for an archived client.</div>
                <?php } ?>
            <?php } else { ?>
                <div class="card-body small text-secondary">No client has a vendor made from it yet<?= $others ? " - $others archived, or for an archived client" : '' ?>. Agents add one from <strong>New Vendor</strong> on a client's Vendors<?= $archived ? '' : ', or add it to several clients from here' ?>.</div>
            <?php } ?>
        </div>

    </div>

    <div class="col-lg-4 d-print-none">

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-chart-line me-2"></i>Used</h3></div>
            <div class="card-body" data-vendor-template-used>
                <?php if ($usage['clients'] || $usage['ours']) { ?>
                    <div class="mb-2">
                        <?php if ($usage['clients']) { ?><strong><?= $usage['clients'] ?></strong> client<?= $usage['clients'] === 1 ? ' has' : 's have' ?> a vendor from it<?php } ?>
                        <?php if ($usage['ours']) { ?><?= $usage['clients'] ? ', and it is one of ours' : 'It is one of our own vendors' ?><?php } ?>
                        <span class="small text-secondary">- last added <?= timeAgo($usage['last']) ?></span>
                    </div>
                    <?php if ($client_vendors) { ?>
                        <div class="small <?= $set_up < $client_vendors ? 'text-warning-emphasis' : 'text-success' ?>"><i class="fas fa-fw fa-fingerprint me-1"></i><?= $set_up < $client_vendors ? ($client_vendors - $set_up) . ' without the client\'s account number' : 'Every one has the client\'s account number' ?></div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="small text-secondary">No vendors made from it yet.</div>
                <?php } ?>
            </div>
        </div>

        <?php if ($license_templates) { ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-cube me-2"></i>License templates</h3></div>
                <div class="list-group list-group-flush small" data-vendor-template-licenses>
                    <?php foreach ($license_templates as $software_template_id => $license_template) { ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="software_template.php?software_template_id=<?= $software_template_id ?>">
                            <i class="fas fa-fw fa-cube text-secondary"></i>
                            <span class="min-w-0 text-truncate <?= $license_template['archived'] ? 'text-body-secondary' : '' ?>"><?= escapeHtml($license_template['name']) ?></span>
                            <?php if ($license_template['archived']) { ?><span class="badge text-bg-light border fw-normal ms-auto">Archived</span><?php } ?>
                        </a>
                    <?php } ?>
                </div>
                <div class="card-footer small text-secondary">Licenses made from these get the client's vendor from this template.</div>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-info-circle me-2"></i>Details</h3></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-normal text-secondary">Made</dt>
                    <dd class="col-7 mb-2" title="<?= escapeHtml($template['vendor_template_created_at']) ?>"><?= date('M j, Y', strtotime($template['vendor_template_created_at'])) ?><?= $template['created_by_name'] !== null ? '<div class="text-secondary">' . escapeHtml($template['created_by_name']) . '</div>' : '' ?></dd>
                    <?php if ($template['vendor_template_updated_at']) { ?>
                        <dt class="col-5 fw-normal text-secondary">Last changed</dt>
                        <dd class="col-7 mb-0" title="<?= escapeHtml($template['vendor_template_updated_at']) ?>"><?= timeAgo($template['vendor_template_updated_at']) ?><?= $template['updated_by_name'] !== null ? '<div class="text-secondary">' . escapeHtml($template['updated_by_name']) . '</div>' : '' ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </div>

        <!-- History, from the audit log -->
        <?= auditLogHistoryCard(auditLogHistory('Vendor Template', $vendor_template_id, 10)) ?>

    </div>
</div>

<?php
require_once "../includes/footer.php";
