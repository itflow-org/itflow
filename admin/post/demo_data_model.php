<?php
defined('FROM_POST_HANDLER') || defined('FROM_STARTER_CONTENT') || die("Direct file access is not allowed");

/*
 * ITFlow - Demo data library
 *
 * A fictional book of business for demos, training and screenshots - ten
 * clients with the people, kit, documentation, tickets and billing a typical
 * MSP would be carrying for them, generated across a two year history.
 *
 * Nothing written here is labelled as demo data. A demo instance should look
 * like a working system, so the rows carry ordinary names, references and tags.
 * Identification for removal is by name instead: the ten client names, the four
 * account names, the three SLA names, the two tax names and the organisation
 * vendor names below are the library, and removal matches against those.
 * Rename a demo client and it stops being removable - that is the trade for
 * data that reads as real.
 *
 * Named _model so admin/post.php does not glob it in on every admin request.
 */

require_once __DIR__ . '/starter_content_model.php';

// ------------------------------
// demoDataAccounts
// The bank accounts an MSP runs. Matched by name - an install that already has
// an account called Operating Checking keeps the one it has.
// ------------------------------
function demoDataAccounts() {
    return [
        ['Operating Checking', 'Day to day business banking - client payments in, bills out', 18500.00],
        ['Business Savings', 'Tax and reserve holding', 42000.00],
        ['Merchant Settlement', 'Card and online payments waiting to be paid out', 0.00],
    ];
}

// ------------------------------
// demoCashAccountId
// Setup already creates an account called Cash on every install, so small cash
// spending posts there rather than inventing a second petty cash account. It is
// never created and never removed by this library.
// ------------------------------
function demoCashAccountId($mysqli) {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT account_id FROM accounts WHERE account_name = 'Cash' AND account_archived_at IS NULL ORDER BY account_id ASC LIMIT 1"));
    return intval($row['account_id'] ?? 0);
}

// ------------------------------
// demoDataOrgVendors
// The MSP's own suppliers - vendor_client_id 0, so they sit under the
// organisation rather than under a client. Every organisation level expense and
// both halves of every transfer carry one of these, which is also how removal
// finds the company side of the books.
// name, description, phone, domain, sla, code
// ------------------------------
function demoDataOrgVendors() {
    return [
        ['Ridgeline Technology Distribution', 'Hardware distribution - workstations, servers and networking', '888-555-0110', 'ridgelinedist.example', 'Next business day shipping', 'RTD-4471'],
        ['Netgate', 'pfSense Plus appliances and support subscriptions', '512-555-0182', 'netgate.example', 'Business hours support', 'NG-90233'],
        ['Ubiquiti Store', 'UniFi networking, switching and access points', '855-555-0164', 'uistore.example', 'Advance replacement', 'UI-71622'],
        ['Synology Direct', 'NAS hardware and Active Backup for Business licensing', '425-555-0139', 'synologydirect.example', 'Next business day replacement', 'SYN-2280'],
        ['Proxmox Server Solutions', 'Proxmox VE and Proxmox Backup Server subscriptions', '203-555-0101', 'proxmoxsubs.example', 'Enterprise repository access', 'PVE-5512'],
        ['Nextcloud Hosting Partner', 'Managed Nextcloud instances resold to clients', '203-555-0177', 'ncpartner.example', '4 hour response', 'NC-3390'],
        ['Cascade Cloud Licensing', 'Microsoft and Google licensing through the partner programme', '877-555-0155', 'cascadelicensing.example', 'Partner support portal', 'CCL-6640'],
        ['Three Rivers Colocation', 'Rack space, power and transit for the management stack', '412-555-0128', 'threeriverscolo.example', '24/7 hands and eyes', 'TRC-1180'],
        ['Steelworks Business Bank', 'Business banking, merchant services and card settlement', '412-555-0100', 'steelworksbank.example', 'Business hours', 'SBB-0042'],
        ['Alcott and Reyes CPA', 'Bookkeeping, payroll filing and year end accounts', '412-555-0192', 'alcottreyes.example', 'Monthly close', 'ARC-2210'],
        ['Keystone Commercial Insurance', 'General liability, errors and omissions and cyber cover', '412-555-0173', 'keystonecommercial.example', 'Annual renewal', 'KCI-8890'],
        ['Monongahela Property Group', 'Office lease and building services', '412-555-0121', 'monongahelaproperty.example', 'Business hours', 'MPG-0310'],
    ];
}

// ------------------------------
// demoDataSlas
// Response and resolution targets, assigned per client per priority.
// name, description, response minutes, resolution minutes
// ------------------------------
function demoDataSlas() {
    return [
        ['Standard Support', 'Business hours cover for routine work', 240, 2880],
        ['Priority Support', 'Faster targets for anything stopping a user working', 60, 960],
        ['Critical Response', 'Site down or business stopping - immediate cover', 30, 480],
    ];
}

// ------------------------------
// demoDataTaxes
// Sales tax on goods lines. Labor is not taxed in this fictional jurisdiction,
// which is why only hardware lines carry a rate.
// ------------------------------
function demoDataTaxes() {
    return [
        ['PA Sales Tax', 6.0],
        ['Allegheny County Sales Tax', 7.0],
    ];
}

// ------------------------------
// demoCategoryId
// starterCategoryId returns 0 for a category that is not there, which is how a
// load run before the Categories pack ends up with every expense, invoice and
// ticket uncategorised. This creates what is missing instead, so the data is
// complete whatever order the packs were loaded in.
// ------------------------------
function demoCategoryId($mysqli, $name, $type) {

    static $cache = [];

    $key = $type . '|' . $name;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $id = starterCategoryId($mysqli, $name, $type);

    if (!$id) {
        $id = starterInsert($mysqli, 'categories', [
            'category_name' => $name,
            'category_type' => $type,
            'category_color' => $type === 'Income' ? '#3d9970' : '#dc3545',
            'category_created_at' => demoMonthDateTime(24, 1, 9, 0),
        ]);
    }

    $cache[$key] = $id;

    return $id;
}

// ------------------------------
// demoDataProducts
// Catalogue lines for the things this MSP actually sells, sitting alongside
// whatever the Products starter pack put in. Deliberately a mix - the open
// source stack is sold as hosting and support rather than licences, which is
// where the margin on it comes from.
// name, type, code, price, income category, description
// ------------------------------
function demoDataProducts() {
    return [
        // Hosting - the open source side of the business
        ['Nextcloud Hosting - Per User', 'service', 'HOST-NC', '6.50', 'Web and Hosting', 'Per user, per month. Managed Nextcloud instance with sync, sharing and mobile access, backed up nightly.'],
        ['Nextcloud Hosting - Instance', 'service', 'HOST-NCI', '95.00', 'Web and Hosting', 'Per instance, per month. Dedicated Nextcloud instance, updates, monitoring and support.'],
        ['Web Hosting - Standard', 'service', 'HOST-WEB', '25.00', 'Web and Hosting', 'Per site, per month. Managed hosting with TLS, nightly backup and uptime monitoring.'],
        ['Web Hosting - Business', 'service', 'HOST-WEBB', '65.00', 'Web and Hosting', 'Per site, per month. Higher resource allocation, staging site and priority restore.'],
        ['Email Hosting - Per Mailbox', 'service', 'HOST-MAIL', '4.50', 'Web and Hosting', 'Per mailbox, per month. Mail hosting with spam filtering, webmail and mobile sync.'],
        ['Mail Relay and Filtering', 'service', 'HOST-RELAY', '2.00', 'Web and Hosting', 'Per mailbox, per month. Inbound filtering and outbound relay with reputation monitoring.'],
        ['Offsite Backup Storage - Self Hosted', 'service', 'HOST-BKP', '0.09', 'Backup', 'Per GB, per month. Encrypted offsite backup storage on our own infrastructure rather than a third party cloud.'],
        ['Virtual Server Hosting', 'service', 'HOST-VPS', '55.00', 'Cloud Services', 'Per guest, per month. Proxmox hosted virtual server with backup and monitoring.'],

        // Open source support and migration work
        ['Proxmox VE Support', 'service', 'FOSS-PVE', '145.00', 'Managed Services', 'Per host, per month. Proxmox VE monitoring, updates and enterprise repository access.'],
        ['pfSense Plus Support', 'service', 'FOSS-PF', '65.00', 'Managed Services', 'Per firewall, per month. pfSense Plus subscription, firmware and rule management.'],
        ['OPNsense Support', 'service', 'FOSS-OPN', '65.00', 'Managed Services', 'Per firewall, per month. OPNsense updates, firmware and rule management.'],
        ['TrueNAS Support', 'service', 'FOSS-TN', '95.00', 'Managed Services', 'Per array, per month. TrueNAS monitoring, pool health, snapshots and replication.'],
        ['LibreOffice Migration', 'service', 'FOSS-LO', '145.00', 'Projects', 'Per hour. Template conversion, macro rework and user training when moving off a proprietary office suite.'],
        ['Linux Desktop Build', 'service', 'FOSS-DSK', '125.00', 'Projects', 'Per machine. Standard Linux desktop build, data migration and handover.'],
        ['Open Source Stack Review', 'service', 'FOSS-REV', '850.00', 'Consulting', 'Fixed fee. Review of a self hosted stack with a written report and a costed roadmap.'],

        // Corporate licensing and hardware, resold
        ['Google Workspace Business Standard', 'service', 'LIC-GWBS', '14.00', 'Managed Services', 'Per user, per month. Licence resold and managed.'],
        ['Synology Active Backup Licence', 'product', 'LIC-SYNAB', '0.00', 'Hardware Sales', 'Included with Synology hardware. Listed so it appears on the quote.'],
        ['Netgate 6100 MAX Firewall', 'product', 'HW-NG6100', '1245.00', 'Hardware Sales', 'pfSense Plus appliance with three years of support included.'],
        ['Ubiquiti U6-Pro Access Point', 'product', 'HW-U6PRO', '245.00', 'Hardware Sales', 'Wi-Fi 6 ceiling mounted access point, PoE powered.'],
        ['Ubiquiti USW-Pro-24-PoE Switch', 'product', 'HW-USW24', '785.00', 'Hardware Sales', '24 port managed PoE switch with SFP+ uplinks.'],
        ['Synology RS1221+ with Drives', 'product', 'HW-RS1221', '2850.00', 'Hardware Sales', 'Rack NAS populated with NAS rated drives, running Active Backup for Business.'],
        ['Rack Mount UPS 1500VA', 'product', 'HW-UPS15', '685.00', 'Hardware Sales', 'Rack mount UPS with network management card and graceful shutdown.'],
    ];
}

// ------------------------------
// demoEnsureProducts
// Matched on the product code, which is what makes a catalogue line ours - a
// name on its own could easily be something the install already sells.
// ------------------------------
function demoEnsureProducts($mysqli, $currency) {

    $products = [];

    foreach (demoDataProducts() as $product) {
        $code = escapeSql($product[2]);
        $name = escapeSql($product[0]);
        $description = escapeSql($product[5]);
        // Code, name and description all have to match. The Products starter
        // pack ships its own catalogue and an install may well sell the same
        // thing under the same code, so nothing weaker is safe at removal time.
        // Edit the description and we leave our line behind rather than risk
        // taking theirs - the harmless direction to fail in.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT product_id FROM products WHERE product_code = '$code' AND product_name = '$name' AND product_description = '$description' LIMIT 1"));
        if (!empty($row['product_id'])) {
            $products[$product[0]] = intval($row['product_id']);
            continue;
        }
        $products[$product[0]] = starterInsert($mysqli, 'products', [
            'product_name' => $product[0],
            'product_type' => $product[1],
            'product_code' => $product[2],
            'product_price' => $product[3],
            'product_currency_code' => $currency,
            'product_description' => $product[5],
            'product_category_id' => demoCategoryId($mysqli, $product[4], 'Income'),
            'product_tax_id' => 0,
        ]);
    }

    return $products;
}

// ------------------------------
// demoDataCalendars
// An MSP does not run everything off one calendar. name, colour.
// ------------------------------
function demoDataCalendars() {
    return [
        ['Onsite Visits', 'blue'],
        ['Maintenance Windows', 'orange'],
        ['Internal', 'green'],
    ];
}

function demoEnsureCalendars($mysqli) {

    $calendars = [];

    foreach (demoDataCalendars() as $calendar) {
        $name = escapeSql($calendar[0]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT calendar_id FROM calendars WHERE calendar_name = '$name' AND calendar_color = '{$calendar[1]}' LIMIT 1"));
        if (!empty($row['calendar_id'])) {
            $calendars[$calendar[0]] = intval($row['calendar_id']);
            continue;
        }
        $calendars[$calendar[0]] = starterInsert($mysqli, 'calendars', [
            'calendar_name' => $calendar[0],
            'calendar_color' => $calendar[1],
            'calendar_created_at' => demoMonthDateTime(24, 1, 9, 0),
        ]);
    }

    return $calendars;
}

// ------------------------------
// demoDataClientNames / demoDataClients
// ------------------------------
function demoDataClientNames() {
    $names = [];
    foreach (demoDataSpecs() as $spec) {
        $names[] = $spec['name'];
    }
    return $names;
}

function demoDataClients($mysqli) {
    $clients = [];
    $names = [];
    foreach (demoDataClientNames() as $name) {
        $names[] = "'" . escapeSql($name) . "'";
    }
    $names = implode(',', $names);

    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_name IN ($names) ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])] = $row['client_name'];
    }

    return $clients;
}

