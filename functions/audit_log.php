<?php

/*
 * ITFlow - the audit log, read: who did what, when, from where - every change,
 * sign-in, look at a secret and export logAudit() (functions/logging.php)
 * writes to the logs table. Admin > Audit Logs lists it (admin/audit_logs.php)
 * in views, filtered and exported the same way (auditLogFiltersSql()), each
 * entry with its record when it still exists (auditLogRecords()) and a quick
 * look (admin/modals/audit_log/audit_log.php).
 *
 * An entry's log_type says what kind of record it is about and log_entity_id
 * which one; log_action is what was done, one verb (Create, Edit, Delete,
 * Archive, Restore, ...); log_description says it in words, starting with who
 * did it.
 */

/*
 * The kinds of record an entry can be about, by its log_type - each with its
 * icon and, for the ones that are records, where to find the record: its
 * table, id and name columns, its client column, and its page ('url') - for
 * a record with a quick look rather than a page, its list with ?open= (or
 * ?share=), which opens it. {id} is the record, {client} its client, and
 * {client_q} "client_id=N&" when it has one.
 */
function auditLogKinds() {
    return [
        'Client' => ['icon' => 'fa-user-friends', 'table' => 'clients', 'id' => 'client_id', 'name' => 'client_name', 'url' => '/agent/client_overview.php?client_id={id}'],
        'Contact' => ['icon' => 'fa-address-card', 'table' => 'contacts', 'id' => 'contact_id', 'name' => 'contact_name', 'client' => 'contact_client_id', 'url' => '/agent/contact.php?client_id={client}&contact_id={id}'],
        'Location' => ['icon' => 'fa-map-marker-alt', 'table' => 'locations', 'id' => 'location_id', 'name' => 'location_name', 'client' => 'location_client_id', 'url' => '/agent/location.php?client_id={client}&location_id={id}'],
        'Vendor' => ['icon' => 'fa-building', 'table' => 'vendors', 'id' => 'vendor_id', 'name' => 'vendor_name', 'client' => 'vendor_client_id', 'url' => '/agent/vendor.php?{client_q}vendor_id={id}'],
        'Asset' => ['icon' => 'fa-desktop', 'table' => 'assets', 'id' => 'asset_id', 'name' => 'asset_name', 'client' => 'asset_client_id', 'url' => '/agent/asset.php?client_id={client}&asset_id={id}'],
        'Network' => ['icon' => 'fa-network-wired', 'table' => 'networks', 'id' => 'network_id', 'name' => 'network_name', 'client' => 'network_client_id', 'url' => '/agent/network.php?client_id={client}&network_id={id}'],
        'Rack' => ['icon' => 'fa-server', 'table' => 'racks', 'id' => 'rack_id', 'name' => 'rack_name', 'client' => 'rack_client_id', 'url' => '/agent/rack.php?{client_q}rack_id={id}'],
        'Credential' => ['icon' => 'fa-key', 'table' => 'credentials', 'id' => 'credential_id', 'name' => 'credential_name', 'client' => 'credential_client_id', 'url' => '/agent/credentials.php?{client_q}open={id}'],
        'Certificate' => ['icon' => 'fa-lock', 'table' => 'certificates', 'id' => 'certificate_id', 'name' => 'certificate_name', 'client' => 'certificate_client_id', 'url' => '/agent/certificates.php?{client_q}open={id}'],
        'Domain' => ['icon' => 'fa-globe', 'table' => 'domains', 'id' => 'domain_id', 'name' => 'domain_name', 'client' => 'domain_client_id', 'url' => '/agent/domains.php?{client_q}open={id}'],
        'Software' => ['icon' => 'fa-cube', 'table' => 'software', 'id' => 'software_id', 'name' => 'software_name', 'client' => 'software_client_id', 'url' => '/agent/license.php?client_id={client}&software_id={id}'],
        'Service' => ['icon' => 'fa-stream', 'table' => 'services', 'id' => 'service_id', 'name' => 'service_name', 'client' => 'service_client_id', 'url' => '/agent/service.php?client_id={client}&service_id={id}'],
        'Document' => ['icon' => 'fa-file-alt', 'table' => 'documents', 'id' => 'document_id', 'name' => 'document_name', 'client' => 'document_client_id', 'url' => '/agent/document.php?client_id={client}&document_id={id}'],
        'File' => ['icon' => 'fa-file', 'table' => 'files', 'id' => 'file_id', 'name' => 'file_name', 'client' => 'file_client_id', 'url' => '/agent/files.php?client_id={client}&open={id}'],
        'Share' => ['icon' => 'fa-share-alt', 'table' => 'shared_items', 'id' => 'item_id', 'name' => 'item_name', 'client' => 'item_client_id', 'url' => '/agent/shares.php?{client_q}share={id}'],
        'Ticket' => ['icon' => 'fa-life-ring', 'table' => 'tickets', 'id' => 'ticket_id', 'name' => "CONCAT(ticket_prefix, ticket_number, ' - ', ticket_subject)", 'client' => 'ticket_client_id', 'url' => '/agent/ticket.php?{client_q}ticket_id={id}'],
        'Project' => ['icon' => 'fa-project-diagram', 'table' => 'projects', 'id' => 'project_id', 'name' => "CONCAT(project_prefix, project_number, ' - ', project_name)", 'client' => 'project_client_id', 'url' => '/agent/project.php?{client_q}project_id={id}'],
        'Quote' => ['icon' => 'fa-comment-dollar', 'table' => 'quotes', 'id' => 'quote_id', 'name' => "CONCAT(quote_prefix, quote_number)", 'client' => 'quote_client_id', 'url' => '/agent/quote.php?quote_id={id}'],
        'Invoice' => ['icon' => 'fa-file-invoice', 'table' => 'invoices', 'id' => 'invoice_id', 'name' => "CONCAT(invoice_prefix, invoice_number)", 'client' => 'invoice_client_id', 'url' => '/agent/invoice.php?invoice_id={id}'],
        'Recurring Invoice' => ['icon' => 'fa-redo-alt', 'table' => 'recurring_invoices', 'id' => 'recurring_invoice_id', 'name' => "CONCAT(recurring_invoice_prefix, recurring_invoice_number)", 'client' => 'recurring_invoice_client_id', 'url' => '/agent/recurring_invoice.php?recurring_invoice_id={id}'],
        'Product' => ['icon' => 'fa-box-open', 'table' => 'products', 'id' => 'product_id', 'name' => 'product_name', 'url' => '/agent/product.php?product_id={id}'],
        'Expense' => ['icon' => 'fa-shopping-cart', 'table' => 'expenses', 'id' => 'expense_id', 'name' => 'expense_description', 'client' => 'expense_client_id', 'url' => '/agent/expenses.php?open={id}'],
        'Recurring Expense' => ['icon' => 'fa-redo-alt', 'table' => 'recurring_expenses', 'id' => 'recurring_expense_id', 'name' => 'recurring_expense_description', 'client' => 'recurring_expense_client_id', 'url' => '/agent/recurring_expenses.php?open={id}'],
        'Trip' => ['icon' => 'fa-route', 'table' => 'trips', 'id' => 'trip_id', 'name' => 'trip_purpose', 'client' => 'trip_client_id', 'url' => '/agent/trips.php?open={id}'],
        'Ticket Template' => ['icon' => 'fa-life-ring', 'table' => 'ticket_templates', 'id' => 'ticket_template_id', 'name' => 'ticket_template_name', 'url' => '/admin/ticket_template.php?ticket_template_id={id}'],
        'Project Template' => ['icon' => 'fa-project-diagram', 'table' => 'project_templates', 'id' => 'project_template_id', 'name' => 'project_template_name', 'url' => '/admin/project_template.php?project_template_id={id}'],
        'Document Template' => ['icon' => 'fa-file-alt', 'table' => 'document_templates', 'id' => 'document_template_id', 'name' => 'document_template_name', 'url' => '/admin/document_template.php?document_template_id={id}'],
        'Software Template' => ['icon' => 'fa-cube', 'table' => 'software_templates', 'id' => 'software_template_id', 'name' => 'software_template_name', 'url' => '/admin/software_template.php?software_template_id={id}'],

        // Not one record to open, or nothing to open it with - just an icon
        'Login' => ['icon' => 'fa-sign-in-alt'],
        'Login 2FA' => ['icon' => 'fa-shield-alt'],
        'Logout' => ['icon' => 'fa-sign-out-alt'],
        'Client Login' => ['icon' => 'fa-door-open'],
        'User' => ['icon' => 'fa-user'],
        'User Account' => ['icon' => 'fa-user-cog'],
        'User Role' => ['icon' => 'fa-user-shield'],
        'API' => ['icon' => 'fa-plug'],
        'API Key' => ['icon' => 'fa-key'],
        'Master Key' => ['icon' => 'fa-user-secret'],
        'Settings' => ['icon' => 'fa-cog'],
        'Backup' => ['icon' => 'fa-cloud-upload-alt'],
        'App' => ['icon' => 'fa-download'],
        'Cron' => ['icon' => 'fa-clock'],
        'Calendar' => ['icon' => 'fa-calendar-alt'],
        'Calendar Event' => ['icon' => 'fa-calendar-day'],
        'Recurring Ticket' => ['icon' => 'fa-redo-alt'],
        'Task' => ['icon' => 'fa-tasks'],
        'Ticket Reply' => ['icon' => 'fa-reply'],
        'Folder' => ['icon' => 'fa-folder'],
        'Document Version' => ['icon' => 'fa-history'],
        'Asset Interface' => ['icon' => 'fa-ethernet'],
        'IP Address' => ['icon' => 'fa-sitemap'],
        'Payment' => ['icon' => 'fa-credit-card'],
        'Income' => ['icon' => 'fa-hand-holding-usd'],
        'Revenue' => ['icon' => 'fa-hand-holding-usd'],
        'Credit' => ['icon' => 'fa-wallet'],
        'Account' => ['icon' => 'fa-piggy-bank'],
        'Account Transfer' => ['icon' => 'fa-exchange-alt'],
        'Transaction' => ['icon' => 'fa-exchange-alt'],
        'Reconciliation' => ['icon' => 'fa-balance-scale'],
        'Budget' => ['icon' => 'fa-chart-pie'],
        'Tax' => ['icon' => 'fa-percent'],
        'Stripe' => ['icon' => 'fa-stripe-s'],
        'Payment Provider' => ['icon' => 'fa-credit-card'],
        'Payment Method' => ['icon' => 'fa-credit-card'],
        'Email' => ['icon' => 'fa-envelope'],
        'Bulk Mail' => ['icon' => 'fa-mail-bulk'],
        'Notification' => ['icon' => 'fa-bell'],
        'Announcement' => ['icon' => 'fa-bullhorn'],
        'Tag' => ['icon' => 'fa-tag'],
        'Category' => ['icon' => 'fa-list-ul'],
        'Ticket Status' => ['icon' => 'fa-info-circle'],
        'SLA' => ['icon' => 'fa-stopwatch'],
        'Canned Response' => ['icon' => 'fa-comment-dots'],
        'Contract Template' => ['icon' => 'fa-file-contract'],
        'Vendor Template' => ['icon' => 'fa-building'],
        'Custom Field' => ['icon' => 'fa-th-list'],
        'Custom Link' => ['icon' => 'fa-external-link-alt'],
        'AI Provider' => ['icon' => 'fa-robot'],
        'AI Model' => ['icon' => 'fa-robot'],
        'Starter Content' => ['icon' => 'fa-seedling'],
        'Demo Data' => ['icon' => 'fa-flask'],
        'Report' => ['icon' => 'fa-chart-bar'],
        'Sharing' => ['icon' => 'fa-share-alt'],
        'Audit Log' => ['icon' => 'fa-history'],
    ];
}

