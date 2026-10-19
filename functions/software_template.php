<?php

/*
 * ITFlow - license templates (Admin > License Templates): the licenses an MSP
 * sells or looks after again and again - Microsoft 365 Business Premium, an
 * endpoint protection agent, a backup product - ready to start a client's
 * license from. A license made from one gets its name, version, summary, type,
 * license type and notes, and remembers which template it came from
 * (software.software_template_id, from database 3.2.8 on), so each template's
 * use is counted: how many licenses, for how many clients, with how many seats.
 *
 * From database 3.2.9 on a template also carries the seats a license starts
 * with, how often it renews - its renewal date worked out from when it was
 * bought, and Renew offering that term - the vendor template its vendor comes
 * from (the client's own vendor made from it, or one of that name, or one made
 * from it there and then) and the product it is sold as. Licenses can be made
 * from one for several clients at once.
 *
 * Shared by admin/software_templates.php, admin/software_template.php,
 * admin/modals/software_template/*, admin/post/software_template.php and the
 * New License window and its handlers on the agent side
 * (agent/modals/software/software_form.php, agent/post/software.php).
 */

/** One template, archived or not, with who made it and last changed it - or null. */
function softwareTemplateRow($software_template_id) {
    global $mysqli;
    $software_template_id = intval($software_template_id);
    if ($software_template_id <= 0) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software_template_id, software_template_name, software_template_description, software_template_version,
            software_template_type, software_template_license_type, software_template_notes, software_template_seats, software_template_term,
            software_template_vendor_template_id, software_template_product_id, software_template_created_at, software_template_updated_at,
            software_template_archived_at, software_template_created_by, software_template_updated_by,
            creator.user_name AS created_by_name, updater.user_name AS updated_by_name,
            vendor_template_name, vendor_template_archived_at, product_name, product_price, product_cost, product_currency_code, product_frequency, product_seat_source, product_archived_at
        FROM software_templates
        LEFT JOIN users creator ON creator.user_id = software_template_created_by AND software_template_created_by > 0
        LEFT JOIN users updater ON updater.user_id = software_template_updated_by AND software_template_updated_by > 0
        LEFT JOIN vendor_templates ON vendor_template_id = software_template_vendor_template_id AND software_template_vendor_template_id > 0
        LEFT JOIN products ON product_id = software_template_product_id AND software_template_product_id > 0
        WHERE software_template_id = $software_template_id"));
    return $row ?: null;
}

/*
 * How the templates are used - template id => count (current licenses made
 * from it, for current clients), clients (how many clients have one), seats
 * (seats bought across them, the ones with a seat count), used (seats in use
 * across them), renewing (in the next 30 days), expired, and last (when the
 * latest was made). Ones not used aren't in it.
 */
function softwareTemplateUsage(array $software_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $software_template_ids)));
    $usage = [];
    if (!$ids) {
        return $usage;
    }
    $filters = softwareStatusFilters();
    $sql = mysqli_query($mysqli, "SELECT software_template_id, COUNT(*) AS n, COUNT(DISTINCT software_client_id) AS clients, COALESCE(SUM(software_seats), 0) AS seats,
            COALESCE(SUM(" . softwareSeatsUsedSql('software.software_id') . "), 0) AS used, COALESCE(SUM({$filters['renewing']['sql']}), 0) AS renewing,
            COALESCE(SUM({$filters['expired']['sql']}), 0) AS expired, MAX(software_created_at) AS last
        FROM software JOIN clients ON client_id = software_client_id AND client_archived_at IS NULL
        WHERE software_template_id IN (" . implode(',', $ids) . ") AND software_archived_at IS NULL GROUP BY software_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage[intval($row['software_template_id'])] = ['count' => intval($row['n']), 'clients' => intval($row['clients']), 'seats' => intval($row['seats']),
            'used' => intval($row['used']), 'renewing' => intval($row['renewing']), 'expired' => intval($row['expired']), 'last' => $row['last']];
    }
    return $usage;
}

/*
 * The template window's fields, checked - or null with $error saying why.
 * $post: name, version, description (a line on what it is), type (a software
 * type from Admin > Categories), license_type, notes, seats (blank for no
 * limit), term (one of SOFTWARE_RENEWAL_TERMS, or blank), vendor_template_id
 * (a vendor template, or none) and product_id (a product, or none) - an
 * archived one stays, the window only offering it to the template that has it.
 */