// ------------------------------
// demoDataOrgVendorIds
// ------------------------------
// Name alone is not enough - a real MSP may well have its own vendor called
// Netgate or Ubiquiti Store. Matching the account code as well means removal
// only ever touches the supplier records this library created.
function demoDataOrgVendorIds($mysqli) {
    $ids = [];
    foreach (demoDataOrgVendors() as $vendor) {
        $name = escapeSql($vendor[0]);
        $code = escapeSql($vendor[5]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_name = '$name' AND vendor_code = '$code' AND vendor_client_id = 0 LIMIT 1"));
        if (!empty($row['vendor_id'])) {
            $ids[$vendor[0]] = intval($row['vendor_id']);
        }
    }
    return $ids;
}

// ------------------------------
// demoDataStatus
// ------------------------------
function demoDataStatus($mysqli) {
    $loaded = demoDataClients($mysqli);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(client_id) AS total FROM clients WHERE client_archived_at IS NULL"));
    $all_clients = intval($row['total'] ?? 0);

    return [
        'total' => count(demoDataSpecs()),
        'loaded' => $loaded,
        'other_clients' => max(0, $all_clients - count($loaded)),
    ];
}

// ------------------------------
// Date helpers - everything is generated relative to today
// ------------------------------
function demoDateTime($days_ago, $hour = 9, $minute = 0) {
    return date('Y-m-d H:i:s', mktime($hour, $minute, 0, date('n'), date('j') - $days_ago, date('Y')));
}

function demoDate($days_ago) {
    return date('Y-m-d', mktime(12, 0, 0, date('n'), date('j') - $days_ago, date('Y')));
}

function demoMonthDateTime($months_ago, $day, $hour = 9, $minute = 0) {
    return date('Y-m-d H:i:s', mktime($hour, $minute, 0, date('n') - $months_ago, $day, date('Y')));
}

function demoMonthDate($months_ago, $day) {
    return date('Y-m-d', mktime(12, 0, 0, date('n') - $months_ago, $day, date('Y')));
}

// Month anchored rows can land past today depending on what day of the month
// the load runs - payroll dated the 28th when it is the 24th. Money that has
// not happened yet has no business in the ledger.
function demoIsFuture($date) {
    return strtotime($date) > time();
}

// How much history a client gets - their whole life with us, capped at the two
// year window. Newer clients ramp up rather than appearing fully formed.
function demoClientMonths($profile) {
    return max(3, min(24, (int)floor($profile['age_days'] / 30)));
}

// ------------------------------
// demoNextNumber
// The same atomic increment the real handlers use, so seeded rows do not leave
// ticket/invoice/quote numbering sitting on a number that is already taken.
// ------------------------------
function demoNextNumber($mysqli, $setting) {
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            $setting = LAST_INSERT_ID($setting),
            $setting = $setting + 1
        WHERE company_id = 1
    ");
    return intval(mysqli_insert_id($mysqli));
}

// ------------------------------
// demoTicketStatusId
// Built-in statuses are IDs 1-5 on an install seeded by setup, but look them up
// by name anyway - an install that renamed them still works.
// ------------------------------
function demoTicketStatusId($mysqli, $name, $fallback) {
    $name = escapeSql($name);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_name = '$name' LIMIT 1"));
    return intval($row['ticket_status_id'] ?? 0) ?: $fallback;
}

function demoFirstId($mysqli, $table, $id_column, $archived_column = '') {
    $where = $archived_column ? "WHERE $archived_column IS NULL" : '';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT $id_column FROM $table $where ORDER BY $id_column ASC LIMIT 1"));
    return intval($row[$id_column] ?? 0);
}

// Deterministic stand-in for a serial number - same input, same serial, so a
// reload after a removal does not produce a whole new set of numbers
function demoSerial($seed, $length = 10) {
    return strtoupper(substr(md5('itflow-demo-' . $seed), 0, $length));
}

function demoLicenseKey($seed) {
    return implode('-', str_split(demoSerial($seed, 20), 5));
}

function demoContactEmail($name, $domain) {
    $parts = explode(' ', mb_strtolower($name));
    $first = $parts[0] ?? 'user';
    $last = end($parts);
    return mb_substr($first, 0, 1) . preg_replace('/[^a-z]/', '', $last) . '@' . $domain;
}

// 555-01xx is the reserved fictional range - nothing here can dial a real desk
function demoMobileNumber($area_code, $index, $offset) {
    return $area_code . '-555-' . str_pad((string)(100 + ($index * 9) + $offset), 4, '0', STR_PAD_LEFT);
}

// 02: prefix is locally administered, so no seeded MAC collides with a real OUI
function demoMacAddress($seed) {
    return strtoupper('02:' . implode(':', str_split(substr(md5('itflow-demo-mac-' . $seed), 0, 10), 2)));
}

// ------------------------------
// demoAttachTags
// Tags are matched to the library by name - anything not present is skipped
// rather than created, so the Tags starter pack stays the single source.
// NOTE asset_tags does not follow the convention the other four join tables
// use, hence the explicit tag column rather than a hard coded tag_id.
// ------------------------------
function demoAttachTags($mysqli, $join_table, $join_column, $tag_column, $row_id, $tag_type, $tag_names) {
    foreach ($tag_names as $tag_name) {
        $tag_name = escapeSql($tag_name);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_name = '$tag_name' AND tag_type = $tag_type LIMIT 1"));
        $tag_id = intval($row['tag_id'] ?? 0);
        if ($tag_id) {
            mysqli_query($mysqli, "INSERT INTO $join_table SET $join_column = $row_id, $tag_column = $tag_id");
        }
    }
}

// ------------------------------
// demoLink
// The relationship joins are all two integer columns. Linking assets to the
// credentials that open them, services to the kit they run on, contacts to the
// laptop they were issued - that is what makes the documentation worth opening.
// ------------------------------
function demoLink($mysqli, $table, $left_column, $left_id, $right_column, $right_id) {
    $left_id = intval($left_id);
    $right_id = intval($right_id);
    if (!$left_id || !$right_id) {
        return;
    }
    mysqli_query($mysqli, "INSERT IGNORE INTO $table SET $left_column = $left_id, $right_column = $right_id");
}

// ------------------------------
// demoEnsureAccounts / demoEnsureOrgVendors / demoEnsureSlas / demoEnsureTaxes
// Company level records, matched by name so a second run reuses them.
// ------------------------------
function demoEnsureAccounts($mysqli, $currency) {

    $accounts = [];

    foreach (demoDataAccounts() as $account) {

        $name = escapeSql($account[0]);
        $description = escapeSql($account[1]);

        // Name and description together are what make an account ours. An
        // install with its own Operating Checking keeps it untouched and gets a
        // separate one here, so the company ledger is always built against
        // accounts this library owns rather than being skipped.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT account_id FROM accounts WHERE account_name = '$name' AND account_description = '$description' LIMIT 1"));
        if (!empty($row['account_id'])) {
            $accounts[$account[0]] = intval($row['account_id']);
            continue;
        }

        $accounts[$account[0]] = starterInsert($mysqli, 'accounts', [
            'account_name' => $account[0],
            'account_description' => $account[1],
            'opening_balance' => number_format($account[2], 2, '.', ''),
            'account_currency_code' => $currency,
            'account_created_at' => demoMonthDateTime(24, 1, 9, 0),
        ]);
    }

    return $accounts;
}

function demoEnsureOrgVendors($mysqli) {

    $vendors = [];

    foreach (demoDataOrgVendors() as $vendor) {
        $name = escapeSql($vendor[0]);
        $code = escapeSql($vendor[5]);
        // Only reuse a supplier that is ours - same name and same account code.
        // An install with its own Netgate record keeps it untouched and gets a
        // separate one here, so removal can never take their purchase history.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_name = '$name' AND vendor_code = '$code' AND vendor_client_id = 0 LIMIT 1"));
        if (!empty($row['vendor_id'])) {
            $vendors[$vendor[0]] = intval($row['vendor_id']);
            continue;
        }
        $vendors[$vendor[0]] = starterInsert($mysqli, 'vendors', [
            'vendor_name' => $vendor[0],
            'vendor_description' => $vendor[1],
            'vendor_contact_name' => 'Account Manager',
            'vendor_phone_country_code' => '1',
            'vendor_phone' => $vendor[2],
            'vendor_extension' => (string)(200 + (crc32($vendor[0]) % 90)),
            'vendor_email' => 'accounts@' . $vendor[3],
            'vendor_website' => 'https://www.' . $vendor[3],
            'vendor_hours' => 'Mon-Fri 8:00 - 18:00',
            'vendor_sla' => $vendor[4],
            'vendor_code' => $vendor[5],
            'vendor_account_number' => $vendor[5],
            'vendor_notes' => 'Organisation supplier. Purchase orders are raised against this account and reconciled at month end.',
            'vendor_client_id' => 0,
            'vendor_created_at' => demoMonthDateTime(24, 2, 9, 0),
        ]);
    }

    return $vendors;
}

function demoEnsureSlas($mysqli) {

    $slas = [];

    foreach (demoDataSlas() as $sla) {
        $name = escapeSql($sla[0]);
        // Name alone is not enough - an install may well have its own policy
        // called Standard Support with different targets. Matching the targets
        // as well means removal never takes somebody else's policy.
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_name = '$name' AND sla_response_minutes = {$sla[2]} AND sla_resolution_minutes = {$sla[3]} LIMIT 1"));
        if (!empty($row['sla_id'])) {
            $slas[$sla[0]] = ['id' => intval($row['sla_id']), 'response' => $sla[2], 'resolution' => $sla[3]];
            continue;
        }
        $sla_id = starterInsert($mysqli, 'slas', [
            'sla_name' => $sla[0],
            'sla_description' => $sla[1],
            'sla_response_minutes' => $sla[2],
            'sla_resolution_minutes' => $sla[3],
            'sla_created_at' => demoMonthDateTime(24, 3, 9, 0),
        ]);
        $slas[$sla[0]] = ['id' => $sla_id, 'response' => $sla[2], 'resolution' => $sla[3]];
    }

    return $slas;
}

function demoEnsureTaxes($mysqli) {

    $taxes = [];

    foreach (demoDataTaxes() as $tax) {
        $name = escapeSql($tax[0]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tax_id, tax_percent FROM taxes WHERE tax_name = '$name' AND tax_percent = {$tax[1]} LIMIT 1"));
        if (!empty($row['tax_id'])) {
            $taxes[$tax[0]] = ['id' => intval($row['tax_id']), 'percent' => floatval($row['tax_percent'])];
            continue;
        }
        $tax_id = starterInsert($mysqli, 'taxes', [
            'tax_name' => $tax[0],
            'tax_percent' => $tax[1],
            'tax_created_at' => demoMonthDateTime(24, 3, 9, 0),
        ]);
        $taxes[$tax[0]] = ['id' => $tax_id, 'percent' => $tax[1]];
    }

    return $taxes;
}

// ------------------------------
// demoDataLoad
// Builds the whole book of business. Clients already present by name are
// skipped, so a second run adds nothing and a run after a partial removal only
// fills the gaps.
// ------------------------------
function demoDataLoad($mysqli) {
    global $session_user_id, $session_company_currency;

    $currency = $session_company_currency ?? '';

    $counts = [
        'clients' => 0,
        'contacts' => 0,
        'assets' => 0,
        'documentation' => 0,
        'tickets' => 0,
        'projects' => 0,
        'billing' => 0,
        'company' => 0,
        'skipped_credentials' => 0,
    ];

    $existing = starterExistingNames($mysqli, 'clients', 'client_name');

    $context = [
        'user_id' => intval($session_user_id ?? 0),
        'currency' => $currency,
        'accounts' => demoEnsureAccounts($mysqli, $currency),
        'cash_account_id' => demoCashAccountId($mysqli),
        'org_vendors' => demoEnsureOrgVendors($mysqli),
        'products' => demoEnsureProducts($mysqli, $currency),
        'calendars' => demoEnsureCalendars($mysqli),
        'slas' => demoEnsureSlas($mysqli),
        'taxes' => demoEnsureTaxes($mysqli),
        'calendar_id' => demoFirstId($mysqli, 'calendars', 'calendar_id'),
        'status_new' => demoTicketStatusId($mysqli, 'New', 1),
        'status_open' => demoTicketStatusId($mysqli, 'Open', 2),
        'status_hold' => demoTicketStatusId($mysqli, 'On Hold', 3),
        'status_resolved' => demoTicketStatusId($mysqli, 'Resolved', 4),
        'status_closed' => demoTicketStatusId($mysqli, 'Closed', 5),
        'vault_open' => encryptCredentialEntry('itflow-demo-probe') !== false,
    ];

    // One transaction for the whole load - several thousand rows go in a great
    // deal faster, and a failure part way through does not leave half a demo
    mysqli_begin_transaction($mysqli);

    foreach (demoDataProfiles() as $index => $profile) {

        if (isset($existing[mb_strtolower($profile['name'])])) {
            continue;
        }

        demoBuildClient($mysqli, $profile, $index, $context, $counts);
        $counts['clients']++;
    }

    // The MSP's own operating costs and transfers. Only generated when the
    // accounts were ours to create - an install that already had an account
    // called Operating Checking has a real ledger, and we stay out of it.
    // The company side always gets built. It posts only to accounts and
    // suppliers this library owns, so there is nothing to protect an existing
    // install from - and both builders are guarded, so a run that only fills in
    // missing clients does not double anything up.
    demoBuildInternalDiary($mysqli, $context, $counts);
    demoBuildCompanyFinancials($mysqli, $context, $counts);

    if (!$context['vault_open'] && $counts['clients']) {
        $counts['skipped_credentials'] = 1;
    }

    mysqli_commit($mysqli);

    return $counts;
}

// ------------------------------
// demoDataRemove
// Purges every client carrying the demo tag and everything hanging off it.
// This deliberately goes further than the client delete handler, which leaves
// tags, notes, tasks, ticket history, projects and expenses behind.
// ------------------------------
function demoDataRemove($mysqli) {

    $clients = demoDataClients($mysqli);
    $org_vendors = demoDataOrgVendorIds($mysqli);

    if (!$clients && !$org_vendors) {
        return 0;
    }

    foreach (array_keys($clients) as $client_id) {

        $client_id = intval($client_id);

        // Tickets and everything under them
        $ticket_ids = [];
        $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $ticket_ids[] = intval($row['ticket_id']);
        }
        if ($ticket_ids) {
            $ticket_ids = implode(',', $ticket_ids);
            mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_history WHERE ticket_history_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM tasks WHERE task_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM sla_history WHERE sla_history_ticket_id IN ($ticket_ids)");
        }
        mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT recurring_ticket_id FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $recurring_ticket_id = intval($row['recurring_ticket_id']);
            mysqli_query($mysqli, "DELETE FROM recurring_ticket_tasks WHERE recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id");
            mysqli_query($mysqli, "DELETE FROM recurring_ticket_assets WHERE recurring_ticket_id = $recurring_ticket_id");
        }
        mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id");

        // Billing
        $sql = mysqli_query($mysqli, "SELECT invoice_id FROM invoices WHERE invoice_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $invoice_id = intval($row['invoice_id']);
            mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
        }
        mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT quote_id FROM quotes WHERE quote_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $quote_id = intval($row['quote_id']);
            mysqli_query($mysqli, "DELETE FROM quote_items WHERE item_quote_id = $quote_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_quote_id = $quote_id");
        }
        mysqli_query($mysqli, "DELETE FROM quotes WHERE quote_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $recurring_invoice_id = intval($row['recurring_invoice_id']);
            mysqli_query($mysqli, "DELETE FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_recurring_invoice_id = $recurring_invoice_id");
        }
        mysqli_query($mysqli, "DELETE FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");

        mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM trips WHERE trip_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM credits WHERE credit_client_id = $client_id");

        // Documentation and the join tables the client delete handler misses
        $sql = mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $asset_id = intval($row['asset_id']);
            mysqli_query($mysqli, "DELETE FROM asset_tags WHERE asset_tag_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_notes WHERE asset_note_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_history WHERE asset_history_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_credentials WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_documents WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_files WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM contact_assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM service_assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM software_assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_interfaces WHERE interface_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM rack_units WHERE unit_asset_id = $asset_id");
        }
        mysqli_query($mysqli, "DELETE FROM assets WHERE asset_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT rack_id FROM racks WHERE rack_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $rack_id = intval($row['rack_id']);
            mysqli_query($mysqli, "DELETE FROM rack_units WHERE unit_rack_id = $rack_id");
        }
        mysqli_query($mysqli, "DELETE FROM racks WHERE rack_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $contact_id = intval($row['contact_id']);
            mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_notes WHERE contact_note_contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_credentials WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_documents WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_files WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM service_contacts WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM software_contacts WHERE contact_id = $contact_id");
        }
        mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT credential_id FROM credentials WHERE credential_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $credential_id = intval($row['credential_id']);
            mysqli_query($mysqli, "DELETE FROM credential_tags WHERE credential_id = $credential_id");
            mysqli_query($mysqli, "DELETE FROM service_credentials WHERE credential_id = $credential_id");
            mysqli_query($mysqli, "DELETE FROM software_credentials WHERE credential_id = $credential_id");
            mysqli_query($mysqli, "DELETE FROM vendor_credentials WHERE credential_id = $credential_id");
        }
        mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $location_id = intval($row['location_id']);
            mysqli_query($mysqli, "DELETE FROM location_tags WHERE location_id = $location_id");
        }
        mysqli_query($mysqli, "DELETE FROM locations WHERE location_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT service_id FROM services WHERE service_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $service_id = intval($row['service_id']);
            mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = $service_id");
            mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = $service_id");
            mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = $service_id");
            mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = $service_id");
        }
        mysqli_query($mysqli, "DELETE FROM services WHERE service_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT software_id FROM software WHERE software_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $software_id = intval($row['software_id']);
            mysqli_query($mysqli, "DELETE FROM software_keys WHERE software_key_software_id = $software_id");
            mysqli_query($mysqli, "DELETE FROM software_documents WHERE software_id = $software_id");
            mysqli_query($mysqli, "DELETE FROM software_files WHERE software_id = $software_id");
        }
        mysqli_query($mysqli, "DELETE FROM software WHERE software_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT document_id FROM documents WHERE document_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $document_id = intval($row['document_id']);
            mysqli_query($mysqli, "DELETE FROM document_files WHERE document_id = $document_id");
            mysqli_query($mysqli, "DELETE FROM document_versions WHERE document_version_document_id = $document_id");
        }
        mysqli_query($mysqli, "DELETE FROM documents WHERE document_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $vendor_id = intval($row['vendor_id']);
            mysqli_query($mysqli, "DELETE FROM vendor_credentials WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli, "DELETE FROM vendor_documents WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli, "DELETE FROM vendor_files WHERE vendor_id = $vendor_id");
        }
        mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_client_id = $client_id");

        mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM domains WHERE domain_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM networks WHERE network_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM projects WHERE project_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM contracts WHERE contract_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM client_notes WHERE client_note_client_id = $client_id");
        $sql = mysqli_query($mysqli, "SELECT event_id FROM calendar_events WHERE event_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $event_id = intval($row['event_id']);
            mysqli_query($mysqli, "DELETE FROM calendar_event_attendees WHERE attendee_event_id = $event_id");
        }
        mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM logs WHERE log_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM notifications WHERE notification_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM clients WHERE client_id = $client_id");

        // Never leave the company's own-client designation pointing at a client that is gone
        mysqli_query($mysqli, "UPDATE settings SET config_internal_client_id = 0 WHERE config_internal_client_id = $client_id");

    }

    // The organisation side. Everything on the company books carries one of the
    // library's own suppliers, including the bank on both halves of a transfer.
    if ($org_vendors) {

        $vendor_ids = implode(',', array_map('intval', $org_vendors));

        mysqli_query($mysqli, "DELETE FROM product_stock WHERE stock_expense_id IN (SELECT expense_id FROM expenses WHERE expense_client_id = 0 AND expense_vendor_id IN ($vendor_ids))");
        mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_client_id = 0 AND expense_vendor_id IN ($vendor_ids)");
        mysqli_query($mysqli, "DELETE FROM recurring_expenses WHERE recurring_expense_client_id = 0 AND recurring_expense_vendor_id IN ($vendor_ids)");

        foreach ($org_vendors as $vendor_id) {
            $vendor_id = intval($vendor_id);
            mysqli_query($mysqli, "DELETE FROM vendor_credentials WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli, "DELETE FROM vendor_documents WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli, "DELETE FROM vendor_files WHERE vendor_id = $vendor_id");
            $in_use = 0;
            foreach (['expenses' => 'expense_vendor_id', 'assets' => 'asset_vendor_id', 'software' => 'software_vendor_id', 'domains' => 'domain_registrar'] as $table => $column) {
                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM $table WHERE $column = $vendor_id"));
                $in_use = $in_use + intval($row['total'] ?? 0);
            }
            if (!$in_use) {
                mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_id = $vendor_id");
            }
        }

    }

    // Transfers carry no vendor and no category, the same as the ones the
    // transfer handler writes, so they are found through the accounts instead.
    // Both halves have to sit on accounts this library created - a transfer
    // touching an account that was already here is somebody else's.
    $demo_account_ids = [];
    foreach (demoDataAccounts() as $account) {
        $account_name = escapeSql($account[0]);
        $account_description = escapeSql($account[1]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT account_id FROM accounts WHERE account_name = '$account_name' AND account_description = '$account_description' LIMIT 1"));
        if (!empty($row['account_id'])) {
            $demo_account_ids[] = intval($row['account_id']);
        }
    }

    if ($demo_account_ids) {

        // The cash float top ups land on the install's own Cash account, so it
        // counts as one end of a transfer we own without ever being removable
        $sweep_ids = $demo_account_ids;
        $cash_account_id = demoCashAccountId($mysqli);
        if ($cash_account_id) {
            $sweep_ids[] = $cash_account_id;
        }
        $sweep_ids = implode(',', $sweep_ids);
        $owned_ids = implode(',', $demo_account_ids);

        $sql = mysqli_query($mysqli, "SELECT transfer_id, transfer_expense_id, transfer_revenue_id FROM transfers
            LEFT JOIN expenses ON expense_id = transfer_expense_id
            LEFT JOIN revenues ON revenue_id = transfer_revenue_id
            WHERE expense_account_id IN ($sweep_ids) AND revenue_account_id IN ($sweep_ids)
            AND (expense_account_id IN ($owned_ids) OR revenue_account_id IN ($owned_ids))");
        while ($row = mysqli_fetch_assoc($sql)) {
            $transfer_id = intval($row['transfer_id']);
            $expense_id = intval($row['transfer_expense_id']);
            $revenue_id = intval($row['transfer_revenue_id']);
            mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = $expense_id");
            mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_id = $revenue_id");
            mysqli_query($mysqli, "DELETE FROM transfers WHERE transfer_id = $transfer_id");
        }

    }

    // The transfer categories are ours - the Categories pack has no equivalent.
    // Anything else demoCategoryId had to create was a category this install was
    // missing and genuinely needs, so those stay.
    foreach (['Expense', 'Income'] as $category_type) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_name = 'Account Transfer' AND category_type = '$category_type' LIMIT 1"));
        $category_id = intval($row['category_id'] ?? 0);
        if (!$category_id) {
            continue;
        }
        $in_use = 0;
        foreach (['expenses' => 'expense_category_id', 'revenues' => 'revenue_category_id', 'invoices' => 'invoice_category_id', 'quotes' => 'quote_category_id', 'recurring_invoices' => 'recurring_invoice_category_id', 'recurring_expenses' => 'recurring_expense_category_id', 'products' => 'product_category_id', 'budget' => 'budget_category_id'] as $table => $column) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM $table WHERE $column = $category_id"));
            $in_use = $in_use + intval($row['total'] ?? 0);
        }
        if (!$in_use) {
            mysqli_query($mysqli, "DELETE FROM categories WHERE category_id = $category_id");
        }
    }

    // Closure days and budget lines, matched on exactly what we wrote
    foreach ([date('Y') - 1, date('Y'), date('Y') + 1] as $year) {
        foreach (demoDataHolidays($year) as $holiday) {
            $holiday_date = escapeSql($holiday[0]);
            $holiday_name = escapeSql($holiday[1]);
            mysqli_query($mysqli, "DELETE FROM business_holidays WHERE holiday_date = '$holiday_date' AND holiday_name = '$holiday_name'");
        }
    }

    foreach ([date('Y') - 1, date('Y')] as $year) {
        foreach (demoDataBudgets() as $budget) {
            $category_id = demoCategoryId($mysqli, $budget[0], 'Expense');
            if (!$category_id) {
                continue;
            }
            mysqli_query($mysqli, "DELETE FROM budget WHERE budget_category_id = $category_id AND budget_year = $year AND budget_description = 'Planned monthly spend'");
        }
    }

    // Catalogue lines and calendars, matched the same conservative way - the
    // product code has to be ours, and a calendar only goes when it is empty
    foreach (demoDataProducts() as $product) {
        $code = escapeSql($product[2]);
        $name = escapeSql($product[0]);
        $description = escapeSql($product[5]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT product_id FROM products WHERE product_code = '$code' AND product_name = '$name' AND product_description = '$description' LIMIT 1"));
        $product_id = intval($row['product_id'] ?? 0);
        if (!$product_id) {
            continue;
        }
        $in_use = 0;
        foreach (['invoice_items', 'quote_items', 'recurring_invoice_items'] as $table) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM $table WHERE item_product_id = $product_id"));
            $in_use = $in_use + intval($row['total'] ?? 0);
        }
        if (!$in_use) {
            mysqli_query($mysqli, "DELETE FROM product_stock WHERE stock_product_id = $product_id");
            mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");
        }
    }

    foreach (demoDataCalendars() as $calendar) {
        $calendar_name = escapeSql($calendar[0]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT calendar_id FROM calendars WHERE calendar_name = '$calendar_name' AND calendar_color = '{$calendar[1]}' LIMIT 1"));
        $calendar_id = intval($row['calendar_id'] ?? 0);
        if (!$calendar_id) {
            continue;
        }
        // Our own internal fixtures carry no client, so they go with the calendar
        $sql = mysqli_query($mysqli, "SELECT event_id FROM calendar_events WHERE event_calendar_id = $calendar_id AND event_client_id = 0");
        while ($event = mysqli_fetch_assoc($sql)) {
            $event_id = intval($event['event_id']);
            mysqli_query($mysqli, "DELETE FROM calendar_event_attendees WHERE attendee_event_id = $event_id");
            mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_id = $event_id");
        }
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM calendar_events WHERE event_calendar_id = $calendar_id"));
        if (!intval($row['total'] ?? 0)) {
            mysqli_query($mysqli, "DELETE FROM calendars WHERE calendar_id = $calendar_id");
        }
    }

    // Our own unbilled running around, matched on the exact purposes we wrote.
    // Anything else with no client against it belongs to whoever logged it.
    foreach (demoDataErrands() as $errand) {
        $purpose = escapeSql($errand[0]);
        $destination = escapeSql($errand[1]);
        mysqli_query($mysqli, "DELETE FROM trips WHERE trip_client_id = 0 AND trip_source = 'Office' AND trip_purpose = '$purpose' AND trip_destination = '$destination'");
    }

    // Accounts, SLAs and tax rates are shared company config, so each one only
    // goes if nothing at all is left pointing at it
    // Reuse matches on name so a second run does not create duplicates, but
    // removal also requires the description to be ours - an account that was
    // already here keeps its history even when it shares a name
    foreach (demoDataAccounts() as $account) {
        $account_name = escapeSql($account[0]);
        $account_description = escapeSql($account[1]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT account_id FROM accounts WHERE account_name = '$account_name' AND account_description = '$account_description' LIMIT 1"));
        $account_id = intval($row['account_id'] ?? 0);
        if (!$account_id) {
            continue;
        }
        $in_use = 0;
        foreach (['payments' => 'payment_account_id', 'expenses' => 'expense_account_id', 'revenues' => 'revenue_account_id', 'recurring_expenses' => 'recurring_expense_account_id'] as $table => $column) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM $table WHERE $column = $account_id"));
            $in_use = $in_use + intval($row['total'] ?? 0);
        }
        if (!$in_use) {
            mysqli_query($mysqli, "DELETE FROM accounts WHERE account_id = $account_id");
        }
    }

    foreach (demoDataSlas() as $sla) {
        $sla_name = escapeSql($sla[0]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_name = '$sla_name' AND sla_response_minutes = {$sla[2]} AND sla_resolution_minutes = {$sla[3]} LIMIT 1"));
        $sla_id = intval($row['sla_id'] ?? 0);
        if (!$sla_id) {
            continue;
        }
        $in_use = 0;
        foreach (['tickets' => 'ticket_sla_id', 'sla_assignments' => 'sla_assignment_sla_id'] as $table => $column) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM $table WHERE $column = $sla_id"));
            $in_use = $in_use + intval($row['total'] ?? 0);
        }
        if (!$in_use) {
            mysqli_query($mysqli, "DELETE FROM slas WHERE sla_id = $sla_id");
        }
    }

    foreach (demoDataTaxes() as $tax) {
        $tax_name = escapeSql($tax[0]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tax_id FROM taxes WHERE tax_name = '$tax_name' AND tax_percent = {$tax[1]} LIMIT 1"));
        $tax_id = intval($row['tax_id'] ?? 0);
        if (!$tax_id) {
            continue;
        }
        $in_use = 0;
        foreach (['invoice_items', 'quote_items', 'recurring_invoice_items', 'products'] as $table) {
            $column = $table === 'products' ? 'product_tax_id' : 'item_tax_id';
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM $table WHERE $column = $tax_id"));
            $in_use = $in_use + intval($row['total'] ?? 0);
        }
        if (!$in_use) {
            mysqli_query($mysqli, "DELETE FROM taxes WHERE tax_id = $tax_id");
        }
    }

    return count($clients);
}

// ------------------------------
// demoBuildClient
// One client and everything hanging off it. Order matters - locations and
// contacts first because assets point at them, then assets before services and
// tickets because both link back to the kit.
// ------------------------------
function demoBuildClient($mysqli, $profile, $index, $context, &$counts) {

    $currency = $context['currency'];
    $user_id = $context['user_id'];
    $months = demoClientMonths($profile);

    $client_id = starterInsert($mysqli, 'clients', [
        'client_name' => $profile['name'],
        'client_type' => $profile['type'],
        'client_website' => 'https://www.' . $profile['domain'],
        'client_referral' => $profile['referral'],
        'client_rate' => $profile['rate'],
        'client_currency_code' => $currency,
        'client_net_terms' => $profile['net_terms'],
        'client_abbreviation' => $profile['abbreviation'],
        'client_tax_id_number' => '82-' . demoSerial($profile['abbreviation'] . 'ein', 7),
        'client_lead' => $profile['lead'],
        'client_favorite' => $profile['favorite'],
        'client_notes' => $profile['notes'],
        // First of the month, because the first agreement invoice is dated the
        // first - a client record cannot postdate its own opening invoice
        'client_created_at' => demoMonthDateTime($months, 1, 9, 14),
        'client_accessed_at' => demoDateTime($index % 5, 11, 20),
    ]);

    demoAttachTags($mysqli, 'client_tags', 'client_id', 'tag_id', $client_id, 1, $profile['tags']);

    // Response targets, by priority
    foreach ($profile['slas'] as $priority => $sla_name) {
        if (isset($context['slas'][$sla_name])) {
            starterInsert($mysqli, 'sla_assignments', [
                'sla_assignment_client_id' => $client_id,
                'sla_assignment_priority' => $priority,
                'sla_assignment_sla_id' => $context['slas'][$sla_name]['id'],
            ]);
        }
    }

    // Locations
    $location_ids = [];
    foreach ($profile['sites'] as $site_index => $site) {
        $location_id = starterInsert($mysqli, 'locations', [
            'location_name' => $site['name'],
            'location_description' => $site['description'],
            'location_country' => 'United States',
            'location_address' => $site['address'],
            'location_city' => $site['city'],
            'location_state' => $site['state'],
            'location_zip' => $site['zip'],
            'location_phone_country_code' => '1',
            'location_phone' => $site['phone'],
            'location_phone_extension' => (string)(100 + $site_index),
            'location_hours' => $site['hours'],
            'location_notes' => $site['notes'],
            'location_primary' => $site_index === 0 ? 1 : 0,
            'location_client_id' => $client_id,
            'location_created_at' => demoMonthDateTime($months, 3, 9, 20),
            'location_accessed_at' => demoDateTime($index % 7, 10, 5),
        ]);
        $location_ids[] = $location_id;
        demoAttachTags($mysqli, 'location_tags', 'location_id', 'tag_id', $location_id, 2, $site['tags']);
    }
    $primary_location_id = $location_ids[0] ?? 0;
    $counts['documentation'] += count($location_ids);

    // Contacts
    $contact_ids = [];
    foreach ($profile['people'] as $person_index => $person) {
        $contact_id = starterInsert($mysqli, 'contacts', [
            'contact_name' => $person[0],
            'contact_title' => $person[1],
            'contact_email' => demoContactEmail($person[0], $profile['domain']),
            'contact_phone_country_code' => '1',
            'contact_phone' => $profile['sites'][0]['phone'],
            'contact_extension' => (string)(100 + ($person_index * 7)),
            'contact_mobile_country_code' => '1',
            'contact_mobile' => demoMobileNumber($profile['area_code'], $index, $person_index),
            'contact_department' => $person[2],
            'contact_pin' => str_pad((string)(((crc32($person[0]) % 9000) + 1000)), 4, '0', STR_PAD_LEFT),
            'contact_primary' => $person_index === 0 ? 1 : 0,
            'contact_important' => $person[3],
            'contact_billing' => $person[4],
            'contact_technical' => $person[5],
            'contact_notes' => $person[6],
            'contact_location_id' => $primary_location_id,
            'contact_client_id' => $client_id,
            'contact_created_at' => demoMonthDateTime($months, 3 + $person_index, 10, 5),
            'contact_accessed_at' => demoDateTime(($index + $person_index) % 9, 14, 30),
        ]);
        $contact_ids[] = $contact_id;
        demoAttachTags($mysqli, 'contact_tags', 'contact_id', 'tag_id', $contact_id, 3, $person[7]);
        $counts['contacts']++;
    }
    $primary_contact_id = $contact_ids[0] ?? 0;
    $technical_contact_id = $contact_ids[2] ?? $primary_contact_id;

    // Notes on the account and on the technical contact
    starterInsert($mysqli, 'client_notes', [
        'client_note_type' => 'General',
        'client_note' => $profile['account_note'],
        'client_note_created_by' => $user_id,
        'client_note_client_id' => $client_id,
        'client_note_created_at' => demoMonthDateTime(max(0, $months - 1), 9, 15, 40),
    ]);
    starterInsert($mysqli, 'client_notes', [
        'client_note_type' => 'General',
        'client_note' => $profile['review_note'],
        'client_note_created_by' => $user_id,
        'client_note_client_id' => $client_id,
        'client_note_created_at' => demoMonthDateTime(max(0, min(2, $months - 1)), 11, 16, 10),
    ]);
    if ($technical_contact_id) {
        starterInsert($mysqli, 'contact_notes', [
            'contact_note_type' => 'General',
            'contact_note' => 'Main day to day contact for approvals and site access. Verify identity with the support PIN before resetting anything.',
            'contact_note_created_by' => $user_id,
            'contact_note_contact_id' => $technical_contact_id,
            'contact_note_created_at' => demoMonthDateTime(max(0, $months - 2), 14, 11, 15),
        ]);
    }

    // The client's own vendors - who they buy their line of business kit from
    $vendor_ids = [];
    foreach ($profile['vendors'] as $vendor_index => $vendor) {
        $vendor_ids[] = starterInsert($mysqli, 'vendors', [
            'vendor_name' => $vendor[0],
            'vendor_description' => $vendor[1],
            'vendor_contact_name' => 'Business Support',
            'vendor_phone_country_code' => '1',
            'vendor_phone' => $vendor[2],
            'vendor_email' => 'support@' . $vendor[3],
            'vendor_website' => 'https://www.' . $vendor[3],
            'vendor_hours' => $vendor_index === 0 ? '24/7' : 'Mon-Fri 8:00 - 17:00',
            'vendor_sla' => $vendor[4],
            'vendor_code' => demoSerial($profile['abbreviation'] . $vendor[0], 6),
            'vendor_account_number' => demoSerial($profile['abbreviation'] . 'acct' . $vendor_index, 8),
            'vendor_notes' => $vendor[5],
            'vendor_client_id' => $client_id,
            'vendor_created_at' => demoMonthDateTime($months, 5, 13, 0),
        ]);
        $counts['documentation']++;
    }
    $primary_vendor_id = $vendor_ids[0] ?? 0;

    // Networks, before assets so interfaces and IP records can point at them
    $network_ids = [];
    foreach ($profile['networks'] as $network) {
        $network_ids[$network[0]] = starterInsert($mysqli, 'networks', [
            'network_name' => $network[0],
            'network_description' => $network[1],
            'network_vlan' => $network[2],
            'network' => $network[3],
            'network_subnet' => '24',
            'network_gateway' => $network[4],
            'network_primary_dns' => $network[5],
            'network_secondary_dns' => $network[6],
            'network_dhcp_range' => $network[7],
            'network_notes' => $network[8],
            'network_location_id' => $primary_location_id,
            'network_client_id' => $client_id,
            'network_created_at' => demoMonthDateTime($months, 6, 14, 30),
            'network_accessed_at' => demoDateTime($index % 11, 9, 45),
        ]);
        $counts['documentation']++;
    }
    $primary_network_id = reset($network_ids) ?: 0;

    // Assets, and the rack the infrastructure sits in
    $assets = demoBuildAssets($mysqli, $profile, $index, $client_id, $primary_location_id, $contact_ids, $primary_vendor_id, $network_ids, $months, $user_id, $counts);
    demoBuildRack($mysqli, $profile, $client_id, $primary_location_id, $assets, $months, $counts);
    demoBuildNetworkIps($mysqli, $profile, $assets, $network_ids, $counts);
    demoBuildPatchPanel($mysqli, $profile, $assets, $network_ids, $months, $counts);

    // Domain, DNS and the certificate on it
    $domain_id = starterInsert($mysqli, 'domains', [
        'domain_name' => $profile['domain'],
        'domain_description' => 'Primary domain - website and email',
        'domain_expire' => demoDate(-1 * (60 + ($index * 21))),
        'domain_ip' => '203.0.113.' . (10 + $index),
        'domain_name_servers' => 'ns1.' . $profile['dns_host'] . ', ns2.' . $profile['dns_host'],
        'domain_raw_whois' => 'Registrar: Harbour Registrar Services. Status: clientTransferProhibited. Registrant: ' . $profile['name'] . '.',
        'domain_mail_servers' => $profile['mail_servers'],
        // These three are vendor IDs, not names - who hosts the site, who runs
        // DNS, and who the mail lands with
        'domain_webhost' => intval($profile['build'] === 1 ? ($context['org_vendors']['Nextcloud Hosting Partner'] ?? 0) : $primary_vendor_id),
        'domain_dnshost' => intval($primary_vendor_id),
        'domain_mailhost' => intval($context['org_vendors']['Cascade Cloud Licensing'] ?? 0),
        'domain_txt' => 'v=spf1 include:' . $profile['dns_host'] . ' -all',
        'domain_notes' => 'Registrar login is held in the credential vault. Renewal is set to manual so it cannot lapse without a ticket.',
        'domain_registrar' => $primary_vendor_id,
        'domain_client_id' => $client_id,
        'domain_created_at' => demoMonthDateTime($months, 7, 9, 45),
        'domain_accessed_at' => demoDateTime($index % 13, 15, 5),
    ]);

    $certificate_id = starterInsert($mysqli, 'certificates', [
        'certificate_name' => 'www.' . $profile['domain'],
        'certificate_description' => $profile['cert_issuer'] === "Let's Encrypt" ? 'Public website certificate, renewed automatically by the reverse proxy' : 'Public website certificate, renewed manually at expiry',
        'certificate_domain' => 'www.' . $profile['domain'],
        'certificate_issued_by' => $profile['cert_issuer'],
        'certificate_expire' => demoDate(-1 * (20 + ($index * 9))),
        'certificate_domain_id' => $domain_id,
        'certificate_client_id' => $client_id,
        'certificate_created_at' => demoMonthDateTime($months, 7, 9, 50),
    ]);
    $counts['documentation'] += 2;

    // The renewals behind them. Both tables cascade off their parent, so the
    // client purge takes these with the domain and the certificate.
    starterInsert($mysqli, 'domain_history', [
        'domain_history_column' => 'domain_expire',
        'domain_history_old_value' => demoMonthDate(12, 14),
        'domain_history_new_value' => demoDate(-1 * (60 + ($index * 21))),
        'domain_history_domain_id' => $domain_id,
        'domain_history_modified_at' => demoMonthDateTime(12, 14, 10, 5),
    ]);
    starterInsert($mysqli, 'domain_history', [
        'domain_history_column' => 'domain_ip',
        'domain_history_old_value' => '198.51.100.' . (10 + $index),
        'domain_history_new_value' => '203.0.113.' . (10 + $index),
        'domain_history_domain_id' => $domain_id,
        'domain_history_modified_at' => demoMonthDateTime(max(1, min(9, $months)), 6, 15, 40),
    ]);
    starterInsert($mysqli, 'certificate_history', [
        'certificate_history_column' => 'certificate_expire',
        'certificate_history_old_value' => demoMonthDate(3, 20),
        'certificate_history_new_value' => demoDate(-1 * (20 + ($index * 9))),
        'certificate_history_certificate_id' => $certificate_id,
        'certificate_history_modified_at' => demoMonthDateTime(3, 20, 4, 15),
    ]);

    // Credentials - only when the vault actually opened for this session
    $credential_ids = [];
    if ($context['vault_open']) {
        foreach ($profile['credentials'] as $credential) {
            $credential_id = starterInsert($mysqli, 'credentials', [
                'credential_name' => $credential[0],
                'credential_description' => $credential[1],
                'credential_uri' => $credential[2],
                'credential_username' => encryptCredentialEntry($credential[3]),
                'credential_password' => encryptCredentialEntry(demoSerial($profile['abbreviation'] . $credential[0], 14) . '!aA1'),
                'credential_note' => $credential[4],
                'credential_favorite' => $credential[6],
                'credential_client_id' => $client_id,
                'credential_created_at' => demoMonthDateTime($months, 8, 16, 10),
                'credential_password_changed_at' => demoMonthDateTime(max(0, min(4, $months)), 8, 16, 10),
                'credential_accessed_at' => demoDateTime(($index + 2) % 15, 11, 30),
            ]);
            $credential_ids[$credential[0]] = $credential_id;
            demoAttachTags($mysqli, 'credential_tags', 'credential_id', 'tag_id', $credential_id, 4, $credential[5]);
            $counts['documentation']++;
        }
    }

    // Documentation, written at different points rather than all on day one
    $document_ids = [];
    foreach ($profile['documents'] as $document_index => $document) {
        $written_months_ago = max(0, $months - 1 - ($document_index * 5));
        $document_id = starterInsert($mysqli, 'documents', [
            'document_name' => $document[0],
            'document_description' => $document[1],
            'document_content' => $document[2],
            'document_content_raw' => strip_tags($document[2]),
            'document_client_visible' => $document[3],
            'document_favorite' => $document_index === 0 ? 1 : 0,
            'document_created_by' => $user_id,
            'document_updated_by' => $user_id,
            'document_client_id' => $client_id,
            'document_created_at' => demoMonthDateTime($written_months_ago, 12 + $document_index, 15, 20),
            'document_accessed_at' => demoDateTime(($index + $document_index) % 10, 13, 15),
        ], ['document_content', 'document_content_raw']);
        $document_ids[] = $document_id;
        $counts['documentation']++;

        // The runbook has been revised at least once since it was written
        if ($document_index === 0 && $written_months_ago > 2) {
            starterInsert($mysqli, 'document_versions', [
                'document_version_name' => $document[0],
                'document_version_description' => $document[1],
                'document_version_content' => $document[2],
                'document_version_created_by' => $user_id,
                'document_version_document_id' => $document_id,
                'document_version_created_at' => demoMonthDateTime($written_months_ago, 12, 15, 20),
            ], ['document_version_content']);
        }
    }
    $runbook_id = $document_ids[0] ?? 0;

    // Services, linked to the kit and credentials that actually run them
    foreach ($profile['services'] as $service) {

        $service_id = starterInsert($mysqli, 'services', [
            'service_name' => $service[0],
            'service_description' => $service[1],
            'service_category' => $service[2],
            'service_importance' => $service[3],
            'service_backup' => $service[4],
            'service_notes' => $service[5],
            'service_review_due' => demoDate(-1 * (90 + ($index * 5))),
            'service_client_id' => $client_id,
            'service_created_at' => demoMonthDateTime($months, 9, 10, 30),
            'service_accessed_at' => demoDateTime($index % 12, 16, 20),
        ]);
        $counts['documentation']++;

        // Link it to the assets whose type it runs on
        foreach ($assets as $asset) {
            if (in_array($asset['type'], $service[6])) {
                demoLink($mysqli, 'service_assets', 'service_id', $service_id, 'asset_id', $asset['id']);
            }
        }
        if (isset($credential_ids[$service[7]])) {
            demoLink($mysqli, 'service_credentials', 'service_id', $service_id, 'credential_id', $credential_ids[$service[7]]);
        }
        demoLink($mysqli, 'service_contacts', 'service_id', $service_id, 'contact_id', $technical_contact_id);
        demoLink($mysqli, 'service_documents', 'service_id', $service_id, 'document_id', $runbook_id);
        demoLink($mysqli, 'service_vendors', 'service_id', $service_id, 'vendor_id', $primary_vendor_id);
        if ($service[2] === 'Email' || $service[2] === 'Web') {
            demoLink($mysqli, 'service_domains', 'service_id', $service_id, 'domain_id', $domain_id);
            demoLink($mysqli, 'service_certificates', 'service_id', $service_id, 'certificate_id', $certificate_id);
        }
    }

    // Software and licensing
    foreach ($profile['software'] as $software) {

        $software_fields = [
            'software_name' => $software[0],
            'software_description' => $software[1],
            'software_version' => $software[2],
            'software_type' => $software[3],
            'software_license_type' => $software[4],
            'software_seats' => $software[5],
            'software_purchase_reference' => 'PO-' . demoSerial($profile['abbreviation'] . $software[0], 6),
            'software_purchase' => demoMonthDate(min(24, $months + 1), 15),
            'software_notes' => $software[6],
            'software_favorite' => 0,
            'software_vendor_id' => $primary_vendor_id,
            'software_client_id' => $client_id,
            'software_created_at' => demoMonthDateTime($months, 10, 11, 45),
            'software_accessed_at' => demoDateTime(($index + 4) % 14, 10, 50),
        ];

        // Open source entries have no renewal date and no key to hold
        if ($software[4] !== 'Open Source') {
            $software_fields['software_expire'] = demoDate(-1 * (65 + ($index * 11)));
        }

        $software_id = starterInsert($mysqli, 'software', $software_fields);
        $counts['documentation']++;

        // Open source software has no key to hold
        if ($software[4] !== 'Open Source') {
            starterInsert($mysqli, 'software_keys', [
                'software_key' => demoLicenseKey($profile['abbreviation'] . $software[0]),
                'software_key_software_id' => $software_id,
            ]);
        }

        foreach ($assets as $asset) {
            if (in_array($asset['type'], $software[7])) {
                demoLink($mysqli, 'software_assets', 'software_id', $software_id, 'asset_id', $asset['id']);
            }
        }
        demoLink($mysqli, 'software_contacts', 'software_id', $software_id, 'contact_id', $technical_contact_id);
        demoLink($mysqli, 'software_documents', 'software_id', $software_id, 'document_id', $runbook_id);
        if (isset($credential_ids[$software[8]])) {
            demoLink($mysqli, 'software_credentials', 'software_id', $software_id, 'credential_id', $credential_ids[$software[8]]);
        }
    }

    // Credential and document links out to the people and kit they belong to
    foreach ($credential_ids as $credential_name => $credential_id) {
        demoLink($mysqli, 'contact_credentials', 'contact_id', $technical_contact_id, 'credential_id', $credential_id);
        demoLink($mysqli, 'vendor_credentials', 'vendor_id', $primary_vendor_id, 'credential_id', $credential_id);
        foreach ($assets as $asset) {
            if (!empty($asset['credential']) && $asset['credential'] === $credential_name) {
                demoLink($mysqli, 'asset_credentials', 'credential_id', $credential_id, 'asset_id', $asset['id']);
            }
        }
    }
    foreach ($document_ids as $document_id) {
        demoLink($mysqli, 'contact_documents', 'contact_id', $technical_contact_id, 'document_id', $document_id);
        demoLink($mysqli, 'vendor_documents', 'vendor_id', $primary_vendor_id, 'document_id', $document_id);
        foreach ($assets as $asset) {
            if (in_array($asset['type'], ['Server', 'Firewall/Router'])) {
                demoLink($mysqli, 'asset_documents', 'asset_id', $asset['id'], 'document_id', $document_id);
            }
        }
    }

    // Support and billing
    $projects = demoBuildProjects($mysqli, $profile, $index, $client_id, $months, $counts);
    demoBuildTickets($mysqli, $profile, $index, $client_id, $contact_ids, $assets, $primary_location_id, $projects, $vendor_ids, $months, $context, $counts);
    demoBuildBilling($mysqli, $profile, $index, $client_id, $primary_vendor_id, $months, $context, $counts);

    // Calendar and mileage
    $onsite_calendar_id = $context['calendars']['Onsite Visits'] ?? $context['calendar_id'];
    if ($onsite_calendar_id) {

        // The visit coming up, and the one before it
        foreach ([-1 * (3 + ($index % 20)), 30 + ($index % 20)] as $visit_index => $days_ago) {

            $event_id = starterInsert($mysqli, 'calendar_events', [
                'event_title' => $profile['abbreviation'] . ' - ' . $profile['onsite_visit'],
                'event_location' => $profile['sites'][0]['address'] . ', ' . $profile['sites'][0]['city'],
                'event_description' => $visit_index === 0
                    ? 'Scheduled onsite visit. Site contact is expecting us.'
                    : 'Completed onsite visit. Notes written up against the maintenance ticket.',
                'event_start' => demoDateTime($days_ago, 10, 0),
                'event_end' => demoDateTime($days_ago, 12, 30),
                'event_all_day' => 0,
                'event_client_id' => $client_id,
                'event_location_id' => $primary_location_id,
                'event_calendar_id' => $onsite_calendar_id,
                'event_created_at' => demoDateTime($days_ago + 10, 9, 0),
            ]);

            // Whoever is meeting us on site
            if ($technical_contact_id) {
                // attendee_invitation_status is a tinyint nothing in the app
                // reads or writes yet, so it is left on its default rather than
                // guessing at a meaning for it
                starterInsert($mysqli, 'calendar_event_attendees', [
                    'attendee_name' => $profile['people'][2][0] ?? $profile['people'][0][0],
                    'attendee_email' => demoContactEmail($profile['people'][2][0] ?? $profile['people'][0][0], $profile['domain']),
                    'attendee_contact_id' => $technical_contact_id,
                    'attendee_event_id' => $event_id,
                    'attendee_created_at' => demoDateTime($days_ago + 10, 9, 0),
                ]);
            }

        }
    }

    // The quarterly review, for the clients who get one
    $internal_calendar_id = $context['calendars']['Internal'] ?? 0;
    if ($internal_calendar_id && in_array('Key Account', $profile['tags'])) {
        starterInsert($mysqli, 'calendar_events', [
            'event_title' => $profile['abbreviation'] . ' - quarterly business review',
            'event_location' => $profile['sites'][0]['city'] . ', ' . $profile['sites'][0]['state'],
            'event_description' => 'Spend to date, open projects, asset age and anything coming up in the next quarter.',
            'event_start' => demoDateTime(-1 * (14 + ($index % 30)), 14, 0),
            'event_end' => demoDateTime(-1 * (14 + ($index % 30)), 15, 30),
            'event_all_day' => 0,
            'event_client_id' => $client_id,
            'event_location_id' => $primary_location_id,
            'event_calendar_id' => $internal_calendar_id,
            'event_created_at' => demoDateTime(3, 9, 0),
        ]);
    }

    for ($trip_month = min(6, $months); $trip_month >= 0; $trip_month = $trip_month - 3) {
        if (demoIsFuture(demoMonthDate($trip_month, 17))) {
            continue;
        }
        starterInsert($mysqli, 'trips', [
            'trip_date' => demoMonthDate($trip_month, 17),
            'trip_purpose' => 'Onsite - ' . $profile['onsite_visit'],
            'trip_source' => 'Office',
            'trip_destination' => $profile['sites'][0]['city'] . ', ' . $profile['sites'][0]['state'],
            'trip_miles' => 8.5 + ($index * 2.5),
            'round_trip' => 1,
            'trip_user_id' => $user_id,
            'trip_client_id' => $client_id,
            'trip_created_at' => demoMonthDateTime($trip_month, 17, 17, 30),
        ]);
    }

}

// ------------------------------
// demoBuildAssets
// Builds the fleet from the profile's counts, named the way an MSP names kit.
// Returns a record per asset so tickets, services and software can point at the
// right ones rather than at whatever came back first.
// ------------------------------
function demoBuildAssets($mysqli, $profile, $index, $client_id, $location_id, $contact_ids, $vendor_id, $network_ids, $months, $user_id, &$counts) {

    $models = demoAssetModels();
    $prefixes = demoAssetPrefixes();
    $assets = [];
    $seed = 0;
    $host_octet = 10;

    foreach ($profile['fleet'] as $asset_type => $quantity) {

        $type_models = $models[$asset_type] ?? [['Generic', 'Standard', '', '']];
        $prefix = $prefixes[$asset_type] ?? 'AST';
        $user_facing = in_array($asset_type, ['Desktop', 'Laptop', 'Mobile Phone', 'Tablet']);

        for ($number = 1; $number <= $quantity; $number++) {

            // A fleet does not mix at random: user facing kit is drawn only
            // from the build family this client runs, rotating within it so a
            // Linux house has both Mint and Pop!_OS rather than one model.
            // Infrastructure rotates across everything, offset by client, which
            // is closer to how a real MSP's estate actually looks.
            $candidates = $type_models;
            if ($user_facing) {
                $family = $profile['build'] === 1 ? 'foss' : 'corporate';
                $family_models = array_values(array_filter($type_models, function ($entry) use ($family) {
                    return $entry[3] === $family;
                }));
                if ($family_models) {
                    $candidates = $family_models;
                }
            }
            $model = $candidates[($number - 1 + ($user_facing ? 0 : $index)) % count($candidates)];
            $seed++;
            $host_octet++;

            $contact_id = 0;
            if ($user_facing && $contact_ids) {
                $contact_id = $contact_ids[($number - 1) % count($contact_ids)];
            }

            // Kit arrives in batches, not all at once, and a client we have had
            // for years has some genuinely old machines in the corner. Spreading
            // this properly is what makes the warranty, end of life and refresh
            // reports show anything - a fleet where nothing is ever old is not a
            // fleet anyone recognises.
            $batch = (int)floor(($number - 1) / 4);
            $purchase_months = min(70, 3 + ($batch * 12) + (($seed + $index) % 7) + (int)floor($profile['age_days'] / 120));
            $status = 'Deployed';
            if ($number === $quantity && $quantity > 3) {
                $status = 'Ready to Deploy';
            }

            $infrastructure = in_array($asset_type, ['Firewall/Router', 'Switch', 'Server', 'Access Point', 'Virtual Machine']);
            $ip = $infrastructure ? substr($profile['networks'][0][3], 0, strrpos($profile['networks'][0][3], '.')) . '.' . $host_octet : '';

            $asset_id = starterInsert($mysqli, 'assets', [
                'asset_type' => $asset_type,
                'asset_name' => $profile['abbreviation'] . '-' . $prefix . '-' . str_pad((string)$number, 2, '0', STR_PAD_LEFT),
                'asset_description' => demoAssetDescription($asset_type),
                'asset_make' => $model[0],
                'asset_model' => $model[1],
                'asset_serial' => demoSerial($profile['abbreviation'] . $asset_type . $number),
                'asset_os' => $model[2],
                'asset_uri' => $ip ? demoAssetUri($asset_type, $ip) : '',
                'asset_status' => $status,
                'asset_purchase_reference' => 'PO-' . demoSerial($profile['abbreviation'] . 'po' . $seed, 6),
                'asset_purchase_date' => demoMonthDate($purchase_months, 8),
                'asset_warranty_expire' => demoMonthDate($purchase_months - 36, 8),
                'asset_install_date' => demoMonthDate($purchase_months, 15),
                'asset_physical_location' => demoAssetPhysicalLocation($asset_type),
                'asset_notes' => demoAssetNote($asset_type, $model),
                'asset_favorite' => in_array($asset_type, ['Firewall/Router', 'Server']) && $number === 1 ? 1 : 0,
                'asset_vendor_id' => $vendor_id,
                'asset_location_id' => $location_id,
                'asset_contact_id' => $contact_id,
                'asset_client_id' => $client_id,
                'asset_created_at' => demoMonthDateTime(min($months, $purchase_months), 15, 12, 0),
                'asset_accessed_at' => demoDateTime(($index + $seed) % 20, 9, 30),
            ]);

            demoAttachTags($mysqli, 'asset_tags', 'asset_tag_asset_id', 'asset_tag_tag_id', $asset_id, 5, demoAssetTags($asset_type, $model[3], $status, $purchase_months, 36 - $purchase_months, $number));

            // The person it was issued to
            if ($contact_id) {
                demoLink($mysqli, 'contact_assets', 'contact_id', $contact_id, 'asset_id', $asset_id);
            }

            // Management interface, for the kit that has one
            $interface_id = 0;
            if ($infrastructure && $ip) {
                $interface_id = starterInsert($mysqli, 'asset_interfaces', [
                    'interface_name' => $asset_type === 'Firewall/Router' ? 'LAN' : 'mgmt0',
                    'interface_description' => 'Management interface',
                    'interface_type' => 'Ethernet',
                    'interface_mac' => demoMacAddress($profile['abbreviation'] . $asset_type . $number . 'mgmt'),
                    'interface_ip' => $ip,
                    'interface_primary' => 1,
                    'interface_network_id' => $network_ids[$profile['networks'][0][0]] ?? 0,
                    'interface_asset_id' => $asset_id,
                    'interface_created_at' => demoMonthDateTime(min($months, $purchase_months), 15, 12, 5),
                ]);
            }

            // Where it came from, and anything worth knowing before touching it
            starterInsert($mysqli, 'asset_history', [
                'asset_history_status' => 'Deployed',
                'asset_history_description' => 'Asset installed and documented',
                'asset_history_asset_id' => $asset_id,
                'asset_history_created_at' => demoMonthDateTime(min($months, $purchase_months), 15, 12, 10),
            ]);
            $note = demoAssetMaintenanceNote($asset_type);
            if ($note) {
                starterInsert($mysqli, 'asset_notes', [
                    'asset_note_type' => 'General',
                    'asset_note' => $note,
                    'asset_note_created_by' => $user_id,
                    'asset_note_asset_id' => $asset_id,
                    'asset_note_created_at' => demoMonthDateTime(max(0, min($months, $purchase_months) - 1), 20, 14, 0),
                ]);
            }

            $assets[] = [
                'id' => $asset_id,
                'type' => $asset_type,
                'name' => $profile['abbreviation'] . '-' . $prefix . '-' . str_pad((string)$number, 2, '0', STR_PAD_LEFT),
                'make' => $model[0],
                'model' => $model[1],
                'os' => $model[2],
                'contact_id' => $contact_id,
                'ip' => $ip,
                'interface_id' => $interface_id,
                'install_months_ago' => $purchase_months,
                'credential' => demoAssetCredentialName($asset_type),
            ];

            $counts['assets']++;

        }
    }

    return $assets;
}

// ------------------------------
// demoBuildRack
// One rack per client with a server room, with the infrastructure racked in it.
// ------------------------------
function demoBuildRack($mysqli, $profile, $client_id, $location_id, $assets, $months, &$counts) {

    $rackable = [];
    foreach ($assets as $asset) {
        if (in_array($asset['type'], ['Firewall/Router', 'Switch', 'Server'])) {
            $rackable[] = $asset;
        }
    }
    if (count($rackable) < 2) {
        return;
    }

    $rack_id = starterInsert($mysqli, 'racks', [
        'rack_name' => $profile['abbreviation'] . '-RACK-01',
        'rack_description' => 'Main comms rack',
        'rack_model' => 'StarTech 12U Wall Mount',
        'rack_depth' => 'Full',
        'rack_type' => 'Wall Mount',
        'rack_units' => 12,
        'rack_physical_location' => 'Server room',
        'rack_notes' => 'Keyed alike with the comms cupboard. Spare key is held by the onsite point of contact.',
        'rack_location_id' => $location_id,
        'rack_client_id' => $client_id,
        'rack_created_at' => demoMonthDateTime($months, 6, 15, 0),
    ]);
    $counts['documentation']++;

    // Patch panel at the top, then the kit under it
    $unit = 12;
    starterInsert($mysqli, 'rack_units', [
        'unit_start_number' => $unit,
        'unit_end_number' => $unit,
        'unit_device' => 'Patch panel - 24 port',
        'unit_rack_id' => $rack_id,
        'unit_created_at' => demoMonthDateTime($months, 6, 15, 5),
    ]);

    foreach ($rackable as $asset) {
        $unit--;
        if ($unit < 1) {
            break;
        }
        $height = $asset['type'] === 'Server' ? 2 : 1;
        $start = max(1, $unit - ($height - 1));
        starterInsert($mysqli, 'rack_units', [
            'unit_start_number' => $start,
            'unit_end_number' => $unit,
            'unit_device' => $asset['name'] . ' - ' . $asset['make'] . ' ' . $asset['model'],
            'unit_asset_id' => $asset['id'],
            'unit_rack_id' => $rack_id,
            'unit_created_at' => demoMonthDateTime($months, 6, 15, 5),
        ]);
        $unit = $start;
    }

}

// ------------------------------
// demoBuildPatchPanel
// Ports on the main switch, and what is plugged into them. The site runbook
// tells an engineer the port map is documented, so it had better be - and a
// switch with no ports on it is the kind of half finished documentation this
// data is supposed to be showing people how to avoid.
// ------------------------------
function demoBuildPatchPanel($mysqli, $profile, $assets, $network_ids, $months, &$counts) {

    $switch = null;
    $patchable = [];
    foreach ($assets as $asset) {
        if ($asset['type'] === 'Switch' && !$switch) {
            $switch = $asset;
            continue;
        }
        // Anything with a management interface is worth recording a port for
        if (!empty($asset['interface_id'])) {
            $patchable[] = $asset;
        }
    }

    if (!$switch) {
        return;
    }

    $network_id = $network_ids[$profile['networks'][0][0]] ?? 0;
    $created_at = demoMonthDateTime(min($months, $switch['install_months_ago']), 16, 13, 0);
    $port_count = 24;
    $ports = [];

    for ($port = 1; $port <= $port_count; $port++) {
        $ports[$port] = starterInsert($mysqli, 'asset_interfaces', [
            'interface_name' => 'Gi1/0/' . $port,
            'interface_description' => $port === $port_count ? 'Uplink to the edge firewall' : 'Access port',
            'interface_type' => 'Ethernet',
            'interface_primary' => 0,
            'interface_network_id' => $network_id,
            'interface_asset_id' => $switch['id'],
            'interface_created_at' => $created_at,
        ]);
        $counts['documentation']++;
    }

    // Patch the documented kit in, leaving the middle of the panel spare the way
    // a real one is - the ports nobody has needed yet
    $port = 1;
    foreach ($patchable as $asset) {
        if ($port > 12) {
            break;
        }
        // The firewall lands on the uplink port, everything else in order
        $target_port = $asset['type'] === 'Firewall/Router' ? $port_count : $port;
        demoLink($mysqli, 'asset_interface_links', 'interface_a_id', $ports[$target_port], 'interface_b_id', $asset['interface_id']);
        if ($asset['type'] !== 'Firewall/Router') {
            $port++;
        }
    }

}

// ------------------------------
// demoBuildNetworkIps
// Static IP documentation for the kit on the office network, plus the reserved
// addresses at the top of the range an MSP would write down.
// ------------------------------
function demoBuildNetworkIps($mysqli, $profile, $assets, $network_ids, &$counts) {

    $network_name = $profile['networks'][0][0];
    $network_id = $network_ids[$network_name] ?? 0;
    if (!$network_id) {
        return;
    }

    $base = substr($profile['networks'][0][3], 0, strrpos($profile['networks'][0][3], '.'));

    starterInsert($mysqli, 'network_ips', [
        'ip_address' => $base . '.1',
        'ip_hostname' => $profile['abbreviation'] . '-FW-01',
        'ip_description' => 'Default gateway',
        'ip_network_id' => $network_id,
    ]);
    $counts['documentation']++;

    foreach ($assets as $asset) {
        if (!$asset['ip']) {
            continue;
        }
        starterInsert($mysqli, 'network_ips', [
            'ip_address' => $asset['ip'],
            'ip_hostname' => $asset['name'],
            'ip_description' => $asset['make'] . ' ' . $asset['model'],
            'ip_network_id' => $network_id,
        ]);
        $counts['documentation']++;
    }

    starterInsert($mysqli, 'network_ips', [
        'ip_address' => $base . '.240',
        'ip_hostname' => 'reserved-printers',
        'ip_description' => 'Static reservations for printers and label writers',
        'ip_network_id' => $network_id,
    ]);
    $counts['documentation']++;

}

// ------------------------------
// demoBuildProjects
// Every client starts with an onboarding project - that is how a client
// actually arrives at an MSP, and it is what the per asset onboarding tickets
// hang off. A refresh project sits behind them, and some have work running now.
// ------------------------------
function demoBuildProjects($mysqli, $profile, $index, $client_id, $months, &$counts) {

    global $config_project_prefix;

    $user_id = intval($GLOBALS['session_user_id'] ?? 0);
    $prefix = $config_project_prefix ?? '';
    $projects = ['onboarding' => 0, 'refresh' => 0, 'active' => 0];

    // Onboarding, opened the month they came on and closed the month after
    $projects['onboarding'] = starterInsert($mysqli, 'projects', [
        'project_prefix' => $prefix,
        'project_number' => demoNextNumber($mysqli, 'config_project_next_number'),
        'project_name' => 'Onboarding - ' . $profile['name'],
        'project_description' => 'Take on ' . $profile['name'] . ': document the environment, deploy monitoring and backup, capture credentials, standardise the endpoint build and hand over to the service desk.',
        'project_due' => demoMonthDate(max(0, $months - 1), 28),
        'project_manager' => $user_id,
        'project_client_id' => $client_id,
        'project_created_at' => demoMonthDateTime($months, 3, 9, 30),
        'project_completed_at' => demoMonthDateTime(max(0, $months - 1), 26, 16, 0),
    ]);
    $counts['projects']++;

    // Something delivered along the way
    if ($months >= 14) {
        $projects['refresh'] = starterInsert($mysqli, 'projects', [
            'project_prefix' => $prefix,
            'project_number' => demoNextNumber($mysqli, 'config_project_next_number'),
            'project_name' => 'Server and network refresh',
            'project_description' => 'Replaced end of life server hardware, rebuilt the switching and brought the site onto the standard build.',
            'project_due' => demoMonthDate(13, 20),
            'project_manager' => $user_id,
            'project_client_id' => $client_id,
            'project_created_at' => demoMonthDateTime(15, 4, 9, 30),
            'project_completed_at' => demoMonthDateTime(13, 18, 16, 45),
        ]);
        $counts['projects']++;
    }

    // And whatever is running now
    if (!empty($profile['project'])) {
        $projects['active'] = starterInsert($mysqli, 'projects', [
            'project_prefix' => $prefix,
            'project_number' => demoNextNumber($mysqli, 'config_project_next_number'),
            'project_name' => $profile['project'][0],
            'project_description' => $profile['project'][1],
            'project_due' => demoMonthDate(-2, 15),
            'project_manager' => $user_id,
            'project_client_id' => $client_id,
            'project_created_at' => demoMonthDateTime(1, 8, 10, 0),
        ]);
        $counts['projects']++;
    }

    return $projects;
}

// ------------------------------
// demoSeedTicket
// One place that writes a ticket and its trail, so every ticket in the demo
// gets the same history, SLA treatment and reply handling.
// ------------------------------
function demoSeedTicket($mysqli, $fields, $trail, $context, &$counts) {

    global $config_ticket_prefix;

    $user_id = $context['user_id'];
    $closed = !empty($trail['closed_at']);

    // Response targets come from the client's assignment for this priority
    $sla = $trail['sla'] ?? null;
    if ($sla) {
        $fields['ticket_sla_id'] = $sla['id'];
        $fields['ticket_response_due_at'] = date('Y-m-d H:i:s', strtotime($fields['ticket_created_at'] . ' +' . $sla['response'] . ' minutes'));
        $fields['ticket_resolution_due_at'] = date('Y-m-d H:i:s', strtotime($fields['ticket_created_at'] . ' +' . $sla['resolution'] . ' minutes'));
        if (!empty($fields['ticket_first_response_at'])) {
            $fields['ticket_response_sla_met'] = strtotime($fields['ticket_first_response_at']) <= strtotime($fields['ticket_response_due_at']) ? 1 : 0;
        }
        if (!empty($fields['ticket_resolved_at'])) {
            $fields['ticket_resolution_sla_met'] = strtotime($fields['ticket_resolved_at']) <= strtotime($fields['ticket_resolution_due_at']) ? 1 : 0;
        }
    }

    $fields['ticket_prefix'] = $config_ticket_prefix ?? '';
    $fields['ticket_number'] = demoNextNumber($mysqli, 'config_ticket_next_number');
    $fields['ticket_url_key'] = randomString(32);

    $ticket_id = starterInsert($mysqli, 'tickets', $fields, ['ticket_details']);
    $counts['tickets']++;

    if (!empty($fields['ticket_asset_id'])) {
        mysqli_query($mysqli, "INSERT INTO ticket_assets SET ticket_id = $ticket_id, asset_id = {$fields['ticket_asset_id']}");
    }

    // History
    starterInsert($mysqli, 'ticket_history', [
        'ticket_history_status' => 'New',
        'ticket_history_description' => 'Ticket created',
        'ticket_history_ticket_id' => $ticket_id,
        'ticket_history_created_at' => $fields['ticket_created_at'],
    ]);
    if (!empty($fields['ticket_first_response_at'])) {
        starterInsert($mysqli, 'ticket_history', [
            'ticket_history_status' => 'Open',
            'ticket_history_description' => 'Assigned and first response sent',
            'ticket_history_ticket_id' => $ticket_id,
            'ticket_history_created_at' => $fields['ticket_first_response_at'],
        ]);
    }
    if (!empty($fields['ticket_resolved_at'])) {
        starterInsert($mysqli, 'ticket_history', [
            'ticket_history_status' => 'Resolved',
            'ticket_history_description' => 'Ticket resolved',
            'ticket_history_ticket_id' => $ticket_id,
            'ticket_history_created_at' => $fields['ticket_resolved_at'],
        ]);
    }
    if ($closed) {
        starterInsert($mysqli, 'ticket_history', [
            'ticket_history_status' => 'Closed',
            'ticket_history_description' => 'Ticket closed',
            'ticket_history_ticket_id' => $ticket_id,
            'ticket_history_created_at' => $trail['closed_at'],
        ]);
    }

    // Replies
    foreach ($trail['replies'] as $reply) {
        $reply_fields = [
            'ticket_reply' => $reply['body'],
            'ticket_reply_type' => $reply['type'],
            'ticket_reply_by' => $reply['type'] === 'Client' ? intval($fields['ticket_contact_id'] ?? 0) : $user_id,
            'ticket_reply_ticket_id' => $ticket_id,
            'ticket_reply_created_at' => $reply['at'],
        ];
        // Only agent time is billable time - client replies never book time
        if ($reply['type'] !== 'Client' && !empty($reply['worked'])) {
            $reply_fields['ticket_reply_time_worked'] = $reply['worked'];
        }
        starterInsert($mysqli, 'ticket_replies', $reply_fields, ['ticket_reply']);
    }

    // Tasks
    foreach ($trail['tasks'] ?? [] as $task_order => $task) {
        $task_fields = [
            'task_name' => $task,
            'task_order' => $task_order + 1,
            'task_completion_estimate' => 15,
            'task_ticket_id' => $ticket_id,
            'task_created_at' => $fields['ticket_created_at'],
        ];
        // Closed tickets have everything ticked off; live ones have the first
        // step done and the rest still open, which is what a real list looks like
        if ($closed) {
            $task_fields['task_completed_at'] = $trail['closed_at'];
            $task_fields['task_completed_by'] = $user_id;
        } elseif ($task_order === 0 && !empty($fields['ticket_first_response_at'])) {
            $task_fields['task_completed_at'] = $fields['ticket_first_response_at'];
            $task_fields['task_completed_by'] = $user_id;
        }
        starterInsert($mysqli, 'tasks', $task_fields);
    }

    // The SLA clock. An open interval means the clock is running right now; a
    // closed one is a finished segment with its minutes booked against the
    // ticket. A ticket sitting in a status that pauses the clock has neither.
    if ($sla) {

        $paused = !empty($trail['sla_paused']);
        $stopped_at = $fields['ticket_resolved_at'] ?? ($trail['sla_paused_at'] ?? '');

        if ($stopped_at) {
            // Business minutes, approximated at eight hours to the day rather
            // than pulling the business hours calculation in here
            $elapsed = max(1, (int)round((strtotime($stopped_at) - strtotime($fields['ticket_created_at'])) / 60));
            $days = max(1, (int)ceil($elapsed / 1440));
            starterInsert($mysqli, 'sla_history', [
                'sla_history_started_at' => $fields['ticket_created_at'],
                'sla_history_ended_at' => $stopped_at,
                'sla_history_minutes' => min($elapsed, $days * 480),
                'sla_history_ticket_id' => $ticket_id,
            ]);
        } elseif (!$paused) {
            // Still running
            starterInsert($mysqli, 'sla_history', [
                'sla_history_started_at' => $fields['ticket_created_at'],
                'sla_history_minutes' => 0,
                'sla_history_ticket_id' => $ticket_id,
            ]);
        }

    }

    // A watcher, where somebody else wanted to be kept in the loop
    if (!empty($trail['watcher'])) {
        starterInsert($mysqli, 'ticket_watchers', [
            'watcher_email' => $trail['watcher'],
            'watcher_ticket_id' => $ticket_id,
        ]);
    }

    return $ticket_id;
}

// ------------------------------
// demoPickAsset
// Tickets have to be about the right box. A printer ticket picks a printer, a
// wireless ticket picks an access point - anything that cannot be matched gets
// no asset rather than a random one.
// ------------------------------
function demoPickAsset($assets, $types, $rotation) {
    if (!$types) {
        return null;
    }
    $matches = [];
    foreach ($assets as $asset) {
        if (in_array($asset['type'], $types)) {
            $matches[] = $asset;
        }
    }
    if (!$matches) {
        return null;
    }
    return $matches[$rotation % count($matches)];
}

// ------------------------------
// demoBuildTickets
// Onboarding first - a ticket per asset, hung off the onboarding project, which
// is how the fleet got documented in the first place. Then two years of closed
// work, then the live queue.
// ------------------------------
function demoBuildTickets($mysqli, $profile, $index, $client_id, $contact_ids, $assets, $location_id, $projects, $vendor_ids, $months, $context, &$counts) {

    $user_id = $context['user_id'];
    $pool = demoTicketPool();
    $pool_size = count($pool);
    $sources = ['Email', 'Agent', 'Portal'];
    $technical_contact_id = $contact_ids[2] ?? ($contact_ids[0] ?? 0);

    // Resolved on demand and cached - a pre-built list goes stale the moment a
    // ticket is written against a category that was not in it
    $category_ids = [];
    $category_id = function ($name) use ($mysqli, &$category_ids) {
        if (!array_key_exists($name, $category_ids)) {
            $category_ids[$name] = demoCategoryId($mysqli, $name, 'Ticket');
        }
        return (string)$category_ids[$name];
    };

    $sla_for = function ($priority) use ($profile, $context) {
        $name = $profile['slas'][$priority] ?? 'Standard Support';
        return $context['slas'][$name] ?? null;
    };

    // ---- Onboarding: the project work, then one ticket per asset ----
    $onboarding_tickets = [
        ['Onboarding - site survey and documentation', 'Network', 'Medium', '<p>Walk the site, record what is here, photograph the comms cupboard and write the first pass of the runbook.</p>', '03:30:00', ['Walk the site', 'Record the kit list', 'Draft the runbook']],
        ['Onboarding - deploy monitoring and remote access', 'Monitoring Alert', 'High', '<p>Push the monitoring and remote access agents to every machine and confirm each one is reporting in.</p>', '02:45:00', ['Push agents', 'Confirm check in', 'Set alert thresholds']],
        ['Onboarding - backup review and first restore test', 'Backup and Recovery', 'High', '<p>Review what is being protected today, correct the gaps and prove a restore before we sign the environment off.</p>', '03:00:00', ['Review current backups', 'Correct scope', 'Run a test restore']],
        ['Onboarding - capture credentials into the vault', 'Account and Access', 'High', '<p>Collect administrative credentials, store them in the vault against this client and rotate anything shared over email.</p>', '01:30:00', ['Collect credentials', 'Store in vault', 'Rotate anything shared insecurely']],
        ['Onboarding - firewall and network review', 'Firewall', 'High', '<p>Review the edge configuration, close anything exposed, document the rule set and record the VLAN layout.</p>', '02:15:00', ['Review rule set', 'Close exposed services', 'Document VLANs']],
        ['Onboarding - handover to the service desk', 'Onboarding', 'Low', '<p>Walk the service desk through the environment, confirm contacts and approvals, and put the client live.</p>', '01:00:00', ['Brief the service desk', 'Confirm approvers', 'Go live']],
    ];

    foreach ($onboarding_tickets as $slot => $onboarding) {
        $created = demoMonthDateTime($months, 4 + $slot, 9, 15);
        demoSeedTicket($mysqli, [
            'ticket_source' => 'Agent',
            'ticket_category' => $category_id($onboarding[1]),
            'ticket_subject' => $onboarding[0],
            'ticket_details' => $onboarding[3],
            'ticket_priority' => $onboarding[2],
            'ticket_status' => $context['status_closed'],
            'ticket_billable' => 0,
            'ticket_created_at' => $created,
            'ticket_first_response_at' => date('Y-m-d H:i:s', strtotime($created . ' +25 minutes')),
            'ticket_resolved_at' => date('Y-m-d H:i:s', strtotime($created . ' +2 days')),
            'ticket_closed_at' => date('Y-m-d H:i:s', strtotime($created . ' +3 days')),
            'ticket_created_by' => $user_id,
            'ticket_assigned_to' => $user_id,
            'ticket_closed_by' => $user_id,
            'ticket_contact_id' => $technical_contact_id,
            'ticket_location_id' => $location_id,
            'ticket_project_id' => $projects['onboarding'],
            'ticket_client_id' => $client_id,
            'ticket_onsite' => $slot < 2 ? 1 : 0,
        ], [
            'closed_at' => date('Y-m-d H:i:s', strtotime($created . ' +3 days')),
            'sla' => $sla_for($onboarding[2]),
            'tasks' => $onboarding[5],
            'replies' => [
                ['type' => 'Public', 'body' => '<p>Completed and documented. Anything outstanding has been raised as its own ticket.</p>', 'worked' => $onboarding[4], 'at' => date('Y-m-d H:i:s', strtotime($created . ' +2 days'))],
            ],
        ], $context, $counts);
    }

    // One per asset - existing kit is onboarded at take on, anything bought
    // later gets deployed and documented when it arrives
    foreach ($assets as $asset_index => $asset) {

        $arrived_after_onboarding = $asset['install_months_ago'] < $months;
        $ticket_month = $arrived_after_onboarding ? $asset['install_months_ago'] : $months;
        $day = 5 + (($asset_index * 3) % 20);
        $created = demoMonthDateTime($ticket_month, $day, 8 + ($asset_index % 8), 40);

        if (demoIsFuture($created)) {
            continue;
        }

        if ($arrived_after_onboarding) {
            $subject = 'Deploy and document ' . $asset['name'];
            $details = '<p>New ' . mb_strtolower($asset['type']) . ' arrived - ' . $asset['make'] . ' ' . $asset['model'] . '. Build it to the standard, record it against the client and put it into monitoring.</p>';
            $tasks = ['Build to standard', 'Add to monitoring and backup', 'Record asset details', 'Hand over to the user'];
        } else {
            $subject = 'Onboard ' . $asset['name'];
            $details = '<p>Existing ' . mb_strtolower($asset['type']) . ' found during onboarding discovery - ' . $asset['make'] . ' ' . $asset['model'] . '. Document it, bring it under management and note anything that needs replacing.</p>';
            $tasks = ['Record make, model and serial', 'Deploy management agent', 'Check patch and firmware level', 'Note anything end of life'];
        }

        demoSeedTicket($mysqli, [
            'ticket_source' => 'Agent',
            'ticket_category' => $category_id('Onboarding'),
            'ticket_subject' => $subject,
            'ticket_details' => $details,
            'ticket_priority' => 'Low',
            'ticket_status' => $context['status_closed'],
            'ticket_billable' => $arrived_after_onboarding ? 1 : 0,
            'ticket_created_at' => $created,
            'ticket_first_response_at' => date('Y-m-d H:i:s', strtotime($created . ' +40 minutes')),
            'ticket_resolved_at' => date('Y-m-d H:i:s', strtotime($created . ' +1 day')),
            'ticket_closed_at' => date('Y-m-d H:i:s', strtotime($created . ' +2 days')),
            'ticket_created_by' => $user_id,
            'ticket_assigned_to' => $user_id,
            'ticket_closed_by' => $user_id,
            'ticket_contact_id' => $asset['contact_id'] ?: $technical_contact_id,
            'ticket_location_id' => $location_id,
            'ticket_asset_id' => $asset['id'],
            'ticket_project_id' => $arrived_after_onboarding ? 0 : $projects['onboarding'],
            'ticket_client_id' => $client_id,
        ], [
            'closed_at' => date('Y-m-d H:i:s', strtotime($created . ' +2 days')),
            'sla' => $sla_for('Low'),
            'tasks' => $tasks,
            'replies' => [
                ['type' => 'Public', 'body' => '<p>' . $asset['name'] . ' is documented, under management and reporting in. Serial and warranty are recorded against the asset.</p>', 'worked' => $arrived_after_onboarding ? '00:45:00' : '00:25:00', 'at' => date('Y-m-d H:i:s', strtotime($created . ' +1 day'))],
            ],
        ], $context, $counts);

    }

    // ---- Two years of closed work ----
    $per_month = max(2, (int)floor($profile['seats'] / 10));
    $sequence = 0;

    for ($month = $months - 1; $month >= 1; $month--) {
        for ($n = 0; $n < $per_month; $n++) {

            $sequence++;
            $ticket = $pool[(($index * 5) + $sequence) % $pool_size];
            $asset = demoPickAsset($assets, $ticket['asset_types'], $sequence);
            $day = 2 + ((($index + $month + $n) * 7) % 25);
            $hour = 8 + (($sequence + $n) % 9);
            $created = demoMonthDateTime($month, $day, $hour, 20);

            // The person who reported it is whoever uses that machine
            $contact_id = $asset && $asset['contact_id'] ? $asset['contact_id'] : ($contact_ids ? $contact_ids[$sequence % count($contact_ids)] : 0);

            // Most work is inside target, but not all of it
            $slow = $sequence % 11 === 0;
            $response_minutes = $slow ? 600 : 35 + ($sequence % 90);

            $fields = [
                'ticket_source' => $sources[$sequence % 3],
                'ticket_category' => $category_id($ticket['category']),
                'ticket_subject' => $ticket['subject'],
                'ticket_details' => $ticket['details'],
                'ticket_priority' => $ticket['priority'],
                'ticket_status' => $context['status_closed'],
                'ticket_billable' => $ticket['billable'],
                'ticket_created_at' => $created,
                'ticket_first_response_at' => date('Y-m-d H:i:s', strtotime($created . " +$response_minutes minutes")),
                'ticket_resolved_at' => demoMonthDateTime($month, $day + 1, 14, 30),
                'ticket_closed_at' => demoMonthDateTime($month, $day + 2, 9, 15),
                'ticket_created_by' => $user_id,
                'ticket_assigned_to' => $user_id,
                'ticket_closed_by' => $user_id,
                'ticket_contact_id' => $contact_id,
                'ticket_location_id' => $location_id,
                'ticket_asset_id' => $asset ? $asset['id'] : 0,
                'ticket_client_id' => $client_id,
                'ticket_onsite' => !empty($ticket['onsite']) ? 1 : 0,
            ];

            if (!empty($ticket['vendor']) && $vendor_ids) {
                $fields['ticket_vendor_id'] = $vendor_ids[0];
                $fields['ticket_vendor_ticket_number'] = 'V' . demoSerial($profile['abbreviation'] . 'vt' . $sequence, 7);
            }

            $replies = [];
            foreach ($ticket['replies'] as $reply_index => $reply) {
                $replies[] = [
                    'type' => $reply[0],
                    'body' => $reply[1],
                    'worked' => $reply[2],
                    'at' => demoMonthDateTime($month, $day + ($reply_index > 0 ? 1 : 0), 10 + $reply_index, 15),
                ];
            }

            demoSeedTicket($mysqli, $fields, [
                'closed_at' => demoMonthDateTime($month, $day + 2, 9, 15),
                'sla' => $sla_for($ticket['priority']),
                'replies' => $replies,
            ], $context, $counts);

        }
    }

    // ---- The live queue ----
    $stages = [
        ['status' => $context['status_closed'], 'created' => 74, 'response' => 74, 'resolved' => 71, 'closed' => 68],
        ['status' => $context['status_closed'], 'created' => 41, 'response' => 41, 'resolved' => 39, 'closed' => 36],
        ['status' => $context['status_resolved'], 'created' => 17, 'response' => 17, 'resolved' => 3, 'closed' => 0],
        ['status' => $context['status_open'], 'created' => 6, 'response' => 6, 'resolved' => 0, 'closed' => 0],
        ['status' => $index % 2 === 0 ? $context['status_hold'] : $context['status_open'], 'created' => 2, 'response' => 2, 'resolved' => 0, 'closed' => 0],
        ['status' => $context['status_new'], 'created' => 0, 'response' => 0, 'resolved' => 0, 'closed' => 0],
    ];

    foreach ($stages as $slot => $stage) {

        $ticket = $pool[(($index * 6) + $slot) % $pool_size];
        $asset = demoPickAsset($assets, $ticket['asset_types'], $index + $slot);
        $contact_id = $asset && $asset['contact_id'] ? $asset['contact_id'] : ($contact_ids ? $contact_ids[$slot % count($contact_ids)] : 0);
        $created = demoDateTime($stage['created'], 8 + $slot, 25);

        // The newest ticket is deliberately left unassigned - a real queue has one
        $assigned_to = $stage['status'] === $context['status_new'] ? 0 : $user_id;

        $fields = [
            'ticket_source' => $sources[$slot % 3],
            'ticket_category' => $category_id($ticket['category']),
            'ticket_subject' => $ticket['subject'],
            'ticket_details' => $ticket['details'],
            'ticket_priority' => $ticket['priority'],
            'ticket_status' => $stage['status'],
            'ticket_billable' => $ticket['billable'],
            'ticket_created_at' => $created,
            'ticket_created_by' => $user_id,
            'ticket_assigned_to' => $assigned_to,
            'ticket_contact_id' => $contact_id,
            'ticket_location_id' => $location_id,
            'ticket_asset_id' => $asset ? $asset['id'] : 0,
            'ticket_client_id' => $client_id,
            'ticket_onsite' => !empty($ticket['onsite']) ? 1 : 0,
        ];

        // Hang the live work off the running project where there is one
        if ($projects['active'] && in_array($slot, [3, 4])) {
            $fields['ticket_project_id'] = $projects['active'];
        }
        if (!empty($ticket['vendor']) && $vendor_ids) {
            $fields['ticket_vendor_id'] = $vendor_ids[0];
            $fields['ticket_vendor_ticket_number'] = 'V' . demoSerial($profile['abbreviation'] . 'vlive' . $slot, 7);
        }
        if ($stage['status'] !== $context['status_new']) {
            $fields['ticket_first_response_at'] = demoDateTime($stage['response'], 9 + $slot, 40);
        }
        if ($stage['resolved']) {
            $fields['ticket_resolved_at'] = demoDateTime($stage['resolved'], 15, 10);
        }
        if ($stage['closed']) {
            $fields['ticket_closed_at'] = demoDateTime($stage['closed'], 15, 30);
            $fields['ticket_closed_by'] = $user_id;
            $fields['ticket_feedback'] = $slot === 0 ? 'Great' : 'Ok';
        }
        if ($stage['status'] === $context['status_open'] && !empty($ticket['onsite'])) {
            $fields['ticket_schedule'] = demoDateTime(-2, 10, 0);
        }

        $replies = [];
        $reply_day = $stage['created'];
        foreach ($ticket['replies'] as $reply_index => $reply) {
            $reply_day = max($stage['resolved'], $reply_day - 1);
            $replies[] = [
                'type' => $reply[0],
                'body' => $reply[1],
                'worked' => $reply[2],
                'at' => demoDateTime($reply_day, 10 + $reply_index, 15),
            ];
        }

        $on_hold = $stage['status'] === $context['status_hold'];

        demoSeedTicket($mysqli, $fields, [
            'closed_at' => $stage['closed'] ? demoDateTime($stage['closed'], 15, 30) : '',
            'sla' => $sla_for($ticket['priority']),
            'sla_paused' => $on_hold,
            'sla_paused_at' => $on_hold ? demoDateTime(max(0, $stage['created'] - 1), 11, 30) : '',
            'replies' => $replies,
            'tasks' => !$stage['closed'] ? ($ticket['tasks'] ?? []) : [],
            'watcher' => $slot === 3 && $contact_ids ? demoContactEmail($profile['people'][0][0], $profile['domain']) : '',
        ], $context, $counts);

    }

    // A standing recurring ticket - the monthly maintenance visit
    $recurring_ticket_id = starterInsert($mysqli, 'recurring_tickets', [
        'recurring_ticket_category' => $category_id('Maintenance'),
        'recurring_ticket_subject' => $profile['recurring_ticket'],
        'recurring_ticket_details' => '<p>Scheduled maintenance visit. Work the task list, log time against this ticket, and note anything needing quoting.</p>',
        'recurring_ticket_priority' => 'Low',
        'recurring_ticket_frequency' => 'Monthly',
        'recurring_ticket_billable' => 0,
        'recurring_ticket_start_date' => demoMonthDate($months, 1),
        'recurring_ticket_next_run' => demoDate(-1 * (5 + ($index % 20))),
        'recurring_ticket_created_by' => $user_id,
        'recurring_ticket_assigned_to' => $user_id,
        'recurring_ticket_client_id' => $client_id,
        'recurring_ticket_contact_id' => $contact_ids[0] ?? 0,
        'recurring_ticket_created_at' => demoMonthDateTime($months, 1, 9, 0),
    ], ['recurring_ticket_details']);

    foreach (['Check backup job results and last test restore', 'Review patch and firmware compliance', 'Check disk, memory and UPS health', 'Walk the site for anything obviously broken', 'Review the asset list for anything past warranty'] as $task_order => $task) {
        starterInsert($mysqli, 'recurring_ticket_tasks', [
            'recurring_ticket_task_name' => $task,
            'recurring_ticket_task_order' => $task_order + 1,
            'recurring_ticket_task_completion_estimate' => 15,
            'recurring_ticket_task_recurring_ticket_id' => $recurring_ticket_id,
        ]);
    }

    // The maintenance visit covers the infrastructure
    foreach ($assets as $asset) {
        if (in_array($asset['type'], ['Server', 'Firewall/Router'])) {
            demoLink($mysqli, 'recurring_ticket_assets', 'recurring_ticket_id', $recurring_ticket_id, 'asset_id', $asset['id']);
        }
    }
    $counts['tickets']++;

}

// ------------------------------
// demoInvoiceItem
// invoice_items, quote_items and recurring_invoice_items are the same shape.
// Goods carry sales tax, labor does not, which is the whole reason the tax
// column is here rather than every line being zero.
// ------------------------------
function demoInvoiceItem($mysqli, $table, $parent_column, $parent_id, $line, $item_order, $created_at, $tax = null, $product_ids = []) {

    $subtotal = round($line[1] * $line[2], 2);
    $taxable = !empty($line[4]);
    $tax_amount = $taxable && $tax ? round($subtotal * ($tax['percent'] / 100), 2) : 0;

    $fields = [
        'item_name' => $line[0],
        'item_description' => $line[3],
        'item_quantity' => $line[1],
        'item_price' => number_format($line[2], 2, '.', ''),
        'item_subtotal' => number_format($subtotal, 2, '.', ''),
        'item_tax' => number_format($tax_amount, 2, '.', ''),
        'item_total' => number_format($subtotal + $tax_amount, 2, '.', ''),
        'item_order' => $item_order + 1,
        'item_tax_id' => $taxable && $tax ? $tax['id'] : 0,
        $parent_column => $parent_id,
        'item_created_at' => $created_at,
    ];

    // Point the line at the catalogue product where the starter pack has one
    if (isset($product_ids[mb_strtolower($line[0])])) {
        $fields['item_product_id'] = $product_ids[mb_strtolower($line[0])];
    }

    starterInsert($mysqli, $table, $fields);

    return $subtotal + $tax_amount;
}

// ------------------------------
// demoDocumentTotal
// ------------------------------
function demoDocumentTotal($lines, $tax) {
    $total = 0;
    foreach ($lines as $line) {
        $subtotal = round($line[1] * $line[2], 2);
        $total = $total + $subtotal;
        if (!empty($line[4]) && $tax) {
            $total = $total + round($subtotal * ($tax['percent'] / 100), 2);
        }
    }
    return round($total, 2);
}

// ------------------------------
// demoPayment
// ------------------------------
function demoPayment($mysqli, $invoice_id, $amount, $currency, $method, $account_id, $month, $profile, $index, $invoice_date = '') {
    if (!$account_id) {
        return;
    }

    $day = 8 + (($index + $month) % 14);
    $date = demoMonthDate($month, $day);

    // Nobody pays an invoice before it is raised - work billed mid month gets
    // settled on terms from the invoice date, not from the start of the month
    if ($invoice_date && strtotime($date) < strtotime($invoice_date)) {
        $date = date('Y-m-d', strtotime($invoice_date . ' +' . (4 + ($index % 10)) . ' days'));
    }

    if (demoIsFuture($date)) {
        return;
    }

    starterInsert($mysqli, 'payments', [
        'payment_date' => $date,
        'payment_amount' => number_format($amount, 2, '.', ''),
        'payment_currency_code' => $currency,
        'payment_method' => $method,
        'payment_reference' => strtoupper(substr($method, 0, 3)) . '-' . demoSerial($profile['abbreviation'] . 'pay' . $invoice_id, 8),
        'payment_account_id' => $account_id,
        'payment_invoice_id' => $invoice_id,
        'payment_created_at' => $date . ' 14:00:00',
    ]);
}

// ------------------------------
// demoBuildBilling
// Managed clients run on an agreement with a monthly invoice behind it. Break
// fix clients have no agreement at all - they get billed for the hours and the
// parts, when there are any, which is why their revenue is lumpy.
// ------------------------------
function demoBuildBilling($mysqli, $profile, $index, $client_id, $vendor_id, $months, $context, &$counts) {

    global $config_invoice_prefix, $config_quote_prefix, $config_recurring_invoice_prefix;

    $currency = $context['currency'];
    $tax = $context['taxes'][$profile['tax']] ?? null;
    $income_category_id = demoCategoryId($mysqli, 'Managed Services', 'Income');
    $project_category_id = demoCategoryId($mysqli, 'Projects', 'Income') ?: $income_category_id;
    $hardware_category_id = demoCategoryId($mysqli, 'Hardware Sales', 'Income') ?: $income_category_id;
    $support_category_id = demoCategoryId($mysqli, 'Support', 'Income') ?: $income_category_id;

    $bank_account_id = $context['accounts']['Operating Checking'] ?? 0;
    $card_account_id = $context['accounts']['Merchant Settlement'] ?? $bank_account_id;

    // Catalogue products, so invoice lines point at the real thing where the
    // name matches something the starter content pack put in
    $product_ids = [];
    $sql = mysqli_query($mysqli, "SELECT product_id, product_name FROM products");
    while ($row = mysqli_fetch_assoc($sql)) {
        $product_ids[mb_strtolower($row['product_name'])] = intval($row['product_id']);
    }

    $payment_methods = ['ACH', 'Credit Card', 'Check'];
    $one_off_pool = demoOneOffInvoicePool();
    $recurring_invoice_id = 0;

    // ---- The agreement, for anyone on one ----
    // Managed clients and hosting only clients both run on a recurring invoice;
    // break fix clients have no agreement lines at all
    if ($profile['agreement']) {

        $recurring_total = demoDocumentTotal($profile['agreement'], $tax);

        $recurring_invoice_id = starterInsert($mysqli, 'recurring_invoices', [
            'recurring_invoice_prefix' => $config_recurring_invoice_prefix ?? '',
            'recurring_invoice_number' => demoNextNumber($mysqli, 'config_recurring_invoice_next_number'),
            'recurring_invoice_scope' => 'Monthly managed services agreement',
            'recurring_invoice_frequency' => 'month',
            'recurring_invoice_next_date' => date('Y-m-01', strtotime('+1 month')),
            'recurring_invoice_last_sent' => date('Y-m-01'),
            'recurring_invoice_status' => 1,
            'recurring_invoice_amount' => number_format($recurring_total, 2, '.', ''),
            'recurring_invoice_currency_code' => $currency,
            'recurring_invoice_note' => 'Billed on the first of each month in advance. Out of scope work is billed separately at the agreed rate.',
            // Fictional contacts must never be mailed, so cron notification is off
            'recurring_invoice_email_notify' => 0,
            'recurring_invoice_category_id' => $income_category_id,
            'recurring_invoice_client_id' => $client_id,
            'recurring_invoice_created_at' => demoMonthDateTime($months, 1, 9, 30),
        ]);

        foreach ($profile['agreement'] as $item_order => $line) {
            demoInvoiceItem($mysqli, 'recurring_invoice_items', 'item_recurring_invoice_id', $recurring_invoice_id, $line, $item_order, demoMonthDateTime($months, 1, 9, 30), $tax, $product_ids);
        }
        $counts['billing']++;

        for ($month = $months; $month >= 0; $month--) {

            $invoice_date = demoMonthDate($month, 1);
            $created_at = demoMonthDateTime($month, 1, 6, 5);
            $due_date = date('Y-m-d', strtotime($invoice_date . ' +' . $profile['net_terms'] . ' days'));

            // Most clients pay on time and sit at zero. Some are carrying the
            // current month, a couple are properly behind - which is what makes
            // the aging report and the outstanding balances report worth opening
            $outstanding_months = 0;
            if ($profile['balance'] === 'current') {
                $outstanding_months = 1;
            } elseif ($profile['balance'] === 'past_due') {
                $outstanding_months = 3;
            }
            $paid = $month >= $outstanding_months;
            $partial = $paid && $month === 5 && $profile['balance'] !== 'clear';

            $status = 'Paid';
            if (!$paid) {
                $status = $month === 0 ? 'Sent' : 'Viewed';
            } elseif ($partial) {
                $status = 'Partial';
            }

            $invoice_id = starterInsert($mysqli, 'invoices', [
                'invoice_prefix' => $config_invoice_prefix ?? '',
                'invoice_number' => demoNextNumber($mysqli, 'config_invoice_next_number'),
                'invoice_scope' => 'Managed services - ' . date('F Y', strtotime($invoice_date)),
                'invoice_status' => $status,
                'invoice_date' => $invoice_date,
                'invoice_due' => $due_date,
                'invoice_amount' => number_format($recurring_total, 2, '.', ''),
                'invoice_currency_code' => $currency,
                'invoice_note' => 'Agreement billing for the month ahead.',
                'invoice_url_key' => randomString(32),
                'invoice_category_id' => $income_category_id,
                'invoice_recurring_invoice_id' => $recurring_invoice_id,
                'invoice_client_id' => $client_id,
                'invoice_created_at' => $created_at,
            ]);

            foreach ($profile['agreement'] as $item_order => $line) {
                demoInvoiceItem($mysqli, 'invoice_items', 'item_invoice_id', $invoice_id, $line, $item_order, $created_at, $tax, $product_ids);
            }

            starterInsert($mysqli, 'history', [
                'history_status' => 'Sent',
                'history_description' => 'Invoice emailed to the billing contact',
                'history_invoice_id' => $invoice_id,
                'history_created_at' => demoMonthDateTime($month, 1, 6, 10),
            ]);

            if ($paid) {
                $method = $payment_methods[($index + $month) % 3];
                $amount = $partial ? round($recurring_total / 2, 2) : $recurring_total;
                demoPayment($mysqli, $invoice_id, $amount, $currency, $method, $method === 'Credit Card' ? $card_account_id : $bank_account_id, $month, $profile, $index, $invoice_date);
                starterInsert($mysqli, 'history', [
                    'history_status' => $partial ? 'Partial' : 'Paid',
                    'history_description' => $partial ? 'Part payment received' : 'Payment received in full',
                    'history_invoice_id' => $invoice_id,
                    'history_created_at' => demoMonthDateTime($month, 12, 14, 0),
                ]);
            }

            $counts['billing']++;

        }

    }

    // ---- Break fix work, billed by the hour when it happens ----
    if ($profile['billing'] === 'break_fix') {

        $labor_pool = demoBreakFixInvoicePool();

        for ($month = $months; $month >= 0; $month--) {

            // Not every month - break fix clients only appear when something breaks
            if (($month + $index) % 3 === 1) {
                continue;
            }

            $visit = $labor_pool[($index + $month) % count($labor_pool)];
            $hours = 1.5 + (($month + $index) % 5);
            $lines = [
                ['Onsite Support - Hourly', $hours, $profile['rate'], $visit[1], 0],
            ];
            foreach ($visit[2] as $part) {
                $lines[] = $part;
            }

            $invoice_date = demoMonthDate($month, 6 + (($index + $month) % 12));
            if (demoIsFuture($invoice_date)) {
                continue;
            }
            $created_at = $invoice_date . ' 17:20:00';
            $total = demoDocumentTotal($lines, $tax);

            // Break fix clients settle on the same terms as everyone else - a
            // client marked as clear has nothing outstanding here either
            $outstanding_months = 0;
            if ($profile['balance'] === 'current') {
                $outstanding_months = 1;
            } elseif ($profile['balance'] === 'past_due') {
                $outstanding_months = 3;
            }
            $paid = $month >= $outstanding_months;

            $invoice_id = starterInsert($mysqli, 'invoices', [
                'invoice_prefix' => $config_invoice_prefix ?? '',
                'invoice_number' => demoNextNumber($mysqli, 'config_invoice_next_number'),
                'invoice_scope' => $visit[0],
                'invoice_status' => $paid ? 'Paid' : 'Sent',
                'invoice_date' => $invoice_date,
                'invoice_due' => date('Y-m-d', strtotime($invoice_date . ' +' . $profile['net_terms'] . ' days')),
                'invoice_amount' => number_format($total, 2, '.', ''),
                'invoice_currency_code' => $currency,
                'invoice_note' => 'Time and materials. Labor billed at the agreed hourly rate, parts at cost plus.',
                'invoice_url_key' => randomString(32),
                'invoice_category_id' => $support_category_id,
                'invoice_client_id' => $client_id,
                'invoice_created_at' => $created_at,
            ]);

            foreach ($lines as $item_order => $line) {
                demoInvoiceItem($mysqli, 'invoice_items', 'item_invoice_id', $invoice_id, $line, $item_order, $created_at, $tax, $product_ids);
            }

            starterInsert($mysqli, 'history', [
                'history_status' => 'Sent',
                'history_description' => 'Invoice emailed after the visit',
                'history_invoice_id' => $invoice_id,
                'history_created_at' => $created_at,
            ]);

            if ($paid) {
                demoPayment($mysqli, $invoice_id, $total, $currency, $payment_methods[($index + $month) % 3], $bank_account_id, max(0, $month - 1), $profile, $index, $invoice_date);
            }

            $counts['billing']++;

        }

    }

    // ---- Project and hardware work, on top of whichever model they are on ----
    for ($month = $months; $month >= 0; $month--) {

        if (($month + $index) % 6 !== 3) {
            continue;
        }

        $one_off = $one_off_pool[($index + $month) % count($one_off_pool)];
        $one_off_created = demoMonthDateTime($month, 18, 11, 30);
        if (demoIsFuture(demoMonthDate($month, 18))) {
            continue;
        }
        $one_off_total = demoDocumentTotal($one_off[1], $tax);

        $one_off_id = starterInsert($mysqli, 'invoices', [
            'invoice_prefix' => $config_invoice_prefix ?? '',
            'invoice_number' => demoNextNumber($mysqli, 'config_invoice_next_number'),
            'invoice_scope' => $one_off[0],
            'invoice_status' => 'Paid',
            'invoice_date' => demoMonthDate($month, 18),
            'invoice_due' => date('Y-m-d', strtotime(demoMonthDate($month, 18) . ' +' . $profile['net_terms'] . ' days')),
            'invoice_amount' => number_format($one_off_total, 2, '.', ''),
            'invoice_currency_code' => $currency,
            'invoice_note' => 'Approved by the client before the work was scheduled.',
            'invoice_url_key' => randomString(32),
            'invoice_category_id' => $one_off[2] === 'hardware' ? $hardware_category_id : $project_category_id,
            'invoice_client_id' => $client_id,
            'invoice_created_at' => $one_off_created,
        ]);

        foreach ($one_off[1] as $item_order => $line) {
            demoInvoiceItem($mysqli, 'invoice_items', 'item_invoice_id', $one_off_id, $line, $item_order, $one_off_created, $tax, $product_ids);
        }

        demoPayment($mysqli, $one_off_id, $one_off_total, $currency, 'ACH', $bank_account_id, max(0, $month - 1), $profile, $index, demoMonthDate($month, 18));
        $counts['billing']++;

    }

    // ---- Quotes, at every stage of the pipeline ----
    $quote_statuses = ['Invoiced', 'Accepted', 'Declined', 'Sent', 'Draft'];
    $quote_slot = 0;
    for ($month = $months; $month >= 0; $month = $month - 5) {

        if (demoIsFuture(demoMonthDate($month, 9))) {
            $quote_slot++;
            continue;
        }

        $quote = $profile['quotes'][$quote_slot % count($profile['quotes'])];
        $quote_total = demoDocumentTotal($quote[1], $tax);
        $status = $month <= 5 ? $quote_statuses[($index + $quote_slot) % 2 + 3] : $quote_statuses[($index + $quote_slot) % 3];
        $quote_created = demoMonthDateTime($month, 9, 11, 20);

        $quote_id = starterInsert($mysqli, 'quotes', [
            'quote_prefix' => $config_quote_prefix ?? '',
            'quote_number' => demoNextNumber($mysqli, 'config_quote_next_number'),
            'quote_scope' => $quote[0],
            'quote_status' => $status,
            'quote_date' => demoMonthDate($month, 9),
            'quote_expire' => demoMonthDate($month - 1, 9),
            'quote_amount' => number_format($quote_total, 2, '.', ''),
            'quote_currency_code' => $currency,
            'quote_note' => 'Pricing held for 30 days. Lead times confirmed at time of order.',
            'quote_url_key' => randomString(32),
            'quote_category_id' => $project_category_id,
            'quote_client_id' => $client_id,
            'quote_created_at' => $quote_created,
        ]);

        foreach ($quote[1] as $item_order => $line) {
            demoInvoiceItem($mysqli, 'quote_items', 'item_quote_id', $quote_id, $line, $item_order, $quote_created, $tax, $product_ids);
        }

        starterInsert($mysqli, 'history', [
            'history_status' => 'Sent',
            'history_description' => 'Quote sent for approval',
            'history_quote_id' => $quote_id,
            'history_created_at' => demoMonthDateTime($month, 9, 11, 30),
        ]);

        $quote_slot++;
        $counts['billing']++;

    }

    // ---- What this client costs us ----
    $expense_slot = 0;
    for ($month = $months; $month >= 0; $month = $month - 2) {
        $expense = $profile['expenses'][$expense_slot % count($profile['expenses'])];
        $expense_slot++;
        if (demoIsFuture(demoMonthDate($month, 14))) {
            continue;
        }
        starterInsert($mysqli, 'expenses', [
            'expense_description' => $expense[0],
            'expense_amount' => number_format($expense[1], 2, '.', ''),
            'expense_currency_code' => $currency,
            'expense_date' => demoMonthDate($month, 14),
            'expense_reference' => 'INV-' . demoSerial($profile['abbreviation'] . $expense[0] . $month, 8),
            'expense_payment_method' => 'Credit Card',
            'expense_vendor_id' => $context['org_vendors'][$expense[3]] ?? 0,
            'expense_client_id' => $client_id,
            'expense_category_id' => demoCategoryId($mysqli, $expense[2], 'Expense'),
            'expense_account_id' => $bank_account_id,
            'expense_created_at' => demoMonthDateTime($month, 14, 16, 45),
        ]);
        $counts['billing']++;
    }

    // ---- Income that never went through an invoice ----
    for ($month = $months; $month >= 0; $month = $month - 12) {
        if (demoIsFuture(demoMonthDate($month, 22))) {
            continue;
        }
        starterInsert($mysqli, 'revenues', [
            'revenue_date' => demoMonthDate($month, 22),
            'revenue_amount' => number_format(1200.00 + ($index * 75), 2, '.', ''),
            'revenue_currency_code' => $currency,
            'revenue_payment_method' => 'Check',
            'revenue_reference' => 'DEP-' . demoSerial($profile['abbreviation'] . 'rev' . $month, 8),
            'revenue_description' => 'Prepaid block of support hours',
            'revenue_category_id' => $support_category_id,
            'revenue_account_id' => $bank_account_id,
            'revenue_client_id' => $client_id,
            'revenue_created_at' => demoMonthDateTime($month, 22, 10, 15),
        ]);
        $counts['billing']++;
    }

    // Block hours bought up front leave a balance on the account
    if ($profile['billing'] === 'break_fix') {
        starterInsert($mysqli, 'credits', [
            'credit_amount' => number_format(500.00, 2, '.', ''),
            'credit_type' => 'prepaid',
            'credit_note' => 'Remaining balance from the prepaid block of hours.',
            'credit_created_by' => $context['user_id'],
            'credit_client_id' => $client_id,
            'credit_created_at' => demoMonthDateTime(min(6, $months), 22, 10, 20),
        ]);
        $counts['billing']++;
    }

}

// ------------------------------
// demoBuildCompanyFinancials
// The MSP's own money - two years of operating costs against the organisation
// suppliers, the standing costs as recurring expenses, and the transfers that
// move card settlements into the bank and the tax reserve into savings.
// ------------------------------
function demoBuildCompanyFinancials($mysqli, $context, &$counts) {

    // Already built on an earlier run - every company expense carries one of
    // our own suppliers, so one of those is enough to tell
    $vendor_ids = array_map('intval', $context['org_vendors']);
    if ($vendor_ids) {
        $vendor_ids = implode(',', $vendor_ids);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(expense_id) AS total FROM expenses WHERE expense_client_id = 0 AND expense_vendor_id IN ($vendor_ids)"));
        if (intval($row['total'] ?? 0)) {
            return;
        }
    }

    $currency = $context['currency'];
    $vendors = $context['org_vendors'];
    $operating_id = $context['accounts']['Operating Checking'] ?? 0;
    $savings_id = $context['accounts']['Business Savings'] ?? 0;
    $merchant_id = $context['accounts']['Merchant Settlement'] ?? 0;

    if (!$operating_id) {
        return;
    }

    // description, amount, category, every N months, day, vendor
    $operating_costs = [
        ['Office rent', 1850.00, 'Rent and Utilities', 1, 1, 'Monongahela Property Group'],
        ['Payroll', 14250.00, 'Payroll', 1, 28, 'Alcott and Reyes CPA'],
        ['Microsoft and Google partner licensing', 2450.00, 'Licensing - Cost of Goods', 1, 6, 'Cascade Cloud Licensing'],
        ['Colocation, power and transit', 495.00, 'Infrastructure', 1, 3, 'Three Rivers Colocation'],
        ['Proxmox and Proxmox Backup Server subscriptions', 385.00, 'Software', 1, 9, 'Proxmox Server Solutions'],
        ['Managed Nextcloud instances', 240.00, 'Cloud and Hosting', 1, 11, 'Nextcloud Hosting Partner'],
        ['Merchant and bank service charges', 148.00, 'Bank Fees', 1, 26, 'Steelworks Business Bank'],
        ['Business insurance', 1450.00, 'Insurance', 3, 12, 'Keystone Commercial Insurance'],
        ['Bookkeeping and payroll filing', 900.00, 'Professional Services', 3, 15, 'Alcott and Reyes CPA'],
        ['Bench stock - spare drives, cables and small parts', 640.00, 'Tools and Test Equipment', 4, 19, 'Ridgeline Technology Distribution'],
        ['UniFi shelf stock for the workshop', 1150.00, 'Hardware - Cost of Goods', 6, 7, 'Ubiquiti Store'],
        ['pfSense Plus support subscriptions', 795.00, 'Software', 12, 22, 'Netgate'],
        ['Office supplies and printing', 145.00, 'Office Supplies', 1, 14, 'Ridgeline Technology Distribution'],
        ['Team lunch', 96.00, 'Meals', 1, 17, 'Monongahela Property Group'],
        ['Mobile phones and data for the team', 268.00, 'Telecom and Internet', 1, 8, 'Three Rivers Colocation'],
        ['Professional body dues and subscriptions', 210.00, 'Dues and Subscriptions', 3, 20, 'Alcott and Reyes CPA'],
        ['Van servicing and tyres', 480.00, 'Vehicle and Fuel', 6, 13, 'Ridgeline Technology Distribution'],
        ['Replacement bench laptop and monitors', 1650.00, 'Equipment', 12, 16, 'Ridgeline Technology Distribution'],
        ['Subcontract cabling work', 1250.00, 'Contractor', 4, 25, 'Ridgeline Technology Distribution'],
        ['Courier and shipping', 88.00, 'Shipping and Postage', 1, 27, 'Ridgeline Technology Distribution'],
        ['Quarterly estimated tax payment', 4200.00, 'Taxes', 3, 10, 'Alcott and Reyes CPA'],
        ['Conference and travel', 1450.00, 'Travel', 12, 18, 'Alcott and Reyes CPA'],
    ];

    // Which purchases put hardware on the shelf, and what they bring in
    $stock_lines = [
        'UniFi shelf stock for the workshop' => ['Ubiquiti U6-Pro Access Point', 4],
        'Bench stock - spare drives, cables and small parts' => ['Rack Mount UPS 1500VA', 1],
    ];

    for ($month = 24; $month >= 0; $month--) {
        foreach ($operating_costs as $cost) {

            if ($month % $cost[3] !== 0 || demoIsFuture(demoMonthDate($month, $cost[4]))) {
                continue;
            }

            // Payroll and rent creep up over two years rather than sitting flat
            $drift = 1 + ((24 - $month) * 0.004);

            $expense_id = starterInsert($mysqli, 'expenses', [
                'expense_description' => $cost[0],
                'expense_amount' => number_format($cost[1] * $drift, 2, '.', ''),
                'expense_currency_code' => $currency,
                'expense_date' => demoMonthDate($month, $cost[4]),
                'expense_reference' => 'BILL-' . demoSerial('company' . $cost[0] . $month, 8),
                'expense_payment_method' => $cost[0] === 'Payroll' ? 'ACH' : 'Credit Card',
                'expense_vendor_id' => $vendors[$cost[5]] ?? 0,
                'expense_client_id' => 0,
                'expense_category_id' => demoCategoryId($mysqli, $cost[2], 'Expense'),
                'expense_account_id' => $operating_id,
                'expense_created_at' => demoMonthDateTime($month, $cost[4], 17, 0),
            ]);
            $counts['company']++;

            // Hardware bought for the shelf goes into stock against the
            // purchase, the same way the stock modal records it
            if (isset($stock_lines[$cost[0]]) && isset($context['products'][$stock_lines[$cost[0]][0]])) {
                starterInsert($mysqli, 'product_stock', [
                    'stock_qty' => $stock_lines[$cost[0]][1],
                    'stock_note' => 'Shelf stock in from ' . $cost[5],
                    'stock_expense_id' => $expense_id,
                    'stock_product_id' => $context['products'][$stock_lines[$cost[0]][0]],
                    'stock_created_at' => demoMonthDateTime($month, $cost[4], 17, 5),
                ]);
                $counts['company']++;
            }

        }
    }

    // Small cash spending, out of the Cash account setup already created
    if ($context['cash_account_id']) {
        $petty = [
            ['Parking while onsite', 12.00, 'Vehicle and Fuel', 'Monongahela Property Group'],
            ['Bench consumables and cable ties', 28.50, 'Tools and Test Equipment', 'Ridgeline Technology Distribution'],
            ['Postage on a warranty return', 18.75, 'Shipping and Postage', 'Ridgeline Technology Distribution'],
            ['Coffee for an all night cutover', 24.00, 'Meals', 'Ridgeline Technology Distribution'],
        ];
        for ($month = 24; $month >= 0; $month--) {
            $item = $petty[$month % count($petty)];
            if (demoIsFuture(demoMonthDate($month, 23))) {
                continue;
            }
            starterInsert($mysqli, 'expenses', [
                'expense_description' => $item[0],
                'expense_amount' => number_format($item[1], 2, '.', ''),
                'expense_currency_code' => $currency,
                'expense_date' => demoMonthDate($month, 23),
                'expense_reference' => 'CASH-' . demoSerial('petty' . $item[0] . $month, 6),
                'expense_payment_method' => 'Cash',
                'expense_vendor_id' => $vendors[$item[3]] ?? 0,
                'expense_client_id' => 0,
                'expense_category_id' => demoCategoryId($mysqli, $item[2], 'Expense'),
                'expense_account_id' => $context['cash_account_id'],
                'expense_created_at' => demoMonthDateTime($month, 23, 18, 10),
            ]);
            $counts['company']++;
        }
    }

    // The standing costs, as the recurring expenses they actually are
    foreach ([
        ['Office rent', 1850.00, 'Rent and Utilities', 1, 1, 'Monongahela Property Group'],
        ['Colocation, power and transit', 495.00, 'Infrastructure', 1, 3, 'Three Rivers Colocation'],
        ['Microsoft and Google partner licensing', 2450.00, 'Licensing - Cost of Goods', 1, 6, 'Cascade Cloud Licensing'],
        ['Business insurance', 1450.00, 'Insurance', 2, 12, 'Keystone Commercial Insurance'],
    ] as $recurring) {
        starterInsert($mysqli, 'recurring_expenses', [
            'recurring_expense_frequency' => $recurring[3],
            'recurring_expense_day' => $recurring[4],
            'recurring_expense_month' => 1,
            'recurring_expense_next_date' => date('Y-m-d', strtotime('+1 month')),
            'recurring_expense_last_sent' => demoMonthDate(0, $recurring[4]),
            'recurring_expense_status' => 1,
            'recurring_expense_description' => $recurring[0],
            'recurring_expense_amount' => number_format($recurring[1], 2, '.', ''),
            'recurring_expense_payment_method' => 'ACH',
            'recurring_expense_reference' => 'STANDING-' . demoSerial('recurring' . $recurring[0], 6),
            'recurring_expense_currency_code' => $currency,
            'recurring_expense_vendor_id' => $vendors[$recurring[5]] ?? 0,
            'recurring_expense_client_id' => 0,
            'recurring_expense_category_id' => demoCategoryId($mysqli, $recurring[2], 'Expense'),
            'recurring_expense_account_id' => $operating_id,
            'recurring_expense_created_at' => demoMonthDateTime(24, $recurring[4], 9, 0),
        ]);
        $counts['company']++;
    }

    // Closure days, so the SLA clock has something to pause on. Matched on the
    // date and the name so the built in US holiday importer can be run either
    // before or after this without doubling anything up.
    foreach ([date('Y') - 1, date('Y'), date('Y') + 1] as $year) {
        foreach (demoDataHolidays($year) as $holiday) {
            $holiday_date = escapeSql($holiday[0]);
            $holiday_name = escapeSql($holiday[1]);
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT holiday_id FROM business_holidays WHERE holiday_date = '$holiday_date' AND holiday_name = '$holiday_name' LIMIT 1"));
            if (!empty($row['holiday_id'])) {
                continue;
            }
            starterInsert($mysqli, 'business_holidays', [
                'holiday_date' => $holiday[0],
                'holiday_name' => $holiday[1],
                'holiday_created_at' => demoMonthDateTime(24, 4, 9, 0),
            ]);
            $counts['company']++;
        }
    }

    // The plan the actual spend gets measured against
    foreach ([date('Y') - 1, date('Y')] as $year) {
        foreach (demoDataBudgets() as $budget) {
            $category_id = demoCategoryId($mysqli, $budget[0], 'Expense');
            if (!$category_id) {
                continue;
            }
            // Last year's plan was set a little lower than this year's
            $amount = $year < (int)date('Y') ? $budget[1] * 0.94 : $budget[1];
            for ($month = 1; $month <= 12; $month++) {
                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT budget_id FROM budget WHERE budget_category_id = $category_id AND budget_month = $month AND budget_year = $year LIMIT 1"));
                if (!empty($row['budget_id'])) {
                    continue;
                }
                starterInsert($mysqli, 'budget', [
                    'budget_month' => $month,
                    'budget_year' => $year,
                    'budget_amount' => number_format($amount, 2, '.', ''),
                    'budget_description' => 'Planned monthly spend',
                    'budget_category_id' => $category_id,
                    'budget_created_at' => demoMonthDateTime(24, 5, 9, 0),
                ]);
                $counts['company']++;
            }
        }
    }

    // Card settlements landing in the bank, and the quarterly tax reserve
    $cash_id = $context['cash_account_id'];
    for ($month = 24; $month >= 0; $month--) {

        if ($merchant_id) {
            demoTransfer($mysqli, $merchant_id, $operating_id, 4250.00 + (($month % 7) * 315), $currency, 'Merchant payout', 'Card settlements paid out to the operating account', $month, 5, $counts);
        }

        if ($savings_id && $month % 3 === 0) {
            demoTransfer($mysqli, $operating_id, $savings_id, 6500.00, $currency, 'Bank Transfer', 'Quarterly tax reserve moved to savings', $month, 16, $counts);
        }

        // Topping the cash tin back up out of the bank
        if ($cash_id && $month % 4 === 0) {
            demoTransfer($mysqli, $operating_id, $cash_id, 250.00, $currency, 'Cash', 'Cash float topped up for parts runs and parking', $month, 19, $counts);
        }

    }

}

// ------------------------------
// demoDataHolidays
// Closure days, so the SLA clock has something to pause on. Only the fixed date
// ones are listed outright - the floating ones are worked out per year, which
// is why they are computed rather than hard coded.
// ------------------------------
function demoDataHolidays($year) {

    $thanksgiving = date('Y-m-d', strtotime("fourth thursday of november $year"));

    return [
        [$year . '-01-01', "New Year's Day"],
        [$year . '-07-04', 'Independence Day'],
        [$year . '-11-11', 'Veterans Day'],
        [$year . '-12-24', 'Christmas Eve'],
        [$year . '-12-25', 'Christmas Day'],
        [date('Y-m-d', strtotime("last monday of may $year")), 'Memorial Day'],
        [date('Y-m-d', strtotime("first monday of september $year")), 'Labor Day'],
        [$thanksgiving, 'Thanksgiving'],
        [date('Y-m-d', strtotime($thanksgiving . ' +1 day')), 'Day after Thanksgiving'],
    ];
}

// ------------------------------
// demoDataBudgets
// What we plan to spend each month, by category. The budget report is empty
// without it, and an empty report is the one thing a demo cannot show.
// category, monthly amount
// ------------------------------
function demoDataBudgets() {
    return [
        ['Payroll', 15000.00],
        ['Rent and Utilities', 1900.00],
        ['Licensing - Cost of Goods', 2600.00],
        ['Hardware - Cost of Goods', 1400.00],
        ['Infrastructure', 550.00],
        ['Software', 450.00],
        ['Insurance', 500.00],
        ['Professional Services', 350.00],
        ['Telecom and Internet', 300.00],
        ['Vehicle and Fuel', 250.00],
        ['Tools and Test Equipment', 200.00],
        ['Advertising', 150.00],
    ];
}

// ------------------------------
// demoDataErrands
// The unbilled running around every MSP does and nobody logs against a client.
// purpose, destination, miles, every N weeks, day offset
// ------------------------------
function demoDataErrands() {
    return [
        ['Bank - deposit cheques and cash', 'Steelworks Business Bank, Pittsburgh, PA', 6.4, 2, 3],
        ['Distributor - parts collection', 'Ridgeline Technology Distribution, Pittsburgh, PA', 14.2, 3, 1],
        ['Colocation - hands on at the rack', 'Three Rivers Colocation, Pittsburgh, PA', 11.8, 4, 4],
        ['Post office - warranty returns', 'Post Office, Pittsburgh, PA', 3.1, 2, 5],
        ['Accountant - drop off paperwork', 'Alcott and Reyes CPA, Pittsburgh, PA', 8.7, 12, 2],
    ];
}

// ------------------------------
// demoBuildInternalDiary
// The MSP's own week - the meetings, on call handovers and maintenance windows
// that have nothing to do with any one client, plus the running around that
// never gets billed. None of this carries a client id, which is the point: a
// calendar and a mileage log with only client work in it looks fake.
// ------------------------------
function demoBuildInternalDiary($mysqli, $context, &$counts) {

    $user_id = $context['user_id'];
    $internal_id = $context['calendars']['Internal'] ?? 0;
    $maintenance_id = $context['calendars']['Maintenance Windows'] ?? 0;

    if (!$internal_id) {
        return;
    }

    // Already built on an earlier run
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(event_id) AS total FROM calendar_events WHERE event_calendar_id = $internal_id AND event_client_id = 0"));
    if (intval($row['total'] ?? 0)) {
        return;
    }

    // Weekly and monthly fixtures, running back a quarter and forward a month
    // title, calendar, description, weekday offset from Monday, start hour, length in hours, every N weeks
    $fixtures = [
        ['Team stand up', $internal_id, 'Fifteen minutes on the queue, anything stuck and who is out.', 0, 8, 1, 1],
        ['Ticket queue review', $internal_id, 'Walk the open queue, reassign anything stale and check nothing is sitting past target.', 2, 16, 1, 1],
        ['On call handover', $internal_id, 'Handover of the on call phone, open incidents and anything scheduled overnight.', 4, 16, 1, 1],
        ['Backup and patch review', $maintenance_id ?: $internal_id, 'Review failed backup jobs and patch compliance across every managed client.', 1, 9, 2, 1],
        ['Monthly maintenance window', $maintenance_id ?: $internal_id, 'Firmware, hypervisor updates and reboots on the management stack. Notify clients 48 hours ahead.', 5, 20, 4, 4],
        ['Vendor briefing', $internal_id, 'Distribution and vendor update - roadmap, stock and pricing.', 3, 13, 1, 4],
        ['Quarterly planning', $internal_id, 'Pipeline, hiring, tooling spend and the client review schedule for the quarter.', 1, 13, 3, 12],
        ['Technical training', $internal_id, 'Protected time for certification study and lab work.', 4, 13, 3, 2],
    ];

    foreach ($fixtures as $fixture) {
        for ($week = 13; $week >= -4; $week--) {

            if ($week % $fixture[6] !== 0) {
                continue;
            }

            // Monday of that week, then the weekday offset
            $days_ago = ($week * 7) - $fixture[3] + (int)date('N') - 1;
            $start = demoDateTime($days_ago, $fixture[4], 0);
            $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $fixture[5] . ' hours'));

            starterInsert($mysqli, 'calendar_events', [
                'event_title' => $fixture[0],
                'event_location' => $fixture[0] === 'Monthly maintenance window' ? 'Remote' : 'Office',
                'event_description' => $fixture[2],
                'event_start' => $start,
                'event_end' => $end,
                'event_all_day' => 0,
                'event_client_id' => 0,
                'event_calendar_id' => $fixture[1],
                'event_created_at' => demoDateTime($days_ago + 7, 9, 0),
            ]);
            $counts['company']++;

        }
    }

    // One off internal days
    foreach ([
        ['Company closed - public holiday', 21, 1, 'Office closed. On call cover only.'],
        ['Insurance renewal call', 9, 2, 'Annual review of liability, errors and omissions and cyber cover.'],
        ['Accountant - year end review', 30, 2, 'Year end accounts walkthrough and tax position.'],
        ['Office network rebuild', -12, 6, 'Replacing our own switching and rerunning the bench cabling.'],
    ] as $one_off) {
        starterInsert($mysqli, 'calendar_events', [
            'event_title' => $one_off[0],
            'event_location' => 'Office',
            'event_description' => $one_off[3],
            'event_start' => demoDateTime($one_off[1], 9, 0),
            'event_end' => demoDateTime($one_off[1], 9 + $one_off[2], 0),
            'event_all_day' => $one_off[2] > 5 ? 1 : 0,
            'event_client_id' => 0,
            'event_calendar_id' => $internal_id,
            'event_created_at' => demoDateTime($one_off[1] + 14, 9, 0),
        ]);
        $counts['company']++;
    }

    // Running around that is not billable to anybody
    foreach (demoDataErrands() as $errand) {
        for ($week = 26; $week >= 0; $week--) {
            if ($week % $errand[3] !== 0) {
                continue;
            }
            $days_ago = ($week * 7) - $errand[4];
            if ($days_ago < 0 || demoIsFuture(demoDate($days_ago))) {
                continue;
            }
            starterInsert($mysqli, 'trips', [
                'trip_date' => demoDate($days_ago),
                'trip_purpose' => $errand[0],
                'trip_source' => 'Office',
                'trip_destination' => $errand[1],
                'trip_miles' => $errand[2],
                'round_trip' => 1,
                'trip_user_id' => $user_id,
                'trip_client_id' => 0,
                'trip_created_at' => demoDateTime($days_ago, 17, 45),
            ]);
            $counts['company']++;
        }
    }

}