/** An entry's icon, by its type. */
function auditLogTypeIcon($type) {
    return auditLogKinds()[(string) $type]['icon'] ?? 'fa-circle';
}

/*
 * How an action looks - its tone (a Bootstrap colour) and icon. Making
 * things green, changing them blue, taking them away red, a failed or blocked
 * sign-in red, a look at a secret or a file leaving amber, the rest grey.
 */
function auditLogActionStyle($action) {
    $action = (string) $action;
    $styles = [
        'Create' => ['success', 'fa-plus'], 'Bulk Create' => ['success', 'fa-plus'], 'Import' => ['success', 'fa-file-import'], 'Upload' => ['success', 'fa-upload'],
        'Edit' => ['primary', 'fa-pen'], 'Bulk Edit' => ['primary', 'fa-pen'], 'Update' => ['primary', 'fa-sync-alt'], 'Modify' => ['primary', 'fa-pen'], 'Rename' => ['primary', 'fa-i-cursor'],
        'Move' => ['primary', 'fa-arrows-alt'], 'Merge' => ['primary', 'fa-code-branch'], 'Link' => ['primary', 'fa-link'], 'Unlink' => ['secondary', 'fa-unlink'],
        'Reply' => ['primary', 'fa-reply'], 'Resolve' => ['success', 'fa-check'], 'Close' => ['secondary', 'fa-check-double'], 'Reopen' => ['primary', 'fa-redo'],
        'Delete' => ['danger', 'fa-trash-alt'], 'Bulk Delete' => ['danger', 'fa-trash-alt'], 'Revoke' => ['danger', 'fa-ban'],
        'Archive' => ['secondary', 'fa-archive'], 'Bulk Archive' => ['secondary', 'fa-archive'], 'Restore' => ['info', 'fa-redo'], 'Bulk Restore' => ['info', 'fa-redo'],
        'Success' => ['success', 'fa-check-circle'], 'Failed' => ['danger', 'fa-times-circle'], 'Failure' => ['danger', 'fa-times-circle'], 'MFA Failed' => ['danger', 'fa-shield-alt'], 'Blocked' => ['danger', 'fa-ban'], 'Denied' => ['danger', 'fa-ban'],
        'View' => ['warning', 'fa-eye'], 'View TOTP' => ['warning', 'fa-eye'], 'View As' => ['warning', 'fa-user-secret'], 'Export' => ['warning', 'fa-download'], 'Download' => ['warning', 'fa-download'],
        'Share' => ['warning', 'fa-share-alt'], 'Send' => ['primary', 'fa-paper-plane'], 'Email' => ['primary', 'fa-envelope'], 'Payment' => ['success', 'fa-credit-card'], 'Auto Payment' => ['success', 'fa-credit-card'],
        'Error' => ['danger', 'fa-exclamation-triangle'],
        'Activate' => ['success', 'fa-toggle-on'], 'Disable' => ['secondary', 'fa-ban'], 'Cancel' => ['secondary', 'fa-ban'], 'Bulk Cancel' => ['secondary', 'fa-ban'], 'End' => ['secondary', 'fa-stop-circle'],
        'Dismiss' => ['secondary', 'fa-times'], 'Discard' => ['danger', 'fa-trash-alt'], 'Import Rule Delete' => ['danger', 'fa-trash-alt'], 'Undo' => ['secondary', 'fa-undo'], 'Reimbursement Undo' => ['secondary', 'fa-undo'],
        'Unshare' => ['secondary', 'fa-unlink'], 'Unbill' => ['secondary', 'fa-unlink'], 'Read' => ['warning', 'fa-eye'], 'Keys' => ['primary', 'fa-key'], 'Rotate' => ['primary', 'fa-sync-alt'], 'Refresh' => ['primary', 'fa-sync-alt'],
        'Change' => ['primary', 'fa-pen'], 'Stock' => ['primary', 'fa-boxes'], 'Seats' => ['primary', 'fa-users'], 'Task' => ['primary', 'fa-tasks'], 'Transfer' => ['primary', 'fa-exchange-alt'], 'Bulk Transfer' => ['primary', 'fa-exchange-alt'],
        'Statement' => ['primary', 'fa-file-invoice'], 'Receipt' => ['primary', 'fa-receipt'], 'Queue' => ['primary', 'fa-stream'], 'Run' => ['primary', 'fa-play'], 'Move On' => ['primary', 'fa-forward'],
        'Check' => ['info', 'fa-check'], 'Review' => ['info', 'fa-clipboard-check'], 'Save' => ['success', 'fa-save'], 'Bill' => ['success', 'fa-file-invoice-dollar'], 'Renew' => ['success', 'fa-redo-alt'],
        'Reimburse' => ['success', 'fa-hand-holding-usd'], 'Finish' => ['success', 'fa-flag-checkered'],
    ];
    $style = $styles[$action] ?? ['secondary', 'fa-circle'];
    return ['tone' => $style[0], 'icon' => $style[1]];
}

