<?php

/*
 * ITFlow - GET/POST request handler for client services
 *
 * A service and the things it depends on (functions/service.php). Links to
 * those things go through the generic link handler (agent/post/link.php) -
 * only Add Service links them here, from the pickers on its form.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_service'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();
    if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {
        flashAlert('Pick the client the service is for.', 'error');
        redirect();
    }

    $error = '';
    $set = serviceFormSet($_POST, $error);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO services SET $set, service_client_id = $client_id");
    $service_id = mysqli_insert_id($mysqli);
    $service_name = escapeSql(trim($_POST['name']));

    logAudit("Service", "Create", "$session_name created service $service_name", $client_id, $service_id);

    tagSetForRecord('service', $service_id, (array) ($_POST['tags'] ?? []));

    // What it depends on, from the form's pickers - the client's own, unarchived records only (linkAdd checks)
    $linked = 0;
    foreach (SERVICE_LINK_TYPES as $type) {
        $ids = $_POST[LINK_ENTITIES[$type]['table']] ?? [];
        if ($ids && linkCanEdit('service', $type)) {
            $linked += count(linkAdd('service', $service_id, $type, (array) $ids));
        }
    }

    flashAlert("Service <strong>" . escapeHtml(trim($_POST['name'])) . "</strong> created" . ($linked ? " with <strong>$linked</strong> linked" : ' - link what it runs on and depends on below'));

    redirect(serviceUrl($service_id, $client_id));

}

if (isset($_POST['edit_service'])) {

    validateCSRFToken();

    $row = serviceForRequest($_POST['service_id'] ?? 0, 2);
    $service_id = intval($row['service_id']);

    $error = '';
    $set = serviceFormSet($_POST, $error, $row);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE services SET $set WHERE service_id = $service_id");

    // Only a form that showed the tags changes them
    if (isset($_POST['tags_form'])) {
        tagSetForRecord('service', $service_id, (array) ($_POST['tags'] ?? []));
    }

    $service_name = escapeSql(trim($_POST['name']));
    logAudit("Service", "Edit", "$session_name edited service $service_name", $client_id, $service_id);

    flashAlert("Service <strong>" . escapeHtml(trim($_POST['name'])) . "</strong> saved");

    redirect();

}

// Notes save from the service page when you click away
if (isset($_POST['service_set_notes'])) {

    validateCSRFToken();

    $row = serviceForRequest($_POST['service_id'] ?? 0, 2);
    $notes = escapeSql((string) ($_POST['notes'] ?? ''));
    mysqli_query($mysqli, "UPDATE services SET service_notes = '$notes' WHERE service_id = " . intval($row['service_id']));

    logAudit("Service", "Edit", "$session_name updated the notes of service " . escapeSql($row['service_name']), $client_id, intval($row['service_id']));

    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    flashAlert("Notes saved");

    redirect();

}

if (isset($_POST['review_service'])) {

    validateCSRFToken();

    $row = serviceForRequest($_POST['service_id'] ?? 0, 2);

    // A new schedule can be set at the same time
    if (isset($_POST['review_interval']) && isset(SERVICE_REVIEW_INTERVALS[intval($_POST['review_interval'])])) {
        $row['service_review_interval'] = intval($_POST['review_interval']);
        mysqli_query($mysqli, "UPDATE services SET service_review_interval = {$row['service_review_interval']} WHERE service_id = " . intval($row['service_id']));
    }

    $next = serviceMarkReviewed($row, trim((string) ($_POST['review_due'] ?? '')), (string) ($_POST['note'] ?? ''));

    flashAlert("Service <strong>" . escapeHtml($row['service_name']) . "</strong> marked reviewed" . ($next ? " - next review " . date('M j, Y', strtotime($next)) : ''));

    redirect();

}

if (isset($_GET['favorite_service']) || isset($_GET['unfavorite_service'])) {

    validateCSRFToken();

    $favorite = isset($_GET['favorite_service']) ? 1 : 0;
    $row = serviceForRequest($favorite ? $_GET['favorite_service'] : $_GET['unfavorite_service'], 2);

    mysqli_query($mysqli, "UPDATE services SET service_favorite = $favorite WHERE service_id = " . intval($row['service_id']));

    logAudit("Service", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " service " . escapeSql($row['service_name']), $client_id, intval($row['service_id']));

    flashAlert("Service <strong>" . escapeHtml($row['service_name']) . "</strong> " . ($favorite ? 'starred' : 'unstarred'));

    redirect();

}

if (isset($_GET['archive_service'])) {

    validateCSRFToken();

    $row = serviceForRequest($_GET['archive_service'], 2);

    serviceArchive($row);

    flashAlert("Service <strong>" . escapeHtml($row['service_name']) . "</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_service'])) {

    validateCSRFToken();

    $row = serviceForRequest($_GET['restore_service'], 2);

    serviceRestore($row);

    flashAlert("Service <strong>" . escapeHtml($row['service_name']) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_service'])) {

    validateCSRFToken();

    $row = serviceForRequest($_GET['delete_service'], 3);

    serviceDelete($row);

    flashAlert("Service <strong>" . escapeHtml($row['service_name']) . "</strong> deleted", 'error');

    // Not back to the page of a service that is gone
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    redirect(strpos($referer, 'service.php') !== false ? 'services.php?client_id=' . intval($row['service_client_id']) : null);

}

// Tickets raised about a service - from its page, or from the ticket
if (isset($_POST['link_service_tickets'])) {

    validateCSRFToken();

    $row = serviceForRequest($_POST['service_id'] ?? 0, 2);

    $added = serviceLinkTickets($row['service_id'], $client_id, (array) ($_POST['ticket_ids'] ?? []));
    if ($added) {
        logAudit("Service", "Link", "$session_name linked $added ticket(s) to service " . escapeSql($row['service_name']), $client_id, intval($row['service_id']));
        flashAlert("Linked <strong>$added</strong> ticket" . ($added === 1 ? '' : 's') . " to <strong>" . escapeHtml($row['service_name']) . "</strong>");
    } else {
        flashAlert("Nothing new to link to <strong>" . escapeHtml($row['service_name']) . "</strong>.", 'warning');
    }

    redirect();

}

if (isset($_POST['link_ticket_services'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id"));
    if (!$ticket || !intval($ticket['ticket_client_id'])) {
        flashAlert('That ticket no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($ticket['ticket_client_id']);
    enforceClientAccess();

    $names = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['service_ids'] ?? []))) as $service_id) {
        $row = serviceRow($service_id);
        if (!$row || intval($row['service_client_id']) !== $client_id || $row['service_archived_at']) {
            continue;
        }
        if (serviceLinkTickets($service_id, $client_id, [$ticket_id])) {
            $names[] = $row['service_name'];
            logAudit("Service", "Link", "$session_name linked ticket " . escapeSql($ticket['ticket_prefix'] . $ticket['ticket_number']) . " to service " . escapeSql($row['service_name']), $client_id, $service_id);
        }
    }

    if ($names) {
        flashAlert("Ticket linked to <strong>" . escapeHtml(implode(', ', $names)) . "</strong>");
    } else {
        flashAlert('Nothing new to link.', 'warning');
    }

    redirect();

}

if (isset($_GET['unlink_service_ticket'])) {

    validateCSRFToken();

    $row = serviceForRequest($_GET['service_id'] ?? 0, 2);
    $ticket_id = intval($_GET['unlink_service_ticket']);

    mysqli_query($mysqli, "DELETE FROM service_tickets WHERE service_id = " . intval($row['service_id']) . " AND ticket_id = $ticket_id");
    if (mysqli_affected_rows($mysqli) > 0) {
        $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id"));
        $reference = $ticket ? $ticket['ticket_prefix'] . $ticket['ticket_number'] : "#$ticket_id";
        logAudit("Service", "Unlink", "$session_name unlinked ticket " . escapeSql($reference) . " from service " . escapeSql($row['service_name']), $client_id, intval($row['service_id']));
        flashAlert("Ticket <strong>" . escapeHtml($reference) . "</strong> unlinked from <strong>" . escapeHtml($row['service_name']) . "</strong>", 'error');
    }

    redirect();

}

/*
 * Bulk actions from the services list
 */

