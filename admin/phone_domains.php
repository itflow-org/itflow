<?php

/*
 * Admin > Phone Systems > a system's domains (functions/phone.php). Each domain (a FusionPBX
 * tenant) it has sent anything about is linked to the client it is, or not linked yet - with the
 * client it looks like suggested. A call on a client's domain is that client's; on your own
 * company's, or one not linked, it is the caller's. Until the system has been heard from, the
 * page says how to set it up (modals/phone/phone_system_setup.php). The handlers are
 * admin/post/phone.php.
 */

// Default Column Sortby Filter
$sort = "phone_domain_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$system = phoneTablesReady() ? phoneSystemRow($_GET['phone_system_id'] ?? 0) : null;
$kind = $system ? phoneKind($system) : null;

if (!$system || !$kind) { ?>
    <div class="alert alert-secondary"><i class="fas fa-fw fa-question-circle me-2"></i><?= $system ? 'This ITFlow doesn\'t know that phone system\'s kind.' : 'That phone system is gone - it may have been deleted.' ?> <a href="phone_systems.php" class="alert-link">Phone Systems</a></div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$phone_system_id = intval($system['phone_system_id']);
$name = phoneSystemName($system);
$short = $kind['short'];
$on = intval($system['phone_system_active']) === 1;
$csrf = $_SESSION['csrf_token'];
$edit_url = "modals/phone/phone_system_edit.php?id=$phone_system_id";
$setup_url = "modals/phone/phone_system_setup.php?id=$phone_system_id";
$suggestions = phoneDomainSuggestions($phone_system_id);
$web = phoneWebUrl($system);

// The views - every domain it has sent, the ones not linked yet, and the linked ones
$shows = [
    '' => ['label' => 'All', 'icon' => 'fa-list', 'sql' => '1 = 1', 'title' => "Every domain $name has sent"],
    'unlinked' => ['label' => 'Not linked', 'icon' => 'fa-unlink', 'sql' => 'phone_domain_client_id = 0', 'title' => 'Their calls go to whoever is calling'],
    'linked' => ['label' => 'Linked', 'icon' => 'fa-link', 'sql' => 'phone_domain_client_id > 0', 'title' => 'Their calls are their client\'s'],
];
$show = isset($shows[$_GET['show'] ?? '']) ? (string) ($_GET['show'] ?? '') : '';
$view_counts = [];
foreach ($shows as $key => $view) {
    $view_counts[$key] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM phone_domains WHERE phone_domain_system_id = $phone_system_id AND {$view['sql']}"))[0]);
}
$filter_url = function (array $change) {
    $get = array_merge($_GET, $change);
    unset($get['page'], $get['setup']);
    $get = array_filter($get, function ($value) {
        return $value !== null && $value !== '';
    });
    return '?' . http_build_query($get);
};

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'phone_domain_name' => "phone_domain_name $order",
    'calls_month' => "calls_month $order, phone_domain_name ASC",
    'client_name' => "client_name IS NULL, client_name $order, phone_domain_name ASC",
    'phone_domain_seen_at' => "phone_domain_seen_at IS NULL, phone_domain_seen_at $order, phone_domain_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'phone_domain_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS phone_domains.*, client_name, client_abbreviation, client_archived_at,
    (SELECT COUNT(*) FROM phone_calls WHERE phone_call_domain_id = phone_domains.phone_domain_id AND phone_call_started_at >= NOW() - INTERVAL 30 DAY) AS calls_month
    FROM phone_domains
    LEFT JOIN clients ON client_id = phone_domain_client_id AND phone_domain_client_id > 0
    WHERE phone_domain_system_id = $phone_system_id AND {$shows[$show]['sql']}
    AND (phone_domain_name LIKE '%$q%' OR client_name LIKE '%$q%')
    ORDER BY {$sortable_columns[$sort]}, phone_domain_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// The clients the suggestions name
$suggested_names = [];
if ($suggestions) {
    $suggested_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_id IN (" . implode(', ', array_map('intval', $suggestions)) . ")");
    while ($row = mysqli_fetch_assoc($suggested_sql)) {
        $suggested_names[intval($row['client_id'])] = $row['client_name'];
    }
}

$calls_total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM phone_calls WHERE phone_call_system_id = $phone_system_id"))[0]);
$own_client = intval($config_internal_client_id ?? 0);

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="<?= escapeHtml($kind['icon']) ?> fa-fw me-2"></i><?= escapeHtml($name) ?> <span class="fw-normal opacity-75">&middot; Domains</span>
            <?php if (!$on) { ?><span class="badge rounded-pill text-bg-secondary ms-2 fw-normal" data-phone-system-state="off" title="What it sends is turned away">Off</span><?php } ?>
        </h3>
        <div class="card-tools">
            <a class="btn btn-outline-light me-1" href="phone_systems.php" title="Back to Phone Systems"><i class="fas fa-fw fa-arrow-left"></i><span class="d-none d-lg-inline ms-2">Phone Systems</span></a>
            <?php if ($web !== '') { ?>
                <a class="btn btn-outline-light me-1" href="<?= escapeHtml($web) ?>" target="_blank" rel="noopener noreferrer" title="<?= escapeHtml($name) ?>"><i class="fas fa-fw fa-external-link-alt"></i><span class="d-none d-lg-inline ms-2">Open <?= escapeHtml($short) ?></span></a>
            <?php } ?>
            <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $setup_url ?>" data-phone-setup><i class="fas fa-fw fa-plug"></i><span class="d-none d-lg-inline ms-2">Setup</span></button>
            <div class="dropdown dropstart d-inline-block ms-1">
                <button class="btn btn-outline-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                    <?php if ($suggestions) { ?>
                        <a class="dropdown-item" href="post.php?link_phone_suggested=all&amp;phone_system_id=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-link text-secondary me-2"></i>Link the <?= count($suggestions) ?> suggested</a>
                    <?php } ?>
                    <a class="dropdown-item confirm-link" href="post.php?new_phone_system_key=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>"
                        data-confirm-title="Make a new key?" data-confirm-button="New key"
                        data-confirm-text="Its addresses change, and the ones in <?= escapeHtml($short) ?> now stop working until you put in the new ones.">
                        <i class="fas fa-fw fa-key text-secondary me-2"></i>New Key
                    </a>
                    <?php if ($on) { ?>
                        <a class="dropdown-item confirm-link" href="post.php?turn_phone_system_off=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>"
                            data-confirm-title="Turn <?= escapeHtml($name) ?> off?" data-confirm-button="Turn off"
                            data-confirm-text="What it sends is turned away - calls aren't kept and caller ID gets no names - until it is on again.">
                            <i class="fas fa-fw fa-toggle-off text-secondary me-2"></i>Turn Off
                        </a>
                    <?php } else { ?>
                        <a class="dropdown-item" href="post.php?turn_phone_system_on=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-toggle-on text-secondary me-2"></i>Turn On</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-2 small" data-phone-heard="<?= $system['phone_system_heard_at'] ? 'yes' : 'never' ?>">
        <div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
            <?php if (!$system['phone_system_heard_at']) { ?>
                <span class="text-body-secondary"><i class="fas fa-fw fa-info-circle me-1"></i>Not heard from yet - <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $setup_url ?>">set <?= escapeHtml($short) ?> up</a> to send ITFlow its calls. Its domains show here as they come in.</span>
            <?php } else { ?>
                <span><i class="fas fa-fw fa-check-circle text-success me-1"></i>Heard from <span title="<?= formatDateTime($system['phone_system_heard_at']) ?>"><?= escapeHtml(timeAgo($system['phone_system_heard_at'])) ?></span></span>
                <span class="text-body-secondary"><a class="text-reset" href="../agent/calls.php"><?= number_format($calls_total) ?> call<?= $calls_total === 1 ? '' : 's' ?></a> in ITFlow</span>
            <?php } ?>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="phone_system_id" value="<?= $phone_system_id ?>">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search domains..." title="The domain, or your client">
                        <button class="btn btn-dark" title="Search"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-7 col-md-6 small text-body-secondary text-md-end">
                    Link each client's domain to them - your own company's domain to your own client record.
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $view) { ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key === '' ? null : $key])) ?>" title="<?= escapeHtml($view['title']) ?>">
                    <i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= number_format(intval($view_counts[$key])) ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= sortLink('phone_domain_name', 'Domain') ?></th>
                <th class="d-none d-md-table-cell"><?= sortLink('calls_month', 'Calls, 30 days') ?></th>
                <th><?= sortLink('client_name', 'ITFlow client') ?></th>
                <th class="d-none d-lg-table-cell"><?= sortLink('phone_domain_seen_at', 'Last heard from') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $phone_domain_id = intval($row['phone_domain_id']);
                $client_id = intval($row['phone_domain_client_id']);
                $linked = $client_id && $row['client_name'] !== null;
                $suggested = $suggestions[$phone_domain_id] ?? 0;
                $link_url = "modals/phone/phone_domain_link.php?id=$phone_domain_id";
                ?>
                <tr data-phone-domain-id="<?= $phone_domain_id ?>">
                    <td class="ps-3">
                        <div class="fw-semibold font-monospace small"><?= escapeHtml($row['phone_domain_name']) ?></div>
                        <div class="d-md-none small text-body-secondary"><?= number_format(intval($row['calls_month'])) ?> calls</div>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap">
                        <?php if ($linked && intval($row['calls_month'])) { ?>
                            <?php // Your own company's calls are their callers' clients' - all of them ?>
                            <a href="../agent/calls.php<?= $client_id === $own_client ? '' : "?client_id=$client_id" ?>"><?= number_format(intval($row['calls_month'])) ?></a>
                        <?php } else { ?>
                            <?= number_format(intval($row['calls_month'])) ?>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($linked) { ?>
                            <div class="d-flex align-items-center gap-2">
                                <span class="itflow-client-avatar d-none d-sm-inline-flex bg-<?= clientInitialsTone($client_id) ?>-subtle text-<?= clientInitialsTone($client_id) ?>-emphasis" aria-hidden="true"><?= escapeHtml(clientInitials($row['client_abbreviation'] ?? '', $row['client_name'])) ?></span>
                                <a class="text-body" href="../agent/client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                                <?php if ($client_id === $own_client) { ?><span class="badge rounded-pill text-bg-light border fw-normal" title="Your own company - its calls go to whoever is calling">Your company</span><?php } ?>
                                <?php if ($row['client_archived_at'] !== null) { ?><span class="badge rounded-pill text-bg-secondary fw-normal">Archived</span><?php } ?>
                            </div>
                        <?php } elseif ($client_id) { ?>
                            <span class="text-warning-emphasis" title="Its client was deleted in ITFlow - link it again"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Its client was deleted</span>
                        <?php } else { ?>
                            <div class="d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                                <span class="text-body-secondary">Not linked</span>
                                <?php if ($suggested && isset($suggested_names[$suggested])) { ?>
                                    <a class="btn btn-sm btn-outline-primary" href="post.php?link_phone_suggested=<?= $phone_domain_id ?>&amp;phone_system_id=<?= $phone_system_id ?>&amp;csrf_token=<?= $csrf ?>" data-phone-suggest="<?= $suggested ?>" title="Link it to the client it looks like">
                                        <i class="fas fa-fw fa-link me-1"></i><?= escapeHtml($suggested_names[$suggested]) ?>
                                    </a>
                                <?php } ?>
                                <a class="btn btn-sm btn-light ajax-modal" href="#" data-modal-url="<?= $link_url ?>"><i class="fas fa-fw fa-search me-1"></i>Pick</a>
                            </div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-nowrap">
                        <?php if ($row['phone_domain_seen_at']) { ?>
                            <span title="<?= formatDateTime($row['phone_domain_seen_at']) ?>"><?= escapeHtml(timeAgo($row['phone_domain_seen_at'])) ?></span>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>">
                                    <i class="fas fa-fw fa-link text-secondary me-2"></i><?= $client_id ? 'Link to another client' : 'Link to a client' ?>
                                </a>
                                <?php if ($client_id) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?unlink_phone_domain=<?= $phone_domain_id ?>&amp;csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Unlink <?= escapeHtml($row['phone_domain_name']) ?>?" data-confirm-button="Unlink"
                                        data-confirm-text="Its calls go to whoever was calling, when ITFlow knows them.">
                                        <i class="fas fa-fw fa-unlink me-2"></i>Unlink
                                    </a>
                                <?php } ?>
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
        'thing' => 'domains',
        'icon' => 'fa-globe',
        'title' => escapeHtml($show === 'unlinked' ? 'Every domain is linked' : 'No domains yet'),
        'text' => $show === 'unlinked' ? '' : escapeHtml($system['phone_system_heard_at'] ? "$name hasn't named a domain yet." : "Once $short is set up, each domain shows here with its first call."),
        'actions' => !$system['phone_system_heard_at'] ? [['label' => 'Setup', 'modal' => $setup_url, 'size' => 'lg', 'icon' => 'fa-plug']] : [],
        'filtered' => $q !== '',
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php if (!empty($_GET['setup'])) { ?>
    <script>
    // Just added, or a new key - the setup window opens on its own
    document.addEventListener('DOMContentLoaded', function () {
        var button = document.querySelector('[data-phone-setup]');
        if (button) {
            button.click();
        }
    });
    </script>
<?php } ?>

<?php
require_once "../includes/footer.php";
