<?php

/*
 * Admin > Vendor Templates - the vendors an MSP's clients use again and
 * again, set up once: the ISPs, the hardware makers, the distributors, the
 * line-of-business software. Each row shows what it is, its support line,
 * hours and SLA, how many clients have a vendor made from it, when one was
 * last added and when it last changed, and by whom. Each opens its page
 * (vendor_template.php). A template can be added to several clients at once,
 * changed, copied as the start of another, archived - not offered for new
 * vendors, while the vendors made from it stay - or deleted. The work is in
 * functions/vendor_template.php; the handlers are
 * admin/post/vendor_template.php.
 */

// Default Column Sortby Filter
$sort = "vendor_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// '' current, 'archived'
$show_archived = ($_GET['show'] ?? '') === 'archived' || ($_GET['archived'] ?? '') === '1';
$show_where = $show_archived ? 'vendor_template_archived_at IS NOT NULL' : 'vendor_template_archived_at IS NULL';

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
    OR vendor_template_hours LIKE '%$q%' OR vendor_template_sla LIKE '%$q%' OR vendor_template_contact_name LIKE '%$q%' OR vendor_template_account_number LIKE '%$q%'
    OR vendor_template_notes LIKE '%$q%'" . ($phone_search !== '' ? " OR vendor_template_phone LIKE '%$phone_search%'" : '') . ")";

// Its use is the current vendors made from it for current clients - as vendorTemplateUsage() counts them
$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS vendor_templates.vendor_template_id, vendor_template_name, vendor_template_description, vendor_template_phone_country_code,
        vendor_template_phone, vendor_template_extension, vendor_template_email, vendor_template_website, vendor_template_hours, vendor_template_sla,
        vendor_template_created_at, vendor_template_updated_at, vendor_template_archived_at,
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

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(vendor_template_archived_at IS NULL), 0), COALESCE(SUM(vendor_template_archived_at IS NOT NULL), 0)
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
                    <a class="dropdown-item" href="starter_content.php">
                        <i class="fas fa-fw fa-seedling text-secondary me-2"></i>Starter vendor templates
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search vendor templates..." title="Name, what they are, support line, email, site, hours, SLA, account manager or notes">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= !$show_archived ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('')) ?>"><i class="fas fa-fw fa-building me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
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
                                <?php if ($website !== '' || trim((string) $row['vendor_template_email']) !== '') { ?>
                                    <div class="small text-body-secondary d-flex flex-wrap column-gap-3">
                                        <?php if ($website !== '') { ?><a class="text-reset text-nowrap" href="<?= escapeHtml(vendorWebsiteUrl($row['vendor_template_website'])) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-fw fa-globe me-1"></i><?= escapeHtml($website) ?></a><?php } ?>
                                        <?php if (trim((string) $row['vendor_template_email']) !== '') { ?><span class="text-nowrap"><i class="fas fa-fw fa-envelope me-1"></i><?= escapeHtml($row['vendor_template_email']) ?></span><?php } ?>
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
                                <a class="dropdown-item" href="post.php?copy_vendor_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A new template starting from this one">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
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
        'text' => 'A vendor your clients use again and again - an ISP, a hardware maker, the software they run - set up once with its support line, hours and site, ready to add to any client. Agents start one from <strong>New Vendor</strong> on a client\'s Vendors.',
        'actions' => [['label' => 'New Vendor Template', 'modal' => $add_url, 'size' => 'lg'], ['label' => 'Starter vendor templates', 'href' => 'starter_content.php', 'icon' => 'fa-seedling']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
