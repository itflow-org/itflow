<?php

/*
 * ajax.php
 * Similar to post.php, but for requests using Asynchronous JavaScript
 * Always returns data in JSON format, unless otherwise specified
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";
require_once "../libs/totp/totp.php";

/*
 * The host in a New / Edit Certificate form, checked when asked: connect,
 * take the certificate it serves and say what it is - OK, for another name,
 * untrusted, expired, or nothing answering - with what will be saved, the
 * client's domain it belongs to, and whether the client has it already.
 */
if (isset($_GET['certificate_check'])) {
    enforceUserPermission('module_support', 2);

    header('Content-Type: application/json');

    $input = trim((string) ($_GET['host'] ?? ''));
    $check_client_id = intval($_GET['client_id'] ?? 0);
    $check_certificate_id = intval($_GET['certificate_id'] ?? 0);

    if ($input === '') {
        echo json_encode(['state' => '', 'message' => '', 'html' => '']);
        exit;
    }
    $host = certificateHost($input);
    if (!$host) {
        echo json_encode(['state' => 'bad', 'message' => "That isn't a host - something like www.example.com, or portal.example.com:8443.", 'html' => '']);
        exit;
    }

    $response = ['host' => $host['display'], 'domain_id' => 0, 'html' => ''];
    $notes = [];
    if (strcasecmp($input, $host['display']) !== 0) {
        $notes[] = "Saved as {$host['display']}.";
    }

    // Already on this client? Which of its domains is it under?
    if ($check_client_id) {
        $client_id = $check_client_id;
        enforceClientAccess();
        $host_sql = escapeSql($host['display']);
        $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT certificate_name FROM certificates WHERE certificate_domain = '$host_sql' AND certificate_client_id = $check_client_id AND certificate_id <> $check_certificate_id AND certificate_archived_at IS NULL LIMIT 1"));
        if ($existing) {
            $notes[] = 'This client already has it, as ' . $existing['certificate_name'] . '.';
        }
        $response['domain_id'] = certificateDomainFor($host['host'], $check_client_id);
    }

    $check = certificateCheck($host);
    if (empty($check['reachable'])) {
        $response['state'] = 'bad';
        $response['message'] = trim(implode(' ', $notes) . " Nothing answered on {$host['display']} - check the host and port. If ITFlow can't reach it, paste the certificate instead.");
        echo json_encode($response);
        exit;
    }

    $state = CERTIFICATE_CHECK_STATES[$check['status']];
    $response['state'] = $check['status'] === 'ok' ? 'ok' : 'warn';
    $response['message'] = trim(implode(' ', $notes) . ' ' . $state['text'] . '.');

    $expiry = certificateExpiry(['certificate_expire' => $check['expire'], 'certificate_issued_at' => $check['issued_at']]);
    $names = $check['names'];
    $response['html'] = '<div class="d-flex flex-wrap gap-2 mb-2">'
        . certificateStatusPill(['certificate_check_status' => $check['status'], 'certificate_domain' => $host['display']])
        . certificateExpiryPill($expiry, true) . '</div>'
        . '<dl class="itflow-asset-dl mb-0">'
        . '<dt>Issuer</dt><dd class="text-break">' . escapeHtml($check['issuer'] ?: 'Unknown') . ($check['self_signed'] ? ' <span class="text-body-secondary">(self-signed)</span>' : '') . '</dd>'
        . '<dt>Valid</dt><dd>' . escapeHtml($check['issued_at']) . ' to ' . escapeHtml($check['expire']) . ' <span class="text-body-secondary">(' . $check['days'] . ' days)</span></dd>'
        . '<dt>Covers</dt><dd class="text-break">' . escapeHtml(implode(', ', array_slice($names, 0, 6))) . (count($names) > 6 ? ' <span class="text-body-secondary">and ' . (count($names) - 6) . ' more</span>' : '') . '</dd>'
        . '</dl>';

    echo json_encode($response);
    exit;
}

/*
 * Tag picker (js/tag_picker.js) - replaces the "Assign Tags" modals.
 *
 * GET lists the tags one record, or a bulk selection of records, can have and
 * how many of them have each. POST ticks a tag on or off for all of them -
 * or, for an admin, makes a new tag and ticks it on - and sends back the
 * redrawn pills for every record, so the page updates in place.
 *
 * Same checks the modals' post handlers made: edit rights on the module and
 * access to each record's client. tagRecordsEditable() does both.
 */
if (isset($_GET['tag_picker'])) {

    header('Content-Type: application/json');

    $entity = (string) ($_GET['entity'] ?? '');
    $config = tagEntity($entity);
    $records = $config ? tagRecordsEditable($entity, (array) ($_GET['ids'] ?? [])) : false;

    if ($records === false) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot change the tags on these.']);
        exit();
    }

    $ids = implode(',', array_keys($records));
    $response = [
        'records' => count($records),
        'type' => $config['type'],
        'create' => (bool) $session_is_admin,
        'manage' => $session_is_admin ? "/admin/tags.php?type={$config['type']}" : '',
        'tags' => []
    ];

    // An archived tag is only offered to take it off the records that still have it
    $sql = mysqli_query($mysqli,
        "SELECT tag_id, tag_name, tag_description, tag_color, tag_icon, tag_archived_at,
            (SELECT COUNT(*) FROM {$config['link_table']}
                WHERE {$config['link_tag']} = tags.tag_id AND {$config['link_record']} IN ($ids)) AS chosen
        FROM tags
        WHERE tag_type = {$config['type']}
        ORDER BY tag_name ASC"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        if ($row['tag_archived_at'] && !intval($row['chosen'])) {
            continue;
        }
        $response['tags'][] = [
            'id' => intval($row['tag_id']),
            'name' => $row['tag_name'],
            'description' => trim((string) $row['tag_description']) . ($row['tag_archived_at'] ? (trim((string) $row['tag_description']) !== '' ? ' - ' : '') . 'Archived' : ''),
            'color' => tagColor($row['tag_color']),
            'icon' => tagIcon($row['tag_icon']),
            'chosen' => intval($row['chosen'])
        ];
    }

    echo json_encode($response);
    exit();
}

if (isset($_POST['tag_picker_set']) || isset($_POST['tag_picker_create'])) {

    validateCSRFToken();

    header('Content-Type: application/json');

    $entity = (string) ($_POST['entity'] ?? '');
    $config = tagEntity($entity);
    $records = $config ? tagRecordsEditable($entity, (array) ($_POST['ids'] ?? [])) : false;

    if ($records === false) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot change the tags on these.']);
        exit();
    }

    $type = $config['type'];
    $link = $config['link_table'];
    $link_record = $config['link_record'];
    $link_tag = $config['link_tag'];

    if (isset($_POST['tag_picker_create'])) {

        // Making tags is an admin job everywhere else (Admin > Tags), so it is here too
        $name = trim((string) ($_POST['name'] ?? ''));
        if (!$session_is_admin || $name === '' || mb_strlen($name) > 200) {
            http_response_code(403);
            echo json_encode(['error' => 'That tag cannot be made here.']);
            exit();
        }
        $name_sql = escapeSql($name);

        // Typing the name of a tag that already exists just picks it - an archived one comes back
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id, tag_archived_at FROM tags WHERE tag_type = $type AND LOWER(tag_name) = LOWER('$name_sql') LIMIT 1"));
        if ($row) {
            $tag_id = intval($row['tag_id']);
            if ($row['tag_archived_at']) {
                tagRestore($tag_id);
                logAudit("Tag", "Restore", "$session_name restored {$config['label']} tag $name_sql", 0, $tag_id);
            }
        } else {
            $tag_id = tagCreate(['name' => $name, 'description' => '', 'type' => $type, 'color' => tagNextColor($type), 'icon' => 'tag']);
            logAudit("Tag", "Create", "$session_name created {$config['label']} tag $name_sql", 0, $tag_id);
        }
        $on = true;

    } else {
        $tag_id = intval($_POST['tag_id'] ?? 0);
        $on = !empty($_POST['on']);
    }

    // The tag has to be one of this kind of record's tags
    $tag = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_name, tag_archived_at FROM tags WHERE tag_id = $tag_id AND tag_type = $type"));
    if (!$tag) {
        http_response_code(404);
        echo json_encode(['error' => 'That tag no longer exists.']);
        exit();
    }
    // An archived tag can come off records, not go on more
    if ($on && $tag['tag_archived_at']) {
        http_response_code(409);
        echo json_encode(['error' => 'That tag is archived.']);
        exit();
    }
    $tag_name = escapeSql($tag['tag_name']);

    $changed = 0;
    foreach ($records as $record_id => $record) {
        $has = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM $link WHERE $link_record = $record_id AND $link_tag = $tag_id")) > 0;
        $record_name = escapeSql($record['name']);
        if ($on && !$has) {
            mysqli_query($mysqli, "INSERT INTO $link SET $link_record = $record_id, $link_tag = $tag_id");
            logAudit($config['log'], "Edit", "$session_name added tag $tag_name to $record_name", $record['client_id'], $record_id);
            if ($entity === 'ticket') {
                logTicketHistory($record_id, "$session_name added tag $tag_name");
            }
            $changed++;
        } elseif (!$on && $has) {
            mysqli_query($mysqli, "DELETE FROM $link WHERE $link_record = $record_id AND $link_tag = $tag_id");
            logAudit($config['log'], "Edit", "$session_name removed tag $tag_name from $record_name", $record['client_id'], $record_id);
            if ($entity === 'ticket') {
                logTicketHistory($record_id, "$session_name removed tag $tag_name");
            }
            $changed++;
        }
    }

    $filter = (string) ($_POST['filter'] ?? '');
    $pills = [];
    foreach (tagsForRecords($entity, array_keys($records)) as $record_id => $tags) {
        $pills[$record_id] = tagPills($entity, $record_id, $tags, $filter);
    }

    echo json_encode(['tag_id' => $tag_id, 'changed' => $changed, 'pills' => $pills]);
    exit();
}

/*
 * Quick pickers (js/quick_pick.js) - a ticket's status, priority, assignee
 * and billable, a project's manager, a recurring ticket's assignee, a
 * service's importance and an asset's status, location and contact, changed in place instead of through a modal. GET lists the choices; POST makes the change through the same
 * functions the post.php handlers use (functions/ticket_fields.php) and sends
 * back the redrawn values.
 */
