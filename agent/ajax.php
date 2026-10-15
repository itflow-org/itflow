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
 * Fetches SSL certificates from remote hosts & returns the relevant info (issuer, expiry, public key)
 */
if (isset($_GET['certificate_fetch_parse_json_details'])) {
    enforceUserPermission('module_support');

    // PHP doesn't appreciate attempting SSL sockets to non-existent domains
    if (empty($_GET['domain'])) {
        exit();
    }

    $name = $_GET['domain'];

    // Get SSL cert for domain (if exists)
    $certificate = getSslCertificate($name);

    if ($certificate['success'] == "TRUE") {
        $response['success'] = "TRUE";
        $response['expire'] = $certificate['expire'];
        $response['issued_by'] = $certificate['issued_by'];
        $response['public_key'] = $certificate['public_key'];
    } else {
        $response['success'] = "FALSE";
    }

    echo json_encode($response);

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

    $sql = mysqli_query($mysqli,
        "SELECT tag_id, tag_name, tag_color, tag_icon,
            (SELECT COUNT(*) FROM {$config['link_table']}
                WHERE {$config['link_tag']} = tags.tag_id AND {$config['link_record']} IN ($ids)) AS chosen
        FROM tags
        WHERE tag_type = {$config['type']}
        ORDER BY tag_name ASC"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        $response['tags'][] = [
            'id' => intval($row['tag_id']),
            'name' => $row['tag_name'],
            'color' => preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,24})$/i', trim((string) $row['tag_color'])) ? trim($row['tag_color']) : '',
            'icon' => preg_replace('/[^a-z0-9-]/', '', strtolower((string) $row['tag_icon'])) ?: 'tag',
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

        // Typing the name of a tag that already exists just picks it
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_type = $type AND tag_name = '$name_sql' LIMIT 1"));
        if ($row) {
            $tag_id = intval($row['tag_id']);
        } else {
            $color = tagNextColor($type);
            mysqli_query($mysqli, "INSERT INTO tags SET tag_name = '$name_sql', tag_type = $type, tag_color = '$color', tag_icon = 'tag'");
            $tag_id = mysqli_insert_id($mysqli);
            logAudit("Tag", "Create", "$session_name created tag $name_sql", 0, $tag_id);
        }
        $on = true;

    } else {
        $tag_id = intval($_POST['tag_id'] ?? 0);
        $on = !empty($_POST['on']);
    }

    // The tag has to be one of this kind of record's tags
    $tag = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id = $tag_id AND tag_type = $type"));
    if (!$tag) {
        http_response_code(404);
        echo json_encode(['error' => 'That tag no longer exists.']);
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
            $changed++;
        } elseif (!$on && $has) {
            mysqli_query($mysqli, "DELETE FROM $link WHERE $link_record = $record_id AND $link_tag = $tag_id");
            logAudit($config['log'], "Edit", "$session_name removed tag $tag_name from $record_name", $record['client_id'], $record_id);
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
 * and billable, a project's manager, a recurring ticket's assignee and an
 * asset's status, location and contact, changed in place instead of through a modal. GET lists the choices; POST makes the change through the same
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
        $options[] = ['value' => '1', 'label' => 'Billable', 'icon' => 'fas fa-dollar-sign', 'description' => 'Shows on the Unbilled Tickets report until invoiced'];
        $options[] = ['value' => '0', 'label' => 'Not billable', 'icon' => 'fas fa-minus'];
    } elseif ($field === 'assignee' || $field === 'recurring_assignee') {
        $options = quickPickAgents();
    } elseif ($field === 'project_manager') {
        $options = quickPickAgents('No manager');
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
 * Ticketing Collision Detection/Avoidance
 * Called upon loading a ticket, and every 2 mins thereafter
 * Is used in conjunction with ticket_query_views to show who is currently viewing a ticket
 */
if (isset($_GET['ticket_add_view'])) {
    $ticket_id = intval($_GET['ticket_id']);

    mysqli_query($mysqli, "INSERT INTO ticket_views SET view_ticket_id = $ticket_id, view_user_id = $session_user_id, view_timestamp = NOW()");
}

/*
 * Ticketing Collision Detection/Avoidance
 * Returns formatted text of the agents currently viewing a ticket
 * Called upon loading a ticket, and every 2 mins thereafter
 */
if (isset($_GET['ticket_query_views'])) {
    $ticket_id = intval($_GET['ticket_id']);

    $query = mysqli_query($mysqli, "SELECT user_name FROM ticket_views LEFT JOIN users ON view_user_id = user_id WHERE view_ticket_id = $ticket_id AND view_user_id != $session_user_id AND view_timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    while ($row = mysqli_fetch_assoc($query)) {
        $users[] = $row['user_name'];
    }

    if (!empty($users)) {
        $users = array_unique($users);
        if (count($users) > 1) {
            // Multiple viewers
            $response['message'] = "<i class='fas fa-fw fa-eye me-2'></i>" . escapeHtml(implode(", ", $users) . " are viewing this ticket.");
        } else {
            // Single viewer
            $response['message'] = "<i class='fas fa-fw fa-eye me-2'></i>" . escapeHtml(implode("", $users) . " is viewing this ticket.");
        }
    } else {
        // No viewers
        $response['message'] = "";
    }

    echo json_encode($response);
}

/*
 * Generates public/guest links for sharing credentials/docs
 */
if (isset($_GET['share_generate_link'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $item_encrypted_username = '';  // Default empty
    $item_encrypted_credential = '';  // Default empty

    $client_id = intval($_GET['client_id']);
    $item_type = escapeSql($_GET['type']);
    $item_id = intval($_GET['id']);
    $item_email = escapeSql($_GET['contact_email']);
    $item_note = escapeSql($_GET['note']);
    $item_view_limit = intval($_GET['views']);
    $item_view_limit_wording = "";
    if ($item_view_limit == 1) {
        $item_view_limit_wording = " and may only be viewed <strong>once</strong>, before the link is destroyed.";
    }
    $item_expires = intval($_GET['expires']);
    $item_expires_friendly = "never"; // default never
    if ($item_expires == 1) {
        $item_expires_friendly = "1 hour";
    } elseif ($item_expires == 24) {
        $item_expires_friendly = "1 day";
    } elseif ($item_expires == 168) {
        $item_expires_friendly = "1 week";
    } elseif ($item_expires == 730) {
        $item_expires_friendly = "1 month";
    }

    enforceClientAccess($client_id);

    $item_key = randomString(32);

    if ($item_type == "Document") {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name FROM documents WHERE document_id = $item_id AND document_client_id = $client_id LIMIT 1"));
        $item_name = escapeSql($row['document_name']);
    }

    if ($item_type == "File") {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name FROM files WHERE file_id = $item_id AND file_client_id = $client_id LIMIT 1"));
        $item_name = escapeSql($row['file_name']);
    }

    if ($item_type == "Credential") {

        // Sharing a credential hands out the plaintext, so it needs the same
        // module access as reading one anywhere else in the app
        enforceUserPermission('module_credential');

        $credential = mysqli_query($mysqli, "SELECT credential_name, credential_username, credential_password FROM credentials WHERE credential_id = $item_id AND credential_client_id = $client_id LIMIT 1");
        $row = mysqli_fetch_assoc($credential);

        $item_name = escapeSql($row['credential_name']);

        // Decrypt & re-encrypt username/password for sharing
        $credential_encryption_key = randomString();

        $credential_username_cleartext = decryptCredentialEntry($row['credential_username']);
        $iv = randomString();
        $username_ciphertext = openssl_encrypt($credential_username_cleartext, 'aes-128-cbc', $credential_encryption_key, 0, $iv);
        $item_encrypted_username = $iv . $username_ciphertext;

        $credential_password_cleartext = decryptCredentialEntry($row['credential_password']);
        $iv = randomString();
        $password_ciphertext = openssl_encrypt($credential_password_cleartext, 'aes-128-cbc', $credential_encryption_key, 0, $iv);
        $item_encrypted_credential = $iv . $password_ciphertext;
    }

    // Insert entry into DB
    $sql = mysqli_query($mysqli, "INSERT INTO shared_items SET item_active = 1, item_key = '$item_key', item_type = '$item_type', item_related_id = $item_id, item_encrypted_username = '$item_encrypted_username', item_encrypted_credential = '$item_encrypted_credential', item_note = '$item_note', item_recipient = '$item_email', item_views = 0, item_view_limit = $item_view_limit, item_expire_at = NOW() + INTERVAL + $item_expires HOUR, item_client_id = $client_id");
    $share_id = $mysqli->insert_id;

    // Return URL
    if ($item_type == "Credential") {
        $url = "https://$config_base_url/guest/guest_view_item.php?id=$share_id&key=$item_key&ek=$credential_encryption_key";
    }
    else {
        $url = "https://$config_base_url/guest/guest_view_item.php?id=$share_id&key=$item_key";
    }

    $sql = mysqli_query($mysqli,"SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql);
    $company_name = escapeSql($row['company_name']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

    // Sanitize Config vars from get_settings.php
    $config_ticket_from_name = escapeSql($config_ticket_from_name);
    $config_ticket_from_email = escapeSql($config_ticket_from_email);
    $config_mail_from_name = escapeSql($config_mail_from_name);
    $config_mail_from_email = escapeSql($config_mail_from_email);

    // Send user e-mail, if specified
    if(!empty($config_smtp_host) && filter_var($item_email, FILTER_VALIDATE_EMAIL)){

        $subject = "Time sensitive - $company_name secure link enclosed";
        if ($item_expires_friendly == "never") {
            $subject = "$company_name secure link enclosed";
        }
        $body = "Hello,<br><br>$session_name from $company_name sent you a time sensitive secure link regarding \"$item_name\".<br><br>The link will expire in <strong>$item_expires_friendly</strong>$item_view_limit_wording.<br><br><strong><a href=\'$url\'>Click here to access your secure content</a></strong><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        // Add the intended recipient disclosure
        $body .= "<br><br><em>This email and any attachments are confidential and intended for the specified recipient(s) only. If you are not the intended recipient, please notify the sender and delete this email. Unauthorized use, disclosure, or distribution is prohibited.</em>";

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $item_email,
                'recipient_name' => $item_email,
                'subject' => $subject,
                'body' => $body
            ]
        ];

        addToMailQueue($data);

    }

    echo json_encode($url);

    // Logging
    logAudit("Share", "Create", "$session_name created shared link for $item_type - $item_name", $client_id, $item_id);

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
        "SELECT contact_id, contact_name, contact_title, contact_email, contact_photo, contact_primary, contact_important, contact_technical FROM contacts
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
 * Returns ordered list of active assets for a specified client
 */
if (isset($_GET['get_client_assets'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    enforceClientAccess();

    $asset_sql = mysqli_query(
        $mysqli,
        "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, contact_name FROM assets
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
        "SELECT vendor_id, vendor_name FROM vendors
        LEFT JOIN clients on vendor_client_id = client_id
        WHERE vendors.vendor_archived_at IS NULL AND vendor_client_id = $client_id
        " . clientScopeSql('vendor_client_id') . "
        ORDER BY vendor_name ASC"
    );

    // Always return the key, so a client with none gives [] rather than null
    $response['vendors'] = [];

    while ($row = mysqli_fetch_assoc($vendors_sql)) {
        $response['vendors'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns open projects for a specified client
 */
if (isset($_GET['get_client_projects'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    enforceClientAccess();

    $projects_sql = mysqli_query(
        $mysqli,
        "SELECT project_id, project_name FROM projects
        LEFT JOIN clients on project_client_id = client_id
        WHERE projects.project_archived_at IS NULL AND projects.project_completed_at IS NULL AND project_client_id = $client_id
        " . clientScopeSql('project_client_id') . "
        ORDER BY project_name ASC"
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
    $name = escapeSql($sql['credential_name']);
    $totp_secret = $sql['credential_otp_secret'];
    $client_id = intval($sql['credential_client_id']);

    enforceClientAccess();

    $otp = TokenAuth6238::getTokenCode(strtoupper($totp_secret));
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
if (isset($_POST['update_kanban_status_position'])) {
    // Update multiple ticket status kanban orders
    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];

    foreach ($positions as $position) {
        $status_id = intval($position['status_id']);
        $kanban = intval($position['status_kanban']);

        mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_order = $kanban WHERE ticket_status_id = $status_id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_kanban_ticket'])) {
    // Update ticket kanban order and status
    enforceUserPermission('module_support', 2);

    // all tickets on the column
    $positions = $_POST['positions'];

    foreach ($positions as $position) {
        $ticket_id = intval($position['ticket_id']);

        // Client perms check
        $client_query = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_client_id FROM tickets WHERE ticket_id = $ticket_id"));
        $client_id = intval($client_query['ticket_client_id']);
        enforceClientAccess();

        $kanban = intval($position['ticket_order']); // ticket kanban position
        $status = intval($position['ticket_status']); // ticket statuses
        $oldStatus = intval($position['ticket_oldStatus']); // ticket old status if moved

        $statuses['Closed'] = 5;
        $statuses['Resolved'] = 4;

        // Continue if status is null / Closed
        if ($status === null || $status === $statuses['Closed']) {
            continue;
        }


        if ($oldStatus === false) {
            // if ticket was not moved, just uptdate the order on kanban
            mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban WHERE ticket_id = $ticket_id");
            triggerCustomAction('ticket_update', $ticket_id);
        } else {
            // If the ticket was moved from a resolved status to another status, we need to update ticket_resolved_at
            if ($oldStatus === $statuses['Resolved']) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban, ticket_status = $status, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id");
                resetTicketResolutionSla($ticket_id);
                syncTicketSlaClock($ticket_id);
                $new_status_name = escapeSql(getTicketStatusName($status));
                logTicketHistory($ticket_id, "$session_name reopened the ticket to $new_status_name from the kanban");
                triggerCustomAction('ticket_update', $ticket_id);
            } elseif ($status === $statuses['Resolved']) {
                // If the ticket was moved to a resolved status, we need to update ticket_resolved_at
                mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban, ticket_status = $status, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
                // An agent resolving the ticket counts as a response, same as
                // resolving from the ticket itself does
                setTicketFirstResponse($ticket_id);
                setTicketResolutionSlaMet($ticket_id);
                syncTicketSlaClock($ticket_id);
                logTicketHistory($ticket_id, "$session_name resolved the ticket from the kanban");
                triggerCustomAction('ticket_update', $ticket_id);

                // Client notification email
                if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

                    // Get details
                    $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_status_name, ticket_assigned_to, ticket_url_key, ticket_client_id FROM tickets
                        LEFT JOIN contacts ON ticket_contact_id = contact_id
                        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
                        WHERE ticket_id = $ticket_id
                    ");
                    $row = mysqli_fetch_assoc($ticket_sql);

                    $contact_name = escapeSql($row['contact_name']);
                    $contact_email = escapeSql($row['contact_email']);
                    $ticket_prefix = escapeSql($row['ticket_prefix']);
                    $ticket_number = intval($row['ticket_number']);
                    $ticket_subject = escapeSql($row['ticket_subject']);
                    $client_id = intval($row['ticket_client_id']);
                    $ticket_assigned_to = intval($row['ticket_assigned_to']);
                    $ticket_status = escapeSql($row['ticket_status_name']);
                    $url_key = escapeSql($row['ticket_url_key']);

                    // Sanitize Config vars from get_settings.php
                    $config_ticket_from_name = escapeSql($config_ticket_from_name);
                    $config_ticket_from_email = escapeSql($config_ticket_from_email);
                    $config_base_url = escapeSql($config_base_url);

                    // Get Company Info
                    $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
                    $row = mysqli_fetch_assoc($sql);
                    $company_name = escapeSql($row['company_name']);
                    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

                    // EMAIL
                    $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
                    $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been marked as solved and is pending closure.<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

                    // Check email valid
                    if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

                        $data = [];

                        // Email Ticket Contact
                        // Queue Mail

                        $data[] = [
                            'from' => $config_ticket_from_email,
                            'from_name' => $config_ticket_from_name,
                            'recipient' => $contact_email,
                            'recipient_name' => $contact_name,
                            'subject' => $subject,
                            'body' => $body
                        ];
                    }

                    // Also Email all the watchers
                    $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
                    $body .= "<br><br>----------------------------------------<br>YOU ARE A COLLABORATOR ON THIS TICKET";
                    while ($row = mysqli_fetch_assoc($sql_watchers)) {
                        $watcher_email = escapeSql($row['watcher_email']);

                        // Queue Mail
                        $data[] = [
                            'from' => $config_ticket_from_email,
                            'from_name' => $config_ticket_from_name,
                            'recipient' => $watcher_email,
                            'recipient_name' => $watcher_email,
                            'subject' => $subject,
                            'body' => $body
                        ];
                    }
                    addToMailQueue($data);
                }
                //End Mail IF

            } else {
                // If the ticket was moved from any status to another status
                mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban, ticket_status = $status WHERE ticket_id = $ticket_id");
                syncTicketSlaClock($ticket_id);
                $new_status_name = escapeSql(getTicketStatusName($status));
                logTicketHistory($ticket_id, "$session_name set the status to $new_status_name from the kanban");
                triggerCustomAction('ticket_update', $ticket_id);
            }
        }

    }

    // return a response
    echo json_encode(['status' => 'success','payload' => $positions]);
    exit;
}

if (isset($_POST['update_ticket_tasks_order'])) {
    // Update multiple ticket tasks order

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];
    $ticket_id = intval($_POST['ticket_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

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

    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];
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

if (isset($_POST['update_quote_items_order'])) {
    // Update multiple quote items order

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $positions = $_POST['positions'];
    $quote_id = intval($_POST['quote_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE quote_items SET item_order = $order WHERE item_quote_id = $quote_id AND item_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_invoice_items_order'])) {
    // Update multiple invoice items order

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $positions = $_POST['positions'];
    $invoice_id = intval($_POST['invoice_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE invoice_items SET item_order = $order WHERE item_invoice_id = $invoice_id AND item_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_recurring_invoice_items_order'])) {
    // Update multiple recurring invoice items order

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $positions = $_POST['positions'];
    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE recurring_invoice_items SET item_order = $order WHERE item_recurring_invoice_id = $recurring_invoice_id AND item_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
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
            $response['message'] = "<i class='fas fa-fw fa-check me-2'></i>" . escapeHtml($ip) . " is available";
        } else {
            // checkIpForNetwork() builds its message for flashAlert(), which
            // escapes at render - this lands in innerHTML instead, so it has
            // to be escaped here
            $response['message'] = "<i class='fas fa-fw fa-exclamation-triangle me-2'></i>" . alertMessageHtml($ip_error);
        }

    }

    echo json_encode($response);
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
    $ticket_subject = $row['ticket_subject'];
    $ticket_details = strip_tags($row['ticket_details']); // strip HTML for cleaner prompt
    $ticket_status = $row['ticket_status_name'];
    $ticket_category = $row['category_name'];
    $ticket_source = $row['ticket_source'];
    $ticket_priority = $row['ticket_priority'];
    $client_id = intval($row['ticket_client_id']);

    enforceClientAccess();

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
if (isset($_GET['apex_domain_check'])) {
    enforceUserPermission('module_support', 2);

    $domain = escapeSql($_GET['domain']);

    $response['message'] = ""; // default

    if (strlen($domain) >= 4) {

        // SOA record check
        //  This isn't 100%, as sub-domains can have their own SOA but will capture 99%
        if (!checkdnsrr($domain, 'SOA')) {
            $response['message'] = "<i class='fas fa-fw fa-exclamation-triangle me-2'></i> Domain name is invalid.";
        }

    }

    echo json_encode($response);
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
    $name = escapeSql($sql['credential_name']);
    $client_id = intval($sql['credential_client_id']);

    enforceClientAccess($client_id);

    $response = array(
        'username' => decryptCredentialEntry($sql['credential_username']),
        'password' => decryptCredentialEntry($sql['credential_password'])
    );
    echo json_encode($response);

    // Only log if this user hasn't viewed this credential recently (mirrors TOTP dedup)
    $check_recent_view = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS recent_view FROM logs WHERE log_type = 'Credential' AND log_action = 'View' AND log_user_id = $session_user_id AND log_entity_id = $credential_id AND log_client_id = $client_id AND log_created_at > (NOW() - INTERVAL 5 MINUTE)"));

    if (intval($check_recent_view['recent_view']) == 0) {
        logAudit("Credential", "View", "$session_name viewed credential $name", $client_id, $credential_id);
    }
}
