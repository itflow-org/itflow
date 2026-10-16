<?php

/*
 * Client export - everything ITFlow holds about one client, two ways:
 *
 *   - the documentation PDF (clientExportPdf()): a runbook to hand over or
 *     keep on file - a cover, contents with page numbers, an overview, then a
 *     chapter per area, and the documents themselves at the end
 *   - the full export (clientExportArchive()): a zip with every record as CSV
 *     and JSON, the documents as web pages with their images, the files in
 *     their folders, ticket conversations with their attachments, photos,
 *     the PDF, and a README.html that ties it together for someone opening
 *     it in a browser
 *
 * Both read the same datasets (clientExportDatasets() / clientExportRows()),
 * and both leave out what the agent may not see: a section needs its module,
 * and its part of ITFlow turned on, whatever was posted. Passwords and 2FA
 * secrets only go out when asked for, and then the file is locked with a
 * password the agent sets (AES-256 for the zip and the PDF).
 */

/** The shortest password that may lock an export holding passwords. */
const CLIENT_EXPORT_PASSWORD_MIN = 8;

/**
 * What an export can hold, in document order. 'module' is the read
 * permission the section needs, 'feature' the part of ITFlow that has to be
 * on (reportFeatureOn()). 'pdf' is whether the PDF has a chapter for it;
 * 'pdf_default' whether its box starts ticked there. The client's own record
 * always goes in - exporting at all needs client access.
 */
function clientExportSections() {
    return [
        'contacts' => ['label' => 'Contacts', 'icon' => 'fa-users', 'module' => 'module_client', 'feature' => '', 'pdf' => true],
        'locations' => ['label' => 'Locations', 'icon' => 'fa-map-marker-alt', 'module' => 'module_client', 'feature' => '', 'pdf' => true],
        'vendors' => ['label' => 'Vendors', 'icon' => 'fa-building', 'module' => 'module_client', 'feature' => '', 'pdf' => true],
        'networks' => ['label' => 'Networks and IP addresses', 'icon' => 'fa-network-wired', 'module' => 'module_support', 'feature' => 'itdoc', 'pdf' => true],
        'assets' => ['label' => 'Assets', 'icon' => 'fa-desktop', 'module' => 'module_support', 'feature' => 'itdoc', 'pdf' => true],
        'racks' => ['label' => 'Racks', 'icon' => 'fa-server', 'module' => 'module_support', 'feature' => 'itdoc', 'pdf' => true],
        'software' => ['label' => 'Licenses', 'icon' => 'fa-cube', 'module' => 'module_support', 'feature' => 'itdoc', 'pdf' => true],
        'domains' => ['label' => 'Domains', 'icon' => 'fa-globe', 'module' => 'module_support', 'feature' => 'itdoc', 'pdf' => true],
        'certificates' => ['label' => 'Certificates', 'icon' => 'fa-lock', 'module' => 'module_support', 'feature' => 'itdoc', 'pdf' => true],
        'services' => ['label' => 'Services', 'icon' => 'fa-stream', 'module' => 'module_support', 'feature' => 'itdoc', 'pdf' => true],
        'credentials' => ['label' => 'Credentials', 'icon' => 'fa-key', 'module' => 'module_credential', 'feature' => 'itdoc', 'pdf' => true, 'pdf_default' => false],
        'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'module' => 'module_client', 'feature' => 'itdoc', 'pdf' => true],
        'files' => ['label' => 'Files', 'icon' => 'fa-paperclip', 'module' => 'module_client', 'feature' => 'itdoc', 'pdf' => true],
        'tickets' => ['label' => 'Tickets and projects', 'icon' => 'fa-life-ring', 'module' => 'module_support', 'feature' => 'ticketing', 'pdf' => false],
        'billing' => ['label' => 'Invoices, quotes and payments', 'icon' => 'fa-file-invoice-dollar', 'module' => 'module_sales', 'feature' => 'accounting', 'pdf' => false],
    ];
}

/** The sections this agent may export, in order. */
function clientExportAllowedSections() {
    $allowed = [];
    foreach (clientExportSections() as $key => $section) {
        if (lookupUserPermission($section['module']) >= 1 && ($section['feature'] === '' || reportFeatureOn($section['feature']))) {
            $allowed[$key] = $section;
        }
    }
    return $allowed;
}

/**
 * The options an export runs with, from what was posted - only the sections
 * this agent may export, and for the PDF only those it has a chapter for.
 * $kind is 'pdf' or 'archive'.
 */
function clientExportOptions($post, $kind) {
    $allowed = clientExportAllowedSections();
    $picked = array_map('strval', (array) ($post['sections'] ?? []));
    $sections = [];
    foreach ($allowed as $key => $section) {
        if (in_array($key, $picked, true) && ($kind === 'archive' || $section['pdf'])) {
            $sections[] = $key;
        }
    }
    $secrets = in_array('credentials', $sections, true) && !empty($post['secrets']);
    return [
        'kind' => $kind,
        'sections' => $sections,
        // The PDF is documentation - what is in use now. The archive can carry everything.
        'archived' => $kind === 'archive' && !empty($post['archived']),
        'secrets' => $secrets,
        'password' => $secrets ? (string) ($post['password'] ?? '') : '',
        'with_pdf' => $kind === 'archive' && !empty($post['with_pdf']),
        // Notes kept for the team - internal ticket notes and the client's quick notes
        'internal' => $kind === 'archive' && !empty($post['internal']),
    ];
}

/** '' when the options can run, or what is wrong - a missing or short password, a closed vault. */
function clientExportCheck($options, $post) {
    if (!$options['secrets']) {
        return '';
    }
    if (strlen($options['password']) < CLIENT_EXPORT_PASSWORD_MIN) {
        return 'To include passwords, set a password of at least ' . CLIENT_EXPORT_PASSWORD_MIN . ' characters to lock the file with.';
    }
    if ($options['password'] !== (string) ($post['password_confirm'] ?? '')) {
        return 'The two passwords do not match.';
    }
    // Nothing can be decrypted without the agent's vault open in this session
    if (encryptCredentialEntry('probe') === false) {
        return 'Your vault session has expired, so passwords cannot be read - sign out and back in, then export again.';
    }
    return '';
}

/** Is this section in the export? */
function clientExportHas($options, $section) {
    return in_array($section, $options['sections'], true);
}

/*
 * ---------- small helpers ----------
 */

/** A date or datetime as stored, or '' for none. */
function clientExportDate($value) {
    $value = trim((string) $value);
    return ($value === '' || strpos($value, '0000-00-00') === 0) ? '' : $value;
}

/** 'Yes' / 'No'. */
function clientExportYes($value) {
    return intval($value) ? 'Yes' : 'No';
}

/** A phone number with its country code, formatted the way ITFlow shows it. */
function clientExportPhone($number, $country_code = '', $extension = '') {
    $number = trim((string) $number);
    if ($number === '') {
        return '';
    }
    $formatted = formatPhoneNumber($number, $country_code);
    return trim($formatted . ($extension !== '' && $extension !== null ? ' x' . $extension : ''));
}

/** HTML as readable plain text - paragraphs and line breaks kept, tags and entities gone. */
function clientExportText($html) {
    $html = (string) $html;
    if ($html === '') {
        return '';
    }
    $html = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/blockquote|/pre)\b[^>]*>#i', "$0\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
    $text = preg_replace("/ *\n */", "\n", $text);
    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

