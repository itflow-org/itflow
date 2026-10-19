<?php

/*
 * ITFlow - vendor templates (Admin > Vendor Templates): the vendors an MSP's
 * clients use again and again - the ISPs, the hardware makers, the
 * distributors, the line-of-business software - set up once with their
 * support line, hours, SLA, support email and site, ready to add to any
 * client. A vendor made from one gets its fields and remembers which template
 * it came from (vendors.vendor_template_id), so each template's use is
 * counted: how many clients have a vendor from it, and when one was last
 * added. License templates take their vendor from one
 * (software_templates.software_template_vendor_template_id).
 *
 * From database 3.3.4 on a template says who made it and last changed it.
 *
 * Shared by admin/vendor_templates.php, admin/vendor_template.php,
 * admin/modals/vendor_template/* and admin/post/vendor_template.php. A
 * vendor is made from one on the agent side by add_vendor,
 * add_vendor_from_template and add_vendor_template_to_clients
 * (agent/post/vendor.php, with vendorTemplateRow() and
 * vendorTemplateFormFields() in functions/vendor.php).
 */

/*
 * What a template carries, as it shows on its page and in the window - key
 * => label and icon, in order. 'phone' is the support line with its country
 * code and extension.
 */
function vendorTemplateFieldLabels() {
    return [
        'name' => ['label' => 'Name', 'icon' => 'fa-building'],
        'description' => ['label' => 'What they are', 'icon' => 'fa-align-left'],
        'phone' => ['label' => 'Support line', 'icon' => 'fa-phone'],
        'hours' => ['label' => 'Support hours', 'icon' => 'fa-clock'],
        'sla' => ['label' => 'SLA', 'icon' => 'fa-handshake'],
        'email' => ['label' => 'Support email', 'icon' => 'fa-envelope'],
        'website' => ['label' => 'Support site', 'icon' => 'fa-globe'],
        'account_number' => ['label' => 'Account number', 'icon' => 'fa-fingerprint'],
        'code' => ['label' => 'PIN', 'icon' => 'fa-key'],
        'contact_name' => ['label' => 'Account manager', 'icon' => 'fa-user-tie'],
        'notes' => ['label' => 'Notes', 'icon' => 'fa-sticky-note'],
    ];
}

/*
 * Each field's columns - on the template, and on a vendor made from it. The
 * support line is three: country code, number and extension.
 */
function vendorTemplateColumns() {
    return [
        'name' => [['vendor_template_name', 'vendor_name']],
        'description' => [['vendor_template_description', 'vendor_description']],
        'phone' => [['vendor_template_phone_country_code', 'vendor_phone_country_code'], ['vendor_template_phone', 'vendor_phone'], ['vendor_template_extension', 'vendor_extension']],
        'hours' => [['vendor_template_hours', 'vendor_hours']],
        'sla' => [['vendor_template_sla', 'vendor_sla']],
        'email' => [['vendor_template_email', 'vendor_email']],
        'website' => [['vendor_template_website', 'vendor_website']],
        'account_number' => [['vendor_template_account_number', 'vendor_account_number']],
        'code' => [['vendor_template_code', 'vendor_code']],
        'contact_name' => [['vendor_template_contact_name', 'vendor_contact_name']],
        'notes' => [['vendor_template_notes', 'vendor_notes']],
    ];
}

/** One template, archived or not, with who made it and last changed it - raw, or null. */
function vendorTemplateInfo($vendor_template_id) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    if ($vendor_template_id <= 0) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT vendor_templates.*, creator.user_name AS created_by_name, updater.user_name AS updated_by_name
        FROM vendor_templates
        LEFT JOIN users creator ON creator.user_id = vendor_template_created_by AND vendor_template_created_by > 0
        LEFT JOIN users updater ON updater.user_id = vendor_template_updated_by AND vendor_template_updated_by > 0
        WHERE vendor_template_id = $vendor_template_id"));
    return $row ?: null;
}

/*
 * How the templates are used - template id => clients (current clients with a
 * current vendor from it), vendors (those vendors), ours (our own vendors from
 * it) and last (when the latest was added). Ones not used aren't in it.
 */
function vendorTemplateUsage(array $vendor_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $vendor_template_ids)));
    $usage = [];
    if (!$ids) {
        return $usage;
    }
    $sql = mysqli_query($mysqli, "SELECT vendor_template_id, COUNT(DISTINCT NULLIF(vendor_client_id, 0)) AS clients, SUM(vendor_client_id > 0) AS vendors,
            SUM(vendor_client_id = 0) AS ours, MAX(vendor_created_at) AS last
        FROM vendors LEFT JOIN clients ON client_id = vendor_client_id AND vendor_client_id > 0
        WHERE vendor_template_id IN (" . implode(',', $ids) . ") AND vendor_archived_at IS NULL AND (vendor_client_id = 0 OR client_archived_at IS NULL AND client_id IS NOT NULL)
        GROUP BY vendor_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage[intval($row['vendor_template_id'])] = ['clients' => intval($row['clients']), 'vendors' => intval($row['vendors']), 'ours' => intval($row['ours']), 'last' => $row['last']];
    }
    return $usage;
}