// ------------------------------
// demoTransfer
// A transfer is an expense on one account and a revenue on the other, joined by
// a transfers row - the same three writes the transfer handler makes, including
// leaving both halves with no vendor and no category. That is deliberate on
// ITFlow's part: the expense and income reports key off those two columns to
// keep money that only moved between accounts out of the profit and loss.
// Removal finds them through the transfers table instead.
// ------------------------------
function demoTransfer($mysqli, $from_account_id, $to_account_id, $amount, $currency, $method, $notes, $month, $day, &$counts) {

    // Both halves are categorised as a transfer. The handler leaves these at
    // zero, but a blank category on the expense list looks like missing data -
    // and because the same amount lands on the income and the expense side, the
    // profit and loss still nets to nothing.
    $expense_category_id = demoCategoryId($mysqli, 'Account Transfer', 'Expense');
    $revenue_category_id = demoCategoryId($mysqli, 'Account Transfer', 'Income');

    $date = demoMonthDate($month, $day);
    if (demoIsFuture($date)) {
        return;
    }
    $created_at = demoMonthDateTime($month, $day, 10, 30);
    $reference = 'TRF-' . demoSerial('transfer' . $from_account_id . $to_account_id . $month . $day, 8);

    $expense_id = starterInsert($mysqli, 'expenses', [
        'expense_date' => $date,
        'expense_amount' => number_format($amount, 2, '.', ''),
        'expense_currency_code' => $currency,
        'expense_reference' => $reference,
        'expense_vendor_id' => 0,
        'expense_category_id' => $expense_category_id,
        'expense_client_id' => 0,
        'expense_account_id' => $from_account_id,
        'expense_created_at' => $created_at,
    ]);

    $revenue_id = starterInsert($mysqli, 'revenues', [
        'revenue_date' => $date,
        'revenue_amount' => number_format($amount, 2, '.', ''),
        'revenue_currency_code' => $currency,
        'revenue_reference' => $reference,
        'revenue_category_id' => $revenue_category_id,
        'revenue_account_id' => $to_account_id,
        'revenue_client_id' => 0,
        'revenue_created_at' => $created_at,
    ]);

    starterInsert($mysqli, 'transfers', [
        'transfer_method' => $method,
        'transfer_notes' => $notes,
        'transfer_expense_id' => $expense_id,
        'transfer_revenue_id' => $revenue_id,
        'transfer_created_at' => $created_at,
    ]);

    $counts['company']++;

}