if (isset($_POST['bulk_edit_services'])) {

    validateCSRFToken();

    $rows = servicesForBulk(2);

    // Each field only when one was picked - blank leaves it as it is
    $sets = [];
    $changes = [];
    if (in_array($_POST['importance'] ?? '', SERVICE_IMPORTANCE, true)) {
        $sets[] = "service_importance = '{$_POST['importance']}'";
        $changes[] = 'importance ' . $_POST['importance'];
    }
    $category = trim((string) ($_POST['category'] ?? ''));
    if ($category !== '') {
        $category = mb_substr($category, 0, 50);
        $sets[] = "service_category = '" . escapeSql($category) . "'";
        $changes[] = 'category ' . $category;
    }
    $interval = (string) ($_POST['review_interval'] ?? '');
    $set_interval = $interval !== '' && isset(SERVICE_REVIEW_INTERVALS[intval($interval)]);

    if (!$sets && !$set_interval) {
        flashAlert('Nothing picked to change.', 'warning');
        redirect();
    }

    $count = 0;
    foreach ($rows as $service_id => $row) {
        $row_sets = $sets;
        if ($set_interval) {
            $months = intval($interval);
            $row_sets[] = "service_review_interval = $months";
            // A schedule needs a next date: from the last review, never in the past - and none without a schedule
            if ($months) {
                $due = max(serviceNextReview($months, $row['service_reviewed_at'] ?: null), date('Y-m-d'));
                $row_sets[] = "service_review_due = '$due'";
            } else {
                $row_sets[] = "service_review_due = NULL";
            }
        }
        mysqli_query($mysqli, "UPDATE services SET " . implode(', ', $row_sets) . " WHERE service_id = $service_id");
        $what = $changes;
        if ($set_interval) {
            $what[] = 'reviews ' . strtolower(SERVICE_REVIEW_INTERVALS[intval($interval)]);
        }
        logAudit("Service", "Edit", "$session_name set " . escapeSql(implode(', ', $what)) . " on service " . escapeSql($row['service_name']), intval($row['service_client_id']), $service_id);
        $count++;
    }

    flashAlert("Updated <strong>$count</strong> service" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_review_services'])) {

    validateCSRFToken();

    $count = 0;
    foreach (servicesForBulk(2) as $row) {
        if ($row['service_archived_at']) {
            continue;
        }
        serviceMarkReviewed($row);
        $count++;
    }

    flashAlert("Marked <strong>$count</strong> service" . ($count === 1 ? '' : 's') . " reviewed");

    redirect();

}

if (isset($_POST['bulk_favorite_services']) || isset($_POST['bulk_unfavorite_services'])) {

    validateCSRFToken();

    $favorite = isset($_POST['bulk_favorite_services']) ? 1 : 0;
    $count = 0;
    foreach (servicesForBulk(2) as $service_id => $row) {
        mysqli_query($mysqli, "UPDATE services SET service_favorite = $favorite WHERE service_id = $service_id");
        $count++;
    }

    flashAlert(($favorite ? 'Starred' : 'Unstarred') . " <strong>$count</strong> service" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_archive_services'])) {

    validateCSRFToken();

    $count = 0;
    foreach (servicesForBulk(2) as $row) {
        if (!$row['service_archived_at']) {
            serviceArchive($row);
            $count++;
        }
    }

    flashAlert("Archived <strong>$count</strong> service" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_services'])) {

    validateCSRFToken();

    $count = 0;
    foreach (servicesForBulk(2) as $row) {
        if ($row['service_archived_at']) {
            serviceRestore($row);
            $count++;
        }
    }

    flashAlert("Restored <strong>$count</strong> service" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_services'])) {

    validateCSRFToken();

    $count = 0;
    foreach (servicesForBulk(3) as $row) {
        serviceDelete($row);
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> service" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_services')) {

    validateCSRFToken();

    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_services']);
    $filter_summary = [];

    $client_id = intval($_POST['client_id'] ?? 0);
    $where = [clientScopeSql('service_client_id')];
    if ($client_id) {
        enforceClientAccess();
        $where[] = "AND service_client_id = $client_id";
        $file_name_prepend = getFieldById('clients', $client_id, 'client_name') . '-';
        $filter_summary['Client'] = getFieldById('clients', $client_id, 'client_name');
    } else {
        $file_name_prepend = "$session_company_name-";
        $where[] = 'AND clients.client_archived_at IS NULL';
        if (intval($_POST['client'] ?? 0)) {
            $where[] = 'AND service_client_id = ' . intval($_POST['client']);
            $filter_summary['Client'] = getFieldById('clients', intval($_POST['client']), 'client_name');
        }
    }

    $views = serviceViews();
    $show = (string) ($_POST['show'] ?? '');
    if (!isset($views[$show])) {
        $show = '';
    }
    if ($views[$show]['where'] !== '') {
        $where[] = 'AND ' . $views[$show]['where'];
    }
    $filter_summary['Showing'] = $views[$show]['label'];

    if (in_array($_POST['importance'] ?? '', SERVICE_IMPORTANCE, true)) {
        $where[] = "AND service_importance = '{$_POST['importance']}'";
        $filter_summary['Importance'] = $_POST['importance'];
    }
    $category = trim((string) ($_POST['category'] ?? ''));
    if ($category !== '') {
        $where[] = "AND service_category = '" . escapeSql($category) . "'";
        $filter_summary['Category'] = $category;
    }

    $where[] = tagExportFilterSql('service', $_POST['tags'] ?? [], $filter_summary, 'services.service_id');

    $q = escapeSql($_POST['q'] ?? '');
    if ($q !== '') {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query($mysqli, "SELECT services.*, client_name, " . tagNamesSql('service', 'services.service_id') . " AS service_tags FROM services
        LEFT JOIN clients ON client_id = service_client_id
        WHERE (service_name LIKE '%$q%' OR service_description LIKE '%$q%' OR service_category LIKE '%$q%' OR client_name LIKE '%$q%'
            " . tagSearchSql('service', $q, 'services.service_id') . ")
        " . implode(' ', $where) . "
        ORDER BY client_name ASC, FIELD(service_importance, 'High', 'Medium', 'Low'), service_name ASC");

    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['service_id'])] = $row;
    }
    $num_rows = count($rows);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $ids = array_keys($rows);
        $in = implode(',', $ids);
        $counts = serviceCounts($ids);
        $issues = serviceIssues($ids);

        // The names of what each one depends on, for the columns that list them
        $names = [];
        foreach (['asset', 'vendor', 'contact', 'domain'] as $type) {
            $relation = linkRelation('service', $type);
            $entity = LINK_ENTITIES[$type];
            $sql = mysqli_query($mysqli, "SELECT l.service_id, t.{$entity['name']} FROM {$relation['table']} l
                JOIN {$entity['table']} t ON t.{$entity['id']} = l.{$entity['id']} AND t.{$entity['archived']} IS NULL
                WHERE l.service_id IN ($in) ORDER BY t.{$entity['name']}");
            while ($name_row = mysqli_fetch_row($sql)) {
                $names[intval($name_row[0])][$type][] = $name_row[1];
            }
        }

        $export = beginExport('services', $format, $file_name_prepend . 'Services', 'Services', summarizeExportFilters($filter_summary));

        foreach ($rows as $service_id => $row) {
            $review = serviceReview($row);
            $row['service_assets'] = implode(', ', $names[$service_id]['asset'] ?? []);
            $row['service_vendors'] = implode(', ', $names[$service_id]['vendor'] ?? []);
            $row['service_contacts'] = implode(', ', $names[$service_id]['contact'] ?? []);
            $row['service_domains'] = implode(', ', $names[$service_id]['domain'] ?? []);
            $row['service_attention'] = implode('; ', array_column($issues[$service_id], 'text'));
            $row['service_review'] = $review['date'] ? $review['date'] . ($review['state'] === 'overdue' ? ' (overdue)' : '') : '';
            $row['service_reviewed'] = $row['service_reviewed_at'] ? substr($row['service_reviewed_at'], 0, 10) : '';
            $row['service_review_every'] = intval($row['service_review_interval']) ? (SERVICE_REVIEW_INTERVALS[intval($row['service_review_interval'])] ?? '') : '';
            $row['open_tickets'] = $counts[$service_id]['open_tickets'];
            $row['service_state'] = $row['service_archived_at'] ? 'Archived' : 'Active';
            $row['service_tags'] = (string) $row['service_tags'];
            addExportRow($export, $row);
        }

        finishExport($export);

        logAudit("Service", "Export", "$session_name exported $num_rows service(s) to a " . strtoupper($format) . " file", $client_id);

        exit;
    }

    flashAlert('No services match - nothing to export.', 'error');

    redirect();

}
