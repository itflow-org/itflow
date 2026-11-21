<?php

/*
 * ITFlow - Archived (agent/archived.php): what was archived lately, on one page,
 * across the kinds of record agents archive - clients and their contacts, sites,
 * vendors, assets, logins, documents, files, domains, certificates, networks,
 * racks, services and licenses, and projects and products - newest first, each
 * with Restore. Only the kinds the agent can see, for the clients they can reach.
 *
 * Restore on a row goes through that record's own handler (restore_contact and
 * so on). Ticked rows go through archivedRestore() here (bulk_restore_archived in
 * agent/post/archived.php), which does what those handlers do - the contact's
 * portal login comes back with the contact, an asset gets its history line.
 */

/*
 * The kinds: their table and columns, the permission that reads them (2 restores
 * them), the audit log type their Archive is logged under, the handler that
 * restores one, and where one opens. 'client' => '' for a kind with no client.
 */
function archivedKinds() {
    global $config_module_enable_ticketing, $config_module_enable_accounting;
    $kinds = [
        'client' => ['label' => 'Client', 'icon' => 'fa-user-friends', 'table' => 'clients', 'id' => 'client_id', 'name' => 'client_name', 'client' => 'client_id', 'archived' => 'client_archived_at', 'module' => 'module_client', 'log' => 'Client'],
        'contact' => ['label' => 'Contact', 'icon' => 'fa-address-book', 'table' => 'contacts', 'id' => 'contact_id', 'name' => 'contact_name', 'client' => 'contact_client_id', 'archived' => 'contact_archived_at', 'module' => 'module_client', 'log' => 'Contact'],
        'location' => ['label' => 'Location', 'icon' => 'fa-map-marker-alt', 'table' => 'locations', 'id' => 'location_id', 'name' => 'location_name', 'client' => 'location_client_id', 'archived' => 'location_archived_at', 'module' => 'module_client', 'log' => 'Location'],
        'vendor' => ['label' => 'Vendor', 'icon' => 'fa-building', 'table' => 'vendors', 'id' => 'vendor_id', 'name' => 'vendor_name', 'client' => 'vendor_client_id', 'archived' => 'vendor_archived_at', 'module' => 'module_client', 'log' => 'Vendor'],
        'asset' => ['label' => 'Asset', 'icon' => 'fa-desktop', 'table' => 'assets', 'id' => 'asset_id', 'name' => 'asset_name', 'client' => 'asset_client_id', 'archived' => 'asset_archived_at', 'module' => 'module_support', 'log' => 'Asset'],
        'credential' => ['label' => 'Credential', 'icon' => 'fa-key', 'table' => 'credentials', 'id' => 'credential_id', 'name' => 'credential_name', 'client' => 'credential_client_id', 'archived' => 'credential_archived_at', 'module' => 'module_credential', 'log' => 'Credential'],
        'document' => ['label' => 'Document', 'icon' => 'fa-file-alt', 'table' => 'documents', 'id' => 'document_id', 'name' => 'document_name', 'client' => 'document_client_id', 'archived' => 'document_archived_at', 'module' => 'module_support', 'log' => 'Document'],
        'file' => ['label' => 'File', 'icon' => 'fa-paperclip', 'table' => 'files', 'id' => 'file_id', 'name' => 'file_name', 'client' => 'file_client_id', 'archived' => 'file_archived_at', 'module' => 'module_support', 'log' => 'File'],
        'domain' => ['label' => 'Domain', 'icon' => 'fa-globe', 'table' => 'domains', 'id' => 'domain_id', 'name' => 'domain_name', 'client' => 'domain_client_id', 'archived' => 'domain_archived_at', 'module' => 'module_support', 'log' => 'Domain'],
        'certificate' => ['label' => 'Certificate', 'icon' => 'fa-lock', 'table' => 'certificates', 'id' => 'certificate_id', 'name' => 'certificate_name', 'client' => 'certificate_client_id', 'archived' => 'certificate_archived_at', 'module' => 'module_support', 'log' => 'Certificate'],
        'network' => ['label' => 'Network', 'icon' => 'fa-network-wired', 'table' => 'networks', 'id' => 'network_id', 'name' => 'network_name', 'client' => 'network_client_id', 'archived' => 'network_archived_at', 'module' => 'module_support', 'log' => 'Network'],
        'rack' => ['label' => 'Rack', 'icon' => 'fa-server', 'table' => 'racks', 'id' => 'rack_id', 'name' => 'rack_name', 'client' => 'rack_client_id', 'archived' => 'rack_archived_at', 'module' => 'module_support', 'log' => 'Rack'],
        'service' => ['label' => 'Service', 'icon' => 'fa-stream', 'table' => 'services', 'id' => 'service_id', 'name' => 'service_name', 'client' => 'service_client_id', 'archived' => 'service_archived_at', 'module' => 'module_support', 'log' => 'Service'],
        'software' => ['label' => 'License', 'icon' => 'fa-cube', 'table' => 'software', 'id' => 'software_id', 'name' => 'software_name', 'client' => 'software_client_id', 'archived' => 'software_archived_at', 'module' => 'module_support', 'log' => 'License'],
    ];
    if (!empty($config_module_enable_ticketing)) {
        $kinds['project'] = ['label' => 'Project', 'icon' => 'fa-project-diagram', 'table' => 'projects', 'id' => 'project_id', 'name' => 'project_name', 'client' => 'project_client_id', 'archived' => 'project_archived_at', 'module' => 'module_support', 'log' => 'Project'];
    }
    if (!empty($config_module_enable_accounting)) {
        $kinds['product'] = ['label' => 'Product', 'icon' => 'fa-box-open', 'table' => 'products', 'id' => 'product_id', 'name' => 'product_name', 'client' => '', 'archived' => 'product_archived_at', 'module' => 'module_sales', 'log' => 'Product'];
    }
    return $kinds;
}

