<?php

/*
 * The kinds of record the Import window brings in (functions/import.php) -
 * kind => [
 *   title, one        "Contacts", "contact"
 *   icon, module      the window's icon; the permission it needs (Modify)
 *   client            imported into the client the window is opened from
 *   ready             a callable - why it can't import now, or ''
 *   about             what the window says about it, HTML
 *   fields            key => [label, also, required, about, example, skip,
 *                     detail (an asset detail, not in the template)]
 *   legacy            the old template's fields, by position
 *   name              the field (or fields, the first given) a row is named by
 *   key               the fields that make two rows the same record, the
 *                     first given, or a callable(row) giving the key
 *   find              callable(row, client_id) - [id, archived] of the one
 *                     already here, or [0, false]
 *   row               callable(row, client_id, existing_id, write) -
 *                     ['error' => why it can't import] or ['notes' => what
 *                     was left out, 'after' => callable run once every row
 *                     is in, giving more notes]
 *   finish            callable(result) - HTML to add to what an import says
 * ]
 * A row has only the fields the file has; updating, a field whose cell is
 * empty leaves what is there (importGiven()).
 */

function importKinds() {
    static $kinds = null;
    if ($kinds !== null) {
        return $kinds;
    }
    $f = function ($label, array $also = [], array $more = []) {
        return ['label' => $label, 'also' => $also] + $more;
    };
    $skip = ['skip' => true, 'about' => 'Not imported'];
    $yes = 'Yes, or leave it empty';
    $tags = $f('Tags', ['Tag'], ['about' => 'Separated by commas - any not yet a tag are added when an admin imports']);

    $kinds = [

        'clients' => [
            'title' => 'Clients', 'one' => 'client', 'icon' => 'fa-users', 'module' => 'module_client', 'client' => false,
            'about' => 'Each row is a client, with its primary location and primary contact. A client\'s other contacts and locations are imported from its own Contacts and Locations pages.',
            'fields' => [
                'client_name' => $f('Client Name', ['Name', 'Client', 'Company', 'Company Name'], ['required' => true, 'example' => 'Example Industries']),
                'client_type' => $f('Industry', ['Type'], ['example' => 'Manufacturing']),
                'client_referral' => $f('Referral', ['Referred By', 'Source'], ['about' => 'A referral not yet one is added']),
                'client_website' => $f('Website', ['Web', 'URL'], ['example' => 'example.com']),
                'client_lead' => $f('Lead', [], ['about' => $yes]),
                'client_abbreviation' => $f('Abbreviation', ['Short Name'], ['about' => 'Up to 6 letters - made from the name when left empty']),
                'client_currency_code' => $f('Currency', ['Currency Code'], ['about' => 'A 3-letter code, like USD - yours when left empty']),
                'client_tax_id_number' => $f('Tax ID', ['Tax Number', 'VAT Number', 'EIN']),
                'client_tax_rate' => $f('Tax Rate', [], ['about' => 'One of your tax rates, by name']),
                'client_tax_exempt' => $f('Tax Exempt', ['Exempt'], ['about' => $yes]),
                'client_tax_exempt_number' => $f('Exemption Certificate', ['Exemption Number', 'Certificate']),
                'client_tax_exempt_expires' => $f('Exemption Expires', [], ['about' => 'A date, YYYY-MM-DD']),
                'hourly_rate' => $f('Hourly Rate', ['Rate'], ['about' => 'New clients only - other than your default, it goes on a Standard terms contract']),
                'payment_terms' => $f('Payment Terms', ['Net Terms', 'Terms'], ['about' => 'Days - new clients only, as Hourly Rate']),
                'client_tags' => $tags,
                'client_notes' => $f('Notes'),
                'location_name' => $f('Primary Location Name', ['Location Name', 'Location'], ['about' => 'Primary when left empty']),
                'location_phone' => $f('Location Phone', ['Office Phone', 'Main Phone']),
                'location_address' => $f('Location Address', ['Address', 'Street'], ['example' => '123 Main St']),
                'location_city' => $f('City', [], ['example' => 'Pittsburgh']),
                'location_state' => $f('State', ['Province', 'Region'], ['example' => 'PA']),
                'location_zip' => $f('Postal Code', ['Zip', 'Zip Code', 'Postcode'], ['example' => '15212']),
                'location_country' => $f('Country'),
                'contact_name' => $f('Primary Contact Name', ['Contact Name', 'Contact'], ['example' => 'Jordan Lee']),
                'contact_title' => $f('Title', ['Job Title']),
                'contact_phone' => $f('Contact Phone', ['Phone']),
                'contact_extension' => $f('Extension', ['Ext']),
                'contact_mobile' => $f('Contact Mobile', ['Mobile', 'Cell']),
                'contact_email' => $f('Contact Email', ['Email', 'Email Address'], ['example' => 'jordan@example.com']),
            ],
            'legacy' => ['client_name', 'client_type', 'client_referral', 'client_website', 'location_name', 'location_phone', 'location_address', 'location_city',
                'location_state', 'location_zip', 'location_country', 'contact_name', 'contact_title', 'contact_phone', 'contact_extension', 'contact_mobile',
                'contact_email', 'hourly_rate', 'client_currency_code', 'payment_terms', 'client_tax_id_number', 'client_abbreviation'],
            'name' => 'client_name',
            'key' => ['client_name'],
            'find' => function (array $row) {
                return importFindBy('clients', 'client', 'client_name', $row['client_name'] ?? '');
            },
            'row' => 'importClientRow',
        ],

        'contacts' => [
            'title' => 'Contacts', 'one' => 'contact', 'icon' => 'fa-address-book', 'module' => 'module_client', 'client' => true,
            'about' => 'Each row is one of the client\'s contacts. One already here is found by its email, else its name.',
            'fields' => [
                'name' => $f('Name', ['Full Name', 'Display Name', 'Contact Name', 'Contact'], ['required' => true, 'example' => 'Jordan Lee']),
                'title' => $f('Title', ['Job Title'], ['example' => 'Office Manager']),
                'department' => $f('Department', ['Department Name']),
                'email' => $f('Email', ['Email Address', 'E-mail'], ['example' => 'jordan@example.com']),
                'phone' => $f('Phone', ['Office Phone', 'Work Phone', 'Phone Number'], ['example' => '412-555-0100']),
                'extension' => $f('Ext', ['Extension', 'Office Extension']),
                'mobile' => $f('Mobile', ['Mobile Phone', 'Cell', 'Cell Phone']),
                'location' => $f('Location', ['Office Location', 'Office', 'Site'], ['about' => 'One of the client\'s locations, by name']),
                'primary' => $f('Primary', ['Primary Contact'], ['about' => $yes]),
                'important' => $f('Important', [], ['about' => $yes]),
                'billing' => $f('Billing', [], ['about' => $yes]),
                'technical' => $f('Technical', [], ['about' => $yes]),
                'tags' => $tags,
                'notes' => $f('Notes'),
                'client_name' => $f('Client') + $skip,
            ],
            'legacy' => ['name', 'title', 'department', 'email', 'phone', 'extension', 'mobile', 'location'],
            'name' => ['name', 'email'],
            'key' => ['email', 'name'],
            'find' => function (array $row, $client_id) {
                // By email - else by name, one with no email or the same one
                $found = importFindBy('contacts', 'contact', 'contact_email', $row['email'] ?? '', "contact_client_id = $client_id");
                if ($found[0] || ($row['email'] ?? '') !== '' && !array_key_exists('name', $row)) {
                    return $found;
                }
                $email = escapeSql((string) ($row['email'] ?? ''));
                return importFindBy('contacts', 'contact', 'contact_name', $row['name'] ?? '', "contact_client_id = $client_id AND (contact_email = '' OR contact_email IS NULL OR '$email' = '')");
            },
            'row' => 'importContactRow',
        ],

        'locations' => [
            'title' => 'Locations', 'one' => 'location', 'icon' => 'fa-map-marker-alt', 'module' => 'module_client', 'client' => true,
            'about' => 'Each row is one of the client\'s locations, found by its name. A client with no primary location gets the first one as its primary.',
            'fields' => [
                'name' => $f('Name', ['Location Name', 'Location', 'Site'], ['required' => true, 'example' => 'Head Office']),
                'primary' => $f('Primary', ['Primary Location'], ['about' => $yes]),
                'description' => $f('Description'),
                'address' => $f('Address', ['Street', 'Address 1', 'Street Address'], ['example' => '123 Main St']),
                'city' => $f('City', [], ['example' => 'Pittsburgh']),
                'state' => $f('State', ['Province', 'Region'], ['example' => 'PA']),
                'zip' => $f('Postal Code', ['Zip', 'Zip Code', 'Postcode'], ['example' => '15212']),
                'country' => $f('Country'),
                'phone' => $f('Phone', ['Phone Number', 'Main Phone'], ['example' => '412-555-0100']),
                'hours' => $f('Hours', ['Opening Hours', 'Business Hours'], ['example' => 'Mon-Fri 8-5']),
                'contact' => $f('On-site Contact', ['Contact'], ['about' => 'One of the client\'s contacts, by name or email']),
                'tags' => $tags,
                'notes' => $f('Notes', ['Access Notes']),
                'people' => $f('People') + $skip,
                'assets' => $f('Assets') + $skip,
            ],
            'legacy' => ['name', 'description', 'address', 'city', 'state', 'zip', 'phone', 'hours'],
            'name' => 'name',
            'key' => ['name'],
            'find' => function (array $row, $client_id) {
                return importFindBy('locations', 'location', 'location_name', $row['name'] ?? '', "location_client_id = $client_id");
            },
            'row' => 'importLocationRow',
        ],

        'credentials' => [
            'title' => 'Credentials', 'one' => 'credential', 'icon' => 'fa-key', 'module' => 'module_credential', 'client' => true,
            'ready' => function () {
                return encryptCredentialEntry('') === false ? 'Your vault session has expired, so nothing could be encrypted - sign out and back in, then import.' : '';
            },
            'about' => 'Each row is one of the client\'s credentials, found by its name. Usernames and passwords are encrypted as they are saved. Delete the file when you are done with it.',
            'fields' => [
                'name' => $f('Name', ['Credential', 'Title'], ['required' => true, 'example' => 'Firewall admin']),
                'description' => $f('Description'),
                'username' => $f('Username', ['User', 'User Name', 'Login', 'Email'], ['example' => 'admin']),
                'password' => $f('Password', ['Pass']),
                'otp_secret' => $f('TOTP', ['2FA', 'OTP', 'MFA', 'TOTP Secret', '2FA Secret'], ['about' => 'The key a site shows under "can\'t scan the code", or its otpauth:// link']),
                'uri' => $f('URI', ['URL', 'Link', 'Website', 'Login URL'], ['example' => 'https://192.168.1.1']),
                'uri_2' => $f('URI 2', ['URL 2', 'Second URL']),
                'favorite' => $f('Favorite', [], ['about' => $yes]),
                'asset' => $f('Asset', [], ['about' => 'One of the client\'s assets, by name']),
                'contact' => $f('Contact', [], ['about' => 'One of the client\'s contacts, by name or email']),
                'tags' => $tags,
                'note' => $f('Notes', ['Note']),
                'password_changed' => $f('Password Changed') + $skip,
                'client_name' => $f('Client') + $skip,
            ],
            'legacy' => ['name', 'description', 'username', 'password', 'otp_secret', 'uri'],
            'name' => 'name',
            'key' => ['name'],
            'find' => function (array $row, $client_id) {
                return importFindBy('credentials', 'credential', 'credential_name', $row['name'] ?? '', "credential_client_id = $client_id");
            },
            'row' => 'importCredentialRow',
        ],

        'networks' => [
            'title' => 'Networks', 'one' => 'network', 'icon' => 'fa-network-wired', 'module' => 'module_support', 'client' => true,
            'about' => 'Each row is one of the client\'s networks, found by its name and subnet. The subnet, gateway, DNS servers and DHCP range are checked as the network form checks them.',
            'fields' => [
                'name' => $f('Name', ['Network Name'], ['required' => true, 'example' => 'LAN']),
                'description' => $f('Description'),
                'vlan' => $f('VLAN', ['VLAN ID'], ['about' => 'A number from 1 to 4094']),
                'network' => $f('Network (CIDR)', ['Network', 'CIDR', 'Subnet'], ['required' => true, 'about' => 'An address and its prefix, like 192.168.1.0/24', 'example' => '192.168.1.0/24']),
                'gateway' => $f('Gateway', ['Default Gateway'], ['example' => '192.168.1.1']),
                'dhcp_range' => $f('IP Range', ['DHCP Range', 'DHCP'], ['example' => '192.168.1.100 - 192.168.1.200']),
                'primary_dns' => $f('Primary DNS', ['DNS', 'DNS 1'], ['example' => '192.168.1.1']),
                'secondary_dns' => $f('Secondary DNS', ['DNS 2']),
                'type' => $f('Type', ['Network Type'], ['about' => implode(', ', array_keys(NETWORK_TYPES)) . ' - guessed from the name when left empty', 'example' => 'LAN']),
                'location' => $f('Site', ['Location'], ['about' => 'One of the client\'s locations, by name']),
                'vendor' => $f('Provider', ['Vendor', 'ISP'], ['about' => 'One of the client\'s vendors or yours, by name']),
                'tags' => $tags,
                'notes' => $f('Notes'),
                'client_name' => $f('Client') + $skip,
            ],
            'name' => 'name',
            'key' => function (array $row) {
                $info = networkSubnetInfo((string) ($row['network'] ?? ''));
                return mb_strtolower((string) ($row['name'] ?? '')) . "\n" . ($info['cidr'] ?? '');
            },
            'find' => function (array $row, $client_id) {
                $info = networkSubnetInfo((string) ($row['network'] ?? ''));
                return importFindBy('networks', 'network', 'network_name', $row['name'] ?? '', "network_client_id = $client_id" . ($info ? " AND network = '" . escapeSql($info['cidr']) . "'" : ''));
            },
            'row' => 'importNetworkRow',
        ],

        'products' => [
            'title' => 'Products', 'one' => 'product', 'icon' => 'fa-box-open', 'module' => 'module_sales', 'client' => false,
            'about' => 'Each row is a product or service in your catalog, found by its code, else its name - a price list can update by Code alone.',
            'fields' => [
                'name' => $f('Name', ['Product', 'Product Name', 'Item', 'Service'], ['required' => true, 'example' => 'Managed Workstation']),
                'type' => $f('Type', [], ['about' => 'Product or Service - a product when left empty', 'example' => 'Service']),
                'code' => $f('Code', ['SKU', 'Product Code', 'Item Code'], ['example' => 'MS-WKS']),
                'category' => $f('Category', [], ['about' => 'One of your income categories, by name', 'example' => 'Managed Services']),
                'description' => $f('Description', [], ['example' => 'Per workstation, per month']),
                'price' => $f('Price', ['Unit Price', 'Sell Price'], ['example' => '65.00']),
                'cost' => $f('Cost', ['Unit Cost', 'Buy Price'], ['example' => '18.00']),
                'tax' => $f('Tax', ['Tax Rate'], ['about' => 'One of your tax rates, by name']),
                'frequency' => $f('Billed', ['Frequency', 'Billing'], ['about' => 'One time, Monthly, Quarterly or Yearly - "Monthly, per user" as the export has it', 'example' => 'Monthly']),
                'seat_source' => $f('Counted', ['Per'], ['about' => 'Per user, Per device or Per managed device - recurring only', 'example' => 'Per device']),
                'vendor' => $f('Vendor', ['Supplier', 'Distributor'], ['about' => 'One of your vendors, by name']),
                'vendor_part' => $f('Part Number', ['Vendor Part', 'MPN', 'Part', 'Part No']),
                'track_stock' => $f('Track Stock', ['Tracked', 'Stock Tracked'], ['about' => $yes . ' - products only']),
                'reorder_level' => $f('Reorder At', ['Reorder Level', 'Reorder Point', 'Minimum Stock']),
                'reorder_qty' => $f('Order', ['Order Qty', 'Reorder Qty', 'Reorder Quantity']),
                'location' => $f('Location', ['Bin', 'Shelf'], ['about' => 'Where its stock is kept']),
                'asset_type' => $f('Asset Type', ['Client Asset'], ['about' => 'The asset a client gets when it is sold - products only']),
                'make' => $f('Make', ['Manufacturer', 'Brand']),
                'model' => $f('Model'),
                'warranty_months' => $f('Warranty', ['Warranty Months', 'Warranty (months)'], ['about' => 'Months']),
                'notes' => $f('Notes', ['Internal Notes']),
                'margin' => $f('Margin') + $skip,
                'currency' => $f('Currency') + $skip,
                'in_stock' => $f('In Stock') + $skip,
                'stock_value' => $f('Stock Value') + $skip,
            ],
            'legacy' => ['name', 'description', 'price'],
            'name' => ['name', 'code'],
            'key' => ['code', 'name'],
            'find' => function (array $row) {
                $found = importFindBy('products', 'product', 'product_code', $row['code'] ?? '');
                return $found[0] || ($row['code'] ?? '') !== '' ? $found : importFindBy('products', 'product', 'product_name', $row['name'] ?? '');
            },
            'row' => 'importProductRow',
            'finish' => function (array $result) {
                // A new price list - how many lines are at an old price now
                $behind = $result['updated'] ? productRepriceCount() : ['lines' => 0];
                return $behind['lines'] ? '<strong>' . intval($behind['lines']) . '</strong> line' . ($behind['lines'] == 1 ? ' is' : 's are') . ' on recurring invoices, drafts or quotes at an old price - Update Prices is in the Products menu.' : '';
            },
        ],

        'assets' => [
            'title' => 'Assets', 'one' => 'asset', 'icon' => 'fa-desktop', 'module' => 'module_support', 'client' => true,
            'about' => 'Each row is one of the client\'s assets, found by its name, with its primary interface. Its details - CPU, RAM, ports and the rest - can follow, headed as the asset export heads them.',
            'fields' => [
                'asset_name' => $f('Name', ['Asset Name', 'Asset', 'Hostname'], ['required' => true, 'example' => 'FRONT-DESK-PC']),
                'asset_description' => $f('Description'),
                'asset_type' => $f('Type', ['Asset Type'], ['about' => implode(', ', array_keys(ASSET_TYPES)), 'example' => 'Desktop']),
                'asset_status' => $f('Status', [], ['about' => 'A status not yet one is added', 'example' => 'Deployed']),
                'asset_make' => $f('Make', ['Manufacturer', 'Brand'], ['example' => 'Dell']),
                'asset_model' => $f('Model', [], ['example' => 'OptiPlex 7010']),
                'asset_serial' => $f('Serial Number', ['Serial', 'Service Tag'], ['example' => 'ABC1234']),
                'asset_os' => $f('Operating System', ['OS', 'Firmware'], ['example' => 'Windows 11 Pro']),
                'interface_ip' => $f('Primary IP', ['IP', 'IP Address'], ['about' => 'An address, or DHCP']),
                'interface_mac' => $f('Primary MAC', ['MAC', 'MAC Address']),
                'network_name' => $f('Network', [], ['about' => 'One of the client\'s networks, by name']),
                'asset_uri' => $f('Link', ['URI', 'URL']),
                'asset_uri_2' => $f('Second Link', ['URI 2']),
                'asset_uri_client' => $f('Client Link'),
                'contact_name' => $f('Assigned To', ['Contact', 'User'], ['about' => 'One of the client\'s contacts, by name or email']),
                'location_name' => $f('Location', ['Site'], ['about' => 'One of the client\'s locations, by name']),
                'asset_physical_location' => $f('Physical Location', ['Room']),
                'vendor_name' => $f('Vendor', [], ['about' => 'One of the client\'s vendors, by name']),
                'asset_purchase_reference' => $f('Order Reference', ['Order / Invoice Reference', 'Purchase Reference', 'PO']),
                'asset_purchase_date' => $f('Purchase Date', ['Purchased'], ['about' => 'A date, YYYY-MM-DD']),
                'asset_install_date' => $f('Install Date', ['Installed'], ['about' => 'A date, YYYY-MM-DD']),
                'asset_warranty_expire' => $f('Warranty Expire', ['Warranty Expires', 'Warranty'], ['about' => 'A date, YYYY-MM-DD']),
                'asset_tags' => $tags,
                'asset_notes' => $f('Notes'),
                'client_name' => $f('Client') + $skip,
            ],
            'legacy' => ['asset_name', 'asset_description', 'asset_type', 'asset_make', 'asset_model', 'asset_serial', 'asset_os', 'asset_purchase_date',
                'contact_name', 'location_name', 'asset_physical_location', 'asset_notes'],
            'name' => 'asset_name',
            'key' => ['asset_name'],
            'find' => function (array $row, $client_id) {
                return importFindBy('assets', 'asset', 'asset_name', $row['asset_name'] ?? '', "asset_client_id = $client_id");
            },
            'row' => 'importAssetRow',
        ],
    ];

    // An asset's details, headed as the asset export heads them (functions/asset_type.php)
    foreach (ASSET_SPECS as $key => $spec) {
        $kinds['assets']['fields']["spec_$key"] = $f($spec['label'] . (isset($spec['unit']) ? ' (' . $spec['unit'] . ')' : ''), [$spec['label'], $key], ['detail' => true, 'about' => 'An asset detail']);
    }
    return $kinds;
}