/** An action as a soft pill, in its tone. */
function auditLogActionPill($action) {
    $style = auditLogActionStyle($action);
    $tone = $style['tone'];
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal text-nowrap"><i class="fas ' . $style['icon'] . ' me-1"></i>' . escapeHtml($action) . '</span>';
}

/*
 * The list's views - key => label, icon, title and the SQL on logs that picks
 * them. Sign-ins are agents and portal contacts signing in and out; security
 * is the sign-ins that failed or were blocked, access denied, the portal
 * viewed as a contact, and changes to users, roles, API keys, the master key,
 * settings and backups; data out is passwords looked at, exports, downloads
 * and shared links.
 */
function auditLogViews() {
    $signins = "log_type IN ('Login', 'Login 2FA', 'Logout', 'Client Login')";
    $data_out = "(log_action IN ('View', 'View TOTP', 'Export', 'Download', 'Share') OR log_type IN ('Share', 'Sharing'))";
    return [
        '' => ['label' => 'All', 'icon' => 'fa-list', 'title' => 'Everything', 'where' => ''],
        'changes' => ['label' => 'Changes', 'icon' => 'fa-pen', 'title' => 'Records made, changed, archived, restored and deleted', 'where' => "NOT $signins AND NOT $data_out"],
        'deletions' => ['label' => 'Deletions', 'icon' => 'fa-trash-alt', 'title' => 'Records deleted for good', 'where' => "log_action IN ('Delete', 'Bulk Delete')"],
        'signins' => ['label' => 'Sign-ins', 'icon' => 'fa-sign-in-alt', 'title' => 'Agents and portal contacts signing in and out', 'where' => $signins],
        'security' => ['label' => 'Security', 'icon' => 'fa-shield-alt', 'title' => 'Failed and blocked sign-ins, access denied, the portal viewed as a contact, and changes to users, roles, API keys, the master key, settings and backups',
            'where' => "(($signins AND log_action IN ('Failed', 'MFA Failed', 'Blocked')) OR log_action IN ('Denied', 'View As') OR log_type IN ('User', 'User Account', 'User Role', 'API Key', 'Master Key', 'Settings', 'Backup', 'Identity Provider'))"],
        'data' => ['label' => 'Data out', 'icon' => 'fa-file-export', 'title' => 'Passwords looked at, exports, downloads and shared links', 'where' => $data_out],
    ];
}

