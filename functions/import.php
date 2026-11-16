<?php

/*
 * CSV imports read by their headings - the columns can come in any order and
 * any can be left out. importColumns() names each import's columns: key =>
 * [label, also, skip]. The label is the template's heading and the export's
 * (getExportColumns()), so a file exported from ITFlow can be imported again;
 * also are other headings taken for it (Serial for Serial Number). A heading
 * matches whatever its case and spacing; a skip column is one the export
 * has that the import takes no notice of. A file of an import's old fixed
 * columns whose headings match none of them is read by position, as it
 * always was (importLegacyColumns()).
 */

/**
 * An import's columns - key => ['label', 'also' => [other headings]], in the
 * template's order. 'clients' and 'assets'; the assets' details follow its
 * columns, headed as the asset export heads them (functions/asset_type.php).
 */
function importColumns($import) {
    $column = function ($label, array $also = []) {
        return ['label' => $label, 'also' => $also];
    };
    if ($import === 'clients') {
        return [
            'client_name' => $column('Client Name', ['Name', 'Client', 'Company']),
            'client_type' => $column('Industry', ['Type']),
            'client_referral' => $column('Referral'),
            'client_website' => $column('Website'),
            'client_lead' => $column('Lead'),
            'client_abbreviation' => $column('Abbreviation'),
            'client_currency_code' => $column('Currency'),
            'client_tax_id_number' => $column('Tax ID'),
            'client_tax_rate' => $column('Tax Rate'),
            'client_tax_exempt' => $column('Tax Exempt'),
            'client_tax_exempt_number' => $column('Exemption Certificate'),
            'client_tax_exempt_expires' => $column('Exemption Expires'),
            'hourly_rate' => $column('Hourly Rate', ['Rate']),
            'payment_terms' => $column('Payment Terms', ['Net Terms']),
            'client_tags' => $column('Tags'),
            'client_notes' => $column('Notes'),
            'location_name' => $column('Primary Location Name', ['Location Name', 'Location']),
            'location_phone' => $column('Location Phone'),
            'location_address' => $column('Location Address', ['Address']),
            'location_city' => $column('City'),
            'location_state' => $column('State'),
            'location_zip' => $column('Postal Code', ['Zip', 'Zip Code', 'Postcode']),
            'location_country' => $column('Country'),
            'contact_name' => $column('Primary Contact Name', ['Contact Name', 'Contact']),
            'contact_title' => $column('Title'),
            'contact_phone' => $column('Contact Phone', ['Phone']),
            'contact_extension' => $column('Extension', ['Ext']),
            'contact_mobile' => $column('Contact Mobile', ['Mobile']),
            'contact_email' => $column('Contact Email', ['Email']),
        ];
    }
    if ($import === 'assets') {
        $columns = [
            'asset_name' => $column('Name', ['Asset Name', 'Asset']),
            'asset_description' => $column('Description'),
            'asset_type' => $column('Type'),
            'asset_status' => $column('Status'),
            'asset_make' => $column('Make', ['Manufacturer']),
            'asset_model' => $column('Model'),
            'asset_serial' => $column('Serial Number', ['Serial']),
            'asset_os' => $column('Operating System', ['OS', 'Firmware']),
            'interface_ip' => $column('Primary IP', ['IP', 'IP Address']),
            'interface_mac' => $column('Primary MAC', ['MAC', 'MAC Address']),
            'network_name' => $column('Network'),
            'asset_uri' => $column('Link', ['URI', 'URL']),
            'asset_uri_2' => $column('Second Link', ['URI 2']),
            'asset_uri_client' => $column('Client Link'),
            'contact_name' => $column('Assigned To', ['Contact']),
            'location_name' => $column('Location'),
            'asset_physical_location' => $column('Physical Location'),
            'vendor_name' => $column('Vendor'),
            'asset_purchase_reference' => $column('Order Reference', ['Order / Invoice Reference', 'Purchase Reference']),
            'asset_purchase_date' => $column('Purchase Date', ['Purchased']),
            'asset_install_date' => $column('Install Date', ['Installed']),
            'asset_warranty_expire' => $column('Warranty Expire', ['Warranty Expires', 'Warranty']),
            'asset_tags' => $column('Tags'),
            'asset_notes' => $column('Notes'),
            // The asset export's Client - the import is into the client it is opened from
            'client_name' => $column('Client') + ['skip' => true],
        ];
        foreach (ASSET_SPECS as $key => $spec) {
            $columns["spec_$key"] = $column($spec['label'] . (isset($spec['unit']) ? ' (' . $spec['unit'] . ')' : ''), [$spec['label'], $key]);
            $columns["spec_$key"]['spec'] = true;
        }
        return $columns;
    }
    return [];
}

