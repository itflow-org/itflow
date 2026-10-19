<?php

/*
 * ITFlow - the audit log, read: who did what, when, from where - every change,
 * sign-in, look at a secret and export logAudit() (functions/logging.php)
 * writes to the logs table. Admin > Audit Logs lists it (admin/audit_logs.php)
 * in views, filtered and exported the same way (auditLogFiltersSql()), each
 * entry with its record when it still exists (auditLogRecords()) and a quick
 * look (admin/modals/audit_log/audit_log.php). A client's Audit Log
 * (agent/audit_log.php) and Your account > Activity list the same way, and
 * record pages show their own history (auditLogHistory()) - for an agent,
 * only the kinds of entry their role can see (auditLogRoleSql()).
 *
 * An entry's log_type says what kind of record it is about and log_entity_id
 * which one; log_action is what was done, one verb (Create, Edit, Delete,
 * Archive, Restore, ...); log_description says it in words, starting with who
 * did it.
 */

/*
 * The kinds of record an entry can be about, by its log_type - each with its
 * icon, the part of ITFlow ('module') whose read access lets an agent see
 * entries of that kind (none: admins only - sign-ins, users, settings,
 * templates and the like), and, for the ones that are records, where to find
 * the record: its table, id and name columns, its client column, and its
 * page ('url') - for a record with a quick look rather than a page, its list
 * with ?open= (or ?share=), which opens it. {id} is the record, {client} its
 * client, and {client_q} "client_id=N&" when it has one.
 */