// ------------------------------
// demoAssetModels
// make, model, operating system, build family.
// User facing kit is indexed by the client's build - slot 0 is the Windows
// house, slot 1 the Linux house - so a fleet does not mix at random. The rest
// rotates, because infrastructure genuinely is mixed vendor at most MSPs.
// ------------------------------
function demoAssetModels() {
    return [
        'Firewall/Router' => [
            ['Netgate', '6100 MAX', 'pfSense Plus 24.11', 'foss'],
            ['Deciso', 'DEC750', 'OPNsense 25.1', 'foss'],
            ['Ubiquiti', 'Dream Machine Special Edition', 'UniFi OS 4.1', 'corporate'],
            ['Protectli', 'VP2420', 'OPNsense 25.1', 'foss'],
        ],
        'Switch' => [
            ['Ubiquiti', 'USW-Pro-24-PoE', 'UniFi Switch firmware 7.0', 'corporate'],
            ['MikroTik', 'CRS326-24G-2S+', 'RouterOS 7.16', 'foss'],
            ['Ubiquiti', 'USW-Lite-16-PoE', 'UniFi Switch firmware 7.0', 'corporate'],
            ['Cisco', 'CBS350-24P', 'Cisco Business firmware', 'corporate'],
        ],
        'Access Point' => [
            ['Ubiquiti', 'U6-Pro', 'UniFi AP firmware 6.6', 'corporate'],
            ['Ubiquiti', 'U7-Pro', 'UniFi AP firmware 7.0', 'corporate'],
            ['TP-Link', 'EAP670', 'Omada firmware', 'corporate'],
        ],
        'Server' => [
            ['Dell', 'PowerEdge R650', 'Proxmox VE 8.3', 'foss'],
            ['TrueNAS', 'Mini R', 'TrueNAS SCALE 24.10', 'foss'],
            ['Synology', 'RS1221+', 'Synology DSM 7.2', 'corporate'],
            ['HPE', 'ProLiant DL360 Gen10', 'Proxmox VE 8.3', 'foss'],
            ['Synology', 'DS1621+', 'Synology DSM 7.2', 'corporate'],
        ],
        'Virtual Machine' => [
            ['Proxmox', 'QEMU Guest', 'Debian 12', 'foss'],
            ['Proxmox', 'QEMU Guest', 'Ubuntu Server 24.04 LTS', 'foss'],
            ['Proxmox', 'QEMU Guest', 'Windows Server 2022 Standard', 'corporate'],
            ['Proxmox', 'LXC Container', 'Nextcloud on Debian 12', 'foss'],
        ],
        'Desktop' => [
            ['Dell', 'OptiPlex 7010', 'Windows 11 Pro 24H2', 'corporate'],
            ['System76', 'Meerkat', 'Pop!_OS 22.04 LTS', 'foss'],
            ['Lenovo', 'ThinkCentre M70q', 'Linux Mint 22', 'foss'],
            ['HP', 'ProDesk 600 G9', 'Windows 11 Pro 24H2', 'corporate'],
        ],
        'Laptop' => [
            ['Dell', 'Latitude 5450', 'Windows 11 Pro 24H2', 'corporate'],
            ['Framework', 'Laptop 13', 'Pop!_OS 22.04 LTS', 'foss'],
            ['Lenovo', 'ThinkPad T14 Gen 4', 'Linux Mint 22', 'foss'],
            ['HP', 'EliteBook 840 G10', 'Windows 11 Pro 24H2', 'corporate'],
        ],
        'Printer' => [
            ['Brother', 'MFC-L8900CDW', 'Vendor firmware', 'corporate'],
            ['HP', 'LaserJet Pro M404dn', 'Vendor firmware', 'corporate'],
            ['Canon', 'imageRUNNER 1643i', 'Vendor firmware', 'corporate'],
        ],
        'Phone' => [
            ['Yealink', 'T46U', 'Vendor firmware', 'corporate'],
            ['Fanvil', 'X4U', 'Vendor firmware', 'corporate'],
        ],
        'Tablet' => [
            ['Apple', 'iPad 10th Gen', 'iPadOS 18', 'corporate'],
            ['Samsung', 'Galaxy Tab A9', 'Android 14', 'corporate'],
        ],
        'Mobile Phone' => [
            ['Apple', 'iPhone 14', 'iOS 18', 'corporate'],
            ['Fairphone', 'Fairphone 5', 'Android 14', 'foss'],
        ],
        'Camera' => [
            ['Ubiquiti', 'G4 Bullet', 'UniFi Protect firmware', 'corporate'],
            ['Reolink', 'RLC-810A', 'Vendor firmware', 'corporate'],
        ],
    ];
}

