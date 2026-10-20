<?php

/*
 * Admin > Vendor Templates - the vendors an MSP's clients use again and
 * again, set up once: the ISPs, the hardware makers, the distributors, the
 * line-of-business software. Each row shows what it is, its support line,
 * hours and SLA, how many clients have a vendor made from it, when one was
 * last added and when it last changed, and by whom - with how many of the
 * clients it is required of have it, and how many of its vendors are out of
 * step with it (the Required and Out of step tabs). Each opens its page
 * (vendor_template.php). A template can be added to several clients at once,
 * changed, copied as the start of another, archived - not offered for new
 * vendors, while the vendors made from it stay - or deleted, and they go to
 * and from a file (Export and Import). The work is in
 * functions/vendor_template.php; the handlers are
 * admin/post/vendor_template.php.
 */

// Default Column Sortby Filter
$sort = "vendor_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// The templates with vendors out of step with them, and how many (vendorTemplateOutOfStep())
$made_from = [];
$sql = mysqli_query($mysqli, "SELECT DISTINCT vendor_template_id FROM vendors WHERE vendor_template_id > 0");
while ($row = mysqli_fetch_row($sql)) {
    $made_from[] = intval($row[0]);
}
$out_of_step = vendorTemplateOutOfStep($made_from);
$out_ids = implode(',', array_keys($out_of_step) ?: [0]);

// '' current, 'required' the current ones someone should have, 'out' the current ones with vendors out of step, 'archived'
$show = (string) ($_GET['show'] ?? '');
if (($_GET['archived'] ?? '') === '1') {
    $show = 'archived';
}
$show = in_array($show, ['required', 'out', 'archived'], true) ? $show : '';
$show_archived = $show === 'archived';
$show_where = [
    '' => 'vendor_template_archived_at IS NULL',
    'required' => 'vendor_template_archived_at IS NULL AND vendor_template_required > 0',
    'out' => "vendor_template_archived_at IS NULL AND vendor_templates.vendor_template_id IN ($out_ids)",
    'archived' => 'vendor_template_archived_at IS NOT NULL',
][$show];

// Tab title - the file is named for the table, the page for what it holds
$page_title = 'Vendor Templates';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'vendor_template_name' => "vendor_template_name $order",
    'used_count' => "used_count $order, vendor_template_name ASC",
    'last_used' => "last_used IS NULL, last_used $order, vendor_template_name ASC",
    'changed_at' => "changed_at $order, vendor_template_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'vendor_template_name';
}

$phone_search = preg_replace('/[^0-9]/', '', (string) ($_GET['q'] ?? ''));
$search = "(vendor_template_name LIKE '%$q%' OR vendor_template_description LIKE '%$q%' OR vendor_template_website LIKE '%$q%' OR vendor_template_email LIKE '%$q%'
    OR vendor_template_status_page LIKE '%$q%' OR vendor_template_portal LIKE '%$q%' OR vendor_template_contact_email LIKE '%$q%'
    OR vendor_template_hours LIKE '%$q%' OR vendor_template_sla LIKE '%$q%' OR vendor_template_contact_name LIKE '%$q%' OR vendor_template_account_number LIKE '%$q%'
    OR vendor_template_notes LIKE '%$q%'" . ($phone_search !== '' ? " OR vendor_template_phone LIKE '%$phone_search%'" : '') . ")";

