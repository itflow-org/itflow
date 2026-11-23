<?php

/*
 * Calls - every call the phone systems sent (functions/phone.php, Admin > Phone Systems), or one
 * client's, or one contact's (contact_id). Each row is who it was with - the contact, else the
 * caller ID name or number - the extension and agent on this side, when, how long they talked and
 * how it ended, and the ticket it went on. Add to Ticket puts it on one of the client's open
 * tickets, or a new one, as an internal note with its talk time
 * (modals/phone/phone_call_ticket.php, agent/post/phone.php).
 */

// Default Column Sortby Filter
$sort = "phone_call_started_at";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND phone_call_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

enforceUserPermission('module_support');

$page_title = 'Calls';

if (!phoneSystemsExist()) { ?>
    <div class="card">
        <div class="card-body text-center text-body-secondary py-5">
            <i class="fas fa-phone-volume fa-2x mb-2 d-block opacity-50"></i>
            No phone system is set up - an admin adds one in Admin &gt; Integrations &gt; Phone Systems.
        </div>
    </div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$can_edit = lookupUserPermission('module_support') >= 2;
$statuses = phoneCallStatuses();
$directions = phoneCallDirections();

// One contact's calls - from their page
$contact_filter = intval($_GET['contact_id'] ?? 0);
$contact_filter_name = '';
if ($contact_filter) {
    $contact_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_filter"));
    if ($contact_row && hasClientAccess($contact_row['contact_client_id'])) {
        $contact_filter_name = (string) $contact_row['contact_name'];
    } else {
        $contact_filter = 0;
    }
}
$contact_query = $contact_filter ? "AND phone_call_contact_id = $contact_filter" : '';

// The views - all of them, each way, missed ones, and callers nobody knows
$shows = [
    '' => ['label' => 'All', 'icon' => 'fa-list', 'sql' => '1 = 1'],
    'inbound' => ['label' => 'Inbound', 'icon' => 'fa-arrow-down', 'sql' => "phone_call_direction = 'inbound'"],
    'outbound' => ['label' => 'Outbound', 'icon' => 'fa-arrow-up', 'sql' => "phone_call_direction = 'outbound'"],
    'missed' => ['label' => 'Missed', 'icon' => 'fa-phone-slash', 'sql' => "phone_call_direction = 'inbound' AND phone_call_status IN ('missed', 'voicemail')"],
    'unknown' => ['label' => 'Unknown', 'icon' => 'fa-question', 'sql' => "phone_call_direction <> 'local' AND phone_call_contact_id = 0", 'title' => 'With numbers that aren\'t a contact\'s'],
];
$show = isset($shows[$_GET['show'] ?? '']) ? (string) ($_GET['show'] ?? '') : '';

$scope = clientScopeSql('phone_call_client_id') . " $client_query $contact_query";
$view_counts = [];
foreach ($shows as $key => $view) {
    $view_counts[$key] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM phone_calls WHERE {$view['sql']} $scope"))[0]);
}
$filter_url = function (array $change) {
    $get = array_merge($_GET, $change);
    unset($get['page']);
    $get = array_filter($get, function ($value) {
        return $value !== null && $value !== '';
    });
    return '?' . http_build_query($get);
};

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'phone_call_started_at' => "phone_call_started_at $order",
    'who' => "COALESCE(contact_name, NULLIF(phone_call_name, ''), phone_call_number) $order, phone_call_started_at DESC",
    'client_name' => "client_name IS NULL, client_name $order, phone_call_started_at DESC",
    'phone_call_extension' => "phone_call_extension = '', phone_call_extension $order, phone_call_started_at DESC",
    'phone_call_talk_seconds' => "phone_call_talk_seconds $order, phone_call_started_at DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'phone_call_started_at';
}