/** What a cell couldn't be - "Location Annex isn't one of the client's locations - left empty". */
function importLeftNote($label, $value, $what, $updating) {
    return "$label " . $value . " $what - left " . ($updating ? 'as it was' : 'empty');
}

/** Clients - with the primary location and contact the row has. */
function importClientRow(array $row, $client_id, $existing_id, $write) {
    global $mysqli, $session_company_currency;
    $up = $existing_id > 0;
    $give = function ($key) use ($row, $up) {
        return importGiven($row, $key, $up);
    };
    if ($up && !hasClientAccess($existing_id)) {
        return ['error' => "You don't have access to this client."];
    }
    $name = trim((string) ($row['client_name'] ?? ''));
    if (!$up && $name === '') {
        return ['error' => 'No Client Name'];
    }
    $notes = [];
    $set = $up ? [] : ['client_name = ' . importSqlText($name)];
    foreach (['client_type' => 200, 'client_tax_id_number' => 255, 'client_notes' => 0] as $key => $max) {
        if (($value = $give($key)) !== null) {
            $set[] = "$key = " . importSqlText($value, $max);
        }
    }
    if (($value = $give('client_referral')) !== null) {
        // One not yet a referral is made one; one that is takes its own spelling
        $set[] = "client_referral = '" . escapeSql(categoryEnsure('Referral', $value)) . "'";
    }
    if (($value = $give('client_website')) !== null) {
        $set[] = 'client_website = ' . importSqlText(preg_replace('(^https?://)', '', $value));
    }
    if (($value = $give('client_lead')) !== null) {
        $set[] = 'client_lead = ' . (importYes($value) ? 1 : 0);
    }
    $value = $give('client_abbreviation');
    if ($value !== null || !$up) {
        $set[] = 'client_abbreviation = ' . importSqlText((string) $value !== '' ? $value : shortenClientName($name), 6);
    }
    $currency = strtoupper((string) $give('client_currency_code'));
    if ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) {
        $notes[] = importLeftNote('Currency', $row['client_currency_code'], "isn't a 3-letter code", $up);
        $currency = '';
    }
    if ($currency !== '' || !$up) {
        $set[] = "client_currency_code = '" . escapeSql($currency !== '' ? $currency : $session_company_currency) . "'";
    }

    // Its tax rate and exemption, as the Billing tab sets them (functions/tax.php)
    $tax = [];
    if (($value = $give('client_tax_rate')) !== null) {
        $tax_id = importTaxId($value);
        if ($value !== '' && !$tax_id) {
            $notes[] = importLeftNote('Tax Rate', $value, "isn't one of your tax rates", $up);
        } else {
            $tax['default_tax_id'] = $tax_id;
        }
    }
    if (($value = $give('client_tax_exempt')) !== null) {
        $tax['tax_exempt'] = importYes($value);
    }
    if (($value = $give('client_tax_exempt_number')) !== null) {
        $tax['tax_exempt_number'] = $value;
    }
    if (($value = $give('client_tax_exempt_expires')) !== null) {
        $date = importDate($value);
        if ($date === null) {
            $notes[] = importLeftNote('Exemption Expires', $value, "isn't a date (YYYY-MM-DD)", $up);
        } else {
            $tax['tax_exempt_expires'] = $date;
        }
    }
    if ($tax) {
        $current = $up ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_default_tax_id, client_tax_exempt, client_tax_exempt_number, client_tax_exempt_expires FROM clients WHERE client_id = $existing_id")) : [];
        $set[] = taxClientSetSql($tax, $current ?: []);
    }

    if ($up) {
        $id = $existing_id;
        if ($set) {
            mysqli_query($mysqli, "UPDATE clients SET " . implode(', ', $set) . " WHERE client_id = $id");
        }
    } else {
        mysqli_query($mysqli, "INSERT INTO clients SET " . implode(', ', $set) . billingClientLegacySql());
        $id = intval(mysqli_insert_id($mysqli));
        if ($write && !file_exists("../uploads/clients/$id")) {
            mkdir("../uploads/clients/$id");
            file_put_contents("../uploads/clients/$id/index.php", "");
        }
    }

    // Its primary location and contact - the one it has, or a new one when the row has them
    $parts = [
        'location' => ['locations', ['location_name' => 200, 'location_address' => 200, 'location_city' => 200, 'location_state' => 200, 'location_zip' => 200, 'location_country' => 200, 'location_phone' => 'phone'], 'Primary'],
        'contact' => ['contacts', ['contact_name' => 200, 'contact_title' => 200, 'contact_email' => 200, 'contact_phone' => 'phone', 'contact_extension' => 'digits', 'contact_mobile' => 'phone'], (string) ($row['contact_email'] ?? '')],
    ];
    foreach ($parts as $part => [$table, $columns, $fallback_name]) {
        $part_set = [];
        foreach ($columns as $column => $how) {
            $value = (string) $give($column);
            if ($value === '') {
                continue;
            }
            $part_set[] = $how === 'phone' ? importPhoneSql($value, $column) : ($how === 'digits' ? "$column = '" . substr(preg_replace('/\D/', '', $value), 0, 10) . "'" : "$column = " . importSqlText($value, $how));
        }
        if (!$part_set) {
            continue;
        }
        $primary = $up ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$part}_id FROM $table WHERE {$part}_client_id = $id AND {$part}_primary = 1 AND {$part}_archived_at IS NULL LIMIT 1"))[0] ?? 0) : 0;
        if ($primary) {
            mysqli_query($mysqli, "UPDATE $table SET " . implode(', ', $part_set) . " WHERE {$part}_id = $primary");
        } else {
            if ((string) ($row["{$part}_name"] ?? '') === '') {
                $part_set[] = "{$part}_name = " . importSqlText($fallback_name !== '' ? $fallback_name : 'Primary');
            }
            mysqli_query($mysqli, "INSERT INTO $table SET " . implode(', ', $part_set) . ", {$part}_primary = 1" . ($part === 'contact' ? ', contact_important = 1' : '') . ", {$part}_client_id = $id");
        }
    }

    importTagsSet('client', $id, $row, 'client_tags');

    // An hourly rate or payment terms other than the defaults become a new client's Standard terms contract (functions/contract.php)
    $rate = (string) ($row['hourly_rate'] ?? '');
    $terms = (string) ($row['payment_terms'] ?? '');
    if ($up && $rate . $terms !== '') {
        $notes[] = "Hourly Rate and Payment Terms are on the client's contract - not changed";
    } elseif (!$up && $rate . $terms !== '' && function_exists('contractsReady') && contractsReady()) {
        $rate = $rate !== '' ? floatval(str_replace([',', '$'], '', $rate)) : null;
        $terms = is_numeric($terms) ? max(0, min(365, intval($terms))) : null;
        if (contractStandardTermsAdd($id, $rate, $terms, [], null, 'Made by the client import from its Hourly Rate and Payment Terms.')) {
            $notes[] = 'Its own rate or terms are on a Standard terms contract';
        }
    }
    return ['notes' => $notes];
}