// Its use is the current vendors made from it for current clients - as vendorTemplateUsage() counts them
$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS vendor_templates.vendor_template_id, vendor_template_name, vendor_template_description, vendor_template_phone_country_code,
        vendor_template_phone, vendor_template_extension, vendor_template_email, vendor_template_website, vendor_template_status_page, vendor_template_hours, vendor_template_sla,
        vendor_template_created_at, vendor_template_updated_at, vendor_template_archived_at, vendor_template_required,
        COALESCE(vendor_template_updated_at, vendor_template_created_at) AS changed_at, COALESCE(updater.user_name, creator.user_name) AS changed_by,
        COALESCE(used.used_count, 0) AS used_count, used.last_used
    FROM vendor_templates
    LEFT JOIN users creator ON creator.user_id = vendor_template_created_by AND vendor_template_created_by > 0
    LEFT JOIN users updater ON updater.user_id = vendor_template_updated_by AND vendor_template_updated_by > 0
    LEFT JOIN (
        SELECT vendor_template_id AS used_template_id, COUNT(DISTINCT vendor_client_id) AS used_count, MAX(vendor_created_at) AS last_used
        FROM vendors JOIN clients ON client_id = vendor_client_id AND client_archived_at IS NULL
        WHERE vendor_template_id > 0 AND vendor_archived_at IS NULL GROUP BY vendor_template_id
    ) used ON used.used_template_id = vendor_templates.vendor_template_id
    WHERE $search AND $show_where
    ORDER BY {$sortable_columns[$sort]}, vendor_templates.vendor_template_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$rows = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[intval($row['vendor_template_id'])] = $row;
}
$usage = vendorTemplateUsage(array_keys($rows));

// How many of the clients a required one is for have it
$required_tags = vendorTemplateRequiredTags(array_keys($rows));
$coverage = [];
foreach ($rows as $template_id => $row) {
    if (intval($row['vendor_template_required']) && !$row['vendor_template_archived_at']) {
        $coverage[$template_id] = vendorTemplateCoverage($template_id, $row['vendor_template_required'], array_keys($required_tags[$template_id]));
    }
}

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(vendor_template_archived_at IS NULL), 0), COALESCE(SUM(vendor_template_archived_at IS NOT NULL), 0),
    COALESCE(SUM(vendor_template_archived_at IS NULL AND vendor_template_required > 0), 0), COALESCE(SUM(vendor_template_archived_at IS NULL AND vendor_template_id IN ($out_ids)), 0)
    FROM vendor_templates WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($show) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $show]));
};