/*
 * The window's fields, checked - or null with $error saying why. Raw values:
 * name, description, phone_country_code, phone and extension (digits),
 * hours, sla, email, website (without its scheme), account_number, code,
 * contact_name and notes - as vendorFormSet() reads a vendor's.
 */
function vendorTemplateFields(array $post, &$error = '') {
    $error = '';
    $line = function ($key, $length = 200) use ($post) {
        $value = is_scalar($post[$key] ?? null) ? trim(preg_replace('/\s+/u', ' ', (string) $post[$key])) : '';
        return mb_substr($value, 0, $length);
    };
    $digits = function ($key, $length = 200) use ($post) {
        return substr(preg_replace('/[^0-9]/', '', is_scalar($post[$key] ?? null) ? (string) $post[$key] : ''), 0, $length);
    };
    $name = is_scalar($post['name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', (string) $post['name'])) : '';
    if ($name === '') {
        $error = 'A vendor template needs a name';
        return null;
    }
    if (mb_strlen($name) > 200) {
        $error = 'Keep the name to 200 characters';
        return null;
    }
    $email = $line('email');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "The support email isn't an email address";
        return null;
    }
    $phone = $digits('phone');
    return [
        'name' => $name,
        'description' => $line('description'),
        'phone_country_code' => $phone !== '' ? $digits('phone_country_code', 10) : '',
        'phone' => $phone,
        'extension' => $digits('extension'),
        'hours' => $line('hours'),
        'sla' => $line('sla'),
        'email' => $email,
        'website' => mb_substr(rtrim(preg_replace('#^https?://#i', '', $line('website')), '/'), 0, 200),
        'account_number' => $line('account_number'),
        'code' => $line('code'),
        'contact_name' => $line('contact_name'),
        'notes' => is_scalar($post['notes'] ?? null) ? trim((string) $post['notes']) : '',
    ];
}

/** A template's fields as vendorTemplateFields() gives them - from its row, for comparing and copying. */
function vendorTemplateRowFields(array $row) {
    return [
        'name' => (string) $row['vendor_template_name'],
        'description' => (string) $row['vendor_template_description'],
        'phone_country_code' => (string) $row['vendor_template_phone_country_code'],
        'phone' => (string) $row['vendor_template_phone'],
        'extension' => (string) $row['vendor_template_extension'],
        'hours' => (string) $row['vendor_template_hours'],
        'sla' => (string) $row['vendor_template_sla'],
        'email' => (string) $row['vendor_template_email'],
        'website' => (string) $row['vendor_template_website'],
        'account_number' => (string) $row['vendor_template_account_number'],
        'code' => (string) $row['vendor_template_code'],
        'contact_name' => (string) $row['vendor_template_contact_name'],
        'notes' => (string) $row['vendor_template_notes'],
    ];
}

/* Save a template's fields (vendorTemplateFields()) - a new one with $vendor_template_id 0. Its id. */
function vendorTemplateSave($vendor_template_id, array $f) {
    global $mysqli, $session_user_id;
    $vendor_template_id = intval($vendor_template_id);
    $user_id = intval($session_user_id ?? 0);
    $set = [];
    foreach (vendorTemplateColumns() as $key => $pairs) {
        foreach ($pairs as [$template_column]) {
            $field = substr($template_column, strlen('vendor_template_'));
            $set[] = "$template_column = '" . escapeSql((string) ($f[$field] ?? '')) . "'";
        }
    }
    $set = implode(', ', $set);
    if ($vendor_template_id) {
        mysqli_query($mysqli, "UPDATE vendor_templates SET $set, vendor_template_updated_by = $user_id WHERE vendor_template_id = $vendor_template_id");
        return $vendor_template_id;
    }
    mysqli_query($mysqli, "INSERT INTO vendor_templates SET $set, vendor_template_created_by = $user_id");
    return intval(mysqli_insert_id($mysqli));
}

/** What changed between two sets of fields - the labels, in order: ['Support line', 'Support hours']. */
function vendorTemplateChanged(array $before, array $after) {
    $changed = [];
    foreach (vendorTemplateColumns() as $key => $pairs) {
        foreach ($pairs as [$template_column]) {
            $field = substr($template_column, strlen('vendor_template_'));
            if ((string) ($before[$field] ?? '') !== (string) ($after[$field] ?? '')) {
                $changed[$key] = vendorTemplateFieldLabels()[$key]['label'];
                break;
            }
        }
    }
    return $changed;
}

/* A new template starting from this one - "Copy of". Its id, or 0. */
function vendorTemplateCopy($vendor_template_id) {
    $row = vendorTemplateInfo($vendor_template_id);
    if (!$row) {
        return 0;
    }
    $fields = vendorTemplateRowFields($row);
    $fields['name'] = mb_substr('Copy of ' . $fields['name'], 0, 200);
    return vendorTemplateSave(0, $fields);
}

/*
 * Delete a template. The vendors made from it stay, no longer linked to it,
 * and license templates that took their vendor from it have none.
 */