/** Seconds as H:MM. */
function clientExportHours($seconds) {
    $seconds = intval($seconds);
    return $seconds ? sprintf('%d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)) : '';
}

/**
 * A name made safe as a file or folder name in the archive, on Windows,
 * macOS and Linux alike: no slashes or reserved characters, no leading
 * dots, not too long. Never empty.
 */
function clientExportSafeName($name, $max = 100) {
    $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', (string) $name);
    $name = trim(preg_replace('/\s+/u', ' ', $name), " .");
    if (mb_strlen($name) > $max) {
        $name = rtrim(mb_substr($name, 0, $max), " .");
    }
    if ($name === '' || preg_match('/^(CON|PRN|AUX|NUL|COM\d|LPT\d)$/i', $name)) {
        $name = 'Untitled';
    }
    return $name;
}

/**
 * A name not yet used in that folder of the archive - "Notes.txt", then
 * "Notes (2).txt". $used is kept between calls, keyed by lower-case path.
 */
function clientExportUniquePath($folder, $name, &$used) {
    $folder = trim($folder, '/');
    $dot = strrpos($name, '.');
    $stem = $dot > 0 ? substr($name, 0, $dot) : $name;
    $ext = $dot > 0 ? substr($name, $dot) : '';
    $candidate = $name;
    for ($n = 2; isset($used[mb_strtolower("$folder/$candidate")]); $n++) {
        $candidate = "$stem ($n)$ext";
    }
    $used[mb_strtolower("$folder/$candidate")] = true;
    return ($folder !== '' ? "$folder/" : '') . $candidate;
}

/** Names by id for one client's records - for "used by", "location", "vendor" and the like. */
function clientExportNames($client_id) {
    global $mysqli;
    static $cache = [];
    $client_id = intval($client_id);
    if (isset($cache[$client_id])) {
        return $cache[$client_id];
    }
    $names = ['contact' => [], 'location' => [], 'asset' => [], 'vendor' => [], 'network' => [], 'project' => [], 'user' => [], 'category' => [], 'status' => [], 'sla' => []];
    $lists = [
        'contact' => "SELECT contact_id, contact_name FROM contacts WHERE contact_client_id = $client_id",
        'location' => "SELECT location_id, location_name FROM locations WHERE location_client_id = $client_id",
        'asset' => "SELECT asset_id, asset_name FROM assets WHERE asset_client_id = $client_id",
        // A client's records can name company-wide vendors (a registrar) as well as the client's own
        'vendor' => "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id IN (0, $client_id)",
        'network' => "SELECT network_id, network_name FROM networks WHERE network_client_id = $client_id",
        'project' => "SELECT project_id, project_name FROM projects WHERE project_client_id = $client_id",
        'user' => "SELECT user_id, user_name FROM users",
        'category' => "SELECT category_id, category_name FROM categories",
        'status' => "SELECT ticket_status_id, ticket_status_name FROM ticket_statuses",
        'sla' => "SELECT sla_id, sla_name FROM slas",
    ];
    foreach ($lists as $type => $query) {
        $sql = mysqli_query($mysqli, $query);
        while ($row = mysqli_fetch_row($sql)) {
            $names[$type][intval($row[0])] = (string) $row[1];
        }
    }
    return $cache[$client_id] = $names;
}

/** Tag names per record, for one tag table: id => "Tag, Tag". */
function clientExportTags($join_table, $id_column, $ids, $tag_column = 'tag_id') {
    global $mysqli;
    $ids = array_filter(array_map('intval', (array) $ids));
    if (!$ids) {
        return [];
    }
    $tags = [];
    $sql = mysqli_query($mysqli, "SELECT j.$id_column AS id, GROUP_CONCAT(t.tag_name ORDER BY t.tag_name SEPARATOR ', ') AS names
        FROM $join_table j JOIN tags t ON t.tag_id = j.$tag_column WHERE j.$id_column IN (" . implode(',', $ids) . ") GROUP BY j.$id_column");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tags[intval($row['id'])] = $row['names'];
    }
    return $tags;
}

/** The folder path of a document or file, "Network/Diagrams", from the client's folder tree. */
function clientExportFolderPath($tree, $folder_id) {
    return implode('/', array_map(function ($folder) { return $folder['folder_name']; }, folderPath($tree, $folder_id)));
}

/*
 * ---------- the datasets ----------
 *
 * Each is a table of the export: its section, its title, and its columns
 * (key => heading). The keys are the JSON field names; the headings head the
 * CSV. clientExportRows() fills them in.
 */
function clientExportDatasets() {
    $archived = ['created' => 'Created', 'archived' => 'Archived'];
    return [
        'client' => ['section' => '', 'label' => 'Client', 'columns' => [
            'name' => 'Name', 'abbreviation' => 'Abbreviation', 'industry' => 'Industry', 'website' => 'Website', 'referral' => 'Referral', 'lead' => 'Lead',
            'tags' => 'Tags', 'hourly_rate' => 'Hourly rate', 'net_terms' => 'Net terms (days)', 'currency' => 'Currency', 'tax_id' => 'Tax ID', 'notes' => 'Notes',
        ] + $archived],
        'client_notes' => ['section' => '', 'label' => 'Client notes', 'columns' => [
            'type' => 'Type', 'note' => 'Note', 'by' => 'By', 'created' => 'Created', 'updated' => 'Updated', 'archived' => 'Archived',
        ]],
        'slas' => ['section' => '', 'label' => 'Service levels', 'columns' => [
            'priority' => 'Priority', 'sla' => 'SLA', 'response_minutes' => 'Response (business minutes)', 'resolution_minutes' => 'Resolution (business minutes)',
        ]],
        'contacts' => ['section' => 'contacts', 'label' => 'Contacts', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'title' => 'Title', 'department' => 'Department', 'email' => 'Email', 'phone' => 'Phone', 'mobile' => 'Mobile',
            'location' => 'Location', 'primary' => 'Primary', 'billing' => 'Billing', 'technical' => 'Technical', 'important' => 'Important',
            'portal' => 'Client portal', 'tags' => 'Tags', 'notes' => 'Notes',
        ] + $archived],
        'contact_notes' => ['section' => 'contacts', 'label' => 'Contact notes', 'columns' => [
            'contact' => 'Contact', 'type' => 'Type', 'note' => 'Note', 'by' => 'By', 'created' => 'Created', 'archived' => 'Archived',
        ]],
        'locations' => ['section' => 'locations', 'label' => 'Locations', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'address' => 'Address', 'city' => 'City', 'state' => 'State', 'zip' => 'Postal code',
            'country' => 'Country', 'phone' => 'Phone', 'fax' => 'Fax', 'hours' => 'Hours', 'primary' => 'Primary', 'contact' => 'Contact', 'tags' => 'Tags', 'notes' => 'Notes',
        ] + $archived],
        'vendors' => ['section' => 'vendors', 'label' => 'Vendors', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'account_number' => 'Account number', 'code' => 'Code / PIN', 'phone' => 'Phone',
            'email' => 'Email', 'website' => 'Website', 'hours' => 'Hours', 'sla' => 'SLA', 'contact_name' => 'Account manager', 'contact_phone' => 'Account manager phone',
            'contact_email' => 'Account manager email', 'tags' => 'Tags', 'notes' => 'Notes',
        ] + $archived],
        'networks' => ['section' => 'networks', 'label' => 'Networks', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'type' => 'Type', 'description' => 'Description', 'vlan' => 'VLAN', 'network' => 'Network', 'subnet' => 'Subnet mask',
            'gateway' => 'Gateway', 'primary_dns' => 'Primary DNS', 'secondary_dns' => 'Secondary DNS', 'dhcp_range' => 'DHCP range', 'location' => 'Location',
            'provider' => 'Provider', 'notes' => 'Notes',
        ] + $archived],
        'ip_addresses' => ['section' => 'networks', 'label' => 'IP addresses', 'columns' => [
            'network' => 'Network', 'address' => 'Address', 'type' => 'Type', 'hostname' => 'Hostname', 'mac' => 'MAC address', 'description' => 'Description',
        ]],
        'assets' => ['section' => 'assets', 'label' => 'Assets', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'type' => 'Type', 'description' => 'Description', 'make' => 'Make', 'model' => 'Model', 'serial' => 'Serial number',
            'os' => 'Operating system', 'status' => 'Status', 'ip' => 'IP address', 'mac' => 'MAC address', 'uri' => 'Link', 'uri_2' => 'Link 2', 'uri_client' => 'Client link',
            'location' => 'Location', 'physical_location' => 'Physical location', 'used_by' => 'Used by', 'vendor' => 'Vendor', 'purchase_reference' => 'Purchase reference',
            'purchase_date' => 'Purchased', 'install_date' => 'Installed', 'warranty_expire' => 'Warranty ends', 'tags' => 'Tags', 'notes' => 'Notes',
        ] + $archived],
        'asset_interfaces' => ['section' => 'assets', 'label' => 'Asset interfaces', 'columns' => [
            'asset' => 'Asset', 'name' => 'Interface', 'type' => 'Type', 'description' => 'Description', 'mac' => 'MAC address', 'ip' => 'IP address',
            'nat_ip' => 'NAT IP', 'ipv6' => 'IPv6', 'vlans' => 'VLANs', 'network' => 'Network', 'primary' => 'Primary', 'notes' => 'Notes', 'archived' => 'Archived',
        ]],
        'asset_notes' => ['section' => 'assets', 'label' => 'Asset notes', 'columns' => [
            'asset' => 'Asset', 'type' => 'Type', 'note' => 'Note', 'by' => 'By', 'created' => 'Created', 'archived' => 'Archived',
        ]],
        'asset_fields' => ['section' => 'assets', 'label' => 'Asset custom fields', 'columns' => [
            'asset' => 'Asset', 'field' => 'Field', 'value' => 'Value',
        ]],
        'racks' => ['section' => 'racks', 'label' => 'Racks', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'model' => 'Model', 'type' => 'Type', 'units' => 'Units', 'depth' => 'Depth',
            'power_capacity' => 'Power capacity (W)', 'location' => 'Location', 'physical_location' => 'Physical location', 'notes' => 'Notes',
        ] + $archived],
        'rack_units' => ['section' => 'racks', 'label' => 'Rack contents', 'columns' => [
            'rack' => 'Rack', 'from_unit' => 'From unit', 'to_unit' => 'To unit', 'face' => 'Face', 'device' => 'Device', 'asset' => 'Asset', 'type' => 'Type',
            'power_watts' => 'Power (W)', 'full_depth' => 'Full depth', 'notes' => 'Notes',
        ]],
        'licenses' => ['section' => 'software', 'label' => 'Licenses', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'version' => 'Version', 'type' => 'Type', 'license_type' => 'License type', 'key' => 'License key', 'seats' => 'Seats',
            'seats_used' => 'Seats in use', 'purchase_reference' => 'Purchase reference', 'purchase_date' => 'Purchased', 'expires' => 'Renews / expires',
            'vendor' => 'Vendor', 'description' => 'Description', 'notes' => 'Notes',
        ] + $archived],
        'license_seats' => ['section' => 'software', 'label' => 'License seats', 'columns' => [
            'license' => 'License', 'seat' => 'Seat', 'name' => 'Assigned to',
        ]],
        'license_keys' => ['section' => 'software', 'label' => 'License keys', 'columns' => [
            'license' => 'License', 'key' => 'Key', 'assigned_to' => 'Assigned to',
        ]],
        'domains' => ['section' => 'domains', 'label' => 'Domains', 'columns' => [
            'id' => 'ID', 'name' => 'Domain', 'description' => 'Description', 'expires' => 'Expires', 'auto_renew' => 'Auto-renew', 'registrar' => 'Registrar',
            'web_host' => 'Web host', 'dns_host' => 'DNS host', 'mail_host' => 'Mail host', 'ip' => 'A record', 'name_servers' => 'Name servers',
            'mail_servers' => 'Mail servers', 'txt' => 'TXT records', 'dmarc' => 'DMARC', 'dkim' => 'DKIM', 'notes' => 'Notes',
        ] + $archived],
        'certificates' => ['section' => 'certificates', 'label' => 'Certificates', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'host' => 'Host', 'names' => 'Names covered', 'issued_by' => 'Issued by', 'issued' => 'Issued',
            'expires' => 'Expires', 'fingerprint' => 'Fingerprint', 'check' => 'Last check result', 'checked' => 'Last checked', 'domain' => 'Domain', 'notes' => 'Notes',
            'public_key' => 'Certificate (PEM)',
        ] + $archived],
        'services' => ['section' => 'services', 'label' => 'Services', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'category' => 'Category', 'importance' => 'Importance', 'description' => 'Description', 'backup' => 'Backup',
            'review_due' => 'Next review', 'reviewed' => 'Last reviewed', 'notes' => 'Notes',
        ] + $archived],
        'credentials' => ['section' => 'credentials', 'label' => 'Credentials', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'uri' => 'Link', 'uri_2' => 'Link 2', 'username' => 'Username', 'password' => 'Password',
            'otp_secret' => '2FA secret', 'has_2fa' => '2FA', 'contact' => 'Contact', 'asset' => 'Asset', 'password_changed' => 'Password changed', 'tags' => 'Tags', 'note' => 'Notes',
        ] + $archived],
        'documents' => ['section' => 'documents', 'label' => 'Documents', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'folder' => 'Folder', 'client_visible' => 'In client portal', 'created_by' => 'Created by',
            'created' => 'Created', 'updated' => 'Updated', 'archived' => 'Archived', 'path' => 'File in this export',
        ]],
        'files' => ['section' => 'files', 'label' => 'Files', 'columns' => [
            'id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'folder' => 'Folder', 'type' => 'Type', 'size' => 'Size (bytes)', 'created_by' => 'Uploaded by',
            'created' => 'Uploaded', 'archived' => 'Archived', 'path' => 'File in this export',
        ]],
        'links' => ['section' => '', 'label' => 'Links between records', 'columns' => [
            'type' => 'Type', 'name' => 'Name', 'linked_type' => 'Linked type', 'linked_name' => 'Linked to',
        ]],
        'tickets' => ['section' => 'tickets', 'label' => 'Tickets', 'columns' => [
            'id' => 'ID', 'number' => 'Ticket', 'subject' => 'Subject', 'status' => 'Status', 'priority' => 'Priority', 'category' => 'Category', 'source' => 'Source',
            'contact' => 'Contact', 'assigned_to' => 'Assigned to', 'asset' => 'Asset', 'location' => 'Location', 'project' => 'Project', 'vendor' => 'Vendor',
            'vendor_ticket' => 'Vendor ticket', 'billable' => 'Billable', 'created' => 'Opened', 'first_response' => 'First reply', 'resolved' => 'Resolved',
            'closed' => 'Closed', 'due' => 'Due', 'sla' => 'SLA', 'response_met' => 'Response met', 'resolution_met' => 'Resolution met', 'feedback' => 'Rating',
            'time_worked' => 'Time worked (h:mm)', 'details' => 'Details', 'archived' => 'Archived', 'path' => 'Conversation in this export',
        ]],
        'ticket_replies' => ['section' => 'tickets', 'label' => 'Ticket replies', 'columns' => [
            'ticket' => 'Ticket', 'type' => 'Type', 'by' => 'By', 'created' => 'Date', 'time_worked' => 'Time worked (h:mm)', 'reply' => 'Reply',
        ]],
        'ticket_tasks' => ['section' => 'tickets', 'label' => 'Ticket tasks', 'columns' => [
            'ticket' => 'Ticket', 'task' => 'Task', 'completed' => 'Completed',
        ]],
        'recurring_tickets' => ['section' => 'tickets', 'label' => 'Recurring tickets', 'columns' => [
            'subject' => 'Subject', 'priority' => 'Priority', 'category' => 'Category', 'frequency' => 'Every', 'next_run' => 'Next run', 'paused' => 'Paused',
            'assigned_to' => 'Assigned to', 'contact' => 'Contact', 'asset' => 'Asset', 'details' => 'Details', 'created' => 'Created',
        ]],
        'projects' => ['section' => 'tickets', 'label' => 'Projects', 'columns' => [
            'number' => 'Project', 'name' => 'Name', 'description' => 'Description', 'manager' => 'Manager', 'contact' => 'Contact', 'start' => 'Start', 'due' => 'Due',
            'budget_hours' => 'Budget (hours)', 'completed' => 'Completed', 'created' => 'Created', 'archived' => 'Archived',
        ]],
        'invoices' => ['section' => 'billing', 'label' => 'Invoices', 'columns' => [
            'number' => 'Invoice', 'scope' => 'Scope', 'status' => 'Status', 'date' => 'Date', 'due' => 'Due', 'amount' => 'Amount', 'paid' => 'Paid', 'balance' => 'Balance',
            'discount' => 'Discount', 'credit' => 'Credit applied', 'currency' => 'Currency', 'category' => 'Category', 'note' => 'Note', 'archived' => 'Archived',
        ]],
        'invoice_items' => ['section' => 'billing', 'label' => 'Invoice items', 'columns' => [
            'invoice' => 'Invoice', 'item' => 'Item', 'description' => 'Description', 'quantity' => 'Quantity', 'price' => 'Price', 'subtotal' => 'Subtotal', 'tax' => 'Tax', 'total' => 'Total',
        ]],
        'payments' => ['section' => 'billing', 'label' => 'Payments', 'columns' => [
            'invoice' => 'Invoice', 'date' => 'Date', 'amount' => 'Amount', 'currency' => 'Currency', 'method' => 'Method', 'reference' => 'Reference',
        ]],
        'quotes' => ['section' => 'billing', 'label' => 'Quotes', 'columns' => [
            'number' => 'Quote', 'scope' => 'Scope', 'status' => 'Status', 'date' => 'Date', 'expires' => 'Expires', 'amount' => 'Amount', 'discount' => 'Discount',
            'currency' => 'Currency', 'category' => 'Category', 'note' => 'Note', 'archived' => 'Archived',
        ]],
        'quote_items' => ['section' => 'billing', 'label' => 'Quote items', 'columns' => [
            'quote' => 'Quote', 'item' => 'Item', 'description' => 'Description', 'quantity' => 'Quantity', 'price' => 'Price', 'subtotal' => 'Subtotal', 'tax' => 'Tax', 'total' => 'Total',
        ]],
        'recurring_invoices' => ['section' => 'billing', 'label' => 'Recurring invoices', 'columns' => [
            'number' => 'Recurring invoice', 'scope' => 'Scope', 'frequency' => 'Every', 'active' => 'Active', 'next_date' => 'Next date', 'last_sent' => 'Last sent',
            'amount' => 'Amount', 'currency' => 'Currency', 'auto_send' => 'Sends automatically', 'category' => 'Category', 'note' => 'Note', 'archived' => 'Archived',
        ]],
        'recurring_invoice_items' => ['section' => 'billing', 'label' => 'Recurring invoice items', 'columns' => [
            'recurring_invoice' => 'Recurring invoice', 'item' => 'Item', 'description' => 'Description', 'quantity' => 'Quantity', 'price' => 'Price',
            'subtotal' => 'Subtotal', 'tax' => 'Tax', 'total' => 'Total',
        ]],
        'credits' => ['section' => 'billing', 'label' => 'Credits', 'columns' => [
            'amount' => 'Amount', 'type' => 'Type', 'note' => 'Note', 'created' => 'Created', 'expires' => 'Expires', 'invoice' => 'Used on invoice',
        ]],
    ];
}

/** The datasets the options cover, in order. */
function clientExportDatasetsFor($options) {
    return array_filter(clientExportDatasets(), function ($dataset, $key) use ($options) {
        if ($key === 'client_notes' && empty($options['internal'])) {
            return false;
        }
        return $dataset['section'] === '' || clientExportHas($options, $dataset['section']);
    }, ARRAY_FILTER_USE_BOTH);
}

/**
 * The rows of one dataset for a client, each keyed by the dataset's
 * columns. Archived records only when the options ask for them; passwords
 * and 2FA secrets only with 'secrets'.
 */
