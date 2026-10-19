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
 * From database 3.3.0 on a template says who should have it - every client, or
 * the clients with one of its tags - so the clients missing it show on its
 * page, on their Licenses and on their overview. Its licenses can be looked
 * after across clients from its page: their seats trued up to what is in use,
 * renewed together, and their seats billed on each client's recurring invoice
 * as the product it is sold as.
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
            software_template_vendor_template_id, software_template_product_id, software_template_required, software_template_created_at, software_template_updated_at,
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

/* ---------------------------------------------------------------- who should have one */

/** Who should have a license from a template (software_template_required). */
const SOFTWARE_TEMPLATE_REQUIRED_NONE = 0;
const SOFTWARE_TEMPLATE_REQUIRED_EVERY = 1;
const SOFTWARE_TEMPLATE_REQUIRED_TAGGED = 2;

/** The client tags templates are required by - [template id => [tag id => tag name]], in name order. */
function softwareTemplateRequiredTags(array $software_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $software_template_ids)));
    $tags = array_fill_keys($ids, []);
    if (!$ids) {
        return $tags;
    }
    $sql = mysqli_query($mysqli, "SELECT software_template_id, tags.tag_id, tag_name FROM software_template_required_tags
        JOIN tags ON tags.tag_id = software_template_required_tags.tag_id
        WHERE software_template_id IN (" . implode(',', $ids) . ") ORDER BY tag_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tags[intval($row['software_template_id'])][intval($row['tag_id'])] = (string) $row['tag_name'];
    }
    return $tags;
}

/*
 * Set who should have a license from a template - $required one of the
 * SOFTWARE_TEMPLATE_REQUIRED_ values, $tag_ids the client tags for TAGGED.
 * Not a change to what a license starts with, so when it last changed stays
 * as it was.
 */
