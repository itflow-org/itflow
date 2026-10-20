<?php

/*
 * Vendor templates to and from a file - to copy a library between ITFlow
 * installs, keep a copy, or share one. Admin > Vendor Templates exports them
 * (export_vendor_templates) and imports them (import_vendor_templates); the
 * Vendor Templates pack on Maintenance > Starter Content is loaded and
 * brought up to date the same way.
 *
 * The file is JSON: {"format": "itflow-vendor-templates", "version": 1,
 * "exported_at", "templates": [...]}. Each template carries what a vendor
 * made from it starts with - its name, what they are, the support line,
 * hours, SLA, support email, site, status page and portal, and its notes -
 * and, when asked for, the account manager. Never an account number or PIN:
 * those stay in the install they belong to, and one coming in keeps its own.
 * Not who should have one either: a new one comes in required of no one, and
 * one replaced keeps its own. A template replaced from a file changes the
 * vendors made from it that still said what it did (vendorTemplateFollow()).
 */

const VENDOR_TEMPLATE_FILE_FORMAT = 'itflow-vendor-templates';
const VENDOR_TEMPLATE_FILE_VERSION = 1;

/** The fields that go in a file - key => longest it may be (0 for notes). Never the account number or PIN. */
const VENDOR_TEMPLATE_FILE_FIELDS = ['description' => 200, 'phone_country_code' => 10, 'phone' => 200, 'extension' => 200, 'hours' => 200, 'sla' => 200, 'email' => 200,
    'website' => 200, 'status_page' => 200, 'portal' => 200, 'notes' => 0];

/** ...and the account manager's, when asked for. */
const VENDOR_TEMPLATE_FILE_MANAGER_FIELDS = ['contact_name' => 200, 'contact_phone_country_code' => 10, 'contact_phone' => 200, 'contact_email' => 200];

/*
 * Templates as a file's contents (an array, for json_encode()) - the ones in
 * $ids, or every current one when $ids is empty, the archived ones too with
 * $archived, and their account manager with $manager.
 */
function vendorTemplatesExport(array $ids = [], $archived = false, $manager = false) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $where = $ids ? 'vendor_template_id IN (' . implode(',', $ids) . ')' : ($archived ? '1 = 1' : 'vendor_template_archived_at IS NULL');
    $fields = VENDOR_TEMPLATE_FILE_FIELDS + ($manager ? VENDOR_TEMPLATE_FILE_MANAGER_FIELDS : []);
    $templates = [];
    $sql = mysqli_query($mysqli, "SELECT * FROM vendor_templates WHERE $where ORDER BY vendor_template_name ASC, vendor_template_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $template = ['name' => (string) $row['vendor_template_name']];
        foreach (array_keys($fields) as $field) {
            $template[$field] = (string) $row["vendor_template_$field"];
        }
        $templates[] = $template;
    }
    return ['format' => VENDOR_TEMPLATE_FILE_FORMAT, 'version' => VENDOR_TEMPLATE_FILE_VERSION, 'exported_at' => date('c'), 'templates' => $templates];
}

/** A file name for an export - its one template's name, or the date. */
function vendorTemplatesExportFileName(array $export) {
    $templates = $export['templates'] ?? [];
    if (count($templates) === 1) {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($templates[0]['name'])), '-');
        return 'vendor-template-' . ($slug !== '' ? substr($slug, 0, 60) : 'export') . '.json';
    }
    return 'vendor-templates-' . date('Y-m-d') . '.json';
}

/*
 * One field from a file, made safe as the template window would save it -
 * text tidied (softwareTemplateFileText() and softwareTemplateFileNotes(),
 * shared with the license templates file), phone numbers as digits, sites
 * without their scheme or a trailing slash, an email that isn't one dropped.
 */