/*
 * The audit log's filters as SQL on logs (joined to users as the person who
 * did it and clients) - from the list page's GET or the export window's POST:
 * q (the description, type, action, IP, browser, who or client), client,
 * user ('system' for ITFlow itself), type, action, entity (one record, with
 * its type), view (auditLogViews()) and dtf / dtt. $summary gets them in
 * words, for an export.
 */
function auditLogFiltersSql(array $src, &$summary = []) {
    $summary = [];
    $sql = '1 = 1';
    // One value each - anything else (a list in the query string) is no filter
    $src = array_map(function ($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    }, $src);

    $q = $src['q'] ?? '';
    if ($q !== '') {
        $like = escapeSql($q);
        $sql .= " AND (log_description LIKE '%$like%' OR log_type LIKE '%$like%' OR log_action LIKE '%$like%' OR log_ip LIKE '%$like%' OR log_user_agent LIKE '%$like%'
            OR user_name LIKE '%$like%' OR client_name LIKE '%$like%')";
        $summary['Search'] = $q;
    }
    $client = intval($src['client'] ?? 0);
    if ($client) {
        $sql .= " AND log_client_id = $client";
        $summary['Client'] = (string) getFieldById('clients', $client, 'client_name');
    }
    $user = $src['user'] ?? '';
    if ($user === 'system') {
        $sql .= " AND log_user_id = 0";
        $summary['Who'] = 'ITFlow itself';
    } elseif (intval($user)) {
        $sql .= " AND log_user_id = " . intval($user);
        $summary['Who'] = (string) getFieldById('users', intval($user), 'user_name');
    }
    foreach (['type' => ['log_type', 'Type'], 'action' => ['log_action', 'Action']] as $key => [$column, $label]) {
        $value = $src[$key] ?? '';
        if ($value !== '') {
            $sql .= " AND $column = '" . escapeSql($value) . "'";
            $summary[$label] = $value;
        }
    }
    $entity = intval($src['entity'] ?? 0);
    if ($entity) {
        $sql .= " AND log_entity_id = $entity";
        $summary['Record'] = "#$entity";
    }
    $views = auditLogViews();
    $view = $src['view'] ?? '';
    if (isset($views[$view]) && $views[$view]['where'] !== '') {
        $sql .= " AND {$views[$view]['where']}";
        $summary['Showing'] = $views[$view]['label'];
    }
    // A half-open range on the column itself, so KEY log_created_at serves it
    $dtf = $src['dtf'] ?? '';
    $dtt = $src['dtt'] ?? '';
    $dtf = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtf) ? $dtf : '1970-01-01';
    $dtt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtt) ? $dtt : '2099-12-31';
    if ($dtf !== '1970-01-01' || $dtt !== '2099-12-31') {
        $sql .= " AND log_created_at >= '$dtf 00:00:00' AND log_created_at < '$dtt' + INTERVAL 1 DAY";
        $range = formatExportDateRange($dtf, $dtt);
        if ($range) {
            $summary['Dated'] = $range;
        }
    }
    return $sql;
}

