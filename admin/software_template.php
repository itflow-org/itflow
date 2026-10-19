<?php

/*
 * One license template - what a license made from it starts with (its name,
 * version, summary, type, license type, seats, how often it renews, its vendor,
 * the product it is sold as and its notes), who made it and last changed it,
 * and its use: how many licenses have been made from it, for how many
 * clients, the seats on them, the ones renewing soon or lapsed - and who
 * should have one, with the clients missing it. Its licenses are listed by
 * client with their seats, renewal and how they are billed, in views - over
 * seats, spare seats, renewing, expired, not billed - and can be trued up,
 * renewed and billed on the clients' recurring invoices from here, ticked or
 * a whole view at once. New licenses can be made from here for a client,
 * several at once, or every client missing one. The work is in
 * functions/software_template.php.
 */

require_once "includes/inc_all_admin.php";

$template = softwareTemplateRow($_GET['software_template_id'] ?? 0);
if (!$template) {
    echo emptyStateHtml(['thing' => 'license templates', 'title' => 'That license template is gone', 'text' => 'It may have been deleted.', 'icon' => 'fa-cube', 'filtered' => false,
        'actions' => [['label' => 'License Templates', 'href' => 'software_templates.php']]]);
    require_once "../includes/footer.php";
    exit();
}

$software_template_id = intval($template['software_template_id']);
$name = escapeHtml($template['software_template_name']);
// Tab title - used raw, the footer encodes it
$page_title = $template['software_template_name'];
$archived = !empty($template['software_template_archived_at']);
$usage = softwareTemplateUsage([$software_template_id])[$software_template_id] ?? ['count' => 0, 'clients' => 0, 'seats' => 0, 'used' => 0, 'renewing' => 0, 'expired' => 0, 'last' => null];
$type_style = softwareTypeStyle($template['software_template_type']);
$csrf = $_SESSION['csrf_token'];

// Its licenses by client, with how each is billed when it is sold as a product - in the view picked
$product_id = intval($template['software_template_product_id']);
$has_product = $template['product_name'] !== null;
$all_licenses = softwareTemplateLicenses($software_template_id, $product_id);
$views = softwareTemplateLicenseViews($has_product);
$view = isset($views[(string) ($_GET['view'] ?? '')]) ? (string) $_GET['view'] : '';
$view_counts = [];
foreach ($views as $key => $definition) {
    $view_counts[$key] = count(array_filter($all_licenses, $definition['test']));
}
$licenses = array_filter($all_licenses, $views[$view]['test']);
$view_url = function ($key) use ($software_template_id) {
    return '?' . http_build_query(array_filter(['software_template_id' => $software_template_id, 'view' => $key])) . '#softwareTemplateLicenses';
};

// Who should have one - not counted while it is archived
$required = intval($template['software_template_required']);
$required_tags = softwareTemplateRequiredTags([$software_template_id])[$software_template_id];
$scope_text = softwareTemplateScopeText($required, $required_tags);
$coverage = $required && !$archived ? softwareTemplateCoverage($software_template_id, $required, array_keys($required_tags)) : null;
$list_limit = 8;
$missing_text = $coverage && $coverage['missing'] ? (count($coverage['missing']) === 1 ? 'the client missing one' : 'the ' . count($coverage['missing']) . ' clients missing one') : '';

$edit_url = "modals/software_template/software_template_edit.php?id=$software_template_id";
$use_url = "modals/software_template/software_template_use.php?id=$software_template_id";
$several_url = "modals/software_template/software_template_use_several.php?id=$software_template_id";
$required_url = "modals/software_template/software_template_required.php?id=$software_template_id";
$true_up_url = "modals/software_template/software_template_true_up.php?id=$software_template_id";
$bill_url = "modals/software_template/software_template_bill.php?id=$software_template_id";
$renew_url = '/agent/modals/software/software_renew.php';