function auditLogKinds() {
    return [
        'Client' => ['icon' => 'fa-user-friends', 'module' => 'module_client', 'table' => 'clients', 'id' => 'client_id', 'name' => 'client_name', 'url' => '/agent/client_overview.php?client_id={id}'],
        'Contact' => ['icon' => 'fa-address-card', 'module' => 'module_client', 'table' => 'contacts', 'id' => 'contact_id', 'name' => 'contact_name', 'client' => 'contact_client_id', 'url' => '/agent/contact.php?client_id={client}&contact_id={id}'],
        'Location' => ['icon' => 'fa-map-marker-alt', 'module' => 'module_client', 'table' => 'locations', 'id' => 'location_id', 'name' => 'location_name', 'client' => 'location_client_id', 'url' => '/agent/location.php?client_id={client}&location_id={id}'],
        'Vendor' => ['icon' => 'fa-building', 'module' => 'module_client', 'table' => 'vendors', 'id' => 'vendor_id', 'name' => 'vendor_name', 'client' => 'vendor_client_id', 'url' => '/agent/vendor.php?{client_q}vendor_id={id}'],
        'Asset' => ['icon' => 'fa-desktop', 'module' => 'module_support', 'table' => 'assets', 'id' => 'asset_id', 'name' => 'asset_name', 'client' => 'asset_client_id', 'url' => '/agent/asset.php?client_id={client}&asset_id={id}'],
        'Network' => ['icon' => 'fa-network-wired', 'module' => 'module_support', 'table' => 'networks', 'id' => 'network_id', 'name' => 'network_name', 'client' => 'network_client_id', 'url' => '/agent/network.php?client_id={client}&network_id={id}'],
        'Rack' => ['icon' => 'fa-server', 'module' => 'module_support', 'table' => 'racks', 'id' => 'rack_id', 'name' => 'rack_name', 'client' => 'rack_client_id', 'url' => '/agent/rack.php?{client_q}rack_id={id}'],
        'Credential' => ['icon' => 'fa-key', 'module' => 'module_credential', 'table' => 'credentials', 'id' => 'credential_id', 'name' => 'credential_name', 'client' => 'credential_client_id', 'url' => '/agent/credentials.php?{client_q}open={id}'],
        'Certificate' => ['icon' => 'fa-lock', 'module' => 'module_support', 'table' => 'certificates', 'id' => 'certificate_id', 'name' => 'certificate_name', 'client' => 'certificate_client_id', 'url' => '/agent/certificates.php?{client_q}open={id}'],
        'Domain' => ['icon' => 'fa-globe', 'module' => 'module_support', 'table' => 'domains', 'id' => 'domain_id', 'name' => 'domain_name', 'client' => 'domain_client_id', 'url' => '/agent/domains.php?{client_q}open={id}'],
        'Software' => ['icon' => 'fa-cube', 'module' => 'module_support', 'table' => 'software', 'id' => 'software_id', 'name' => 'software_name', 'client' => 'software_client_id', 'url' => '/agent/license.php?client_id={client}&software_id={id}'],
        'Service' => ['icon' => 'fa-stream', 'module' => 'module_support', 'table' => 'services', 'id' => 'service_id', 'name' => 'service_name', 'client' => 'service_client_id', 'url' => '/agent/service.php?client_id={client}&service_id={id}'],
        'Document' => ['icon' => 'fa-file-alt', 'module' => 'module_support', 'table' => 'documents', 'id' => 'document_id', 'name' => 'document_name', 'client' => 'document_client_id', 'url' => '/agent/document.php?client_id={client}&document_id={id}'],
        'File' => ['icon' => 'fa-file', 'module' => 'module_client', 'table' => 'files', 'id' => 'file_id', 'name' => 'file_name', 'client' => 'file_client_id', 'url' => '/agent/files.php?client_id={client}&open={id}'],
        'Share' => ['icon' => 'fa-share-alt', 'module' => 'module_support', 'table' => 'shared_items', 'id' => 'item_id', 'name' => 'item_name', 'client' => 'item_client_id', 'url' => '/agent/shares.php?{client_q}share={id}'],
        'Ticket' => ['icon' => 'fa-life-ring', 'module' => 'module_support', 'table' => 'tickets', 'id' => 'ticket_id', 'name' => "CONCAT(ticket_prefix, ticket_number, ' - ', ticket_subject)", 'client' => 'ticket_client_id', 'url' => '/agent/ticket.php?{client_q}ticket_id={id}'],
        'Project' => ['icon' => 'fa-project-diagram', 'module' => 'module_support', 'table' => 'projects', 'id' => 'project_id', 'name' => "CONCAT(project_prefix, project_number, ' - ', project_name)", 'client' => 'project_client_id', 'url' => '/agent/project.php?{client_q}project_id={id}'],
        'Quote' => ['icon' => 'fa-comment-dollar', 'module' => 'module_sales', 'table' => 'quotes', 'id' => 'quote_id', 'name' => "CONCAT(quote_prefix, quote_number)", 'client' => 'quote_client_id', 'url' => '/agent/quote.php?quote_id={id}'],
        'Invoice' => ['icon' => 'fa-file-invoice', 'module' => 'module_sales', 'table' => 'invoices', 'id' => 'invoice_id', 'name' => "CONCAT(invoice_prefix, invoice_number)", 'client' => 'invoice_client_id', 'url' => '/agent/invoice.php?invoice_id={id}'],
        'Recurring Invoice' => ['icon' => 'fa-redo-alt', 'module' => 'module_sales', 'table' => 'recurring_invoices', 'id' => 'recurring_invoice_id', 'name' => "CONCAT(recurring_invoice_prefix, recurring_invoice_number)", 'client' => 'recurring_invoice_client_id', 'url' => '/agent/recurring_invoice.php?recurring_invoice_id={id}'],
        'Product' => ['icon' => 'fa-box-open', 'module' => 'module_sales', 'table' => 'products', 'id' => 'product_id', 'name' => 'product_name', 'url' => '/agent/product.php?product_id={id}'],
        'Expense' => ['icon' => 'fa-shopping-cart', 'module' => 'module_financial', 'table' => 'expenses', 'id' => 'expense_id', 'name' => 'expense_description', 'client' => 'expense_client_id', 'url' => '/agent/expenses.php?open={id}'],
        'Recurring Expense' => ['icon' => 'fa-redo-alt', 'module' => 'module_financial', 'table' => 'recurring_expenses', 'id' => 'recurring_expense_id', 'name' => 'recurring_expense_description', 'client' => 'recurring_expense_client_id', 'url' => '/agent/recurring_expenses.php?open={id}'],
        'Trip' => ['icon' => 'fa-route', 'module' => 'module_financial', 'table' => 'trips', 'id' => 'trip_id', 'name' => 'trip_purpose', 'client' => 'trip_client_id', 'url' => '/agent/trips.php?open={id}'],
        'Ticket Template' => ['icon' => 'fa-life-ring', 'table' => 'ticket_templates', 'id' => 'ticket_template_id', 'name' => 'ticket_template_name', 'url' => '/admin/ticket_template.php?ticket_template_id={id}'],
        'Project Template' => ['icon' => 'fa-project-diagram', 'table' => 'project_templates', 'id' => 'project_template_id', 'name' => 'project_template_name', 'url' => '/admin/project_template.php?project_template_id={id}'],
        'Document Template' => ['icon' => 'fa-file-alt', 'table' => 'document_templates', 'id' => 'document_template_id', 'name' => 'document_template_name', 'url' => '/admin/document_template.php?document_template_id={id}'],
        'Software Template' => ['icon' => 'fa-cube', 'table' => 'software_templates', 'id' => 'software_template_id', 'name' => 'software_template_name', 'url' => '/admin/software_template.php?software_template_id={id}'],

        // Not one record to open, or nothing to open it with - just an icon
        'Login' => ['icon' => 'fa-sign-in-alt'],
        'Login 2FA' => ['icon' => 'fa-shield-alt'],
        'Logout' => ['icon' => 'fa-sign-out-alt'],
        'Client Login' => ['icon' => 'fa-door-open', 'module' => 'module_client'],
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
        'Calendar' => ['icon' => 'fa-calendar-alt', 'module' => 'module_client'],
        'Calendar Event' => ['icon' => 'fa-calendar-day', 'module' => 'module_client'],
        'Recurring Ticket' => ['icon' => 'fa-redo-alt', 'module' => 'module_support'],
        'Task' => ['icon' => 'fa-tasks', 'module' => 'module_support'],
        'Ticket Reply' => ['icon' => 'fa-reply', 'module' => 'module_support'],
        'Folder' => ['icon' => 'fa-folder', 'module' => 'module_client'],
        'Document Version' => ['icon' => 'fa-history', 'module' => 'module_support'],
        'Asset Interface' => ['icon' => 'fa-ethernet', 'module' => 'module_support'],
        'IP Address' => ['icon' => 'fa-sitemap', 'module' => 'module_support'],
        'Payment' => ['icon' => 'fa-credit-card', 'module' => 'module_financial'],
        'Income' => ['icon' => 'fa-hand-holding-usd', 'module' => 'module_financial'],
        'Revenue' => ['icon' => 'fa-hand-holding-usd', 'module' => 'module_financial'],
        'Credit' => ['icon' => 'fa-wallet', 'module' => 'module_sales'],
        'Account' => ['icon' => 'fa-piggy-bank', 'module' => 'module_financial'],
        'Account Transfer' => ['icon' => 'fa-exchange-alt', 'module' => 'module_financial'],
        'Transaction' => ['icon' => 'fa-exchange-alt', 'module' => 'module_financial'],
        'Reconciliation' => ['icon' => 'fa-balance-scale', 'module' => 'module_financial'],
        'Budget' => ['icon' => 'fa-chart-pie', 'module' => 'module_financial'],
        'Tax' => ['icon' => 'fa-percent'],
        'Stripe' => ['icon' => 'fa-stripe-s', 'module' => 'module_financial'],
        'Payment Provider' => ['icon' => 'fa-credit-card'],
        'Payment Method' => ['icon' => 'fa-credit-card', 'module' => 'module_financial'],
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
        'Report' => ['icon' => 'fa-chart-bar', 'module' => 'module_reporting'],
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
        'Success' => ['success', 'fa-check-circle'], 'Alert' => ['danger', 'fa-bell'], 'Failed' => ['danger', 'fa-times-circle'], 'Failure' => ['danger', 'fa-times-circle'], 'MFA Failed' => ['danger', 'fa-shield-alt'], 'Blocked' => ['danger', 'fa-ban'], 'Denied' => ['danger', 'fa-ban'],
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
 * is the sign-ins that failed or were turned away and the admins told about
 * them, API calls turned away, access denied, the portal viewed as a contact,
 * and changes to users, roles, API keys, the master key, settings and
 * backups; data out is passwords looked at, exports, downloads and shared
 * links.
 */
function auditLogViews() {
    $signins = "log_type IN ('Login', 'Login 2FA', 'Logout', 'Client Login')";
    $data_out = "(log_action IN ('View', 'View TOTP', 'Export', 'Download', 'Share') OR log_type IN ('Share', 'Sharing'))";
    return [
        '' => ['label' => 'All', 'icon' => 'fa-list', 'title' => 'Everything', 'where' => ''],
        'changes' => ['label' => 'Changes', 'icon' => 'fa-pen', 'title' => 'Records made, changed, archived, restored and deleted', 'where' => "NOT $signins AND NOT $data_out"],
        'deletions' => ['label' => 'Deletions', 'icon' => 'fa-trash-alt', 'title' => 'Records deleted for good', 'where' => "log_action IN ('Delete', 'Bulk Delete')"],
        'signins' => ['label' => 'Sign-ins', 'icon' => 'fa-sign-in-alt', 'title' => 'Agents and portal contacts signing in and out', 'where' => $signins],
        'security' => ['label' => 'Security', 'icon' => 'fa-shield-alt', 'title' => 'Failed and turned away sign-ins and the alerts about them, API calls turned away, access denied, the portal viewed as a contact, and changes to users, roles, API keys, the master key, settings and backups',
            'where' => "(($signins AND log_action IN ('Failed', 'MFA Failed', 'Blocked', 'Alert')) OR (log_type = 'API' AND log_action = 'Failed') OR log_action IN ('Denied', 'View As') OR log_type IN ('User', 'User Account', 'User Role', 'API Key', 'Master Key', 'Settings', 'Backup', 'Identity Provider'))"],
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
 * cron, the API, the email parser. A failed sign in names the account it was
 * to, marked Their account - it may not have been them.
 */
function auditLogWho(array $row) {
    $user_id = intval($row['log_user_id']);
    if (!$user_id || $row['user_name'] === null) {
        $name = $user_id ? 'A user no longer here' : 'ITFlow';
        return ['name' => $name, 'kind' => 'system', 'html' => '<span class="d-inline-flex align-items-center gap-2 text-body-secondary"><span class="itflow-asset-icon itflow-asset-icon-xs itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw ' . ($user_id ? 'fa-user-slash' : 'fa-cog') . '"></i></span>' . escapeHtml($name) . '</span>'];
    }
    $portal = intval($row['user_type'] ?? 1) === 2;
    // A failed sign in is to their account, not necessarily by them
    $against = in_array($row['log_type'] ?? '', SIGN_IN_TYPES, true) && in_array($row['log_action'] ?? '', SIGN_IN_FAILED, true);
    $html = '<span class="d-inline-flex align-items-center gap-2 text-nowrap">' . userAvatar($user_id, $row['user_name'], (string) ($row['user_avatar'] ?? ''), 'xs')
        . '<span class="text-truncate">' . escapeHtml($row['user_name']) . '</span>'
        . ($portal ? '<span class="badge rounded-pill text-bg-light border fw-normal" title="A client contact, in the client portal">Portal</span>' : '')
        . ($against ? '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal" title="A failed sign in to their account - not necessarily by them" data-their-account>Their account</span>' : '') . '</span>';
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

/*
 * What of the audit log this agent may see, as SQL on logs: everything for
 * an admin; otherwise the kinds of entry whose part of ITFlow their role can
 * read (auditLogKinds() 'module') - not sign-ins, users, settings or the
 * like - and never another agent turned away from a client.
 */
function auditLogRoleSql() {
    global $session_is_admin;
    if (!empty($session_is_admin)) {
        return '1 = 1';
    }
    $types = [];
    foreach (auditLogKinds() as $type => $kind) {
        if (!empty($kind['module']) && lookupUserPermission($kind['module']) >= 1) {
            $types[] = "'" . escapeSql($type) . "'";
        }
    }
    return $types ? "log_type IN (" . implode(', ', $types) . ") AND log_action <> 'Denied'" : '1 = 0';
}

/*
 * A record's history - its audit log entries, newest first, that this agent
 * may see: ['type', 'id', 'client_id', 'rows' (up to $limit, with who did
 * it), 'count' (all of them)].
 */
function auditLogHistory($type, $id, $limit = 50) {
    global $mysqli;
    $type = (string) $type;
    $id = intval($id);
    $limit = max(1, intval($limit));
    $where = "log_type = '" . escapeSql($type) . "' AND log_entity_id = $id AND " . auditLogRoleSql();
    $history = ['type' => $type, 'id' => $id, 'client_id' => 0, 'rows' => [], 'count' => 0];
    if ($id <= 0) {
        return $history;
    }
    $history['count'] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $where"))[0]);
    $sql = mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_ip, log_user_agent, log_created_at, log_client_id, log_user_id, log_entity_id,
            user_name, user_avatar, user_type
        FROM logs LEFT JOIN users ON user_id = log_user_id AND log_user_id > 0
        WHERE $where
        ORDER BY log_id DESC LIMIT $limit");
    while ($row = mysqli_fetch_assoc($sql)) {
        $history['rows'][] = $row;
        $history['client_id'] = $history['client_id'] ?: intval($row['log_client_id']);
    }
    return $history;
}

/*
 * Where all of a record's history is: Admin > Audit Logs for an admin, the
 * client's Audit Log for anyone else when the record is a client's, and
 * nowhere ('') otherwise.
 */
function auditLogHistoryUrl(array $history, $client_id = 0) {
    global $session_is_admin;
    $query = 'type=' . rawurlencode($history['type']) . '&entity=' . intval($history['id']);
    if (!empty($session_is_admin)) {
        return '/admin/audit_logs.php?' . $query;
    }
    $client_id = intval($client_id) ?: intval($history['client_id']);
    return $client_id ? "/agent/audit_log.php?client_id=$client_id&$query" : '';
}

/*
 * A record's history as a list - each entry's action as a tile in its tone,
 * what it says, who did it with their picture, when and, for an admin, from
 * where - with how many more there are and a link to all of them. $opts:
 * 'client_id' (the record's client, for the link), 'empty' (what to say when
 * there is nothing), 'flush' (false for a list with its own border, in a
 * quick look), 'compact' (no IP address, for a narrow card).
 */
function auditLogHistoryHtml(array $history, array $opts = []) {
    global $session_is_admin;
    if (!$history['rows']) {
        return '<div class="itflow-asset-empty" data-audit-history-empty>' . escapeHtml($opts['empty'] ?? 'Nothing logged yet.') . '</div>';
    }
    $flush = $opts['flush'] ?? true;
    $html = '<ul class="list-group ' . ($flush ? 'list-group-flush' : '') . ' itflow-audit-history" data-audit-history="' . escapeHtml($history['type']) . '">';
    foreach ($history['rows'] as $row) {
        $style = auditLogActionStyle($row['log_action']);
        $who = auditLogWho($row);
        $ip = trim((string) $row['log_ip']);
        $html .= '<li class="list-group-item d-flex gap-3 py-2" data-log-id="' . intval($row['log_id']) . '">'
            . '<span class="itflow-asset-icon itflow-asset-icon-sm bg-' . $style['tone'] . '-subtle text-' . $style['tone'] . '-emphasis" title="' . escapeHtml($row['log_action']) . '"><i class="fas fa-fw ' . $style['icon'] . '"></i></span>'
            . '<div class="flex-grow-1 min-w-0">'
            . '<div class="text-break">' . escapeHtml(auditLogDescription($row)) . '</div>'
            . '<div class="small text-body-secondary d-flex flex-wrap align-items-center column-gap-2 row-gap-1 mt-1">' . $who['html']
            . '<span title="' . escapeHtml($row['log_created_at']) . '">' . escapeHtml(timeAgo($row['log_created_at'])) . '</span>'
            . (!empty($session_is_admin) && empty($opts['compact']) && $ip !== '' ? '<span class="font-monospace" title="' . escapeHtml(auditLogDevice($row['log_user_agent'])) . '">' . escapeHtml($ip) . '</span>' : '')
            . '</div></div></li>';
    }
    $html .= '</ul>';

    $more = $history['count'] - count($history['rows']);
    $url = auditLogHistoryUrl($history, $opts['client_id'] ?? 0);
    if ($more > 0 || $url !== '') {
        $html .= '<div class="d-flex flex-wrap align-items-center gap-2 small px-3 py-2 ' . ($flush ? 'border-top' : '') . '" data-audit-history-more>'
            . ($more > 0 ? '<span class="text-body-secondary">and ' . number_format($more) . ' more</span>' : '')
            . ($url !== '' ? '<a class="ms-auto" href="' . escapeHtml($url) . '"><i class="fas fa-fw fa-history me-1"></i>' . ($history['count'] > 1 ? 'All ' . number_format($history['count']) . ' in the audit log' : 'In the audit log') . '</a>' : '')
            . '</div>';
    }
    return $html;
}

/** A record's history as a card of its own, for a page without tabs - narrow, so compact. $opts as auditLogHistoryHtml(), and 'title'. */
function auditLogHistoryCard(array $history, array $opts = []) {
    $opts += ['compact' => true];
    return '<div class="card mb-3" data-audit-history-card>'
        . '<div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-history me-2 text-secondary"></i>' . escapeHtml($opts['title'] ?? 'History')
        . ($history['count'] ? ' <span class="text-body-secondary fw-normal">(' . number_format($history['count']) . ')</span>' : '') . '</h3></div>'
        . auditLogHistoryHtml($history, $opts)
        . '</div>';
}

/*
 * A record's history folded away at the foot of its quick look - History
 * with its count, opening to the list. Nothing when it has none.
 */
function auditLogHistorySection(array $history, array $opts = []) {
    if (!$history['count']) {
        return '';
    }
    $id = 'auditHistory' . bin2hex(random_bytes(4));
    return '<div class="mt-3" data-audit-history-section>'
        . '<button class="btn btn-link btn-sm p-0 text-decoration-none small fw-semibold text-uppercase text-body-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#' . $id . '" aria-expanded="false" aria-controls="' . $id . '">'
        . '<i class="fas fa-fw fa-history me-1"></i>History <span class="badge rounded-pill text-bg-light border fw-normal ms-1">' . number_format($history['count']) . '</span><i class="fas fa-chevron-down ms-2 small"></i></button>'
        . '<div class="collapse mt-2" id="' . $id . '">' . auditLogHistoryHtml($history, ['flush' => false] + $opts) . '</div>'
        . '</div>';
}