function demoAssetPrefixes() {
    return [
        'Firewall/Router' => 'FW',
        'Switch' => 'SW',
        'Access Point' => 'AP',
        'Server' => 'SRV',
        'Virtual Machine' => 'VM',
        'Desktop' => 'WS',
        'Laptop' => 'LT',
        'Printer' => 'PRN',
        'Phone' => 'PH',
        'Tablet' => 'TAB',
        'Mobile Phone' => 'MOB',
        'Camera' => 'CAM',
    ];
}

function demoAssetDescription($asset_type) {
    $descriptions = [
        'Firewall/Router' => 'Edge firewall - site to site VPN, DNS filtering and inter VLAN rules',
        'Switch' => 'Access layer switch - PoE for handsets and access points',
        'Access Point' => 'Ceiling mounted wireless access point',
        'Server' => 'Virtualisation host and file storage',
        'Virtual Machine' => 'Virtual guest',
        'Desktop' => 'Standard staff workstation',
        'Laptop' => 'Mobile staff laptop',
        'Printer' => 'Shared multifunction printer',
        'Phone' => 'Desk handset',
        'Tablet' => 'Shared tablet',
        'Mobile Phone' => 'Company mobile handset',
        'Camera' => 'Site security camera',
    ];
    return $descriptions[$asset_type] ?? 'Client equipment';
}

