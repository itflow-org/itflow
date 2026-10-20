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
 * From database 3.3.6 on a template - and a vendor - has a status page and
 * the support portal cases are opened in, and a template the account
 * manager's line and email, as a vendor has them. Templates go to and from a
 * file in functions/vendor_template_transfer.php.
 *
 * From database 3.3.5 on a template says who should have it - every client,
 * or the clients with one of its tags - so the clients missing it show on its
 * page, on their Vendors and on their overview. The vendors made from it are
 * kept in step with it: the fields every client shares (VENDOR_TEMPLATE_SHARED
 * - not the account number, PIN or account manager, which are each client's
 * own) are compared with the template's, a vendor that differs is out of step,
 * and it can be brought in line field by field. Saving a template changes the
 * vendors that still had what it said before; vendors made by hand with its
 * name or support site can be linked to it.
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
        'status_page' => ['label' => 'Status page', 'icon' => 'fa-heartbeat'],
        'portal' => ['label' => 'Support portal', 'icon' => 'fa-door-open'],
        'account_number' => ['label' => 'Account number', 'icon' => 'fa-fingerprint'],
        'code' => ['label' => 'PIN', 'icon' => 'fa-key'],
        'contact_name' => ['label' => 'Account manager', 'icon' => 'fa-user-tie'],
        'contact_phone' => ['label' => "Account manager's line", 'icon' => 'fa-phone-alt'],
        'contact_email' => ['label' => "Account manager's email", 'icon' => 'fa-at'],
        'notes' => ['label' => 'Notes', 'icon' => 'fa-sticky-note'],
    ];
}

