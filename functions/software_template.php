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
            software_template_type, software_template_license_type, software_template_notes, software_template_created_at, software_template_updated_at,
            software_template_archived_at, software_template_created_by, software_template_updated_by,
            creator.user_name AS created_by_name, updater.user_name AS updated_by_name
        FROM software_templates
        LEFT JOIN users creator ON creator.user_id = software_template_created_by AND software_template_created_by > 0
        LEFT JOIN users updater ON updater.user_id = software_template_updated_by AND software_template_updated_by > 0
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
 * type from Admin > Categories), license_type and notes.
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
        software_template_license_type = '" . escapeSql($f['license_type']) . "', software_template_notes = '" . escapeSql($f['notes']) . "'";
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

/*
 * Make a client's license from a template - its name, version, summary, type,
 * license type and notes - with what $post gives it: seats (blank is no
 * limit), key, purchase_reference, purchase and expire (Y-m-d). It remembers
 * the template. The new license's id, or 0 when the template is gone or
 * archived.
 */
function softwareTemplateMakeLicense($software_template_id, $client_id, array $post = []) {
    global $mysqli;
    $row = softwareTemplateRow($software_template_id);
    if (!$row || $row['software_template_archived_at']) {
        return 0;
    }
    $set = softwareFormSet([
        'name' => $row['software_template_name'],
        'version' => $row['software_template_version'],
        'description' => $row['software_template_description'],
        'type' => $row['software_template_type'],
        'license_type' => $row['software_template_license_type'],
        'notes' => $row['software_template_notes'],
        'seats' => $post['seats'] ?? '',
        'key' => $post['key'] ?? '',
        'purchase_reference' => $post['purchase_reference'] ?? '',
        'purchase' => $post['purchase'] ?? '',
        'expire' => $post['expire'] ?? '',
    ], intval($client_id));
    mysqli_query($mysqli, "INSERT INTO software SET $set, software_client_id = " . intval($client_id) . ", software_template_id = " . intval($row['software_template_id']));
    return intval(mysqli_insert_id($mysqli));
}
