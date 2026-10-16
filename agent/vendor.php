<?php

/*
 * Vendor - one of a client's vendors, or one of ours.
 *
 * Built for the call to them: the support line, hours and SLA, the account
 * number and PIN they will ask for (the PIN as dots until it is wanted), the
 * account manager, and the notes - escalation path, who may call - always in
 * view. Then, for a client's vendor, what is open with them and what came
 * from them in tabs with counts: cases (tickets with their reference, open
 * first), licenses, assets bought from them, domains they host, services,
 * portal logins, documents, files and history. For one of ours: what we spend
 * with them, the recurring expenses and the catalog items bought from them. "Also used by" lists the agent's
 * other clients with the same vendor - who else an outage hits.
 *
 * The open tab follows the URL hash (#cases) and is remembered between visits.
 */

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
} else {
    require_once "includes/inc_all.php";
}

$vendor_id = intval($_GET['vendor_id'] ?? 0);
$vendor = vendorRow($vendor_id);
$vendor_client_id = $vendor ? intval($vendor['vendor_client_id']) : 0;

if (!$vendor || (isset($_GET['client_id']) && $vendor_client_id !== intval($client_id)) || !vendorCan($vendor_client_id, 1)) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $client_id = $vendor_client_id;
    if ($client_id) {
        enforceUserPermission('module_client');
        enforceClientAccess();
    }
    $is_ours = !$client_id;

    $vendor_name = escapeHtml($vendor['vendor_name']);
    $vendor_description = escapeHtml($vendor['vendor_description']);
    $vendor_archived_at = escapeHtml($vendor['vendor_archived_at']);
    $vendor_created_at = escapeHtml($vendor['vendor_created_at']);
    $vendor_favorite = intval($vendor['vendor_favorite']);
    $vendor_phone = contactPhoneLink($vendor['vendor_phone'], $vendor['vendor_phone_country_code'], $vendor['vendor_extension']);
    $vendor_phone_text = $vendor['vendor_phone'] ? formatPhoneNumber($vendor['vendor_phone'], $vendor['vendor_phone_country_code']) : '';
    $vendor_dial = vendorDialNumber($vendor['vendor_phone'], $vendor['vendor_phone_country_code']);
    $vendor_email = escapeHtml($vendor['vendor_email']);
    $vendor_website_url = vendorWebsiteUrl($vendor['vendor_website']);
    $vendor_website_label = escapeHtml(vendorWebsiteLabel($vendor['vendor_website']));
    $vendor_hours = escapeHtml($vendor['vendor_hours']);
    $vendor_sla = escapeHtml($vendor['vendor_sla']);
    $vendor_account = escapeHtml($vendor['vendor_account_number']);
    $vendor_pin = vendorSecretHtml($vendor['vendor_code']);
    $vendor_notes = escapeHtml($vendor['vendor_notes']);
    $manager_name = escapeHtml($vendor['vendor_contact_name']);
    $manager_phone = contactPhoneLink($vendor['vendor_contact_phone'], $vendor['vendor_contact_phone_country_code']);
    $manager_email = escapeHtml($vendor['vendor_contact_email']);

    $can_edit = vendorCan($client_id, 2);
    $can_delete = vendorCan($client_id, 3) && !empty($config_destructive_deletes_enable);
    $can_support = lookupUserPermission('module_support') >= 1;
    $can_support_edit = lookupUserPermission('module_support') >= 2;
    $can_credentials = lookupUserPermission('module_credential') >= 1;
    $can_credentials_edit = lookupUserPermission('module_credential') >= 2;
    $can_financial = lookupUserPermission('module_financial') >= 1;
    $can_financial_edit = lookupUserPermission('module_financial') >= 2;
    // A client's vendor can be linked to that client's services
    $can_link_services = !$is_ours && $can_support_edit && !$vendor_archived_at;

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $page_title = $vendor['vendor_name'];

    $list_url = $is_ours ? 'vendors.php' : "vendors.php?client_id=$client_id";
    $vendor_tags_display = tagPills('vendor', $vendor_id, tagsForRecord('vendor', $vendor_id), ($is_ours ? 'vendors.php?' : "vendors.php?client_id=$client_id&"), $can_edit);

    // Cases - every open one, then the most recent closed ones
    $cases = [];
    $case_count = $case_open_count = 0;
    $licenses = $assets = $domains = $services = $logins = $documents = $files = [];
    if ($can_support) {
        [$case_count, $case_open_count] = ticketCounts("tickets.ticket_client_id = $client_id AND tickets.ticket_vendor_id = $vendor_id");
        $cases = ticketRows("tickets.ticket_client_id = $client_id AND tickets.ticket_vendor_id = $vendor_id", max(25, $case_open_count));

        if (!$is_ours) {
            $sql = mysqli_query($mysqli, "SELECT software_id AS id, software_name AS name, software_type, software_version, software_license_type,
                    software_seats, software_expire, software_purchase_reference, " . softwareSeatsUsedSql('software.software_id') . " AS seats_used
                FROM software
                WHERE software_client_id = $client_id AND software_vendor_id = $vendor_id AND software_archived_at IS NULL
                ORDER BY software_expire IS NULL, software_expire ASC, software_name ASC");
            while ($row = mysqli_fetch_assoc($sql)) {
                $licenses[] = $row;
            }
            $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_serial, asset_purchase_date, asset_purchase_reference, asset_warranty_expire
                FROM assets
                WHERE asset_client_id = $client_id AND asset_vendor_id = $vendor_id AND asset_archived_at IS NULL
                ORDER BY asset_warranty_expire IS NULL, asset_warranty_expire ASC, asset_name ASC");
            while ($row = mysqli_fetch_assoc($sql)) {
                $assets[] = $row;
            }
            $services = servicesFor('vendor', $vendor_id);
            $documents = linkedRecords('vendor', $vendor_id, 'document');
            $files = linkedRecords('vendor', $vendor_id, 'file');
        }

        // Domains they register or host - the client's, or for one of ours (DNS or
        // mail we run for clients), every client's that the agent can see
        $sql = mysqli_query($mysqli, "SELECT domain_id, domain_name, domain_description, domain_expire, domain_auto_renew, domain_registrar, domain_webhost, domain_dnshost, domain_mailhost,
                domain_client_id, client_name
            FROM domains
            LEFT JOIN clients ON client_id = domain_client_id
            WHERE domain_archived_at IS NULL AND $vendor_id IN (domain_registrar, domain_webhost, domain_dnshost, domain_mailhost)
            " . ($is_ours ? "AND client_archived_at IS NULL " . clientScopeSql('domain_client_id') : "AND domain_client_id = $client_id") . "
            ORDER BY client_name ASC, domain_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $domains[] = $row;
        }
    }
    if ($can_credentials && !$is_ours) {
        // The whole credential, so the password and 2FA code are right there to use
        $logins = credentialRows("credential_id IN (SELECT credential_id FROM vendor_credentials WHERE vendor_id = $vendor_id)");
    }

    // What we spend with one of ours
    $expenses = $recurring = [];
    $spend = null;
    if ($is_ours && $can_financial) {
        $spend = vendorSpend($vendor_id);
        $sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, expense_amount, expense_currency_code, expense_description, expense_reference, category_name, account_name
            FROM expenses
            LEFT JOIN categories ON category_id = expense_category_id
            LEFT JOIN accounts ON account_id = expense_account_id
            WHERE expense_vendor_id = $vendor_id AND expense_archived_at IS NULL
            ORDER BY expense_date DESC, expense_id DESC
            LIMIT 25");
        while ($row = mysqli_fetch_assoc($sql)) {
            $expenses[] = $row;
        }
        $sql = mysqli_query($mysqli, "SELECT recurring_expense_id, recurring_expense_frequency, recurring_expense_next_date, recurring_expense_status, recurring_expense_amount,
                recurring_expense_currency_code, recurring_expense_description, category_name
            FROM recurring_expenses
            LEFT JOIN categories ON category_id = recurring_expense_category_id
            WHERE recurring_expense_vendor_id = $vendor_id AND recurring_expense_archived_at IS NULL
            ORDER BY recurring_expense_status DESC, recurring_expense_next_date ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $recurring[] = $row;
        }
    }

    // What we buy from one of ours - the catalog items they supply
    $vendor_products = [];
    $can_sales = lookupUserPermission('module_sales') >= 1;
    if ($is_ours && $can_sales) {
        $sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_type, product_code, product_vendor_part, product_price, product_cost, product_currency_code,
                product_track_stock, product_frequency, product_seat_source, " . productStockSql() . " AS product_stock
            FROM products
            WHERE product_vendor_id = $vendor_id AND product_archived_at IS NULL
            ORDER BY product_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $vendor_products[] = $row;
        }
    }

    $elsewhere = lookupUserPermission('module_client') >= 1 ? vendorElsewhere($vendor) : [];
    $history = vendorHistory($vendor_id, 100);

    if ($is_ours) {
        $vendor_tabs = [
            'expenses' => ['label' => 'Expenses', 'icon' => 'fa-receipt', 'count' => $spend ? $spend['count'] : 0],
            'recurring' => ['label' => 'Recurring', 'icon' => 'fa-redo-alt', 'count' => count($recurring)],
            'products' => ['label' => 'Products', 'icon' => 'fa-box-open', 'count' => count($vendor_products)],
            'cases' => ['label' => 'Cases', 'icon' => 'fa-life-ring', 'count' => $case_count, 'badge' => $case_open_count],
            'domains' => ['label' => 'Client domains', 'icon' => 'fa-globe', 'count' => count($domains)],
            'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => count($history)],
        ];
        if (!$domains) {
            unset($vendor_tabs['domains']);
        }
        if (!$can_financial) {
            unset($vendor_tabs['expenses'], $vendor_tabs['recurring']);
        }
        if (!$can_sales) {
            unset($vendor_tabs['products']);
        }
        if (!$case_count) {
            unset($vendor_tabs['cases']);
        }
    } else {
        $vendor_tabs = [
            'cases' => ['label' => 'Cases', 'icon' => 'fa-life-ring', 'count' => $case_count, 'badge' => $case_open_count],
            'licenses' => ['label' => 'Licenses', 'icon' => 'fa-cube', 'count' => count($licenses)],
            'assets' => ['label' => 'Assets', 'icon' => 'fa-desktop', 'count' => count($assets)],
            'domains' => ['label' => 'Domains', 'icon' => 'fa-globe', 'count' => count($domains)],
            'services' => ['label' => 'Services', 'icon' => 'fa-stream', 'count' => count($services)],
            'logins' => ['label' => 'Logins', 'icon' => 'fa-key', 'count' => count($logins)],
            'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'count' => count($documents)],
            'files' => ['label' => 'Files', 'icon' => 'fa-paperclip', 'count' => count($files)],
            'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => count($history)],
        ];
        if (!$can_support) {
            unset($vendor_tabs['cases'], $vendor_tabs['licenses'], $vendor_tabs['assets'], $vendor_tabs['domains'], $vendor_tabs['services'], $vendor_tabs['documents'], $vendor_tabs['files']);
        }
        if (!$can_credentials) {
            unset($vendor_tabs['logins']);
        }
    }

    $link_url = "modals/link/link.php?from=vendor&id=$vendor_id&to=";
    $unlink_url = function ($to, $other_id) use ($vendor_id) {
        return "post.php?unlink_item&from=vendor&id=$vendor_id&to=$to&other=" . intval($other_id) . "&csrf_token=" . $_SESSION['csrf_token'];
    };
    $new_case_url = "modals/ticket/ticket_add.php?client_id=$client_id&vendor_id=$vendor_id";
    $can_new = !$vendor_archived_at && (($is_ours && $can_financial_edit) || (!$is_ours && ($can_support_edit || $can_credentials_edit)));

    ?>

    <ol class="breadcrumb d-print-none">
        <?php if ($is_ours) { ?>
            <li class="breadcrumb-item"><a href="vendors.php">Vendors</a></li>
        <?php } else { ?>
            <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($vendor['client_name']) ?></a></li>
            <li class="breadcrumb-item"><a href="vendors.php?client_id=<?= $client_id ?>">Vendors</a></li>
        <?php } ?>
        <li class="breadcrumb-item active"><?= $vendor_name ?></li>
    </ol>

    <!-- Who they are and how to reach them -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?= vendorTile($vendor['vendor_name'], 'lg') ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= $vendor_name ?></span>
                    <?php if ($can_edit && !$vendor_archived_at) { ?>
                        <a class="text-decoration-none fs-6" href="post.php?<?= $vendor_favorite ? 'unfavorite_vendor' : 'favorite_vendor' ?>=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="<?= $vendor_favorite ? 'Unstar' : 'Star - starred vendors come first' ?>">
                            <i class="<?= $vendor_favorite ? 'fas text-warning' : 'far text-body-secondary' ?> fa-star"></i>
                        </a>
                    <?php } elseif ($vendor_favorite) { ?>
                        <i class="fas fa-star text-warning fs-6" title="Starred"></i>
                    <?php } ?>
                    <?php if ($is_ours) { ?><span class="badge rounded-pill text-bg-dark fw-normal fs-6">Our vendor</span><?php } ?>
                    <?php if ($vendor_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <?php if ($vendor_description) { ?><span><?= $vendor_description ?></span><?php } ?>
                    <?php if ($vendor_hours) { ?><span><i class="fas fa-fw fa-clock me-1"></i><?= $vendor_hours ?></span><?php } ?>
                    <?php if ($vendor['vendor_template_name']) { ?><span title="Made from this template"><i class="fas fa-fw fa-puzzle-piece me-1"></i><?= escapeHtml($vendor['vendor_template_name']) ?></span><?php } ?>
                </div>
                <?php if ($vendor_tags_display) { ?>
                    <div class="mt-2"><?= $vendor_tags_display ?></div>
                <?php } ?>
            </div>

            <div class="d-flex flex-wrap gap-2 d-print-none">
                <?php if ($vendor_dial) { ?>
                    <a class="btn btn-outline-primary" href="tel:<?= escapeHtml($vendor_dial) ?><?= $vendor['vendor_extension'] ? ',' . escapeHtml($vendor['vendor_extension']) : '' ?>" title="<?= escapeHtml($vendor_phone_text) ?>"><i class="fas fa-phone me-2"></i>Call support</a>
                <?php } ?>
                <?php if ($vendor_email) { ?>
                    <a class="btn btn-outline-primary" href="mailto:<?= $vendor_email ?>"><i class="fas fa-envelope me-2"></i>Email</a>
                <?php } ?>
                <?php if ($vendor_website_url) { ?>
                    <a class="btn btn-outline-primary" href="<?= escapeHtml($vendor_website_url) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-2"></i>Support site</a>
                <?php } ?>
                <?php if ($can_new) { ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-plus me-2"></i>New</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php if ($is_ours) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/expense/expense_add.php?vendor_id=<?= $vendor_id ?>">
                                    <i class="fa fa-fw fa-receipt text-secondary me-2"></i>Expense
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense_add.php?vendor_id=<?= $vendor_id ?>">
                                    <i class="fa fa-fw fa-redo-alt text-secondary me-2"></i>Recurring expense
                                </a>
                            <?php } else { ?>
                                <?php if ($can_support_edit) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $new_case_url ?>">
                                        <i class="fa fa-fw fa-life-ring text-secondary me-2"></i>Case with them
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software/software_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                        <i class="fa fa-fw fa-cube text-secondary me-2"></i>License from them
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                        <i class="fa fa-fw fa-desktop text-secondary me-2"></i>Asset bought from them
                                    </a>
                                <?php } ?>
                                <?php if ($can_credentials_edit) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/credential/credential_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                        <i class="fa fa-fw fa-key text-secondary me-2"></i>Portal login
                                    </a>
                                <?php } ?>
                                <?php if ($can_support_edit) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                        <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/file/file_upload.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                        <i class="fa fa-fw fa-upload text-secondary me-2"></i>Upload File(s)
                                    </a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/vendor/vendor_edit.php?id=<?= $vendor_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($vendor_archived_at) { ?>
                                <a class="dropdown-item" href="post.php?restore_vendor=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                </a>
                                <?php if ($can_delete) { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_vendor=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= $vendor_name ?> goes for good. Its cases, licenses, assets and domains stay, with no vendor.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_vendor=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- Calling them, what they will ask for, who looks after us -->
        <div class="col-xl-4 col-lg-5">

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-headset text-secondary me-2"></i>Support</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Phone</dt>
                        <dd>
                            <?php if ($vendor_phone) { ?>
                                <?= $vendor_phone ?><button type="button" class="itflow-copy clipboardjs" title="Copy phone number" data-clipboard-text="<?= escapeHtml($vendor_phone_text) ?>"><i class="far fa-copy"></i></button>
                            <?php } else { ?>
                                <span class="text-body-secondary">Not recorded</span>
                            <?php } ?>
                        </dd>
                        <dt>Hours</dt>
                        <dd><?= $vendor_hours ?: '<span class="text-body-secondary">Not recorded</span>' ?></dd>
                        <?php if ($vendor_sla) { ?>
                            <dt>SLA</dt><dd><?= $vendor_sla ?></dd>
                        <?php } ?>
                        <?php if ($vendor_email) { ?>
                            <dt>Email</dt>
                            <dd class="text-break"><a href="mailto:<?= $vendor_email ?>"><?= $vendor_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $vendor_email ?>"><i class="far fa-copy"></i></button></dd>
                        <?php } ?>
                        <?php if ($vendor_website_url) { ?>
                            <dt>Site</dt>
                            <dd class="text-break"><a href="<?= escapeHtml($vendor_website_url) ?>" target="_blank" rel="noopener"><?= $vendor_website_label ?><i class="fas fa-external-link-alt ms-1 small"></i></a></dd>
                        <?php } ?>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-id-card text-secondary me-2"></i>Account</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Account</dt>
                        <dd><?= $vendor_account ? '<span class="font-monospace">' . $vendor_account . '</span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="' . $vendor_account . '"><i class="far fa-copy"></i></button>' : '<span class="text-body-secondary">Not recorded</span>' ?></dd>
                        <dt>PIN</dt>
                        <dd><?= $vendor_pin ?: '<span class="text-body-secondary">Not recorded</span>' ?></dd>
                        <dt>Manager</dt>
                        <dd>
                            <?php if ($manager_name || $manager_phone || $manager_email) { ?>
                                <?php if ($manager_name) { ?><div class="fw-semibold"><?= $manager_name ?></div><?php } ?>
                                <?php if ($manager_phone) { ?><div class="d-flex align-items-baseline"><i class="fas fa-fw fa-phone text-body-secondary me-1 flex-shrink-0"></i><span><?= $manager_phone ?></span></div><?php } ?>
                                <?php if ($manager_email) { ?><div class="d-flex align-items-baseline min-w-0"><i class="fas fa-fw fa-envelope text-body-secondary me-1 flex-shrink-0"></i><a class="text-truncate" href="mailto:<?= $manager_email ?>" title="<?= $manager_email ?>"><?= $manager_email ?></a><button type="button" class="itflow-copy clipboardjs flex-shrink-0" title="Copy email" data-clipboard-text="<?= $manager_email ?>"><i class="far fa-copy"></i></button></div><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">Not recorded</span>
                            <?php } ?>
                        </dd>
                        <dt>Added</dt><dd title="<?= $vendor_created_at ?>"><?= timeAgo($vendor_created_at) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-sticky-note text-secondary me-2"></i>Notes</h3>
                </div>
                <textarea class="form-control border-0 rounded-0 rounded-bottom itflow-vendor-notes" rows="7" placeholder="Escalation path, who is authorised to call them, circuit IDs, contract renewal - saves when you click away"
                    data-vendor-id="<?= $vendor_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token']) ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $vendor_notes ?></textarea>
            </div>

            <?php if ($elsewhere) { ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-users text-secondary me-2"></i>Also used by <span class="badge rounded-pill text-bg-secondary ms-1"><?= count($elsewhere) ?></span></h3>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($elsewhere as $other) {
                            $other_client_id = intval($other['vendor_client_id']); ?>
                            <li class="list-group-item d-flex align-items-center gap-2 py-2">
                                <a class="text-truncate flex-grow-1" href="vendor.php?client_id=<?= $other_client_id ?>&vendor_id=<?= intval($other['vendor_id']) ?>"><?= escapeHtml($other['client_name']) ?></a>
                                <?php if ($other['vendor_account_number']) { ?><span class="small text-body-secondary font-monospace text-nowrap" title="Their account number"><?= escapeHtml($other['vendor_account_number']) ?></span><?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                    <div class="card-footer small text-body-secondary">Clients with a vendor of the same name or template - who else an outage hits.</div>
                </div>
            <?php } ?>

        </div>

        <!-- What is open with them and what came from them -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-3 itflow-asset-related">
                <div class="card-header py-2">
                    <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                        <?php foreach ($vendor_tabs as $tab_key => $tab) { ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="vendor-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#vendor-pane-<?= $tab_key ?>" data-vendor-tab="<?= $tab_key ?>" type="button" role="tab">
                                    <i class="fas fa-fw <?= $tab['icon'] ?> me-1 d-none d-xxl-inline"></i><?= $tab['label'] ?>
                                    <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                                    <?php if (!empty($tab['badge'])) { ?>
                                        <span class="badge rounded-pill text-bg-danger" title="Open"><?= intval($tab['badge']) ?> open</span>
                                    <?php } ?>
                                </button>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <div class="tab-content">

                    <?php if (isset($vendor_tabs['cases'])) { ?>
                    <!-- Cases - tickets logged with this vendor, open first -->
                    <div class="tab-pane fade" id="vendor-pane-cases" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Tickets waiting on or worked with them, open first - with their reference.</span>
                            <?php if ($can_support_edit && !$vendor_archived_at) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $new_case_url ?>"><i class="fas fa-plus me-2"></i>New Case</button>
                            <?php } ?>
                        </div>
                        <?php if ($cases) { ?>
                            <?= ticketTableHtml($cases, ['vendor_ref' => true]) ?>
                            <?php if ($case_count > count($cases)) { ?>
                                <div class="small text-body-secondary px-3 py-2 border-top">Showing every open case and the <?= count($cases) - $case_open_count ?> most recent closed of <?= $case_count ?>.</div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No cases with them. Set the vendor on a ticket, with their reference, when you log a call with them.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <?php if (isset($vendor_tabs['licenses'])) { ?>
                    <!-- Licenses bought through them - soonest renewal first -->
                    <div class="tab-pane fade" id="vendor-pane-licenses" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Licenses and subscriptions bought through them, soonest renewal first.</span>
                            <?php if ($can_support_edit && !$vendor_archived_at) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/software/software_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>"><i class="fas fa-plus me-2"></i>New License</button>
                            <?php } ?>
                        </div>
                        <?php if ($licenses) { ?>
                            <ul class="list-unstyled mb-0 itflow-links itflow-license-links">
                                <?php foreach ($licenses as $license) {
                                    $renewal = softwareRenewal($license['software_expire']); ?>
                                    <li class="itflow-link-row">
                                        <?= linkRowHtml('software', $license, $client_id) ?>
                                        <?php if ($renewal) { ?><span class="text-nowrap"><?= softwareRenewalPill($renewal) ?></span><?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No licenses from them.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <?php if (isset($vendor_tabs['assets'])) { ?>
                    <!-- Assets bought from them - for warranty claims -->
                    <div class="tab-pane fade" id="vendor-pane-assets" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Bought from them, warranty ending soonest first - what to have to hand for a claim.</span>
                            <?php if ($can_support_edit && !$vendor_archived_at) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset/asset_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>"><i class="fas fa-plus me-2"></i>New Asset</button>
                            <?php } ?>
                        </div>
                        <?php if ($assets) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light"><tr><th>Asset</th><th>Serial</th><th>Order ref</th><th>Purchased</th><th>Warranty</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($assets as $asset) {
                                        $asset_id = intval($asset['asset_id']);
                                        $serial = escapeHtml($asset['asset_serial']);
                                        $order_ref = escapeHtml($asset['asset_purchase_reference']);
                                        $warranty = softwareRenewal($asset['asset_warranty_expire'], 60); ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($asset['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($asset['asset_type']) ?>"></i></span>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= $asset_id ?>"><?= escapeHtml($asset['asset_name']) ?></a>
                                                        <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$asset['asset_type'], trim($asset['asset_make'] . ' ' . $asset['asset_model'])]))) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-nowrap"><?php if ($serial) { ?><span class="font-monospace small"><?= $serial ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy serial" data-clipboard-text="<?= $serial ?>"><i class="far fa-copy"></i></button><?php } else { ?><span class="text-body-secondary">-</span><?php } ?></td>
                                            <td class="text-nowrap small"><?= $order_ref ?: '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="text-nowrap small"><?= escapeHtml($asset['asset_purchase_date']) ?: '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="text-nowrap">
                                                <?php if ($warranty) {
                                                    $tone = $warranty['tone']; ?>
                                                    <span class="badge rounded-pill bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis border border-<?= $tone ?>-subtle fw-normal" title="<?= escapeHtml($warranty['date']) ?>">
                                                        <?= $warranty['state'] === 'expired' ? 'Expired' : ($warranty['state'] === 'soon' ? 'Ends in ' . intval($warranty['days']) . ' days' : 'To ' . escapeHtml($warranty['date'])) ?>
                                                    </span>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No assets bought from them.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <?php if (isset($vendor_tabs['domains'])) { ?>
                    <!-- Domains they register or host -->
                    <div class="tab-pane fade" id="vendor-pane-domains" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small"><?= $is_ours ? "Clients' domains we register or host with them, and for what." : 'Domains they register or host, and for what.' ?></span>
                            <?php if (!$is_ours && $can_support_edit && !$vendor_archived_at) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/domain/domain_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                    <i class="fas fa-plus me-2"></i>New Domain
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($domains) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light"><tr><th>Domain</th><th>They are</th><th>Expires</th><?= $is_ours ? '<th>Client</th>' : '' ?></tr></thead>
                                    <tbody>
                                    <?php foreach ($domains as $domain) {
                                        $roles = [];
                                        foreach (VENDOR_DOMAIN_ROLES as $column => $role) {
                                            if (intval($domain[$column]) === $vendor_id) {
                                                $roles[] = $role;
                                            }
                                        }
                                        $expiry = domainExpiry($domain['domain_expire'], $domain['domain_auto_renew']); ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= domainTile('sm') ?>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/domain/domain.php?id=<?= intval($domain['domain_id']) ?>"><?= escapeHtml($domain['domain_name']) ?></a>
                                                        <?php if ($domain['domain_description']) { ?><div class="small text-body-secondary"><?= escapeHtml($domain['domain_description']) ?></div><?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php foreach ($roles as $role) { ?><span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal me-1"><?= $role ?></span><?php } ?></td>
                                            <td class="text-nowrap"><?= domainExpiryPill($expiry, true) ?></td>
                                            <?php if ($is_ours) { ?>
                                                <td><a href="domains.php?client_id=<?= intval($domain['domain_client_id']) ?>&vendor=<?= $vendor_id ?>"><?= escapeHtml($domain['client_name']) ?></a></td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No domains with them.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <?php if (isset($vendor_tabs['services'])) { ?>
                    <!-- Services they are part of -->
                    <div class="tab-pane fade" id="vendor-pane-services" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">The client's services that depend on them - who gets the call when one breaks.</span>
                            <?php if ($can_link_services) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/link/link.php?from=vendor&id=<?= $vendor_id ?>&to=service">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($services) { ?>
                            <?= serviceRowsHtml($services, $can_link_services ? function ($row) use ($vendor_id) {
                                return "post.php?unlink_item&from=vendor&id=$vendor_id&to=service&other=" . intval($row['service_id']) . "&csrf_token=" . $_SESSION['csrf_token'];
                            } : null) ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No services depend on them.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Portal logins, documents and files -->
                    <?php foreach (['logins' => ['credential', $logins, 'Their portal and support logins - linked from the client\'s credentials.', 'No logins linked.', $can_credentials_edit],
                                    'documents' => ['document', $documents, 'The contract, the escalation sheet, how to raise a case with them.', 'No documents linked.', $can_support_edit],
                                    'files' => ['file', $files, 'Quotes, invoices, the signed agreement.', 'No files linked.', $can_support_edit]] as $pane => [$to, $linked, $hint, $empty, $can_link]) {
                        if (!isset($vendor_tabs[$pane])) {
                            continue;
                        } ?>
                        <div class="tab-pane fade" id="vendor-pane-<?= $pane ?>" role="tabpanel">
                            <div class="itflow-asset-pane-bar">
                                <span class="text-body-secondary small"><?= $hint ?></span>
                                <?php if ($can_link && !$vendor_archived_at) { ?>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $link_url . $to ?>">
                                            <i class="fas fa-link me-2"></i>Link
                                        </button>
                                        <?php if ($to === 'credential') { ?>
                                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/credential/credential_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                                <i class="fas fa-plus me-2"></i>New Login
                                            </button>
                                        <?php } elseif ($to === 'document') { ?>
                                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                                <i class="fas fa-plus me-2"></i>New Document
                                            </button>
                                        <?php } else { ?>
                                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/file/file_upload.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>">
                                                <i class="fas fa-upload me-2"></i>Upload
                                            </button>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($linked && $to === 'credential') { ?>
                                <?= credentialTableHtml($linked, [
                                    'for' => true,
                                    'unlink' => function ($row) use ($unlink_url, $can_link) {
                                        return $can_link ? $unlink_url('credential', $row['credential_id']) : '';
                                    },
                                ]) ?>
                            <?php } elseif ($linked) { ?>
                                <ul class="list-unstyled mb-0 itflow-links itflow-license-links">
                                    <?php foreach ($linked as $linked_row) { ?>
                                        <li class="itflow-link-row">
                                            <?= linkRowHtml($to, $linked_row, $client_id) ?>
                                            <?php if ($can_link) { ?>
                                                <a class="itflow-link-remove" href="<?= $unlink_url($to, $linked_row['id']) ?>" title="Unlink <?= $to ?>"><i class="fas fa-unlink"></i></a>
                                            <?php } ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <div class="itflow-asset-empty"><?= $empty ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <?php if (isset($vendor_tabs['expenses'])) { ?>
                    <!-- What we spend with them -->
                    <div class="tab-pane fade" id="vendor-pane-expenses" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small d-flex flex-wrap column-gap-3">
                                <span><strong class="text-body"><?= numfmt_format_currency($currency_format, $spend['year'], $session_company_currency) ?></strong> this year</span>
                                <span><?= numfmt_format_currency($currency_format, $spend['last_year'], $session_company_currency) ?> last year</span>
                                <span><?= numfmt_format_currency($currency_format, $spend['total'], $session_company_currency) ?> in all</span>
                            </span>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-secondary" href="expenses.php?vendor=<?= $vendor_id ?>"><i class="fas fa-list me-2"></i>All</a>
                                <?php if ($can_financial_edit && !$vendor_archived_at) { ?>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/expense/expense_add.php?vendor_id=<?= $vendor_id ?>"><i class="fas fa-plus me-2"></i>New Expense</button>
                                <?php } ?>
                            </div>
                        </div>
                        <?php if ($expenses) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light"><tr><th>Date</th><th>What for</th><th>Account</th><th class="text-end">Amount</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($expenses as $expense) { ?>
                                        <tr>
                                            <td class="text-nowrap"><a class="ajax-modal" href="#" data-modal-url="modals/expense/expense_edit.php?id=<?= intval($expense['expense_id']) ?>"><?= escapeHtml($expense['expense_date']) ?></a></td>
                                            <td class="w-100">
                                                <div><?= escapeHtml(truncate((string) $expense['expense_description'], 90)) ?: '<span class="text-body-secondary">-</span>' ?></div>
                                                <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$expense['category_name'], $expense['expense_reference']]))) ?></div>
                                            </td>
                                            <td class="text-nowrap small"><?= escapeHtml($expense['account_name']) ?></td>
                                            <td class="text-end text-nowrap font-monospace"><?= numfmt_format_currency($currency_format, floatval($expense['expense_amount']), $expense['expense_currency_code']) ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($spend['count'] > count($expenses)) { ?>
                                <div class="small text-body-secondary px-3 py-2 border-top">The <?= count($expenses) ?> most recent of <?= $spend['count'] ?> - <a href="expenses.php?vendor=<?= $vendor_id ?>">see them all</a>.</div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Nothing spent with them yet.</div>
                        <?php } ?>
                    </div>

                    <!-- What is booked to them every month or year -->
                    <div class="tab-pane fade" id="vendor-pane-recurring" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Subscriptions and contracts booked automatically.</span>
                            <?php if ($can_financial_edit && !$vendor_archived_at) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense_add.php?vendor_id=<?= $vendor_id ?>"><i class="fas fa-plus me-2"></i>New Recurring</button>
                            <?php } ?>
                        </div>
                        <?php if ($recurring) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light"><tr><th>Next</th><th>Every</th><th>What for</th><th class="text-end">Amount</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($recurring as $recurring_expense) {
                                        $active = intval($recurring_expense['recurring_expense_status']) === 1; ?>
                                        <tr class="<?= $active ? '' : 'text-body-secondary' ?>">
                                            <td class="text-nowrap"><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense_edit.php?id=<?= intval($recurring_expense['recurring_expense_id']) ?>"><?= escapeHtml($recurring_expense['recurring_expense_next_date']) ?></a><?php if (!$active) { ?> <span class="badge rounded-pill text-bg-secondary fw-normal">Paused</span><?php } ?></td>
                                            <td class="text-nowrap"><?= intval($recurring_expense['recurring_expense_frequency']) === 1 ? 'Month' : 'Year' ?></td>
                                            <td class="w-100">
                                                <div><?= escapeHtml(truncate((string) $recurring_expense['recurring_expense_description'], 90)) ?: '<span class="text-body-secondary">-</span>' ?></div>
                                                <div class="small text-body-secondary"><?= escapeHtml($recurring_expense['category_name']) ?></div>
                                            </td>
                                            <td class="text-end text-nowrap font-monospace"><?= numfmt_format_currency($currency_format, floatval($recurring_expense['recurring_expense_amount']), $recurring_expense['recurring_expense_currency_code']) ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Nothing recurring with them.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <?php if (isset($vendor_tabs['products'])) { ?>
                    <!-- What we buy from them - catalog items -->
                    <div class="tab-pane fade" id="vendor-pane-products" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Products and services in the catalog bought from them, with their part number.</span>
                            <a class="btn btn-sm btn-outline-secondary" href="products.php?vendor=<?= $vendor_id ?>"><i class="fas fa-box-open me-2"></i>In Products</a>
                        </div>
                        <?php if ($vendor_products) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light"><tr><th>Item</th><th class="d-none d-md-table-cell">Part #</th><th class="text-end">Cost</th><th class="text-end">Price</th><th class="text-end d-none d-sm-table-cell">Stock</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($vendor_products as $vendor_product) { ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= productTile($vendor_product['product_type'], 'sm') ?>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold" href="product.php?product_id=<?= intval($vendor_product['product_id']) ?>"><?= escapeHtml($vendor_product['product_name']) ?></a>
                                                        <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$vendor_product['product_code'], $vendor_product['product_frequency'] ? productBilledText($vendor_product['product_frequency'], $vendor_product['product_seat_source']) : '']))) ?: '&nbsp;' ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell font-monospace small"><?= $vendor_product['product_vendor_part'] ? escapeHtml($vendor_product['product_vendor_part']) : '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="text-end itflow-money"><?= floatval($vendor_product['product_cost']) > 0 ? escapeHtml(billingMoney($vendor_product['product_cost'], $vendor_product['product_currency_code'])) : '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="text-end itflow-money">
                                                <?= escapeHtml(billingMoney($vendor_product['product_price'], $vendor_product['product_currency_code'])) ?>
                                                <div><?= productMarginPill(productMargin($vendor_product['product_price'], $vendor_product['product_cost'])) ?></div>
                                            </td>
                                            <td class="text-end d-none d-sm-table-cell"><?= intval($vendor_product['product_track_stock']) ? intval($vendor_product['product_stock']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Nothing in the catalog comes from them. Pick them as the vendor on a product to list it here.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- History, from the audit log -->
                    <div class="tab-pane fade" id="vendor-pane-history" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Edits, links and notes - newest first.</span>
                        </div>
                        <?php if ($history) {
                            $history_icons = ['Create' => 'fa-plus', 'Edit' => 'fa-edit', 'Archive' => 'fa-archive', 'Restore' => 'fa-redo', 'Link' => 'fa-link', 'Unlink' => 'fa-unlink']; ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($history as $entry) { ?>
                                    <li class="list-group-item d-flex gap-3 py-2">
                                        <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-other" title="<?= escapeHtml($entry['log_action']) ?>"><i class="fas fa-fw <?= $history_icons[$entry['log_action']] ?? 'fa-circle' ?>"></i></span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div><?= escapeHtml($entry['log_description']) ?></div>
                                            <div class="small text-body-secondary"><?= escapeHtml($entry['user_name'] ?: 'System') ?> &middot; <span title="<?= escapeHtml($entry['log_created_at']) ?>"><?= timeAgo($entry['log_created_at']) ?></span></div>
                                        </div>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Nothing logged yet.</div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <script>
        // Notes save when you click away
        document.querySelectorAll('.itflow-vendor-notes').forEach(function (notes) {
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('ajax.php', {
                    vendor_set_notes: 'TRUE',
                    csrf_token: notes.dataset.csrf,
                    vendor_id: notes.dataset.vendorId,
                    notes: notes.value
                });
            });
        });

        /*
         * Which tab is open: the URL hash wins (#licenses), then the tab last
         * used on any vendor, then the first. Choosing a tab updates the hash
         * without a history entry, so the redirect back from a save lands on
         * the same tab. Bootstrap loads in the footer, so this waits for it.
         */
        document.addEventListener('DOMContentLoaded', function () {
            var key = 'itflow.vendorTab';
            var tabs = document.querySelectorAll('[data-vendor-tab]');
            if (!tabs.length) {
                return;
            }
            function byName(name) {
                return name ? document.querySelector('[data-vendor-tab="' + name + '"]') : null;
            }
            var remembered = '';
            try { remembered = localStorage.getItem(key) || ''; } catch (e) {}
            var start = byName(location.hash.replace('#', '')) || byName(remembered) || tabs[0];
            bootstrap.Tab.getOrCreateInstance(start).show();

            tabs.forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function () {
                    var name = tab.getAttribute('data-vendor-tab');
                    try { localStorage.setItem(key, name); } catch (e) {}
                    history.replaceState(null, '', location.pathname + location.search + '#' + name);
                });
            });
        });
    </script>

<?php
}

require_once "../includes/footer.php";