function vendorTemplateDelete($vendor_template_id) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    mysqli_query($mysqli, "UPDATE vendors SET vendor_template_id = 0, vendor_updated_at = vendor_updated_at WHERE vendor_template_id = $vendor_template_id");
    mysqli_query($mysqli, "UPDATE software_templates SET software_template_vendor_template_id = 0, software_template_updated_at = software_template_updated_at WHERE software_template_vendor_template_id = $vendor_template_id");
    mysqli_query($mysqli, "DELETE FROM vendor_templates WHERE vendor_template_id = $vendor_template_id");
}

/*
 * Put the template's fields picked ($keys, from vendorTemplateColumns()) on
 * every vendor made from it - for Change them on the client vendors too.
 * The vendors changed: vendor id => ['name', 'client_id'].
 */
function vendorTemplatePush($vendor_template_id, array $keys) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $row = vendorTemplateInfo($vendor_template_id);
    $columns = vendorTemplateColumns();
    $keys = array_values(array_intersect(array_keys($columns), $keys));
    if (!$row || !$keys) {
        return [];
    }
    $set = [];
    foreach ($keys as $key) {
        foreach ($columns[$key] as [$template_column, $vendor_column]) {
            $set[] = "$vendor_column = '" . escapeSql((string) $row[$template_column]) . "'";
        }
    }
    $vendors = [];
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_client_id FROM vendors WHERE vendor_template_id = $vendor_template_id");
    while ($vendor = mysqli_fetch_assoc($sql)) {
        $vendors[intval($vendor['vendor_id'])] = ['name' => $vendor['vendor_name'], 'client_id' => intval($vendor['vendor_client_id'])];
    }
    if ($vendors) {
        mysqli_query($mysqli, "UPDATE vendors SET " . implode(', ', $set) . " WHERE vendor_template_id = $vendor_template_id");
    }
    return $vendors;
}

/*
 * The vendors made from a template, for its page - current ones first, by
 * client: vendor id => vendor_id, vendor_name, client_id (0 for ours),
 * client_name, client_abbreviation, account_number, code, created_at,
 * archived (the vendor), client_archived.
 */
function vendorTemplateVendors($vendor_template_id) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $vendors = [];
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_client_id, vendor_account_number, vendor_code, vendor_created_at, vendor_archived_at,
            client_name, client_abbreviation, client_archived_at
        FROM vendors LEFT JOIN clients ON client_id = vendor_client_id AND vendor_client_id > 0
        WHERE vendor_template_id = $vendor_template_id
        ORDER BY vendor_archived_at IS NOT NULL, client_archived_at IS NOT NULL, vendor_client_id = 0, client_name ASC, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendors[intval($row['vendor_id'])] = [
            'vendor_id' => intval($row['vendor_id']),
            'vendor_name' => (string) $row['vendor_name'],
            'client_id' => intval($row['vendor_client_id']),
            'client_name' => (string) $row['client_name'],
            'client_abbreviation' => (string) $row['client_abbreviation'],
            'account_number' => trim((string) $row['vendor_account_number']),
            'code' => trim((string) $row['vendor_code']),
            'created_at' => $row['vendor_created_at'],
            'archived' => !empty($row['vendor_archived_at']),
            'client_archived' => !empty($row['client_archived_at']),
        ];
    }
    return $vendors;
}

/** The license templates that take their vendor from it - id => name, archived. */
function vendorTemplateLicenseTemplates($vendor_template_id) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $templates = [];
    $sql = mysqli_query($mysqli, "SELECT software_template_id, software_template_name, software_template_archived_at FROM software_templates
        WHERE software_template_vendor_template_id = $vendor_template_id ORDER BY software_template_archived_at IS NOT NULL, software_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $templates[intval($row['software_template_id'])] = ['name' => $row['software_template_name'], 'archived' => !empty($row['software_template_archived_at'])];
    }
    return $templates;
}

/** A template's tile - its initials on the grey of the other template lists. $size as vendorTile(). */
function vendorTemplateTile($name, $size = '') {
    $size_class = in_array($size, ['xs', 'sm', 'lg'], true) ? " itflow-asset-icon-$size itflow-vendor-tile-$size" : '';
    return '<span class="itflow-asset-icon itflow-vendor-tile itflow-asset-tone-other' . $size_class . '" aria-hidden="true">' . escapeHtml(vendorInitials($name)) . '</span>';
}

/** The support line as it reads - "(412) 555-0100, then 2" - or ''. Raw in, escaped out. */
function vendorTemplatePhoneHtml($phone, $country_code, $extension) {
    if (preg_replace('/[^0-9]/', '', (string) $phone) === '') {
        return '';
    }
    $html = escapeHtml(formatPhoneNumber($phone, $country_code));
    $dial = vendorDialNumber($phone, $country_code);
    if ($dial !== '') {
        $html = '<a href="tel:' . escapeHtml($dial) . '">' . $html . '</a>';
    }
    return $html . (trim((string) $extension) !== '' ? ' <span class="text-body-secondary">then ' . escapeHtml($extension) . '</span>' : '');
}