function softwareTemplateSetRequired($software_template_id, $required, array $tag_ids) {
    global $mysqli;
    $software_template_id = intval($software_template_id);
    $required = in_array(intval($required), [SOFTWARE_TEMPLATE_REQUIRED_EVERY, SOFTWARE_TEMPLATE_REQUIRED_TAGGED], true) ? intval($required) : SOFTWARE_TEMPLATE_REQUIRED_NONE;
    mysqli_query($mysqli, "UPDATE software_templates SET software_template_required = $required, software_template_updated_at = software_template_updated_at
        WHERE software_template_id = $software_template_id");
    // Client tags only - any others are left out
    $tag_ids = $required === SOFTWARE_TEMPLATE_REQUIRED_TAGGED ? array_values(array_unique(array_filter(array_map('intval', $tag_ids)))) : [];
    mysqli_query($mysqli, "DELETE FROM software_template_required_tags WHERE software_template_id = $software_template_id");
    if ($tag_ids) {
        mysqli_query($mysqli, "INSERT INTO software_template_required_tags (software_template_id, tag_id)
            SELECT $software_template_id, tag_id FROM tags WHERE tag_type = 1 AND tag_id IN (" . implode(',', $tag_ids) . ")");
    }
}

/*
 * The clients a template is required of, as SQL on clients: current clients,
 * not leads - every one, or the ones with any of its tags (none when it has
 * no tags left, or isn't required).
 */
function softwareTemplateScopeSql($required, array $tag_ids) {
    $sql = "clients.client_archived_at IS NULL AND clients.client_lead = 0";
    if (intval($required) === SOFTWARE_TEMPLATE_REQUIRED_EVERY) {
        return $sql;
    }
    $tag_ids = array_values(array_filter(array_map('intval', $tag_ids)));
    if (intval($required) !== SOFTWARE_TEMPLATE_REQUIRED_TAGGED || !$tag_ids) {
        return '0';
    }
    return "$sql AND EXISTS (SELECT 1 FROM client_tags WHERE client_tags.client_id = clients.client_id AND client_tags.tag_id IN (" . implode(',', $tag_ids) . "))";
}

/** Who a template is required of, in words - '' when it isn't. */
function softwareTemplateScopeText($required, array $tags) {
    if (intval($required) === SOFTWARE_TEMPLATE_REQUIRED_EVERY) {
        return 'Every client';
    }
    if (intval($required) !== SOFTWARE_TEMPLATE_REQUIRED_TAGGED) {
        return '';
    }
    return $tags ? 'Clients tagged ' . implode(' or ', $tags) : 'Clients with a tag - none picked';
}

/*
 * How far a required template reaches - ['clients' => how many it is
 * required of, 'covered' => how many of them have a current license from it,
 * 'missing' => [client id => name] the rest, by name].
 */
function softwareTemplateCoverage($software_template_id, $required, array $tag_ids) {
    global $mysqli;
    $software_template_id = intval($software_template_id);
    $coverage = ['clients' => 0, 'covered' => 0, 'missing' => []];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, EXISTS (SELECT 1 FROM software WHERE software_client_id = clients.client_id
            AND software_template_id = $software_template_id AND software_archived_at IS NULL) AS covered
        FROM clients WHERE " . softwareTemplateScopeSql($required, $tag_ids) . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $coverage['clients']++;
        if (intval($row['covered'])) {
            $coverage['covered']++;
        } else {
            $coverage['missing'][intval($row['client_id'])] = (string) $row['client_name'];
        }
    }
    return $coverage;
}

/*
 * The required templates a client has no current license from - [template
 * id => name]. None for an archived client or a lead.
 */
function softwareTemplatesMissingFor($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $missing = [];
    $sql = mysqli_query($mysqli, "SELECT software_template_id, software_template_name FROM software_templates
        WHERE software_template_archived_at IS NULL AND software_template_required IN (" . SOFTWARE_TEMPLATE_REQUIRED_EVERY . ", " . SOFTWARE_TEMPLATE_REQUIRED_TAGGED . ")
        AND EXISTS (SELECT 1 FROM clients WHERE clients.client_id = $client_id AND clients.client_archived_at IS NULL AND clients.client_lead = 0)
        AND (software_template_required = " . SOFTWARE_TEMPLATE_REQUIRED_EVERY . " OR EXISTS (SELECT 1 FROM software_template_required_tags rt
            JOIN client_tags ON client_tags.tag_id = rt.tag_id AND client_tags.client_id = $client_id
            WHERE rt.software_template_id = software_templates.software_template_id))
        AND NOT EXISTS (SELECT 1 FROM software WHERE software_client_id = $client_id AND software.software_template_id = software_templates.software_template_id AND software_archived_at IS NULL)
        ORDER BY software_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $missing[intval($row['software_template_id'])] = (string) $row['software_template_name'];
    }
    return $missing;
}

/* ---------------------------------------------------------------- its licenses across clients */

/*
 * The current licenses made from a template, for current clients, by client
 * name - [software id => row]: the license and its client, 'seats'
 * (softwareSeats()), 'renewal' (softwareRenewal()) and 'billing'
 * (softwareTemplateBilling(), with the template's $product_id).
 */
function softwareTemplateLicenses($software_template_id, $product_id = 0) {
    global $mysqli;
    $software_template_id = intval($software_template_id);
    $licenses = [];
    $sql = mysqli_query($mysqli, "SELECT software_id, software_name, software_seats, software_expire, software_created_at, software_client_id, client_name, client_abbreviation,
            " . softwareSeatsUsedSql('software.software_id') . " AS seats_used
        FROM software JOIN clients ON client_id = software_client_id AND client_archived_at IS NULL
        WHERE software_template_id = $software_template_id AND software_archived_at IS NULL
        ORDER BY client_name ASC, software_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['seats'] = softwareSeats($row['seats_used'], $row['software_seats']);
        $row['renewal'] = softwareRenewal($row['software_expire']);
        $licenses[intval($row['software_id'])] = $row;
    }
    $billing = softwareTemplateBilling($licenses, $product_id);
    foreach ($licenses as $software_id => $row) {
        $licenses[$software_id]['billing'] = $billing[$software_id] ?? null;
    }
    return $licenses;
}

/*
 * How each license is billed on its client's recurring invoices (not
 * archived) - [software id => ['how' => 'seats' (a line counts its seats) or
 * 'product' (a line for $product_id, its quantity typed in), 'item_id',
 * 'recurring_invoice_id', 'number', 'paused', 'quantity', 'count' ('used' or
 * 'total', for seats), 'drift' (a typed quantity that isn't its seats)]]. Not
 * there when it isn't billed. $licenses: rows with software_id,
 * software_client_id, software_seats and seats_used.
 */
function softwareTemplateBilling(array $licenses, $product_id = 0) {
    global $mysqli;
    $product_id = intval($product_id);
    $billing = [];
    $by_client = [];
    foreach ($licenses as $row) {
        $by_client[intval($row['software_client_id'])][] = intval($row['software_id']);
    }
    if (!$by_client) {
        return $billing;
    }
    $sql = mysqli_query($mysqli, "SELECT item_id, item_quantity, item_seat_source, item_seat_filter, item_product_id, recurring_invoice_id, recurring_invoice_prefix,
            recurring_invoice_number, recurring_invoice_status, recurring_invoice_client_id
        FROM recurring_invoice_items JOIN recurring_invoices ON recurring_invoice_id = item_recurring_invoice_id AND recurring_invoice_archived_at IS NULL
        WHERE recurring_invoice_client_id IN (" . implode(',', array_keys($by_client)) . ")
        AND (item_seat_source = 'license'" . ($product_id ? " OR item_product_id = $product_id" : '') . ")
        ORDER BY recurring_invoice_status DESC, recurring_invoice_id ASC, item_order ASC, item_id ASC");
    $product_lines = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $line = [
            'item_id' => intval($row['item_id']), 'recurring_invoice_id' => intval($row['recurring_invoice_id']),
            'number' => (string) $row['recurring_invoice_prefix'] . $row['recurring_invoice_number'], 'paused' => intval($row['recurring_invoice_status']) !== 1,
            'quantity' => floatval($row['item_quantity']),
        ];
        if ($row['item_seat_source'] === 'license') {
            $filter = recurringSeatFilter('license', $row['item_seat_filter']);
            $software_id = intval($filter['software_id']);
            if (in_array($software_id, $by_client[intval($row['recurring_invoice_client_id'])], true) && !isset($billing[$software_id])) {
                $billing[$software_id] = $line + ['how' => 'seats', 'count' => $filter['count'], 'drift' => false];
            }
        } elseif ($product_id && intval($row['item_product_id']) === $product_id) {
            $product_lines[intval($row['recurring_invoice_client_id'])] = $product_lines[intval($row['recurring_invoice_client_id'])] ?? $line;
        }
    }
    // A line for the product, its quantity typed in, bills the client's licenses not counted by a line of their own
    foreach ($licenses as $row) {
        $software_id = intval($row['software_id']);
        $line = $product_lines[intval($row['software_client_id'])] ?? null;
        if (isset($billing[$software_id]) || !$line) {
            continue;
        }
        $seats = intval($row['software_seats']) > 0 ? intval($row['software_seats']) : intval($row['seats_used']);
        $billing[$software_id] = $line + ['how' => 'product', 'count' => '', 'drift' => round($line['quantity'], 2) != $seats];
    }
    return $billing;
}

/*
 * The views of a template's licenses on its page - key => [label, icon,
 * title, test(row)]: all of them, over seats, spare seats, renewing in 30
 * days, past their renewal date, and - when it is sold as a product - not
 * billed.
 */
function softwareTemplateLicenseViews($has_product) {
    $views = [
        '' => ['label' => 'All', 'icon' => 'fa-list', 'title' => 'Every current license made from it', 'test' => function () {
            return true;
        }],
        'over' => ['label' => 'Over seats', 'icon' => 'fa-exclamation-triangle', 'title' => 'More devices and users than seats bought - a true-up to bill', 'test' => function ($row) {
            return $row['seats']['state'] === 'over';
        }],
        'spare' => ['label' => 'Spare seats', 'icon' => 'fa-chair', 'title' => 'Seats bought that nobody is on - seats to free', 'test' => function ($row) {
            return $row['seats']['total'] > 0 && $row['seats']['used'] < $row['seats']['total'];
        }],
        'renewing' => ['label' => 'Renewing soon', 'icon' => 'fa-hourglass-half', 'title' => 'Renewing in the next 30 days', 'test' => function ($row) {
            return $row['renewal'] && $row['renewal']['state'] === 'soon';
        }],
        'expired' => ['label' => 'Expired', 'icon' => 'fa-calendar-times', 'title' => 'Past their renewal date', 'test' => function ($row) {
            return $row['renewal'] && $row['renewal']['state'] === 'expired';
        }],
    ];
    if ($has_product) {
        $views['unbilled'] = ['label' => 'Not billed', 'icon' => 'fa-file-invoice-dollar', 'title' => "Not on the client's recurring invoice", 'test' => function ($row) {
            return empty($row['billing']);
        }];
    }
    return $views;
}

/*
 * True up licenses from a template - [software id => seats] (blank for no
 * limit). Only current licenses from it whose seats change. What changed -
 * [software id => ['name', 'client_id', 'client_name', 'from', 'to']], seats
 * as numbers or null.
 */
function softwareTemplateTrueUp($software_template_id, array $seats_by_id) {
    global $mysqli;
    $software_template_id = intval($software_template_id);
    $changes = [];
    foreach ($seats_by_id as $software_id => $seats) {
        $software_id = intval($software_id);
        $seats = trim((string) $seats);
        $to = $seats === '' ? null : max(0, min(1000000, intval($seats)));
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software_name, software_seats, software_client_id, client_name FROM software
            JOIN clients ON client_id = software_client_id AND client_archived_at IS NULL
            WHERE software_id = $software_id AND software_template_id = $software_template_id AND software_archived_at IS NULL"));
        if (!$row) {
            continue;
        }
        $from = $row['software_seats'] === null ? null : intval($row['software_seats']);
        if ($from === $to) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE software SET software_seats = " . ($to === null ? 'NULL' : $to) . " WHERE software_id = $software_id");
        $changes[$software_id] = ['name' => (string) $row['software_name'], 'client_id' => intval($row['software_client_id']), 'client_name' => (string) $row['client_name'], 'from' => $from, 'to' => $to];
    }
    return $changes;
}

/* Seats in words - "12 seats", "no seat limit". */
function softwareTemplateSeatsText($seats) {
    return $seats === null ? 'no seat limit' : intval($seats) . ' seat' . (intval($seats) === 1 ? '' : 's');
}

/*
 * The recurring invoices licenses can be billed on - [client id =>
 * [recurring invoice id => row with 'number']], each client's not archived,
 * the active ones first.
 */
function softwareTemplateRecurringFor(array $client_ids) {
    global $mysqli;
    $client_ids = array_values(array_unique(array_filter(array_map('intval', $client_ids))));
    $recurring = array_fill_keys($client_ids, []);
    if (!$client_ids) {
        return $recurring;
    }
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_frequency,
            recurring_invoice_status, recurring_invoice_amount, recurring_invoice_currency_code, recurring_invoice_client_id
        FROM recurring_invoices WHERE recurring_invoice_archived_at IS NULL AND recurring_invoice_client_id IN (" . implode(',', $client_ids) . ")
        ORDER BY recurring_invoice_status DESC, recurring_invoice_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['number'] = (string) $row['recurring_invoice_prefix'] . $row['recurring_invoice_number'];
        $recurring[intval($row['recurring_invoice_client_id'])][intval($row['recurring_invoice_id'])] = $row;
    }
    return $recurring;
}

