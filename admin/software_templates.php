<?php

/*
 * Admin > License Templates - the licenses an MSP sells or looks after again
 * and again, ready to start a client's from: Microsoft 365 Business Premium,
 * an endpoint protection agent, a backup product. Each row shows what it is,
 * its type and license type, what a license from it starts with (seats, how
 * often it renews, its vendor, the product it is sold as), how many licenses
 * have been made from it and for how many clients, with their seats, when it
 * was last used and last changed. A template every client, or the clients
 * with a tag, should have shows how many of them have it; the Required tab
 * lists those. New licenses can be made from one for a client or several at
 * once. They can be exported to a file and imported from one, and the MSP
 * starter pack added from Maintenance > Starter Content.
 * A template can be changed, copied as the start of another, archived - not
 * offered for new licenses, while the licenses made from it stay - or
 * deleted. The work is in functions/software_template.php; the handlers are
 * admin/post/software_template.php.
 */

// Default Column Sortby Filter
$sort = "software_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// '' current, 'required' the current ones someone should have, 'archived'
$show = (string) ($_GET['show'] ?? '');
$show = in_array($show, ['required', 'archived'], true) ? $show : '';
$show_archived = $show === 'archived';
$show_where = [
    '' => 'software_template_archived_at IS NULL',
    'required' => 'software_template_archived_at IS NULL AND software_template_required > 0',
    'archived' => 'software_template_archived_at IS NOT NULL',
][$show];

// Tab title - the file is named for the table, the page for what it holds
$page_title = 'License Templates';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'software_template_name' => "software_template_name $order",
    'software_template_type' => "software_template_type $order, software_template_name ASC",
    'software_template_license_type' => "software_template_license_type = '', software_template_license_type $order, software_template_name ASC",
    'used_count' => "used_count $order, software_template_name ASC",
    'last_used' => "last_used IS NULL, last_used $order, software_template_name ASC",
    'changed_at' => "changed_at $order, software_template_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'software_template_name';
}

$search = "(software_template_name LIKE '%$q%' OR software_template_version LIKE '%$q%' OR software_template_description LIKE '%$q%' OR software_template_type LIKE '%$q%'
    OR software_template_license_type LIKE '%$q%' OR software_template_notes LIKE '%$q%' OR vendor_template_name LIKE '%$q%' OR product_name LIKE '%$q%')";
// Its vendor template and product - searched, and shown under the name
$joins = "LEFT JOIN vendor_templates ON vendor_template_id = software_template_vendor_template_id AND software_template_vendor_template_id > 0
    LEFT JOIN products ON product_id = software_template_product_id AND software_template_product_id > 0";

// Its use is the current licenses made from it, for current clients - as softwareTemplateUsage() counts them
$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS software_templates.software_template_id, software_template_name, software_template_version, software_template_description,
        software_template_type, software_template_license_type, software_template_notes, software_template_created_at, software_template_updated_at, software_template_archived_at,
        software_template_seats, software_template_term, software_template_required, vendor_template_name, product_name, product_price, product_currency_code, product_frequency, product_seat_source,
        COALESCE(software_template_updated_at, software_template_created_at) AS changed_at, COALESCE(updater.user_name, creator.user_name) AS changed_by,
        COALESCE(used.used_count, 0) AS used_count, used.last_used
    FROM software_templates
    $joins
    LEFT JOIN users creator ON creator.user_id = software_template_created_by AND software_template_created_by > 0
    LEFT JOIN users updater ON updater.user_id = software_template_updated_by AND software_template_updated_by > 0
    LEFT JOIN (
        SELECT software_template_id AS used_template_id, COUNT(*) AS used_count, MAX(software_created_at) AS last_used
        FROM software JOIN clients ON client_id = software_client_id AND client_archived_at IS NULL
        WHERE software_template_id > 0 AND software_archived_at IS NULL GROUP BY software_template_id
    ) used ON used.used_template_id = software_templates.software_template_id
    WHERE $search AND $show_where
    ORDER BY {$sortable_columns[$sort]}, software_templates.software_template_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$rows = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[intval($row['software_template_id'])] = $row;
}
$usage = softwareTemplateUsage(array_keys($rows));

