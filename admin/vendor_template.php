<?php

/*
 * One vendor template - what a vendor made from it starts with (its name,
 * what they are, the support line, hours, SLA, support email and site, any
 * account number, PIN and account manager every client shares, and its
 * notes), who made it and last changed it, and its use: the clients with a
 * vendor made from it - each with its own account number and PIN, or not yet,
 * and in step with it or saying something else (views, and bringing one or
 * several in line) - the vendors made by hand that look like it (linking
 * them), who should have it with the clients missing it, and the license
 * templates that take their vendor from it. It can be added to clients from
 * here. The work is in functions/vendor_template.php.
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
$current = array_filter($all_vendors, function ($vendor) {
    return !$vendor['archived'] && !$vendor['client_archived'];
});
$set_up = count(array_filter($current, function ($vendor) {
    return $vendor['client_id'] && $vendor['account_number'] !== '';
}));
$client_vendors = count(array_filter($current, function ($vendor) {
    return $vendor['client_id'] > 0;
}));
$out_of_step = count(array_filter($current, function ($vendor) {
    return (bool) $vendor['differences'];
}));
$others = count($all_vendors) - count($current);
$license_templates = vendorTemplateLicenseTemplates($vendor_template_id);

// ...seen all at once, the ones out of step, or the ones without the client's account number
$views = [
    '' => ['label' => 'All', 'icon' => 'fa-list', 'title' => 'Every current vendor made from it', 'test' => function ($vendor) {
        return true;
    }],
    'out' => ['label' => 'Out of step', 'icon' => 'fa-not-equal', 'title' => 'Saying something other than the template does', 'test' => function ($vendor) {
        return (bool) $vendor['differences'];
    }],
    'account' => ['label' => 'No account number', 'icon' => 'fa-fingerprint', 'title' => "Without the client's account number", 'test' => function ($vendor) {
        return $vendor['client_id'] && $vendor['account_number'] === '';
    }],
];
$view = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$view_counts = [];
foreach ($views as $key => $definition) {
    $view_counts[$key] = count(array_filter($current, $definition['test']));
}
$vendors = array_filter($current, $views[$view]['test']);
$view_url = function ($key) use ($vendor_template_id) {
    return '?' . http_build_query(array_filter(['vendor_template_id' => $vendor_template_id, 'view' => $key])) . '#vendorTemplateVendors';
};

// Vendors made by hand that look like it - not offered while it is archived
$matches = $archived ? [] : vendorTemplateMatches($vendor_template_id);

// Who should have one - not counted while it is archived
$required = intval($template['vendor_template_required']);
$required_tags = vendorTemplateRequiredTags([$vendor_template_id])[$vendor_template_id];
$scope_text = vendorTemplateScopeText($required, $required_tags);
$coverage = $required && !$archived ? vendorTemplateCoverage($vendor_template_id, $required, array_keys($required_tags)) : null;
$list_limit = 8;
$missing_text = $coverage && $coverage['missing'] ? (count($coverage['missing']) === 1 ? 'the client missing it' : 'the ' . count($coverage['missing']) . ' clients missing it') : '';

$edit_url = "modals/vendor_template/vendor_template_edit.php?id=$vendor_template_id";
$clients_url = "/agent/modals/vendor/vendor_template_clients.php?template_id=$vendor_template_id";
$required_url = "modals/vendor_template/vendor_template_required.php?id=$vendor_template_id";
$update_url = "/agent/modals/vendor/vendor_template_update.php";
$in_line_url = "$update_url?template_id=$vendor_template_id";

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
                <?php if ($missing_text) { ?>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $clients_url ?>"><i class="fas fa-fw fa-users me-2"></i>Add to clients</button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More ways to add it"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $clients_url ?>&missing=1"><i class="fas fa-fw fa-clipboard-check me-2"></i>To <?= $missing_text ?></a>
                        </div>
                    </div>
                <?php } else { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $clients_url ?>"><i class="fas fa-fw fa-users me-2"></i>Add to clients</button>
                <?php } ?>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $required_url ?>"><i class="fas fa-fw fa-clipboard-check me-2"></i>Who should have it</a>
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
                <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                <?php if ($current) { ?>
                    <div class="dropdown" id="bulkActionButton" hidden>
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-layer-group me-1"></i>Bulk Action (<span id="selectedCount"></span>)
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $in_line_url ?>" data-bulk="true"><i class="fas fa-fw fa-sync-alt me-2"></i>Bring in line</a>
                        </div>
                    </div>
                <?php } ?>
                <?php if (!$archived) { ?>
                    <button type="button" class="btn btn-sm btn-link ajax-modal" data-modal-url="<?= $clients_url ?>"><i class="fas fa-plus me-1"></i>Add to clients</button>
                <?php } ?>
                </div>
            </div>
            <?php if ($current) { ?>
                <div class="card-header py-2">
                    <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto">
                        <?php foreach ($views as $key => $definition) {
                            if ($key !== '' && $key !== $view && !$view_counts[$key]) {
                                continue;
                            } ?>
                            <a class="nav-link text-nowrap <?= $key === $view ? 'active' : '' ?>" href="<?= escapeHtml($view_url($key)) ?>" title="<?= escapeHtml($definition['title']) ?>" data-vendor-view="<?= $key ?>">
                                <i class="fas fa-fw <?= $definition['icon'] ?> me-1"></i><?= escapeHtml($definition['label']) ?>
                                <span class="badge rounded-pill ms-1 <?= $key === 'out' && $key !== $view ? 'text-bg-warning' : '' ?>"><?= $view_counts[$key] ?></span>
                            </a>
                        <?php } ?>
                    </nav>
                </div>
                <?php if ($view === 'out' && $vendors) { ?>
                    <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2 bg-body-tertiary">
                        <span class="small text-secondary">Saying something other than the template does - it may be the client's own, or the template changed since.</span>
                        <button type="button" class="btn btn-sm btn-primary ms-auto ajax-modal" data-modal-size="lg" data-view-action
                            data-modal-url="<?= escapeHtml($in_line_url . '&' . implode('&', array_map(function ($id) {
                                return 'vendor_ids[]=' . $id;
                            }, array_keys($vendors)))) ?>"><i class="fas fa-fw fa-sync-alt me-1"></i>Bring these in line</button>
                    </div>
                <?php } ?>
            <?php } ?>
            <?php if ($vendors) { ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <td class="ps-3 pe-0" style="width: 1rem;"><input class="form-check-input" type="checkbox" id="selectAllCheckbox" aria-label="Tick them all"></td>
                                <th>Client</th><th class="d-none d-md-table-cell">Their vendor</th><th>In step</th><th class="d-none d-sm-table-cell">Account</th><th class="d-none d-lg-table-cell">Added</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vendors as $vendor_id => $vendor) {
                            $client_id = $vendor['client_id'];
                            $vendor_url = '/agent/vendor.php?' . ($client_id ? "client_id=$client_id&amp;" : '') . "vendor_id=$vendor_id";
                            $differences = $vendor['differences'];
                            $differs_text = count($differences) > 2 ? count($differences) . ' fields' : ucfirst(vendorTemplateLabelsText($differences));
                            ?>
                            <tr data-vendor-id="<?= $vendor_id ?>">
                                <td class="ps-3 pe-0"><input class="form-check-input bulk-select" type="checkbox" name="vendor_ids[]" value="<?= $vendor_id ?>" aria-label="Tick <?= escapeHtml($client_id ? $vendor['client_name'] : 'ours') ?>"></td>
                                <td>
                                    <?php if ($client_id) { ?>
                                        <a class="fw-semibold text-body" href="/agent/vendors.php?client_id=<?= $client_id ?>"><?= escapeHtml($vendor['client_name']) ?></a>
                                    <?php } else { ?>
                                        <span class="text-body-secondary"><i class="fas fa-fw fa-briefcase me-1"></i>Ours</span>
                                    <?php } ?>
                                    <?php if ($vendor['vendor_name'] !== $template['vendor_template_name']) { ?><div class="d-md-none small text-secondary"><?= escapeHtml($vendor['vendor_name']) ?></div><?php } ?>
                                </td>
                                <td class="d-none d-md-table-cell"><a class="text-reset" href="<?= $vendor_url ?>"><?= escapeHtml($vendor['vendor_name']) ?></a></td>
                                <td class="small">
                                    <?php if ($differences) { ?>
                                        <a href="#" class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal text-decoration-none ajax-modal" data-modal-size="lg"
                                            data-modal-url="<?= $update_url ?>?vendor_id=<?= $vendor_id ?>" title="Differs in <?= escapeHtml(vendorTemplateLabelsText($differences)) ?> - bring it in line" data-out-of-step="<?= escapeHtml(implode(',', array_keys($differences))) ?>"><i class="fas fa-not-equal me-1"></i><?= escapeHtml($differs_text) ?></a>
                                    <?php } else { ?>
                                        <span class="text-success text-nowrap" data-in-step><i class="fas fa-check me-1"></i>In step</span>
                                    <?php } ?>
                                </td>
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
                                <td class="d-none d-lg-table-cell small text-body-secondary text-nowrap" title="<?= escapeHtml($vendor['created_at']) ?>"><?= date('M j, Y', strtotime($vendor['created_at'])) ?></td>
                                <td class="text-end pe-3">
                                    <div class="dropdown dropstart">
                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="<?= $vendor_url ?>"><i class="fas fa-fw fa-building text-secondary me-2"></i>Open their vendor</a>
                                            <?php if ($differences) { ?>
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $update_url ?>?vendor_id=<?= $vendor_id ?>"><i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Bring in line</a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item confirm-link" href="/agent/post.php?unlink_vendor_template=<?= $vendor_id ?>&amp;csrf_token=<?= $csrf ?>"
                                                data-confirm-title="Unlink <?= escapeHtml($client_id ? $vendor['client_name'] . "'s" : 'our') ?> <?= escapeHtml($vendor['vendor_name']) ?>?" data-confirm-text="It stays as it is, and is no longer kept in step with the template." data-confirm-button="Unlink"><i class="fas fa-fw fa-unlink text-secondary me-2"></i>Unlink</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($others) { ?>
                    <div class="card-footer small text-secondary">And <?= $others ?> archived, or for an archived client.</div>
                <?php } ?>
            <?php } elseif ($current) { ?>
                <div class="card-body small text-secondary">None of them - <a href="<?= escapeHtml($view_url('')) ?>">see them all</a>.</div>
            <?php } else { ?>
                <div class="card-body small text-secondary">No client has a vendor made from it yet<?= $others ? " - $others archived, or for an archived client" : '' ?>. Agents add one from <strong>New Vendor</strong> on a client's Vendors<?= $archived ? '' : ', or add it to several clients from here' ?>.</div>
            <?php } ?>
        </div>

        <?php if ($matches) { ?>
            <!-- Vendors made by hand that look like it -->
            <div class="card mb-3" id="vendorTemplateMatches">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-link me-2"></i>Not linked yet <span class="badge rounded-pill text-bg-light border fw-normal ms-1"><?= count($matches) ?></span></h3>
                </div>
                <div class="card-body small text-secondary pb-2">Vendors made by hand with its name or support site, at clients without one from it. Linked, they count as made from it and are kept in step with it - nothing on them changes unless you say so.</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($matches as $match_id => $match) {
                        $match_client = $match['client_id'];
                        $match_url = '/agent/vendor.php?' . ($match_client ? "client_id=$match_client&amp;" : '') . "vendor_id=$match_id"; ?>
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-2" data-match-vendor-id="<?= $match_id ?>">
                            <div class="min-w-0 flex-grow-1">
                                <span class="fw-semibold"><?= $match_client ? escapeHtml($match['client_name']) : '<i class="fas fa-fw fa-briefcase me-1 text-secondary"></i>Ours' ?></span>
                                <a class="text-reset ms-1" href="<?= $match_url ?>"><?= escapeHtml($match['vendor_name']) ?></a>
                                <div class="small text-secondary">
                                    <?= $match['how'] === 'name' ? 'Same name' : 'Same support site' ?>
                                    - <?= $match['differences'] ? 'differs in ' . escapeHtml(vendorTemplateLabelsText($match['differences'])) : 'says what the template says' ?>
                                </div>
                            </div>
                            <form action="/agent/post.php" method="post" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="vendor_template_id" value="<?= $vendor_template_id ?>">
                                <input type="hidden" name="vendor_ids[]" value="<?= $match_id ?>">
                                <button type="submit" name="link_vendors_to_template" class="btn btn-sm btn-light"><i class="fas fa-link me-1"></i>Link</button>
                            </form>
                        </div>
                    <?php } ?>
                </div>
                <?php if (count($matches) > 1 || array_filter(array_column($matches, 'differences'))) { ?>
                    <form action="/agent/post.php" method="post" class="card-footer d-flex flex-wrap align-items-center gap-3" data-link-all>
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="vendor_template_id" value="<?= $vendor_template_id ?>">
                        <?php foreach (array_keys($matches) as $match_id) { ?>
                            <input type="hidden" name="vendor_ids[]" value="<?= $match_id ?>">
                        <?php } ?>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="bring" value="1" id="vendorTemplateLinkBring">
                            <label class="form-check-label small" for="vendorTemplateLinkBring">and take the template's support details, notes and name</label>
                        </div>
                        <button type="submit" name="link_vendors_to_template" class="btn btn-sm btn-outline-primary ms-auto"><i class="fas fa-link me-1"></i>Link <?= count($matches) === 1 ? 'it' : 'all ' . count($matches) ?></button>
                    </form>
                <?php } ?>
            </div>
        <?php } ?>

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
                    <?php if ($current) { ?>
                        <div class="small mb-1 <?= $out_of_step ? 'text-warning-emphasis' : 'text-success' ?>" data-vendor-template-in-step><i class="fas fa-fw <?= $out_of_step ? 'fa-not-equal' : 'fa-check' ?> me-1"></i><?= $out_of_step ? "$out_of_step out of step with it" : (count($current) === 1 ? 'In step with it' : 'Every one in step with it') ?></div>
                    <?php } ?>
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

        <div class="card mb-3" id="vendorTemplateCoverage">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-clipboard-check me-2"></i>Who should have it</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-url="<?= $required_url ?>"><i class="fas fa-edit me-1"></i>Set up</button>
            </div>
            <div class="card-body">
                <?php if (!$required) { ?>
                    <div class="small text-secondary">No one in particular. Set it up to see which clients are missing it - every client, or the ones with a tag.</div>
                <?php } elseif ($archived) { ?>
                    <div class="small text-secondary"><?= escapeHtml($scope_text) ?> - not counted while it is archived.</div>
                <?php } else {
                    $percent = $coverage['clients'] ? intval(floor($coverage['covered'] * 100 / $coverage['clients'])) : 100;
                    ?>
                    <div class="d-flex align-items-baseline gap-2 mb-1">
                        <span class="fw-semibold min-w-0 text-truncate" title="<?= escapeHtml($scope_text) ?>"><?= escapeHtml($scope_text) ?></span>
                        <span class="ms-auto small text-nowrap"><strong><?= $coverage['covered'] ?></strong> of <?= $coverage['clients'] ?> have it</span>
                    </div>
                    <div class="progress mb-2" role="progressbar" aria-label="Clients with it" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                        <div class="progress-bar <?= $coverage['missing'] ? 'bg-warning' : 'bg-success' ?>" style="width: <?= $percent ?>%"></div>
                    </div>
                    <?php if ($coverage['missing']) { ?>
                        <div class="small text-secondary mb-1">Missing it</div>
                        <div class="list-group list-group-flush small mb-2">
                            <?php foreach (array_slice($coverage['missing'], 0, $list_limit, true) as $client_id => $client_name) { ?>
                                <div class="list-group-item px-0 d-flex align-items-center gap-2" data-missing-client-id="<?= $client_id ?>">
                                    <i class="fas fa-fw fa-user text-secondary"></i>
                                    <a class="min-w-0 flex-grow-1 text-truncate text-reset" href="/agent/vendors.php?client_id=<?= $client_id ?>"><?= escapeHtml($client_name) ?></a>
                                    <button type="button" class="btn btn-sm btn-light py-0 ajax-modal" data-modal-url="<?= $clients_url ?>&client_ids[]=<?= $client_id ?>"><i class="fas fa-plus me-1"></i>Add</button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if (count($coverage['missing']) > $list_limit) { ?>
                            <div class="small text-secondary mb-2">and <?= count($coverage['missing']) - $list_limit ?> more</div>
                        <?php } ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-url="<?= $clients_url ?>&missing=1"><i class="fas fa-users me-1"></i>Add to <?= $missing_text ?></button>
                    <?php } elseif ($coverage['clients']) { ?>
                        <div class="small text-success"><i class="fas fa-fw fa-check me-1"></i>Every one of them has it.</div>
                    <?php } else { ?>
                        <div class="small text-secondary"><?= $required === VENDOR_TEMPLATE_REQUIRED_TAGGED ? ($required_tags ? 'No clients have ' . (count($required_tags) === 1 ? 'that tag' : 'those tags') : 'No tags picked, so no clients') : 'No clients yet' ?>.</div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

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

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