function demoAssetPhysicalLocation($asset_type) {
    if (in_array($asset_type, ['Firewall/Router', 'Switch', 'Server', 'Virtual Machine'])) {
        return 'Server room rack';
    }
    if ($asset_type === 'Access Point') {
        return 'Ceiling - open office';
    }
    if ($asset_type === 'Printer') {
        return 'Copy room';
    }
    return 'Staff desk';
}

// The management URL an engineer would actually reach for
function demoAssetUri($asset_type, $ip) {
    if ($asset_type === 'Server') {
        return 'https://' . $ip . ':8006';
    }
    if ($asset_type === 'Switch' || $asset_type === 'Access Point') {
        return 'https://' . $ip;
    }
    if ($asset_type === 'Firewall/Router') {
        return 'https://' . $ip;
    }
    return '';
}

// Which vault entry opens it
function demoAssetCredentialName($asset_type) {
    if ($asset_type === 'Firewall/Router') {
        return 'Firewall Administrator';
    }
    if (in_array($asset_type, ['Switch', 'Access Point'])) {
        return 'Network Controller';
    }
    if (in_array($asset_type, ['Server', 'Virtual Machine'])) {
        return 'Hypervisor and Storage Administrator';
    }
    return '';
}

function demoAssetNote($asset_type, $model) {
    if ($asset_type === 'Firewall/Router') {
        return 'Config backup is pulled nightly and kept with the client documentation. Do not push firmware without a scheduled window.';
    }
    if ($asset_type === 'Server' && $model[0] === 'Synology') {
        return 'Active Backup for Business runs here. Check the job results before touching the volume.';
    }
    if ($asset_type === 'Server') {
        return 'Cluster member. Migrate guests off before rebooting.';
    }
    if ($asset_type === 'Switch') {
        return 'Uplink is on the last port. Port map is in the site runbook.';
    }
    return '';
}

function demoAssetMaintenanceNote($asset_type) {
    if ($asset_type === 'Firewall/Router') {
        return 'Firmware reviewed at the last maintenance visit. Next review is due with the quarterly check.';
    }
    if ($asset_type === 'Server') {
        return 'Disk health checked and backup job verified at the last maintenance visit.';
    }
    if ($asset_type === 'Printer') {
        return 'Toner is ordered by the client directly. We hold the model numbers in the runbook.';
    }
    return '';
}

function demoAssetTags($asset_type, $family, $status, $age_months, $warranty_months_left, $number) {

    $tags = [];

    // Infrastructure is tagged because the tag says something operational -
    // what wakes somebody up, what is monitored, what is backed up
    $infrastructure = [
        'Firewall/Router' => ['Firewall', 'Business Critical', 'Monitored'],
        'Switch' => ['Switch', 'Monitored'],
        'Access Point' => ['Access Point', 'Monitored'],
        'Server' => ['Server', 'Business Critical', 'Backed Up', 'Monitored'],
        'Virtual Machine' => ['Server', 'Backed Up', 'Monitored'],
    ];
    if (isset($infrastructure[$asset_type])) {
        $tags = $infrastructure[$asset_type];
    }

    // Everything else only earns a tag when there is something to say about it.
    // Tagging all forty workstations "Workstation" tells nobody anything, and it
    // makes the tag filter useless for the cases that do matter.
    if ($status === 'Ready to Deploy') {
        $tags[] = 'Spare Stock';
    }
    if ($warranty_months_left <= 0) {
        $tags[] = 'Warranty Expired';
    } elseif ($warranty_months_left <= 4) {
        $tags[] = 'Under Warranty';
    }
    if ($age_months >= 60) {
        $tags[] = 'End of Life';
    }
    if ($asset_type === 'Server' && $family === 'foss') {
        $tags[] = 'Hypervisor Host';
    }
    if (in_array($asset_type, ['Laptop', 'Mobile Phone']) && $number === 1) {
        $tags[] = 'Leased';
    }

    return array_values(array_unique($tags));
}

// ------------------------------
// demoTicketPool
// The work an MSP actually sees. asset_types is what keeps a ticket attached to
// the right box - a printer ticket picks a printer, never a firewall.
// ------------------------------
function demoTicketPool() {
    return [
        [
            'subject' => 'Outlook stuck on disconnected after password reset',
            'category' => 'Microsoft 365', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Desktop', 'Laptop'],
            'details' => '<p>User reset their password this morning and Outlook has been sat on Disconnected since. Webmail works fine.</p>',
            'replies' => [
                ['Client', '<p>Still not connecting after a reboot. Can someone take a look today?</p>', ''],
                ['Public', '<p>Cleared the cached credential in Credential Manager and re-authenticated the profile. Mail is flowing again.</p>', '00:25:00'],
                ['Internal', '<p>Stale credential left over from the reset. Worth adding to the password change checklist.</p>', '00:05:00'],
            ],
            'tasks' => ['Clear cached credentials', 'Re-authenticate profile', 'Confirm mail flow with user'],
        ],
        [
            'subject' => 'Workstation running very slowly since last week',
            'category' => 'Workstation', 'priority' => 'Low', 'billable' => 1,
            'asset_types' => ['Desktop', 'Laptop'],
            'details' => '<p>Machine takes several minutes to get to a usable desktop and browsing is sluggish once it does.</p>',
            'replies' => [
                ['Public', '<p>Remoted on - disk was pinned at 100% with a stalled update. Cleared the update cache, applied pending patches and rebooted. Boot time is back to normal.</p>', '00:45:00'],
                ['Internal', '<p>Still on a spinning disk and past five years old. Flagged for the next refresh quote.</p>', '00:10:00'],
            ],
            'tasks' => ['Check resource usage', 'Clear update cache', 'Confirm with user'],
        ],
        [
            'subject' => 'Cannot print to the front office printer',
            'category' => 'Printer', 'priority' => 'Medium', 'billable' => 0,
            'asset_types' => ['Printer'],
            'details' => '<p>Nothing comes out from any of the front desk machines. The printer display shows ready.</p>',
            'replies' => [
                ['Client', '<p>It was working yesterday afternoon.</p>', ''],
                ['Public', '<p>Printer had picked up a new DHCP address. Set a static reservation on the firewall, repointed the queues and documented the address.</p>', '00:35:00'],
            ],
            'tasks' => ['Check printer IP', 'Set DHCP reservation', 'Repoint print queues'],
        ],
        [
            'subject' => 'Site internet dropping intermittently',
            'category' => 'Network', 'priority' => 'High', 'billable' => 1,
            'asset_types' => ['Firewall/Router'], 'vendor' => 1, 'onsite' => 1,
            'details' => '<p>Connection drops for a minute or two several times an hour. Affecting card processing and handsets.</p>',
            'replies' => [
                ['Public', '<p>WAN interface is flapping in the firewall logs. Raised it with the circuit provider and gave them the timestamps.</p>', '00:40:00'],
                ['Internal', '<p>Provider found a bad line card at the pole. Replacement scheduled - monitor for another 48 hours before closing.</p>', '00:15:00'],
            ],
            'tasks' => ['Pull firewall WAN logs', 'Raise provider ticket', 'Monitor for 48 hours'],
        ],
        [
            'subject' => 'New starter setup - workstation, mailbox and access',
            'category' => 'Onboarding', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Desktop', 'Laptop'],
            'details' => '<p>New hire starting a week Monday. Needs the standard build, a mailbox and access to the shared folders.</p>',
            'replies' => [
                ['Public', '<p>Machine is built to the standard image, mailbox is licensed, and group memberships match the department template. Credentials handed over on their first morning.</p>', '01:30:00'],
            ],
            'tasks' => ['Build workstation to standard', 'Create mailbox and assign licence', 'Apply department group memberships', 'Handover on day one'],
        ],
        [
            'subject' => 'Suspicious invoice email reported by staff',
            'category' => 'Phishing Report', 'priority' => 'High', 'billable' => 0,
            'asset_types' => ['Desktop', 'Laptop'],
            'details' => '<p>Staff member forwarded an emailed invoice from what looks like a supplier address but with changed bank details.</p>',
            'replies' => [
                ['Client', '<p>Nobody clicked anything as far as I know. Wanted to flag it straight away.</p>', ''],
                ['Public', '<p>Confirmed a spoofed display name from an external domain. Blocked the sender, audited mailbox rules and confirmed no forwarding was added. Good catch reporting it.</p>', '00:50:00'],
                ['Internal', '<p>Worth a short reminder to the finance team about verifying bank detail changes by phone.</p>', '00:10:00'],
            ],
            'tasks' => ['Confirm sender and headers', 'Block sender', 'Audit mailbox rules', 'Confirm no credential entry'],
        ],
        [
            'subject' => 'Backup job reporting failures overnight',
            'category' => 'Backup and Recovery', 'priority' => 'High', 'billable' => 0,
            'asset_types' => ['Server', 'Virtual Machine'],
            'details' => '<p>Backup console has flagged three consecutive failed runs on the file server.</p>',
            'replies' => [
                ['Public', '<p>Snapshot was failing on a locked database file. Excluded the live file, added the database dump to the job, and the last two runs are green with a test restore completed.</p>', '01:10:00'],
                ['Internal', '<p>Restore test evidence filed with the client documentation for the compliance folder.</p>', '00:15:00'],
            ],
            'tasks' => ['Review job logs', 'Adjust job scope', 'Run a test restore', 'File restore evidence'],
        ],
        [
            'subject' => 'Wireless dropping in the back offices',
            'category' => 'Wireless', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Access Point'], 'onsite' => 1,
            'details' => '<p>Staff at the far end of the building lose wireless when they move between rooms.</p>',
            'replies' => [
                ['Public', '<p>Survey shows a dead spot behind the store room. Adjusted transmit power on the nearest access point as a stopgap and quoted an additional unit for proper coverage.</p>', '01:00:00'],
            ],
            'tasks' => ['Survey coverage', 'Adjust AP power', 'Quote additional access point'],
        ],
        [
            'subject' => 'Shared folder permissions wrong after department move',
            'category' => 'Account and Access', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Server'],
            'details' => '<p>Two staff moved departments and can still see their old folders but not the new ones.</p>',
            'replies' => [
                ['Public', '<p>Group memberships were updated but the sessions had not refreshed. Corrected the groups, removed the leftover membership and confirmed access after sign out and back in.</p>', '00:30:00'],
            ],
            'tasks' => ['Audit group membership', 'Correct permissions', 'Confirm access with both users'],
        ],
        [
            'subject' => 'Storage pool low space warning',
            'category' => 'Monitoring Alert', 'priority' => 'High', 'billable' => 0,
            'asset_types' => ['Server', 'Virtual Machine'],
            'details' => '<p>Monitoring raised a low space alert on the storage pool - under 8% free.</p>',
            'replies' => [
                ['Public', '<p>Cleared old snapshots and expired backup sets, which recovered enough headroom for now. Recommend adding capacity at the next maintenance window.</p>', '00:40:00'],
                ['Internal', '<p>Capacity expansion needs a short outage - put it on the next scheduled visit and quote the drives.</p>', '00:05:00'],
            ],
            'tasks' => ['Free immediate space', 'Identify growth', 'Schedule capacity expansion'],
        ],
        [
            'subject' => 'Staff member leaving - offboarding',
            'category' => 'Offboarding', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Laptop', 'Desktop'],
            'details' => '<p>Leaving at the end of the week. Mailbox needs delegating to their manager and the laptop collecting.</p>',
            'replies' => [
                ['Public', '<p>Account disabled at close of business, mailbox converted and delegated, licence reclaimed, and the machine wiped and returned to spare stock.</p>', '01:15:00'],
            ],
            'tasks' => ['Disable account', 'Delegate mailbox', 'Reclaim licence', 'Collect and wipe machine'],
        ],
        [
            'subject' => 'Phones not ringing at the front desk',
            'category' => 'Phone and VoIP', 'priority' => 'High', 'billable' => 1,
            'asset_types' => ['Phone'],
            'details' => '<p>Inbound calls go straight to voicemail on the front desk handsets.</p>',
            'replies' => [
                ['Public', '<p>Ring group had lost a member after a handset swap. Re-added the extension and tested inbound from an outside line.</p>', '00:35:00'],
            ],
            'tasks' => ['Check ring group', 'Re-add extension', 'Test inbound call'],
        ],
        [
            'subject' => 'Line of business application will not launch',
            'category' => 'Line of Business Application', 'priority' => 'High', 'billable' => 1,
            'asset_types' => ['Desktop', 'Laptop'], 'vendor' => 1,
            'details' => '<p>Application closes immediately on launch for two users since this morning.</p>',
            'replies' => [
                ['Client', '<p>Everyone else seems fine. It is just the two of us.</p>', ''],
                ['Public', '<p>Client version was behind the server after the vendor updated overnight. Updated both machines to the matching build and confirmed login.</p>', '00:55:00'],
                ['Internal', '<p>Vendor pushes server side updates without notice - asked them to add us to the release list.</p>', '00:10:00'],
            ],
            'tasks' => ['Confirm client version', 'Update to matching build', 'Confirm login with both users'],
        ],
        [
            'subject' => 'Multi factor prompts looping on mobile',
            'category' => 'Account and Access', 'priority' => 'Medium', 'billable' => 0,
            'asset_types' => ['Mobile Phone'],
            'details' => '<p>Authenticator prompts keep repeating and never complete on the user mobile.</p>',
            'replies' => [
                ['Public', '<p>Removed and re-registered the authenticator entry, then confirmed sign in on both mobile and desktop.</p>', '00:30:00'],
            ],
            'tasks' => ['Re-register authenticator', 'Confirm sign in'],
        ],
        [
            'subject' => 'Nextcloud sync stuck on one machine',
            'category' => 'Software', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Desktop', 'Laptop'],
            'details' => '<p>Desktop sync client sits on Checking for changes and never finishes. Web interface is fine.</p>',
            'replies' => [
                ['Public', '<p>Sync database had gone stale. Stopped the client, cleared the local sync journal and let it rebuild - files are current again.</p>', '00:40:00'],
                ['Internal', '<p>Second time on this machine. If it comes back, reinstall the client rather than clearing the journal again.</p>', '00:05:00'],
            ],
            'tasks' => ['Stop sync client', 'Clear sync journal', 'Confirm files current'],
        ],
        [
            'subject' => 'Quote request - workstation refresh',
            'category' => 'Procurement', 'priority' => 'Low', 'billable' => 0,
            'asset_types' => [],
            'details' => '<p>Client has asked for pricing on replacing the oldest machines in the office.</p>',
            'replies' => [
                ['Public', '<p>Pulled the asset list, picked out everything past five years old and put pricing together. Quote sent for review.</p>', '00:40:00'],
            ],
            'tasks' => ['Pull asset age report', 'Build quote', 'Send for approval'],
        ],
        // ---- Break fix: things that break rather than things that are managed
        [
            'subject' => 'Machine will not power on at all',
            'category' => 'Hardware Failure', 'priority' => 'High', 'billable' => 1,
            'asset_types' => ['Desktop'], 'onsite' => 1,
            'details' => '<p>No lights, no fans, nothing. Tried a different wall socket and a known good lead.</p>',
            'replies' => [
                ['Public', '<p>Power supply had failed. Swapped in a unit from bench stock, confirmed it posts and boots clean, and the machine is back with the user.</p>', '01:15:00'],
                ['Internal', '<p>Out of warranty. Parts billed on the visit invoice.</p>', '00:05:00'],
            ],
            'tasks' => ['Confirm no power', 'Swap power supply', 'Confirm boot and hand back'],
        ],
        [
            'subject' => 'Laptop screen cracked - needs replacement',
            'category' => 'Hardware Failure', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Laptop'],
            'details' => '<p>Laptop was dropped in transit. Screen is cracked and the display is unusable.</p>',
            'replies' => [
                ['Public', '<p>Unit is out of warranty. Quoted a replacement panel and issued a loaner from spare stock so they can keep working.</p>', '00:45:00'],
            ],
            'tasks' => ['Check warranty status', 'Issue loaner', 'Quote replacement panel'],
        ],
        [
            'subject' => 'Drive failed in the NAS overnight',
            'category' => 'Hardware Failure', 'priority' => 'Urgent', 'billable' => 1,
            'asset_types' => ['Server'], 'onsite' => 1,
            'details' => '<p>Storage alert overnight - one drive has dropped out of the pool and the array is degraded.</p>',
            'replies' => [
                ['Public', '<p>Replaced the failed drive from bench stock and started the rebuild. Array is healthy again and the backup job has been re-run to be certain.</p>', '02:00:00'],
                ['Internal', '<p>Drives are all from the same batch. Recommend keeping a cold spare on site.</p>', '00:10:00'],
            ],
            'tasks' => ['Identify failed drive', 'Replace and rebuild', 'Verify backup after rebuild'],
        ],
        [
            'subject' => 'Water damage in the comms cupboard',
            'category' => 'Hardware Failure', 'priority' => 'Urgent', 'billable' => 1,
            'asset_types' => ['Switch'], 'onsite' => 1,
            'details' => '<p>Leak from the floor above came through into the comms cupboard. Switch is dead and half the office is off the network.</p>',
            'replies' => [
                ['Client', '<p>Building maintenance are dealing with the leak. We need the network back today if at all possible.</p>', ''],
                ['Public', '<p>Onsite within the hour. Replaced the switch from stock, re-patched to the documented port map and confirmed every user back on. Old unit retained for the insurance claim.</p>', '03:30:00'],
            ],
            'tasks' => ['Attend site', 'Replace switch from stock', 'Re-patch to documented port map', 'Retain failed unit for insurance'],
        ],
        [
            'subject' => 'Ransomware warning from endpoint protection',
            'category' => 'Security Incident', 'priority' => 'Urgent', 'billable' => 0,
            'asset_types' => ['Desktop', 'Laptop'], 'onsite' => 1,
            'details' => '<p>Endpoint protection quarantined something on a workstation and the user reports files with odd extensions in one folder.</p>',
            'replies' => [
                ['Public', '<p>Isolated the machine straight away. Quarantine held - the encryption never got past the local folder. Restored the affected files from backup and rebuilt the machine to be safe.</p>', '04:00:00'],
                ['Internal', '<p>Entry point was a macro in an emailed document. Written up and sent to the client with a recommendation to block macros from the internet.</p>', '00:45:00'],
            ],
            'tasks' => ['Isolate machine', 'Confirm scope of encryption', 'Restore from backup', 'Rebuild machine', 'Write up the incident'],
        ],
        [
            'subject' => 'Handset dead after power cut',
            'category' => 'Phone and VoIP', 'priority' => 'Medium', 'billable' => 1,
            'asset_types' => ['Phone'],
            'details' => '<p>Power cut at the weekend. One handset has not come back up since.</p>',
            'replies' => [
                ['Public', '<p>PoE port had latched off after the surge. Bounced the port, handset registered again. Recommended a UPS on the comms rack.</p>', '00:30:00'],
            ],
            'tasks' => ['Check PoE port', 'Bounce port', 'Confirm registration'],
        ],
    ];
}

// ------------------------------
// demoBreakFixInvoicePool
// scope, what the visit was for, parts billed with it.
// Line format: name, quantity, price, description, taxable
// ------------------------------
function demoBreakFixInvoicePool() {
    return [
        ['Onsite visit - workstation repair', 'Onsite diagnosis and repair of a workstation that would not power on', [
            ['Replacement Power Supply', 1, 89.00, 'Standard ATX power supply supplied from bench stock', 1],
        ]],
        ['Onsite visit - network fault', 'Onsite diagnosis of an intermittent network fault and switch replacement', [
            ['Managed PoE Switch', 1, 385.00, 'Replacement 16 port PoE switch', 1],
            ['Patch Leads', 6, 6.50, 'Cat6 patch leads', 1],
        ]],
        ['Remote support - mail and account issues', 'Remote support session covering mailbox and account access problems', []],
        ['Onsite visit - printer and scanning', 'Onsite work on the multifunction printer and scan to folder setup', []],
        ['Onsite visit - storage failure', 'Emergency attendance for a failed drive and array rebuild', [
            ['Enterprise Hard Drive 4TB', 1, 165.00, 'NAS rated drive supplied from stock', 1],
        ]],
        ['Remote support - malware cleanup', 'Remote isolation, cleanup and rebuild after a malware detection', []],
    ];
}

// ------------------------------
// demoOneOffInvoicePool
// Project and hardware work billed outside the agreement.
// Line format: name, quantity, price, description, taxable
// ------------------------------
function demoOneOffInvoicePool() {
    return [
        ['Workstation refresh - supply and deployment', [
            ['Business Desktop', 4, 985.00, 'Small form factor desktop, 16GB memory, 512GB NVMe', 1],
            ['Deployment and Data Migration', 4, 145.00, 'Build, migrate and hand over per machine', 0],
        ], 'hardware'],
        ['Firewall replacement and rule migration', [
            ['Netgate 6100 MAX Firewall', 1, 1245.00, 'pfSense Plus appliance with three years of support', 1],
            ['Configuration and Cutover', 8, 145.00, 'Rule migration, cutover and post change checks', 0],
        ], 'project'],
        ['Wireless coverage extension', [
            ['Ubiquiti U6-Pro Access Point', 3, 245.00, 'Wi-Fi 6 ceiling mounted access point', 1],
            ['Installation and Survey', 6, 145.00, 'Cabling, mounting and post install survey', 0],
        ], 'project'],
        ['Backup platform replacement', [
            ['Synology RS1221+ with drives', 1, 2850.00, 'Rack NAS with drives, running Active Backup for Business', 1],
            ['Build and Seed', 10, 145.00, 'Build, seed the first full backup and verify a restore', 0],
        ], 'project'],
        ['Proxmox host build and migration', [
            ['Virtualisation Host', 1, 4650.00, 'Dual socket host with NVMe storage for Proxmox VE', 1],
            ['Migration Labor', 16, 145.00, 'Guest migration, cutover and post migration support', 0],
            ['Out of Hours Cutover', 6, 195.00, 'Cutover work outside operating hours', 0],
        ], 'project'],
        ['Nextcloud deployment and file server migration', [
            ['Deployment Labor', 14, 145.00, 'Build, configure and migrate shared folders to Nextcloud', 0],
            ['User Training', 3, 145.00, 'Two short sessions covering desktop sync and sharing', 0],
        ], 'project'],
    ];
}