// How many of the clients a required one is for have it
$required_tags = softwareTemplateRequiredTags(array_keys($rows));
$coverage = [];
foreach ($rows as $template_id => $row) {
    if (intval($row['software_template_required']) && !$row['software_template_archived_at']) {
        $coverage[$template_id] = softwareTemplateCoverage($template_id, $row['software_template_required'], array_keys($required_tags[$template_id]));
    }
}

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(software_template_archived_at IS NULL), 0), COALESCE(SUM(software_template_archived_at IS NOT NULL), 0),
    COALESCE(SUM(software_template_archived_at IS NULL AND software_template_required > 0), 0) FROM software_templates $joins WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($show) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $show]));
};

$add_url = 'modals/software_template/software_template_add.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-cube me-2"></i>License Templates</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New License Template</span></button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/software_template/software_template_import.php">
                        <i class="fas fa-fw fa-file-upload text-secondary me-2"></i>Import
                    </a>
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/software_template/software_template_export.php">
                        <i class="fas fa-fw fa-file-download text-secondary me-2"></i>Export
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/agent/reports/license_templates.php">
                        <i class="fas fa-fw fa-chart-bar text-secondary me-2"></i>How they are used
                    </a>
                    <a class="dropdown-item" href="starter_content.php">
                        <i class="fas fa-fw fa-seedling text-secondary me-2"></i>Starter license templates
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
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search license templates..." title="Name, version, summary, type, license type, notes, vendor or product">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('')) ?>"><i class="fas fa-fw fa-cube me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show === 'required' ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('required')) ?>" title="Every client, or the clients with a tag, should have it"><i class="fas fa-fw fa-clipboard-check me-1"></i>Required <span class="badge rounded-pill ms-1"><?= intval($counts[2]) ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="<?= escapeHtml($pill_url('archived')) ?>" title="Not offered for new licenses - the licenses made from them stay"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('software_template_name', 'Template') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('software_template_type', 'Type') ?></th>
                <th class="d-none d-xl-table-cell"><?= $sort_link('software_template_license_type', 'License type') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('used_count', 'Used') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('last_used', 'Last used') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('changed_at', 'Changed') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $template_id => $row) {
                $name = escapeHtml($row['software_template_name']);
                $use = $usage[$template_id] ?? null;
                $used_count = $use['count'] ?? 0;
                $used_text = $used_count ? $used_count . ' license' . ($used_count === 1 ? '' : 's') . ($use['clients'] > 1 ? ' · ' . $use['clients'] . ' clients' : '') : '';
                $seats_text = $use && $use['seats'] ? $use['used'] . ' of ' . $use['seats'] . ' seats in use' : ($use && $use['used'] ? $use['used'] . ' seats in use' : '');
                $summary = trim((string) $row['software_template_description']);
                $edit_url = "modals/software_template/software_template_edit.php?id=$template_id";
                // What a license from it starts with
                $starts = array_filter([
                    $row['software_template_seats'] !== null ? '<i class="fas fa-fw fa-users me-1"></i>' . intval($row['software_template_seats']) . ' seat' . (intval($row['software_template_seats']) === 1 ? '' : 's') : '',
                    isset(SOFTWARE_RENEWAL_TERMS[(string) $row['software_template_term']]) ? '<i class="fas fa-fw fa-redo-alt me-1"></i>' . escapeHtml(SOFTWARE_RENEWAL_TERMS[$row['software_template_term']]) . ' term' : '',
                    (string) $row['vendor_template_name'] !== '' ? '<i class="fas fa-fw fa-building me-1"></i>' . escapeHtml($row['vendor_template_name']) : '',
                    (string) $row['product_name'] !== '' ? '<i class="fas fa-fw fa-box-open me-1"></i>' . escapeHtml($row['product_name']) . ' · ' . escapeHtml(softwareTemplatePriceText($row)) : '',
                ]);
                ?>
                <tr data-software-template-id="<?= $template_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw <?= softwareTypeStyle($row['software_template_type'])['icon'] ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="software_template.php?software_template_id=<?= $template_id ?>"><?= $name ?></a>
                                <?php if ($row['software_template_version'] !== null && $row['software_template_version'] !== '') { ?><span class="text-body-secondary ms-1"><?= escapeHtml($row['software_template_version']) ?></span><?php } ?>
                                <?php if ($summary !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($summary) ?></div>
                                <?php } ?>
                                <?php if ($starts) { ?>
                                    <div class="small text-body-secondary d-flex flex-wrap column-gap-3" data-template-starts><?php foreach ($starts as $start) { ?><span class="text-nowrap"><?= $start ?></span><?php } ?></div>
                                <?php } ?>
                                <?php if (isset($coverage[$template_id])) {
                                    $reach = $coverage[$template_id]; ?>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <a class="badge rounded-pill fw-normal text-decoration-none <?= $reach['missing'] ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success-emphasis border border-success-subtle' ?>"
                                            href="software_template.php?software_template_id=<?= $template_id ?>#softwareTemplateCoverage" title="<?= escapeHtml(softwareTemplateScopeText($row['software_template_required'], $required_tags[$template_id])) ?>" data-template-reach>
                                            <i class="fas fa-clipboard-check me-1"></i><?= $reach['covered'] ?> of <?= $reach['clients'] ?> clients
                                        </a>
                                    </div>
                                <?php } ?>
                                <?php if ($row['software_template_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['software_template_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens its type and use come along under the name -->
                                <div class="d-lg-none small text-body-secondary mt-1"><?= escapeHtml(implode(' · ', array_filter([$row['software_template_type'], $row['software_template_license_type']]))) ?></div>
                                <div class="d-md-none small text-body-secondary"><?= $used_count ? escapeHtml($used_text) . ' · last ' . timeAgo($use['last']) : 'Not used yet' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell small"><?= escapeHtml($row['software_template_type']) ?></td>
                    <td class="d-none d-xl-table-cell small text-nowrap"><?php if ($row['software_template_license_type']) { ?><i class="fas fa-fw <?= softwareLicenseTypeIcon($row['software_template_license_type']) ?> text-secondary me-1"></i><?= escapeHtml($row['software_template_license_type']) ?><?php } else { ?><span class="text-body-secondary">-</span><?php } ?></td>
                    <td class="d-none d-md-table-cell small text-nowrap">
                        <?php if ($used_count) { ?>
                            <?= escapeHtml($used_text) ?>
                            <?php if ($seats_text !== '') { ?><div class="text-body-secondary"><?= escapeHtml($seats_text) ?></div><?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not yet</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-body-secondary text-nowrap"<?= $row['last_used'] ? ' title="' . escapeHtml($row['last_used']) . '"' : '' ?>><?= $row['last_used'] ? timeAgo($row['last_used']) : '-' ?></td>
                    <td class="d-none d-lg-table-cell small text-body-secondary text-nowrap" title="<?= escapeHtml($row['changed_at']) ?>">
                        <?= timeAgo($row['changed_at']) ?>
                        <?php if ($row['changed_by'] !== null) { ?><div><?= escapeHtml($row['changed_by']) ?></div><?php } ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if (!$row['software_template_archived_at']) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software_template/software_template_use.php?id=<?= $template_id ?>">
                                        <i class="fas fa-fw fa-plus text-secondary me-2"></i>New license for a client
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software_template/software_template_use_several.php?id=<?= $template_id ?>">
                                        <i class="fas fa-fw fa-copy text-secondary me-2"></i>For several clients
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/software_template/software_template_required.php?id=<?= $template_id ?>">
                                    <i class="fas fa-fw fa-clipboard-check text-secondary me-2"></i>Who should have it
                                </a>
                                <a class="dropdown-item" href="post.php?copy_software_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A new template starting from this one">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                                <a class="dropdown-item" href="post.php?export_software_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A file to import into another ITFlow">
                                    <i class="fas fa-fw fa-file-download text-secondary me-2"></i>Export
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['software_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_software_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_software_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="Not offered for new licenses - the licenses made from it stay">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_software_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Licenses made from it stay as they are." data-confirm-button="Delete">
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
        'thing' => 'license templates',
        'icon' => 'fa-cube',
        'filtered' => $q !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'view' => $show === 'required' ? ['title' => 'No required templates', 'text' => 'Set one up from its page - <strong>Who should have it</strong> - to see which clients are missing a license from it.'] : null,
        'text' => 'A license you sell or look after again and again - Microsoft 365 Business Premium, an endpoint protection agent, a backup product - ready to start a client\'s from. Agents start one from <strong>New License</strong> on a client\'s Licenses.',
        'actions' => [['label' => 'New License Template', 'modal' => $add_url, 'size' => 'lg'], ['label' => 'Import', 'modal' => 'modals/software_template/software_template_import.php', 'icon' => 'fa-file-upload'],
            ['label' => 'Starter license templates', 'href' => 'starter_content.php', 'icon' => 'fa-seedling']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