// What can be done to the whole of a view at once
$view_ids = function () use ($licenses) {
    return implode('&', array_map(function ($id) {
        return 'software_ids[]=' . $id;
    }, array_keys($licenses)));
};
$view_action = [
    'over' => ['label' => 'True up these', 'icon' => 'fa-balance-scale', 'url' => $true_up_url],
    'spare' => ['label' => 'True up these', 'icon' => 'fa-balance-scale', 'url' => $true_up_url],
    'renewing' => ['label' => 'Renew these', 'icon' => 'fa-redo-alt', 'url' => $renew_url],
    'expired' => ['label' => 'Renew these', 'icon' => 'fa-redo-alt', 'url' => $renew_url],
    'unbilled' => ['label' => 'Bill these', 'icon' => 'fa-file-invoice-dollar', 'url' => $bill_url],
][$view] ?? null;

$term = (string) $template['software_template_term'];
$vendor_name = (string) $template['vendor_template_name'];
$product_name = (string) $template['product_name'];
$facts = array_filter([
    'Type' => $template['software_template_type'] !== '' ? '<i class="fas fa-fw ' . $type_style['icon'] . ' text-secondary me-1"></i>' . escapeHtml($template['software_template_type']) : '',
    'License type' => (string) $template['software_template_license_type'] !== '' ? '<i class="fas fa-fw ' . softwareLicenseTypeIcon($template['software_template_license_type']) . ' text-secondary me-1"></i>' . escapeHtml($template['software_template_license_type']) : '',
    'Version' => escapeHtml((string) $template['software_template_version']),
    'Seats' => $template['software_template_seats'] !== null ? '<i class="fas fa-fw fa-users text-secondary me-1"></i>' . intval($template['software_template_seats']) : '',
    'Renews every' => isset(SOFTWARE_RENEWAL_TERMS[$term]) ? '<i class="fas fa-fw fa-redo-alt text-secondary me-1"></i>' . escapeHtml(SOFTWARE_RENEWAL_TERMS[$term]) . ' <span class="small text-secondary">- from when it was bought</span>' : '',
    'Vendor' => $vendor_name !== '' ? '<span class="d-inline-flex align-items-center gap-2">' . vendorTile($vendor_name, 'xs') . escapeHtml($vendor_name) . '</span>'
        . ($template['vendor_template_archived_at'] ? ' <span class="small text-secondary">- its vendor template is archived, so licenses from it get no vendor</span>' : '') : '',
    'Sold as' => $product_name !== '' ? '<a href="/agent/product.php?product_id=' . intval($template['software_template_product_id']) . '">' . escapeHtml($product_name) . '</a> <span class="small text-secondary">' . escapeHtml(softwareTemplatePriceText($template)) . '</span>'
        . ($template['product_archived_at'] ? ' <span class="small text-secondary">- archived</span>' : '') : '',
]);

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item"><a href="users.php">Admin</a></li>
    <li class="breadcrumb-item"><a href="software_templates.php">License Templates</a></li>
    <li class="breadcrumb-item active"><?= $name ?></li>