/** The kinds this agent can read - level 2 restores them. */
function archivedKindsFor($level = 1) {
    return array_filter(archivedKinds(), function ($kind) use ($level) {
        return lookupUserPermission($kind['module']) >= $level;
    });
}

/** May this agent restore this one - level 2 for its kind; our own vendors (no client) are the financial module's, as vendorEnforce() has it. */
function archivedCanRestore($kind_key, $client_id) {
    if (!isset(archivedKindsFor(2)[$kind_key])) {
        return false;
    }
    if ($kind_key === 'vendor' && !intval($client_id)) {
        return lookupUserPermission('module_financial') >= 2;
    }
    return true;
}

/** Can this agent see Archived at all - is there any kind they can read? */
function archivedCan() {
    return (bool) archivedKindsFor(1);
}

/*
 * SQL for every archived record of $kinds (archivedKindsFor()) as kind, id, name,
 * client_id, archived_at - archived in the last $days (0 for any time), for one
 * client when $client_id is set, its name matching $q (already escaped), scoped
 * to the clients the agent can reach. One UNION ALL, to sort and page as one list.
 */
function archivedUnionSql(array $kinds, $days = 0, $client_id = 0, $q = '') {
    $days = intval($days);
    $client_id = intval($client_id);
    $parts = [];
    foreach ($kinds as $key => $kind) {
        if ($client_id && $kind['client'] === '') {
            continue;
        }
        $client_column = $kind['client'] !== '' ? $kind['client'] : '0';
        $where = "{$kind['archived']} IS NOT NULL"
            . ($days ? " AND {$kind['archived']} >= NOW() - INTERVAL $days DAY" : '')
            . ($client_id ? " AND $client_column = $client_id" : '')
            . ($q !== '' ? " AND {$kind['name']} LIKE '%$q%'" : '')
            . ($kind['client'] !== '' ? clientScopeSql($kind['client']) : '');
        $parts[] = "SELECT '$key' AS archived_kind, {$kind['id']} AS archived_id, {$kind['name']} AS archived_name,
            $client_column AS archived_client_id, {$kind['archived']} AS archived_at FROM {$kind['table']} WHERE $where";
    }
    return $parts ? implode("\nUNION ALL\n", $parts) : "SELECT '' AS archived_kind, 0 AS archived_id, '' AS archived_name, 0 AS archived_client_id, NULL AS archived_at FROM DUAL WHERE 0";
}