function softwareTemplateFields(array $post, &$error = '') {
    $error = '';
    $line = function ($key) use ($post) {
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($post[$key] ?? ''))));
    };
    $name = $line('name');
    if ($name === '') {
        $error = 'A license template needs a name';
        return null;
    }
    if (mb_strlen($name) > 200) {
        $error = 'Keep the name to 200 characters';
        return null;
    }
    $type = $line('type');
    if ($type === '') {
        $error = 'Pick what type of software it is';
        return null;
    }
    $fields = [
        'name' => $name,
        'version' => mb_substr($line('version'), 0, 200),
        'description' => mb_substr($line('description'), 0, 500),
        'type' => mb_substr($type, 0, 200),
        'license_type' => mb_substr($line('license_type'), 0, 200),
        'notes' => trim(strip_tags((string) ($post['notes'] ?? ''))),
        'seats' => trim((string) ($post['seats'] ?? '')) === '' ? null : max(0, min(1000000, intval($post['seats']))),
        'term' => isset(SOFTWARE_RENEWAL_TERMS[(string) ($post['term'] ?? '')]) ? (string) $post['term'] : '',
        'vendor_template_id' => softwareTemplateLinkId('vendor_templates', 'vendor_template_id', $post['vendor_template_id'] ?? 0),
        'product_id' => softwareTemplateLinkId('products', 'product_id', $post['product_id'] ?? 0),
    ];
    return $fields;
}

/* Save a template's fields (softwareTemplateFields()) - a new one with $software_template_id 0. Its id. */
function softwareTemplateSave($software_template_id, array $f) {
    global $mysqli, $session_user_id;
    $software_template_id = intval($software_template_id);
    $user_id = intval($session_user_id ?? 0);
    $set = "software_template_name = '" . escapeSql($f['name']) . "', software_template_version = '" . escapeSql($f['version']) . "',
        software_template_description = '" . escapeSql($f['description']) . "', software_template_type = '" . escapeSql($f['type']) . "',
        software_template_license_type = '" . escapeSql($f['license_type']) . "', software_template_notes = '" . escapeSql($f['notes']) . "',
        software_template_seats = " . (($f['seats'] ?? null) === null ? 'NULL' : intval($f['seats'])) . ",
        software_template_term = " . (($f['term'] ?? '') !== '' ? "'" . escapeSql($f['term']) . "'" : 'NULL') . ",
        software_template_vendor_template_id = " . intval($f['vendor_template_id'] ?? 0) . ", software_template_product_id = " . intval($f['product_id'] ?? 0);
    if ($software_template_id) {
        mysqli_query($mysqli, "UPDATE software_templates SET $set, software_template_updated_by = $user_id WHERE software_template_id = $software_template_id");
        return $software_template_id;
    }
    mysqli_query($mysqli, "INSERT INTO software_templates SET $set, software_template_created_by = $user_id");
    return intval(mysqli_insert_id($mysqli));
}

/* A new template starting from this one - "Copy of". Its id, or 0. */
function softwareTemplateCopy($software_template_id) {
    $row = softwareTemplateRow($software_template_id);
    if (!$row) {
        return 0;
    }
    return softwareTemplateSave(0, [
        'name' => mb_substr('Copy of ' . $row['software_template_name'], 0, 200),
        'version' => (string) $row['software_template_version'],
        'description' => (string) $row['software_template_description'],
        'type' => (string) $row['software_template_type'],
        'license_type' => (string) $row['software_template_license_type'],
        'notes' => (string) $row['software_template_notes'],
        'seats' => $row['software_template_seats'] === null ? null : intval($row['software_template_seats']),
        'term' => (string) $row['software_template_term'],
        'vendor_template_id' => intval($row['software_template_vendor_template_id']),
        'product_id' => intval($row['software_template_product_id']),
    ]);
}

/* Delete a template. The licenses made from it stay, no longer linked to it. */
function softwareTemplateDelete($software_template_id) {
    global $mysqli;
    $software_template_id = intval($software_template_id);
    mysqli_query($mysqli, "DELETE FROM software_templates WHERE software_template_id = $software_template_id");
    mysqli_query($mysqli, "UPDATE software SET software_template_id = 0, software_updated_at = software_updated_at WHERE software_template_id = $software_template_id");
}

/** A current template's id, or 0 - for a license form saying which one it started from. */
function softwareTemplateLiveId($software_template_id) {
    $row = softwareTemplateRow($software_template_id);
    return $row && !$row['software_template_archived_at'] ? intval($row['software_template_id']) : 0;
}

/** A vendor template or product a template links to, when it exists - or 0. */
function softwareTemplateLinkId($table, $column, $id) {
    global $mysqli;
    $id = intval($id);
    return $id > 0 && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM $table WHERE $column = $id")) ? $id : 0;
}

/*
 * When a license bought on $purchase (Y-m-d, or today when blank) renews
 * with a template's $term - Y-m-d, or '' without a term.
 */
function softwareTemplateRenewal($term, $purchase = '') {
    $intervals = ['1m' => 'P1M', '3m' => 'P3M', '6m' => 'P6M', '1y' => 'P1Y', '2y' => 'P2Y', '3y' => 'P3Y'];
    if (!isset($intervals[(string) $term])) {
        return '';
    }
    $from = softwareFormDate($purchase);
    return (new DateTime($from ?: 'today'))->add(new DateInterval($intervals[$term]))->format('Y-m-d');
}

/** A template's price as it is sold - "$22.00 · Monthly, per user" - or ''. */
function softwareTemplatePriceText(array $template) {
    if (empty($template['product_name'])) {
        return '';
    }
    $billed = productBilledText($template['product_frequency'], $template['product_seat_source']);
    return billingMoney($template['product_price'], $template['product_currency_code']) . ' · ' . $billed;
}