/** Contacts. */
function importContactRow(array $row, $client_id, $existing_id, $write) {
    global $mysqli;
    $up = $existing_id > 0;
    $give = function ($key) use ($row, $up) {
        return importGiven($row, $key, $up);
    };
    $name = trim((string) ($row['name'] ?? ''));
    if (!$up && $name === '') {
        return ['error' => 'No Name'];
    }
    $notes = [];
    $set = [];
    foreach (['name' => 200, 'title' => 200, 'department' => 200, 'notes' => 0] as $key => $max) {
        if (($value = $give($key)) !== null && ($key !== 'name' || $value !== '')) {
            $set[] = "contact_$key = " . importSqlText($value, $max);
        }
    }
    if (($value = $give('email')) !== null) {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $notes[] = importLeftNote('Email', $value, "isn't an email address", $up);
        } else {
            $set[] = 'contact_email = ' . importSqlText($value);
        }
    }
    foreach (['phone', 'mobile'] as $key) {
        if (($value = $give($key)) !== null) {
            $set[] = importPhoneSql($value, "contact_$key");
        }
    }
    if (($value = $give('extension')) !== null) {
        $set[] = "contact_extension = '" . substr(preg_replace('/\D/', '', $value), 0, 10) . "'";
    }
    if (($value = $give('location')) !== null) {
        $location_id = importClientRecordId('location', $value, $client_id);
        if ($value !== '' && !$location_id) {
            $notes[] = importLeftNote('Location', $value, "isn't one of the client's locations", $up);
        } else {
            $set[] = "contact_location_id = $location_id";
        }
    }
    foreach (['important', 'billing', 'technical'] as $key) {
        if (($value = $give($key)) !== null) {
            $set[] = "contact_$key = " . (importYes($value) ? 1 : 0);
        }
    }

    if ($up) {
        $id = $existing_id;
        if ($set) {
            mysqli_query($mysqli, "UPDATE contacts SET " . implode(', ', $set) . " WHERE contact_id = $id");
        }
    } else {
        mysqli_query($mysqli, "INSERT INTO contacts SET " . implode(', ', $set) . ", contact_client_id = $client_id");
        $id = intval(mysqli_insert_id($mysqli));
    }
    if (importYes($row['primary'] ?? '')) {
        mysqli_query($mysqli, "UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id AND contact_id != $id");
        mysqli_query($mysqli, "UPDATE contacts SET contact_primary = 1, contact_important = 1 WHERE contact_id = $id");
    }
    importTagsSet('contact', $id, $row, 'tags');
    return ['notes' => $notes];
}