/** An import's old fixed columns, by position - for a file made from the old template whose headings match none of today's. */
function importLegacyColumns($import) {
    if ($import === 'clients') {
        return ['client_name', 'client_type', 'client_referral', 'client_website', 'location_name', 'location_phone', 'location_address', 'location_city',
            'location_state', 'location_zip', 'location_country', 'contact_name', 'contact_title', 'contact_phone', 'contact_extension', 'contact_mobile',
            'contact_email', 'hourly_rate', 'client_currency_code', 'payment_terms', 'client_tax_id_number', 'client_abbreviation'];
    }
    if ($import === 'assets') {
        return ['asset_name', 'asset_description', 'asset_type', 'asset_make', 'asset_model', 'asset_serial', 'asset_os', 'asset_purchase_date',
            'contact_name', 'location_name', 'asset_physical_location', 'asset_notes'];
    }
    return [];
}

/** A heading as it is compared - its case, spacing and a trailing colon or asterisk aside. */
function importHeadingKey($heading) {
    return trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim(rtrim(trim((string) $heading), ':*')))));
}

/**
 * The uploaded CSV ($_FILES[$field]) for an import, read by its headings -
 * [$rows, $ignored, $error]: each row key => its cell, trimmed, for the
 * columns the file has; $ignored the headings that are none of the import's.
 * Comma or semicolon separated, whichever the heading line has more of; a
 * byte-order mark is skipped, and so is a row with nothing in it.
 */
function importCsvRows($import, $field = 'file', $max_rows = 10000) {
    $upload = $_FILES[$field] ?? null;
    if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        return [[], [], 'Please select a file to upload.'];
    }
    if (strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)) !== 'csv') {
        return [[], [], "That isn't a CSV file - save it as CSV and import it again."];
    }
    $handle = fopen($upload['tmp_name'], 'r');
    $first = $handle ? fgets($handle) : false;
    if ($first === false || trim($first) === '') {
        return [[], [], 'The file is empty.'];
    }
    $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    rewind($handle);
    $headings = fgetcsv($handle, 0, $delimiter, '"', '');
    if (isset($headings[0])) {
        $headings[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headings[0]);
    }

    // Each heading's column - the first heading for a column takes it
    $lookup = [];
    foreach (importColumns($import) as $key => $column) {
        foreach (array_merge([$column['label']], $column['also']) as $heading) {
            $lookup[importHeadingKey($heading)] = $lookup[importHeadingKey($heading)] ?? $key;
        }
    }
    $map = [];
    $ignored = [];
    foreach ((array) $headings as $index => $heading) {
        $key = $lookup[importHeadingKey($heading)] ?? null;
        if ($key !== null && !in_array($key, $map, true)) {
            $map[$index] = $key;
        } elseif (trim((string) $heading) !== '') {
            $ignored[] = trim((string) $heading);
        }
    }
    // The old template's columns, by position, when no heading is one of today's
    $legacy = importLegacyColumns($import);
    if (!$map && $legacy && count((array) $headings) === count($legacy)) {
        $map = $legacy;
        $ignored = [];
    }
    if (!$map) {
        fclose($handle);
        return [[], [], "None of the file's headings is one this import takes - download the template to see them."];
    }

    $rows = [];
    while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        if (count($rows) >= $max_rows) {
            fclose($handle);
            return [[], [], "The file has more than $max_rows rows - split it and import each part."];
        }
        $row = [];
        foreach ($map as $index => $key) {
            $row[$key] = trim((string) ($cells[$index] ?? ''));
        }
        if (implode('', $row) !== '') {
            $rows[] = $row;
        }
    }
    fclose($handle);
    return [$rows, $ignored, ''];
}