function vendorTemplateFileValue($field, $value, $max) {
    if (!$max) {
        return softwareTemplateFileNotes($value);
    }
    $value = softwareTemplateFileText($value, $max);
    if (in_array($field, ['phone_country_code', 'phone', 'extension', 'contact_phone_country_code', 'contact_phone'], true)) {
        return substr(preg_replace('/[^0-9]/', '', $value), 0, $max);
    }
    if (in_array($field, ['website', 'status_page', 'portal'], true)) {
        return mb_substr(rtrim(preg_replace('#^https?://#i', '', $value), '/'), 0, $max);
    }
    if (in_array($field, ['email', 'contact_email'], true) && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return $value;
}

/*
 * A file's templates, read and checked - from what export wrote, or the
 * starter pack. Each made safe (vendorTemplateFileValue()), with only the
 * fields a file carries: an account number or PIN in it is left out. The
 * account manager only when the file has one - 'manager' says whether it
 * does. Null with $error saying why the file can't be used.
 */
function vendorTemplatesImportRead($contents, &$error = '') {
    $data = is_array($contents) ? $contents : json_decode((string) $contents, true);
    if (!is_array($data) || ($data['format'] ?? '') !== VENDOR_TEMPLATE_FILE_FORMAT || !isset($data['templates']) || !is_array($data['templates'])) {
        $error = "That isn't a vendor templates file - export one from Admin > Vendor Templates.";
        return null;
    }
    if (intval($data['version'] ?? 0) > VENDOR_TEMPLATE_FILE_VERSION) {
        $error = 'That file was made by a newer version of ITFlow - update this one first.';
        return null;
    }
    if (count($data['templates']) > 1000) {
        $error = 'That file holds more than 1000 vendor templates - split it up.';
        return null;
    }

    $templates = [];
    foreach ($data['templates'] as $t) {
        $name = is_array($t) ? softwareTemplateFileText($t['name'] ?? '', 200) : '';
        if ($name === '') {
            continue;
        }
        $template = ['name' => $name];
        foreach (VENDOR_TEMPLATE_FILE_FIELDS as $field => $max) {
            $template[$field] = vendorTemplateFileValue($field, $t[$field] ?? '', $max);
        }
        if ($template['phone'] === '') {
            $template['phone_country_code'] = '';
        }
        $template['manager'] = false;
        foreach (array_keys(VENDOR_TEMPLATE_FILE_MANAGER_FIELDS) as $field) {
            $template['manager'] = $template['manager'] || array_key_exists($field, $t);
        }
        if ($template['manager']) {
            foreach (VENDOR_TEMPLATE_FILE_MANAGER_FIELDS as $field => $max) {
                $template[$field] = vendorTemplateFileValue($field, $t[$field] ?? '', $max);
            }
        }
        $templates[] = $template;
    }
    if (!$templates) {
        $error = 'There are no vendor templates in that file.';
        return null;
    }
    return $templates;
}

/*
 * Bring in templates read by vendorTemplatesImportRead(). One whose name is
 * already here is skipped ($existing 'skip'), added under a name of its own
 * ('copy') or has what the file says put in its place ('replace') - every
 * field the file carries, or only the ones in $only_fields (the starter
 * pack's update). A template replaced keeps its own account number and PIN,
 * its account manager unless the file has one, and who should have it; the
 * vendors made from it that still said what it did change with it. A new one
 * is required of no one. $only_existing: bring in only the ones already here
 * (to update them). ['added', 'replaced', 'skipped', 'ids' of those added or
 * replaced, 'followed' - [template name => vendorTemplateFollow()'s vendors]].
 */
function vendorTemplatesImport(array $templates, $existing = 'skip', $only_existing = false, array $only_fields = []) {
    global $mysqli;
    $counts = ['added' => 0, 'replaced' => 0, 'skipped' => 0, 'ids' => [], 'followed' => []];
    $here = [];
    $sql = mysqli_query($mysqli, "SELECT vendor_template_id, vendor_template_name FROM vendor_templates ORDER BY vendor_template_archived_at IS NOT NULL, vendor_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $key = mb_strtolower($row['vendor_template_name']);
        $here[$key] = $here[$key] ?? intval($row['vendor_template_id']);
    }

    foreach ($templates as $t) {
        $id = $here[mb_strtolower($t['name'])] ?? 0;
        if (($only_existing && !$id) || ($id && $existing === 'skip')) {
            $counts['skipped']++;
            continue;
        }
        $name = $t['name'];
        if ($id && $existing === 'copy') {
            for ($n = 2; isset($here[mb_strtolower($name)]); $n++) {
                $name = mb_substr($t['name'], 0, 190) . " ($n)";
            }
            $id = 0;
        }

        // What it has now - nothing, for a new one - with the file's fields put in
        $before = $id ? vendorTemplateRowFields(vendorTemplateInfo($id)) : vendorTemplateRowFields([]);
        $fields = $before;
        $fields['name'] = $name;
        $from_file = array_keys(VENDOR_TEMPLATE_FILE_FIELDS + (!empty($t['manager']) ? VENDOR_TEMPLATE_FILE_MANAGER_FIELDS : []));
        foreach ($only_fields ? array_intersect($from_file, $only_fields) : $from_file as $field) {
            $fields[$field] = (string) ($t[$field] ?? '');
        }
        if ($fields['phone'] === '') {
            $fields['phone_country_code'] = '';
        }

        $saved = vendorTemplateSave($id, $fields);
        $here[mb_strtolower($name)] = $saved;
        $counts[$id ? 'replaced' : 'added']++;
        $counts['ids'][] = $saved;
        if ($id) {
            $followed = vendorTemplateFollow($saved, $before, $fields);
            if ($followed) {
                $counts['followed'][$name] = $followed;
            }
        }
    }
    return $counts;
}

/* Each vendor that changed with a template brought in from a file, in its own history. */
function vendorTemplatesImportLogFollowed(array $counts) {
    global $session_name;
    foreach ($counts['followed'] as $template_name => $vendors) {
        foreach ($vendors as $vendor_id => $vendor) {
            logAudit("Vendor", "Edit", "$session_name updated vendor " . escapeSql($vendor['name']) . " from template " . escapeSql($template_name) . " - " . escapeSql(mb_strtolower(implode(', ', $vendor['fields']))), $vendor['client_id'], $vendor_id);
        }
    }
}

/* How many vendors changed with the templates brought in. */
function vendorTemplatesImportFollowedCount(array $counts) {
    return array_sum(array_map('count', $counts['followed']));
}

/* What an import did, in words - for its message. HTML. */
function vendorTemplatesImportSummary(array $counts) {
    $parts = [];
    foreach (['added' => 'added', 'replaced' => 'replaced', 'skipped' => 'already here, left as they were'] as $key => $word) {
        if ($counts[$key]) {
            $parts[] = '<strong>' . $counts[$key] . '</strong> ' . $word;
        }
    }
    $followed = vendorTemplatesImportFollowedCount($counts);
    return ucfirst(implode(', ', $parts) ?: 'nothing brought in')
        . ($followed ? '. <strong>' . $followed . '</strong> vendor' . ($followed === 1 ? '' : 's') . ' made from them changed with them' : '')
        . '.';
}