/** Locations - a client with no primary location gets its first. */
function importLocationRow(array $row, $client_id, $existing_id, $write) {
    global $mysqli;
    $up = $existing_id > 0;
    $give = function ($key) use ($row, $up) {
        return importGiven($row, $key, $up);
    };
    $name = trim((string) ($row['name'] ?? ''));
    if (!$up && $name === '') {
        return ['error' => 'No Name'];
    }
    $notes = [];
    $set = $up ? [] : ['location_name = ' . importSqlText($name)];
    foreach (['description' => 0, 'address' => 200, 'city' => 200, 'state' => 200, 'zip' => 200, 'country' => 200, 'hours' => 200, 'notes' => 0] as $key => $max) {
        if (($value = $give($key)) !== null) {
            $set[] = "location_$key = " . importSqlText($value, $max);
        }
    }
    if (($value = $give('phone')) !== null) {
        $set[] = importPhoneSql($value, 'location_phone');
    }
    if (($value = $give('contact')) !== null) {
        $contact_id = importClientRecordId('contact', $value, $client_id);
        if ($value !== '' && !$contact_id) {
            $notes[] = importLeftNote('On-site Contact', $value, "isn't one of the client's contacts", $up);
        } else {
            $set[] = "location_contact_id = $contact_id";
        }
    }

    if ($up) {
        $id = $existing_id;
        if ($set) {
            mysqli_query($mysqli, "UPDATE locations SET " . implode(', ', $set) . " WHERE location_id = $id");
        }
    } else {
        mysqli_query($mysqli, "INSERT INTO locations SET " . implode(', ', $set) . ", location_client_id = $client_id");
        $id = intval(mysqli_insert_id($mysqli));
    }
    $has_primary = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM locations WHERE location_client_id = $client_id AND location_primary = 1 AND location_archived_at IS NULL LIMIT 1"));
    if (importYes($row['primary'] ?? '') || !$has_primary) {
        locationMakePrimary($id, $client_id);
    }
    importTagsSet('location', $id, $row, 'tags');
    return ['notes' => $notes];
}