/** An import's template - its columns' headings, as a CSV download. Ends the request. */
function importTemplateDownload($import, $filename) {
    $headings = [];
    foreach (importColumns($import) as $column) {
        if (empty($column['spec']) && empty($column['skip'])) {
            $headings[] = $column['label'];
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headings, ',', '"', '');
    fclose($out);
    exit;
}

/** An import's headings for its window - "Client Name, Industry, ..." - without the asset details. */
function importHeadingsText($import) {
    $labels = [];
    foreach (importColumns($import) as $column) {
        if (empty($column['spec']) && empty($column['skip'])) {
            $labels[] = $column['label'];
        }
    }
    return implode(', ', $labels);
}

/** A yes in a cell - Yes, Y, True, 1 or X. */
function importYes($value) {
    return in_array(mb_strtolower(trim((string) $value)), ['yes', 'y', 'true', '1', 'x'], true);
}

/** A date in a cell as YYYY-MM-DD - or '' when it is empty, and null when it is something else. */
function importDate($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) && checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    return null;
}

/** A cell's list - tags - split on commas and semicolons. */
function importList($value) {
    return array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $value)), 'strlen'));
}

/**
 * Tags named in a cell, as the ids a record of $entity can have (functions/tags.php)
 * - its tags of those names, and new ones for the rest when an admin imports,
 * as typing them in the tag picker does.
 */
function importTagIds($entity, $value) {
    global $mysqli;
    $config = tagEntity($entity);
    $values = [];
    foreach (importList($value) as $name) {
        $row = $config ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_type = {$config['type']} AND tag_archived_at IS NULL
            AND LOWER(tag_name) = LOWER('" . escapeSql($name) . "') LIMIT 1")) : null;
        $values[] = $row ? (string) intval($row['tag_id']) : $name;
    }
    return $values;
}

/**
 * One of a client's records named in a cell - its id, or 0. $kind: contact (by
 * name or email), location, vendor or network; archived ones aren't taken.
 */
function importClientRecordId($kind, $value, $client_id) {
    global $mysqli;
    $value = trim((string) $value);
    $client_id = intval($client_id);
    if ($value === '' || !$client_id) {
        return 0;
    }
    $sql = escapeSql($value);
    $queries = [
        'contact' => "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND (contact_name = '$sql' OR contact_email = '$sql') ORDER BY contact_name = '$sql' DESC, contact_id LIMIT 1",
        'location' => "SELECT location_id FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL AND location_name = '$sql' ORDER BY location_id LIMIT 1",
        'vendor' => "SELECT vendor_id FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL AND vendor_name = '$sql' ORDER BY vendor_id LIMIT 1",
        'network' => "SELECT network_id FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL AND network_name = '$sql' ORDER BY network_id LIMIT 1",
    ];
    if (!isset($queries[$kind])) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, $queries[$kind]));
    return intval($row[0] ?? 0);
}

/** What an import left out, for its message - "vendors not found: 1, dates left empty (use YYYY-MM-DD): 2". '' when nothing. */
function importNotesText(array $notes) {
    $parts = [];
    foreach ($notes as $what => $count) {
        if ($count > 0) {
            $parts[] = "$what: $count";
        }
    }
    return implode(', ', $parts);
}

/** A tax rate named in a cell - by its name, or its name and rate as ITFlow shows it ("Sales Tax 7%") - its id, or 0. */
function importTaxId($value) {
    global $mysqli;
    $key = importHeadingKey($value);
    if ($key === '') {
        return 0;
    }
    $sql = mysqli_query($mysqli, "SELECT tax_id FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tax = taxRow($row['tax_id']);
        if ($tax && (importHeadingKey($tax['tax_name']) === $key || importHeadingKey(taxLabel($tax)) === $key)) {
            return intval($tax['tax_id']);
        }
    }
    return 0;
}

/**
 * A phone number in a cell as [dial code, number] - "+1 (412) 555-0100", as
 * the exports write it, is ['1', '4125550100']; one without a + or 00 in
 * front is just its digits. The dial code is the one its number fits
 * (functions/format.php).
 */
function importPhone($value) {
    // The ' the exports put in front, so a spreadsheet doesn't take it for a formula
    $value = ltrim(trim((string) $value), "'");
    $digits = preg_replace('/\D/', '', $value);
    if ($digits === '' || !preg_match('/^(\+|00)/', $value)) {
        return ['', $digits];
    }
    if (strpos($value, '00') === 0) {
        $digits = substr($digits, 2);
    }
    for ($length = 1; $length <= 3; $length++) {
        $rules = phoneFormatRules(substr($digits, 0, $length));
        if ($rules && ($rules['valid'] === '' || preg_match('/^(?:' . $rules['valid'] . ')$/', substr($digits, $length)))) {
            return [substr($digits, 0, $length), substr($digits, $length)];
        }
    }
    return ['', $digits];
}