if (isset($_GET['quick_pick_options'])) {

    header('Content-Type: application/json');
    // A contact's location is the clients module's; everything else here is support's
    enforceUserPermission(($_GET['field'] ?? '') === 'contact_location' ? 'module_client' : 'module_support', 2);

    $field = (string) ($_GET['field'] ?? '');
    $options = [];

    if ($field === 'priority') {
        $colors = ['Urgent' => 'var(--bs-emphasis-color)', 'High' => 'var(--bs-danger)', 'Medium' => 'var(--bs-warning)', 'Low' => 'var(--bs-info)'];
        foreach (TICKET_PRIORITIES as $priority) {
            $options[] = ['value' => $priority, 'label' => $priority, 'color' => $colors[$priority]];
        }
    } elseif ($field === 'status') {
        foreach (ticketPickableStatuses() as $status_id => $status) {
            $options[] = ['value' => (string) $status_id, 'label' => $status['ticket_status_name'], 'color' => preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,24})$/i', (string) $status['ticket_status_color']) ? $status['ticket_status_color'] : ''];
        }
    } elseif ($field === 'billable') {
        $options[] = ['value' => '1', 'label' => 'Billable', 'icon' => 'fas fa-dollar-sign', 'description' => 'Shows under Unbilled Time once done, until invoiced'];
        $options[] = ['value' => '0', 'label' => 'Not billable', 'icon' => 'fas fa-minus'];
    } elseif ($field === 'assignee' || $field === 'recurring_assignee') {
        $options = quickPickAgents();
    } elseif ($field === 'project_manager') {
        $options = quickPickAgents('No manager');
    } elseif ($field === 'service_importance') {
        $colors = ['High' => 'var(--bs-danger)', 'Medium' => 'var(--bs-warning)', 'Low' => 'var(--bs-secondary)'];
        $descriptions = ['High' => 'Down means the client cannot work', 'Medium' => 'Down hurts, but there is a way round it', 'Low' => 'Down is an inconvenience'];
        foreach (SERVICE_IMPORTANCE as $importance) {
            $options[] = ['value' => $importance, 'label' => $importance, 'color' => $colors[$importance], 'description' => $descriptions[$importance]];
        }
    } elseif ($field === 'network_type') {
        foreach (NETWORK_TYPES as $type => [$icon, $tone, $hint]) {
            $options[] = ['value' => $type, 'label' => $type, 'icon' => "fas $icon", 'color' => networkToneColor($tone), 'description' => $hint];
        }
    } elseif (in_array($field, ASSET_QUICK_FIELDS, true) || $field === 'contact_location') {
        // Locations and contacts are the client's own - functions/asset.php
        $options = assetQuickOptions($field === 'contact_location' ? 'asset_location' : $field, intval($_GET['scope'] ?? 0));
        if ($options === null) {
            http_response_code(403);
            echo json_encode(['error' => 'You cannot see that client.']);
            exit();
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown field']);
        exit();
    }

    echo json_encode(['options' => $options]);
    exit();
}

if (isset($_POST['quick_pick_set'])) {

    validateCSRFToken();

    header('Content-Type: application/json');

    $field = (string) ($_POST['field'] ?? '');
    $value = (string) ($_POST['value'] ?? '');
    $ticket_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));

    // An asset's status, location or contact - assets of clients this agent
    // can reach, with the same permission as Edit Asset (functions/asset.php)
    if (in_array($field, ASSET_QUICK_FIELDS, true)) {
        $asset_ids = $ticket_ids;
        $allowed = lookupUserPermission('module_support') >= 2 && $asset_ids;
        foreach ($allowed ? $asset_ids : [] as $id) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_client_id FROM assets WHERE asset_id = $id"));
            if (!$row || !hasClientAccess($row['asset_client_id'])) {
                $allowed = false;
                break;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'You cannot change that on these assets.']);
            exit();
        }

        $message = '';
        $response = ['records' => []];
        foreach ($asset_ids as $id) {
            [$ok, $message] = assetQuickSet($id, $field, $value);
            if (!$ok) {
                http_response_code(422);
                echo json_encode(['error' => $message]);
                exit();
            }
            $response['records'][$id] = assetQuickRecord($id, $field);
        }
        $response['message'] = count($asset_ids) === 1 ? $message : 'Updated ' . count($asset_ids) . ' assets';
        echo json_encode($response);
        exit();
    }

    // A contact's location - contacts of clients this agent can reach, with
    // the same permission as Edit Contact (functions/contact.php)
    if ($field === 'contact_location') {
        $contact_ids = $ticket_ids;
        $allowed = lookupUserPermission('module_client') >= 2 && $contact_ids;
        foreach ($allowed ? $contact_ids : [] as $id) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_client_id FROM contacts WHERE contact_id = $id"));
            if (!$row || !hasClientAccess($row['contact_client_id'])) {
                $allowed = false;
                break;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'You cannot change that on these contacts.']);
            exit();
        }

        $message = '';
        $response = ['records' => []];
        foreach ($contact_ids as $id) {
            [$ok, $message] = contactQuickSetLocation($id, $value);
            if (!$ok) {
                http_response_code(422);
                echo json_encode(['error' => $message]);
                exit();
            }
            $response['records'][$id] = contactQuickRecord($id);
        }
        $response['message'] = count($contact_ids) === 1 ? $message : 'Updated ' . count($contact_ids) . ' contacts';
        echo json_encode($response);
        exit();
    }

    // A service's importance - services of clients this agent can reach, not archived, like Edit Service
    // A network's type - networks of clients this agent can reach, like Edit Network
    if ($field === 'network_type') {
        $allowed = networkCan(2) && $ticket_ids && isset(NETWORK_TYPES[$value]);
        $rows = [];
        foreach ($allowed ? $ticket_ids : [] as $id) {
            $row = networkRow($id);
            if (!$row || !hasClientAccess($row['network_client_id'])) {
                $allowed = false;
                break;
            }
            $rows[$id] = $row;
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'You cannot change that on these networks.']);
            exit();
        }

        $response = ['records' => []];
        foreach ($rows as $id => $row) {
            mysqli_query($mysqli, "UPDATE networks SET network_type = '$value' WHERE network_id = $id");
            if ($row['network_type'] !== $value) {
                logAudit("Network", "Edit", "$session_name changed the type of network " . escapeSql($row['network_name']) . " from " . escapeSql(networkType($row)) . " to $value", intval($row['network_client_id']), $id);
            }
            $html = networkTypeLabel($value);
            $response['records'][$id] = ['current' => $value, 'html' => ['list' => $html, 'detail' => $html]];
        }
        $response['message'] = count($rows) === 1 ? 'Type set to ' . $value : 'Updated ' . count($rows) . ' networks';
        echo json_encode($response);
        exit();
    }

    if ($field === 'service_importance') {
        $service_ids = $ticket_ids;
        $allowed = serviceCan(2) && $service_ids && in_array($value, SERVICE_IMPORTANCE, true);
        $rows = [];
        foreach ($allowed ? $service_ids : [] as $id) {
            $row = serviceRow($id);
            if (!$row || $row['service_archived_at'] || !hasClientAccess($row['service_client_id'])) {
                $allowed = false;
                break;
            }
            $rows[$id] = $row;
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'You cannot change that on these services.']);
            exit();
        }

        $response = ['records' => []];
        foreach ($rows as $id => $row) {
            mysqli_query($mysqli, "UPDATE services SET service_importance = '$value' WHERE service_id = $id");
            if ($row['service_importance'] !== $value) {
                logAudit("Service", "Edit", "$session_name changed the importance of service " . escapeSql($row['service_name']) . " from {$row['service_importance']} to $value", intval($row['service_client_id']), $id);
            }
            $html = serviceImportancePill($value);
            $response['records'][$id] = ['current' => $value, 'html' => ['list' => $html, 'detail' => $html]];
        }
        $response['message'] = count($rows) === 1 ? 'Importance set to ' . $value : 'Updated ' . count($rows) . ' services';
        echo json_encode($response);
        exit();
    }

    // A project's manager or a recurring ticket's assignee - open projects
    // and recurring tickets of clients this agent can reach, like their edit modals
    if ($field === 'project_manager' || $field === 'recurring_assignee') {
        $is_project = $field === 'project_manager';
        $allowed = lookupUserPermission('module_support') >= 2 && $ticket_ids;
        foreach ($allowed ? $ticket_ids : [] as $id) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, $is_project
                ? "SELECT project_client_id AS client_id, project_completed_at IS NULL AND project_archived_at IS NULL AS is_open FROM projects WHERE project_id = $id"
                : "SELECT recurring_ticket_client_id AS client_id, 1 AS is_open FROM recurring_tickets WHERE recurring_ticket_id = $id"
            ));
            if (!$row || !$row['is_open'] || (intval($row['client_id']) && !hasClientAccess($row['client_id']))) {
                $allowed = false;
                break;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'You cannot change that here.']);
            exit();
        }

        $response = ['records' => []];
        foreach ($ticket_ids as $id) {
            [$ok, $message] = $is_project ? projectChangeManager($id, intval($value)) : recurringTicketChangeAssignee($id, intval($value));
            if (!$ok) {
                http_response_code(422);
                echo json_encode(['error' => $message]);
                exit();
            }
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, $is_project
                ? "SELECT project_manager AS user_id, user_name, user_avatar FROM projects LEFT JOIN users ON user_id = project_manager WHERE project_id = $id"
                : "SELECT recurring_ticket_assigned_to AS user_id, user_name, user_avatar FROM recurring_tickets LEFT JOIN users ON user_id = recurring_ticket_assigned_to WHERE recurring_ticket_id = $id"
            ));
            $html = formatAssignee($row['user_id'], $row['user_name'], $row['user_avatar'], false, $is_project ? 'No manager' : 'Unassigned');
            $response['records'][$id] = ['current' => (string) intval($row['user_id']), 'html' => ['list' => $html, 'detail' => $html]];
        }
        $response['message'] = $message;
        echo json_encode($response);
        exit();
    }

    $allowed = lookupUserPermission('module_support') >= 2
        && in_array($field, ['priority', 'assignee', 'billable', 'status'], true)
        && ($field !== 'billable' || lookupUserPermission('module_sales') >= 2)
        && $ticket_ids;

    // Every ticket has to be one this agent can reach, and still open
    // for priority and assignee - the same tickets the pickers are shown on
    if ($allowed) {
        foreach ($ticket_ids as $ticket_id) {
            $row = ticketFieldsRow($ticket_id);
            if (!$row || (intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id']))
                || ($field !== 'billable' && $row['ticket_closed_at'])) {
                $allowed = false;
                break;
            }
        }
    }
    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot change that on these tickets.']);
        exit();
    }

    $message = '';
    $response = ['records' => []];
    foreach ($ticket_ids as $ticket_id) {
        if ($field === 'priority') {
            [$ok, $message] = ticketChangePriority($ticket_id, $value);
        } elseif ($field === 'assignee') {
            [$ok, $message] = ticketChangeAssignee($ticket_id, intval($value));
        } elseif ($field === 'status') {
            [$ok, $message] = ticketChangeStatus($ticket_id, intval($value));
        } else {
            [$ok, $message] = ticketChangeBillable($ticket_id, intval($value));
        }
        if (!$ok) {
            http_response_code(422);
            echo json_encode(['error' => strip_tags($message)]);
            exit();
        }

        // Redraw what changed, in both looks, plus the status (assigning a New ticket opens it)
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT ticket_priority, ticket_assigned_to, ticket_billable, ticket_closed_at, ticket_resolved_at, ticket_status,
                ticket_status_name, ticket_status_color, user_name, user_avatar
            FROM tickets
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
            LEFT JOIN users ON user_id = ticket_assigned_to
            WHERE ticket_id = $ticket_id"
        ));
        $is_open = empty($row['ticket_resolved_at']) && empty($row['ticket_closed_at']);
        if ($field === 'priority') {
            $current = $row['ticket_priority'];
            $html = ['list' => ticketPriorityHtml($current, 'list'), 'detail' => ticketPriorityHtml($current, 'detail')];
        } elseif ($field === 'assignee') {
            $current = (string) intval($row['ticket_assigned_to']);
            $assignee = formatAssignee($row['ticket_assigned_to'], $row['user_name'], $row['user_avatar'], $is_open);
            $html = ['list' => $assignee, 'detail' => $assignee];
        } elseif ($field === 'status') {
            $current = (string) intval($row['ticket_status']);
            $html = ['list' => ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'list'), 'detail' => ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail')];
        } else {
            $current = (string) intval($row['ticket_billable']);
            $html = ['list' => ticketBillableHtml($current, 'list'), 'detail' => ticketBillableHtml($current, 'detail')];
        }
        $response['records'][$ticket_id] = [
            'current' => $current,
            'html' => $html,
            'status_id' => intval($row['ticket_status']),
            'status' => ['list' => ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'list'), 'detail' => ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail')]
        ];
    }

    $response['message'] = count($ticket_ids) === 1 ? strip_tags($message) : 'Updated ' . count($ticket_ids) . ' tickets';
    echo json_encode($response);
    exit();
}