/** Credentials - encrypted as the credential form saves them. */
function importCredentialRow(array $row, $client_id, $existing_id, $write) {
    global $mysqli;
    $up = $existing_id > 0;
    $give = function ($key) use ($row, $up) {
        return importGiven($row, $key, $up);
    };
    $notes = [];
    $post = [];
    foreach (['name', 'description', 'username', 'password', 'otp_secret', 'uri', 'uri_2', 'note'] as $key) {
        if (($value = $give($key)) !== null) {
            $post[$key] = $value;
        }
    }
    if (($value = $give('favorite')) !== null) {
        $post['favorite'] = importYes($value) ? 1 : 0;
    }
    foreach (['asset' => 'Asset', 'contact' => 'Contact'] as $key => $label) {
        if (($value = $give($key)) !== null) {
            $record_id = importClientRecordId($key, $value, $client_id);
            if ($value !== '' && !$record_id) {
                $notes[] = importLeftNote($label, $value, "isn't one of the client's " . $key . 's', $up);
            } else {
                $post[$key] = $record_id;
            }
        }
    }

    if (!$up) {
        // As New Credential saves one - checked, encrypted, the 2FA seed tidied (functions/credential.php)
        $error = '';
        $set = credentialFormSet($post, $client_id, $error);
        if ($set === null) {
            return ['error' => strip_tags($error)];
        }
        mysqli_query($mysqli, "INSERT INTO credentials SET $set, credential_password_changed_at = NOW(), credential_client_id = $client_id");
        $id = intval(mysqli_insert_id($mysqli));
    } else {
        // Only what the file gives - what it leaves out is never decrypted and saved back
        $id = $existing_id;
        if ($too_long = checkCredentialLengths($post)) {
            return ['error' => "The $too_long is too long to store."];
        }
        $set = [];
        foreach (['name' => 200, 'description' => 500, 'uri' => 500, 'uri_2' => 500, 'note' => 0] as $key => $max) {
            if (isset($post[$key]) && ($key !== 'name' || $post[$key] !== '')) {
                $set[] = "credential_$key = " . importSqlText($post[$key], $max);
            }
        }
        if (isset($post['otp_secret'])) {
            $otp = credentialOtpSeed($post['otp_secret']);
            if ($otp === false) {
                return ['error' => "That TOTP isn't a 2FA secret - use the key the site shows under \"can't scan the code\", or the otpauth:// link."];
            }
            $set[] = "credential_otp_secret = '" . escapeSql($otp) . "'";
        }
        foreach (['username', 'password'] as $key) {
            if (isset($post[$key])) {
                $ciphertext = encryptCredentialEntry($post[$key]);
                if ($ciphertext === false) {
                    return ['error' => 'Your vault session has expired, so the login could not be encrypted - sign out and back in, then import.'];
                }
                $set[] = "credential_$key = '" . escapeSql($ciphertext) . "'";
            }
        }
        if (isset($post['password'])) {
            $before = mysqli_fetch_row(mysqli_query($mysqli, "SELECT credential_password FROM credentials WHERE credential_id = $id"))[0] ?? '';
            if (decryptCredentialEntry((string) $before) !== $post['password']) {
                $set[] = 'credential_password_changed_at = NOW()';
            }
        }
        foreach (['favorite', 'asset', 'contact'] as $key) {
            if (isset($post[$key])) {
                $set[] = "credential_" . ($key === 'favorite' ? 'favorite' : $key . '_id') . " = " . intval($post[$key]);
            }
        }
        if ($set) {
            mysqli_query($mysqli, "UPDATE credentials SET " . implode(', ', $set) . " WHERE credential_id = $id");
        }
    }
    importTagsSet('credential', $id, $row, 'tags');
    return ['notes' => $notes];
}

