<?php

/*
 * Admin > Phone Systems - the PBXs that send ITFlow their calls (functions/phone.php), FusionPBX
 * the first kind. Each row says which it is, how many of its domains are linked to clients, how
 * many calls came in the last 30 days, and when it was last heard from. Its domains, and how to
 * set it up, are on its Domains page (phone_domains.php). Add and Edit are one window
 * (modals/phone/phone_system_edit.php); the rest are admin/post/phone.php.
 */

// Default Column Sortby Filter
$sort = "phone_system_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// The tables arrive with the 4.1.4 database update
if (!phoneTablesReady()) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-exclamation-triangle me-2"></i>Phone Systems arrive with a database update - run it in <a href="update.php">Maintenance &gt; Update</a> first.</div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$kinds = phoneSystemKinds();

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'phone_system_name' => "phone_system_name $order",
    'phone_system_kind' => "phone_system_kind $order, phone_system_name ASC",
    'domains_linked' => "domains_linked $order, phone_system_name ASC",
    'calls_month' => "calls_month $order, phone_system_name ASC",
    'phone_system_heard_at' => "phone_system_heard_at IS NULL, phone_system_heard_at $order, phone_system_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'phone_system_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS phone_systems.*,
    (SELECT COUNT(*) FROM phone_domains WHERE phone_domain_system_id = phone_system_id) AS domains_total,
    (SELECT COUNT(*) FROM phone_domains WHERE phone_domain_system_id = phone_system_id AND phone_domain_client_id > 0) AS domains_linked,
    (SELECT COUNT(*) FROM phone_calls WHERE phone_call_system_id = phone_system_id AND phone_call_started_at >= NOW() - INTERVAL 30 DAY) AS calls_month
    FROM phone_systems
    WHERE (phone_system_name LIKE '%$q%' OR phone_system_web_url LIKE '%$q%' OR phone_system_kind LIKE '%$q%')
    ORDER BY {$sortable_columns[$sort]}, phone_system_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$add_url = 'modals/phone/phone_system_add.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-phone-volume me-2"></i>Phone Systems</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Phone System</span></button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search phone systems..." title="Name, kind or address">
                        <button class="btn btn-dark" title="Search"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= sortLink('phone_system_name', 'Phone system') ?></th>
                <th class="d-none d-lg-table-cell"><?= sortLink('phone_system_kind', 'Kind') ?></th>
                <th class="d-none d-md-table-cell"><?= sortLink('domains_linked', 'Domains') ?></th>
                <th class="d-none d-md-table-cell"><?= sortLink('calls_month', 'Calls, 30 days') ?></th>
                <th class="d-none d-sm-table-cell"><?= sortLink('phone_system_heard_at', 'Last heard from') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $phone_system_id = intval($row['phone_system_id']);
                $name = phoneSystemName($row);
                $kind = phoneKind($row);
                $on = intval($row['phone_system_active']) === 1;
                $domains_url = "phone_domains.php?phone_system_id=$phone_system_id";
                $edit_url = "modals/phone/phone_system_edit.php?id=$phone_system_id";
                $setup_url = "modals/phone/phone_system_setup.php?id=$phone_system_id";
                $linked = intval($row['domains_linked']);
                $total = intval($row['domains_total']);
                $suggested = count(phoneDomainSuggestions($phone_system_id));
                $calls = intval($row['calls_month']);
                $host = (string) parse_url((string) $row['phone_system_web_url'], PHP_URL_HOST);
                $web = phoneWebUrl($row);
                ?>
                <tr data-phone-system-id="<?= $phone_system_id ?>" class="<?= $on ? '' : 'text-body-secondary' ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-sm-inline-flex itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="<?= escapeHtml($kind['icon'] ?? 'fas fa-phone-volume') ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="<?= $domains_url ?>"><?= escapeHtml($name) ?></a>
                                <?php if (!$kind) { ?>
                                    <span class="badge rounded-pill text-bg-warning ms-1" title="This ITFlow doesn't know its kind (<?= escapeHtml($row['phone_system_kind']) ?>) - what it sends is turned away">Unknown kind</span>
                                <?php } elseif (!$on) { ?>
                                    <span class="badge rounded-pill text-bg-secondary ms-1" data-phone-system-state="off" title="What it sends is turned away">Off</span>
                                <?php } ?>
                                <div class="small text-body-secondary text-truncate"><span class="d-lg-none"><?= escapeHtml($kind['name'] ?? $row['phone_system_kind']) ?><?= $host !== '' ? ' &middot; ' : '' ?></span><?= escapeHtml($host) ?></div>
                                <div class="d-md-none small text-body-secondary"><?= $linked ?> of <?= $total ?> domains linked &middot; <?= number_format($calls) ?> calls</div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell small"><?= escapeHtml($kind['name'] ?? $row['phone_system_kind']) ?></td>
                    <td class="d-none d-md-table-cell small text-nowrap">
                        <a class="text-reset" href="<?= $domains_url ?>"><?= $linked ?> of <?= $total ?> linked</a>
                        <?php if ($suggested) { ?>
                            <div><a class="text-body-secondary" href="<?= $domains_url ?>&amp;show=unlinked" data-phone-suggested="<?= $suggested ?>" title="Your clients they look like - link them on its Domains page"><?= $suggested ?> to link</a></div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap"><?= number_format($calls) ?></td>
                    <td class="d-none d-sm-table-cell small" data-phone-heard="<?= $row['phone_system_heard_at'] ? 'yes' : 'never' ?>">
                        <?php if ($row['phone_system_heard_at']) { ?>
                            <span class="text-nowrap" title="<?= formatDateTime($row['phone_system_heard_at']) ?>"><i class="fas fa-fw fa-check-circle text-success me-1"></i><?= escapeHtml(timeAgo($row['phone_system_heard_at'])) ?></span>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not yet</span>
                            <div><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $setup_url ?>">Set it up</a></div>
                        <?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?= $domains_url ?>"><i class="fas fa-fw fa-globe text-secondary me-2"></i>Domains</a>
                                <?php if ($kind) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $setup_url ?>"><i class="fas fa-fw fa-plug text-secondary me-2"></i>Setup</a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                <?php } ?>
                                <?php if ($web !== '') { ?>
                                    <a class="dropdown-item" href="<?= escapeHtml($web) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open <?= escapeHtml($kind['short'] ?? $name) ?></a>
                                <?php } ?>
                                <?php if ($on) { ?>
                                    <a class="dropdown-item confirm-link" href="post.php?turn_phone_system_off=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Turn <?= escapeHtml($name) ?> off?" data-confirm-button="Turn off"
                                        data-confirm-text="What it sends is turned away - calls aren't kept and caller ID gets no names - until it is on again.">
                                        <i class="fas fa-fw fa-toggle-off text-secondary me-2"></i>Turn Off
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item" href="post.php?turn_phone_system_on=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-toggle-on text-secondary me-2"></i>Turn On</a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_phone_system=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= escapeHtml($name) ?>?" data-confirm-button="Delete"
                                    data-confirm-text="Its domains and the calls it sent go with it. Notes already on tickets stay.">
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
        'thing' => 'phone systems',
        'icon' => 'fa-phone-volume',
        'text' => 'Add ' . escapeHtml(implode(' or ', array_column($kinds, 'name'))) . ' and set it up to tell ITFlow about its calls: each call is kept with the contact it was with, and its desk phones show who is calling - nothing is asked of its API or database.',
        'actions' => [['label' => 'New Phone System', 'modal' => $add_url]],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