if (isset($_POST['client_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);
    $notes = escapeSql($_POST['notes']);

    enforceClientAccess();

    // Update notes
    mysqli_query($mysqli, "UPDATE clients SET client_notes = '$notes' WHERE client_id = $client_id");

    // Logging
    logAudit("Client", "Edit", "$session_name edited client notes", $client_id);

}

/*
 * Inline notes for sales documents.
 *
 * These replace the invoice/quote/recurring-invoice note modals, which posted
 * to post.php and reloaded the page. Same permission, client-access and audit
 * behaviour as those handlers had - only the delivery changed.
 */

if (isset($_POST['invoice_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $note = escapeSql($_POST['note']);

    $sql = mysqli_query($mysqli, "SELECT invoice_client_id, invoice_number, invoice_prefix FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "UPDATE invoices SET invoice_note = '$note' WHERE invoice_id = $invoice_id");

    logAudit("Invoice", "Edit", "$session_name edited notes on invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

}

if (isset($_POST['quote_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);
    $note = escapeSql($_POST['note']);

    $sql = mysqli_query($mysqli, "SELECT quote_client_id, quote_number, quote_prefix FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "UPDATE quotes SET quote_note = '$note' WHERE quote_id = $quote_id");

    logAudit("Quote", "Edit", "$session_name edited notes on quote $quote_prefix$quote_number", $client_id, $quote_id);

}

if (isset($_POST['product_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    // A product's internal notes, from its page
    $product_id = intval($_POST['product_id'] ?? 0);
    $product = productRow($product_id);
    if (!$product) {
        http_response_code(404);
        exit;
    }
    productSave(['notes' => (string) ($_POST['note'] ?? '')], $product_id);

    logAudit("Product", "Edit", "$session_name edited notes on product " . escapeSql($product['product_name']), 0, $product_id);

}

if (isset($_POST['recurring_invoice_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $note = escapeSql($_POST['note']);

    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_client_id FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $recurring_invoice_prefix = escapeSql($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_note = '$note' WHERE recurring_invoice_id = $recurring_invoice_id");

    logAudit("Recurring Invoice", "Edit", "$session_name edited notes on recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_invoice_id);

}

if (isset($_POST['contact_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_POST['contact_id']);
    $notes = escapeSql($_POST['notes']);

    // Get Contact Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id
        FROM contacts WHERE contact_id = $contact_id"
    );
    $row = mysqli_fetch_assoc($sql);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    enforceClientAccess();

    // Update notes
    mysqli_query($mysqli, "UPDATE contacts SET contact_notes = '$notes' WHERE contact_id = $contact_id");

    // Logging
    logAudit("Contact", "Edit", "$session_name edited contact notes for $contact_name", $client_id, $contact_id);

}

if (isset($_POST['asset_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_POST['asset_id']);
    $notes = escapeSql($_POST['notes']);

    // Get Asset Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id
        FROM assets WHERE asset_id = $asset_id"
    );
    $row = mysqli_fetch_assoc($sql);
    $asset_name = escapeSql($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    enforceClientAccess();

    // Update notes
    mysqli_query($mysqli, "UPDATE assets SET asset_notes = '$notes' WHERE asset_id = $asset_id");

    // Logging
    logAudit("Asset", "Edit", "$session_name edited asset notes for $asset_name", $client_id, $asset_id);

}

/*
 * Location notes (access, parking, alarm) - the location page and quick look save on blur
 */
if (isset($_POST['location_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $row = locationRow($_POST['location_id'] ?? 0);
    if (!$row) {
        exit;
    }
    $client_id = intval($row['location_client_id']);

    enforceClientAccess();

    $location_id = intval($row['location_id']);
    $notes = escapeSql($_POST['notes'] ?? '');
    mysqli_query($mysqli, "UPDATE locations SET location_notes = '$notes' WHERE location_id = $location_id");

    logAudit("Location", "Edit", "$session_name edited the notes on location " . escapeSql($row['location_name']), $client_id, $location_id);

}

/*
 * Vendor notes (escalation path, who is authorised to call) - the vendor page saves on blur
 */
if (isset($_POST['vendor_set_notes'])) {

    validateCSRFToken();

    $row = vendorRow($_POST['vendor_id'] ?? 0);
    if (!$row) {
        exit;
    }
    $client_id = intval($row['vendor_client_id']);

    vendorEnforce($client_id, 2);

    $vendor_id = intval($row['vendor_id']);
    $notes = escapeSql($_POST['notes'] ?? '');
    mysqli_query($mysqli, "UPDATE vendors SET vendor_notes = '$notes' WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Edit", "$session_name edited the notes on vendor " . escapeSql($row['vendor_name']), $client_id, $vendor_id);

}

/*
 * License quick notes - the license page and quick look save on blur
 */
if (isset($_POST['software_set_notes'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $software_id = intval($_POST['software_id'] ?? 0);
    $row = softwareRow($software_id);
    if (!$row) {
        exit;
    }
    $client_id = intval($row['software_client_id']);

    enforceClientAccess();

    $notes = escapeSql($_POST['notes'] ?? '');
    mysqli_query($mysqli, "UPDATE software SET software_notes = '$notes' WHERE software_id = $software_id");

    logAudit("Software", "Edit", "$session_name edited the notes on license " . escapeSql($row['software_name']), $client_id, $software_id);

}

/*
 * Ticketing Collision Detection/Avoidance
 * Called upon loading a ticket, and every 2 mins thereafter
 * Is used in conjunction with ticket_query_views to show who is currently viewing a ticket
 */
if (isset($_GET['ticket_add_view'])) {
    $ticket_id = intval($_GET['ticket_id'] ?? 0);
    $view_ticket = ticketRow($ticket_id);

    // Only a ticket this agent may open - the list of who is looking is no one else's business
    if (lookupUserPermission('module_support') >= 1 && $view_ticket && (!intval($view_ticket['ticket_client_id']) || hasClientAccess($view_ticket['ticket_client_id']))) {
        mysqli_query($mysqli, "INSERT INTO ticket_views SET view_ticket_id = $ticket_id, view_user_id = $session_user_id, view_timestamp = NOW()");
    }
}

/*
 * Ticketing Collision Detection/Avoidance
 * Returns formatted text of the agents currently viewing a ticket
 * Called upon loading a ticket, and every 2 mins thereafter
 */
if (isset($_GET['ticket_query_views'])) {
    $ticket_id = intval($_GET['ticket_id'] ?? 0);
    $view_ticket = ticketRow($ticket_id);
    $users = [];

    if (lookupUserPermission('module_support') >= 1 && $view_ticket && (!intval($view_ticket['ticket_client_id']) || hasClientAccess($view_ticket['ticket_client_id']))) {
        $query = mysqli_query($mysqli, "SELECT user_name FROM ticket_views LEFT JOIN users ON view_user_id = user_id WHERE view_ticket_id = $ticket_id AND view_user_id != $session_user_id AND view_timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        while ($row = mysqli_fetch_assoc($query)) {
            $users[] = $row['user_name'];
        }
    }

    $users = array_values(array_unique($users));
    $response = ['message' => ''];
    if (count($users) > 1) {
        $response['message'] = "<i class='fas fa-fw fa-eye me-2'></i>" . escapeHtml(implode(", ", $users) . " are viewing this ticket.");
    } elseif ($users) {
        $response['message'] = "<i class='fas fa-fw fa-eye me-2'></i>" . escapeHtml($users[0] . " is viewing this ticket.");
    }

    echo json_encode($response);
}

/*
 * Make a shared link (agent/modals/share/share_add.php, functions/share.php)
 * - a login, a document, a file, a secure note, or a request for a login.
 * The link comes back once, with a QR code of it; its secret is not kept.
 */
if (isset($_POST['share_create'])) {

    validateCSRFToken();

    header('Content-Type: application/json');

    $type = (string) ($_POST['type'] ?? '');
    if (!shareCanMake($type)) {
        exit(json_encode(['error' => "You don't have access to share that."]));
    }
    $client_id = intval($_POST['client_id'] ?? 0);
    if (!$client_id || !hasClientAccess($client_id)) {
        exit(json_encode(['error' => "You don't have access to that client."]));
    }

    $result = shareCreate([
        'client_id' => $client_id,
        'type' => $type,
        'related_id' => intval($_POST['id'] ?? 0),
        'name' => (string) ($_POST['name'] ?? ''),
        'text' => (string) ($_POST['text'] ?? ''),
        'recipient' => (string) ($_POST['recipient'] ?? ''),
        'send' => !empty($_POST['send']),
        'expires' => intval($_POST['expires'] ?? 0),
        'views' => intval($_POST['views'] ?? 1),
        'protect' => (string) ($_POST['protect'] ?? ''),
        'passphrase' => (string) ($_POST['passphrase'] ?? ''),
        'otp' => !empty($_POST['otp']),
        'notify' => !empty($_POST['notify']),
        'note' => (string) ($_POST['note'] ?? ''),
    ], $error);

    if (!$result) {
        exit(json_encode(['error' => $error]));
    }

    // "Works until Oct 17 at 9:00 AM, opens once, needs a passphrase, emailed to bob@example.com"
    $summary = ['Works until ' . date('M j \a\t g:i A', strtotime($result['expire_at']))];
    if ($type !== 'Request') {
        $summary[] = $result['views'] ? 'opens ' . strtolower(shareViewOptions()[$result['views']]) : 'opens any number of times';
    }
    if ($result['protect'] === 'passphrase') {
        $summary[] = 'needs the passphrase';
    } elseif ($result['protect'] === 'code') {
        $summary[] = 'needs a code emailed to ' . $result['recipient'];
    }
    if ($result['emailed']) {
        $summary[] = 'emailed to ' . $result['recipient'];
    }

    echo json_encode([
        'url' => $result['url'],
        'passphrase' => $result['passphrase'],
        'summary' => implode(', ', $summary),
        'qr' => shareQrSvg($result['url']),
    ]);
    exit;
}

/*
 * Returns sorted list of active clients
 */
/*
 * Every active client this agent can open, most recently opened first - the
 * client switcher in the client side nav (js/client_switcher.js) loads it once
 * and filters it as you type. Names are raw; the script renders them as text.
 */
if (isset($_GET['get_active_clients'])) {
    enforceUserPermission('module_client');

    $client_sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, client_abbreviation FROM clients
        WHERE client_archived_at IS NULL
        " . clientScopeSql('clients.client_id') . "
        ORDER BY client_accessed_at DESC"
    );

    $response = ['clients' => []];
    while ($row = mysqli_fetch_assoc($client_sql)) {
        $response['clients'][] = [
            'client_id' => intval($row['client_id']),
            'client_name' => $row['client_name'],
            'client_initials' => clientInitials($row['client_abbreviation'], $row['client_name']),
            'client_tone' => clientInitialsTone($row['client_id']),
        ];
    }

    echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
}

/*
 * The bell panel in the top bar (js/notifications.js).
 *
 * notifications_feed  - the latest notifications for the panel, and the unread count
 * notifications_count - just the count, polled for the badge
 * notifications_mark  - POST: mark ids read or unread, or all=1 for everything
 *
 * All of it goes through functions/notifications.php, which only ever reads or
 * changes the current user's own rows. Reading one is not audited; marking all
 * read is, as the old "Dismiss all" was.
 */
if (isset($_GET['notifications_feed'])) {
    $notifications_view = ($_GET['view'] ?? '') === 'all' ? 'all' : 'unread';

    header('Content-Type: application/json');
    echo json_encode([
        'unread_count' => notificationUnreadCount(),
        'items' => notificationsFetch($notifications_view, 20),
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (isset($_GET['notifications_count'])) {
    header('Content-Type: application/json');
    echo json_encode(['unread_count' => notificationUnreadCount()]);
    exit;
}

if (isset($_POST['notifications_mark'])) {

    validateCSRFToken();

    if (!empty($_POST['all'])) {
        $notifications_changed = notificationsMarkAllRead();
        if ($notifications_changed) {
            logAudit("Notification", "Dismiss", "$session_name marked $notifications_changed notifications as read");
        }
    } else {
        notificationsMark($_POST['ids'] ?? [], ($_POST['state'] ?? 'read') !== 'unread');
    }

    header('Content-Type: application/json');
    echo json_encode(['unread_count' => notificationUnreadCount()]);
    exit;
}

/*
 * Live results for the search palette in the top bar (js/global_search.js).
 *
 * Same search as the full results page - functions/search.php - so the two can
 * never disagree about what this user may see. Five per group plus any app page
 * whose name matches; with nothing typed yet, the recently opened clients.
 * Values are raw: the palette renders them as text, never as HTML.
 */
if (isset($_GET['global_search'])) {

    $global_search_query = (string) ($_GET['q'] ?? '');

    $response = [
        'query' => $global_search_query,
        'pages' => [],
        'groups' => [],
        'recent' => [],
    ];

    if (trim($global_search_query) === '') {
        $response['recent'] = globalSearchRecentClients();
    } else {
        $response['pages'] = globalSearchPages($global_search_query);
        $response['groups'] = globalSearch($global_search_query, 5);
    }

    header('Content-Type: application/json');
    echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/*
 * Returns the body of one canned response, for the picker on the ticket reply form.
 * Fetched on demand rather than rendered into every ticket page, because the bodies are
 * full HTML replies and an install can have a lot of them.
 */
if (isset($_GET['get_canned_response'])) {
    enforceUserPermission('module_support');

    $canned_response_id = intval($_GET['get_canned_response']);

    $canned_sql = mysqli_query($mysqli, "SELECT canned_response_body FROM canned_responses
        WHERE canned_response_id = $canned_response_id AND canned_response_archived_at IS NULL LIMIT 1");

    $canned_row = mysqli_fetch_assoc($canned_sql);

    // Purified here rather than at save time, the same way ticket replies and ticket
    // template details are - what lands in the editor is what would have been rendered
    require_once "../libs/htmlpurifier/HTMLPurifier.standalone.php";

    $canned_purifier_config = HTMLPurifier_Config::createDefault();
    $canned_purifier_config->set('Cache.DefinitionImpl', null);
    $canned_purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $canned_purifier = new HTMLPurifier($canned_purifier_config);

    $response = [];
    $response['body'] = $canned_row ? $canned_purifier->purify($canned_row['canned_response_body']) : '';

    echo json_encode($response);

}

/*
 * Returns a document's rendered content for the file previewer on agent/files.php.
 *
 * The previewer shows files by pointing an iframe at file.php, but a document is
 * rows in a table, not bytes on disk. Rather than embedding every document's
 * HTML in the page payload - a folder of long documents would be megabytes of
 * JSON on a page that shows two dozen tiles - it is fetched when the document is
 * actually opened.
 *
 * Purified here rather than at save time, the same way canned responses above
 * and agent/document.php do it.
 */
if (isset($_GET['get_document_content'])) {
    // Whoever can open the files list can preview its documents in the grid
    enforceUserPermission('module_client');

    $document_id = intval($_GET['get_document_content']);

    $document_sql = mysqli_query($mysqli, "SELECT document_client_id, document_content, document_name
        FROM documents WHERE document_id = $document_id LIMIT 1");

    $document_row = mysqli_fetch_assoc($document_sql);

    $response = [];

    if ($document_row) {
        // Scope the fetch to the client the document belongs to - this endpoint
        // takes an id straight from the query string
        $client_id = intval($document_row['document_client_id']);
        enforceClientAccess();

        $response['name'] = $document_row['document_name'];
        $response['content'] = documentHtmlPurify($document_row['document_content']);
    } else {
        $response['name'] = '';
        $response['content'] = '';
    }

    echo json_encode($response);

}

/*
 * Returns ordered list of active contacts for a specified client
 */
if (isset($_GET['get_client_contacts'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    enforceClientAccess();

    $contact_sql = mysqli_query(
        $mysqli,
        "SELECT contact_id, contact_name, contact_title, contact_email, contact_photo, contact_primary, contact_important, contact_technical, contact_location_id FROM contacts
        LEFT JOIN clients on contact_client_id = client_id
        WHERE contacts.contact_archived_at IS NULL AND contact_client_id = $client_id
        " . clientScopeSql('contact_client_id') . "
        ORDER BY contact_primary DESC, contact_technical DESC, contact_important DESC, contact_name"
    );

    // Always return the key, so a client with none gives [] rather than null
    $response['contacts'] = [];

    while ($row = mysqli_fetch_assoc($contact_sql)) {
        // For the picker: initials on their own colour, or the photo when there is one
        $row['contact_initials'] = personInitials($row['contact_name']);
        $row['contact_tone'] = contactAvatarTone($row['contact_id']);
        $row['contact_photo_url'] = $row['contact_photo'] ? "/uploads/clients/$client_id/" . rawurlencode($row['contact_photo']) : '';
        unset($row['contact_photo']);
        $response['contacts'][] = $row;
    }

    echo json_encode($response);
}

/*
 * New Ticket - the other open tickets for the contact or asset picked, so a
 * second ticket for the same problem shows up before it is raised.
 */
if (isset($_GET['ticket_also_open'])) {
    enforceUserPermission('module_support', 2);

    header('Content-Type: application/json');

    $client_id = intval($_GET['client_id'] ?? 0);
    if (!$client_id || !hasClientAccess($client_id)) {
        echo json_encode(['html' => '']);
        exit;
    }
    $also_contact_id = ticketClientOwnedId('contact', $_GET['contact_id'] ?? 0, $client_id);
    $also_asset_id = ticketClientOwnedId('asset', $_GET['asset_id'] ?? 0, $client_id);
    $rows = ticketAlsoOpen($also_contact_id, $also_asset_id, 0, 5);

    $html = '';
    if ($rows) {
        $html = '<div class="alert alert-warning small py-2 mb-3"><div class="fw-semibold mb-1"><i class="fas fa-layer-group me-1"></i>Already open for '
            . ($also_contact_id && $also_asset_id ? 'this contact or asset' : ($also_contact_id ? 'this contact' : 'this asset')) . '</div>';
        foreach ($rows as $row) {
            $html .= '<div class="d-flex align-items-center gap-2 text-truncate">' . ticketLink($row)
                . ' <span class="ms-auto flex-shrink-0">' . ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail') . '</span></div>';
        }
        $html .= '<div class="text-body-secondary mt-1">Add to one of these instead, if it is the same problem.</div></div>';
    }

    echo json_encode(['html' => $html]);
    exit;
}

/*
 * Returns ordered list of active assets for a specified client
 */
if (isset($_GET['get_client_assets'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    enforceClientAccess();

    $asset_sql = mysqli_query(
        $mysqli,
        "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_contact_id, asset_location_id, contact_name FROM assets
        LEFT JOIN clients on asset_client_id = client_id
        LEFT JOIN contacts ON contact_id = asset_contact_id
        WHERE assets.asset_archived_at IS NULL AND asset_client_id = $client_id
        " . clientScopeSql('asset_client_id') . "
        ORDER BY asset_type ASC, asset_favorite DESC, asset_name"
    );

    // Always return the key, so a client with no assets gives [] rather than null
    $response['assets'] = [];

    while ($row = mysqli_fetch_assoc($asset_sql)) {
        $row['asset_icon'] = getAssetIcon($row['asset_type']);
        $response['assets'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns locations for a specified client
 */
if (isset($_GET['get_client_locations'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    enforceClientAccess();

    $locations_sql = mysqli_query(
        $mysqli,
        "SELECT location_id, location_name FROM locations
        LEFT JOIN clients on location_client_id = client_id
        WHERE locations.location_archived_at IS NULL AND location_client_id = $client_id
        " . clientScopeSql('location_client_id') . "
        ORDER BY location_primary DESC, location_name ASC"
    );

    // Always return the key, so a client with none gives [] rather than null
    $response['locations'] = [];

    while ($row = mysqli_fetch_assoc($locations_sql)) {
        $response['locations'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns ordered list of vendors for a specified client
 */
if (isset($_GET['get_client_vendors'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    enforceClientAccess();

    $vendors_sql = mysqli_query(
        $mysqli,
        "SELECT vendor_id, vendor_name, vendor_description FROM vendors
        LEFT JOIN clients on vendor_client_id = client_id
        WHERE vendors.vendor_archived_at IS NULL AND vendor_client_id = $client_id
        " . clientScopeSql('vendor_client_id') . "
        ORDER BY vendor_favorite DESC, vendor_name ASC"
    );

    // Always return the key, so a client with none gives [] rather than null
    $response['vendors'] = [];

    while ($row = mysqli_fetch_assoc($vendors_sql)) {
        // The vendor's tile in the picker, the same as everywhere else
        $row['vendor_initials'] = vendorInitials($row['vendor_name']);
        $row['vendor_tone'] = vendorTone($row['vendor_name']);
        $response['vendors'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns the open projects a ticket of this client can go on - the client's
 * own, then our internal ones
 */
if (isset($_GET['get_client_projects'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    enforceClientAccess();

    $projects_sql = mysqli_query(
        $mysqli,
        "SELECT project_id, project_name, project_prefix, project_number, project_client_id = 0 AS project_internal FROM projects
        WHERE projects.project_archived_at IS NULL AND projects.project_completed_at IS NULL AND project_client_id IN (0, $client_id)
        " . clientScopeSql('project_client_id') . "
        ORDER BY project_client_id = 0, project_name ASC"
    );

    // Always return the key, so a client with none gives [] rather than null
    $response['projects'] = [];

    while ($row = mysqli_fetch_assoc($projects_sql)) {
        $response['projects'][] = $row;
    }

    echo json_encode($response);
}

/*
 * NEW TOTP getter for client login/passwords page
 * When provided with a login ID, checks permissions and returns the 6-digit code
 */
if (isset($_GET['get_totp_token_via_id'])) {
    enforceUserPermission('module_credential');

    $credential_id = intval($_GET['credential_id']);

    $sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credential_name, credential_otp_secret, credential_client_id FROM credentials WHERE credential_id = $credential_id"));
    if (!$sql) {
        echo json_encode('');
        exit;
    }
    $name = escapeSql($sql['credential_name']);
    $totp_secret = $sql['credential_otp_secret'];
    $client_id = intval($sql['credential_client_id']);

    enforceClientAccess();

    // Seeds saved before they were tidied on save may still carry spaces, dashes or the whole otpauth:// link
    $totp_seed = credentialOtpSeed($totp_secret);
    $otp = $totp_seed ? TokenAuth6238::getTokenCode($totp_seed) : '';
    echo json_encode($otp);

    // Logging
    //  Only log the TOTP view if the user hasn't already viewed this specific login entry recently, this prevents logs filling if a user hovers across an entry a few times
    $check_recent_totp_view_logged_sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS recent_totp_view FROM logs WHERE log_type = 'Credential' AND log_action = 'View TOTP' AND log_user_id = $session_user_id AND log_entity_id = $credential_id AND log_client_id = $client_id AND log_created_at > (NOW() - INTERVAL 5 MINUTE)"));
    $recent_totp_view_logged_count = intval($check_recent_totp_view_logged_sql['recent_totp_view']);

    if ($recent_totp_view_logged_count == 0) {
        // Logging
        logAudit("Credential", "View TOTP", "$session_name viewed credential TOTP code for $name", $client_id, $credential_id);

    }
}

if (isset($_GET['get_readable_pass'])) {
    echo json_encode(generateReadablePassword());
}

/*
 * ITFlow - POST request handler for client tickets
 */
/*
 * The ticket board (agent/ticket_kanban.php, js/tickets_kanban.js).
 *
 * Moving a column reorders the statuses - only when the admin allows it.
 * Moving a card changes its status the way the ticket page does: into
 * Resolved resolves it (open tasks first, and the client is told), out of
 * Resolved reopens it, anything else is a status change. New is set when a
 * ticket comes in and Closed by closing it, so neither is a place to drop
 * one. With ticket ordering on, the column's order is kept too.
 */
if (isset($_POST['update_kanban_status_position'])) {

    validateCSRFToken();

    header('Content-Type: application/json');

    if (lookupUserPermission('module_support') < 2 || intval($config_ticket_moving_columns) !== 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Columns cannot be moved here.']);
        exit;
    }

    foreach ((array) ($_POST['positions'] ?? []) as $position) {
        $status_id = intval($position['status_id'] ?? 0);
        $kanban = intval($position['status_kanban'] ?? 0);
        mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_order = $kanban WHERE ticket_status_id = $status_id");
    }

    echo json_encode(['ok' => true]);
    exit;
}

if (isset($_POST['update_kanban_ticket'])) {

    validateCSRFToken();

    header('Content-Type: application/json');

    $kanban_error = function ($message, $code = 422) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    };

    if (lookupUserPermission('module_support') < 2) {
        $kanban_error('You cannot change tickets.', 403);
    }

    $row = ticketRow($_POST['ticket_id'] ?? 0);
    if (!$row || (intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id']))) {
        $kanban_error('That ticket is not there any more - reload the board.', 404);
    }
    $ticket_id = intval($row['ticket_id']);
    $reference = ticketReference($row);
    if (!empty($row['ticket_closed_at'])) {
        $kanban_error("$reference is closed.");
    }

    $status_id = intval($_POST['status_id'] ?? 0);
    $current_status = intval($row['ticket_status']);
    $message = '';

    if ($status_id !== $current_status) {
        $pickable = ticketPickableStatuses();
        if ($status_id === TICKET_STATUS_RESOLVED) {
            $open_tasks = ticketOpenTaskCount($ticket_id);
            if ($open_tasks) {
                $kanban_error("Finish the $open_tasks open task" . ($open_tasks === 1 ? '' : 's') . " on $reference before resolving it.");
            }
            ticketResolve($row, ' from the board');
            if (intval($config_ticket_client_general_notifications) === 1) {
                ticketMailResolved($ticket_id);
            }
            $message = "$reference resolved";
        } elseif (isset($pickable[$status_id])) {
            if (!empty($row['ticket_resolved_at'])) {
                ticketReopen($row, $status_id, ' from the board');
                $message = "$reference reopened";
            } else {
                [$ok, $message] = ticketChangeStatus($ticket_id, $status_id);
                if (!$ok) {
                    $kanban_error(strip_tags($message));
                }
                $message = "$reference: " . strip_tags($message);
            }
        } elseif ($status_id === TICKET_STATUS_NEW) {
            $kanban_error('New is for tickets nobody has picked up yet - a ticket can only move on from there.');
        } else {
            $kanban_error('A ticket cannot be moved to that status.');
        }
    }

    // The column's order, when the admin keeps one. Setting ticket_updated_at to
    // itself stops ON UPDATE moving it - reordering a board is not activity.
    if (intval($config_ticket_ordering) === 1) {
        $scope = clientScopeSql('ticket_client_id');
        foreach (array_values((array) ($_POST['order'] ?? [])) as $position => $order_ticket_id) {
            $order_ticket_id = intval($order_ticket_id);
            mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $position, ticket_updated_at = ticket_updated_at WHERE ticket_id = $order_ticket_id $scope");
        }
    }

    echo json_encode(['ok' => true, 'message' => $message, 'status_id' => $status_id]);
    exit;
}

if (isset($_POST['update_ticket_tasks_order'])) {
    // Update multiple ticket tasks order

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $positions = (array) ($_POST['positions'] ?? []);
    $ticket_id = intval($_POST['ticket_id'] ?? 0);

    // A ticket this agent can reach - one with no client is internal work anyone can
    $task_ticket = ticketRow($ticket_id);
    if (!$task_ticket || (intval($task_ticket['ticket_client_id']) && !hasClientAccess($task_ticket['ticket_client_id']))) {
        http_response_code(403);
        echo json_encode(['status' => 'error']);
        exit;
    }

    foreach ($positions as $position) {
        $id = intval($position['id'] ?? 0);
        $order = intval($position['order'] ?? 0);

        mysqli_query($mysqli, "UPDATE tasks SET task_order = $order WHERE task_ticket_id = $ticket_id AND task_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_task_templates_order'])) {
    // Update multiple task templates order

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];
    $ticket_template_id = intval($_POST['ticket_template_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE task_templates SET task_template_order = $order WHERE task_template_ticket_template_id = $ticket_template_id AND task_template_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_project_template_ticket_order'])) {

    validateCSRFToken();

    // Project templates are the admins' - admin/project_template.php
    if (empty($session_is_admin)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Only an admin can change a project template.']);
        exit;
    }

    $positions = (array) ($_POST['positions'] ?? []);
    $project_template_id = intval($_POST['project_template_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE project_template_ticket_templates SET ticket_template_order = $order WHERE project_template_id = $project_template_id AND ticket_template_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

/*
 * The order of the lines on an invoice, quote or recurring invoice, after a
 * drag - "items" is the line ids top to bottom, comma separated.
 */
if (isset($_POST['billing_items_order'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    header('Content-Type: application/json');

    $type = (string) ($_POST['type'] ?? '');
    $doc = billingType($type) ? billingRow($type, intval($_POST['doc_id'] ?? 0)) : null;
    if (!$doc || !hasClientAccess(intval($doc['client_id'])) || !billingCanEditItems($type, $doc)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => "Those lines can't be moved now."]);
        exit;
    }

    $item_ids = array_filter(array_map('intval', explode(',', (string) ($_POST['items'] ?? ''))));
    billingItemsOrder($type, intval($doc[billingType($type)['col'] . '_id']), $item_ids);

    echo json_encode(['status' => 'success']);
    exit;
}

/*
 * How many a seat line on a recurring invoice would count now - for the Edit
 * Line window as its choices change. source, tag, types[], software_id,
 * count (used or total) and minimum, as the form posts them.
 */
if (isset($_GET['recurring_seat_count'])) {

    enforceUserPermission('module_sales', 2);

    header('Content-Type: application/json');

    $recurring = billingRow('recurring', intval($_GET['recurring_invoice_id'] ?? 0));
    if (!$recurring || !hasClientAccess(intval($recurring['client_id']))) {
        http_response_code(403);
        echo json_encode(['error' => "That recurring invoice can't be opened."]);
        exit;
    }

    $source = (string) ($_GET['source'] ?? '');
    if (!isset(recurringSeatSources()[$source])) {
        echo json_encode(['error' => 'Pick what to count.']);
        exit;
    }
    $filter = recurringSeatFilter($source, ['tag' => $_GET['seat_tag'] ?? 0, 'types' => (array) ($_GET['seat_types'] ?? []),
        'software_id' => $_GET['seat_software_id'] ?? 0, 'count' => $_GET['seat_count'] ?? 'used']);
    $count = recurringSeatCount(intval($recurring['client_id']), $source, $filter);
    if ($count === null) {
        echo json_encode(['error' => "Pick one of the client's licenses."]);
        exit;
    }
    $minimum = billingNumberInput($_GET['seat_minimum'] ?? '0');
    if ($minimum === null || $minimum < 0) {
        echo json_encode(['error' => 'The minimum has to be a number, zero or more.']);
        exit;
    }

    echo json_encode(['count' => $count, 'quantity' => billingQuantity(max($count, $minimum)), 'label' => recurringSeatDescribe($source, $filter)]);
    exit;
}

if (isset($_GET['client_duplicate_check'])) {
    enforceUserPermission('module_client', 2);

    $name = escapeSql($_GET['name']);

    $response['message'] = ""; // default

    if (strlen($name) >= 5) {
        $sql_clients = mysqli_query($mysqli, "SELECT client_name FROM clients
        WHERE client_archived_at IS NULL
        AND client_name LIKE '%$name%'
        ORDER BY client_id DESC LIMIT 1"
        );

        if (mysqli_num_rows($sql_clients) > 0) {
            while ($row = mysqli_fetch_assoc($sql_clients)) {
                $response['message'] = "<i class='fas fa-fw fa-copy me-2'></i> Potential duplicate: <i>" . escapeHtml($row['client_name']) . "</i> already exists.";
            }
        }
    }

    echo json_encode($response);
}

/*
 * Live check behind the IP address field on the add/edit network IP modals.
 *
 * Runs the exact same checkIpForNetwork() the POST handler runs, so what the
 * field says while you type is what the save will do - no second copy of the
 * rules to drift. It's advisory only; the handler still checks on submit.
 */
if (isset($_GET['network_ip_check'])) {
    enforceUserPermission('module_support', 2);

    $network_id = intval($_GET['network_id'] ?? 0);
    $ip_id = intval($_GET['ip_id'] ?? 0);

    $client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

    enforceClientAccess();

    $ip = $_GET['ip'] ?? '';

    $response = ['ok' => false, 'ip' => '', 'message' => ''];

    if (trim($ip) !== '') {

        $ip_error = checkIpForNetwork($ip, $network_id, $ip_id);

        if ($ip_error === '') {
            $response['ok'] = true;
            $response['ip'] = $ip;
            // Worth knowing, not wrong - a device has it already, or DHCP hands it out
            $notes = [];
            $network = networkRow($network_id);
            $device = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name, interface_name FROM asset_interfaces JOIN assets ON asset_id = interface_asset_id
                WHERE interface_network_id = $network_id AND interface_ip = '" . escapeSql($ip) . "' AND interface_archived_at IS NULL AND asset_archived_at IS NULL LIMIT 1"));
            if ($device) {
                $notes[] = 'on ' . escapeHtml($device['asset_name'] . ' ' . $device['interface_name']);
            }
            $pool = networkParseRange($network['network_dhcp_range'] ?? '', networkSubnetInfo($network['network'] ?? ''));
            $int = ipv4ToInt($ip);
            if ($pool && $int !== null && $int >= $pool[0] && $int <= $pool[1]) {
                $notes[] = 'inside the DHCP range - right for a reservation, not a fixed address';
            }
            if ($ip === normalizeIpAddress((string) ($network['network_gateway'] ?? ''))) {
                $notes[] = 'the gateway';
            }
            $response['message'] = "<i class='fas fa-fw fa-check me-2'></i>" . escapeHtml($ip) . ($notes ? ' - ' . implode('; ', $notes) : ' is free');
        } else {
            // checkIpForNetwork() builds its message for flashAlert(), which
            // escapes at render - this lands in innerHTML instead, so it has
            // to be escaped here
            $response['message'] = "<i class='fas fa-fw fa-exclamation-triangle me-2'></i>" . alertMessageHtml($ip_error);
        }

    }

    echo json_encode($response);
}

/*
 * What a subnet typed into New / Edit Network is - its size, mask, first and
 * last host, a gateway and DHCP range to suggest, and the client's networks it
 * overlaps (agent/js/network_form.js).
 */
if (isset($_GET['network_subnet_info'])) {

    header('Content-Type: application/json');
    enforceUserPermission('module_support', 2);

    $client_id = intval($_GET['client_id'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
    }

    $info = networkSubnetInfo((string) ($_GET['cidr'] ?? ''));
    if (!$info) {
        echo json_encode(['ok' => false]);
        exit();
    }

    $response = ['ok' => true, 'cidr' => $info['cidr'], 'family' => $info['family'], 'size' => networkSizeText($info), 'first' => $info['first_host'], 'last' => $info['last_host'], 'gateway' => '', 'range' => '', 'overlaps' => []];
    if ($info['family'] === 4 && $info['prefix'] <= 30) {
        $first = ipv4ToInt($info['first_host']);
        $last = ipv4ToInt($info['last_host']);
        $response['gateway'] = $info['first_host'];
        // Room at the bottom for fixed addresses and some at the top, the middle to DHCP: .100 to .199 of a /24;
        // the second /24 of a /23; the second /24 to the second-last of anything bigger
        $hosts = $last - $first + 1;
        $base = ipv4ToInt($info['network']);
        if ($info['prefix'] === 24) {
            [$start, $end] = [$first + 99, $first + 198];
        } elseif ($info['prefix'] === 23) {
            [$start, $end] = [$base + 257, $base + 510];
        } elseif ($info['prefix'] < 23) {
            [$start, $end] = [$base + 257, $last - 256];
        } elseif ($hosts >= 30) {
            [$start, $end] = [$first + intdiv($hosts * 2, 5), $first + intdiv($hosts * 4, 5)];
        }
        if (isset($start) && $start < $end) {
            $response['range'] = intToIpv4($start) . ' - ' . intToIpv4($end);
        }
    }
    if ($client_id) {
        foreach (networkOverlaps(['network' => $info['cidr'], 'network_client_id' => $client_id, 'network_id' => intval($_GET['network_id'] ?? 0)]) as $overlap) {
            $response['overlaps'][] = $overlap['network_name'] . ' (' . $overlap['network'] . ')' . ($overlap['location_name'] ? ' at ' . $overlap['location_name'] : '');
        }
    }

    echo json_encode($response);
    exit();
}

// The first address on a network nothing has - not documented, on no device, not the gateway, outside the DHCP range
if (isset($_GET['network_next_free_ip'])) {

    header('Content-Type: application/json');
    enforceUserPermission('module_support', 2);

    $network = networkRow($_GET['network_id'] ?? 0);
    if (!$network || !hasClientAccess($network['network_client_id'])) {
        echo json_encode(['ip' => '', 'message' => 'That network no longer exists.']);
        exit();
    }

    $plan = networkAddressPlan($network);
    echo json_encode([
        'ip' => $plan['next_free'],
        'message' => $plan['next_free'] ? '' : ($plan['info'] && $plan['info']['family'] === 4 && $plan['info']['prefix'] >= 20 ? 'Every address outside the DHCP range is taken.' : 'Next free works on IPv4 networks up to a /20.'),
    ]);
    exit();
}

/*
 * The address typed on New / Edit Interface - whether it is one, inside the
 * network picked, and who else has it (agent/js/interface_form.js). The same
 * checks interfaceFormSet() makes on saving.
 */
if (isset($_GET['interface_ip_check'])) {

    header('Content-Type: application/json');
    enforceUserPermission('module_support', 2);

    $ip_typed = trim((string) ($_GET['ip'] ?? ''));
    $network = intval($_GET['network_id'] ?? 0) ? networkRow($_GET['network_id']) : null;
    if ($network && !hasClientAccess($network['network_client_id'])) {
        $network = null;
    }

    $reply = function ($state, $message) {
        echo json_encode(['state' => $state, 'message' => $message]);
        exit();
    };

    if ($ip_typed === '') {
        $reply('', '');
    }
    $ip = normalizeIpAddress($ip_typed);
    if ($ip === false) {
        $reply('error', escapeHtml($ip_typed) . ' is not an IP address');
    }
    if (!$network) {
        $reply('ok', escapeHtml($ip) . ' - pick its network to check it against the others there');
    }
    if (networkSubnetInfo($network['network']) && !isIpInSubnet($ip, $network['network'])) {
        $reply('error', escapeHtml($ip) . ' is outside ' . escapeHtml($network['network_name']) . ' (' . escapeHtml($network['network']) . ')');
    }
    $info = networkSubnetInfo($network['network']);
    if ($info && $info['family'] === 4 && $info['prefix'] <= 30 && ($ip === $info['network'] || $ip === $info['last'])) {
        $reply('error', escapeHtml($ip) . ' is the ' . ($ip === $info['network'] ? 'network' : 'broadcast') . ' address of ' . escapeHtml($network['network']) . ' - no device can use it');
    }
    $clash = interfaceIpClash($ip, $network['network_id'], intval($_GET['interface_id'] ?? 0), intval($_GET['asset_id'] ?? 0));
    if ($clash) {
        $reply('warning', escapeHtml($ip) . ' is on ' . escapeHtml($clash) . ' too');
    }
    $documented = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ip_type, ip_hostname, ip_mac FROM network_ips WHERE ip_network_id = " . intval($network['network_id']) . " AND ip_address = '" . escapeSql($ip) . "' LIMIT 1"));
    $mac = normalizeMacAddress($_GET['mac'] ?? '');
    if ($documented && $documented['ip_type'] === 'Reservation' && $mac && normalizeMacAddress($documented['ip_mac']) && normalizeMacAddress($documented['ip_mac']) !== $mac) {
        $reply('warning', escapeHtml($ip) . ' is reserved for ' . escapeHtml($documented['ip_mac']) . ($documented['ip_hostname'] ? ' (' . escapeHtml($documented['ip_hostname']) . ')' : ''));
    }
    if ($documented && $documented['ip_type'] === 'Reserved') {
        $reply('warning', escapeHtml($ip) . ' is held back' . ($documented['ip_hostname'] ? ' for ' . escapeHtml($documented['ip_hostname']) : '') . ' - not to be used');
    }
    $pool = networkParseRange($network['network_dhcp_range'], $info);
    $int = ipv4ToInt($ip);
    if ($pool && $int !== null && $int >= $pool[0] && $int <= $pool[1]) {
        $reply('warning', escapeHtml($ip) . ' is inside the DHCP range (' . escapeHtml($network['network_dhcp_range']) . ') - fixed there, DHCP can hand it to something else');
    }
    if ($ip === normalizeIpAddress((string) $network['network_gateway'])) {
        $reply('warning', escapeHtml($ip) . ' is the gateway of ' . escapeHtml($network['network_name']));
    }
    $reply('ok', escapeHtml($ip) . ($documented ? ' - documented' . ($documented['ip_hostname'] ? ' as ' . escapeHtml($documented['ip_hostname']) : '') : ' is free on ' . escapeHtml($network['network_name'])));
}

if (isset($_GET['contact_email_check'])) {
    enforceUserPermission('module_client', 2);

    $email = escapeSql($_GET['email']);
    $domain = escapeSql(substr($_GET['email'], strpos($_GET['email'], '@') + 1));

    $response['message'] = ""; // default

    if (strlen($email) >= 3) {

        // 1. Duplicate check
        $sql_contacts = mysqli_query($mysqli, "SELECT contact_email FROM contacts WHERE contact_email = '$email' LIMIT 1");
        if (mysqli_num_rows($sql_contacts) > 0) {
            while ($row = mysqli_fetch_assoc($sql_contacts)) {
                $response['message'] = "<i class='fas fa-fw fa-copy me-2'></i> Potential duplicate: <i>" . escapeHtml($row['contact_email']) . "</i> already exists.";
            }
        }

        // 2. MX record check
        if (!checkdnsrr($domain, 'MX')) {
            $response['message'] = "<i class='fas fa-fw fa-exclamation-triangle me-2'></i> E-mail domain invalid.";
        }

    }

    echo json_encode($response);
}

if (isset($_GET['ai_reword'])) {

    header('Content-Type: application/json');

    // The reword button sits on every TinyMCE instance, so the ticket editor asks for
    // the Tickets model and everything else gets General. Anything unrecognised is
    // treated as General rather than trusted into the query.
    $use_case = ($_GET['use_case'] ?? '') === 'Tickets' ? 'Tickets' : 'General';

    $model = getAiModel($use_case);

    if (!$model) {
        echo json_encode(['error' => aiModelMissingError($use_case)]);
        exit;
    }

    // Collecting the input data from the AJAX request.
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE); // Convert JSON into array.

    $userText = $input['text'] ?? '';

    $result = callAiApi($model, [
        ["role" => "system", "content" => $model['ai_model_prompt']],
        ["role" => "user", "content" => $userText],
    ]);

    // Report failures as an error, never as reworded text - the editor writes
    // rewordedText straight back over the user's content
    if (!$result['ok']) {
        echo json_encode(['error' => $result['error']]);
        exit;
    }

    $content = $result['content'];

    // Clean any leading "html" word or other unwanted text at the beginning.
    $content = preg_replace('/^html/i', '', $content);  // Remove any occurrence of 'html' at the start

    // Clean the response content to remove backticks or code block markers.
    $cleanedContent = str_replace('```', '', $content); // Remove backticks if they exist.

    // Trim any leading/trailing whitespace.
    $cleanedContent = trim($cleanedContent);

    echo json_encode(['rewordedText' => $cleanedContent]);

}

if (isset($_GET['ai_create_document_template'])) {

    enforceUserPermission('module_support');

    header('Content-Type: text/html; charset=UTF-8');

    $prompt = $_POST['prompt'] ?? '';

    // Basic validation
    if (empty($prompt)) {
        echo "No prompt provided.";
        exit;
    }

    $model = getAiModel('Documentation');

    if (!$model) {
        echo escapeHtml(aiModelMissingError('Documentation'));
        exit;
    }

    // Prepare prompt
    $system_message = "You are a helpful IT documentation assistant. You will create a well-structured HTML template for IT documentation based on a given prompt. Include headings, subheadings, bullet points, and possibly tables for clarity. No Lorem Ipsum, use realistic placeholders and professional language.";
    $user_message = "Create an HTML formatted IT documentation template based on the following request:\n\n\"$prompt\"\n\nThe template should be structured, professional, and useful for IT staff. Include relevant sections, instructions, prerequisites, and best practices.";

    $result = callAiApi($model, [
        ["role" => "system", "content" => $system_message],
        ["role" => "user", "content" => $user_message]
    ]);

    if (!$result['ok']) {
        echo "<p>" . escapeHtml($result['error']) . "</p>";
        exit;
    }

    // Print the generated HTML template directly
    echo $result['content'];
}

if (isset($_GET['ai_ticket_summary'])) {

    enforceUserPermission('module_support');

    header('Content-Type: text/html; charset=UTF-8');

    $model = getAiModel('Tickets');

    if (!$model) {
        echo escapeHtml(aiModelMissingError('Tickets'));
        exit;
    }

    // Retrieve the ticket_id from POST
    $ticket_id = intval($_POST['ticket_id']);

    // Query the database for ticket details
    $sql = mysqli_query($mysqli, "
        SELECT ticket_subject, ticket_details, ticket_source, ticket_priority, ticket_status_name, category_name, ticket_client_id
        FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN categories ON ticket_category = category_id
        WHERE ticket_id = $ticket_id
        LIMIT 1
    ");
    $row = mysqli_fetch_assoc($sql);
    if (!$row) {
        echo 'That ticket no longer exists.';
        exit;
    }
    $ticket_subject = $row['ticket_subject'];
    $ticket_details = strip_tags($row['ticket_details']); // strip HTML for cleaner prompt
    $ticket_status = $row['ticket_status_name'];
    $ticket_category = $row['category_name'];
    $ticket_source = $row['ticket_source'];
    $ticket_priority = $row['ticket_priority'];
    $client_id = intval($row['ticket_client_id']);

    // A ticket with no client is internal work any agent may read
    if ($client_id) {
        enforceClientAccess();
    }

    // Get ticket replies
    $sql_replies = mysqli_query($mysqli, "
        SELECT ticket_reply, ticket_reply_type, user_name
        FROM ticket_replies
        LEFT JOIN users ON ticket_reply_by = user_id
        WHERE ticket_reply_ticket_id = $ticket_id
        AND ticket_reply_archived_at IS NULL
        ORDER BY ticket_reply_id ASC
    ");

    $all_replies_text = "";
    while ($reply = mysqli_fetch_assoc($sql_replies)) {
        $reply_type = $reply['ticket_reply_type'];
        $reply_text = strip_tags($reply['ticket_reply']);
        $reply_by = $reply['user_name'];
        $all_replies_text .= "\nReply Type: $reply_type Reply By: $reply_by: Reply Text: $reply_text";
    }

    $prompt = "
    Summarize the following IT support ticket and its responses in a concise, clear, and professional manner.
    The summary should include:

    1. Main Issue: What was the problem reported by the user?
    2. Actions Taken: What steps were taken to address the issue?
    3. Resolution or Next Steps: Was the issue resolved or is it ongoing?

    Please ensure:
    - If there are multiple issues, summarize each separately.
    - Urgency: If the ticket or replies express urgency or escalation, highlight it.
    - Attachments: If mentioned in the ticket, note any relevant attachments or files.
    - Avoid extra explanations or unnecessary information.

    Ticket Data:
    - Ticket Source: $ticket_source
    - Current Ticket Status: $ticket_status
    - Ticket Priority: $ticket_priority
    - Ticket Category: $ticket_category
    - Ticket Subject: $ticket_subject
    - Ticket Details: $ticket_details
    - Replies:
    $all_replies_text

    Formatting instructions:
    - Use valid HTML tags only.
    - Use <h3> for section headers (Main Issue, Actions Taken, Resolution).
    - Use <ul><li> for bullet points under each section.
    - Do NOT wrap the output in ```html or any other code fences.
    - Do NOT include <html>, <head>, or <body>.
    - Output only the summary content in pure HTML.
    If any part of the ticket or replies is unclear or ambiguous, mention it in the summary and suggest if further clarification is needed.
    ";

    $result = callAiApi($model, [
        ["role" => "system", "content" => "Your task is to summarize IT support tickets with clear, concise details."],
        ["role" => "user", "content" => $prompt]
    ]);

    if (!$result['ok']) {
        echo "<p>" . escapeHtml($result['error']) . "</p>";
        exit;
    }

    echo $result['content'];
}

// Stops people trying to use sub-domains in the domains tracker
/*
 * The domain name in a New / Edit Domain form, checked as it is typed: what it
 * is saved as, whether DNS answers for it, whether it looks like a subdomain
 * of one that does, and whether the client has it already.
 */
if (isset($_GET['domain_check'])) {
    enforceUserPermission('module_support', 2);

    $input = (string) ($_GET['domain'] ?? '');
    $name = domainNormalizeName($input);
    $check_client_id = intval($_GET['client_id'] ?? 0);
    $check_domain_id = intval($_GET['domain_id'] ?? 0);
    $response = ['state' => '', 'message' => '', 'name' => $name];

    if (trim($input) === '') {
        echo json_encode($response);
        exit;
    }
    if ($name === '') {
        echo json_encode(['state' => 'bad', 'message' => "That isn't a domain name - something like example.com.", 'name' => '']);
        exit;
    }

    $saved_as = strcasecmp(trim($input), $name) !== 0 ? "Saved as $name. " : '';

    // Already on this client?
    if ($check_client_id) {
        $client_id = $check_client_id;
        enforceClientAccess();
        $name_sql = escapeSql($name);
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM domains WHERE domain_name = '$name_sql' AND domain_client_id = $check_client_id AND domain_id <> $check_domain_id LIMIT 1"))) {
            echo json_encode(['state' => 'bad', 'message' => $saved_as . "$name is already on this client.", 'name' => $name]);
            exit;
        }
    }

    if (@checkdnsrr($name, 'SOA')) {
        $response = ['state' => 'ok', 'message' => $saved_as . 'Found in DNS - its records and expiry are looked up when it is saved.', 'name' => $name];
    } else {
        // Nothing for the name itself - is it a subdomain of one that does answer?
        $parts = explode('.', $name);
        $parent = '';
        while (count($parts) > 2) {
            array_shift($parts);
            $candidate = implode('.', $parts);
            if (@checkdnsrr($candidate, 'SOA')) {
                $parent = $candidate;
                break;
            }
        }
        $response = $parent
            ? ['state' => 'warn', 'message' => $saved_as . "That looks like a subdomain of $parent - add $parent instead, unless it is registered on its own.", 'name' => $name]
            : ['state' => 'warn', 'message' => $saved_as . "Nothing answers for it in DNS yet - check the spelling. It can still be saved.", 'name' => $name];
    }

    echo json_encode($response);
    exit;
}

// Get internal users/techs
if (isset($_GET['get_internal_users'])) {
    enforceUserPermission('module_support');

    $sql = mysqli_query(
        $mysqli,
        "SELECT user_id, user_name
         FROM users
         WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL
         ORDER BY user_name"
    );

    while ($row = mysqli_fetch_assoc($sql)) {
        $response['users'][] = $row;
    }

    echo json_encode($response);
    exit;
}

if (isset($_GET['get_credential_via_id'])) {
    enforceUserPermission('module_credential');

    $credential_id = intval($_GET['credential_id']);

    $sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credential_name, credential_username, credential_password, credential_client_id FROM credentials WHERE credential_id = $credential_id"));
    if (!$sql) {
        echo json_encode(['error' => 'That credential no longer exists.']);
        exit;
    }
    $name = escapeSql($sql['credential_name']);
    $client_id = intval($sql['credential_client_id']);

    enforceClientAccess($client_id);

    $response = array(
        'username' => decryptCredentialEntry($sql['credential_username']),
        'password' => decryptCredentialEntry($sql['credential_password'])
    );
    echo json_encode($response);

    // When it was last used - the credentials list sorts by it. Kept off the updated date, which means an edit
    mysqli_query($mysqli, "UPDATE credentials SET credential_accessed_at = NOW(), credential_updated_at = credential_updated_at WHERE credential_id = $credential_id");

    // Only log if this user hasn't viewed this credential recently (mirrors TOTP dedup)
    $check_recent_view = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS recent_view FROM logs WHERE log_type = 'Credential' AND log_action = 'View' AND log_user_id = $session_user_id AND log_entity_id = $credential_id AND log_client_id = $client_id AND log_created_at > (NOW() - INTERVAL 5 MINUTE)"));

    if (intval($check_recent_view['recent_view']) == 0) {
        logAudit("Credential", "View", "$session_name viewed credential $name", $client_id, $credential_id);
    }
}

/*
 * The calendar page's items between two days: the events on the calendars
 * shown, and what the built-in sources shown have dated (calendarSources()),
 * for one client or every client this agent can reach, everyone's or one
 * agent's. Repeating events come back as their occurrences.
 */
if (isset($_GET['calendar_items'])) {

    header('Content-Type: application/json');

    $from = calendarIsDate($_GET['from'] ?? '') ? $_GET['from'] . ' 00:00:00' : date('Y-m-01 00:00:00');
    $to = calendarIsDate($_GET['to'] ?? '') ? $_GET['to'] . ' 23:59:59' : date('Y-m-t 23:59:59');
    // A window of more than a year is not something the page asks for
    if (strtotime($to) - strtotime($from) > 86400 * 400) {
        $to = date('Y-m-d 23:59:59', strtotime($from) + 86400 * 400);
    }

    $client_id = intval($_GET['client_id'] ?? 0);
    if ($client_id && !hasClientAccess($client_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have access to that client.']);
        exit;
    }
    $opts = ['client_id' => $client_id, 'user_id' => intval($_GET['user_id'] ?? 0)];

    $calendar_ids = array_filter(array_map('intval', explode(',', (string) ($_GET['calendars'] ?? ''))));
    $items = calendarEventItems($calendar_ids, $from, $to, $opts);
    foreach (array_filter(explode(',', (string) ($_GET['sources'] ?? ''))) as $key) {
        $items = array_merge($items, calendarSourceItems($key, $from, $to, $opts));
    }

    echo json_encode($items);
    exit;
}

/*
 * Something dragged on the calendar: a scheduled ticket to a new time, a
 * ticket from To schedule onto the calendar, or an event that does not repeat
 * to a new day or length. A ticket is booked exactly as from the ticket - the
 * agent, contact and watchers are emailed - and an event moved with notify
 * sends the update to the people who have it in their calendar.
 */
if (isset($_POST['calendar_move'])) {

    validateCSRFToken();

    header('Content-Type: application/json');
    $fail = function ($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    };

    $start = ticketScheduleValue($_POST['start'] ?? '');
    if ($start === '') {
        $fail('That is not a date and time.');
    }

    if (($_POST['kind'] ?? '') === 'ticket') {

        if (!calendarCanScheduleTickets()) {
            $fail('Your role cannot schedule tickets.', 403);
        }
        $row = ticketRow($_POST['id'] ?? 0);
        if (!$row || !empty($row['ticket_archived_at'])) {
            $fail('That ticket no longer exists.', 404);
        }
        if (intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id'])) {
            $fail('You do not have access to that client.', 403);
        }
        if (!ticketIsOpen($row)) {
            $fail('Ticket ' . ticketReference($row) . ' is resolved or closed - reopen it to schedule work.');
        }
        // Dropped on a whole day: the time it already had, or 9:00
        if (!empty($_POST['all_day'])) {
            $start = substr($start, 0, 10) . ' ' . ($row['ticket_schedule'] ? date('H:i:s', strtotime($row['ticket_schedule'])) : '09:00:00');
        }
        $onsite = isset($_POST['onsite']) ? intval($_POST['onsite']) : intval($row['ticket_onsite']);
        $result = ticketSetSchedule($row, $start, $onsite);

        echo json_encode(['ok' => true, 'message' => ticketReference($row) . ' scheduled for ' . $result['when_text'], 'conflicts' => $result['conflicts']]);
        exit;
    }

    $event = calendarEventRow($_POST['id'] ?? 0);
    if (!$event) {
        $fail('That event no longer exists.', 404);
    }
    if (intval($event['event_client_id']) && !hasClientAccess($event['event_client_id'])) {
        $fail('You do not have access to that client.', 403);
    }
    if (!calendarEventCan($event, 'edit')) {
        $fail(permissionRefusedText('module_client', 2, intval(lookupUserPermission('module_client'))), 403);
    }
    if (calendarEventRepeats($event)) {
        $fail('A repeating event is moved from Edit, so the whole series moves together.');
    }
    $event_id = intval($event['event_id']);

    if (!empty($_POST['all_day'])) {
        // FullCalendar's all-day end is exclusive; event_end is the last day covered
        $start = substr($start, 0, 10) . ' 00:00:00';
        $end_value = ticketScheduleValue($_POST['end'] ?? '');
        $end = $end_value !== '' ? date('Y-m-d 00:00:00', strtotime(substr($end_value, 0, 10) . ' -1 day')) : $start;
        if ($end < $start) {
            $end = $start;
        }
        $all_day = 1;
    } else {
        $end = ticketScheduleValue($_POST['end'] ?? '');
        if ($end === '' || $end <= $start) {
            // Keep its length - or an hour, coming off a whole day
            $length = $event['event_all_day'] ? 3600 : max(900, strtotime($event['event_end'] ?: $event['event_start']) - strtotime($event['event_start']));
            $end = date('Y-m-d H:i:s', strtotime($start) + $length);
        }
        $all_day = 0;
    }

    mysqli_query($mysqli, "UPDATE calendar_events SET event_start = '$start', event_end = '$end', event_all_day = $all_day, event_sequence = event_sequence + 1 WHERE event_id = $event_id");

    $event = calendarEventRow($event_id);
    $sent = !empty($_POST['notify']) ? calendarSendInvites($event, 'update', calendarInvitedAttendees($event_id)) : 0;

    logAudit("Calendar Event", "Edit", "$session_name moved the event " . escapeSql($event['event_title']) . " to " . escapeSql(calendarWhenText($event)), intval($event['event_client_id']), $event_id);

    echo json_encode(['ok' => true, 'message' => $event['event_title'] . ' moved to ' . calendarWhenText($event) . ($sent ? " - the update was emailed to $sent " . ($sent === 1 ? 'person' : 'people') : '')]);
    exit;
}

/*
 * The calendar's To schedule list: an agent's open tickets with no work booked
 * (most urgent first), to drag onto the calendar - in one client, or all.
 */
if (isset($_GET['calendar_unscheduled'])) {

    header('Content-Type: application/json');

    if (!calendarCanScheduleTickets()) {
        echo json_encode(['tickets' => []]);
        exit;
    }
    $user_id = intval($_GET['user_id'] ?? 0) ?: $session_user_id;
    $client_id = intval($_GET['client_id'] ?? 0);
    if ($client_id && !hasClientAccess($client_id)) {
        echo json_encode(['tickets' => []]);
        exit;
    }

    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_onsite, ticket_client_id, client_name
        FROM tickets
        LEFT JOIN clients ON client_id = ticket_client_id
        WHERE ticket_schedule IS NULL AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL
        AND ticket_assigned_to = $user_id
        " . ($client_id ? "AND ticket_client_id = $client_id" : '') . "
        " . clientScopeSql('ticket_client_id') . "
        ORDER BY FIELD(ticket_priority, 'Urgent', 'High', 'Medium', 'Low'), ticket_created_at ASC
        LIMIT 25");
    $tickets = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $tickets[] = [
            'id' => intval($row['ticket_id']),
            'reference' => ticketReference($row),
            'subject' => $row['ticket_subject'],
            'client' => $row['client_name'] ?: 'Internal',
            'priority' => $row['ticket_priority'],
            'onsite' => intval($row['ticket_onsite']),
        ];
    }

    echo json_encode(['tickets' => $tickets]);
    exit;
}

/*
 * One agent's day - their scheduled tickets and the events they are on - for
 * the ticket's Schedule dialog, so a visit is not booked on top of another.
 * $_GET: date, user_id, ticket_id (the ticket being scheduled, left out).
 */
if (isset($_GET['calendar_day'])) {

    header('Content-Type: application/json');

    $date = calendarIsDate($_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
    $user_id = intval($_GET['user_id'] ?? 0);
    $skip_ticket = 'ticket-' . intval($_GET['ticket_id'] ?? 0);
    if (!$user_id || lookupUserPermission('module_support') < 1) {
        echo json_encode(['items' => []]);
        exit;
    }

    $from = "$date 00:00:00";
    $to = "$date 23:59:59";
    $opts = ['user_id' => $user_id];
    $items = array_merge(
        calendarSourceItems('tickets', $from, $to, $opts),
        calendarEventItems(array_keys(calendarList()), $from, $to, $opts)
    );

    $day = [];
    foreach ($items as $item) {
        if ($item['id'] === $skip_ticket) {
            continue;
        }
        $all_day = !empty($item['allDay']);
        $day[] = [
            'start' => $all_day ? $date . ' 00:00:00' : $item['start'],
            'time' => $all_day ? 'All day' : date('g:i A', strtotime($item['start'])) . (!empty($item['end']) ? ' - ' . date('g:i A', strtotime($item['end'])) : ''),
            'title' => $item['title'],
            'kind' => $item['extendedProps']['kind'] ?? '',
            'color' => $item['color'] ?? '',
        ];
    }
    usort($day, function ($a, $b) {
        return strcmp($a['start'], $b['start']);
    });

    echo json_encode(['items' => $day]);
    exit;
}

/*
 * Reconcile (agent/reconcile.php, js/reconcile.js): tick or untick lines on
 * a statement being reconciled - POST reconciliation_id, lines[] (payment-12,
 * revenue-5, expense-9) and tick (1 or 0). Answers with where it stands now
 * (reconcileTotals()).
 */
if (isset($_POST['reconcile_tick'])) {

    validateCSRFToken();

    header('Content-Type: application/json');

    if (lookupUserPermission('module_financial') < 2) {
        http_response_code(403);
        echo json_encode(['error' => 'Reconciling takes write access to Financial.']);
        exit;
    }
    $reconciliation_id = intval($_POST['reconciliation_id'] ?? 0);
    $lines = array_slice(array_values(array_filter(array_map('strval', (array) ($_POST['lines'] ?? [])))), 0, 5000);
    $changed = reconcileTick($reconciliation_id, $lines, !empty($_POST['tick']), $error);
    if ($changed === null) {
        echo json_encode(['error' => $error]);
        exit;
    }
    $row = reconcileRow($reconciliation_id);
    echo json_encode(['changed' => $changed, 'totals' => reconcileTotals($row)]);
    exit;
}