/** Networks - checked as the network form checks them (functions/network.php). */
function importNetworkRow(array $row, $client_id, $existing_id, $write) {
    global $mysqli;
    $up = $existing_id > 0;
    $give = function ($key) use ($row, $up) {
        return importGiven($row, $key, $up);
    };
    if (!$up && trim((string) ($row['network'] ?? '')) === '') {
        return ['error' => trim((string) ($row['name'] ?? '')) === '' ? 'No Name' : 'No Network (CIDR)'];
    }
    $notes = [];
    // Updating, what the file leaves out is what the network has
    $post = [];
    if ($up) {
        $current = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT network_name, network_description, network_vlan, network, network_gateway, network_dhcp_range,
            network_primary_dns, network_secondary_dns, network_type, network_location_id, network_vendor_id FROM networks WHERE network_id = $existing_id"));
        $post = ['name' => $current['network_name'], 'description' => $current['network_description'], 'vlan' => (string) $current['network_vlan'], 'network' => $current['network'],
            'gateway' => $current['network_gateway'], 'dhcp_range' => $current['network_dhcp_range'], 'primary_dns' => $current['network_primary_dns'],
            'secondary_dns' => $current['network_secondary_dns'], 'type' => $current['network_type'], 'location_id' => $current['network_location_id'], 'vendor_id' => $current['network_vendor_id']];
    }
    foreach (['name', 'description', 'vlan', 'network', 'gateway', 'dhcp_range', 'primary_dns', 'secondary_dns'] as $key) {
        if (($value = $give($key)) !== null) {
            $post[$key] = $value;
        }
    }
    if (($value = $give('type')) !== null) {
        // A type in any case ("lan") is that type; one that isn't one is guessed from the name
        $type = '';
        foreach (array_keys(NETWORK_TYPES) as $name) {
            $type = strcasecmp($name, $value) === 0 ? $name : $type;
        }
        if ($value !== '' && $type === '') {
            $notes[] = "Type $value isn't one - " . ($up ? 'left as it was' : 'guessed from its name');
        }
        if ($type !== '' || !$up) {
            $post['type'] = $type;
        }
    }
    foreach (['location' => ['location', 'location_id', 'Site', "the client's locations", false], 'vendor' => ['vendor', 'vendor_id', 'Provider', "the client's vendors or yours", true]] as $key => [$kind, $post_key, $label, $whose, $ours]) {
        if (($value = $give($key)) !== null) {
            $record_id = importClientRecordId($kind, $value, $client_id, $ours);
            if ($value !== '' && !$record_id) {
                $notes[] = importLeftNote($label, $value, "isn't one of $whose", $up);
            } else {
                $post[$post_key] = $record_id;
            }
        }
    }
    $error = $note = '';
    $set = networkFormSet($post, $client_id, $error, $note);
    if ($set === '') {
        return ['error' => strip_tags($error)];
    }
    if ($note !== '') {
        $notes[] = strip_tags($note);
    }
    if (($value = $give('notes')) !== null) {
        $set .= ', network_notes = ' . importSqlText($value, 0);
    }
    if ($up) {
        $id = $existing_id;
        mysqli_query($mysqli, "UPDATE networks SET $set WHERE network_id = $id");
    } else {
        mysqli_query($mysqli, "INSERT INTO networks SET $set, network_client_id = $client_id");
        $id = intval(mysqli_insert_id($mysqli));
    }
    importTagsSet('network', $id, $row, 'tags');
    return ['notes' => $notes];
}

