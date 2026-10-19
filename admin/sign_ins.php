<?php

/*
 * Admin > Sign-ins - who signs in, and from where. People: each agent and
 * portal contact with sign-in activity - their last sign in with its IP
 * address and device, how many sign-ins and failed ones (the wrong MFA codes
 * among them), how many IP addresses and, for a date range, how many of them
 * were new. IP addresses: each address - the sign-ins and failures from it,
 * the accounts it tried (and emails with no account), when it was first and
 * last seen, and whether it is being turned away now. Searched by name, email
 * or IP address, for a date range, sorted from the column headings, a page at
 * a time; each opens its sign-ins in Admin > Audit Logs. The work is in
 * functions/sign_in.php.
 */

// Default Column Sortby Filter
$sort = "last_seen";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$page_title = 'Sign-ins';

$view = ($_GET['view'] ?? '') === 'ips' ? 'ips' : '';
$types = "log_type IN ('" . implode("', '", SIGN_IN_TYPES) . "')";
$failed = "log_action IN ('" . implode("', '", SIGN_IN_FAILED) . "')";
$dated = $dtf !== '1970-01-01' || $dtt !== '2099-12-31';
$range = $dated ? "AND log_created_at >= '$dtf 00:00:00' AND log_created_at < '$dtt' + INTERVAL 1 DAY" : '';
$like = $q !== '' ? "AND (user_name LIKE '%$q%' OR user_email LIKE '%$q%' OR log_ip LIKE '%$q%')" : '';
$joins = "LEFT JOIN users ON users.user_id = log_user_id AND log_user_id > 0";

// Only these sort, for the view on show
$sortable_columns = $view === 'ips' ? [
    'log_ip' => "log_ip $order",
    'sign_ins' => "sign_ins $order, last_seen DESC",
    'failures' => "failures $order, last_seen DESC",
    'accounts' => "accounts $order, last_seen DESC",
    'first_seen' => "first_seen $order",
    'last_seen' => "last_seen $order",
] : [
    'user_name' => "user_name $order",
    'sign_ins' => "sign_ins $order, last_seen DESC",
    'failures' => "failures $order, last_seen DESC",
    'ips' => "ips $order, last_seen DESC",
    'last_seen' => "last_seen $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'last_seen';
}

$rows = [];
if ($view === 'ips') {
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS log_ip,
            SUM(log_action = 'Success') AS sign_ins, SUM($failed) AS failures, SUM(log_action = 'Blocked') AS blocked,
            COUNT(DISTINCT NULLIF(log_user_id, 0)) AS accounts,
            COUNT(DISTINCT IF(log_user_id = 0 AND log_action = 'Failed', log_description, NULL)) AS unknown,
            MIN(log_created_at) AS first_seen, MAX(log_created_at) AS last_seen, MAX(log_id) AS last_id
        FROM logs $joins
        WHERE $types AND log_ip <> '' $range $like
        GROUP BY log_ip
        ORDER BY {$sortable_columns[$sort]}
        LIMIT $record_from, $record_to");
} else {
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS log_user_id, user_name, user_email, user_avatar, user_type, user_status, user_archived_at,
            SUM(log_action = 'Success') AS sign_ins, SUM($failed) AS failures, SUM(log_action = 'MFA Failed') AS mfa_failures,
            COUNT(DISTINCT IF(log_action = 'Success', log_ip, NULL)) AS ips,
            MAX(IF(log_action = 'Success', log_id, NULL)) AS last_success_id, MAX(log_created_at) AS last_seen
        FROM logs JOIN users ON users.user_id = log_user_id
        WHERE $types AND log_user_id > 0 $range $like
        GROUP BY log_user_id
        ORDER BY {$sortable_columns[$sort]}
        LIMIT $record_from, $record_to");
}
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[] = $row;
}

// How many of each, for the filters
$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT users.user_id), COUNT(DISTINCT NULLIF(log_ip, ''))
    FROM logs $joins WHERE $types $range $like"));

