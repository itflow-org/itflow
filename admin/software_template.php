<?php

/*
 * One license template - what a license made from it starts with (its name,
 * version, summary, type, license type, seats, how often it renews, its vendor,
 * the product it is sold as and its notes), who made it and last changed it,
 * and its use: how many licenses have been made from it, for how many
 * clients, the seats on them, the ones renewing soon or lapsed, and the latest
 * of them. New licenses can be made from here for a client or several at
 * once. The work is in functions/software_template.php.
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

// The licenses made from it, the latest first
$licenses = [];
$sql = mysqli_query($mysqli, "SELECT software_id, software_name, software_seats, software_expire, software_created_at, software_client_id, client_name, client_abbreviation,
        " . softwareSeatsUsedSql('software.software_id') . " AS seats_used
    FROM software JOIN clients ON client_id = software_client_id AND client_archived_at IS NULL
    WHERE software_template_id = $software_template_id AND software_archived_at IS NULL
    ORDER BY software_created_at DESC, software_id DESC LIMIT 25");
while ($row = mysqli_fetch_assoc($sql)) {
    $licenses[] = $row;
}

$edit_url = "modals/software_template/software_template_edit.php?id=$software_template_id";
$use_url = "modals/software_template/software_template_use.php?id=$software_template_id";
$several_url = "modals/software_template/software_template_use_several.php?id=$software_template_id";

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
                    </div>
                </div>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
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

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-list me-2"></i>Licenses made from it</h3>
                <?php if ($usage['count'] > count($licenses)) { ?><span class="ms-auto small text-secondary">The latest <?= count($licenses) ?> of <?= $usage['count'] ?></span><?php } ?>
            </div>
            <?php if ($licenses) { ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr><th class="ps-3">Client</th><th>Seats</th><th class="d-none d-sm-table-cell">Renews</th><th class="d-none d-md-table-cell">Made</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($licenses as $row) {
                            $renewal = softwareRenewal($row['software_expire']);
                            ?>
                            <tr data-software-id="<?= intval($row['software_id']) ?>">
                                <td class="ps-3">
                                    <a class="fw-semibold text-body" href="/agent/software.php?client_id=<?= intval($row['software_client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                                    <?php if ($row['software_name'] !== $template['software_template_name']) { ?><div class="small text-secondary"><?= escapeHtml($row['software_name']) ?></div><?php } ?>
                                </td>
                                <td style="min-width: 7rem;"><?= softwareSeatBar(softwareSeats($row['seats_used'], $row['software_seats']), false) ?></td>
                                <td class="d-none d-sm-table-cell"><?= $renewal ? softwareRenewalPill($renewal) : '<span class="text-secondary small">-</span>' ?></td>
                                <td class="d-none d-md-table-cell small text-secondary text-nowrap" title="<?= escapeHtml($row['software_created_at']) ?>"><?= timeAgo($row['software_created_at']) ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
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
                    </ul>
                <?php } else { ?>
                    <div class="small text-secondary">No licenses made from it yet - counting started with this version of ITFlow, with the licenses already called what it is called.</div>
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

<?php
require_once "../includes/footer.php";