/*
 * Each field's columns - on the template, and on a vendor made from it. The
 * support line is three: country code, number and extension; the account
 * manager's line two.
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
        'status_page' => [['vendor_template_status_page', 'vendor_status_page']],
        'portal' => [['vendor_template_portal', 'vendor_portal']],
        'account_number' => [['vendor_template_account_number', 'vendor_account_number']],
        'code' => [['vendor_template_code', 'vendor_code']],
        'contact_name' => [['vendor_template_contact_name', 'vendor_contact_name']],
        'contact_phone' => [['vendor_template_contact_phone_country_code', 'vendor_contact_phone_country_code'], ['vendor_template_contact_phone', 'vendor_contact_phone']],
        'contact_email' => [['vendor_template_contact_email', 'vendor_contact_email']],
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
 * hours, sla, email, website, status_page and portal (without their scheme
 * or a trailing slash), account_number, code, contact_name,
 * contact_phone_country_code and contact_phone (digits), contact_email and
 * notes - as vendorFormSet() reads a vendor's.
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
    $contact_email = $line('contact_email');
    if ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = "The account manager's email isn't an email address";
        return null;
    }
    $site = function ($key) use ($line) {
        return mb_substr(rtrim(preg_replace('#^https?://#i', '', $line($key)), '/'), 0, 200);
    };
    $phone = $digits('phone');
    $contact_phone = $digits('contact_phone');
    return [
        'name' => $name,
        'description' => $line('description'),
        'phone_country_code' => $phone !== '' ? $digits('phone_country_code', 10) : '',
        'phone' => $phone,
        'extension' => $digits('extension'),
        'hours' => $line('hours'),
        'sla' => $line('sla'),
        'email' => $email,
        'website' => $site('website'),
        'status_page' => $site('status_page'),
        'portal' => $site('portal'),
        'account_number' => $line('account_number'),
        'code' => $line('code'),
        'contact_name' => $line('contact_name'),
        'contact_phone_country_code' => $contact_phone !== '' ? $digits('contact_phone_country_code', 10) : '',
        'contact_phone' => $contact_phone,
        'contact_email' => $contact_email,
        'notes' => is_scalar($post['notes'] ?? null) ? trim((string) $post['notes']) : '',
    ];
}

/** A template's fields as vendorTemplateFields() gives them - from its row (blank for none), for comparing and copying. */
function vendorTemplateRowFields(array $row) {
    $fields = [];
    foreach (vendorTemplateColumns() as $pairs) {
        foreach ($pairs as [$template_column]) {
            $fields[substr($template_column, strlen('vendor_template_'))] = (string) ($row[$template_column] ?? '');
        }
    }
    return $fields;
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
 * The vendors made from a template, for its page - current ones first, by
 * client: vendor id => vendor_id, vendor_name, client_id (0 for ours),
 * client_name, client_abbreviation, account_number, code, created_at,
 * archived (the vendor), client_archived, differences (key => label, as
 * vendorTemplateDifferences()) and row (the vendor, raw).
 */
function vendorTemplateVendors($vendor_template_id) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $template = vendorTemplateInfo($vendor_template_id) ?: [];
    $vendors = [];
    $sql = mysqli_query($mysqli, "SELECT vendors.*, client_name, client_abbreviation, client_archived_at
        FROM vendors LEFT JOIN clients ON client_id = vendor_client_id AND vendor_client_id > 0
        WHERE vendor_template_id = $vendor_template_id
        ORDER BY vendor_archived_at IS NOT NULL, client_archived_at IS NOT NULL, vendor_client_id = 0, client_name ASC, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendors[intval($row['vendor_id'])] = [
            'differences' => $template ? vendorTemplateDifferences($template, $row) : [],
            'row' => $row,
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

/* ---------------------------------------------------------------- in step */

/** The fields a vendor keeps in step with its template. Its account number, PIN and account manager are its client's own. */
const VENDOR_TEMPLATE_SHARED = ['name', 'description', 'phone', 'hours', 'sla', 'email', 'website', 'status_page', 'portal', 'notes'];

/*
 * A field of a row as it compares - $side 'template' for a template's
 * columns, 'vendor' for a vendor's. Text by its words, whatever the case and
 * spacing; the email by its address; the site, status page and portal
 * without their scheme, www or a trailing slash; notes by their lines; the
 * support line as [country code, number, extension] digits.
 */
function vendorTemplateCompareValue($key, array $row, $side) {
    $index = $side === 'template' ? 0 : 1;
    $values = [];
    foreach (vendorTemplateColumns()[$key] ?? [] as $pair) {
        $values[] = trim(html_entity_decode((string) ($row[$pair[$index]] ?? ''), ENT_QUOTES, 'UTF-8'));
    }
    switch ($key) {
        case 'phone':
            return array_map(function ($value) {
                return preg_replace('/[^0-9]/', '', $value);
            }, $values);
        case 'website':
        case 'status_page':
        case 'portal':
            return mb_strtolower(rtrim(preg_replace(['#^[a-z][a-z0-9+.-]*://#i', '#^www\.#i'], '', $values[0]), '/'));
        case 'notes':
            return trim(preg_replace("/[ \t]+\n/", "\n", str_replace(["\r\n", "\r"], "\n", $values[0])));
        default:
            return mb_strtolower(preg_replace('/\s+/u', ' ', $values[0]));
    }
}

/** Does a template say anything for this field? */
function vendorTemplateHasValue($key, array $template) {
    $value = vendorTemplateCompareValue($key, $template, 'template');
    return $key === 'phone' ? $value[1] !== '' : $value !== '';
}

/*
 * Where a vendor differs from its template - key => label, for the shared
 * fields the template says something for. A support line differs by its
 * number or extension, or its country code when both have one.
 */
function vendorTemplateDifferences(array $template, array $vendor) {
    $labels = vendorTemplateFieldLabels();
    $differences = [];
    foreach (VENDOR_TEMPLATE_SHARED as $key) {
        if (!vendorTemplateHasValue($key, $template)) {
            continue;
        }
        $theirs = vendorTemplateCompareValue($key, $vendor, 'vendor');
        $ours = vendorTemplateCompareValue($key, $template, 'template');
        if ($key === 'phone') {
            $same = $theirs[1] === $ours[1] && $theirs[2] === $ours[2] && ($theirs[0] === '' || $ours[0] === '' || $theirs[0] === $ours[0]);
        } else {
            $same = $theirs === $ours;
        }
        if (!$same) {
            $differences[$key] = $labels[$key]['label'];
        }
    }
    return $differences;
}

/** Field labels as they read in a sentence - "support line, SLA and notes": lower case, but for SLA. */
function vendorTemplateLabelsText(array $labels) {
    $labels = array_values(array_map(function ($label) {
        return preg_match('/^[A-Z]{2,}\b/', $label) ? $label : mb_strtolower($label);
    }, $labels));
    $last = array_pop($labels);
    return $labels ? implode(', ', $labels) . ' and ' . $last : (string) $last;
}

/** A field of a row, as it reads - the support line formatted with its extension. Raw in, escaped out; '' for nothing. */
function vendorTemplateValueHtml($key, array $row, $side) {
    $index = $side === 'template' ? 0 : 1;
    $columns = vendorTemplateColumns()[$key];
    if ($key === 'phone') {
        $values = array_map(function ($pair) use ($row, $index) {
            return (string) ($row[$pair[$index]] ?? '');
        }, $columns);
        return vendorTemplatePhoneHtml($values[1], $values[0], $values[2]);
    }
    $value = trim((string) ($row[$columns[0][$index]] ?? ''));
    if ($value !== '' && in_array($key, ['website', 'status_page', 'portal'], true)) {
        $url = vendorWebsiteUrl($value);
        return $url !== '' ? '<a href="' . escapeHtml($url) . '" target="_blank" rel="noopener noreferrer">' . escapeHtml(vendorWebsiteLabel($value)) . '</a>' : escapeHtml($value);
    }
    return $value === '' ? '' : ($key === 'notes' ? '<span style="white-space: pre-wrap;">' . escapeHtml($value) . '</span>' : escapeHtml($value));
}

/** A template's fields (vendorTemplateFields()) as a row of its columns - for comparing what it said before a save. */
function vendorTemplateFieldsRow(array $fields) {
    $row = [];
    foreach (vendorTemplateColumns() as $pairs) {
        foreach ($pairs as [$template_column]) {
            $row[$template_column] = (string) ($fields[substr($template_column, strlen('vendor_template_'))] ?? '');
        }
    }
    return $row;
}

/* Set these fields of a vendor to the template's - raw template row, keys of VENDOR_TEMPLATE_SHARED. */
function vendorTemplateApply($vendor_id, array $template, array $keys) {
    global $mysqli;
    $set = [];
    foreach (array_intersect(VENDOR_TEMPLATE_SHARED, $keys) as $key) {
        foreach (vendorTemplateColumns()[$key] as [$template_column, $vendor_column]) {
            $set[] = "$vendor_column = '" . escapeSql((string) ($template[$template_column] ?? '')) . "'";
        }
    }
    if ($set) {
        mysqli_query($mysqli, "UPDATE vendors SET " . implode(', ', $set) . " WHERE vendor_id = " . intval($vendor_id));
    }
}

/*
 * After a template is saved: each vendor made from it that still had what
 * it said before - field by field - gets what it says now. A vendor with its
 * own value is left alone, and shows as out of step. A field the template no
 * longer says anything for takes nothing away. The vendors changed: vendor
 * id => ['name', 'client_id', 'fields' => [labels]].
 */
function vendorTemplateFollow($vendor_template_id, array $before, array $after) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $old = vendorTemplateFieldsRow($before);
    $new = vendorTemplateFieldsRow($after);
    $changed = [];
    foreach (VENDOR_TEMPLATE_SHARED as $key) {
        if (vendorTemplateHasValue($key, $new) && vendorTemplateCompareValue($key, $old, 'template') !== vendorTemplateCompareValue($key, $new, 'template')) {
            $changed[] = $key;
        }
    }
    $followed = [];
    if (!$changed) {
        return $followed;
    }
    $labels = vendorTemplateFieldLabels();
    $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_template_id = $vendor_template_id");
    while ($vendor = mysqli_fetch_assoc($sql)) {
        $keys = [];
        $differed = vendorTemplateDifferences($old, $vendor);
        foreach ($changed as $key) {
            if (vendorTemplateHasValue($key, $old)) {
                // It still had what the template said
                $follows = !isset($differed[$key]);
            } else {
                // The template said nothing - only a vendor with nothing there either takes it
                $theirs = vendorTemplateCompareValue($key, $vendor, 'vendor');
                $follows = $key === 'phone' ? $theirs[1] === '' : $theirs === '';
            }
            if ($follows) {
                $keys[] = $key;
            }
        }
        if ($keys) {
            vendorTemplateApply($vendor['vendor_id'], $new, $keys);
            $followed[intval($vendor['vendor_id'])] = ['name' => $vendor['vendor_name'], 'client_id' => intval($vendor['vendor_client_id']),
                'fields' => array_map(function ($key) use ($labels) {
                    return $labels[$key]['label'];
                }, $keys)];
        }
    }
    return $followed;
}

/*
 * Bring vendors made from a template in line with it - the fields picked,
 * where they differ. The vendors changed: vendor id => ['name', 'client_id',
 * 'fields' => [labels]]. Vendors not made from it are left alone.
 */
function vendorTemplateBringInLine($vendor_template_id, array $vendor_ids, array $keys) {
    global $mysqli;
    $template = vendorTemplateInfo($vendor_template_id);
    $vendor_ids = array_values(array_unique(array_filter(array_map('intval', $vendor_ids))));
    $keys = array_values(array_intersect(VENDOR_TEMPLATE_SHARED, $keys));
    $changed = [];
    if (!$template || !$vendor_ids || !$keys) {
        return $changed;
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_template_id = " . intval($template['vendor_template_id']) . " AND vendor_id IN (" . implode(',', $vendor_ids) . ")");
    while ($vendor = mysqli_fetch_assoc($sql)) {
        $differences = array_intersect_key(vendorTemplateDifferences($template, $vendor), array_flip($keys));
        if ($differences) {
            vendorTemplateApply($vendor['vendor_id'], $template, array_keys($differences));
            $changed[intval($vendor['vendor_id'])] = ['name' => $vendor['vendor_name'], 'client_id' => intval($vendor['vendor_client_id']), 'fields' => array_values($differences)];
        }
    }
    return $changed;
}

/** How many of each template's current vendors, for current clients, are out of step - template id => count. */
function vendorTemplateOutOfStep(array $vendor_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $vendor_template_ids)));
    $counts = [];
    if (!$ids) {
        return $counts;
    }
    $templates = [];
    $sql = mysqli_query($mysqli, "SELECT * FROM vendor_templates WHERE vendor_template_id IN (" . implode(',', $ids) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $templates[intval($row['vendor_template_id'])] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT vendors.* FROM vendors LEFT JOIN clients ON client_id = vendor_client_id AND vendor_client_id > 0
        WHERE vendor_template_id IN (" . implode(',', $ids) . ") AND vendor_archived_at IS NULL AND (vendor_client_id = 0 OR client_archived_at IS NULL AND client_id IS NOT NULL)");
    while ($vendor = mysqli_fetch_assoc($sql)) {
        $template_id = intval($vendor['vendor_template_id']);
        if (isset($templates[$template_id]) && vendorTemplateDifferences($templates[$template_id], $vendor)) {
            $counts[$template_id] = ($counts[$template_id] ?? 0) + 1;
        }
    }
    return $counts;
}

/** A website's host, for matching - lowercase, without www. '' for none. */
function vendorTemplateHost($website) {
    $website = trim(html_entity_decode((string) $website, ENT_QUOTES, 'UTF-8'));
    if ($website === '') {
        return '';
    }
    $host = (string) parse_url(preg_match('#^[a-z][a-z0-9+.-]*://#i', $website) ? $website : "https://$website", PHP_URL_HOST);
    return preg_replace('/^www\./', '', mb_strtolower($host));
}

/*
 * Current vendors not made from any template that look like this one - the
 * same name, whatever the case, or a website on the same host - at clients
 * (or ours) without a vendor from it already: vendor id => vendor_id,
 * vendor_name, client_id, client_name, how ('name' or 'site') and
 * differences (as if it were made from it).
 */
function vendorTemplateMatches($vendor_template_id) {
    global $mysqli;
    $template = vendorTemplateInfo($vendor_template_id);
    $matches = [];
    if (!$template) {
        return $matches;
    }
    $template_id = intval($template['vendor_template_id']);
    $name = escapeSql(mb_strtolower(trim($template['vendor_template_name'])));
    $host = vendorTemplateHost($template['vendor_template_website']);
    $site_sql = $host !== '' ? " OR vendor_website LIKE '%" . escapeSql($host) . "%'" : '';
    $sql = mysqli_query($mysqli, "SELECT vendors.*, client_name FROM vendors LEFT JOIN clients ON client_id = vendor_client_id AND vendor_client_id > 0
        WHERE vendor_template_id = 0 AND vendor_archived_at IS NULL AND (vendor_client_id = 0 OR client_archived_at IS NULL AND client_id IS NOT NULL)
        AND (LOWER(TRIM(vendor_name)) = '$name'$site_sql)
        AND NOT EXISTS (SELECT 1 FROM vendors made WHERE made.vendor_template_id = $template_id AND made.vendor_client_id = vendors.vendor_client_id AND made.vendor_archived_at IS NULL)
        ORDER BY vendor_client_id = 0, client_name ASC, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $by_name = mb_strtolower(trim($row['vendor_name'])) === mb_strtolower(trim($template['vendor_template_name']));
        if (!$by_name && ($host === '' || vendorTemplateHost($row['vendor_website']) !== $host)) {
            continue;
        }
        $matches[intval($row['vendor_id'])] = ['vendor_id' => intval($row['vendor_id']), 'vendor_name' => (string) $row['vendor_name'], 'client_id' => intval($row['vendor_client_id']),
            'client_name' => (string) $row['client_name'], 'how' => $by_name ? 'name' : 'site', 'differences' => vendorTemplateDifferences($template, $row)];
    }
    return $matches;
}

/*
 * The current template a vendor made by hand looks like - the same name,
 * whatever the case, or a support site on the same host - when its client
 * (or we) has no current vendor from it already. The template's row, its
 * name first, or null.
 */
function vendorTemplateMatchFor(array $vendor) {
    global $mysqli;
    if (intval($vendor['vendor_template_id'] ?? 0)) {
        return null;
    }
    $client_id = intval($vendor['vendor_client_id'] ?? 0);
    $name = mb_strtolower(trim((string) ($vendor['vendor_name'] ?? '')));
    $host = vendorTemplateHost($vendor['vendor_website'] ?? '');
    $sql = mysqli_query($mysqli, "SELECT * FROM vendor_templates WHERE vendor_template_archived_at IS NULL
        AND (LOWER(TRIM(vendor_template_name)) = '" . escapeSql($name) . "'" . ($host !== '' ? " OR vendor_template_website LIKE '%" . escapeSql($host) . "%'" : '') . ")
        AND NOT EXISTS (SELECT 1 FROM vendors WHERE vendor_client_id = $client_id AND vendors.vendor_template_id = vendor_templates.vendor_template_id AND vendor_archived_at IS NULL)
        ORDER BY LOWER(TRIM(vendor_template_name)) = '" . escapeSql($name) . "' DESC, vendor_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (mb_strtolower(trim($row['vendor_template_name'])) === $name || ($host !== '' && vendorTemplateHost($row['vendor_template_website']) === $host)) {
            return $row;
        }
    }
    return null;
}

/*
 * Link vendors to a template, as if made from it - only ones not made from
 * one. The vendors linked: vendor id => ['name', 'client_id'].
 */
function vendorTemplateLink($vendor_template_id, array $vendor_ids) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $vendor_ids = array_values(array_unique(array_filter(array_map('intval', $vendor_ids))));
    $linked = [];
    if (!$vendor_template_id || !$vendor_ids) {
        return $linked;
    }
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_client_id FROM vendors WHERE vendor_template_id = 0 AND vendor_id IN (" . implode(',', $vendor_ids) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $linked[intval($row['vendor_id'])] = ['name' => $row['vendor_name'], 'client_id' => intval($row['vendor_client_id'])];
    }
    if ($linked) {
        mysqli_query($mysqli, "UPDATE vendors SET vendor_template_id = $vendor_template_id WHERE vendor_template_id = 0 AND vendor_id IN (" . implode(',', array_keys($linked)) . ")");
    }
    return $linked;
}