/** Products and services - saved as the product form saves them, a price change in its price history (functions/products.php). */
function importProductRow(array $row, $client_id, $existing_id, $write) {
    global $mysqli;
    $up = $existing_id > 0;
    $notes = [];
    $in = [];
    foreach (array_keys($row) as $key) {
        $value = importGiven($row, $key, $up);
        if ($value === null) {
            continue;
        }
        switch ($key) {
            case 'type':
                $type = mb_strtolower($value);
                if ($type !== '' && !in_array($type, ['product', 'products', 'hardware', 'goods', 'service', 'services', 'labor', 'labour'], true)) {
                    $notes[] = "Type $value isn't Product or Service - " . ($up ? 'left as it was' : 'imported as a product');
                    continue 2;
                }
                if ($type !== '') {
                    $in['type'] = in_array($type, ['service', 'services', 'labor', 'labour'], true) ? 'service' : 'product';
                }
                break;
            case 'category':
                $category_id = $value !== '' ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL
                    AND LOWER(category_name) = LOWER('" . escapeSql($value) . "') LIMIT 1"))[0] ?? 0) : 0;
                if ($value !== '' && !$category_id) {
                    $notes[] = importLeftNote('Category', $value, "isn't one of your income categories", $up);
                    continue 2;
                }
                $in['category'] = $category_id;
                break;
            case 'tax':
                $tax_id = importTaxId($value);
                if ($value !== '' && !$tax_id) {
                    $notes[] = importLeftNote('Tax', $value, "isn't one of your tax rates", $up);
                    continue 2;
                }
                $in['tax'] = $tax_id;
                break;
            case 'vendor':
                $vendor_id = importClientRecordId('vendor', $value, 0, true);
                if ($value !== '' && !$vendor_id) {
                    $notes[] = importLeftNote('Vendor', $value, "isn't one of your vendors", $up);
                    continue 2;
                }
                $in['vendor'] = $vendor_id;
                break;
            case 'frequency':
                // "Monthly, per user" as the export writes it is how often and what counts it
                $parts = array_map('trim', explode(',', mb_strtolower($value), 2));
                $frequencies = ['' => '', 'once' => '', 'one time' => '', 'one-time' => '', 'monthly' => 'month', 'month' => 'month', 'quarterly' => 'quarter', 'quarter' => 'quarter',
                    'yearly' => 'year', 'year' => 'year', 'annually' => 'year', 'annual' => 'year'];
                if (!isset($frequencies[$parts[0]])) {
                    $notes[] = "Billed $value isn't One time, Monthly, Quarterly or Yearly - " . ($up ? 'left as it was' : 'imported as one time');
                    continue 2;
                }
                $in['frequency'] = $frequencies[$parts[0]];
                if (isset($parts[1]) && !isset($row['seat_source'])) {
                    $in['seat_source'] = importProductSeatSource($parts[1]) ?? '';
                }
                break;
            case 'seat_source':
                $seat = importProductSeatSource($value);
                if ($seat === null) {
                    $notes[] = "Counted $value isn't Per user, Per device or Per managed device - " . ($up ? 'left as it was' : 'left as typed');
                    continue 2;
                }
                $in['seat_source'] = $seat;
                break;
            case 'track_stock':
                $in['track_stock'] = importYes($value) || mb_strtolower($value) === 'tracked';
                break;
            case 'warranty_months':
                // "12" or "12 months"
                $in['warranty_months'] = preg_match('/^(\d+)\s*(months?|mos?)?$/i', $value, $m) ? $m[1] : $value;
                break;
            default:
                $in[$key] = $value;
        }
    }
    if (!$up) {
        if (($in['name'] ?? '') === '') {
            return ['error' => 'No Name'];
        }
        // A new one starts as the kind the file says, a product when it doesn't say
        $in['type'] = $in['type'] ?? 'product';
    } elseif (($in['name'] ?? null) === '') {
        unset($in['name']);
    }
    $error = '';
    if (!productSave($in, $existing_id, $error, 'import')) {
        return ['error' => rtrim($error, '.')];
    }
    return ['notes' => $notes];
}