/** The joins the filters need - who did it, and the client. */
function auditLogJoinsSql() {
    return "LEFT JOIN users ON user_id = log_user_id AND log_user_id > 0 LEFT JOIN clients ON client_id = log_client_id AND log_client_id > 0";
}

/*
 * The records entries are about, where they still exist - ["type|id" =>
 * ['name', 'url']] - one query per kind of record on the page.
 */
function auditLogRecords(array $rows) {
    global $mysqli;
    $kinds = auditLogKinds();
    $wanted = [];
    foreach ($rows as $row) {
        $type = (string) $row['log_type'];
        $id = intval($row['log_entity_id']);
        if ($id > 0 && isset($kinds[$type]['table'])) {
            $wanted[$type][$id] = true;
        }
    }
    $records = [];
    foreach ($wanted as $type => $ids) {
        $kind = $kinds[$type];
        $client = isset($kind['client']) ? $kind['client'] : '0';
        $sql = mysqli_query($mysqli, "SELECT {$kind['id']} AS record_id, {$kind['name']} AS record_name, $client AS record_client
            FROM {$kind['table']} WHERE {$kind['id']} IN (" . implode(',', array_map('intval', array_keys($ids))) . ")");
        while ($sql && $row = mysqli_fetch_assoc($sql)) {
            $id = intval($row['record_id']);
            $client_id = intval($row['record_client']);
            $fill = function ($template) use ($id, $client_id) {
                return str_replace(['{id}', '{client}', '{client_q}'], [$id, $client_id, $client_id ? "client_id=$client_id&" : ''], $template);
            };
            $records["$type|$id"] = ['name' => (string) $row['record_name'], 'url' => $fill($kind['url'])];
        }
    }
    return $records;
}

/** The record an entry is about, as a link - '' when there is none to open, or it is gone. */
function auditLogRecordLink(array $row, array $records, $label = null) {
    $record = $records[$row['log_type'] . '|' . intval($row['log_entity_id'])] ?? null;
    if (!$record) {
        return '';
    }
    $text = $label === null ? escapeHtml($record['name']) : $label;
    return '<a href="' . escapeHtml($record['url']) . '" title="Open ' . escapeHtml($record['name']) . '">' . $text . '</a>';
}

/*
 * What an entry says, without who did it at the start when it is the person
 * shown beside it - "Johnny Test created client Acme" reads "Created client
 * Acme".
 */
function auditLogDescription(array $row) {
    $description = (string) $row['log_description'];
    $name = trim((string) ($row['user_name'] ?? ''));
    if ($name !== '' && stripos($description, $name . ' ') === 0) {
        $description = ucfirst(substr($description, strlen($name) + 1));
    }
    return $description;
}

/*
 * Who did it - ['name', 'kind' (agent, portal - a client contact - or
 * system), 'html' (their avatar or tile and name)]. No one is ITFlow itself:
 * cron, the API, the email parser.
 */
function auditLogWho(array $row) {
    $user_id = intval($row['log_user_id']);
    if (!$user_id || $row['user_name'] === null) {
        $name = $user_id ? 'A user no longer here' : 'ITFlow';
        return ['name' => $name, 'kind' => 'system', 'html' => '<span class="d-inline-flex align-items-center gap-2 text-body-secondary"><span class="itflow-asset-icon itflow-asset-icon-xs itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw ' . ($user_id ? 'fa-user-slash' : 'fa-cog') . '"></i></span>' . escapeHtml($name) . '</span>'];
    }
    $portal = intval($row['user_type'] ?? 1) === 2;
    $html = '<span class="d-inline-flex align-items-center gap-2 text-nowrap">' . userAvatar($user_id, $row['user_name'], (string) ($row['user_avatar'] ?? ''), 'xs')
        . '<span class="text-truncate">' . escapeHtml($row['user_name']) . '</span>'
        . ($portal ? '<span class="badge rounded-pill text-bg-light border fw-normal" title="A client contact, in the client portal">Portal</span>' : '') . '</span>';
    return ['name' => (string) $row['user_name'], 'kind' => $portal ? 'portal' : 'agent', 'html' => $html];
}

/*
 * A browser's user agent as its system and browser, in words - "Windows ·
 * Chrome". One that is not a browser - a script calling the API, curl - is
 * its own first word: "python-requests/2.34.2".
 */
function auditLogDevice($user_agent) {
    $user_agent = trim((string) $user_agent);
    if ($user_agent === '') {
        return '';
    }
    $os = trim(strip_tags(getOS($user_agent)));
    $browser = trim(strip_tags(getWebBrowser($user_agent)));
    $device = implode(' · ', array_filter([$os !== '-' ? $os : '', $browser !== '-' ? $browser : '']));
    return $device !== '' ? $device : mb_strimwidth(strtok($user_agent, ' '), 0, 40, '…');
}