$phone_digits = preg_replace('/\D/', '', (string) $q);
$number_search = strlen($phone_digits) >= 3 ? " OR phone_call_number LIKE '%" . escapeSql($phone_digits) . "%' OR phone_call_dialed LIKE '%" . escapeSql($phone_digits) . "%'" : '';

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS phone_calls.*, phone_system_kind, phone_system_name, phone_system_web_url,
        contact_name, client_name, ticket_prefix, ticket_number, ticket_subject, user_name
    FROM phone_calls
    LEFT JOIN phone_systems ON phone_system_id = phone_call_system_id
    LEFT JOIN contacts ON contact_id = phone_call_contact_id AND phone_call_contact_id > 0
    LEFT JOIN clients ON client_id = phone_call_client_id AND phone_call_client_id > 0
    LEFT JOIN tickets ON ticket_id = phone_call_ticket_id AND phone_call_ticket_id > 0
    LEFT JOIN users ON users.user_id = phone_call_user_id AND phone_call_user_id > 0
    WHERE {$shows[$show]['sql']} $scope
    AND (contact_name LIKE '%$q%' OR phone_call_name LIKE '%$q%' OR client_name LIKE '%$q%' OR phone_call_extension LIKE '%$q%' OR user_name LIKE '%$q%'$number_search)
    ORDER BY {$sortable_columns[$sort]}, phone_call_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-phone-volume me-2"></i>Calls
            <?php if ($contact_filter_name !== '') { ?>
                <span class="small text-body-secondary fw-normal ms-1">with <?= escapeHtml($contact_filter_name) ?></span>
                <a class="small ms-2" href="<?= escapeHtml($filter_url(['contact_id' => null])) ?>" title="Every call"><i class="fas fa-times"></i></a>
            <?php } ?>
        </h3>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?><input type="hidden" name="client_id" value="<?= $client_id ?>"><?php } ?>
            <?php if ($contact_filter) { ?><input type="hidden" name="contact_id" value="<?= $contact_filter ?>"><?php } ?>
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search calls..." title="Contact, caller ID name, number, client, extension or agent">
                        <button class="btn btn-dark" title="Search"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $view) { ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key === '' ? null : $key])) ?>"<?= isset($view['title']) ? ' title="' . escapeHtml($view['title']) . '"' : '' ?>>
                    <i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= number_format(intval($view_counts[$key])) ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= sortLink('who', 'Call') ?></th>
                <?php if (!$client_url) { ?><th class="d-none d-md-table-cell"><?= sortLink('client_name', 'Client') ?></th><?php } ?>
                <th class="d-none d-xl-table-cell"><?= sortLink('phone_call_extension', 'Taken by') ?></th>
                <th><?= sortLink('phone_call_started_at', 'When') ?></th>
                <th class="d-none d-sm-table-cell text-end"><?= sortLink('phone_call_talk_seconds', 'Talked') ?></th>
                <th class="d-none d-lg-table-cell">Ticket</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $phone_call_id = intval($row['phone_call_id']);
                $call_client_id = intval($row['phone_call_client_id']);
                $contact_id = intval($row['phone_call_contact_id']);
                [$status_label, $status_icon, $status_tone] = $statuses[$row['phone_call_status']] ?? [ucfirst((string) $row['phone_call_status']), 'fa-phone', 'secondary'];
                [$direction_label, $direction_icon] = $directions[$row['phone_call_direction']] ?? ['Call', 'fa-phone'];
                $who = phoneCallWho($row);
                $number = phoneNumberText($row['phone_call_number']);
                $answered = $row['phone_call_status'] === 'answered';
                $call_web = $row['phone_system_kind'] !== null ? phoneCallWebUrl($row, (string) $row['phone_call_uuid']) : '';
                $ticket_id = intval($row['phone_call_ticket_id']);
                ?>
                <tr data-phone-call-id="<?= $phone_call_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-phone-call-icon text-<?= $answered ? 'success' : $status_tone ?>-emphasis bg-<?= $answered ? 'success' : $status_tone ?>-subtle" title="<?= escapeHtml("$direction_label - $status_label") ?>">
                                <i class="fas fa-fw <?= $answered ? $direction_icon : $status_icon ?>"></i>
                            </span>
                            <div class="min-w-0">
                                <?php if ($contact_id && $row['contact_name'] !== null) { ?>
                                    <a class="fw-semibold" href="contact.php?client_id=<?= $call_client_id ?>&amp;contact_id=<?= $contact_id ?>"><?= escapeHtml($who) ?></a>
                                <?php } else { ?>
                                    <span class="fw-semibold"><?= escapeHtml($who) ?></span>
                                <?php } ?>
                                <div class="small text-body-secondary text-truncate">
                                    <?= escapeHtml($direction_label) ?><?php if ($number !== '' && $number !== $who) { ?> &middot; <?= escapeHtml($number) ?><?php } ?>
                                    <?php if (!$answered) { ?>&middot; <span class="text-<?= $status_tone ?>-emphasis"><?= escapeHtml($status_label) ?></span><?php } ?>
                                    <?php if ($row['phone_call_extension'] !== '') { ?><span class="d-xl-none"> &middot; <?= escapeHtml($row['user_name'] ?? phoneNumberText($row['phone_call_extension'])) ?></span><?php } ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <?php if (!$client_url) { ?>
                        <td class="d-none d-md-table-cell small">
                            <?php if ($call_client_id && $row['client_name'] !== null) { ?>
                                <a class="text-reset d-block text-truncate" style="max-width: 14rem" href="calls.php?client_id=<?= $call_client_id ?>" title="<?= escapeHtml($row['client_name']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                    <?php } ?>
                    <td class="d-none d-xl-table-cell small">
                        <?php if ($row['user_name'] !== null) { ?>
                            <div><?= escapeHtml($row['user_name']) ?></div>
                        <?php } ?>
                        <div class="text-body-secondary"><?= $row['phone_call_extension'] !== '' ? escapeHtml(phoneNumberText($row['phone_call_extension'])) : '-' ?></div>
                    </td>
                    <td class="small text-nowrap">
                        <span title="<?= formatDateTime($row['phone_call_started_at']) ?>"><?= escapeHtml(timeAgo($row['phone_call_started_at'])) ?></span>
                        <div class="text-body-secondary"><?= escapeHtml(date('M j, g:i a', strtotime($row['phone_call_started_at']))) ?></div>
                    </td>
                    <td class="d-none d-sm-table-cell small text-end text-nowrap"><?= $answered ? escapeHtml(phoneSecondsText($row['phone_call_talk_seconds'])) : '<span class="text-body-secondary">-</span>' ?></td>
                    <td class="d-none d-lg-table-cell small text-nowrap">
                        <?php if ($ticket_id && $row['ticket_number'] !== null) { ?>
                            <a href="ticket.php?client_id=<?= $call_client_id ?>&amp;ticket_id=<?= $ticket_id ?>" title="<?= escapeHtml((string) $row['ticket_subject']) ?>"><?= escapeHtml($row['ticket_prefix'] . $row['ticket_number']) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <?php if (($can_edit && $row['phone_call_direction'] !== 'local') || $call_web !== '') { ?>
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <?php if ($can_edit && $row['phone_call_direction'] !== 'local') { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/phone/phone_call_ticket.php?id=<?= $phone_call_id ?>"><i class="fas fa-fw fa-life-ring text-secondary me-2"></i>Add to Ticket</a>
                                    <?php } ?>
                                    <?php if ($call_web !== '') { ?>
                                        <a class="dropdown-item" href="<?= escapeHtml($call_web) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open in <?= escapeHtml(phoneKind($row)['short'] ?? 'the phone system') ?></a>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'calls',
        'icon' => 'fa-phone-volume',
        'title' => $show === 'missed' ? 'No missed calls' : null,
        'text' => $show === '' ? 'Calls come in as the phone system sends them - each one with the contact it was with.' : '',
        'filtered' => $q !== '',
    ];
    $empty_state = array_filter($empty_state, function ($value) {
        return $value !== null;
    });
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