/** What counts a recurring product - its key, '' for as typed, null when it is none of them. */
function importProductSeatSource($value) {
    $sources = ['' => '', 'as typed' => '', 'per user' => 'contacts', 'user' => 'contacts', 'users' => 'contacts', 'per device' => 'assets', 'device' => 'assets', 'devices' => 'assets',
        'per managed device' => 'managed', 'managed device' => 'managed', 'managed devices' => 'managed'];
    return $sources[mb_strtolower(trim((string) $value))] ?? null;
}

/** Assets - with their primary interface, and their details once every row is in, so a VM's host can come later in the file. */
function importAssetRow(array $row, $client_id, $existing_id, $write) {
    global $mysqli, $session_name;
    $up = $existing_id > 0;
    $give = function ($key) use ($row, $up) {
        return importGiven($row, $key, $up);
    };
    $name = trim((string) ($row['asset_name'] ?? ''));
    if (!$up && $name === '') {
        return ['error' => 'No Name'];
    }
    $notes = [];
    $set = $up ? [] : ['asset_name = ' . importSqlText($name)];
    // Every text column a new asset has, so none is missing
    $texts = ['asset_description' => 255, 'asset_make' => 200, 'asset_model' => 200, 'asset_serial' => 200, 'asset_os' => 200, 'asset_uri' => 500, 'asset_uri_2' => 500,
        'asset_uri_client' => 500, 'asset_purchase_reference' => 200, 'asset_physical_location' => 200, 'asset_notes' => 0];
    foreach ($texts as $key => $max) {
        $value = $give($key);
        if ($value !== null || !$up) {
            $set[] = "$key = " . importSqlText((string) $value, $max);
        }
    }
    // Its type as the list spells it, when it is one; its status one of the statuses, made when new (functions/categories.php)
    $value = $give('asset_type');
    if ($value !== null || !$up) {
        $set[] = "asset_type = '" . escapeSql(assetTypeName((string) $value) ?: mb_substr((string) $value, 0, 200)) . "'";
    }
    if (($value = $give('asset_status')) !== null) {
        $set[] = "asset_status = '" . escapeSql(categoryEnsure('asset_status', $value)) . "'";
    }
    foreach (['asset_purchase_date' => 'Purchase Date', 'asset_install_date' => 'Install Date', 'asset_warranty_expire' => 'Warranty Expire'] as $key => $label) {
        if (($value = $give($key)) !== null) {
            $date = importDate($value);
            if ($date === null) {
                $notes[] = importLeftNote($label, $value, "isn't a date (YYYY-MM-DD)", $up);
            } else {
                $set[] = "$key = " . ($date !== '' ? "'$date'" : 'NULL');
            }
        }
    }
    // The client's own records, by name - a contact by its email too
    $ids = [];
    foreach (['contact' => ['contact_name', 'Assigned To'], 'location' => ['location_name', 'Location'], 'vendor' => ['vendor_name', 'Vendor'], 'network' => ['network_name', 'Network']] as $kind => [$key, $label]) {
        if (($value = $give($key)) !== null) {
            $record_id = importClientRecordId($kind, $value, $client_id);
            if ($value !== '' && !$record_id) {
                $notes[] = importLeftNote($label, $value, "isn't one of the client's {$kind}s", $up);
            } else {
                $ids[$kind] = $record_id;
            }
        }
    }
    foreach (['contact', 'location', 'vendor'] as $kind) {
        if (isset($ids[$kind])) {
            $set[] = "asset_{$kind}_id = " . $ids[$kind];
        }
    }

    if ($up) {
        $id = $existing_id;
        if ($set) {
            mysqli_query($mysqli, "UPDATE assets SET " . implode(', ', $set) . " WHERE asset_id = $id");
        }
    } else {
        mysqli_query($mysqli, "INSERT INTO assets SET " . implode(', ', $set) . ", asset_client_id = $client_id");
        $id = intval(mysqli_insert_id($mysqli));
    }
    $asset = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name, asset_type, asset_status FROM assets WHERE asset_id = $id"));
    mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = '" . escapeSql($asset['asset_status']) . "',
        asset_history_description = '" . escapeSql($session_name . ($up ? ' updated ' : ' imported ') . $asset['asset_name'] . ($up ? ' from an import' : '')) . "', asset_history_asset_id = $id");

    // Its primary interface, with its address, MAC and network - as the asset form makes it
    $interface = [];
    if (($value = $give('interface_ip')) !== null) {
        $interface[] = "interface_ip = " . importSqlText(strcasecmp($value, 'DHCP') === 0 ? 'DHCP' : (normalizeIpAddress($value) ?: $value));
    }
    if (($value = $give('interface_mac')) !== null) {
        $interface[] = "interface_mac = " . importSqlText(normalizeMacAddress($value) ?: $value);
    }
    if (isset($ids['network'])) {
        $interface[] = "interface_network_id = " . $ids['network'];
    }
    $primary = $up ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT interface_id FROM asset_interfaces WHERE interface_asset_id = $id AND interface_primary = 1 LIMIT 1"))[0] ?? 0) : 0;
    if ($primary && $interface) {
        mysqli_query($mysqli, "UPDATE asset_interfaces SET " . implode(', ', $interface) . " WHERE interface_id = $primary");
    } elseif (!$primary && (!$up || $interface)) {
        mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', interface_primary = 1, interface_asset_id = $id" . ($interface ? ', ' . implode(', ', $interface) : ''));
    }

    importTagsSet('asset', $id, $row, 'asset_tags');

    // Its details - on top of the ones it has
    $specs = [];
    foreach ($row as $key => $value) {
        if (strpos($key, 'spec_') === 0 && $value !== '') {
            $specs[substr($key, 5)] = $value;
        }
    }
    if (!$specs && !($up && ($row['asset_type'] ?? '') !== '')) {
        return ['notes' => $notes];
    }
    return ['notes' => $notes, 'after' => function () use ($id, $client_id, $specs, $up) {
        $type = (string) getFieldById('assets', $id, 'asset_type');
        [$made, $problems] = assetSpecsFromPost($id, $type, $specs + ($up ? assetSpecs($id) : []), $client_id);
        $notes = $made !== '' ? [ucfirst(substr($made, strlen(', with ')))] : [];
        foreach ($problems as $problem) {
            $notes[] = 'Not kept - ' . $problem;
        }
        return $notes;
    }];
}