/*
 * A client's vendor for a vendor template - the client's current vendor made
 * from it, or one of its name; or, with $make, one made from the template
 * there and then ($made says so). 0 when there is none, or the template is
 * gone or archived.
 */
function softwareVendorFromTemplate($vendor_template_id, $client_id, $make = true, &$made = false) {
    global $mysqli;
    $made = false;
    $client_id = intval($client_id);
    $template = vendorTemplateRow($vendor_template_id);
    if (!$template || !$client_id) {
        return 0;
    }
    $vendor_template_id = intval($template['vendor_template_id']);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL
        AND (vendor_template_id = $vendor_template_id OR LOWER(vendor_name) = LOWER('" . escapeSql($template['vendor_template_name']) . "'))
        ORDER BY vendor_template_id = $vendor_template_id DESC, vendor_id ASC LIMIT 1"));
    if ($row) {
        return intval($row[0]);
    }
    if (!$make) {
        return 0;
    }
    $set = vendorFormSet(vendorTemplateFormFields($template));
    if ($set === null) {
        return 0;
    }
    mysqli_query($mysqli, "INSERT INTO vendors SET $set, vendor_client_id = $client_id");
    $made = true;
    return intval(mysqli_insert_id($mysqli));
}

/*
 * Make a client's license from a template - its name, version, summary, type,
 * license type and notes, its vendor (softwareVendorFromTemplate() - made for
 * the client when it hasn't got it, with $make_vendor) - with what $post
 * gives it: seats (the template's when not posted at all; blank is no
 * limit), key, purchase_reference, purchase and expire (Y-m-d - blank for the
 * template's term from the purchase date). It remembers the template. The new
 * license's id, or 0 when the template is gone or archived; $vendor_made says
 * when a vendor was made for it.
 */
function softwareTemplateMakeLicense($software_template_id, $client_id, array $post = [], $make_vendor = true, &$vendor_made = false) {
    global $mysqli;
    $vendor_made = false;
    $row = softwareTemplateRow($software_template_id);
    if (!$row || $row['software_template_archived_at']) {
        return 0;
    }
    $client_id = intval($client_id);
    $seats = array_key_exists('seats', $post) ? trim((string) $post['seats']) : (string) $row['software_template_seats'];
    $expire = trim((string) ($post['expire'] ?? ''));
    $set = softwareFormSet([
        'name' => $row['software_template_name'],
        'version' => $row['software_template_version'],
        'description' => $row['software_template_description'],
        'type' => $row['software_template_type'],
        'license_type' => $row['software_template_license_type'],
        'notes' => $row['software_template_notes'],
        'seats' => $seats,
        'key' => $post['key'] ?? '',
        'purchase_reference' => $post['purchase_reference'] ?? '',
        'purchase' => $post['purchase'] ?? '',
        'expire' => $expire !== '' ? $expire : softwareTemplateRenewal($row['software_template_term'], $post['purchase'] ?? ''),
        'vendor' => softwareVendorFromTemplate($row['software_template_vendor_template_id'], $client_id, $make_vendor, $vendor_made),
    ], $client_id);
    mysqli_query($mysqli, "INSERT INTO software SET $set, software_client_id = $client_id, software_template_id = " . intval($row['software_template_id']));
    return intval(mysqli_insert_id($mysqli));
}

/*
 * Licenses from a template for several clients at once, each with the same
 * $post (softwareTemplateMakeLicense()). With $skip_existing, a client with a
 * current license from it is passed over. ['made' => [client id => license
 * id], 'skipped' => [client id => name], 'vendors' => [client id => true] for
 * the clients a vendor was made for] - only current clients.
 */
function softwareTemplateMakeForClients($software_template_id, array $client_ids, array $post, $skip_existing = true, $make_vendor = true) {
    global $mysqli;
    $software_template_id = intval($software_template_id);
    $result = ['made' => [], 'skipped' => [], 'vendors' => []];
    $client_ids = array_values(array_unique(array_filter(array_map('intval', $client_ids))));
    if (!$client_ids) {
        return $result;
    }
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, EXISTS (SELECT 1 FROM software WHERE software_client_id = client_id
            AND software_template_id = $software_template_id AND software_archived_at IS NULL) AS has_one
        FROM clients WHERE client_archived_at IS NULL AND client_id IN (" . implode(',', $client_ids) . ") ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_id = intval($row['client_id']);
        if ($skip_existing && intval($row['has_one'])) {
            $result['skipped'][$client_id] = (string) $row['client_name'];
            continue;
        }
        $software_id = softwareTemplateMakeLicense($software_template_id, $client_id, $post, $make_vendor, $vendor_made);
        if (!$software_id) {
            break;
        }
        $result['made'][$client_id] = $software_id;
        if ($vendor_made) {
            $result['vendors'][$client_id] = true;
        }
    }
    return $result;
}