</ol>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw <?= $type_style['icon'] ?>"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h1 class="h4 mb-0"><?= $name ?><?php if ($template['software_template_version']) { ?> <span class="text-secondary fw-normal fs-6"><?= escapeHtml($template['software_template_version']) ?></span><?php } ?></h1>
            <?php if (trim((string) $template['software_template_description']) !== '') { ?>
                <div class="text-secondary"><?= escapeHtml($template['software_template_description']) ?></div>
            <?php } ?>
            <?php if ($archived) { ?>
                <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($template['software_template_archived_at'])) ?> - not offered for new licenses</div>
            <?php } ?>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <?php if (!$archived) { ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $use_url ?>"><i class="fas fa-fw fa-plus me-2"></i>New license for a client</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More ways to make one"></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $several_url ?>"><i class="fas fa-fw fa-copy me-2"></i>For several clients</a>
                        <?php if ($coverage && $coverage['missing']) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $several_url ?>&missing=1"><i class="fas fa-fw fa-clipboard-check me-2"></i>For <?= $missing_text ?></a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $required_url ?>"><i class="fas fa-fw fa-clipboard-check me-2"></i>Who should have it</a>
                    <a class="dropdown-item" href="post.php?copy_software_template=<?= $software_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-copy me-2"></i>Copy</a>
                    <?php if ($archived) { ?>
                        <a class="dropdown-item" href="post.php?restore_software_template=<?= $software_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                    <?php } else { ?>
                        <a class="dropdown-item" href="post.php?archive_software_template=<?= $software_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_software_template=<?= $software_template_id ?>&from_page=1&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Licenses made from it stay as they are." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-cube me-2"></i>What a license starts with</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-edit me-1"></i>Edit</button>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3 fw-normal text-secondary">Name</dt>
                    <dd class="col-sm-9"><?= $name ?></dd>
                    <?php foreach ($facts as $label => $value) { ?>
                        <dt class="col-sm-3 fw-normal text-secondary"><?= $label ?></dt>
                        <dd class="col-sm-9"><?= $value ?></dd>
                    <?php } ?>
                    <dt class="col-sm-3 fw-normal text-secondary">Notes</dt>
                    <dd class="col-sm-9 mb-0">
                        <?php if (trim((string) $template['software_template_notes']) !== '') { ?>
                            <div class="itflow-template-notes" style="white-space: pre-wrap;"><?= escapeHtml($template['software_template_notes']) ?></div>
                        <?php } else { ?>
                            <span class="text-secondary">None - what every license from it should say: where to download it, how it is installed, who to call.</span>
                        <?php } ?>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3" id="softwareTemplateLicenses">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-list me-2"></i>Clients with it</h3>
                <?php if ($all_licenses) { ?>
                    <div class="dropdown ms-auto" id="bulkActionButton" hidden>
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-layer-group me-1"></i>Bulk Action (<span id="selectedCount"></span>)
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $renew_url ?>" data-modal-size="lg" data-bulk="true"><i class="fas fa-fw fa-redo-alt me-2"></i>Renew</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $true_up_url ?>" data-modal-size="lg" data-bulk="true"><i class="fas fa-fw fa-balance-scale me-2"></i>True up seats</a>
                            <?php if ($has_product) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $bill_url ?>" data-modal-size="lg" data-bulk="true"><i class="fas fa-fw fa-file-invoice-dollar me-2"></i>Bill seats</a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <?php if ($all_licenses) { ?>
                <div class="card-header py-2">
                    <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto">
                        <?php foreach ($views as $key => $definition) {
                            if ($key !== '' && $key !== $view && !$view_counts[$key]) {
                                continue;
                            }
                            $alarm = in_array($key, ['over', 'expired', 'unbilled'], true) && $view_counts[$key]; ?>
                            <a class="nav-link text-nowrap <?= $key === $view ? 'active' : '' ?>" href="<?= escapeHtml($view_url($key)) ?>" title="<?= escapeHtml($definition['title']) ?>" data-license-view="<?= $key ?>">
                                <i class="fas fa-fw <?= $definition['icon'] ?> me-1"></i><?= escapeHtml($definition['label']) ?>
                                <span class="badge rounded-pill ms-1 <?= $alarm && $key !== $view ? 'text-bg-danger' : '' ?>"><?= $view_counts[$key] ?></span>
                            </a>
                        <?php } ?>
                    </nav>
                </div>
                <?php if ($view_action && $licenses && !$archived) { ?>
                    <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2 bg-body-tertiary">
                        <span class="small text-secondary"><?= escapeHtml($views[$view]['title']) ?>.</span>
                        <button type="button" class="btn btn-sm btn-primary ms-auto ajax-modal" data-modal-size="lg" data-view-action
                            data-modal-url="<?= escapeHtml($view_action['url'] . (strpos($view_action['url'], '?') === false ? '?' : '&') . $view_ids()) ?>"><i class="fas fa-fw <?= $view_action['icon'] ?> me-1"></i><?= $view_action['label'] ?></button>
                    </div>
                <?php } ?>
            <?php } ?>
            <?php if ($licenses) { ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <td class="ps-3 pe-0" style="width: 1rem;"><input class="form-check-input" type="checkbox" id="selectAllCheckbox" aria-label="Tick them all"></td>
                                <th>Client</th><th>Seats</th><th class="d-none d-sm-table-cell">Renews</th>
                                <?php if ($has_product) { ?><th class="d-none d-md-table-cell">Billed</th><?php } ?>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($licenses as $software_id => $row) {
                            $client_id = intval($row['software_client_id']);
                            $billing = $row['billing'];
                            ?>
                            <tr data-software-id="<?= $software_id ?>">
                                <td class="ps-3 pe-0"><input class="form-check-input bulk-select" type="checkbox" name="software_ids[]" value="<?= $software_id ?>" aria-label="Tick <?= escapeHtml($row['client_name']) ?>"></td>
                                <td>
                                    <a class="fw-semibold text-body" href="/agent/software.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                                    <?php if ($row['software_name'] !== $template['software_template_name']) { ?><div class="small text-secondary"><?= escapeHtml($row['software_name']) ?></div><?php } ?>
                                    <?php if ($has_product) { ?><div class="d-md-none small text-secondary"><?= $billing ? 'Billed on ' . escapeHtml($billing['number']) : 'Not billed' ?></div><?php } ?>
                                </td>
                                <td style="min-width: 7rem;"><?= softwareSeatBar($row['seats'], $row['seats']['state'] === 'over') ?></td>
                                <td class="d-none d-sm-table-cell"><?= $row['renewal'] ? softwareRenewalPill($row['renewal']) : '<span class="text-secondary small">-</span>' ?></td>
                                <?php if ($has_product) { ?>
                                    <td class="d-none d-md-table-cell small" data-billing="<?= $billing ? $billing['how'] : 'none' ?>">
                                        <?php if ($billing) { ?>
                                            <a class="text-reset" href="/agent/recurring_invoice.php?recurring_invoice_id=<?= intval($billing['recurring_invoice_id']) ?>"><i class="fas fa-fw fa-redo-alt text-secondary me-1"></i><?= escapeHtml($billing['number']) ?></a>
                                            <?php if ($billing['paused']) { ?><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal ms-1">Paused</span><?php } ?>
                                            <div class="text-secondary"><?= $billing['how'] === 'seats' ? 'Counts the seats ' . ($billing['count'] === 'total' ? 'bought' : 'in use') : billingQuantity($billing['quantity']) . ' typed in' ?></div>
                                            <?php if ($billing['drift']) { ?><div class="text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>Not its seats</div><?php } ?>
                                        <?php } else { ?>
                                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal">Not billed</span>
                                        <?php } ?>
                                    </td>
                                <?php } ?>
                                <td class="text-end pe-3">
                                    <div class="dropdown dropstart">
                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $renew_url ?>?id=<?= $software_id ?>"><i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Renew</a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $true_up_url ?>&software_ids[]=<?= $software_id ?>"><i class="fas fa-fw fa-balance-scale text-secondary me-2"></i>True up seats</a>
                                            <?php if ($has_product && !$billing) { ?>
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $bill_url ?>&software_ids[]=<?= $software_id ?>"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Bill seats</a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="/agent/software.php?client_id=<?= $client_id ?>"><i class="fas fa-fw fa-cube text-secondary me-2"></i>The client's Licenses</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } elseif ($all_licenses) { ?>
                <div class="card-body small text-secondary">None of them - <a href="<?= escapeHtml($view_url('')) ?>">see them all</a>.</div>
            <?php } else { ?>
                <div class="card-body small text-secondary">No licenses made from it yet. Agents start one from <strong>New License</strong> on a client's Licenses<?= $archived ? '' : ', or make one from here' ?>.</div>
            <?php } ?>
        </div>

    </div>

    <div class="col-lg-4 d-print-none">

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-chart-line me-2"></i>Used</h3></div>
            <div class="card-body">
                <?php if ($usage['count']) { ?>
                    <div class="mb-2"><strong><?= $usage['count'] ?></strong> license<?= $usage['count'] === 1 ? '' : 's' ?> made from it<?= $usage['clients'] > 1 ? ', for <strong>' . $usage['clients'] . '</strong> clients' : '' ?> <span class="small text-secondary">- last <?= timeAgo($usage['last']) ?></span></div>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-1"><i class="fas fa-fw fa-users text-secondary me-1"></i><?= $usage['seats'] ? '<strong>' . $usage['used'] . '</strong> of <strong>' . $usage['seats'] . '</strong> seats in use' : '<strong>' . $usage['used'] . '</strong> seats in use' ?></li>
                        <?php if ($usage['renewing']) { ?><li class="mb-1 text-warning-emphasis"><i class="fas fa-fw fa-hourglass-half me-1"></i><?= $usage['renewing'] ?> renewing in the next 30 days</li><?php } ?>
                        <?php if ($usage['expired']) { ?><li class="mb-1 text-danger-emphasis"><i class="fas fa-fw fa-calendar-times me-1"></i><?= $usage['expired'] ?> past their renewal date</li><?php } ?>
                        <?php if ($has_product) { ?>
                            <li class="mb-1 <?= $view_counts['unbilled'] ? 'text-warning-emphasis' : 'text-success' ?>"><i class="fas fa-fw fa-file-invoice-dollar me-1"></i><?= $view_counts['unbilled'] ? $view_counts['unbilled'] . ' not billed' : 'Every one billed' ?></li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <div class="small text-secondary">No licenses made from it yet - counting started with this version of ITFlow, with the licenses already called what it is called.</div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3" id="softwareTemplateCoverage">
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
                                    <a class="min-w-0 flex-grow-1 text-truncate text-reset" href="/agent/software.php?client_id=<?= $client_id ?>"><?= escapeHtml($client_name) ?></a>
                                    <button type="button" class="btn btn-sm btn-light py-0 ajax-modal" data-modal-size="lg" data-modal-url="<?= $use_url ?>&client_id=<?= $client_id ?>"><i class="fas fa-plus me-1"></i>Make</button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if (count($coverage['missing']) > $list_limit) { ?>
                            <div class="small text-secondary mb-2">and <?= count($coverage['missing']) - $list_limit ?> more</div>
                        <?php } ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $several_url ?>&missing=1"><i class="fas fa-copy me-1"></i>Make for <?= $missing_text ?></button>
                    <?php } elseif ($coverage['clients']) { ?>
                        <div class="small text-success"><i class="fas fa-fw fa-check me-1"></i>Every one of them has it.</div>
                    <?php } else { ?>
                        <div class="small text-secondary"><?= $required === SOFTWARE_TEMPLATE_REQUIRED_TAGGED ? ($required_tags ? 'No clients have ' . (count($required_tags) === 1 ? 'that tag' : 'those tags') : 'No tags picked, so no clients') : 'No clients yet' ?>.</div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-info-circle me-2"></i>Details</h3></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-normal text-secondary">Made</dt>
                    <dd class="col-7 mb-2" title="<?= escapeHtml($template['software_template_created_at']) ?>"><?= date('M j, Y', strtotime($template['software_template_created_at'])) ?><?= $template['created_by_name'] !== null ? '<div class="text-secondary">' . escapeHtml($template['created_by_name']) . '</div>' : '' ?></dd>
                    <?php if ($template['software_template_updated_at']) { ?>
                        <dt class="col-5 fw-normal text-secondary">Last changed</dt>
                        <dd class="col-7 mb-0" title="<?= escapeHtml($template['software_template_updated_at']) ?>"><?= timeAgo($template['software_template_updated_at']) ?><?= $template['updated_by_name'] !== null ? '<div class="text-secondary">' . escapeHtml($template['updated_by_name']) . '</div>' : '' ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </div>

    </div>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