/* ---------------------------------------------------------------- who should have one */

/** Who should have a vendor from a template (vendor_template_required). */
const VENDOR_TEMPLATE_REQUIRED_NONE = 0;
const VENDOR_TEMPLATE_REQUIRED_EVERY = 1;
const VENDOR_TEMPLATE_REQUIRED_TAGGED = 2;

/** The client tags templates are required by - [template id => [tag id => tag name]], in name order. */
function vendorTemplateRequiredTags(array $vendor_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $vendor_template_ids)));
    $tags = array_fill_keys($ids, []);
    if (!$ids) {
        return $tags;
    }
    $sql = mysqli_query($mysqli, "SELECT vendor_template_id, tags.tag_id, tag_name FROM vendor_template_required_tags
        JOIN tags ON tags.tag_id = vendor_template_required_tags.tag_id
        WHERE vendor_template_id IN (" . implode(',', $ids) . ") ORDER BY tag_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tags[intval($row['vendor_template_id'])][intval($row['tag_id'])] = (string) $row['tag_name'];
    }
    return $tags;
}

/*
 * Set who should have a vendor from a template - $required one of the
 * VENDOR_TEMPLATE_REQUIRED_ values, $tag_ids the client tags for TAGGED. Not
 * a change to what a vendor starts with, so when it last changed stays.
 */