/** Who archived each row, from the audit log - [kind:id => user name], for one page of rows. */
function archivedBy(array $rows) {
    global $mysqli;
    $kinds = archivedKinds();
    $by = [];
    foreach ($rows as $row) {
        $kind = $kinds[$row['archived_kind']] ?? null;
        if (!$kind) {
            continue;
        }
        $found = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_name FROM logs LEFT JOIN users ON user_id = log_user_id
            WHERE log_type = '" . escapeSql($kind['log']) . "' AND log_action = 'Archive' AND log_entity_id = " . intval($row['archived_id']) . "
            ORDER BY log_id DESC LIMIT 1"));
        if ($found && $found[0] !== null) {
            $by[$row['archived_kind'] . ':' . intval($row['archived_id'])] = $found[0];
        }
    }
    return $by;
}

/** Where an archived record opens - its own page, or its list showing archived ones; '' when there is nowhere. */
function archivedUrl($kind, $id, $client_id, $name = '') {
    $id = intval($id);
    $client_id = intval($client_id);
    $client = $client_id ? "client_id=$client_id&" : '';
    $search = 'archived=1&q=' . rawurlencode((string) $name);
    switch ($kind) {
        case 'client': return "client_overview.php?client_id=$id";
        case 'contact': return "contact.php?{$client}contact_id=$id";
        case 'location': return "location.php?{$client}location_id=$id";
        case 'vendor': return "vendor.php?{$client}vendor_id=$id";
        case 'asset': return "asset.php?{$client}asset_id=$id";
        case 'credential': return $client_id ? "credentials.php?{$client}$search" : '';
        case 'document': return "document.php?{$client}document_id=$id";
        case 'domain': return $client_id ? "domains.php?{$client}$search" : '';
        case 'certificate': return $client_id ? "certificates.php?{$client}$search" : '';
        case 'network': return "network.php?{$client}network_id=$id";
        case 'rack': return "rack.php?{$client}rack_id=$id";
        case 'service': return "service.php?{$client}service_id=$id";
        case 'software': return $client_id ? "software.php?{$client}$search" : '';
        case 'project': return projectUrl($id, $client_id);
        case 'product': return "product.php?product_id=$id";
    }
    return '';
}

/*
 * Restore one, as its own handler would: the agent's level 2 for its kind, a client
 * they can reach, then archived_at cleared, the audit log, and what that handler also
 * brings back. The record's name, or null when it isn't one they may restore.
 */
function archivedRestore($kind_key, $id) {
    global $mysqli, $session_name;
    $kinds = archivedKindsFor(2);
    $kind = $kinds[$kind_key] ?? null;
    $id = intval($id);
    if (!$kind || !$id) {
        return null;
    }
    $client_select = $kind['client'] !== '' ? $kind['client'] : '0';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT {$kind['name']} AS name, $client_select AS client_id" . ($kind_key === 'contact' ? ', contact_user_id' : '') . "
        FROM {$kind['table']} WHERE {$kind['id']} = $id AND {$kind['archived']} IS NOT NULL" . ($kind['client'] !== '' ? clientScopeSql($kind['client']) : '')));
    if (!$row) {
        return null;
    }
    $client_id = intval($row['client_id']);
    if (!archivedCanRestore($kind_key, $client_id)) {
        return null;
    }
    $name = escapeSql($row['name']);

    if ($kind_key === 'contact' && intval($row['contact_user_id']) > 0) {
        // Its portal login comes back with it
        mysqli_query($mysqli, "UPDATE users SET user_archived_at = NULL WHERE user_id = " . intval($row['contact_user_id']));
    }
    mysqli_query($mysqli, "UPDATE {$kind['table']} SET {$kind['archived']} = NULL" . ($kind_key === 'document' ? ', document_updated_at = document_updated_at' : '') . " WHERE {$kind['id']} = $id");
    if ($kind_key === 'asset') {
        mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = 'Restored', asset_history_description = '$session_name restored $name', asset_history_asset_id = $id");
    }
    $noun = strtolower($kind['label'] === 'License' ? 'software' : $kind['label']);
    logAudit($kind['log'], "Restore", "$session_name restored $noun $name", $client_id, $id);
    return $row['name'];
}