function clientExportRows($dataset, $client_id, $options) {
    global $mysqli;

    $client_id = intval($client_id);
    $names = clientExportNames($client_id);
    $live = function ($column) use ($options) {
        return $options['archived'] ? '' : " AND $column IS NULL";
    };
    $name = function ($type, $id) use ($names) {
        return $names[$type][intval($id)] ?? '';
    };
    $rows = [];
    $fetch = function ($query) use ($mysqli) {
        $out = [];
        $sql = mysqli_query($mysqli, $query);
        while ($sql && ($row = mysqli_fetch_assoc($sql))) {
            $out[] = $row;
        }
        return $out;
    };
    // Row by row, unbuffered - for tickets and replies, whose HTML (pasted
    // screenshots and all) would not fit in memory at once. Nothing else may
    // query until it is done, which is why the names are looked up first.
    $stream = function ($query, callable $each) use ($mysqli) {
        $sql = mysqli_query($mysqli, $query, MYSQLI_USE_RESULT);
        while ($sql && ($row = mysqli_fetch_assoc($sql))) {
            $each($row);
        }
        if ($sql) {
            mysqli_free_result($sql);
        }
    };

    switch ($dataset) {

        case 'client':
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id"));
            if ($row) {
                $billing = clientExportHas($options, 'billing');
                $rows[] = [
                    'name' => $row['client_name'], 'abbreviation' => $row['client_abbreviation'], 'industry' => $row['client_type'], 'website' => $row['client_website'],
                    'referral' => $row['client_referral'], 'lead' => clientExportYes($row['client_lead']),
                    'tags' => clientExportTags('client_tags', 'client_id', [$client_id])[$client_id] ?? '',
                    'hourly_rate' => $billing ? $row['client_rate'] : '', 'net_terms' => $billing ? $row['client_net_terms'] : '',
                    'currency' => $billing ? $row['client_currency_code'] : '', 'tax_id' => $billing ? $row['client_tax_id_number'] : '',
                    'notes' => $row['client_notes'], 'created' => clientExportDate($row['client_created_at']), 'archived' => clientExportDate($row['client_archived_at']),
                ];
            }
            break;

        case 'client_notes':
            foreach ($fetch("SELECT * FROM client_notes WHERE client_note_client_id = $client_id" . $live('client_note_archived_at') . " ORDER BY client_note_created_at") as $row) {
                $rows[] = ['type' => $row['client_note_type'], 'note' => clientExportText($row['client_note']), 'by' => $name('user', $row['client_note_created_by']),
                    'created' => $row['client_note_created_at'], 'updated' => clientExportDate($row['client_note_updated_at']), 'archived' => clientExportDate($row['client_note_archived_at'])];
            }
            break;

        case 'slas':
            foreach ($fetch("SELECT sla_assignment_priority, sla_name, sla_response_minutes, sla_resolution_minutes FROM sla_assignments
                JOIN slas ON sla_id = sla_assignment_sla_id WHERE sla_assignment_client_id = $client_id
                ORDER BY FIELD(sla_assignment_priority, 'Urgent', 'High', 'Medium', 'Low')") as $row) {
                $rows[] = ['priority' => $row['sla_assignment_priority'], 'sla' => $row['sla_name'], 'response_minutes' => $row['sla_response_minutes'],
                    'resolution_minutes' => $row['sla_resolution_minutes']];
            }
            break;

        case 'contacts':
            $list = $fetch("SELECT * FROM contacts WHERE contact_client_id = $client_id" . $live('contact_archived_at') . " ORDER BY contact_primary DESC, contact_name");
            $tags = clientExportTags('contact_tags', 'contact_id', array_column($list, 'contact_id'));
            foreach ($list as $row) {
                $id = intval($row['contact_id']);
                $rows[] = ['id' => $id, 'name' => $row['contact_name'], 'title' => $row['contact_title'], 'department' => $row['contact_department'], 'email' => $row['contact_email'],
                    'phone' => clientExportPhone($row['contact_phone'], $row['contact_phone_country_code'], $row['contact_extension']),
                    'mobile' => clientExportPhone($row['contact_mobile'], $row['contact_mobile_country_code']), 'location' => $name('location', $row['contact_location_id']),
                    'primary' => clientExportYes($row['contact_primary']), 'billing' => clientExportYes($row['contact_billing']), 'technical' => clientExportYes($row['contact_technical']),
                    'important' => clientExportYes($row['contact_important']), 'portal' => clientExportYes(intval($row['contact_user_id']) > 0), 'tags' => $tags[$id] ?? '',
                    'notes' => $row['contact_notes'], 'created' => $row['contact_created_at'], 'archived' => clientExportDate($row['contact_archived_at']), '_photo' => $row['contact_photo']];
            }
            break;

        case 'contact_notes':
            foreach ($fetch("SELECT contact_notes.*, contact_name FROM contact_notes JOIN contacts ON contact_id = contact_note_contact_id
                WHERE contact_client_id = $client_id" . $live('contact_note_archived_at') . $live('contact_archived_at') . " ORDER BY contact_name, contact_note_created_at") as $row) {
                $rows[] = ['contact' => $row['contact_name'], 'type' => $row['contact_note_type'], 'note' => clientExportText($row['contact_note']),
                    'by' => $name('user', $row['contact_note_created_by']), 'created' => $row['contact_note_created_at'], 'archived' => clientExportDate($row['contact_note_archived_at'])];
            }
            break;

        case 'locations':
            $list = $fetch("SELECT * FROM locations WHERE location_client_id = $client_id" . $live('location_archived_at') . " ORDER BY location_primary DESC, location_name");
            $tags = clientExportTags('location_tags', 'location_id', array_column($list, 'location_id'));
            foreach ($list as $row) {
                $id = intval($row['location_id']);
                $rows[] = ['id' => $id, 'name' => $row['location_name'], 'description' => $row['location_description'], 'address' => $row['location_address'],
                    'city' => $row['location_city'], 'state' => $row['location_state'], 'zip' => $row['location_zip'], 'country' => $row['location_country'],
                    'phone' => clientExportPhone($row['location_phone'], $row['location_phone_country_code'], $row['location_phone_extension']),
                    'fax' => clientExportPhone($row['location_fax'], $row['location_fax_country_code']), 'hours' => $row['location_hours'],
                    'primary' => clientExportYes($row['location_primary']), 'contact' => $name('contact', $row['location_contact_id']), 'tags' => $tags[$id] ?? '',
                    'notes' => $row['location_notes'], 'created' => $row['location_created_at'], 'archived' => clientExportDate($row['location_archived_at']), '_photo' => $row['location_photo']];
            }
            break;

        case 'vendors':
            $list = $fetch("SELECT * FROM vendors WHERE vendor_client_id = $client_id" . $live('vendor_archived_at') . " ORDER BY vendor_name");
            $tags = clientExportTags('vendor_tags', 'vendor_id', array_column($list, 'vendor_id'));
            foreach ($list as $row) {
                $id = intval($row['vendor_id']);
                $rows[] = ['id' => $id, 'name' => $row['vendor_name'], 'description' => $row['vendor_description'], 'account_number' => $row['vendor_account_number'],
                    'code' => $row['vendor_code'], 'phone' => clientExportPhone($row['vendor_phone'], $row['vendor_phone_country_code'], $row['vendor_extension']),
                    'email' => $row['vendor_email'], 'website' => $row['vendor_website'], 'hours' => $row['vendor_hours'], 'sla' => $row['vendor_sla'],
                    'contact_name' => $row['vendor_contact_name'], 'contact_phone' => clientExportPhone($row['vendor_contact_phone'], $row['vendor_contact_phone_country_code']),
                    'contact_email' => $row['vendor_contact_email'], 'tags' => $tags[$id] ?? '', 'notes' => $row['vendor_notes'], 'created' => $row['vendor_created_at'],
                    'archived' => clientExportDate($row['vendor_archived_at'])];
            }
            break;

        case 'networks':
            foreach ($fetch("SELECT * FROM networks WHERE network_client_id = $client_id" . $live('network_archived_at') . " ORDER BY network_name") as $row) {
                $rows[] = ['id' => intval($row['network_id']), 'name' => $row['network_name'], 'type' => $row['network_type'], 'description' => $row['network_description'],
                    'vlan' => $row['network_vlan'] ?: '', 'network' => $row['network'], 'subnet' => $row['network_subnet'], 'gateway' => $row['network_gateway'],
                    'primary_dns' => $row['network_primary_dns'], 'secondary_dns' => $row['network_secondary_dns'], 'dhcp_range' => $row['network_dhcp_range'],
                    'location' => $name('location', $row['network_location_id']), 'provider' => $name('vendor', $row['network_vendor_id']), 'notes' => $row['network_notes'],
                    'created' => $row['network_created_at'], 'archived' => clientExportDate($row['network_archived_at'])];
            }
            break;

        case 'ip_addresses':
            foreach ($fetch("SELECT network_ips.*, network_name FROM network_ips JOIN networks ON network_id = ip_network_id
                WHERE network_client_id = $client_id" . $live('network_archived_at') . " ORDER BY network_name, INET_ATON(ip_address), ip_address") as $row) {
                $rows[] = ['network' => $row['network_name'], 'address' => $row['ip_address'], 'type' => $row['ip_type'], 'hostname' => $row['ip_hostname'],
                    'mac' => $row['ip_mac'], 'description' => $row['ip_description'], '_network_id' => intval($row['ip_network_id'])];
            }
            break;

        case 'assets':
            $list = $fetch("SELECT assets.*, interface_ip, interface_mac FROM assets
                LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 AND interface_archived_at IS NULL
                WHERE asset_client_id = $client_id" . $live('asset_archived_at') . " GROUP BY asset_id ORDER BY asset_type, asset_name");
            $tags = clientExportTags('asset_tags', 'asset_tag_asset_id', array_column($list, 'asset_id'), 'asset_tag_tag_id');
            foreach ($list as $row) {
                $id = intval($row['asset_id']);
                $rows[] = ['id' => $id, 'name' => $row['asset_name'], 'type' => $row['asset_type'], 'description' => $row['asset_description'], 'make' => $row['asset_make'],
                    'model' => $row['asset_model'], 'serial' => $row['asset_serial'], 'os' => $row['asset_os'], 'status' => $row['asset_status'],
                    'ip' => $row['interface_ip'] ?? '', 'mac' => $row['interface_mac'] ?? '', 'uri' => $row['asset_uri'], 'uri_2' => $row['asset_uri_2'],
                    'uri_client' => $row['asset_uri_client'], 'location' => $name('location', $row['asset_location_id']), 'physical_location' => $row['asset_physical_location'],
                    'used_by' => $name('contact', $row['asset_contact_id']), 'vendor' => $name('vendor', $row['asset_vendor_id']),
                    'purchase_reference' => $row['asset_purchase_reference'], 'purchase_date' => clientExportDate($row['asset_purchase_date']),
                    'install_date' => clientExportDate($row['asset_install_date']), 'warranty_expire' => clientExportDate($row['asset_warranty_expire']),
                    'tags' => $tags[$id] ?? '', 'notes' => $row['asset_notes'], 'created' => $row['asset_created_at'], 'archived' => clientExportDate($row['asset_archived_at']),
                    '_photo' => $row['asset_photo']];
            }
            break;

        case 'asset_interfaces':
            foreach ($fetch("SELECT asset_interfaces.*, asset_name FROM asset_interfaces JOIN assets ON asset_id = interface_asset_id
                WHERE asset_client_id = $client_id" . $live('asset_archived_at') . $live('interface_archived_at') . " ORDER BY asset_name, interface_primary DESC, interface_name") as $row) {
                $rows[] = ['asset' => $row['asset_name'], 'name' => $row['interface_name'], 'type' => $row['interface_type'], 'description' => $row['interface_description'],
                    'mac' => $row['interface_mac'], 'ip' => $row['interface_ip'], 'nat_ip' => $row['interface_nat_ip'], 'ipv6' => $row['interface_ipv6'],
                    'vlans' => $row['interface_vlans'], 'network' => $name('network', $row['interface_network_id']), 'primary' => clientExportYes($row['interface_primary']),
                    'notes' => $row['interface_notes'], 'archived' => clientExportDate($row['interface_archived_at'])];
            }
            break;

        case 'asset_notes':
            foreach ($fetch("SELECT asset_notes.*, asset_name FROM asset_notes JOIN assets ON asset_id = asset_note_asset_id
                WHERE asset_client_id = $client_id" . $live('asset_archived_at') . $live('asset_note_archived_at') . " ORDER BY asset_name, asset_note_created_at") as $row) {
                $rows[] = ['asset' => $row['asset_name'], 'type' => $row['asset_note_type'], 'note' => clientExportText($row['asset_note']),
                    'by' => $name('user', $row['asset_note_created_by']), 'created' => $row['asset_note_created_at'], 'archived' => clientExportDate($row['asset_note_archived_at'])];
            }
            break;

        case 'asset_fields':
            foreach ($fetch("SELECT asset_name, custom_field_label, asset_custom_field_value FROM asset_custom
                JOIN assets ON asset_id = asset_custom_asset_id JOIN custom_fields ON custom_field_id = asset_custom_field_id
                WHERE asset_client_id = $client_id" . $live('asset_archived_at') . " AND asset_custom_field_value != '' ORDER BY asset_name, custom_field_order") as $row) {
                $rows[] = ['asset' => $row['asset_name'], 'field' => $row['custom_field_label'], 'value' => $row['asset_custom_field_value']];
            }
            break;

        case 'racks':
            foreach ($fetch("SELECT * FROM racks WHERE rack_client_id = $client_id" . $live('rack_archived_at') . " ORDER BY rack_name") as $row) {
                $rows[] = ['id' => intval($row['rack_id']), 'name' => $row['rack_name'], 'description' => $row['rack_description'], 'model' => $row['rack_model'],
                    'type' => $row['rack_type'], 'units' => $row['rack_units'], 'depth' => $row['rack_depth'], 'power_capacity' => $row['rack_power_capacity'] ?: '',
                    'location' => $name('location', $row['rack_location_id']), 'physical_location' => $row['rack_physical_location'], 'notes' => $row['rack_notes'],
                    'created' => $row['rack_created_at'], 'archived' => clientExportDate($row['rack_archived_at']), '_photo' => $row['rack_photo'], '_photo_rear' => $row['rack_photo_rear']];
            }
            break;

        case 'rack_units':
            foreach ($fetch("SELECT rack_units.*, rack_name FROM rack_units JOIN racks ON rack_id = unit_rack_id
                WHERE rack_client_id = $client_id" . $live('rack_archived_at') . $live('unit_archived_at') . " ORDER BY rack_name, unit_face, unit_start_number") as $row) {
                $asset = $name('asset', $row['unit_asset_id']);
                $rows[] = ['rack' => $row['rack_name'], 'from_unit' => $row['unit_start_number'], 'to_unit' => $row['unit_end_number'], 'face' => ucfirst((string) $row['unit_face']),
                    'device' => $row['unit_device'] ?: $asset, 'asset' => $asset, 'type' => $row['unit_type'], 'power_watts' => $row['unit_power_watts'] ?: '',
                    'full_depth' => clientExportYes($row['unit_full_depth']), 'notes' => $row['unit_notes'], '_rack_id' => intval($row['unit_rack_id'])];
            }
            break;

        case 'licenses':
            foreach ($fetch("SELECT software.*, " . softwareSeatsUsedSql('software.software_id') . " AS seats_used FROM software
                WHERE software_client_id = $client_id" . $live('software_archived_at') . " ORDER BY software_name") as $row) {
                $rows[] = ['id' => intval($row['software_id']), 'name' => $row['software_name'], 'version' => $row['software_version'], 'type' => $row['software_type'],
                    'license_type' => $row['software_license_type'], 'key' => $row['software_key'], 'seats' => intval($row['software_seats']) ?: '',
                    'seats_used' => intval($row['seats_used']), 'purchase_reference' => $row['software_purchase_reference'], 'purchase_date' => clientExportDate($row['software_purchase']),
                    'expires' => clientExportDate($row['software_expire']), 'vendor' => $name('vendor', $row['software_vendor_id']), 'description' => $row['software_description'],
                    'notes' => $row['software_notes'], 'created' => $row['software_created_at'], 'archived' => clientExportDate($row['software_archived_at'])];
            }
            break;

        case 'license_seats':
            foreach ($fetch("SELECT software_name, 'Device' AS seat, asset_name AS holder FROM software_assets
                    JOIN software ON software.software_id = software_assets.software_id JOIN assets ON assets.asset_id = software_assets.asset_id
                    WHERE software_client_id = $client_id AND asset_client_id = $client_id" . $live('software_archived_at') . $live('asset_archived_at') . "
                UNION ALL
                SELECT software_name, 'User', contact_name FROM software_contacts
                    JOIN software ON software.software_id = software_contacts.software_id JOIN contacts ON contacts.contact_id = software_contacts.contact_id
                    WHERE software_client_id = $client_id AND contact_client_id = $client_id" . $live('software_archived_at') . $live('contact_archived_at') . "
                ORDER BY software_name, seat, holder") as $row) {
                $rows[] = ['license' => $row['software_name'], 'seat' => $row['seat'], 'name' => $row['holder']];
            }
            break;

        case 'license_keys':
            foreach ($fetch("SELECT software_name, k.software_key AS license_key, k.software_key_id,
                    (SELECT GROUP_CONCAT(asset_name ORDER BY asset_name SEPARATOR ', ') FROM software_key_asset_assignments ka JOIN assets a ON a.asset_id = ka.asset_id WHERE ka.software_key_id = k.software_key_id) AS on_assets,
                    (SELECT GROUP_CONCAT(contact_name ORDER BY contact_name SEPARATOR ', ') FROM software_key_contact_assignments kc JOIN contacts c ON c.contact_id = kc.contact_id WHERE kc.software_key_id = k.software_key_id) AS on_contacts
                FROM software_keys k JOIN software ON software.software_id = k.software_key_software_id
                WHERE software_client_id = $client_id" . $live('software_archived_at') . " ORDER BY software_name, k.software_key_id") as $row) {
                $rows[] = ['license' => $row['software_name'], 'key' => $row['license_key'],
                    'assigned_to' => implode(', ', array_filter([$row['on_assets'], $row['on_contacts']]))];
            }
            break;

        case 'domains':
            foreach ($fetch("SELECT * FROM domains WHERE domain_client_id = $client_id" . $live('domain_archived_at') . " ORDER BY domain_name") as $row) {
                $rows[] = ['id' => intval($row['domain_id']), 'name' => $row['domain_name'], 'description' => $row['domain_description'], 'expires' => clientExportDate($row['domain_expire']),
                    'auto_renew' => clientExportYes($row['domain_auto_renew']), 'registrar' => $name('vendor', $row['domain_registrar']), 'web_host' => $name('vendor', $row['domain_webhost']),
                    'dns_host' => $name('vendor', $row['domain_dnshost']), 'mail_host' => $name('vendor', $row['domain_mailhost']), 'ip' => $row['domain_ip'],
                    'name_servers' => $row['domain_name_servers'], 'mail_servers' => $row['domain_mail_servers'], 'txt' => $row['domain_txt'], 'dmarc' => $row['domain_dmarc'],
                    'dkim' => $row['domain_dkim'], 'notes' => $row['domain_notes'], 'created' => $row['domain_created_at'], 'archived' => clientExportDate($row['domain_archived_at'])];
            }
            break;

        case 'certificates':
            foreach ($fetch("SELECT certificates.*, domain_name FROM certificates LEFT JOIN domains ON domain_id = certificate_domain_id AND domain_client_id = $client_id
                WHERE certificate_client_id = $client_id" . $live('certificate_archived_at') . " ORDER BY certificate_name") as $row) {
                $rows[] = ['id' => intval($row['certificate_id']), 'name' => $row['certificate_name'], 'description' => $row['certificate_description'], 'host' => $row['certificate_domain'],
                    'names' => $row['certificate_names'], 'issued_by' => $row['certificate_issued_by'], 'issued' => clientExportDate($row['certificate_issued_at']),
                    'expires' => clientExportDate($row['certificate_expire']), 'fingerprint' => $row['certificate_fingerprint'],
                    'check' => CERTIFICATE_CHECK_STATES[$row['certificate_check_status'] ?? '']['label'] ?? '', 'checked' => clientExportDate($row['certificate_checked_at']),
                    'domain' => $row['domain_name'] ?? '', 'notes' => $row['certificate_notes'], 'public_key' => $row['certificate_public_key'],
                    'created' => $row['certificate_created_at'], 'archived' => clientExportDate($row['certificate_archived_at'])];
            }
            break;

        case 'services':
            foreach ($fetch("SELECT * FROM services WHERE service_client_id = $client_id" . $live('service_archived_at') . "
                ORDER BY FIELD(service_importance, 'High', 'Medium', 'Low'), service_name") as $row) {
                $rows[] = ['id' => intval($row['service_id']), 'name' => $row['service_name'], 'category' => $row['service_category'], 'importance' => $row['service_importance'],
                    'description' => $row['service_description'], 'backup' => $row['service_backup'], 'review_due' => clientExportDate($row['service_review_due']),
                    'reviewed' => clientExportDate($row['service_reviewed_at']), 'notes' => $row['service_notes'], 'created' => $row['service_created_at'],
                    'archived' => clientExportDate($row['service_archived_at'])];
            }
            break;

        case 'credentials':
            $list = $fetch("SELECT * FROM credentials WHERE credential_client_id = $client_id" . $live('credential_archived_at') . " ORDER BY credential_name");
            $tags = clientExportTags('credential_tags', 'credential_id', array_column($list, 'credential_id'));
            foreach ($list as $row) {
                $id = intval($row['credential_id']);
                $password = '';
                if ($options['secrets'] && $row['credential_password'] !== '') {
                    $password = decryptCredentialEntry($row['credential_password']);
                    $password = $password === false ? '' : (string) $password;
                }
                $rows[] = ['id' => $id, 'name' => $row['credential_name'], 'description' => $row['credential_description'], 'uri' => $row['credential_uri'],
                    'uri_2' => $row['credential_uri_2'], 'username' => credentialUsername($row), 'password' => $password,
                    'otp_secret' => $options['secrets'] ? (string) $row['credential_otp_secret'] : '', 'has_2fa' => clientExportYes(trim((string) $row['credential_otp_secret']) !== ''),
                    'contact' => $name('contact', $row['credential_contact_id']), 'asset' => $name('asset', $row['credential_asset_id']),
                    'password_changed' => clientExportDate($row['credential_password_changed_at']), 'tags' => $tags[$id] ?? '', 'note' => $row['credential_note'],
                    'created' => $row['credential_created_at'], 'archived' => clientExportDate($row['credential_archived_at'])];
            }
            break;

        case 'documents':
            $tree = folderTree($client_id);
            foreach ($fetch("SELECT document_id, document_name, document_description, document_folder_id, document_client_visible, document_created_by,
                    document_created_at, document_updated_at, document_archived_at FROM documents
                WHERE document_client_id = $client_id" . $live('document_archived_at') . " ORDER BY document_name") as $row) {
                $rows[] = ['id' => intval($row['document_id']), 'name' => $row['document_name'], 'description' => $row['document_description'],
                    'folder' => clientExportFolderPath($tree, $row['document_folder_id']), 'client_visible' => clientExportYes($row['document_client_visible']),
                    'created_by' => $name('user', $row['document_created_by']), 'created' => $row['document_created_at'], 'updated' => clientExportDate($row['document_updated_at']),
                    'archived' => clientExportDate($row['document_archived_at']), 'path' => ''];
            }
            break;

        case 'files':
            $tree = folderTree($client_id);
            foreach ($fetch("SELECT * FROM files WHERE file_client_id = $client_id" . $live('file_archived_at') . " ORDER BY file_name") as $row) {
                $rows[] = ['id' => intval($row['file_id']), 'name' => $row['file_name'], 'description' => $row['file_description'],
                    'folder' => clientExportFolderPath($tree, $row['file_folder_id']), 'type' => strtoupper((string) $row['file_ext']), 'size' => intval($row['file_size']),
                    'created_by' => $name('user', $row['file_created_by']), 'created' => $row['file_created_at'], 'archived' => clientExportDate($row['file_archived_at']),
                    'path' => '', '_reference' => $row['file_reference_name']];
            }
            break;

        case 'links':
            $rows = clientExportLinks($client_id, $options);
            break;

        case 'tickets':
            $met = function ($value) {
                return $value === null ? '' : clientExportYes($value);
            };
            $stream("SELECT tickets.*,
                    (SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = tickets.ticket_id AND ticket_reply_archived_at IS NULL) AS seconds,
                    (SELECT CONCAT(invoice_prefix, invoice_number) FROM invoices WHERE invoice_id = ticket_invoice_id AND invoice_client_id = $client_id) AS invoice_ref
                FROM tickets WHERE ticket_client_id = $client_id" . $live('ticket_archived_at') . " ORDER BY ticket_number DESC", function ($row) use (&$rows, $name, $met) {
                $rows[] = ['id' => intval($row['ticket_id']), 'number' => ticketReference($row), 'subject' => $row['ticket_subject'], 'status' => $name('status', $row['ticket_status']),
                    'priority' => $row['ticket_priority'], 'category' => $name('category', $row['ticket_category']), 'source' => $row['ticket_source'],
                    'contact' => $name('contact', $row['ticket_contact_id']), 'assigned_to' => $name('user', $row['ticket_assigned_to']), 'asset' => $name('asset', $row['ticket_asset_id']),
                    'location' => $name('location', $row['ticket_location_id']), 'project' => $name('project', $row['ticket_project_id']), 'vendor' => $name('vendor', $row['ticket_vendor_id']),
                    'vendor_ticket' => (string) $row['ticket_vendor_ticket_number'], 'billable' => clientExportYes($row['ticket_billable']), 'created' => $row['ticket_created_at'],
                    'first_response' => clientExportDate($row['ticket_first_response_at']), 'resolved' => clientExportDate($row['ticket_resolved_at']),
                    'closed' => clientExportDate($row['ticket_closed_at']), 'due' => clientExportDate($row['ticket_due_at']), 'sla' => $name('sla', $row['ticket_sla_id']),
                    'response_met' => $met($row['ticket_response_sla_met']), 'resolution_met' => $met($row['ticket_resolution_sla_met']), 'feedback' => (string) $row['ticket_feedback'],
                    'time_worked' => clientExportHours($row['seconds']), 'details' => clientExportText($row['ticket_details']),
                    'archived' => clientExportDate($row['ticket_archived_at']), 'path' => '', '_invoice' => (string) $row['invoice_ref']];
            });
            break;

        case 'ticket_replies':
            $stream("SELECT ticket_replies.*, ticket_prefix, ticket_number FROM ticket_replies JOIN tickets ON ticket_id = ticket_reply_ticket_id
                WHERE ticket_client_id = $client_id AND ticket_reply_archived_at IS NULL" . $live('ticket_archived_at')
                . (empty($options['internal']) ? " AND ticket_reply_type != 'Internal'" : '') . " ORDER BY ticket_number DESC, ticket_reply_created_at", function ($row) use (&$rows, $name) {
                $by = $row['ticket_reply_type'] === 'Client' ? $name('contact', $row['ticket_reply_by']) : $name('user', $row['ticket_reply_by']);
                $rows[] = ['ticket' => ticketReference($row), 'type' => $row['ticket_reply_type'], 'by' => $by, 'created' => $row['ticket_reply_created_at'],
                    'time_worked' => clientExportHours(clientExportSeconds($row['ticket_reply_time_worked'])), 'reply' => clientExportText($row['ticket_reply'])];
            });
            break;

        case 'ticket_tasks':
            foreach ($fetch("SELECT task_name, task_completed_at, ticket_prefix, ticket_number FROM tasks JOIN tickets ON ticket_id = task_ticket_id
                WHERE ticket_client_id = $client_id" . $live('ticket_archived_at') . " ORDER BY ticket_number DESC, task_order, task_id") as $row) {
                $rows[] = ['ticket' => ticketReference($row), 'task' => $row['task_name'], 'completed' => clientExportDate($row['task_completed_at'])];
            }
            break;

        case 'recurring_tickets':
            foreach ($fetch("SELECT * FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id ORDER BY recurring_ticket_subject") as $row) {
                $rows[] = ['subject' => $row['recurring_ticket_subject'], 'priority' => $row['recurring_ticket_priority'], 'category' => $name('category', $row['recurring_ticket_category']),
                    'frequency' => $row['recurring_ticket_frequency'], 'next_run' => clientExportDate($row['recurring_ticket_next_run']),
                    'paused' => clientExportDate($row['recurring_ticket_paused_at']), 'assigned_to' => $name('user', $row['recurring_ticket_assigned_to']),
                    'contact' => $name('contact', $row['recurring_ticket_contact_id']), 'asset' => $name('asset', $row['recurring_ticket_asset_id']),
                    'details' => clientExportText($row['recurring_ticket_details']), 'created' => $row['recurring_ticket_created_at']];
            }
            break;

        case 'projects':
            foreach ($fetch("SELECT * FROM projects WHERE project_client_id = $client_id" . $live('project_archived_at') . " ORDER BY project_number DESC") as $row) {
                $rows[] = ['number' => projectReference($row), 'name' => $row['project_name'], 'description' => clientExportText($row['project_description']),
                    'manager' => $name('user', $row['project_manager']), 'contact' => $name('contact', $row['project_contact_id']), 'start' => clientExportDate($row['project_start']),
                    'due' => clientExportDate($row['project_due']), 'budget_hours' => floatval($row['project_budget_hours']) ?: '', 'completed' => clientExportDate($row['project_completed_at']),
                    'created' => $row['project_created_at'], 'archived' => clientExportDate($row['project_archived_at'])];
            }
            break;

        case 'invoices':
            foreach ($fetch("SELECT invoices.*, (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE payment_invoice_id = invoice_id) AS paid FROM invoices
                WHERE invoice_client_id = $client_id" . $live('invoice_archived_at') . " ORDER BY invoice_number DESC") as $row) {
                $rows[] = ['number' => $row['invoice_prefix'] . $row['invoice_number'], 'scope' => $row['invoice_scope'], 'status' => $row['invoice_status'],
                    'date' => clientExportDate($row['invoice_date']), 'due' => clientExportDate($row['invoice_due']), 'amount' => round(floatval($row['invoice_amount']), 2),
                    'paid' => round(floatval($row['paid']), 2), 'balance' => in_array($row['invoice_status'], ['Draft', 'Cancelled', 'Non-Billable'], true) ? 0 : round(floatval($row['invoice_amount']) - floatval($row['paid']), 2),
                    'discount' => round(floatval($row['invoice_discount_amount']), 2), 'credit' => round(floatval($row['invoice_credit_amount']), 2),
                    'currency' => $row['invoice_currency_code'], 'category' => $name('category', $row['invoice_category_id']), 'note' => clientExportText($row['invoice_note']),
                    'archived' => clientExportDate($row['invoice_archived_at'])];
            }
            break;

        case 'invoice_items':
        case 'quote_items':
        case 'recurring_invoice_items':
            [$parent, $table, $prefix, $key] = [
                'invoice_items' => ['invoice', 'invoices', 'invoice', 'item_invoice_id'],
                'quote_items' => ['quote', 'quotes', 'quote', 'item_quote_id'],
                'recurring_invoice_items' => ['recurring_invoice', 'recurring_invoices', 'recurring_invoice', 'item_recurring_invoice_id'],
            ][$dataset];
            foreach ($fetch("SELECT i.*, CONCAT({$prefix}_prefix, {$prefix}_number) AS parent_ref FROM $dataset i JOIN $table ON {$prefix}_id = i.$key
                WHERE {$prefix}_client_id = $client_id" . $live("{$prefix}_archived_at") . " AND i.item_archived_at IS NULL ORDER BY {$prefix}_number DESC, i.item_order, i.item_id") as $row) {
                $rows[] = [$parent => $row['parent_ref'], 'item' => $row['item_name'], 'description' => $row['item_description'], 'quantity' => floatval($row['item_quantity']),
                    'price' => round(floatval($row['item_price']), 2), 'subtotal' => round(floatval($row['item_subtotal']), 2), 'tax' => round(floatval($row['item_tax']), 2),
                    'total' => round(floatval($row['item_total']), 2)];
            }
            break;

        case 'payments':
            foreach ($fetch("SELECT payments.*, CONCAT(invoice_prefix, invoice_number) AS invoice_ref FROM payments JOIN invoices ON invoice_id = payment_invoice_id
                WHERE invoice_client_id = $client_id" . $live('invoice_archived_at') . " ORDER BY payment_date DESC, payment_id DESC") as $row) {
                $rows[] = ['invoice' => $row['invoice_ref'], 'date' => clientExportDate($row['payment_date']), 'amount' => round(floatval($row['payment_amount']), 2),
                    'currency' => $row['payment_currency_code'], 'method' => $row['payment_method'], 'reference' => $row['payment_reference']];
            }
            break;

        case 'quotes':
            foreach ($fetch("SELECT * FROM quotes WHERE quote_client_id = $client_id" . $live('quote_archived_at') . " ORDER BY quote_number DESC") as $row) {
                $rows[] = ['number' => $row['quote_prefix'] . $row['quote_number'], 'scope' => $row['quote_scope'], 'status' => $row['quote_status'],
                    'date' => clientExportDate($row['quote_date']), 'expires' => clientExportDate($row['quote_expire']), 'amount' => round(floatval($row['quote_amount']), 2),
                    'discount' => round(floatval($row['quote_discount_amount']), 2), 'currency' => $row['quote_currency_code'], 'category' => $name('category', $row['quote_category_id']),
                    'note' => clientExportText($row['quote_note']), 'archived' => clientExportDate($row['quote_archived_at'])];
            }
            break;

        case 'recurring_invoices':
            foreach ($fetch("SELECT * FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id" . $live('recurring_invoice_archived_at') . " ORDER BY recurring_invoice_number") as $row) {
                $rows[] = ['number' => $row['recurring_invoice_prefix'] . $row['recurring_invoice_number'], 'scope' => $row['recurring_invoice_scope'],
                    'frequency' => recurringInvoiceFrequencies()[$row['recurring_invoice_frequency']] ?? ucfirst((string) $row['recurring_invoice_frequency']), 'active' => clientExportYes($row['recurring_invoice_status']),
                    'next_date' => clientExportDate($row['recurring_invoice_next_date']), 'last_sent' => clientExportDate($row['recurring_invoice_last_sent']),
                    'amount' => round(floatval($row['recurring_invoice_amount']), 2), 'currency' => $row['recurring_invoice_currency_code'],
                    'auto_send' => clientExportYes($row['recurring_invoice_auto_send']), 'category' => $name('category', $row['recurring_invoice_category_id']),
                    'note' => clientExportText($row['recurring_invoice_note']), 'archived' => clientExportDate($row['recurring_invoice_archived_at'])];
            }
            break;

        case 'credits':
            foreach ($fetch("SELECT credits.*, (SELECT CONCAT(invoice_prefix, invoice_number) FROM invoices WHERE invoice_id = credit_invoice_id AND invoice_client_id = $client_id) AS invoice_ref
                FROM credits WHERE credit_client_id = $client_id ORDER BY credit_created_at DESC") as $row) {
                $rows[] = ['amount' => round(floatval($row['credit_amount']), 2), 'type' => $row['credit_type'], 'note' => $row['credit_note'], 'created' => $row['credit_created_at'],
                    'expires' => clientExportDate($row['credit_expire_at']), 'invoice' => (string) $row['invoice_ref']];
            }
            break;
    }

    // NULL columns read as empty, the same as in the CSV
    foreach ($rows as &$row) {
        foreach ($row as $column => $value) {
            if ($value === null) {
                $row[$column] = '';
            }
        }
    }
    unset($row);

    return $rows;
}

/** Seconds in a TIME value ("01:30:00"). */
function clientExportSeconds($time) {
    $parts = array_map('intval', explode(':', (string) $time));
    return ($parts[0] ?? 0) * 3600 + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0);
}

/**
 * Every link between two of the client's records whose sections are both in
 * the export - what a document covers, the devices and users on a license,
 * what a service depends on - one row per link, each way it reads naturally.
 */
function clientExportLinks($client_id, $options) {
    global $mysqli;
    $client_id = intval($client_id);
    $type_section = ['asset' => 'assets', 'contact' => 'contacts', 'document' => 'documents', 'file' => 'files', 'software' => 'software', 'location' => 'locations',
        'credential' => 'credentials', 'vendor' => 'vendors', 'service' => 'services', 'domain' => 'domains', 'certificate' => 'certificates'];
    $labels = ['software' => 'License'];
    $rows = [];
    foreach (LINK_RELATIONS as $pair => $relation) {
        [$a, $b] = explode('|', $pair);
        if (!clientExportHas($options, $type_section[$a]) || !clientExportHas($options, $type_section[$b])) {
            continue;
        }
        $ea = LINK_ENTITIES[$a];
        $eb = LINK_ENTITIES[$b];
        $archived = $options['archived'] ? '' : " AND ta.{$ea['archived']} IS NULL AND tb.{$eb['archived']} IS NULL";
        $sql = mysqli_query($mysqli, "SELECT ta.{$ea['name']} AS a_name, tb.{$eb['name']} AS b_name FROM {$relation['table']} l
            JOIN {$ea['table']} ta ON ta.{$ea['id']} = l.{$ea['id']} JOIN {$eb['table']} tb ON tb.{$eb['id']} = l.{$eb['id']}
            WHERE ta.{$ea['client']} = $client_id AND tb.{$eb['client']} = $client_id $archived");
        while ($sql && ($row = mysqli_fetch_assoc($sql))) {
            // Read from the thing that depends or covers: a service's asset, a document's contact, a license's device
            $from_b = in_array($b, ['service', 'software', 'document'], true) && !in_array($a, ['service', 'software', 'document'], true);
            [$ft, $fn, $tt, $tn] = $from_b ? [$b, $row['b_name'], $a, $row['a_name']] : [$a, $row['a_name'], $b, $row['b_name']];
            $rows[] = ['type' => $labels[$ft] ?? ucfirst($ft), 'name' => $fn, 'linked_type' => $labels[$tt] ?? ucfirst($tt), 'linked_name' => $tn];
        }
    }
    usort($rows, function ($x, $y) {
        return strcmp($x['type'], $y['type']) ?: strcasecmp($x['name'], $y['name']) ?: strcmp($x['linked_type'], $y['linked_type']) ?: strcasecmp($x['linked_name'], $y['linked_name']);
    });
    return $rows;
}

/** A dataset's rows as CSV text - headings from the dataset, formula-guarded cells. */
function clientExportCsv($dataset, array $rows) {
    $columns = clientExportDatasets()[$dataset]['columns'];
    $out = fopen('php://memory', 'w');
    // A byte-order mark, so Excel reads the UTF-8 rather than guessing
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_values($columns), ',', '"', '\\');
    foreach ($rows as $row) {
        $values = [];
        foreach (array_keys($columns) as $key) {
            $values[] = escapeCsvFormula((string) ($row[$key] ?? ''));
        }
        fputcsv($out, $values, ',', '"', '\\');
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return $csv;
}

/** A dataset's rows for JSON - the dataset's columns only, none of the working fields. */
function clientExportJsonRows($dataset, array $rows) {
    $keys = array_keys(clientExportDatasets()[$dataset]['columns']);
    return array_map(function ($row) use ($keys) {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $row[$key] ?? '';
        }
        return $out;
    }, $rows);
}

/*
 * ---------- the documentation PDF ----------
 */

/** The company's own details - for the cover and "getting support". */
function clientExportCompany() {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code, company_email, company_website, company_logo, company_country
        FROM companies WHERE company_id = 1"));
    return $row ?: ['company_name' => '', 'company_phone' => '', 'company_phone_country_code' => '', 'company_email' => '', 'company_website' => '', 'company_logo' => '', 'company_country' => ''];
}

/** "Oct 16, 2026" for a stored date, or ''. */
function clientExportShowDate($value) {
    $value = clientExportDate($value);
    return $value === '' ? '' : date('M j, Y', strtotime($value));
}

/** Text for a PDF cell: escaped, line breaks kept. */
function clientExportPdfText($value) {
    return nl2br(escapeHtml(trim((string) $value)));
}

/** Small grey text under a value in a PDF cell. */
function clientExportPdfSub($value) {
    $value = trim((string) $value);
    return $value === '' ? '' : '<br><span style="color:#6c757d;font-size:7.5pt;">' . clientExportPdfText($value) . '</span>';
}

/**
 * A table for the PDF. $columns is heading => width in percent, $rows lists
 * of cell HTML in the same order. TCPDF sizes a table from every cell, so
 * each one carries its width; the heading row repeats on a new page.
 */
function clientExportPdfTable(array $columns, array $rows) {
    $widths = array_values($columns);
    $html = '<table cellpadding="3.5" cellspacing="0" border="0"><thead><tr bgcolor="#343a40" style="color:#ffffff;font-weight:bold;font-size:7.5pt;">';
    foreach ($columns as $label => $width) {
        $html .= '<th width="' . $width . '%">' . escapeHtml($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach (array_values($rows) as $i => $cells) {
        $html .= '<tr' . ($i % 2 ? ' bgcolor="#f3f5f7"' : '') . ' nobr="true">';
        foreach (array_values($cells) as $c => $cell) {
            $html .= '<td width="' . ($widths[$c] ?? 10) . '%">' . ($cell === '' ? '<span style="color:#adb5bd;">-</span>' : $cell) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

/** Label / value pairs as a two-column table - for a client's or a location's details. */
function clientExportPdfFacts(array $facts, $label_width = 26) {
    $html = '<table cellpadding="3" cellspacing="0" border="0">';
    foreach ($facts as $label => $value) {
        if (trim(strip_tags((string) $value)) === '') {
            continue;
        }
        $html .= '<tr nobr="true"><td width="' . $label_width . '%" style="color:#6c757d;">' . escapeHtml($label) . '</td><td width="' . (100 - $label_width) . '%">' . $value . '</td></tr>';
    }
    return $html . '</table>';
}

/**
 * A document's HTML made fit for TCPDF: purified, and every image either
 * one of the document's own (uploads/documents/<id>/), inlined, or dropped -
 * TCPDF would otherwise go and fetch any URL a document points at, and a
 * format it cannot draw ends the whole PDF. Wide images are scaled to the
 * page.
 */
function clientExportPdfDocumentHtml($html, $document_id) {
    $html = documentHtmlPurify($html);
    if (stripos($html, '<img') === false) {
        return $html;
    }
    $base = realpath(__DIR__ . '/../uploads/documents/' . intval($document_id));
    return preg_replace_callback('/<img\b[^>]*>/i', function ($match) use ($base) {
        $tag = $match[0];
        $src = preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $tag, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
        $alt = preg_match('/\balt\s*=\s*"([^"]*)"/i', $tag, $m) ? $m[1] : '';
        $data = null;
        if (preg_match('#^data:image/(png|jpe?g|gif);base64,(.+)$#is', $src, $m)) {
            $data = base64_decode($m[2], true) ?: null;
        } elseif ($base && preg_match('#^/?uploads/documents/\d+/([^/?\#]+)$#', $src, $m)) {
            $path = realpath($base . '/' . rawurldecode($m[1]));
            if ($path && strpos($path, $base . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
                $data = file_get_contents($path);
            }
        }
        $info = $data ? @getimagesizefromstring($data) : false;
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
            return $alt !== '' ? '<i>[' . $alt . ']</i>' : '<i>[image]</i>';
        }
        $width = min(intval($info[0]), 640);
        return '<img src="@' . base64_encode($data) . '" width="' . $width . '" alt="' . $alt . '">';
    }, $html);
}

/**
 * The documentation PDF for one client, as a string. $options from
 * clientExportOptions(): the sections to include, and with 'secrets' the
 * passwords and 2FA secrets - the PDF is then locked with 'password'.
 */
function clientExportPdf($client_id, $options) {
    global $session_name, $config_whitelabel_enabled;

    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/client_export_pdf.php';

    $client_id = intval($client_id);
    $pdf_options = ['archived' => false] + $options;
    $data = function ($dataset) use ($client_id, $pdf_options) {
        static $cache = [];
        return $cache[$dataset] ?? ($cache[$dataset] = clientExportRows($dataset, $client_id, $pdf_options));
    };
    $client = $data('client')[0] ?? ['name' => ''];
    $company = clientExportCompany();
    $sections = clientExportSections();
    $has = function ($section) use ($options) {
        return clientExportHas($options, $section);
    };
    $e = function ($value) {
        return clientExportPdfText($value);
    };

    // Letter for North America, A4 everywhere else
    $format = in_array($company['company_country'], ['United States', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new ClientExportPdf('P', 'mm', $format, true, 'UTF-8', false);
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor($company['company_name']);
    $pdf->SetTitle($client['name'] . ' - IT Documentation');
    $pdf->SetSubject('IT Documentation');
    $pdf->SetMargins(16, 20, 16);
    $pdf->SetHeaderMargin(8);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->setImageScale(1.6);
    $pdf->setFontSubsetting(true);
    $pdf->running_title = $client['name'];
    $pdf->running_footer = trim($company['company_name'] . ' · Confidential · ' . date('M j, Y'), ' ·');
    if ($options['secrets']) {
        // Readable and printable with the password, not editable
        $pdf->SetProtection(['modify', 'annot-forms', 'fill-forms', 'assemble'], $options['password'], bin2hex(random_bytes(16)), 3);
    }
    $margins = $pdf->getMargins();
    $width = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

    // By reference: a rolled-back document swaps the object out from under them
    $write = function ($html) use (&$pdf) {
        $pdf->SetFont('dejavusans', '', 8.5);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->writeHTML($html, true, false, true, false, '');
    };
    $chapter = function ($title, $subtitle = '', $level = 0, $new_page = false, $size = null) use (&$pdf, $width) {
        // A heading near the foot of a page goes over to the next with what follows it
        if ($new_page || $pdf->GetY() > $pdf->getPageHeight() - 70) {
            $pdf->AddPage();
        } elseif ($pdf->GetY() > 30) {
            $pdf->Ln($level ? 3 : 6);
        }
        $pdf->Bookmark($title, $level, 0, '', $level ? '' : 'B', [33, 37, 41]);
        $size = $size ?? ($level ? 11 : 15);
        $pdf->SetFont('dejavusans', 'B', $size);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->MultiCell($width, $size > 12 ? 8 : 6, $title, 0, 'L', false, 1);
        if (!$level) {
            $pdf->SetDrawColor(13, 110, 253);
            $pdf->SetLineWidth(0.6);
            $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 18, $pdf->GetY());
            $pdf->Ln(1.5);
        }
        if ($subtitle !== '') {
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->SetTextColor(108, 117, 125);
            $pdf->MultiCell($width, 4, $subtitle, 0, 'L', false, 1);
        }
        $pdf->Ln(1.5);
    };
    $count = function ($n, $one, $many = null) {
        return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
    };

    /*
     * The cover
     */
    $pdf->AddPage();
    $logo = $company['company_logo'] ? realpath(__DIR__ . '/../uploads/settings/' . basename($company['company_logo'])) : false;
    if ($logo && is_file($logo) && @getimagesize($logo)) {
        try {
            $pdf->Image($logo, $margins['left'], 22, 0, 16, '', '', '', true, 300, '', false, false, 0, 'L');
        } catch (Exception $exception) {
            // A logo TCPDF cannot read leaves the cover without one
        }
    }
    $pdf->SetFillColor(13, 110, 253);
    $pdf->Rect($margins['left'], 78, 22, 1.6, 'F');
    $pdf->SetY(86);
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell($width, 7, 'IT Documentation', 0, 1, 'L');
    $pdf->SetFont('dejavusans', 'B', 26);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->MultiCell($width, 12, $client['name'], 0, 'L', false, 1);
    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetTextColor(73, 80, 87);
    $pdf->MultiCell($width, 5, 'Prepared ' . date('F j, Y') . ' by ' . stripslashes((string) $session_name) . ($company['company_name'] ? ', ' . $company['company_name'] : ''), 0, 'L', false, 1);

    $included = array_map(function ($key) use ($sections) { return lcfirst($sections[$key]['label']); }, $options['sections']);
    $about = 'This document records ' . $client['name'] . "'s IT environment as documented on " . date('F j, Y') . ': the client details'
        . ($included ? ', ' . implode(', ', array_slice($included, 0, -1)) . (count($included) > 1 ? ' and ' : '') . end($included) : '') . '.';
    $pdf->SetY(150);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetTextColor(73, 80, 87);
    $pdf->MultiCell($width, 5, $about, 0, 'L', false, 1);
    $pdf->Ln(3);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetTextColor($options['secrets'] ? 176 : 73, $options['secrets'] ? 42 : 80, $options['secrets'] ? 55 : 87);
    $pdf->MultiCell($width, 5, $options['secrets']
        ? 'Confidential - this copy includes passwords. Keep it somewhere secure and destroy printed copies when they are no longer needed.'
        : 'Confidential - store it securely and share it only with people who look after these systems.', 0, 'L', false, 1);

    $contact_lines = array_filter([$company['company_name'], clientExportPhone($company['company_phone'], $company['company_phone_country_code']), $company['company_email'], $company['company_website']]);
    $pdf->SetY($pdf->getPageHeight() - 46);
    $pdf->SetDrawColor(222, 226, 230);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($margins['left'], $pdf->GetY(), $pdf->getPageWidth() - $margins['right'], $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(73, 80, 87);
    $pdf->MultiCell($width, 4.5, implode("\n", $contact_lines), 0, 'L', false, 1);
    if (!$config_whitelabel_enabled) {
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetTextColor(173, 181, 189);
        $pdf->Cell($width, 4, 'Powered by ITFlow', 0, 1, 'R');
    }

    /*
     * Overview
     */
    $pdf->AddPage();
    $contacts = $has('contacts') ? $data('contacts') : [];
    $locations = $has('locations') ? $data('locations') : [];
    $primary_location = $locations[0] ?? null;
    $chapter('Overview');
    $write(clientExportPdfFacts([
        'Client' => '<b>' . $e($client['name']) . '</b>',
        'Industry' => $e($client['industry'] ?? ''),
        'Website' => $e($client['website'] ?? ''),
        'Main address' => $primary_location ? $e(formatAddress($primary_location['address'], $primary_location['city'], $primary_location['state'], $primary_location['zip'], $primary_location['country'])) : '',
        'Main phone' => $primary_location ? $e($primary_location['phone']) : '',
        'Client since' => $e(clientExportShowDate($client['created'] ?? '')),
    ]));

    $key_contacts = array_values(array_filter($contacts, function ($c) {
        return $c['primary'] === 'Yes' || $c['billing'] === 'Yes' || $c['technical'] === 'Yes' || $c['important'] === 'Yes';
    }));
    if ($key_contacts) {
        $chapter('Key contacts', '', 1);
        $write(clientExportPdfTable(['Name' => 24, 'Role' => 16, 'Email' => 30, 'Phone' => 15, 'Mobile' => 15], array_map(function ($c) use ($e) {
            $roles = array_keys(array_filter(['Primary' => $c['primary'] === 'Yes', 'Billing' => $c['billing'] === 'Yes', 'Technical' => $c['technical'] === 'Yes', 'Important' => $c['important'] === 'Yes']));
            return ['<b>' . $e($c['name']) . '</b>' . clientExportPdfSub($c['title']), $e(implode(', ', $roles)), $e($c['email']), $e($c['phone']), $e($c['mobile'])];
        }, $key_contacts)));
    }

    // What the document holds, at a glance
    $glance = [];
    $tally = ['contacts' => ['contacts', 'contact'], 'locations' => ['locations', 'location'], 'vendors' => ['vendors', 'vendor'], 'networks' => ['networks', 'network'],
        'assets' => ['assets', 'asset'], 'racks' => ['racks', 'rack'], 'software' => ['licenses', 'license'], 'domains' => ['domains', 'domain'],
        'certificates' => ['certificates', 'certificate'], 'services' => ['services', 'service'], 'credentials' => ['credentials', 'credential'],
        'documents' => ['documents', 'document'], 'files' => ['files', 'file']];
    foreach ($tally as $section => [$dataset, $noun]) {
        if ($has($section)) {
            $glance[] = [$e($sections[$section]['label']), $e($count(count($data($dataset)), $noun))];
        }
    }
    if ($glance) {
        $chapter('In this document', '', 1);
        $write(clientExportPdfTable(['Section' => 50, 'Records' => 50], $glance));
    }

    $slas = $data('slas');
    if ($slas) {
        $chapter('Service levels', 'Targets run in business hours.', 1);
        $minutes = function ($m) {
            $m = intval($m);
            return $m >= 60 ? round($m / 60, 1) . ' h' : $m . ' min';
        };
        $write(clientExportPdfTable(['Priority' => 20, 'SLA' => 40, 'First reply within' => 20, 'Resolved within' => 20], array_map(function ($s) use ($e, $minutes) {
            return [$e($s['priority']), $e($s['sla']), $e($minutes($s['response_minutes'])), $e($minutes($s['resolution_minutes']))];
        }, $slas)));
    }

    $support = array_filter(['Company' => $e($company['company_name']), 'Phone' => $e(clientExportPhone($company['company_phone'], $company['company_phone_country_code'])),
        'Email' => $e($company['company_email']), 'Website' => $e($company['company_website'])]);
    if ($support) {
        $chapter('Getting support', '', 1);
        $write(clientExportPdfFacts($support));
    }

    /*
     * Contacts
     */
    if ($has('contacts') && $contacts) {
        $chapter('Contacts', $count(count($contacts), 'contact'), 0, true);
        $write(clientExportPdfTable(['Name' => 24, 'Email' => 25, 'Phone' => 17, 'Mobile' => 15, 'Location' => 19], array_map(function ($c) use ($e) {
            $roles = implode(', ', array_keys(array_filter(['Primary' => $c['primary'] === 'Yes', 'Billing' => $c['billing'] === 'Yes', 'Technical' => $c['technical'] === 'Yes'])));
            return ['<b>' . $e($c['name']) . '</b>' . clientExportPdfSub(trim($c['title'] . ($c['department'] ? ' · ' . $c['department'] : ''), ' ·')) . clientExportPdfSub($roles),
                $e($c['email']), $e($c['phone']), $e($c['mobile']), $e($c['location'])];
        }, $contacts)));
    }

    /*
     * Locations
     */
    if ($has('locations') && $locations) {
        $chapter('Locations', $count(count($locations), 'location'), 0, true);
        foreach ($locations as $location) {
            $chapter($location['name'] . ($location['primary'] === 'Yes' ? ' (primary)' : ''), $location['description'], 1);
            $write(clientExportPdfFacts([
                'Address' => $e(formatAddress($location['address'], $location['city'], $location['state'], $location['zip'], $location['country'])),
                'Phone' => $e($location['phone']), 'Fax' => $e($location['fax']), 'Hours' => $e($location['hours']), 'Contact' => $e($location['contact']), 'Notes' => $e($location['notes']),
            ]));
        }
    }

    /*
     * Networks
     */
    $networks = $has('networks') ? $data('networks') : [];
    if ($networks) {
        $chapter('Networks', $count(count($networks), 'network'), 0, true);
        $write(clientExportPdfTable(['Network' => 22, 'Address' => 20, 'Gateway' => 14, 'DNS' => 16, 'DHCP range' => 16, 'Location' => 12], array_map(function ($n) use ($e) {
            return ['<b>' . $e($n['name']) . '</b>' . clientExportPdfSub(trim($n['type'] . ($n['vlan'] !== '' ? ' · VLAN ' . $n['vlan'] : '') . ($n['provider'] ? ' · ' . $n['provider'] : ''), ' ·')),
                $e($n['network']) . clientExportPdfSub($n['subnet']), $e($n['gateway']), $e(trim($n['primary_dns'] . "\n" . $n['secondary_dns'])), $e($n['dhcp_range']), $e($n['location'])];
        }, $networks)));
        $addresses = [];
        foreach ($data('ip_addresses') as $ip) {
            $addresses[$ip['network']][] = $ip;
        }
        foreach ($networks as $network) {
            if (!empty($addresses[$network['name']])) {
                $chapter($network['name'] . ' - addresses', '', 1);
                $write(clientExportPdfTable(['Address' => 16, 'Type' => 13, 'Hostname' => 20, 'MAC address' => 19, 'Description' => 32], array_map(function ($ip) use ($e) {
                    return [$e($ip['address']), $e(ucfirst((string) $ip['type'])), $e($ip['hostname']), $e($ip['mac']), $e($ip['description'])];
                }, $addresses[$network['name']])));
            }
        }
    }

    /*
     * Assets, by what they are
     */
    $assets = $has('assets') ? $data('assets') : [];
    if ($assets) {
        $chapter('Assets', $count(count($assets), 'asset') . ' in use', 0, true);
        $families = [
            'Servers' => ['Server', 'Virtual Machine'],
            'Network equipment' => ['Firewall/Router', 'Switch', 'Access Point'],
            'Workstations' => ['Desktop', 'Laptop'],
            'Phones and mobile devices' => ['Phone', 'Mobile Phone', 'Tablet'],
        ];
        $placed = [];
        foreach ($families as $family => $types) {
            $placed[$family] = array_values(array_filter($assets, function ($a) use ($types) { return in_array($a['type'], $types, true); }));
        }
        $placed['Printers and other devices'] = array_values(array_filter($assets, function ($a) use ($families) {
            return !in_array($a['type'], array_merge(...array_values($families)), true);
        }));
        $warranty = function ($date) {
            if ($date === '') {
                return '';
            }
            $expired = $date < date('Y-m-d');
            return '<span style="color:' . ($expired ? '#b02a37' : '#212529') . ';">' . ($expired ? 'Ended ' : '') . clientExportShowDate($date) . '</span>';
        };
        foreach ($placed as $family => $list) {
            if (!$list) {
                continue;
            }
            $chapter($family, $count(count($list), 'device'), 1);
            $user_facing = in_array($family, ['Workstations', 'Phones and mobile devices', 'Printers and other devices'], true);
            $write(clientExportPdfTable(
                ['Name' => 19, 'Make and model' => 19, 'Serial' => 14, ($family === 'Network equipment' ? 'Firmware' : 'OS') => 14, ($user_facing ? 'Used by' : 'IP address') => 13, 'Location' => 11, 'Warranty' => 10],
                array_map(function ($a) use ($e, $warranty, $user_facing) {
                    return ['<b>' . $e($a['name']) . '</b>' . clientExportPdfSub($a['type'] . ($a['status'] && $a['status'] !== 'Deployed' ? ' · ' . $a['status'] : '')),
                        $e(trim($a['make'] . ' ' . $a['model'])), $e($a['serial']), $e($a['os']), $e($user_facing ? $a['used_by'] : $a['ip']),
                        $e($a['location']) . clientExportPdfSub($a['physical_location']), $warranty($a['warranty_expire'])];
                }, $list)
            ));
        }
    }

    /*
     * Racks
     */
    $racks = $has('racks') ? $data('racks') : [];
    if ($racks) {
        $chapter('Racks', $count(count($racks), 'rack'), 0, true);
        $units = [];
        foreach ($data('rack_units') as $unit) {
            $units[$unit['rack']][] = $unit;
        }
        foreach ($racks as $rack) {
            $chapter($rack['name'], trim(implode(' · ', array_filter([$rack['units'] ? $rack['units'] . 'U' : '', $rack['model'], $rack['location'], $rack['physical_location']]))), 1);
            $list = $units[$rack['name']] ?? [];
            usort($list, function ($a, $b) { return intval($b['from_unit']) <=> intval($a['from_unit']); });
            $write($list ? clientExportPdfTable(['Units' => 12, 'Face' => 10, 'Device' => 38, 'Type' => 16, 'Power' => 10, 'Notes' => 14], array_map(function ($u) use ($e) {
                $span = intval($u['from_unit']) === intval($u['to_unit']) ? 'U' . $u['from_unit'] : 'U' . $u['from_unit'] . '-' . $u['to_unit'];
                return [$e($span), $e($u['face']), '<b>' . $e($u['device']) . '</b>', $e($u['type']), $e($u['power_watts'] ? $u['power_watts'] . ' W' : ''), $e($u['notes'])];
            }, $list)) : '<span style="color:#6c757d;">Nothing documented in this rack.</span>');
        }
    }

    /*
     * Licenses
     */
    $licenses = $has('software') ? $data('licenses') : [];
    if ($licenses) {
        $chapter('Licenses', $count(count($licenses), 'license'), 0, true);
        $keys = [];
        foreach ($data('license_keys') as $key) {
            $keys[$key['license']][] = $key['key'] . ($key['assigned_to'] !== '' ? ' (' . $key['assigned_to'] . ')' : '');
        }
        $write(clientExportPdfTable(['License' => 26, 'Type' => 15, 'Keys' => 24, 'Seats' => 9, 'Renews' => 12, 'Vendor' => 14], array_map(function ($l) use ($e, $keys) {
            $seats = $l['seats'] !== '' ? $l['seats_used'] . ' of ' . $l['seats'] : ($l['seats_used'] ? $l['seats_used'] . ' in use' : '');
            $license_keys = array_filter(array_merge([$l['key']], $keys[$l['name']] ?? []), 'strlen');
            return ['<b>' . $e(trim($l['name'] . ' ' . $l['version'])) . '</b>' . clientExportPdfSub($l['description']), $e($l['type']) . clientExportPdfSub($l['license_type']),
                $license_keys ? '<span style="font-family:dejavusansmono;font-size:7pt;">' . $e(implode("\n", $license_keys)) . '</span>' : '', $e($seats),
                $e(clientExportShowDate($l['expires'])), $e($l['vendor'])];
        }, $licenses)));
        $holders = [];
        foreach ($data('license_seats') as $seat) {
            $holders[$seat['license']][$seat['seat']][] = $seat['name'];
        }
        if ($holders) {
            $chapter('Who has each license', '', 1);
            $write(clientExportPdfTable(['License' => 26, 'Devices' => 37, 'Users' => 37], array_map(function ($license, $seats) use ($e) {
                return ['<b>' . $e($license) . '</b>', $e(implode(', ', $seats['Device'] ?? [])), $e(implode(', ', $seats['User'] ?? []))];
            }, array_keys($holders), $holders)));
        }
    }

    /*
     * Domains and certificates
     */
    $domains = $has('domains') ? $data('domains') : [];
    if ($domains) {
        $chapter('Domains', $count(count($domains), 'domain'), 0, true);
        $write(clientExportPdfTable(['Domain' => 22, 'Expires' => 14, 'Registrar' => 16, 'DNS host' => 16, 'Web host' => 16, 'Mail host' => 16], array_map(function ($d) use ($e) {
            return ['<b>' . $e($d['name']) . '</b>' . clientExportPdfSub($d['description']), $e(clientExportShowDate($d['expires'])) . clientExportPdfSub($d['auto_renew'] === 'Yes' ? 'Auto-renews' : ''),
                $e($d['registrar']), $e($d['dns_host']), $e($d['web_host']), $e($d['mail_host'])];
        }, $domains)));
        $chapter('DNS', 'As last looked up.', 1);
        $write(clientExportPdfTable(['Domain' => 22, 'Name servers' => 30, 'Mail servers' => 30, 'A record' => 18], array_map(function ($d) use ($e) {
            return ['<b>' . $e($d['name']) . '</b>', $e($d['name_servers']), $e($d['mail_servers']), $e($d['ip'])];
        }, $domains)));
    }
    $certificates = $has('certificates') ? $data('certificates') : [];
    if ($certificates) {
        $chapter('Certificates', $count(count($certificates), 'certificate'), 0, !$domains);
        $write(clientExportPdfTable(['Certificate' => 26, 'Host' => 22, 'Issued by' => 20, 'Expires' => 14, 'Last check' => 18], array_map(function ($c) use ($e) {
            return ['<b>' . $e($c['name']) . '</b>', $e($c['host']), $e($c['issued_by']), $e(clientExportShowDate($c['expires'])), $e($c['check']) . clientExportPdfSub(clientExportShowDate($c['checked']))];
        }, $certificates)));
    }

    /*
     * Services - what each one is, what it runs on and needs, who supports it
     */
    $services = $has('services') ? $data('services') : [];
    if ($services) {
        $chapter('Services', $count(count($services), 'service') . ', most important first', 0, true);
        $depends = [];
        foreach ($data('links') as $link) {
            if ($link['type'] === 'Service') {
                $depends[$link['name']][$link['linked_type']][] = $link['linked_name'];
            }
        }
        foreach ($services as $service) {
            $chapter($service['name'], trim(implode(' · ', array_filter([$service['importance'] ? $service['importance'] . ' importance' : '', $service['category']]))), 1);
            $on = $depends[$service['name']] ?? [];
            $write(clientExportPdfFacts([
                'What it is' => $e($service['description']),
                'Runs on' => $e(implode(', ', $on['Asset'] ?? [])),
                'Domains' => $e(implode(', ', $on['Domain'] ?? [])),
                'Certificates' => $e(implode(', ', $on['Certificate'] ?? [])),
                'Logins' => $e(implode(', ', $on['Credential'] ?? [])),
                'Supported by' => $e(implode(', ', array_merge($on['Vendor'] ?? [], $on['Contact'] ?? []))),
                'Documents' => $e(implode(', ', $on['Document'] ?? [])),
                'Backup' => $e($service['backup']),
                'Notes' => $e($service['notes']),
                'Next review' => $e(clientExportShowDate($service['review_due'])),
            ]));
        }
    }

    /*
     * Vendors
     */
    $vendors = $has('vendors') ? $data('vendors') : [];
    if ($vendors) {
        $chapter('Vendors', $count(count($vendors), 'vendor'), 0, true);
        $write(clientExportPdfTable(['Vendor' => 22, 'Account' => 15, 'Support' => 22, 'Hours and SLA' => 18, 'Account manager' => 23], array_map(function ($v) use ($e) {
            return ['<b>' . $e($v['name']) . '</b>' . clientExportPdfSub($v['description']), $e($v['account_number']) . clientExportPdfSub($v['code'] !== '' ? 'PIN ' . $v['code'] : ''),
                $e(trim($v['phone'] . "\n" . $v['email'] . "\n" . $v['website'])), $e(trim($v['hours'] . "\n" . $v['sla'])),
                $e(trim($v['contact_name'] . "\n" . $v['contact_phone'] . "\n" . $v['contact_email']))];
        }, $vendors)));
    }

    /*
     * Credentials - passwords only when asked for, and then the PDF is locked
     */
    $credentials = $has('credentials') ? $data('credentials') : [];
    if ($credentials) {
        $chapter('Credentials', $count(count($credentials), 'credential') . ($options['secrets'] ? ' - with passwords' : ' - passwords are not included'), 0, true);
        $mono = function ($value) use ($e) {
            return $value === '' ? '' : '<span style="font-family:dejavusansmono;font-size:7.5pt;">' . $e($value) . '</span>';
        };
        if ($options['secrets']) {
            $write(clientExportPdfTable(['Name' => 24, 'Username' => 20, 'Password' => 20, '2FA secret' => 14, 'Link' => 22], array_map(function ($c) use ($e, $mono) {
                return ['<b>' . $e($c['name']) . '</b>' . clientExportPdfSub($c['description']), $mono($c['username']), $mono($c['password']),
                    $c['otp_secret'] !== '' ? $mono($c['otp_secret']) : $e($c['has_2fa'] === 'Yes' ? 'Yes' : ''), $e($c['uri'])];
            }, $credentials)));
        } else {
            $write(clientExportPdfTable(['Name' => 30, 'Username' => 26, '2FA' => 8, 'Link' => 36], array_map(function ($c) use ($e, $mono) {
                return ['<b>' . $e($c['name']) . '</b>' . clientExportPdfSub($c['description']), $mono($c['username']), $e($c['has_2fa'] === 'Yes' ? 'Yes' : ''), $e($c['uri'])];
            }, $credentials)));
        }
    }

    /*
     * Files - listed; the files themselves are in the full export
     */
    $files = $has('files') ? $data('files') : [];
    if ($files) {
        $chapter('Files', $count(count($files), 'file') . ' on record. The files themselves are in the full export.', 0, true);
        $write(clientExportPdfTable(['Name' => 36, 'Folder' => 26, 'Type' => 10, 'Size' => 12, 'Uploaded' => 16], array_map(function ($f) use ($e) {
            return ['<b>' . $e($f['name']) . '</b>' . clientExportPdfSub($f['description']), $e($f['folder']), $e($f['type']), $e(formatBytes($f['size'], 1)), $e(clientExportShowDate($f['created']))];
        }, $files)));
    }

    /*
     * Documents - each one in full, on its own pages
     */
    $documents = $has('documents') ? $data('documents') : [];
    if ($documents) {
        $chapter('Documents', $count(count($documents), 'document') . ', each on the pages that follow', 0, true);
        $write(clientExportPdfTable(['Document' => 46, 'Folder' => 30, 'Updated' => 24], array_map(function ($d) use ($e) {
            return ['<b>' . $e($d['name']) . '</b>' . clientExportPdfSub($d['description']), $e($d['folder']), $e(clientExportShowDate($d['updated'] ?: $d['created']))];
        }, $documents)));
        foreach ($documents as $document) {
            $content = clientExportDocumentContent($document['id'], $client_id);
            $chapter($document['name'], trim(implode(' · ', array_filter([$document['folder'], 'Updated ' . clientExportShowDate($document['updated'] ?: $document['created'])])), ' ·'), 1, true, 15);
            if ($document['description'] !== '') {
                $write('<p style="color:#495057;"><i>' . $e($document['description']) . '</i></p>');
            }
            // One document TCPDF cannot draw goes in as plain text rather than ending the PDF
            $pdf->startTransaction();
            try {
                $write(clientExportPdfDocumentHtml($content, $document['id']));
                $pdf->commitTransaction();
            } catch (Exception $exception) {
                $pdf = $pdf->rollbackTransaction();
                $write('<p style="color:#6c757d;"><i>This document has formatting the PDF cannot show, so here is its text.</i></p>' . $e(clientExportText($content)));
            }
        }
    }

    /*
     * Contents, after the cover
     */
    $pdf->addTOCPage();
    $pdf->SetFont('dejavusans', 'B', 15);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell($width, 8, 'Contents', 0, 1, 'L');
    $pdf->SetDrawColor(13, 110, 253);
    $pdf->SetLineWidth(0.6);
    $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 18, $pdf->GetY());
    $pdf->Ln(4);
    $toc_line = function ($indent, $style) {
        return '<table cellpadding="1.4" cellspacing="0"><tr>' . ($indent ? '<td width="4%"></td>' : '')
            . '<td width="' . ($indent ? 86 : 90) . '%"><span style="' . $style . '">#TOC_DESCRIPTION#</span></td>'
            . '<td width="10%" align="right"><span style="' . $style . '">#TOC_PAGE_NUMBER#</span></td></tr></table>';
    };
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->addHTMLTOC(2, 'Contents', [
        $toc_line(false, 'font-weight:bold;font-size:9.5pt;color:#212529;'),
        $toc_line(true, 'font-size:8.5pt;color:#495057;'),
    ], true, 'B', [33, 37, 41]);
    $pdf->endTOCPage();

    return $pdf->Output('', 'S');
}

/** A document's HTML, checked to belong to the client. */
function clientExportDocumentContent($document_id, $client_id) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT document_content FROM documents WHERE document_id = " . intval($document_id) . " AND document_client_id = " . intval($client_id)));
    return (string) ($row[0] ?? '');
}

/** "Acme Inc - IT Documentation - 2026-10-16.pdf" - $what before the date. */
function clientExportFileName($client_name, $what, $extension) {
    return clientExportSafeName($client_name, 60) . " - $what - " . date('Y-m-d') . ".$extension";
}

/*
 * ---------- the full export (zip) ----------
 */

/** File types already compressed - stored as they are rather than squeezed again. */
const CLIENT_EXPORT_STORED_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'zip', '7z', 'rar', 'gz', 'tgz', 'bz2', 'xz', 'pdf', 'docx', 'xlsx', 'pptx',
    'odt', 'ods', 'odp', 'mp3', 'mp4', 'm4a', 'mov', 'avi', 'mkv', 'webm', 'ogg'];

/** A web page of the export - a document, a ticket, the README - with its own styles, readable offline. */
function clientExportHtmlPage($title, $body, $up = '') {
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . escapeHtml($title) . '</title><style>'
        . 'html{background:#f6f7f9}body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#212529;background:#f6f7f9;margin:0;line-height:1.5;font-size:15px}'
        . 'main{max-width:960px;margin:0 auto;padding:32px 20px 64px}'
        . '.card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px 24px;margin-bottom:18px}'
        . 'h1{font-size:26px;margin:0 0 4px}h2{font-size:19px;margin:28px 0 10px}h3{font-size:16px;margin:20px 0 8px}'
        . '.muted{color:#6c757d}.small{font-size:13px}a{color:#0b5ed7}'
        . 'table{border-collapse:collapse;width:100%;font-size:14px}th,td{text-align:left;padding:6px 10px;border-bottom:1px solid #e9ecef;vertical-align:top}'
        . 'th{background:#f1f3f5;font-weight:600}td.num{text-align:right}'
        . '.badge{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;background:#e9ecef;color:#495057;margin-right:4px}'
        . '.badge.blue{background:#cfe2ff;color:#084298}.badge.amber{background:#fff3cd;color:#664d03}.badge.red{background:#f8d7da;color:#842029}'
        . '.reply{border-left:3px solid #ced4da;padding:4px 0 4px 14px;margin:14px 0}.reply.client{border-color:#0d6efd}.reply.internal{border-color:#ffc107;background:#fffbea}'
        . '.content img{max-width:100%;height:auto}.content table{width:auto}.content td,.content th{border:1px solid #dee2e6}'
        . 'nav.crumbs{font-size:13px;margin-bottom:14px}ul.plain{list-style:none;padding-left:0}ul.plain li{padding:3px 0}'
        . '.notice{background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;padding:12px 16px;margin-bottom:18px}'
        . '</style></head><body><main>' . ($up !== null ? '<nav class="crumbs"><a href="' . $up . 'README.html">&larr; Back to the export</a></nav>' : '')
        . $body . '</main></body></html>';
}

/**
 * Point a page's images at copies inside the archive. $roots maps the web
 * path a stored image lives under ("/uploads/documents/12/") to its folder on
 * disk; anything under one is copied once into $image_folder and the src
 * rewritten relative to the page. Inline (data:) images stay as they are, and
 * anything else is left pointing where it did.
 */
function clientExportLocalImages($html, array $roots, $image_folder, $up, callable $add_file) {
    return preg_replace_callback('/(<img\b[^>]*\bsrc\s*=\s*")([^"]*)(")/i', function ($m) use ($roots, $image_folder, $up, $add_file) {
        $src = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        foreach ($roots as $web => $dir) {
            $web_relative = ltrim($web, '/');
            if (strpos(ltrim($src, '/'), $web_relative) !== 0) {
                continue;
            }
            $name = rawurldecode(substr(ltrim($src, '/'), strlen($web_relative)));
            $base = realpath($dir);
            $path = $base ? realpath($base . '/' . basename($name)) : false;
            if (!$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
                return $m[0];
            }
            $target = rtrim($image_folder, '/') . '/' . clientExportSafeName(basename($name));
            $add_file($target, $path);
            return $m[1] . escapeHtml($up . $target) . $m[3];
        }
        return $m[0];
    }, $html);
}

/**
 * Build the full export for one client into a zip under uploads/tmp, and
 * return its path - false with $error set if it could not be made. The
 * caller sends it and deletes it.
 */
function clientExportArchive($client_id, $options, &$error = '') {
    global $session_name;

    require_once __DIR__ . '/../includes/app_version.php';

    $client_id = intval($client_id);
    $client = clientExportRows('client', $client_id, $options)[0] ?? null;
    if (!$client) {
        $error = 'That client no longer exists.';
        return false;
    }

    $tmp_dir = __DIR__ . '/../uploads/tmp';
    mkdirMissing($tmp_dir);
    $zip_path = $tmp_dir . '/client-export-' . bin2hex(random_bytes(12)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $error = 'The export could not be started - the server could not write to uploads/tmp.';
        return false;
    }
    // Whatever ends the request - an error, a time limit, a dropped download -
    // the export does not stay on the server. An archive still open is
    // dropped first: ZipArchive writes it out when the object is destroyed,
    // which would otherwise come after this.
    $zip_open = true;
    register_shutdown_function(function () use (&$zip_open, $zip, $zip_path) {
        if ($zip_open) {
            try {
                $zip->unchangeAll();
                $zip->close();
            } catch (Throwable $exception) {
                // Nothing more to undo
            }
        }
        if (is_file($zip_path)) {
            @unlink($zip_path);
        }
    });
    if ($options['secrets']) {
        $zip->setPassword($options['password']);
    }

    $root = clientExportSafeName($client['name'], 60) . ' - Export ' . date('Y-m-d') . '/';
    $added = [];
    $failed = false;
    $seal = function ($name) use ($zip, $options, &$failed) {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $zip->setCompressionName($name, in_array($extension, CLIENT_EXPORT_STORED_TYPES, true) ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
        if ($options['secrets'] && !$zip->setEncryptionName($name, ZipArchive::EM_AES_256)) {
            $failed = true;
        }
    };
    $add_string = function ($path, $content) use ($zip, $root, $seal, &$added) {
        $name = $root . $path;
        $zip->addFromString($name, (string) $content);
        $added[mb_strtolower($name)] = true;
        $seal($name);
    };
    $add_file = function ($path, $file) use ($zip, $root, $seal, &$added) {
        $name = $root . $path;
        if (isset($added[mb_strtolower($name)])) {
            return true;
        }
        if (!is_file($file) || !is_readable($file) || !$zip->addFile($file, $name)) {
            return false;
        }
        $added[mb_strtolower($name)] = true;
        $seal($name);
        return true;
    };
    $used = [];
    $missing = [];
    $client_dir = realpath(__DIR__ . "/../uploads/clients/$client_id");
    // A file stored for this client, or false - never one outside the client's own folder
    $client_file = function ($reference) use ($client_dir) {
        $reference = basename((string) $reference);
        if (!$client_dir || $reference === '') {
            return false;
        }
        $path = realpath($client_dir . '/' . $reference);
        return ($path && strpos($path, $client_dir . DIRECTORY_SEPARATOR) === 0 && is_file($path)) ? $path : false;
    };

    $rows = [];
    foreach (clientExportDatasetsFor($options) as $key => $dataset) {
        $rows[$key] = clientExportRows($key, $client_id, $options);
    }

    /*
     * Documents - each as a web page in its folder, with its images
     */
    $document_pages = [];
    if (isset($rows['documents'])) {
        foreach ($rows['documents'] as $i => $document) {
            $folder = 'documents' . ($document['folder'] !== '' ? '/' . implode('/', array_map('clientExportSafeName', explode('/', $document['folder']))) : '');
            $path = clientExportUniquePath($folder, clientExportSafeName($document['name']) . '.html', $used);
            $up = str_repeat('../', substr_count($path, '/'));
            $content = documentHtmlPurify(clientExportDocumentContent($document['id'], $client_id));
            $content = clientExportLocalImages($content, ['/uploads/documents/' . $document['id'] . '/' => __DIR__ . '/../uploads/documents/' . $document['id']],
                'documents/_images/' . $document['id'], $up, $add_file);
            $meta = array_filter([$document['folder'] !== '' ? 'Folder: ' . $document['folder'] : '', 'Updated ' . clientExportShowDate($document['updated'] ?: $document['created']),
                $document['created_by'] !== '' ? 'by ' . $document['created_by'] : '', $document['archived'] !== '' ? 'Archived ' . clientExportShowDate($document['archived']) : '']);
            $add_string($path, clientExportHtmlPage($document['name'], '<div class="card"><h1>' . escapeHtml($document['name']) . '</h1>'
                . '<div class="muted small">' . escapeHtml(implode(' · ', $meta)) . '</div>'
                . ($document['description'] !== '' ? '<p><em>' . escapeHtml($document['description']) . '</em></p>' : '')
                . '</div><div class="card content">' . $content . '</div>', $up));
            $rows['documents'][$i]['path'] = $path;
            $document_pages[] = ['name' => $document['name'], 'folder' => $document['folder'], 'path' => $path, 'archived' => $document['archived']];
        }
    }

    /*
     * Files - the files themselves, in their folders, under their own names
     */
    if (isset($rows['files'])) {
        foreach ($rows['files'] as $i => $file) {
            $source = $client_file($file['_reference']);
            if (!$source) {
                $missing[] = 'File "' . $file['name'] . '" is on record but not on the server';
                continue;
            }
            $folder = 'files' . ($file['folder'] !== '' ? '/' . implode('/', array_map('clientExportSafeName', explode('/', $file['folder']))) : '');
            $path = clientExportUniquePath($folder, clientExportSafeName($file['name']), $used);
            if ($add_file($path, $source)) {
                $rows['files'][$i]['path'] = $path;
            }
        }
    }

    /*
     * Tickets - a page per conversation, its attachments beside it
     */
    $ticket_pages = [];
    if (isset($rows['tickets'])) {
        global $mysqli;
        $names = clientExportNames($client_id);
        $attachments = [];
        $sql = mysqli_query($mysqli, "SELECT ticket_attachments.* FROM ticket_attachments JOIN tickets ON ticket_id = ticket_attachment_ticket_id
            WHERE ticket_client_id = $client_id ORDER BY ticket_attachment_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $attachments[intval($row['ticket_attachment_ticket_id'])][] = $row;
        }
        foreach ($rows['tickets'] as $i => $ticket) {
            $path = clientExportUniquePath('tickets', clientExportSafeName($ticket['number'] . ' - ' . $ticket['subject']) . '.html', $used);
            $files_html = '';
            foreach ($attachments[$ticket['id']] ?? [] as $attachment) {
                $dir = realpath(__DIR__ . '/../uploads/tickets/' . $ticket['id']);
                $source = $dir ? realpath($dir . '/' . basename((string) $attachment['ticket_attachment_reference_name'])) : false;
                if (!$source || strpos($source, $dir . DIRECTORY_SEPARATOR) !== 0) {
                    $missing[] = 'Attachment "' . $attachment['ticket_attachment_name'] . '" on ' . $ticket['number'] . ' is on record but not on the server';
                    continue;
                }
                $target = clientExportUniquePath('tickets/attachments/' . clientExportSafeName($ticket['number']), clientExportSafeName($attachment['ticket_attachment_name']), $used);
                if ($add_file($target, $source)) {
                    $files_html .= '<li><a href="../' . escapeHtml($target) . '">' . escapeHtml($attachment['ticket_attachment_name']) . '</a></li>';
                }
            }
            $badges = '<span class="badge blue">' . escapeHtml($ticket['status']) . '</span><span class="badge">' . escapeHtml($ticket['priority']) . '</span>'
                . ($ticket['category'] !== '' ? '<span class="badge">' . escapeHtml($ticket['category']) . '</span>' : '');
            $facts = array_filter(['Contact' => $ticket['contact'], 'Assigned to' => $ticket['assigned_to'], 'Asset' => $ticket['asset'], 'Location' => $ticket['location'],
                'Project' => $ticket['project'], 'Vendor' => trim($ticket['vendor'] . ($ticket['vendor_ticket'] !== '' ? ' (case ' . $ticket['vendor_ticket'] . ')' : '')),
                'Opened' => clientExportShowDate($ticket['created']), 'Resolved' => clientExportShowDate($ticket['resolved']), 'Closed' => clientExportShowDate($ticket['closed']),
                'Time worked' => $ticket['time_worked'], 'Rating' => $ticket['feedback'], 'Invoice' => $ticket['_invoice']]);
            $facts_html = '<table>';
            foreach ($facts as $label => $value) {
                $facts_html .= '<tr><th style="width:160px">' . escapeHtml($label) . '</th><td>' . escapeHtml($value) . '</td></tr>';
            }
            $facts_html .= '</table>';
            // One ticket's conversation at a time - years of email would not fit in memory at once
            $thread = '';
            $details = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_details FROM tickets WHERE ticket_id = " . intval($ticket['id']) . " AND ticket_client_id = $client_id"))[0] ?? '');
            $sql = mysqli_query($mysqli, "SELECT ticket_reply, ticket_reply_type, ticket_reply_by, ticket_reply_created_at, ticket_reply_time_worked FROM ticket_replies
                WHERE ticket_reply_ticket_id = " . intval($ticket['id']) . " AND ticket_reply_archived_at IS NULL" . (empty($options['internal']) ? " AND ticket_reply_type != 'Internal'" : '') . "
                ORDER BY ticket_reply_created_at, ticket_reply_id");
            while ($reply = mysqli_fetch_assoc($sql)) {
                $type = $reply['ticket_reply_type'];
                $by = $type === 'Client' ? ($names['contact'][intval($reply['ticket_reply_by'])] ?? '') : ($names['user'][intval($reply['ticket_reply_by'])] ?? '');
                $worked = clientExportHours(clientExportSeconds($reply['ticket_reply_time_worked']));
                $class = $type === 'Client' ? 'client' : ($type === 'Internal' ? 'internal' : '');
                $label = $type === 'Internal' ? 'Internal note' : ($type === 'Client' ? 'Client' : 'Reply');
                $thread .= '<div class="reply ' . $class . '"><div class="small muted"><strong>' . escapeHtml($by ?: 'Unknown') . '</strong> · ' . escapeHtml($label)
                    . ' · ' . escapeHtml(date('M j, Y g:i A', strtotime($reply['ticket_reply_created_at']))) . ($worked !== '' ? ' · ' . escapeHtml($worked) . ' worked' : '')
                    . '</div><div class="content">' . documentHtmlPurify($reply['ticket_reply']) . '</div></div>';
            }
            $add_string($path, clientExportHtmlPage($ticket['number'] . ' - ' . $ticket['subject'],
                '<div class="card"><div class="muted small">' . escapeHtml($ticket['number']) . ($ticket['archived'] !== '' ? ' · Archived' : '') . '</div>'
                . '<h1>' . escapeHtml($ticket['subject']) . '</h1><div>' . $badges . '</div></div>'
                . '<div class="card">' . $facts_html . '</div>'
                . '<div class="card"><h3 style="margin-top:0">Details</h3><div class="content">' . documentHtmlPurify($details) . '</div></div>'
                . ($thread !== '' ? '<div class="card"><h3 style="margin-top:0">Conversation</h3>' . $thread . '</div>' : '')
                . ($files_html !== '' ? '<div class="card"><h3 style="margin-top:0">Attachments</h3><ul>' . $files_html . '</ul></div>' : ''), '../'));
            $rows['tickets'][$i]['path'] = $path;
            $ticket_pages[] = ['number' => $ticket['number'], 'subject' => $ticket['subject'], 'status' => $ticket['status'], 'created' => $ticket['created'], 'path' => $path];
        }
    }

    /*
     * Photos of contacts, assets, locations and racks
     */
    $photos = 0;
    foreach (['contacts' => ['contacts', '_photo'], 'locations' => ['locations', '_photo'], 'assets' => ['assets', '_photo'], 'racks' => ['racks', '_photo'], 'racks_rear' => ['racks', '_photo_rear']] as $kind => [$dataset, $column]) {
        foreach ($rows[$dataset] ?? [] as $record) {
            $source = $client_file($record[$column] ?? '');
            if ($source) {
                $folder = 'photos/' . ($kind === 'racks_rear' ? 'racks' : $kind);
                $name = clientExportSafeName($record['name'] . ($kind === 'racks_rear' ? ' (rear)' : '')) . '.' . strtolower(pathinfo($source, PATHINFO_EXTENSION));
                $photos += $add_file(clientExportUniquePath($folder, $name, $used), $source) ? 1 : 0;
            }
        }
    }

    /*
     * The data - CSV for spreadsheets, JSON for moving to another system
     */
    $manifest_sets = [];
    foreach (clientExportDatasetsFor($options) as $key => $dataset) {
        $set = $rows[$key] ?? [];
        $add_string("data/csv/$key.csv", clientExportCsv($key, $set));
        $add_string("data/json/$key.json", json_encode(clientExportJsonRows($key, $set), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        $manifest_sets[$key] = ['label' => $dataset['label'], 'rows' => count($set), 'csv' => "data/csv/$key.csv", 'json' => "data/json/$key.json", 'columns' => $dataset['columns']];
    }

    /*
     * The documentation PDF, locked the same way when it holds passwords
     */
    $pdf_name = '';
    if ($options['with_pdf']) {
        $pdf_sections = array_values(array_intersect($options['sections'], array_keys(array_filter(clientExportSections(), function ($s) { return $s['pdf']; }))));
        try {
            $pdf_name = clientExportFileName($client['name'], 'IT Documentation', 'pdf');
            $add_string($pdf_name, clientExportPdf($client_id, ['kind' => 'pdf', 'sections' => $pdf_sections, 'archived' => false] + $options));
        } catch (Exception $exception) {
            $pdf_name = '';
            $missing[] = 'The documentation PDF could not be made: ' . $exception->getMessage();
        }
    }

    /*
     * The README - what is here, and links to all of it
     */
    $sections = clientExportSections();
    $readme = '<div class="card"><div class="muted small">Client data export</div><h1>' . escapeHtml($client['name']) . '</h1>'
        . '<div class="muted">Exported ' . escapeHtml(date('F j, Y g:i A')) . ' by ' . escapeHtml(stripslashes((string) $session_name)) . ' from ITFlow ' . escapeHtml(APP_VERSION) . '</div></div>';
    if ($options['secrets']) {
        $readme .= '<div class="notice"><strong>This export includes passwords and 2FA secrets.</strong> Keep it encrypted, share the password separately, and delete copies once they are no longer needed.</div>';
    }
    $readme .= '<div class="card"><h2 style="margin-top:0">What is in this export</h2><ul>'
        . ($pdf_name !== '' ? '<li><a href="' . escapeHtml(rawurlencode($pdf_name)) . '">' . escapeHtml($pdf_name) . '</a> - the IT documentation, ready to print or share</li>' : '')
        . '<li><strong>data/csv</strong> - every record as a spreadsheet (opens in Excel, Numbers or LibreOffice)</li>'
        . '<li><strong>data/json</strong> - the same records for importing into another system, described in data/export.json</li>'
        . ($document_pages ? '<li><strong>documents</strong> - ' . count($document_pages) . ' documents as web pages, in their folders</li>' : '')
        . (isset($rows['files']) ? '<li><strong>files</strong> - ' . count(array_filter(array_column($rows['files'], 'path'))) . ' files, in their folders</li>' : '')
        . ($ticket_pages ? '<li><strong>tickets</strong> - ' . count($ticket_pages) . ' ticket conversations with their attachments</li>' : '')
        . ($photos ? '<li><strong>photos</strong> - ' . $photos . ' photos of contacts, assets, locations and racks</li>' : '')
        . '</ul><p class="small muted">Included: client details, ' . escapeHtml(implode(', ', array_map(function ($key) use ($sections) { return lcfirst($sections[$key]['label']); }, $options['sections'])))
        . '. ' . ($options['archived'] ? 'Archived records are included and dated in their Archived column.' : 'Archived records are left out.')
        . ($options['secrets'] ? '' : (in_array('credentials', $options['sections'], true) ? ' Passwords and 2FA secrets are not included.' : '')) . '</p></div>';

    $readme .= '<div class="card"><h2 style="margin-top:0">Records</h2><table><tr><th>Table</th><th class="num">Rows</th><th>Download</th></tr>';
    foreach ($manifest_sets as $set) {
        $readme .= '<tr><td>' . escapeHtml($set['label']) . '</td><td class="num">' . $set['rows'] . '</td><td><a href="' . $set['csv'] . '">CSV</a> · <a href="' . $set['json'] . '">JSON</a></td></tr>';
    }
    $readme .= '</table></div>';

    if ($document_pages) {
        $readme .= '<div class="card"><h2 style="margin-top:0">Documents</h2><ul class="plain">';
        foreach ($document_pages as $page) {
            $readme .= '<li><a href="' . escapeHtml(implode('/', array_map('rawurlencode', explode('/', $page['path'])))) . '">' . escapeHtml($page['name']) . '</a>'
                . ($page['folder'] !== '' ? ' <span class="muted small">' . escapeHtml($page['folder']) . '</span>' : '')
                . ($page['archived'] !== '' ? ' <span class="badge">Archived</span>' : '') . '</li>';
        }
        $readme .= '</ul></div>';
    }
    if (!empty($rows['files'])) {
        $readme .= '<div class="card"><h2 style="margin-top:0">Files</h2><table><tr><th>File</th><th>Folder</th><th class="num">Size</th></tr>';
        foreach ($rows['files'] as $file) {
            $readme .= '<tr><td>' . ($file['path'] !== '' ? '<a href="' . escapeHtml(implode('/', array_map('rawurlencode', explode('/', $file['path'])))) . '">' . escapeHtml($file['name']) . '</a>'
                : escapeHtml($file['name']) . ' <span class="badge red">Missing</span>') . '</td><td>' . escapeHtml($file['folder']) . '</td><td class="num">' . escapeHtml(formatBytes($file['size'], 1)) . '</td></tr>';
        }
        $readme .= '</table></div>';
    }
    if ($ticket_pages) {
        $readme .= '<div class="card"><h2 style="margin-top:0">Tickets</h2><table><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Opened</th></tr>';
        foreach ($ticket_pages as $page) {
            $readme .= '<tr><td><a href="' . escapeHtml(implode('/', array_map('rawurlencode', explode('/', $page['path'])))) . '">' . escapeHtml($page['number']) . '</a></td><td>'
                . escapeHtml($page['subject']) . '</td><td>' . escapeHtml($page['status']) . '</td><td>' . escapeHtml(clientExportShowDate($page['created'])) . '</td></tr>';
        }
        $readme .= '</table></div>';
    }
    if ($missing) {
        $readme .= '<div class="card"><h2 style="margin-top:0">Not included</h2><ul>';
        foreach ($missing as $line) {
            $readme .= '<li>' . escapeHtml($line) . '</li>';
        }
        $readme .= '</ul></div>';
    }
    $add_string('README.html', clientExportHtmlPage($client['name'] . ' - Client data export', $readme, null));

    $add_string('data/export.json', json_encode([
        'exported_at' => date('c'),
        'exported_by' => stripslashes((string) $session_name),
        'itflow_version' => APP_VERSION,
        'client' => clientExportJsonRows('client', [$client])[0],
        'sections' => array_merge(['client'], $options['sections']),
        'includes_archived' => $options['archived'],
        'includes_secrets' => $options['secrets'],
        'includes_internal_notes' => $options['internal'],
        'datasets' => $manifest_sets,
        'not_included' => $missing,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

    $zip_open = false;
    if ($failed) {
        $zip->close();
        @unlink($zip_path);
        $error = "This server's zip library cannot encrypt archives (libzip 1.2 or newer is needed), so the export was not made. Export it without passwords instead.";
        return false;
    }
    if (!$zip->close()) {
        @unlink($zip_path);
        $error = 'The export could not be finished - the server may be out of disk space.';
        return false;
    }
    return $zip_path;
}

/*
 * ---------- the export window and the download ----------
 */

/** How many records each section holds now (archived left out) - shown beside its box. */
function clientExportCounts($client_id) {
    global $mysqli;
    $c = intval($client_id);
    $queries = [
        'contacts' => "SELECT COUNT(*) FROM contacts WHERE contact_client_id = $c AND contact_archived_at IS NULL",
        'locations' => "SELECT COUNT(*) FROM locations WHERE location_client_id = $c AND location_archived_at IS NULL",
        'vendors' => "SELECT COUNT(*) FROM vendors WHERE vendor_client_id = $c AND vendor_archived_at IS NULL",
        'networks' => "SELECT COUNT(*) FROM networks WHERE network_client_id = $c AND network_archived_at IS NULL",
        'assets' => "SELECT COUNT(*) FROM assets WHERE asset_client_id = $c AND asset_archived_at IS NULL",
        'racks' => "SELECT COUNT(*) FROM racks WHERE rack_client_id = $c AND rack_archived_at IS NULL",
        'software' => "SELECT COUNT(*) FROM software WHERE software_client_id = $c AND software_archived_at IS NULL",
        'domains' => "SELECT COUNT(*) FROM domains WHERE domain_client_id = $c AND domain_archived_at IS NULL",
        'certificates' => "SELECT COUNT(*) FROM certificates WHERE certificate_client_id = $c AND certificate_archived_at IS NULL",
        'services' => "SELECT COUNT(*) FROM services WHERE service_client_id = $c AND service_archived_at IS NULL",
        'credentials' => "SELECT COUNT(*) FROM credentials WHERE credential_client_id = $c AND credential_archived_at IS NULL",
        'documents' => "SELECT COUNT(*) FROM documents WHERE document_client_id = $c AND document_archived_at IS NULL",
        'files' => "SELECT COUNT(*) FROM files WHERE file_client_id = $c AND file_archived_at IS NULL",
        'tickets' => "SELECT COUNT(*) FROM tickets WHERE ticket_client_id = $c AND ticket_archived_at IS NULL",
        'billing' => "SELECT COUNT(*) FROM invoices WHERE invoice_client_id = $c AND invoice_archived_at IS NULL",
    ];
    $counts = [];
    foreach ($queries as $section => $query) {
        $counts[$section] = intval(mysqli_fetch_row(mysqli_query($mysqli, $query))[0] ?? 0);
    }
    return $counts;
}

/**
 * The section boxes for the export window, two columns, each with how many
 * records it holds. $kind 'pdf' offers only the sections the PDF has a
 * chapter for.
 */
function clientExportSectionPicker($client_id, $kind) {
    $counts = clientExportCounts($client_id);
    $nouns = ['billing' => 'invoice', 'software' => 'license'];
    $html = '<div class="row row-cols-1 row-cols-sm-2 g-0">';
    foreach (clientExportAllowedSections() as $key => $section) {
        if ($kind === 'pdf' && !$section['pdf']) {
            continue;
        }
        $checked = $kind === 'pdf' ? ($section['pdf_default'] ?? true) : true;
        $id = 'clientExport' . ucfirst($kind) . ucfirst($key);
        $count = $counts[$key] ?? 0;
        $html .= '<div class="col"><div class="form-check py-1">'
            . '<input class="form-check-input" type="checkbox" name="sections[]" value="' . $key . '" id="' . $id . '"' . ($checked ? ' checked' : '')
            . ($key === 'credentials' ? ' data-client-export-credentials' : '') . '>'
            . '<label class="form-check-label" for="' . $id . '"><i class="fas fa-fw ' . $section['icon'] . ' text-body-secondary me-1"></i>' . escapeHtml($section['label'])
            . ' <span class="small text-body-tertiary">' . $count . '</span></label></div></div>';
    }
    return $html . '</div>';
}

/**
 * Send a finished export: the headers for a download that is not cached,
 * with the name in UTF-8 and a plain fallback, and the cookie the export
 * window watches for to know the download has started.
 */
function clientExportSendHeaders($filename, $type, $length) {
    $token = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['download_token'] ?? ''));
    if ($token !== '') {
        setcookie('itflow_download', $token, ['path' => '/', 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    }
    $fallback = preg_replace('/[^A-Za-z0-9 ._-]/', '', $filename) ?: 'export';
    header("Content-Type: $type");
    header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . $length);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Pragma: no-cache');
}

/** The sections of an export as words, for the audit log. */
function clientExportSummary($options) {
    $sections = clientExportSections();
    $labels = array_map(function ($key) use ($sections) { return lcfirst($sections[$key]['label']); }, $options['sections']);
    return 'client details' . ($labels ? ', ' . implode(', ', $labels) : '');
}