/*
 * Bill a license from a template on one of its client's recurring invoices:
 * a line for the template's product - its name, description, price and tax -
 * counting the license's seats bought ($count 'total') or in use ('used'),
 * so it follows them from then on. One with no seat limit counts the seats in
 * use - it has none bought to count. The line's id, or null with $error.
 */
function softwareTemplateBill($software_template_id, $software_id, $recurring_invoice_id, $count, &$error = '') {
    global $mysqli;
    $error = '';
    $template = softwareTemplateRow($software_template_id);
    $product = $template && intval($template['software_template_product_id']) ? productRow($template['software_template_product_id']) : null;
    if (!$product) {
        $error = 'It is not sold as a product - pick the product on the template first.';
        return null;
    }
    if ($product['product_archived_at']) {
        $error = 'The product it is sold as is archived - restore it, or pick another on the template.';
        return null;
    }
    $software_id = intval($software_id);
    $recurring_invoice_id = intval($recurring_invoice_id);
    $license = mysqli_fetch_row(mysqli_query($mysqli, "SELECT software_client_id, software_seats FROM software WHERE software_id = $software_id
        AND software_template_id = " . intval($template['software_template_id']) . " AND software_archived_at IS NULL"));
    $client_id = intval($license[0] ?? 0);
    if (intval($license[1] ?? 0) <= 0) {
        $count = 'used';
    }
    if (!$client_id) {
        $error = 'That license is gone, or not made from this template.';
        return null;
    }
    if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id
        AND recurring_invoice_client_id = $client_id AND recurring_invoice_archived_at IS NULL"))) {
        $error = "That isn't one of the client's recurring invoices.";
        return null;
    }
    return billingItemSave('recurring', $recurring_invoice_id, [
        'name' => $product['product_name'], 'description' => (string) $product['product_description'],
        'price' => $product['product_price'], 'tax_id' => intval($product['product_tax_id']), 'product_id' => intval($product['product_id']),
        'seat_source' => 'license', 'seat_software_id' => $software_id, 'seat_count' => $count === 'total' ? 'total' : 'used', 'seat_minimum' => 0,
    ], 0, $error);
}