$add_url = 'modals/vendor_template/vendor_template_add.php';
$clients_url = '/agent/modals/vendor/vendor_template_clients.php';
$import_url = 'modals/vendor_template/vendor_template_import.php';
$export_url = 'modals/vendor_template/vendor_template_export.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-building me-2"></i>Vendor Templates</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Vendor Template</span></button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $clients_url ?>">
                        <i class="fas fa-fw fa-users text-secondary me-2"></i>Add one to clients
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $import_url ?>">
                        <i class="fas fa-fw fa-file-upload text-secondary me-2"></i>Import
                    </a>
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $export_url ?>">
                        <i class="fas fa-fw fa-file-download text-secondary me-2"></i>Export
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="starter_content.php">
                        <i class="fas fa-fw fa-seedling text-secondary me-2"></i>Starter vendor templates
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= $show ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search vendor templates..." title="Name, what they are, support line, email, site, status page, portal, hours, SLA, account manager or notes">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-2">
            <a class="nav-link <?= $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('')) ?>"><i class="fas fa-fw fa-building me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show === 'required' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('required')) ?>" title="Every client, or the clients with a tag, should have it"><i class="fas fa-fw fa-clipboard-check me-1"></i>Required <span class="badge rounded-pill ms-1"><?= intval($counts[2]) ?></span></a>
            <a class="nav-link <?= $show === 'out' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('out')) ?>" title="Clients' vendors made from it that say something other than it does"><i class="fas fa-fw fa-not-equal me-1"></i>Out of step <span class="badge rounded-pill ms-1 <?= $show !== 'out' && intval($counts[3]) ? 'text-bg-warning' : '' ?>"><?= intval($counts[3]) ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('archived')) ?>" title="Not offered for new vendors - the vendors made from them stay"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('vendor_template_name', 'Template') ?></th>
                <th class="d-none d-lg-table-cell">Support</th>
                <th class="d-none d-md-table-cell"><?= $sort_link('used_count', 'Used') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('last_used', 'Last used') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('changed_at', 'Changed') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $template_id => $row) {
                $name = escapeHtml($row['vendor_template_name']);
                $use = $usage[$template_id] ?? null;
                $used_clients = $use['clients'] ?? 0;
                $used_text = $used_clients ? $used_clients . ' client' . ($used_clients === 1 ? '' : 's') : '';
                if ($use && $use['ours']) {
                    $used_text = trim($used_text . ($used_text !== '' ? ' and ours' : 'Ours'));
                }
                $summary = trim((string) $row['vendor_template_description']);
                $page_url = "vendor_template.php?vendor_template_id=$template_id";
                $phone = vendorTemplatePhoneHtml($row['vendor_template_phone'], $row['vendor_template_phone_country_code'], $row['vendor_template_extension']);
                $website = vendorWebsiteLabel($row['vendor_template_website']);
                $status_url = vendorWebsiteUrl($row['vendor_template_status_page']);
                // How to reach their support
                $support = array_filter([
                    $phone !== '' ? '<i class="fas fa-fw fa-phone text-secondary me-1"></i>' . $phone : '',
                    trim((string) $row['vendor_template_hours']) !== '' ? '<i class="fas fa-fw fa-clock text-secondary me-1"></i>' . escapeHtml($row['vendor_template_hours']) : '',
                    trim((string) $row['vendor_template_sla']) !== '' ? '<i class="fas fa-fw fa-handshake text-secondary me-1"></i>' . escapeHtml($row['vendor_template_sla']) : '',
                ]);
                ?>
                <tr data-vendor-template-id="<?= $template_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-sm-inline-flex"><?= vendorTemplateTile($row['vendor_template_name']) ?></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="<?= $page_url ?>"><?= $name ?></a>
                                <?php if ($summary !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($summary) ?></div>
                                <?php } ?>
                                <?php if ($website !== '' || trim((string) $row['vendor_template_email']) !== '' || $status_url !== '') { ?>
                                    <div class="small text-body-secondary d-flex flex-wrap column-gap-3">
                                        <?php if ($website !== '') { ?><a class="text-reset text-nowrap" href="<?= escapeHtml(vendorWebsiteUrl($row['vendor_template_website'])) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-fw fa-globe me-1"></i><?= escapeHtml($website) ?></a><?php } ?>
                                        <?php if (trim((string) $row['vendor_template_email']) !== '') { ?><span class="text-nowrap"><i class="fas fa-fw fa-envelope me-1"></i><?= escapeHtml($row['vendor_template_email']) ?></span><?php } ?>
                                        <?php if ($status_url !== '') { ?><a class="text-reset text-nowrap" href="<?= escapeHtml($status_url) ?>" target="_blank" rel="noopener noreferrer" title="<?= escapeHtml(vendorWebsiteLabel($row['vendor_template_status_page'])) ?>" data-template-status-page><i class="fas fa-fw fa-heartbeat me-1"></i>Status</a><?php } ?>
                                    </div>
                                <?php } ?>
                                <?php if (isset($coverage[$template_id]) || (!$row['vendor_template_archived_at'] && isset($out_of_step[$template_id]))) { ?>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php if (isset($coverage[$template_id])) {
                                            $reach = $coverage[$template_id]; ?>
                                            <a class="badge rounded-pill fw-normal text-decoration-none <?= $reach['missing'] ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success-emphasis border border-success-subtle' ?>"
                                                href="<?= $page_url ?>#vendorTemplateCoverage" title="<?= escapeHtml(vendorTemplateScopeText($row['vendor_template_required'], $required_tags[$template_id])) ?>" data-template-reach>
                                                <i class="fas fa-clipboard-check me-1"></i><?= $reach['covered'] ?> of <?= $reach['clients'] ?> clients
                                            </a>
                                        <?php } ?>
                                        <?php if (!$row['vendor_template_archived_at'] && isset($out_of_step[$template_id])) { ?>
                                            <a class="badge rounded-pill fw-normal text-decoration-none bg-warning-subtle text-warning-emphasis border border-warning-subtle"
                                                href="<?= $page_url ?>&amp;view=out#vendorTemplateVendors" title="Vendors made from it that say something other than it does" data-template-out-of-step="<?= $out_of_step[$template_id] ?>">
                                                <i class="fas fa-not-equal me-1"></i><?= $out_of_step[$template_id] ?> out of step
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <?php if ($row['vendor_template_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['vendor_template_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens its support line and use come along under the name -->
                                <?php if ($support) { ?><div class="d-lg-none small text-body-secondary mt-1"><?= reset($support) ?></div><?php } ?>
                                <div class="d-md-none small text-body-secondary"><?= $used_text !== '' ? escapeHtml($used_text) . ' · last ' . timeAgo($use['last']) : 'Not used yet' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell small">
                        <?php if ($support) { ?>
                            <?php foreach ($support as $line) { ?><div class="text-nowrap"><?= $line ?></div><?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap" data-used="<?= $used_clients ?>">
                        <?php if ($used_text !== '') { ?>
                            <a class="text-reset" href="<?= $page_url ?>#vendorTemplateVendors"><?= escapeHtml($used_text) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not yet</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-body-secondary text-nowrap"<?= $row['last_used'] ? ' title="' . escapeHtml($row['last_used']) . '"' : '' ?>><?= $row['last_used'] ? timeAgo($row['last_used']) : '-' ?></td>
                    <td class="d-none d-lg-table-cell small text-body-secondary text-nowrap" title="<?= escapeHtml($row['changed_at']) ?>">
                        <?= timeAgo($row['changed_at']) ?>
                        <?php if ($row['changed_by'] !== null) { ?><div><?= escapeHtml($row['changed_by']) ?></div><?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/vendor_template/vendor_template_edit.php?id=<?= $template_id ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if (!$row['vendor_template_archived_at']) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $clients_url ?>?template_id=<?= $template_id ?>">
                                        <i class="fas fa-fw fa-users text-secondary me-2"></i>Add to clients
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/vendor_template/vendor_template_required.php?id=<?= $template_id ?>">
                                    <i class="fas fa-fw fa-clipboard-check text-secondary me-2"></i>Who should have it
                                </a>
                                <a class="dropdown-item" href="post.php?copy_vendor_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A new template starting from this one">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                                <a class="dropdown-item" href="post.php?export_vendor_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A file to import into another ITFlow - without account numbers or PINs">
                                    <i class="fas fa-fw fa-file-download text-secondary me-2"></i>Export
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['vendor_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_vendor_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_vendor_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="Not offered for new vendors - the vendors made from it stay">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_vendor_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="The vendors made from it stay as they are." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'vendor templates',
        'icon' => 'fa-building',
        'filtered' => $q !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'view' => ['required' => ['title' => 'No required templates', 'text' => 'Set one up from its page - <strong>Who should have it</strong> - to see which clients are missing a vendor from it.'],
            'out' => ['title' => 'Every vendor is in step', 'text' => 'Every client\'s vendor made from a template says what the template says.']][$show] ?? null,
        'text' => 'A vendor your clients use again and again - an ISP, a hardware maker, the software they run - set up once with its support line, hours and site, ready to add to any client. Agents start one from <strong>New Vendor</strong> on a client\'s Vendors.',
        'actions' => [['label' => 'New Vendor Template', 'modal' => $add_url, 'size' => 'lg'], ['label' => 'Import', 'modal' => $import_url, 'icon' => 'fa-file-upload'],
            ['label' => 'Starter vendor templates', 'href' => 'starter_content.php', 'icon' => 'fa-seedling']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