function vendorTemplateSetRequired($vendor_template_id, $required, array $tag_ids) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $required = in_array(intval($required), [VENDOR_TEMPLATE_REQUIRED_EVERY, VENDOR_TEMPLATE_REQUIRED_TAGGED], true) ? intval($required) : VENDOR_TEMPLATE_REQUIRED_NONE;
    mysqli_query($mysqli, "UPDATE vendor_templates SET vendor_template_required = $required, vendor_template_updated_at = vendor_template_updated_at
        WHERE vendor_template_id = $vendor_template_id");
    // Client tags only - any others are left out
    $tag_ids = $required === VENDOR_TEMPLATE_REQUIRED_TAGGED ? array_values(array_unique(array_filter(array_map('intval', $tag_ids)))) : [];
    mysqli_query($mysqli, "DELETE FROM vendor_template_required_tags WHERE vendor_template_id = $vendor_template_id");
    if ($tag_ids) {
        mysqli_query($mysqli, "INSERT INTO vendor_template_required_tags (vendor_template_id, tag_id)
            SELECT $vendor_template_id, tag_id FROM tags WHERE tag_type = 1 AND tag_id IN (" . implode(',', $tag_ids) . ")");
    }
}

/*
 * The clients a template is required of, as SQL on clients: current clients,
 * not leads - every one, or the ones with any of its tags (none when it has
 * no tags left, or isn't required).
 */
function vendorTemplateScopeSql($required, array $tag_ids) {
    $sql = "clients.client_archived_at IS NULL AND clients.client_lead = 0";
    if (intval($required) === VENDOR_TEMPLATE_REQUIRED_EVERY) {
        return $sql;
    }
    $tag_ids = array_values(array_filter(array_map('intval', $tag_ids)));
    if (intval($required) !== VENDOR_TEMPLATE_REQUIRED_TAGGED || !$tag_ids) {
        return '0';
    }
    return "$sql AND EXISTS (SELECT 1 FROM client_tags WHERE client_tags.client_id = clients.client_id AND client_tags.tag_id IN (" . implode(',', $tag_ids) . "))";
}

/** Who a template is required of, in words - '' when it isn't. */
function vendorTemplateScopeText($required, array $tags) {
    if (intval($required) === VENDOR_TEMPLATE_REQUIRED_EVERY) {
        return 'Every client';
    }
    if (intval($required) !== VENDOR_TEMPLATE_REQUIRED_TAGGED) {
        return '';
    }
    return $tags ? 'Clients tagged ' . implode(' or ', $tags) : 'Clients with a tag - none picked';
}

/*
 * How far a required template reaches - ['clients' => how many it is
 * required of, 'covered' => how many of them have a current vendor from it,
 * 'missing' => [client id => name] the rest, by name].
 */
function vendorTemplateCoverage($vendor_template_id, $required, array $tag_ids) {
    global $mysqli;
    $vendor_template_id = intval($vendor_template_id);
    $coverage = ['clients' => 0, 'covered' => 0, 'missing' => []];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, EXISTS (SELECT 1 FROM vendors WHERE vendor_client_id = clients.client_id
            AND vendor_template_id = $vendor_template_id AND vendor_archived_at IS NULL) AS covered
        FROM clients WHERE " . vendorTemplateScopeSql($required, $tag_ids) . " ORDER BY client_name ASC");
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
 * The required templates a client has no current vendor from - [template id
 * => name]. None for an archived client or a lead.
 */
function vendorTemplatesMissingFor($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $missing = [];
    $sql = mysqli_query($mysqli, "SELECT vendor_template_id, vendor_template_name FROM vendor_templates
        WHERE vendor_template_archived_at IS NULL AND vendor_template_required IN (" . VENDOR_TEMPLATE_REQUIRED_EVERY . ", " . VENDOR_TEMPLATE_REQUIRED_TAGGED . ")
        AND EXISTS (SELECT 1 FROM clients WHERE clients.client_id = $client_id AND clients.client_archived_at IS NULL AND clients.client_lead = 0)
        AND (vendor_template_required = " . VENDOR_TEMPLATE_REQUIRED_EVERY . " OR EXISTS (SELECT 1 FROM vendor_template_required_tags rt
            JOIN client_tags ON client_tags.tag_id = rt.tag_id AND client_tags.client_id = $client_id
            WHERE rt.vendor_template_id = vendor_templates.vendor_template_id))
        AND NOT EXISTS (SELECT 1 FROM vendors WHERE vendor_client_id = $client_id AND vendors.vendor_template_id = vendor_templates.vendor_template_id AND vendor_archived_at IS NULL)
        ORDER BY vendor_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $missing[intval($row['vendor_template_id'])] = (string) $row['vendor_template_name'];
    }
    return $missing;
}