if ($view === 'ips') {
    // The accounts each address tried, its last device, and whether it is turned away now
    $ip_list = implode(', ', array_map(function ($row) { return "'" . escapeSql($row['log_ip']) . "'"; }, $rows)) ?: "''";
    $tried = [];
    $sql = mysqli_query($mysqli, "SELECT log_ip, users.user_id, user_name, user_avatar, user_type, MAX(log_id) AS last_id FROM logs JOIN users ON users.user_id = log_user_id
        WHERE log_ip IN ($ip_list) AND $types $range GROUP BY log_ip, users.user_id ORDER BY last_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tried[$row['log_ip']][] = $row;
    }
    $last_devices = [];
    $last_ids = array_filter(array_map('intval', array_column($rows, 'last_id')));
    if ($last_ids) {
        $sql = mysqli_query($mysqli, "SELECT log_ip, log_user_agent FROM logs WHERE log_id IN (" . implode(',', $last_ids) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $last_devices[$row['log_ip']] = auditLogDevice($row['log_user_agent']);
        }
    }
    $turned_away = [];
    $sql = mysqli_query($mysqli, "SELECT log_ip, COUNT(*) FROM logs WHERE log_ip IN ($ip_list) AND $types AND $failed
        AND log_created_at > NOW() - INTERVAL " . SIGN_IN_WINDOW_MINUTES . " MINUTE GROUP BY log_ip HAVING COUNT(*) >= " . SIGN_IN_LOCKOUT);
    while ($row = mysqli_fetch_row($sql)) {
        $turned_away[$row[0]] = true;
    }
} else {
    // Each one's last sign in, and the addresses that were new in the date range
    $last = [];
    $last_ids = array_filter(array_map('intval', array_column($rows, 'last_success_id')));
    if ($last_ids) {
        $sql = mysqli_query($mysqli, "SELECT log_id, log_ip, log_user_agent, log_created_at FROM logs WHERE log_id IN (" . implode(',', $last_ids) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $last[intval($row['log_id'])] = $row;
        }
    }
    $new_ips = [];
    $user_ids = array_filter(array_map('intval', array_column($rows, 'log_user_id')));
    if ($dated && $user_ids) {
        $sql = mysqli_query($mysqli, "SELECT log_user_id, COUNT(*) FROM (
                SELECT log_user_id, MIN(log_created_at) AS first_at FROM logs
                WHERE log_user_id IN (" . implode(',', $user_ids) . ") AND $types AND log_action = 'Success' AND log_ip <> ''
                GROUP BY log_user_id, log_type, log_ip) firsts
            WHERE first_at >= '$dtf 00:00:00' AND first_at < '$dtt' + INTERVAL 1 DAY GROUP BY log_user_id");
        while ($row = mysqli_fetch_row($sql)) {
            $new_ips[intval($row[0])] = intval($row[1]);
        }
    }
    // A portal contact opens on their contact page
    $contacts = [];
    if ($user_ids) {
        $sql = mysqli_query($mysqli, "SELECT contact_user_id, contact_id, contact_client_id FROM contacts WHERE contact_user_id IN (" . implode(',', $user_ids) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $contacts[intval($row['contact_user_id'])] = $row;
        }
    }
}

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
// A view keeps the search and dates, and starts from the top
$view_url = function ($key) {
    $query = array_diff_key($_GET, ['page' => 1, 'sort' => 1, 'order' => 1]);
    $query['view'] = $key;
    return '?' . http_build_query(array_filter($query, function ($value) {
        return $value !== '' && $value !== null && $value !== [];
    }));
};
// Their sign-ins in Admin > Audit Logs, for the same dates
$dates_query = $dated ? ['canned_date' => 'custom', 'dtf' => $dtf, 'dtt' => $dtt] : [];
$audit_url = function (array $query) use ($dates_query) {
    return '/admin/audit_logs.php?' . http_build_query(['view' => 'signins'] + $query + $dates_query);
};

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-sign-in-alt me-2"></i>Sign-ins</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light" href="settings_security.php" title="When admins are told about failed sign-ins">
                <i class="fas fa-fw fa-bell"></i><span class="d-none d-lg-inline ms-2">Alerts</span>
            </a>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($view !== '') { ?><input type="hidden" name="view" value="<?= $view ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-6 col-md-7">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Name, email or IP address">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-5">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="When" aria-label="When">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml(is_string($_GET['canned_date'] ?? null) ? $_GET['canned_date'] : '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-3">
            <a class="nav-link text-nowrap <?= $view === '' ? 'active' : '' ?>" href="<?= escapeHtml($view_url('')) ?>" data-sign-in-view="people" title="Agents and portal contacts">
                <i class="fas fa-fw fa-user me-1"></i>People <span class="badge rounded-pill ms-1"><?= number_format(intval($counts[0])) ?></span>
            </a>
            <a class="nav-link text-nowrap <?= $view === 'ips' ? 'active' : '' ?>" href="<?= escapeHtml($view_url('ips')) ?>" data-sign-in-view="ips" title="Where sign-ins came from">
                <i class="fas fa-fw fa-network-wired me-1"></i>IP addresses <span class="badge rounded-pill ms-1"><?= number_format(intval($counts[1])) ?></span>
            </a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <?php if ($view === 'ips') { ?>
                <tr>
                    <th class="ps-3"><?= $sort_link('log_ip', 'IP address') ?></th>
                    <th><?= $sort_link('accounts', 'Accounts tried') ?></th>
                    <th class="text-end"><?= $sort_link('sign_ins', 'Sign-ins') ?></th>
                    <th class="text-end"><?= $sort_link('failures', 'Failed') ?></th>
                    <th class="d-none d-lg-table-cell"><?= $sort_link('first_seen', 'First seen') ?></th>
                    <th><?= $sort_link('last_seen', 'Last seen') ?></th>
                    <th></th>
                </tr>
            <?php } else { ?>
                <tr>
                    <th class="ps-3"><?= $sort_link('user_name', 'Who') ?></th>
                    <th class="d-none d-md-table-cell">Last sign in</th>
                    <th class="text-end"><?= $sort_link('sign_ins', 'Sign-ins') ?></th>
                    <th class="text-end"><?= $sort_link('failures', 'Failed') ?></th>
                    <th class="text-end d-none d-sm-table-cell"><?= $sort_link('ips', 'IP addresses') ?></th>
                    <th class="d-none d-lg-table-cell"><?= $sort_link('last_seen', 'Last seen') ?></th>
                    <th></th>
                </tr>
            <?php } ?>
            </thead>
            <tbody>
            <?php if ($view === 'ips') {
                foreach ($rows as $row) {
                    $ip = (string) $row['log_ip'];
                    $people = $tried[$ip] ?? [];
                    $unknown = intval($row['unknown']); ?>
                    <tr data-sign-in-ip="<?= escapeHtml($ip) ?>">
                        <td class="ps-3">
                            <div class="font-monospace"><?= escapeHtml($ip) ?></div>
                            <div class="small text-body-secondary"><?= escapeHtml($last_devices[$ip] ?? '') ?></div>
                            <?php if (isset($turned_away[$ip])) { ?>
                                <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal mt-1" title="<?= SIGN_IN_LOCKOUT ?> or more failed sign-ins in the last <?= SIGN_IN_WINDOW_MINUTES ?> minutes" data-turned-away><i class="fas fa-ban me-1"></i>Turned away now</span>
                            <?php } elseif (intval($row['blocked'])) { ?>
                                <span class="badge rounded-pill text-bg-light border fw-normal mt-1" title="Turned away for a while, for too many failed sign-ins"><i class="fas fa-ban me-1"></i>Turned away <?= number_format(intval($row['blocked'])) ?>×</span>
                            <?php } ?>
                        </td>
                        <td class="small" style="min-width: 14rem;">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach (array_slice($people, 0, 3) as $person) { ?>
                                    <span class="d-inline-flex align-items-center gap-1 text-nowrap"><?= userAvatar($person['user_id'], $person['user_name'], (string) $person['user_avatar'], 'xs') ?><?= escapeHtml($person['user_name']) ?><?= intval($person['user_type']) === 2 ? ' <span class="badge rounded-pill text-bg-light border fw-normal">Portal</span>' : '' ?></span>
                                <?php } ?>
                                <?php if (count($people) > 3) { ?><span class="text-body-secondary">and <?= count($people) - 3 ?> more</span><?php } ?>
                            </div>
                            <?php if ($unknown) { ?>
                                <div class="text-danger-emphasis mt-1" data-unknown-emails><i class="fas fa-fw fa-question-circle me-1"></i><?= $unknown ?> email<?= $unknown === 1 ? '' : 's' ?> with no account</div>
                            <?php } ?>
                        </td>
                        <td class="text-end"><?= number_format(intval($row['sign_ins'])) ?></td>
                        <td class="text-end <?= intval($row['failures']) ? 'text-danger-emphasis fw-semibold' : 'text-body-secondary' ?>"><?= number_format(intval($row['failures'])) ?></td>
                        <td class="d-none d-lg-table-cell small text-nowrap" title="<?= escapeHtml($row['first_seen']) ?>"><?= escapeHtml(date('M j, Y g:i A', strtotime($row['first_seen']))) ?></td>
                        <td class="small text-nowrap" title="<?= escapeHtml($row['last_seen']) ?>"><?= escapeHtml(timeAgo($row['last_seen'])) ?></td>
                        <td class="text-end pe-3">
                            <a class="btn btn-sm btn-light" href="<?= escapeHtml($audit_url(['q' => $ip])) ?>" title="Its sign-ins in the audit log"><i class="fas fa-fw fa-history"></i></a>
                        </td>
                    </tr>
                <?php }
            } else {
                foreach ($rows as $row) {
                    $user_id = intval($row['log_user_id']);
                    $portal = intval($row['user_type']) === 2;
                    $success = $last[intval($row['last_success_id'])] ?? null;
                    $contact = $contacts[$user_id] ?? null;
                    $state = $row['user_archived_at'] !== null ? 'Archived' : (intval($row['user_status']) === 1 ? '' : (USER_STATUSES[intval($row['user_status'])][0] ?? 'Disabled')); ?>
                    <tr data-sign-in-user="<?= $user_id ?>">
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <?= userAvatar($user_id, $row['user_name'], (string) $row['user_avatar'], 'sm') ?>
                                <div class="min-w-0">
                                    <?php if ($portal && $contact) { ?>
                                        <a class="fw-semibold text-body" href="/agent/contact.php?client_id=<?= intval($contact['contact_client_id']) ?>&amp;contact_id=<?= intval($contact['contact_id']) ?>"><?= escapeHtml($row['user_name']) ?></a>
                                    <?php } elseif (!$portal) { ?>
                                        <a class="fw-semibold text-body ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/user/user.php?id=<?= $user_id ?>"><?= escapeHtml($row['user_name']) ?></a>
                                    <?php } else { ?>
                                        <span class="fw-semibold"><?= escapeHtml($row['user_name']) ?></span>
                                    <?php } ?>
                                    <?= $portal ? '<span class="badge rounded-pill text-bg-light border fw-normal">Portal</span>' : '' ?>
                                    <?= $state !== '' ? '<span class="badge rounded-pill text-bg-secondary fw-normal">' . escapeHtml($state) . '</span>' : '' ?>
                                    <div class="small text-body-secondary text-truncate"><?= escapeHtml($row['user_email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell small">
                            <?php if ($success) { ?>
                                <div title="<?= escapeHtml($success['log_created_at']) ?>"><?= escapeHtml(timeAgo($success['log_created_at'])) ?></div>
                                <div class="text-body-secondary"><span class="font-monospace"><?= escapeHtml($success['log_ip']) ?></span><?= auditLogDevice($success['log_user_agent']) !== '' ? ' · ' . escapeHtml(auditLogDevice($success['log_user_agent'])) : '' ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">None<?= $dated ? ' in these dates' : '' ?></span>
                            <?php } ?>
                        </td>
                        <td class="text-end"><?= number_format(intval($row['sign_ins'])) ?></td>
                        <td class="text-end">
                            <span class="<?= intval($row['failures']) ? 'text-danger-emphasis fw-semibold' : 'text-body-secondary' ?>"><?= number_format(intval($row['failures'])) ?></span>
                            <?php if (intval($row['mfa_failures'])) { ?>
                                <div class="small text-danger-emphasis" title="The password was right, the MFA code wrong"><?= number_format(intval($row['mfa_failures'])) ?> MFA</div>
                            <?php } ?>
                        </td>
                        <td class="text-end d-none d-sm-table-cell">
                            <?= number_format(intval($row['ips'])) ?>
                            <?php if (!empty($new_ips[$user_id])) { ?>
                                <div><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal" title="First used to sign in in these dates" data-new-ips><?= $new_ips[$user_id] ?> new</span></div>
                            <?php } ?>
                        </td>
                        <td class="d-none d-lg-table-cell small text-nowrap" title="<?= escapeHtml($row['last_seen']) ?>"><?= escapeHtml(timeAgo($row['last_seen'])) ?></td>
                        <td class="text-end pe-3">
                            <a class="btn btn-sm btn-light" href="<?= escapeHtml($audit_url(['user' => $user_id])) ?>" title="Their sign-ins in the audit log"><i class="fas fa-fw fa-history"></i></a>
                        </td>
                    </tr>
                <?php }
            } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => $view === 'ips' ? 'IP addresses' : 'sign-ins',
        'icon' => 'fa-sign-in-alt',
        'filtered' => $q !== '' || $dated,
        'views' => ['view'],
        'text' => 'Agents and portal contacts signing in - and failing to - show here, by who and by where from.',
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