// ------------------------------
// demoDataSpecs
// The ten clients. Domains sit on the RFC 2606 reserved .example TLD on purpose
// - fictional contacts must never be deliverable and a fictional domain must
// never resolve to somebody's real business if a refresh gets clicked.
// build 0 is a Windows house, build 1 is a Linux house.
// ------------------------------
function demoDataSpecs() {
    return [
        [
            'name' => 'Ravenwood Dental Group', 'abbreviation' => 'RDG', 'type' => 'Healthcare',
            'domain' => 'ravenwooddental.example', 'referral' => 'Client', 'rate' => 145.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15212', 'area' => '412', 'street' => '1140 Ridge Avenue',
            'second_site' => ['Wexford Practice', '3025 Church Road', 'Wexford', 'PA', '15090'],
            'age_days' => 880, 'seats' => 26, 'servers' => 2, 'build' => 0, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [['Web Hosting - Standard', 1, 25.00, 'Practice website hosting with TLS and nightly backup', 0]],
            'lead' => 0, 'favorite' => 1, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Managed', 'Key Account', 'Compliance', 'Multi Site', 'After Hours Support'],
            'notes' => 'Two practices sharing one patient system. Anything touching the imaging server has to be out of clinic hours.',
            'account_note' => 'Practice owner signs off anything over $500. Quarterly review is booked with the office manager, not the owner.',
            'review_note' => 'Last quarterly review went well. They want costed options for replacing the imaging server before the next one.',
            'onsite_visit' => 'Quarterly review and maintenance',
            'recurring_ticket' => 'Monthly maintenance visit - Ravenwood Dental',
            'project' => ['Imaging server replacement', 'Replace the end of life imaging server and migrate the patient imaging database with no clinic downtime.'],
            'vendors' => [
                ['Keystone Business Fiber', 'Primary internet circuit and failover', '412-555-0140', 'keystonefiber.example', '4 hour response', 'Circuit ID is recorded in the site runbook. Support will not talk to anyone not on the authorised list.'],
                ['Meridian Dental Systems', 'Patient records and imaging platform', '412-555-0145', 'meridiandental.example', '8x5 support', 'Vendor manages the application, we manage the box it runs on.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 4, 'Server' => 2, 'Desktop' => 14, 'Laptop' => 3, 'Printer' => 3, 'Phone' => 8],
            'people' => [
                ['Marla Whitfield', 'Practice Owner', 'Management', 1, 1, 0, 'Signs off spend. Prefers a phone call over email.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Dana Prescott', 'Office Manager', 'Administration', 1, 1, 0, 'Day to day contact for scheduling and invoices.', ['Billing', 'Onsite Point of Contact']],
                ['Owen Castellano', 'Operations Lead', 'Operations', 0, 0, 1, 'Knows the imaging system better than the vendor does.', ['Technical', 'After Hours']],
                ['Priya Raghavan', 'Front Desk Supervisor', 'Reception', 0, 0, 0, 'First to report anything broken at the front desk.', []],
            ],
        ],
        [
            'name' => 'Kestrel Precision Manufacturing', 'abbreviation' => 'KPM', 'type' => 'Manufacturing',
            'domain' => 'kestrelprecision.example', 'referral' => 'Networking Group', 'rate' => 155.00, 'terms' => 30,
            'city' => 'Erie', 'state' => 'PA', 'zip' => '16510', 'area' => '814', 'street' => '4400 Foundry Way',
            'second_site' => ['Plant Two', '820 Industrial Parkway', 'Erie', 'PA', '16511'],
            'age_days' => 1240, 'seats' => 42, 'servers' => 4, 'build' => 0, 'billing' => 'managed',
            'balance' => 'current', 'hosting' => [],
            'lead' => 0, 'favorite' => 1, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => 'DigiCert',
            'tags' => ['Managed', 'Key Account', 'Multi Site', 'After Hours Support', 'Compliance'],
            'notes' => 'Shop floor runs two shifts. Anything touching the ERP waits for the Sunday window.',
            'account_note' => 'Plant manager owns the maintenance window. ERP vendor must be on any call involving the production database.',
            'review_note' => 'Plant Two switching is the current sore point - unmanaged kit in a dusty cupboard. Quote is with them.',
            'onsite_visit' => 'Shop floor equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Kestrel Precision',
            'project' => ['Plant Two network rebuild', 'Replace the unmanaged switching in Plant Two, add VLAN separation for shop floor equipment and extend wireless to the yard.'],
            'vendors' => [
                ['Lakeshore Telecom', 'Fibre circuits for both plants', '814-555-0175', 'lakeshoretelecom.example', 'Next business day', 'Two circuits, one per plant, on separate accounts. Both account numbers are in the runbook.'],
                ['Foundry ERP Systems', 'Production and scheduling platform', '814-555-0178', 'foundryerp.example', '8x5 support', 'They push server side updates without warning. Ask for release notes.'],
            ],
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 6, 'Access Point' => 8, 'Server' => 3, 'Virtual Machine' => 4, 'Desktop' => 22, 'Laptop' => 9, 'Printer' => 4, 'Phone' => 14, 'Camera' => 6],
            'people' => [
                ['Grant Holloway', 'Operations Director', 'Management', 1, 0, 0, 'Approves project work and downtime windows.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Renata Vasquez', 'Finance Manager', 'Finance', 0, 1, 0, 'Handles purchase orders - invoices need a PO number.', ['Billing']],
                ['Tobias Lindqvist', 'Maintenance Supervisor', 'Operations', 1, 0, 1, 'Site contact for anything on the shop floor.', ['Technical', 'Onsite Point of Contact', 'After Hours']],
                ['Alicia Freeman', 'Quality Coordinator', 'Quality', 0, 0, 0, 'Owns the audit paperwork our documentation feeds.', ['Emergency']],
            ],
        ],
        [
            'name' => 'Blackburn and Meyer LLP', 'abbreviation' => 'BML', 'type' => 'Legal',
            'domain' => 'blackburnmeyer.example', 'referral' => 'Partner', 'rate' => 165.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15222', 'area' => '412', 'street' => '600 Grant Street, Suite 1900',
            'second_site' => null,
            'age_days' => 700, 'seats' => 18, 'servers' => 2, 'build' => 0, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [],
            'lead' => 0, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => 'DigiCert',
            'tags' => ['Managed', 'Compliance', 'Cyber Insurance', 'Key Account'],
            'notes' => 'Cyber insurance renewal drives most of the security work here. Everything is documented for the questionnaire.',
            'account_note' => 'Managing partner wants a written summary after any security incident, however small.',
            'review_note' => 'Insurance questionnaire is due again in the spring. MFA coverage and backup evidence both need to be current.',
            'onsite_visit' => 'Security review and staff walkthrough',
            'recurring_ticket' => 'Monthly maintenance visit - Blackburn and Meyer',
            'project' => null,
            'vendors' => [
                ['Meridian Legal Systems', 'Practice management and document platform', '412-555-0188', 'meridianlegal.example', '8x5 support', 'Hosted by the vendor. We hold the tenant administrative access only.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 3, 'Server' => 2, 'Desktop' => 11, 'Laptop' => 8, 'Printer' => 2, 'Phone' => 12],
            'people' => [
                ['Eleanor Blackburn', 'Managing Partner', 'Management', 1, 1, 0, 'Final say on anything security related.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Curtis Meyer', 'Partner', 'Management', 1, 0, 0, 'Travels constantly - mobile is the reliable route.', ['Executive']],
                ['Sylvia Nakamura', 'Practice Administrator', 'Administration', 1, 1, 1, 'Runs the office and the practice management system.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Devon Ashcroft', 'Paralegal', 'Legal', 0, 0, 0, 'Reports issues on behalf of the paralegal team.', []],
            ],
        ],
        [
            'name' => 'Harbor Point Credit Union', 'abbreviation' => 'HPCU', 'type' => 'Financial Services',
            'domain' => 'harborpointcu.example', 'referral' => 'Chamber of Commerce', 'rate' => 175.00, 'terms' => 30,
            'city' => 'Cleveland', 'state' => 'OH', 'zip' => '44113', 'area' => '216', 'street' => '1250 Lakeside Avenue',
            'second_site' => ['Parma Branch', '7300 Ridge Road', 'Parma', 'OH', '44129'],
            'age_days' => 1500, 'seats' => 34, 'servers' => 3, 'build' => 0, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [],
            'lead' => 0, 'favorite' => 1, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => 'DigiCert',
            'tags' => ['Managed', 'Compliance', 'Cyber Insurance', 'After Hours Support', 'Multi Site', 'Key Account'],
            'notes' => 'Regulated environment. Change control paperwork is required before anything touches the core banking network.',
            'account_note' => 'Annual examination in the autumn - documentation and restore evidence must be current before then.',
            'review_note' => 'Examiners asked for firewall rule documentation last year. Standardisation project is meant to close that off.',
            'onsite_visit' => 'Branch equipment and compliance check',
            'recurring_ticket' => 'Monthly maintenance visit - Harbor Point CU',
            'project' => ['Branch firewall standardisation', 'Bring both branches onto the same firewall platform with central logging and documented rule sets for the examination.'],
            'vendors' => [
                ['Northcoast Data Services', 'Core banking platform hosting', '216-555-0122', 'northcoastdata.example', '24/7 with 1 hour response', 'Change control forms go through them 5 working days ahead.'],
                ['Cuyahoga Business Fiber', 'Branch circuits and failover', '216-555-0125', 'cuyahogafiber.example', '4 hour response', 'Both branches, separate accounts, both with 4G failover.'],
            ],
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 4, 'Access Point' => 5, 'Server' => 3, 'Virtual Machine' => 3, 'Desktop' => 24, 'Laptop' => 6, 'Printer' => 4, 'Phone' => 20, 'Camera' => 8],
            'people' => [
                ['Vernon Ashby', 'Chief Executive', 'Management', 1, 0, 0, 'Sees the quarterly summary, not the day to day.', ['Primary', 'Executive']],
                ['Karen Doyle', 'Controller', 'Finance', 0, 1, 0, 'Owns the budget and the invoices.', ['Billing', 'Authorized Approver']],
                ['Miguel Santoro', 'Operations Manager', 'Operations', 1, 0, 1, 'Coordinates change control and branch access.', ['Technical', 'Onsite Point of Contact', 'After Hours']],
                ['Hannah Brightwell', 'Branch Manager - Parma', 'Operations', 0, 0, 0, 'Point of contact for the Parma branch.', ['Emergency']],
            ],
        ],
        [
            'name' => 'Sinclair Property Group', 'abbreviation' => 'SPG', 'type' => 'Real Estate',
            'domain' => 'sinclairproperty.example', 'referral' => 'Website', 'rate' => 135.00, 'terms' => 30,
            'city' => 'Columbus', 'state' => 'OH', 'zip' => '43215', 'area' => '614', 'street' => '88 East Broad Street',
            'second_site' => null,
            'age_days' => 520, 'seats' => 15, 'servers' => 1, 'build' => 1, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [['Nextcloud Hosting - Instance', 1, 95.00, 'Managed Nextcloud instance, updates and support', 0], ['Nextcloud Hosting - Per User', 15, 6.50, 'Per user file sync, sharing and mobile access', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Co-Managed', 'Managed', 'Block Hours'],
            'notes' => 'Client has an internal person who handles first line. We pick up escalations and all infrastructure work.',
            'account_note' => 'Internal IT contact triages first - tickets should come through them rather than direct from staff.',
            'review_note' => 'They moved shared files to Nextcloud last year and have been happy with it. Considering the same for the lettings team.',
            'onsite_visit' => 'Office equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Sinclair Property',
            'project' => null,
            'vendors' => [
                ['Buckeye Broadband Business', 'Office internet', '614-555-0166', 'buckeyebusiness.example', 'Next business day', 'Single circuit, no failover. They have declined a backup line twice.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 3, 'Server' => 1, 'Virtual Machine' => 2, 'Desktop' => 8, 'Laptop' => 7, 'Printer' => 2, 'Mobile Phone' => 6],
            'people' => [
                ['Roland Sinclair', 'Principal', 'Management', 1, 1, 0, 'Owner. Wants short answers and no jargon.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Beatrix Nolan', 'Office Manager', 'Administration', 0, 1, 0, 'Handles invoices and supplier accounts.', ['Billing']],
                ['Jeremy Okafor', 'Internal IT Coordinator', 'Operations', 1, 0, 1, 'Co-managed counterpart - handles first line internally.', ['Technical', 'Onsite Point of Contact']],
                ['Nadia Kellerman', 'Lettings Coordinator', 'Operations', 0, 0, 0, 'Field staff - mostly mobile issues.', []],
            ],
        ],
        [
            'name' => 'Northgate Veterinary Clinic', 'abbreviation' => 'NVC', 'type' => 'Veterinary',
            'domain' => 'northgatevet.example', 'referral' => 'Friend', 'rate' => 125.00, 'terms' => 15,
            'city' => 'Wexford', 'state' => 'PA', 'zip' => '15090', 'area' => '724', 'street' => '910 Perry Highway',
            'second_site' => null,
            'age_days' => 400, 'seats' => 12, 'servers' => 1, 'build' => 1, 'billing' => 'break_fix',
            'balance' => 'clear', 'hosting' => [],
            'lead' => 0, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Break Fix', 'Block Hours', 'At Risk'],
            'notes' => 'Small practice, price sensitive. Still break fix after a server failure took them down for a day - we keep quoting an agreement.',
            'account_note' => 'Every visit has to be quoted before we attend. They will query anything that arrives without a heads up.',
            'review_note' => 'Third hardware failure this year. Worth another attempt at an agreement conversation at the next visit.',
            'onsite_visit' => 'Practice equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Northgate Veterinary',
            'project' => null,
            'vendors' => [
                ['Allegheny Cable Business', 'Internet and phone', '724-555-0119', 'alleghenybusiness.example', 'Best effort', 'Consumer grade support. Expect to be on hold.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 1, 'Access Point' => 2, 'Server' => 1, 'Desktop' => 7, 'Laptop' => 2, 'Printer' => 2, 'Tablet' => 3],
            'people' => [
                ['Dr Imogen Hartley', 'Practice Owner', 'Management', 1, 1, 0, 'Owner and lead vet. Best reached between appointments.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Cassidy Boyle', 'Practice Manager', 'Administration', 1, 1, 1, 'Handles everything administrative and technical on site.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Elliot Marsh', 'Veterinary Nurse', 'Clinical', 0, 0, 0, 'Reports issues from the treatment rooms.', []],
                ['Georgia Pemberton', 'Reception Lead', 'Reception', 0, 0, 0, 'Front desk and booking system.', []],
            ],
        ],
        [
            'name' => 'Delaney Construction', 'abbreviation' => 'DLC', 'type' => 'Construction',
            'domain' => 'delaneyconstruction.example', 'referral' => 'Client', 'rate' => 140.00, 'terms' => 30,
            'city' => 'Greensburg', 'state' => 'PA', 'zip' => '15601', 'area' => '724', 'street' => '2200 Route 30 East',
            'second_site' => ['Yard and Workshop', '145 Depot Street', 'Latrobe', 'PA', '15650'],
            'age_days' => 640, 'seats' => 22, 'servers' => 1, 'build' => 0, 'billing' => 'managed',
            'balance' => 'current', 'hosting' => [['Web Hosting - Standard', 1, 25.00, 'Company website hosting', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Managed', 'Multi Site', 'After Hours Support'],
            'notes' => 'Field staff work off laptops and tablets with patchy connectivity. Site offices come and go.',
            'account_note' => 'Field kit takes a beating - replacements are expected rather than exceptional. Keep spares on the shelf.',
            'review_note' => 'Asked about a rugged laptop option for the site supervisors. Pricing to follow at the next review.',
            'onsite_visit' => 'Office and yard equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Delaney Construction',
            'project' => null,
            'vendors' => [
                ['Westmoreland Wireless', 'Site office connectivity and 4G routers', '724-555-0154', 'westmorelandwireless.example', 'Next business day', 'Site office routers are on a pooled data plan. Watch the overage.'],
            ],
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 2, 'Access Point' => 4, 'Server' => 1, 'Desktop' => 6, 'Laptop' => 14, 'Printer' => 2, 'Tablet' => 6, 'Mobile Phone' => 10],
            'people' => [
                ['Fiona Delaney', 'Owner', 'Management', 1, 1, 0, 'Runs the business. Usually on a site rather than at a desk.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Warren Tiller', 'Commercial Manager', 'Finance', 0, 1, 0, 'Approves invoices and equipment purchases.', ['Billing']],
                ['Sasha Nowak', 'Site Systems Coordinator', 'Operations', 1, 0, 1, 'Sets up and strips down site office kit.', ['Technical', 'Onsite Point of Contact']],
                ['Bram Whitaker', 'Project Supervisor', 'Operations', 0, 0, 0, 'Field escalation for laptops and tablets.', ['After Hours']],
            ],
        ],
        [
            'name' => 'Aurora Wellness Center', 'abbreviation' => 'AWC', 'type' => 'Healthcare',
            'domain' => 'aurorawellness.example', 'referral' => 'Social Media', 'rate' => 130.00, 'terms' => 15,
            'city' => 'Morgantown', 'state' => 'WV', 'zip' => '26505', 'area' => '304', 'street' => '415 Chestnut Ridge Road',
            'second_site' => null,
            'age_days' => 300, 'seats' => 16, 'servers' => 1, 'build' => 1, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [['Nextcloud Hosting - Instance', 1, 95.00, 'Managed Nextcloud instance replacing consumer file sync', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Managed', 'Onboarding', 'Compliance'],
            'notes' => 'Recently onboarded. Documentation is still being built out as we find things.',
            'account_note' => 'Onboarding discovery is not finished - expect gaps in the asset list for another month.',
            'review_note' => 'Nextcloud went in during onboarding to replace a consumer file sync account. Staff have taken to it well.',
            'onsite_visit' => 'Onboarding discovery visit',
            'recurring_ticket' => 'Monthly maintenance visit - Aurora Wellness',
            'project' => ['Documentation and standardisation', 'Finish discovery, document the environment, standardise endpoint protection and get backups verified.'],
            'vendors' => [
                ['Mountaineer Connect', 'Internet and managed wifi', '304-555-0133', 'mountaineerconnect.example', '4 hour response', 'They installed the original wireless. We have taken over the controller.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 4, 'Server' => 1, 'Virtual Machine' => 2, 'Desktop' => 9, 'Laptop' => 4, 'Printer' => 2, 'Tablet' => 4],
            'people' => [
                ['Talia Mendoza', 'Clinic Director', 'Management', 1, 1, 0, 'New client contact - still learning what we do.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Ruth Ellery', 'Business Manager', 'Administration', 0, 1, 0, 'Invoices and supplier accounts.', ['Billing']],
                ['Colm Bradigan', 'Facilities Coordinator', 'Operations', 1, 0, 1, 'Holds the keys and the cupboard the switch lives in.', ['Technical', 'Onsite Point of Contact']],
                ['Yusuf Karim', 'Therapist', 'Clinical', 0, 0, 0, 'Reports issues in the treatment rooms.', []],
            ],
        ],
        [
            'name' => 'Pinnacle Freight Systems', 'abbreviation' => 'PFS', 'type' => 'Logistics',
            'domain' => 'pinnaclefreight.example', 'referral' => 'Cold Outreach', 'rate' => 150.00, 'terms' => 30,
            'city' => 'Youngstown', 'state' => 'OH', 'zip' => '44505', 'area' => '330', 'street' => '3300 Logistics Drive',
            'second_site' => ['Distribution Warehouse', '760 Terminal Road', 'Youngstown', 'OH', '44506'],
            'age_days' => 1050, 'seats' => 30, 'servers' => 3, 'build' => 0, 'billing' => 'managed',
            'balance' => 'past_due', 'hosting' => [],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => 'DigiCert',
            'tags' => ['Managed', 'After Hours Support', 'Multi Site', 'Past Due'],
            'notes' => 'Warehouse runs around the clock. Scanner and label printer issues are the bulk of the ticket volume.',
            'account_note' => 'Account has run past terms twice this year - finance chases rather than the account manager.',
            'review_note' => 'Payment terms conversation is overdue. Consider putting them on card on file before the next renewal.',
            'onsite_visit' => 'Warehouse scanner and network check',
            'recurring_ticket' => 'Monthly maintenance visit - Pinnacle Freight',
            'project' => null,
            'vendors' => [
                ['Mahoning Valley Fiber', 'Warehouse and office circuits', '330-555-0147', 'mahoningfiber.example', '24/7 with 2 hour response', 'Good support, ask for the business desk rather than the general line.'],
                ['Terminal Logistics Software', 'Dispatch and tracking platform', '330-555-0149', 'terminallogistics.example', '24/7 support', 'Runs on a VM we host. They connect in for updates - access is logged.'],
            ],
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 5, 'Access Point' => 9, 'Server' => 3, 'Virtual Machine' => 2, 'Desktop' => 16, 'Laptop' => 6, 'Printer' => 5, 'Phone' => 12, 'Tablet' => 8],
            'people' => [
                ['Desmond Iverson', 'General Manager', 'Management', 1, 0, 0, 'Approves spend and downtime.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Lorraine Pike', 'Accounts Payable', 'Finance', 0, 1, 0, 'Pays on their own schedule - chase politely.', ['Billing']],
                ['Ike Ferrante', 'Warehouse Systems Lead', 'Operations', 1, 0, 1, 'Night shift contact for scanner and label printer problems.', ['Technical', 'After Hours', 'Onsite Point of Contact']],
                ['Simone Vasser', 'Dispatch Supervisor', 'Operations', 0, 0, 0, 'Raises most of the day to day tickets.', ['Emergency']],
            ],
        ],
        [
            'name' => 'Copperline Coffee Roasters', 'abbreviation' => 'CCR', 'type' => 'Retail',
            'domain' => 'copperlinecoffee.example', 'referral' => 'Event', 'rate' => 120.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15201', 'area' => '412', 'street' => '2115 Butler Street',
            'second_site' => ['Roastery', '48 Preble Avenue', 'Pittsburgh', 'PA', '15233'],
            'age_days' => 180, 'seats' => 9, 'servers' => 0, 'build' => 1, 'billing' => 'break_fix',
            'balance' => 'clear', 'hosting' => [],
            'lead' => 1, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Prospect', 'Break Fix', 'Multi Site', 'Onboarding'],
            'notes' => 'Two retail sites and a roastery. Point of sale and card processing are the only things that really matter to them.',
            'account_note' => 'Still a lead on paper. Break fix work is how we are proving the value before proposing an agreement.',
            'review_note' => 'Proposal for a light agreement is drafted. Owner wants to see a quiet month first.',
            'onsite_visit' => 'Point of sale and network check',
            'recurring_ticket' => 'Monthly maintenance visit - Copperline Coffee',
            'project' => null,
            'vendors' => [
                ['Steel City Point of Sale', 'Till system and card terminals', '412-555-0198', 'steelcitypos.example', '8x5 support', 'Card terminals are theirs. Anything past the network handoff is their problem, not ours.'],
            ],
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 2, 'Access Point' => 4, 'Desktop' => 3, 'Laptop' => 2, 'Tablet' => 5, 'Printer' => 2],
            'people' => [
                ['Jonah Rickerby', 'Owner', 'Management', 1, 1, 0, 'Hands on owner - usually behind the counter.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Mira Lachlan', 'Operations Manager', 'Operations', 1, 1, 1, 'Runs both sites and the roastery.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Felix Oyelaran', 'Store Lead - Butler Street', 'Retail', 0, 0, 0, 'Front of house point of contact.', []],
                ['Suzanne Kirby', 'Roastery Supervisor', 'Production', 0, 0, 0, 'Reports issues from the roastery.', []],
            ],
        ],
        [
            'name' => 'Larkspur Community Church', 'abbreviation' => 'LCC', 'type' => 'Non Profit',
            'domain' => 'larkspurchurch.example', 'referral' => 'Friend', 'rate' => 95.00, 'terms' => 30,
            'city' => 'Butler', 'state' => 'PA', 'zip' => '16001', 'area' => '724', 'street' => '318 Cedar Lane',
            'second_site' => null,
            'age_days' => 760, 'seats' => 8, 'servers' => 1, 'build' => 1, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [['Web Hosting - Standard', 1, 25.00, 'Church website and service times, hosted with nightly backup', 0], ['Email Hosting - Per Mailbox', 8, 4.50, 'Staff and volunteer mailboxes with filtering', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Managed', 'Non Profit', 'Block Hours'],
            'notes' => 'Non profit rate. Mostly volunteers on the machines, so the build is locked down and simple.',
            'account_note' => 'Charity pricing agreed at onboarding. Anything chargeable needs the treasurer to sign off first.',
            'review_note' => 'Moved off a consumer mail account onto hosted mail with us. Far fewer password problems since.',
            'onsite_visit' => 'Office and media desk check',
            'recurring_ticket' => 'Monthly maintenance visit - Larkspur Church',
            'project' => null,
            'vendors' => [
                ['Butler Valley Internet', 'Building internet connection', '724-555-0181', 'butlervalley.example', 'Best effort', 'Residential grade line. No SLA - expect to wait.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 1, 'Access Point' => 3, 'Server' => 1, 'Desktop' => 5, 'Laptop' => 3, 'Printer' => 2],
            'people' => [
                ['Reverend Alan Petrie', 'Pastor', 'Management', 1, 0, 0, 'Prefers a phone call. Not technical, and does not need to be.', ['Primary', 'Executive']],
                ['Harriet Dunmore', 'Church Treasurer', 'Finance', 1, 1, 0, 'Approves all spend, however small.', ['Billing', 'Authorized Approver']],
                ['Tomas Okonjo', 'Volunteer Coordinator', 'Operations', 0, 0, 1, 'Runs the media desk and knows where everything is plugged in.', ['Technical', 'Onsite Point of Contact']],
                ['Nancy Bell', 'Office Administrator', 'Administration', 0, 0, 0, 'In the office three mornings a week.', []],
            ],
        ],
        [
            'name' => 'Verity Insurance Agency', 'abbreviation' => 'VIA', 'type' => 'Insurance',
            'domain' => 'verityinsurance.example', 'referral' => 'Partner', 'rate' => 155.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15205', 'area' => '412', 'street' => '4700 Campbells Run Road',
            'second_site' => null,
            'age_days' => 1120, 'seats' => 20, 'servers' => 2, 'build' => 0, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [],
            'lead' => 0, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => 'DigiCert',
            'tags' => ['Managed', 'Compliance', 'Cyber Insurance', 'Key Account'],
            'notes' => 'Carrier portals drive everything. Browser and certificate problems are most of the ticket volume.',
            'account_note' => 'They sell cyber cover themselves, so their own posture gets scrutinised. Keep the evidence current.',
            'review_note' => 'Passed their own carrier audit last quarter with no findings. Worth repeating the same evidence pack.',
            'onsite_visit' => 'Office equipment and carrier portal check',
            'recurring_ticket' => 'Monthly maintenance visit - Verity Insurance',
            'project' => null,
            'vendors' => [
                ['Allegheny Business Fiber', 'Office internet and failover', '412-555-0158', 'alleghenyfiber.example', '4 hour response', 'Primary fibre with a 4G backup that has never been tested under load.'],
                ['Carrier Gateway Systems', 'Insurance carrier portal aggregator', '412-555-0159', 'carriergateway.example', '8x5 support', 'Browser fussy. Keep one machine on the known good version.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 3, 'Server' => 2, 'Desktop' => 15, 'Laptop' => 5, 'Printer' => 3, 'Phone' => 14],
            'people' => [
                ['Gordon Verity', 'Principal Agent', 'Management', 1, 1, 0, 'Owner. Wants to hear about problems before clients do.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Marcy Threlfall', 'Office Manager', 'Administration', 1, 1, 1, 'Runs the office and holds the carrier portal logins.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Petra Lindberg', 'Commercial Lines Agent', 'Sales', 0, 0, 0, 'Heavy portal user - first to notice a broken certificate.', []],
                ['Sean Coughlin', 'Claims Handler', 'Operations', 0, 0, 0, 'Works two days a week from home.', ['After Hours']],
            ],
        ],
        [
            'name' => 'Ironbridge Architects', 'abbreviation' => 'IBA', 'type' => 'Architecture',
            'domain' => 'ironbridgearch.example', 'referral' => 'Client', 'rate' => 160.00, 'terms' => 30,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15203', 'area' => '412', 'street' => '1900 East Carson Street',
            'second_site' => null,
            'age_days' => 980, 'seats' => 14, 'servers' => 2, 'build' => 1, 'billing' => 'managed',
            'balance' => 'current', 'hosting' => [['Nextcloud Hosting - Instance', 1, 95.00, 'Managed Nextcloud instance for drawing sets and client sharing', 0], ['Nextcloud Hosting - Per User', 14, 6.50, 'Per user sync and external sharing with expiring links', 0], ['Offsite Backup Storage - Self Hosted', 4000, 0.09, 'Offsite storage for the drawing archive, per GB', 0]],
            'lead' => 0, 'favorite' => 1, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Managed', 'Key Account', 'Compliance'],
            'notes' => 'Enormous drawing files. Storage growth and sharing with external consultants drive most of the work.',
            'account_note' => 'Practice partners approve spend jointly. Do not act on one partner alone for anything over $1000.',
            'review_note' => 'Archive crossed four terabytes this year. Storage expansion is quoted and waiting on a decision.',
            'onsite_visit' => 'Studio workstation and storage check',
            'recurring_ticket' => 'Monthly maintenance visit - Ironbridge Architects',
            'project' => ['Drawing archive storage expansion', 'Expand the storage pool and restructure the drawing archive so the older projects move to slower storage without breaking links.'],
            'vendors' => [
                ['South Side Fibre', 'Studio internet - symmetric line for large uploads', '412-555-0193', 'southsidefibre.example', '4 hour response', 'Symmetric line chosen for upload speed. Do not let anyone downgrade it.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 3, 'Server' => 2, 'Virtual Machine' => 3, 'Desktop' => 12, 'Laptop' => 5, 'Printer' => 2, 'Phone' => 8],
            'people' => [
                ['Yvette Corrigan', 'Managing Partner', 'Management', 1, 1, 0, 'Signs off jointly with the other partner.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Hugh Barrowman', 'Partner', 'Management', 1, 0, 0, 'Second signature on anything significant.', ['Executive', 'Authorized Approver']],
                ['Ines Talavera', 'Studio Manager', 'Operations', 1, 1, 1, 'Manages the file structure and the external sharing.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Rory Dennehy', 'Architectural Technologist', 'Design', 0, 0, 0, 'Pushes the workstations hardest.', []],
            ],
        ],
        [
            'name' => 'Blue Mountain Brewing Co', 'abbreviation' => 'BMB', 'type' => 'Food and Beverage',
            'domain' => 'bluemountainbrew.example', 'referral' => 'Event', 'rate' => 135.00, 'terms' => 15,
            'city' => 'Latrobe', 'state' => 'PA', 'zip' => '15650', 'area' => '724', 'street' => '55 Brewery Road',
            'second_site' => ['Taproom', '210 Main Street', 'Ligonier', 'PA', '15658'],
            'age_days' => 560, 'seats' => 17, 'servers' => 1, 'build' => 0, 'billing' => 'managed',
            'balance' => 'current', 'hosting' => [['Web Hosting - Business', 1, 65.00, 'Brewery website and online shop with a staging site', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Managed', 'Multi Site', 'Seasonal'],
            'notes' => 'Brewery plus a taproom twenty minutes away. Point of sale and the online shop matter more than anything on a desk.',
            'account_note' => 'Do not schedule anything disruptive on a Friday or over an event weekend.',
            'review_note' => 'Taproom wireless struggles when it is busy. Extra access point quoted for the patio.',
            'onsite_visit' => 'Brewery and taproom equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Blue Mountain Brewing',
            'project' => null,
            'vendors' => [
                ['Laurel Highlands Broadband', 'Connectivity at both sites', '724-555-0126', 'laurelhighlands.example', 'Next business day', 'Two accounts, one per site. Taproom line is the weaker of the two.'],
                ['Tapline Point of Sale', 'Till and online ordering platform', '724-555-0127', 'taplinepos.example', '8x5 support', 'They own the card terminals. Our responsibility stops at the network.'],
            ],
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 3, 'Access Point' => 6, 'Server' => 1, 'Desktop' => 6, 'Laptop' => 4, 'Tablet' => 7, 'Printer' => 3, 'Camera' => 5],
            'people' => [
                ['Dale Kovacs', 'Owner and Head Brewer', 'Management', 1, 0, 0, 'Usually in the brewhouse. Text rather than email.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Steph Ruane', 'General Manager', 'Operations', 1, 1, 1, 'Runs both sites day to day.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Marco Devlin', 'Taproom Manager', 'Retail', 0, 0, 0, 'Point of contact at the taproom, evenings and weekends.', ['After Hours']],
                ['Junie Alvarado', 'Events Coordinator', 'Sales', 0, 0, 0, 'Books the events that must not have a network problem.', []],
            ],
        ],
        [
            'name' => 'Summit Ridge Charter School', 'abbreviation' => 'SRC', 'type' => 'Education',
            'domain' => 'summitridgecs.example', 'referral' => 'Networking Group', 'rate' => 120.00, 'terms' => 30,
            'city' => 'Monroeville', 'state' => 'PA', 'zip' => '15146', 'area' => '412', 'street' => '2400 Haymaker Road',
            'second_site' => null,
            'age_days' => 1320, 'seats' => 38, 'servers' => 3, 'build' => 1, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [['Nextcloud Hosting - Instance', 1, 95.00, 'Staff and coursework file sharing', 0], ['Web Hosting - Standard', 1, 25.00, 'School website and newsletter archive', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Co-Managed', 'Managed', 'Non Profit', 'Seasonal', 'Compliance'],
            'notes' => 'Runs on open source deliberately - budget goes on staff, not licences. Quiet in July, chaotic in late August.',
            'account_note' => 'Everything big happens in the summer break. Nothing disruptive during term time, ever.',
            'review_note' => 'Linux desktop rollout finished last summer and cut the refresh budget substantially. Same again for the labs next year.',
            'onsite_visit' => 'Classroom and lab equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Summit Ridge Charter',
            'project' => ['Computer lab refresh', 'Rebuild both computer labs on the standard Linux image over the summer break, including the imaging server and the lab management tooling.'],
            'vendors' => [
                ['Monroeville Municipal Fibre', 'School district connection', '412-555-0136', 'monroevillefibre.example', '4 hour response', 'Filtered connection required by policy. Filtering is theirs, not ours.'],
                ['Cornerstone Student Records', 'Student information system', '412-555-0137', 'cornerstonesis.example', '8x5 support', 'Hosted by the vendor. We hold administrative access only.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 5, 'Access Point' => 10, 'Server' => 2, 'Virtual Machine' => 4, 'Desktop' => 28, 'Laptop' => 12, 'Printer' => 5, 'Tablet' => 10, 'Camera' => 6],
            'people' => [
                ['Dr Marisol Reyes', 'Principal', 'Management', 1, 0, 0, 'Approves anything affecting teaching time.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Wendell Frost', 'Business Manager', 'Finance', 0, 1, 0, 'Purchase orders and the grant paperwork.', ['Billing']],
                ['Ada Kowalczyk', 'Technology Coordinator', 'Operations', 1, 0, 1, 'Internal counterpart - handles first line and the classroom kit.', ['Technical', 'Onsite Point of Contact']],
                ['Byron Mensah', 'Facilities Lead', 'Facilities', 0, 0, 0, 'Building access outside school hours.', ['Emergency']],
            ],
        ],
        [
            'name' => 'Halcyon Physical Therapy', 'abbreviation' => 'HPT', 'type' => 'Healthcare',
            'domain' => 'halcyonpt.example', 'referral' => 'Website', 'rate' => 130.00, 'terms' => 15,
            'city' => 'Cranberry Township', 'state' => 'PA', 'zip' => '16066', 'area' => '724', 'street' => '20510 Route 19',
            'second_site' => null,
            'age_days' => 240, 'seats' => 11, 'servers' => 1, 'build' => 0, 'billing' => 'managed',
            'balance' => 'clear', 'hosting' => [['Email Hosting - Per Mailbox', 11, 4.50, 'Practice mailboxes with filtering and mobile sync', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Managed', 'Onboarding', 'Compliance'],
            'notes' => 'Recently onboarded. Scheduling and notes run in a hosted clinical system, so the local footprint is small.',
            'account_note' => 'Practice owner is the only approver and is treating patients most of the day. Email, do not call.',
            'review_note' => 'Onboarding turned up an unpatched machine still on an unsupported operating system. Replaced, not repaired.',
            'onsite_visit' => 'Clinic equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Halcyon Physical Therapy',
            'project' => null,
            'vendors' => [
                ['Cranberry Connect', 'Clinic internet', '724-555-0168', 'cranberryconnect.example', 'Next business day', 'Single line. Backup connection quoted and declined.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 1, 'Access Point' => 3, 'Server' => 1, 'Desktop' => 8, 'Laptop' => 2, 'Printer' => 2, 'Tablet' => 4],
            'people' => [
                ['Dr Owen Beckwith', 'Practice Owner', 'Management', 1, 1, 0, 'Treating patients most of the day. Email is the reliable route.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Lena Moradi', 'Practice Coordinator', 'Administration', 1, 1, 1, 'First to call and the one who knows what actually happened.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Cal Prentice', 'Physical Therapist', 'Clinical', 0, 0, 0, 'Uses the treatment room tablets.', []],
                ['Wren Sutcliffe', 'Front Desk', 'Reception', 0, 0, 0, 'Reception and scheduling.', []],
            ],
        ],
        [
            'name' => 'Fieldstone Landscaping', 'abbreviation' => 'FSL', 'type' => 'Field Services',
            'domain' => 'fieldstonescapes.example', 'referral' => 'Client', 'rate' => 125.00, 'terms' => 30,
            'city' => 'Gibsonia', 'state' => 'PA', 'zip' => '15044', 'area' => '724', 'street' => '4820 William Flynn Highway',
            'second_site' => null,
            'age_days' => 430, 'seats' => 9, 'servers' => 0, 'build' => 0, 'billing' => 'break_fix',
            'balance' => 'clear', 'hosting' => [['Web Hosting - Standard', 1, 25.00, 'Company website and quote request form', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Break Fix', 'Seasonal', 'Block Hours'],
            'notes' => 'Almost everything is mobile. Office is a portacabin at the yard with one machine and a printer.',
            'account_note' => 'Flat out March to October, quiet all winter. Do the project work in January.',
            'review_note' => 'Phones and tablets take a beating on site. Cases and screen protectors have paid for themselves twice over.',
            'onsite_visit' => 'Yard office equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Fieldstone Landscaping',
            'project' => null,
            'vendors' => [
                ['North Hills Wireless', 'Yard connectivity and crew data plans', '724-555-0143', 'northhillswireless.example', 'Best effort', 'Pooled data across the crew handsets. Check the overage in summer.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Access Point' => 2, 'Desktop' => 2, 'Laptop' => 3, 'Printer' => 1, 'Tablet' => 6, 'Mobile Phone' => 9],
            'people' => [
                ['Craig Fieldstone', 'Owner', 'Management', 1, 1, 0, 'On a job most days. Best reached early morning.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Bonnie Achebe', 'Office Manager', 'Administration', 1, 1, 1, 'Runs the yard office and the scheduling.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Nate Lindqvist', 'Crew Lead', 'Operations', 0, 0, 0, 'Reports broken handsets and tablets.', []],
                ['Rosa Ferreira', 'Estimator', 'Sales', 0, 0, 0, 'Works off a laptop from the truck.', []],
            ],
        ],
        [
            'name' => 'Corbin Machine Works', 'abbreviation' => 'CMW', 'type' => 'Manufacturing',
            'domain' => 'corbinmachine.example', 'referral' => 'Cold Outreach', 'rate' => 150.00, 'terms' => 30,
            'city' => 'New Castle', 'state' => 'PA', 'zip' => '16101', 'area' => '724', 'street' => '900 Industrial Boulevard',
            'second_site' => null,
            'age_days' => 1400, 'seats' => 24, 'servers' => 2, 'build' => 1, 'billing' => 'managed',
            'balance' => 'past_due', 'hosting' => [['Virtual Server Hosting', 2, 55.00, 'Two hosted guests for the file server and the licence server', 0], ['Offsite Backup Storage - Self Hosted', 1500, 0.09, 'Offsite backup for the machine programs, per GB', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Microsoft 365', 'cert_issuer' => 'DigiCert',
            'tags' => ['Managed', 'After Hours Support', 'Past Due', 'At Risk'],
            'notes' => 'Machine shop with CNC controllers on an isolated network. Losing the program files would stop the shop.',
            'account_note' => 'Payment has slipped twice this year. Owner deals with it personally when chased.',
            'review_note' => 'Controllers are on unsupported operating systems and cannot be patched. Isolated at the firewall and documented as accepted risk.',
            'onsite_visit' => 'Shop floor and controller check',
            'recurring_ticket' => 'Monthly maintenance visit - Corbin Machine Works',
            'project' => null,
            'vendors' => [
                ['Lawrence County Fibre', 'Shop internet', '724-555-0171', 'lawrencefibre.example', 'Next business day', 'Reliable line. Support desk is small but good.'],
                ['Precision Controls Group', 'CNC controller support', '724-555-0172', 'precisioncontrols.example', '8x5 support', 'They will not certify anything newer. Isolation is the mitigation.'],
            ],
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 4, 'Access Point' => 4, 'Server' => 2, 'Virtual Machine' => 3, 'Desktop' => 18, 'Laptop' => 4, 'Printer' => 3, 'Phone' => 10, 'Camera' => 4],
            'people' => [
                ['Walter Corbin', 'Owner', 'Management', 1, 1, 0, 'Second generation owner. Hands on and blunt.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Gail Prosser', 'Bookkeeper', 'Finance', 0, 1, 0, 'Part time. Invoices sometimes sit until she is in.', ['Billing', 'Past Due']],
                ['Dmitri Vasiliev', 'Shop Foreman', 'Operations', 1, 0, 1, 'Knows the controllers and the program library.', ['Technical', 'Onsite Point of Contact', 'After Hours']],
                ['Trina Boyle', 'Quality Inspector', 'Quality', 0, 0, 0, 'Owns the measurement machine and its very old software.', []],
            ],
        ],
        [
            'name' => 'Thistle and Vine Restaurant Group', 'abbreviation' => 'TVR', 'type' => 'Hospitality',
            'domain' => 'thistleandvine.example', 'referral' => 'Social Media', 'rate' => 130.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15206', 'area' => '412', 'street' => '5730 Penn Avenue',
            'second_site' => ['Second Restaurant', '1120 Freeport Road', 'Fox Chapel', 'PA', '15238'],
            'age_days' => 320, 'seats' => 13, 'servers' => 0, 'build' => 0, 'billing' => 'break_fix',
            'balance' => 'current', 'hosting' => [['Web Hosting - Business', 1, 65.00, 'Two restaurant sites with menus and booking widget', 0], ['Email Hosting - Per Mailbox', 6, 4.50, 'Management mailboxes', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'Allegheny County Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Break Fix', 'Multi Site', 'Prospect'],
            'notes' => 'Two restaurants. Point of sale, card terminals and the booking system are the only things they care about.',
            'account_note' => 'Never attend during service. Mornings before eleven or after close, nothing in between.',
            'review_note' => 'Third till problem this quarter, all network related. Agreement proposal is drafted and waiting.',
            'onsite_visit' => 'Point of sale and network check',
            'recurring_ticket' => 'Monthly maintenance visit - Thistle and Vine',
            'project' => null,
            'vendors' => [
                ['Penn Avenue Broadband', 'Connectivity at both restaurants', '412-555-0189', 'pennavebroadband.example', 'Best effort', 'Consumer grade at both sites. This is the root of most of their problems.'],
                ['Covermark Hospitality Systems', 'Point of sale and booking platform', '412-555-0190', 'covermarkpos.example', '24/7 support', 'Good support line. Call them first for anything till specific.'],
            ],
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 2, 'Access Point' => 5, 'Desktop' => 3, 'Laptop' => 2, 'Tablet' => 8, 'Printer' => 3],
            'people' => [
                ['Isla Brennan', 'Owner', 'Management', 1, 1, 0, 'Runs both restaurants. Reachable between services.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Duncan Reyes', 'Operations Manager', 'Operations', 1, 1, 1, 'The one who actually calls us.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Priya Anand', 'General Manager - Penn Avenue', 'Retail', 0, 0, 0, 'Front of house at the original site.', ['After Hours']],
                ['Tomasz Wierzbicki', 'General Manager - Fox Chapel', 'Retail', 0, 0, 0, 'Front of house at the second site.', ['After Hours']],
            ],
        ],
        [
            'name' => 'Ashford Legal Services', 'abbreviation' => 'ALS', 'type' => 'Legal',
            'domain' => 'ashfordlegal.example', 'referral' => 'Partner', 'rate' => 165.00, 'terms' => 30,
            'city' => 'Wheeling', 'state' => 'WV', 'zip' => '26003', 'area' => '304', 'street' => '1100 Main Street',
            'second_site' => null,
            'age_days' => 620, 'seats' => 6, 'servers' => 0, 'build' => 1, 'billing' => 'hosting',
            'balance' => 'clear', 'hosting' => [['Web Hosting - Business', 1, 65.00, 'Firm website with staging and priority restore', 0], ['Email Hosting - Per Mailbox', 6, 4.50, 'Six mailboxes with filtering and archiving', 0], ['Mail Relay and Filtering', 6, 2.00, 'Inbound filtering and outbound relay', 0], ['Nextcloud Hosting - Instance', 1, 95.00, 'Client file exchange in place of email attachments', 0]],
            'lead' => 0, 'favorite' => 0, 'tax' => 'PA Sales Tax',
            'mail_platform' => 'Google Workspace', 'cert_issuer' => "Let's Encrypt",
            'tags' => ['Hosting', 'Compliance'],
            'notes' => 'Hosting only. They have their own IT person for the desktops - we run the website, the mail and the file exchange.',
            'account_note' => 'Scope is hosting and nothing else. Desktop requests get politely redirected to their own IT contact.',
            'review_note' => 'Asked twice about taking on the endpoints. Worth a proposal at renewal.',
            'onsite_visit' => 'Annual hosting review',
            'recurring_ticket' => 'Monthly hosting check - Ashford Legal',
            'project' => null,
            'vendors' => [
                ['Ohio Valley Telecom', 'Office internet', '304-555-0152', 'ohiovalleytel.example', 'Next business day', 'Their line, not our responsibility. Listed for context only.'],
            ],
            'fleet' => ['Virtual Machine' => 3, 'Firewall/Router' => 1, 'Desktop' => 2],
            'people' => [
                ['Cordelia Ashford', 'Principal Attorney', 'Management', 1, 1, 0, 'Owner. Only wants to hear about outages and invoices.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Bertram Vance', 'Office Administrator', 'Administration', 1, 1, 0, 'Handles the invoices and the mailbox requests.', ['Billing']],
                ['Nils Andersen', 'Their IT Contact', 'Operations', 0, 0, 1, 'Their own IT person. Owns the desktops - we own the hosting.', ['Technical', 'Onsite Point of Contact']],
                ['Fiona Larkin', 'Paralegal', 'Legal', 0, 0, 0, 'Heaviest user of the client file exchange.', []],
            ],
        ],
    ];
}

// ------------------------------
// demoDataProfiles
// ------------------------------
function demoDataProfiles() {
    $profiles = [];
    foreach (demoDataSpecs() as $spec) {
        $profiles[] = demoExpandProfile($spec);
    }
    return $profiles;
}

// ------------------------------
// demoExpandProfile
// Turns a compact client spec into the full profile the builders consume. The
// documentation, agreement lines and quotes are derived from the spec so the
// numbers hang together - seat counts match licence counts match invoice lines,
// and a Linux house gets a Linux stack rather than a Microsoft one.
// ------------------------------
function demoExpandProfile($spec) {

    $name = $spec['name'];
    $short = $spec['abbreviation'];
    $domain = $spec['domain'];
    $seats = $spec['seats'];
    $servers = $spec['servers'];
    $mail = $spec['mail_platform'];
    $foss = $spec['build'] === 1;
    $phone = $spec['area'] . '-555-0100';
    $breakfix = $spec['billing'] === 'break_fix';

    // Sites
    $sites = [[
        'name' => 'Main Office',
        'description' => 'Primary site - server room, main office space and reception',
        'address' => $spec['street'], 'city' => $spec['city'], 'state' => $spec['state'], 'zip' => $spec['zip'],
        'phone' => $phone, 'hours' => 'Mon-Fri 8:00 - 17:00',
        'notes' => 'Comms cupboard is off the back corridor. Key is held by the onsite point of contact.',
        'tags' => ['Head Office', 'Server Room'],
    ]];
    if (!empty($spec['second_site'])) {
        $sites[] = [
            'name' => $spec['second_site'][0],
            'description' => 'Secondary site connected back to the main office',
            'address' => $spec['second_site'][1], 'city' => $spec['second_site'][2],
            'state' => $spec['second_site'][3], 'zip' => $spec['second_site'][4],
            'phone' => $spec['area'] . '-555-0101', 'hours' => 'Mon-Fri 8:00 - 17:00',
            'notes' => 'Linked to the main office over a site to site VPN on the edge firewalls.',
            'tags' => ['Branch'],
        ];
    }

    // Networks
    $networks = [
        ['Office LAN', 'Staff workstations, printers and handsets', 10, '192.168.10.0', '192.168.10.1', '192.168.10.1', '1.1.1.1', '192.168.10.50 - 192.168.10.220', 'Static addressing above .240 is reserved and documented in the IP list.'],
        ['Guest Wireless', 'Isolated guest access - no route to the office LAN', 40, '192.168.40.0', '192.168.40.1', '1.1.1.1', '9.9.9.9', '192.168.40.20 - 192.168.40.200', 'Client isolation is on. Password is rotated at each quarterly visit.'],
    ];
    if ($servers > 1) {
        $networks[] = ['Server VLAN', 'Hypervisors, storage and management interfaces', 20, '192.168.20.0', '192.168.20.1', '192.168.20.1', '1.1.1.1', 'Static addressing only', 'No DHCP. Every address here is recorded against the asset.'];
    }
    if (in_array($spec['type'], ['Manufacturing', 'Logistics'])) {
        $networks[] = ['Equipment VLAN', 'Shop floor and warehouse equipment - no internet access', 30, '192.168.30.0', '192.168.30.1', '192.168.30.1', '1.1.1.1', '192.168.30.40 - 192.168.30.240', 'Outbound internet is blocked at the firewall by design. Do not "fix" this.'];
    }

    // Credentials - names line up with demoAssetCredentialName so the vault
    // entries attach to the kit they actually open
    $mail_admin_uri = $mail === 'Google Workspace' ? 'https://admin.google.com' : 'https://admin.microsoft.com';
    $credentials = [
        ['Firewall Administrator', 'Edge firewall management interface', 'https://192.168.10.1', 'msp-admin', 'Local administrator on the edge firewall. Rotated at every quarterly review.', ['Firewall', 'Local Admin', 'Rotate Quarterly'], 1],
        [$mail . ' Administrator', 'Tenant administration for mail and identity', $mail_admin_uri, 'msp-admin@' . $domain, 'Break glass administrator. MFA enforced - the token lives with the on call phone.', ['Microsoft 365', 'Domain Admin', 'Break Glass'], 1],
        ['Hypervisor and Storage Administrator', $foss ? 'Proxmox VE and TrueNAS management' : 'Hypervisor and NAS management', 'https://192.168.20.10:8006', 'msp-admin', 'Root equivalent. Use the individual accounts for day to day work and this only for maintenance.', ['Hypervisor', 'Local Admin', 'Break Glass'], 0],
        ['Network Controller', 'UniFi controller for switching and wireless', 'https://192.168.10.12', 'msp-' . strtolower($short), 'Controller runs on the management stack, not on site.', ['Switch', 'Wireless', 'Shared'], 0],
        ['Backup Console', $foss ? 'Proxmox Backup Server and Synology Active Backup' : 'Backup platform for servers and endpoints', 'https://backup.example', 'msp-' . strtolower($short), 'Console login for restore requests and job monitoring.', ['Backup Console', 'Service Account'], 0],
        ['Registrar and DNS', 'Domain registrar and DNS control panel', 'https://registrar.example', 'billing@' . $domain, 'Registrar account is in the client name with us as technical contact.', ['Registrar and DNS', 'Shared'], 0],
    ];

    // Services - the last two entries say which asset types run it and which
    // credential opens it
    $backup_service = $foss
        ? ['Proxmox Backup Server and Synology Active Backup', 'Nightly image and file backup with an offsite copy', 'Backup', 'High', 'Offsite replication to the management stack', 'Nightly job with a monthly test restore. Evidence is filed against the client documentation.', ['Server', 'Virtual Machine'], 'Backup Console']
        : ['Managed Backup', 'Image level backup of ' . max(1, $servers) . ' servers with offsite copy', 'Backup', 'High', 'Offsite replication', 'Nightly job with a monthly test restore. Evidence is filed against the client documentation.', ['Server', 'Virtual Machine'], 'Backup Console'];

    $services = [
        [$mail, 'Company mail, calendars and identity for ' . $seats . ' users', 'Email', 'High', 'Third party mailbox backup', 'Tenant administered by us. Licence count is reconciled against the monthly invoice.', ['Virtual Machine'], $mail . ' Administrator'],
        $backup_service,
        ['Edge Firewall and Site VPN', 'Perimeter filtering, DNS filtering and the site to site tunnel', 'Network', 'High', 'Nightly config export', 'Config backup is pulled nightly. Rule changes go through a ticket, never verbally.', ['Firewall/Router'], 'Firewall Administrator'],
        ['Endpoint Protection and Monitoring', 'Agent based protection and monitoring across the fleet', 'Security', 'High', 'Vendor cloud console', 'Alerts raise tickets automatically. Patch compliance is reviewed at the maintenance visit.', ['Desktop', 'Laptop', 'Server'], 'Network Controller'],
    ];
    if ($foss) {
        $services[] = ['Nextcloud File Sync and Share', 'Company file storage, sync and external sharing for ' . $seats . ' users', 'Web', 'High', 'Included in the nightly backup job', 'Replaced the old mapped drive setup. External shares expire after 30 days by policy.', ['Virtual Machine', 'Server'], 'Hypervisor and Storage Administrator'];
    }

    // Anything we host for them ourselves, on our own infrastructure
    foreach ($spec['hosting'] as $hosting) {
        if (strpos($hosting[0], 'Web Hosting') === 0) {
            $services[] = ['Website Hosting', 'Public website hosted on our infrastructure', 'Web', 'Medium', 'Nightly snapshot with 30 day retention', 'Hosted on our own stack. TLS renews automatically at the reverse proxy.', ['Virtual Machine'], 'Registrar and DNS'];
        }
        if (strpos($hosting[0], 'Email Hosting') === 0) {
            $services[] = ['Mailbox Hosting', 'Mail hosting for ' . $seats . ' mailboxes with filtering', 'Email', 'High', 'Mailbox level backup, 12 month retention', 'Hosted mail rather than a third party tenant. Filtering and relay are ours.', ['Virtual Machine'], 'Registrar and DNS'];
        }
        if (strpos($hosting[0], 'Nextcloud Hosting') === 0) {
            $services[] = ['Nextcloud Hosting', 'Managed Nextcloud instance for ' . $seats . ' users', 'Web', 'High', 'Nightly backup with monthly restore test', 'Runs on our hosting stack. Updates are applied in the monthly maintenance window.', ['Virtual Machine'], 'Hypervisor and Storage Administrator'];
        }
    }

    // Software - the last two entries say which asset types it is installed on
    // and which credential administers it
    $software = [
        [$mail === 'Google Workspace' ? 'Google Workspace Business Standard' : 'Microsoft 365 Business Premium', 'Per user licensing resold and managed', 'Current', 'Software as a Service (SaaS)', 'Subscription', $seats, 'Licence count is reviewed monthly against staff numbers.', ['Desktop', 'Laptop'], $mail . ' Administrator'],
        ['Endpoint Protection', 'Managed endpoint protection agent', 'Current', 'Security Software', 'Subscription', $seats + max(1, $servers), 'Covers workstations, laptops and servers.', ['Desktop', 'Laptop', 'Server'], 'Network Controller'],
    ];
    if ($foss) {
        $software[] = ['LibreOffice', 'Office suite on the Linux fleet', '24.8', 'Productivity Suite', 'Open Source', $seats, 'Documents are saved as ODF by default, with Office formats used for anything going outside.', ['Desktop', 'Laptop'], ''];
        $software[] = ['Nextcloud', 'Self hosted file sync and share', '30', 'Web Application', 'Open Source', $seats, 'Runs in a container on the hypervisor. Updates are applied at the maintenance visit.', ['Virtual Machine'], 'Hypervisor and Storage Administrator'];
        $software[] = ['Proxmox Backup Server', 'Backup target for the virtual guests', '3.3', 'System Software', 'Subscription', max(1, $servers), 'Community repository on site, enterprise subscription on the management stack.', ['Server'], 'Backup Console'];
    } else {
        $software[] = ['Synology Active Backup for Business', 'Server, endpoint and mailbox backup', 'Current', 'System Software', 'Perpetual', $seats + max(1, $servers), 'Licence is included with the NAS. Agent is deployed to every endpoint.', ['Server', 'Desktop', 'Laptop'], 'Backup Console'];
    }
    $software[] = [$spec['vendors'][count($spec['vendors']) - 1][0] . ' Platform', $spec['vendors'][count($spec['vendors']) - 1][1], 'Vendor managed', 'Web Application', 'Subscription', $seats, 'Vendor supported - we hold the administrative access only.', ['Desktop'], ''];

    // Documentation. The last entry is whether the client can see it in the portal.
    $stack_line = $foss
        ? 'Proxmox VE hypervisor with a Synology NAS for file storage, Nextcloud for sync and share, and LibreOffice on the desktop fleet.'
        : 'Windows server estate with a Synology NAS running Active Backup for Business, and Microsoft 365 for mail and identity.';

    $documents = [
        [
            $short . ' - Site Runbook',
            'How this site is put together and what to do first when something breaks',
            '<h2>Site overview</h2><p>' . $name . ' operates from ' . count($sites) . ' site(s), with the main office at ' . $spec['street'] . ', ' . $spec['city'] . '. ' . $stack_line . '</p>'
                . '<h2>Network</h2><p>Office LAN on 192.168.10.0/24 behind the edge firewall. Guest wireless is isolated on VLAN 40 with no route to the office LAN. Static addresses are documented in the IP list against each network.</p>'
                . '<h2>Identity and mail</h2><p>' . $mail . ' tenant administered by us. Break glass credentials are held in the credential vault against this client.</p>'
                . '<h2>First response</h2><ol><li>Confirm whether the problem is site wide or one user.</li><li>Check the firewall WAN status first if more than one person is affected.</li><li>Check the monitoring console for alerts raised in the last hour.</li><li>Escalate to the vendor only after the internal checks are done.</li></ol>'
                . '<h2>Access</h2><p>Site access is arranged through the onsite point of contact. Work outside opening hours must be agreed in advance.</p>',
            0,
        ],
        [
            $short . ' - Backup and Recovery Plan',
            'What is protected, how often, and how to get it back',
            '<h2>Scope</h2><p>Image level backup of ' . max(1, $servers) . ' server(s) plus mailbox level protection for ' . $seats . ' users.</p>'
                . '<h2>Platform</h2><p>' . ($foss ? 'Proxmox Backup Server for the virtual guests, Synology Active Backup for Business for endpoints and mailboxes.' : 'Synology Active Backup for Business for servers, endpoints and mailboxes.') . '</p>'
                . '<h2>Schedule</h2><p>Nightly incremental with a weekly synthetic full. The offsite copy replicates daily.</p>'
                . '<h2>Retention</h2><p>30 days local, 12 months offsite.</p>'
                . '<h2>Restore procedure</h2><ol><li>Raise a ticket recording who asked and what is being restored.</li><li>Confirm the restore point with the client before overwriting anything.</li><li>Restore to an alternate location first where possible.</li><li>Record the result against this document and against the ticket.</li></ol>'
                . '<h2>Test restores</h2><p>Monthly. Evidence is attached to the maintenance ticket.</p>',
            0,
        ],
        [
            $short . ' - Vendors and Escalation',
            'Who to call, in what order, and what they are responsible for',
            '<h2>Escalation order</h2><ol><li>Confirm the fault and record it on a ticket.</li><li>Check the runbook for this site.</li><li>Escalate to the vendor responsible for the failing component.</li><li>Tell the client contact before the vendor call, not after.</li></ol>'
                . '<h2>Vendors</h2>' . implode('', array_map(function ($vendor) {
                    return '<p><strong>' . $vendor[0] . '</strong> - ' . $vendor[1] . '. Response target: ' . $vendor[4] . '.</p>';
                }, $spec['vendors']))
                . '<h2>Client approvals</h2><p>Spend and downtime are approved by the authorised approver on the contact list. Do not take verbal approval from anyone else for work that will be billed.</p>',
            0,
        ],
        [
            $short . ' - Getting Help',
            'Client facing - how to raise a ticket and what to expect',
            '<h2>Raising a ticket</h2><p>Email the service desk or use the portal. Include what you were doing, what happened, and the name of the machine if you know it.</p>'
                . '<h2>What happens next</h2><p>You will get an acknowledgement with a ticket number. Urgent issues are picked up first, and anything site wide is treated as urgent by default.</p>'
                . '<h2>Approvals</h2><p>Work that will be billed outside the agreement is quoted first and needs approval from an authorised approver before we start.</p>',
            1,
        ],
    ];

    // Response targets by priority
    $slas = [
        'Low' => 'Standard Support',
        'Medium' => 'Standard Support',
        'High' => in_array('After Hours Support', $spec['tags']) ? 'Critical Response' : 'Priority Support',
        'Urgent' => 'Critical Response',
    ];

    // The monthly agreement. Line format: name, quantity, price, description, taxable
    $agreement = [];
    $hosting_only = $spec['billing'] === 'hosting';

    if (!$breakfix && !$hosting_only) {
        $agreement[] = ['Managed Services - Per User', $seats, 89.00, 'Monitoring, patching, endpoint protection and remote support', 0];
        if ($servers > 0) {
            $agreement[] = ['Managed Services - Per Server', $servers, 145.00, 'Server monitoring, patching and backup verification', 0];
        }
        $agreement[] = [
            $mail === 'Google Workspace' ? 'Google Workspace Business Standard' : 'Microsoft 365 Business Premium',
            $seats, 22.00, 'Per user licence, resold and managed', 0,
        ];
        $agreement[] = ['Cloud Backup', max(1, $servers), 65.00, 'Offsite backup with monthly test restore', 0];
    }

    // Hosting is billed on its own lines whether or not they are managed - some
    // clients only ever buy hosting from us and nothing else
    foreach ($spec['hosting'] as $hosting) {
        $agreement[] = $hosting;
    }

    // Quotes in flight
    $refresh_count = max(2, (int)floor($seats / 5));
    $quotes = [
        ['Workstation refresh - machines past five years old', [
            ['Business Desktop', $refresh_count, 985.00, $foss ? 'Small form factor desktop supplied with Linux Mint and LibreOffice' : 'Small form factor desktop, 16GB memory, 512GB NVMe', 1],
            ['Deployment and Data Migration', $refresh_count, 145.00, 'Build, migrate and hand over per machine', 0],
            ['Recycling and Data Destruction', $refresh_count, 25.00, 'Certified disposal of the replaced machines', 0],
        ]],
        ['Uninterruptible power supply for the comms rack', [
            ['Rack Mount UPS 1500VA', 1, 685.00, 'Rack mount UPS with network management card', 1],
            ['Installation and Shutdown Configuration', 4, 145.00, 'Install, cable and configure graceful shutdown', 0],
        ]],
    ];
    if (!empty($spec['project'])) {
        $quotes[] = [$spec['project'][0], [
            ['Project Labor', 24, 145.00, 'Design, build, migration and handover', 0],
            ['Project Hardware', 1, 4250.00, 'Hardware supplied as part of the project', 1],
            ['Out of Hours Cutover', 6, 195.00, 'Cutover work outside operating hours', 0],
        ]];
    }

    // What this client costs us, and which supplier it is bought from
    $expenses = [
        ['Hardware supplied to ' . $name, 1450.00 + ($seats * 12), 'Hardware - Cost of Goods', 'Ridgeline Technology Distribution'],
        [$mail . ' licensing for ' . $name, $seats * 14.50, 'Licensing - Cost of Goods', 'Cascade Cloud Licensing'],
        ['Backup storage and licensing for ' . $name, 45.00 + (max(1, $servers) * 28), 'Cloud and Hosting', $foss ? 'Proxmox Server Solutions' : 'Synology Direct'],
    ];

    return [
        'name' => $name,
        'abbreviation' => $short,
        'type' => $spec['type'],
        'domain' => $domain,
        'referral' => $spec['referral'],
        'rate' => number_format($spec['rate'], 2, '.', ''),
        'net_terms' => $spec['terms'],
        'seats' => $seats,
        'servers' => $servers,
        'build' => $spec['build'],
        'billing' => $spec['billing'],
        'balance' => $spec['balance'],
        'hosting' => $spec['hosting'],
        'lead' => $spec['lead'],
        'favorite' => $spec['favorite'],
        'tax' => $spec['tax'],
        'notes' => $spec['notes'],
        'account_note' => $spec['account_note'],
        'review_note' => $spec['review_note'],
        'age_days' => $spec['age_days'],
        'area_code' => $spec['area'],
        'tags' => $spec['tags'],
        'sites' => $sites,
        'people' => $spec['people'],
        'vendors' => $spec['vendors'],
        'fleet' => $spec['fleet'],
        'networks' => $networks,
        'mail_platform' => $mail,
        'mail_servers' => $mail === 'Google Workspace' ? 'aspmx.l.google.example' : $domain . '.mail.protection.example',
        'web_host' => $foss ? 'Self hosted - Nextcloud and website on the hypervisor' : 'Vendor hosted',
        'dns_host' => 'harbourdns.example',
        'cert_issuer' => $spec['cert_issuer'],
        'credentials' => $credentials,
        'services' => $services,
        'software' => $software,
        'documents' => $documents,
        'slas' => $slas,
        'agreement' => $agreement,
        'quotes' => $quotes,
        'expenses' => $expenses,
        'onsite_visit' => $spec['onsite_visit'],
        'recurring_ticket' => $spec['recurring_ticket'],
        'project' => $spec['project'],
    ];

}
