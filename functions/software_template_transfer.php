<?php

/*
 * License templates to and from a file - to copy a library between ITFlow
 * installs, keep a copy, or share one. Admin > License Templates exports them
 * (export_software_templates) and imports them (import_software_templates);
 * the MSP starter pack on Maintenance > Starter Content is loaded the same way.
 *
 * The file is JSON: {"format": "itflow-license-templates", "version": 1,
 * "exported_at", "templates": [...]}. Each template carries what a license
 * made from it starts with - its name, version, summary, type, license type,
 * notes, seats and renewal term - its vendor, as the vendor template it comes
 * from (its name, summary, phone, email, website, hours, SLA and notes, never
 * an account number or code), and the name of the product it is sold as. Not
 * who should have one: that is the call of the install it goes into - a new
 * one comes in required of no one, and one replaced keeps its own. Coming in,
 * a vendor template is matched by name, and made from the file when there is
 * none; a product only by name - prices are yours, so none is made.
 */

const SOFTWARE_TEMPLATE_FILE_FORMAT = 'itflow-license-templates';
const SOFTWARE_TEMPLATE_FILE_VERSION = 1;

/** The vendor template fields that go in a file - its own account numbers and codes don't. */
const SOFTWARE_TEMPLATE_FILE_VENDOR_FIELDS = ['description' => 200, 'phone_country_code' => 10, 'phone' => 200, 'extension' => 200, 'email' => 200, 'website' => 200, 'hours' => 200, 'sla' => 200, 'notes' => 0];

/*
 * Templates as a file's contents (an array, for json_encode()) - the ones in
 * $ids, or every current one when $ids is empty, and the archived ones too
 * with $archived.
 */
function softwareTemplatesExport(array $ids = [], $archived = false) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $where = $ids ? 'software_template_id IN (' . implode(',', $ids) . ')' : ($archived ? '1 = 1' : 'software_template_archived_at IS NULL');
    $templates = [];
    $sql = mysqli_query($mysqli, "SELECT software_templates.*, vendor_templates.*, product_name FROM software_templates
        LEFT JOIN vendor_templates ON vendor_template_id = software_template_vendor_template_id AND software_template_vendor_template_id > 0
        LEFT JOIN products ON product_id = software_template_product_id AND software_template_product_id > 0
        WHERE $where ORDER BY software_template_name ASC, software_template_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendor = null;
        if ($row['vendor_template_name'] !== null) {
            $vendor = ['name' => (string) $row['vendor_template_name']];
            foreach (array_keys(SOFTWARE_TEMPLATE_FILE_VENDOR_FIELDS) as $field) {
                $vendor[$field] = (string) $row["vendor_template_$field"];
            }
        }
        $templates[] = [
            'name' => (string) $row['software_template_name'],
            'version' => (string) $row['software_template_version'],
            'description' => (string) $row['software_template_description'],
            'type' => (string) $row['software_template_type'],
            'license_type' => (string) $row['software_template_license_type'],
            'notes' => (string) $row['software_template_notes'],
            'seats' => $row['software_template_seats'] === null ? null : intval($row['software_template_seats']),
            'term' => (string) $row['software_template_term'],
            'vendor' => $vendor,
            'product' => $row['product_name'] !== null ? (string) $row['product_name'] : null,
        ];
    }
    return ['format' => SOFTWARE_TEMPLATE_FILE_FORMAT, 'version' => SOFTWARE_TEMPLATE_FILE_VERSION, 'exported_at' => date('c'), 'templates' => $templates];
}

/** A file name for an export - its one template's name, or the date. */
function softwareTemplatesExportFileName(array $export) {
    $templates = $export['templates'] ?? [];
    if (count($templates) === 1) {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($templates[0]['name'])), '-');
        return 'license-template-' . ($slug !== '' ? substr($slug, 0, 60) : 'export') . '.json';
    }
    return 'license-templates-' . date('Y-m-d') . '.json';
}

/* One line of text from a file, tidied - no markup, no runs of spaces, no longer than $max. */
function softwareTemplateFileText($value, $max) {
    return is_scalar($value) ? trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) $value)), 0, $max)) : '';
}

/* Notes from a file - no markup, their lines kept, no longer than $max. */
function softwareTemplateFileNotes($value, $max = 20000) {
    return is_scalar($value) ? trim(mb_substr(str_replace("\r\n", "\n", strip_tags((string) $value)), 0, $max)) : '';
}

/*
 * A file's templates, read and checked - from what export wrote, or the
 * starter pack. Each made safe: text tidied, seats a whole number or none, a
 * renewal term that is one, its vendor with a name and only the fields a
 * vendor template has, its product a name. A template with no type is Other.
 * Null with $error saying why the file can't be used.
 */
function softwareTemplatesImportRead($contents, &$error = '') {
    $data = is_array($contents) ? $contents : json_decode((string) $contents, true);
    if (!is_array($data) || ($data['format'] ?? '') !== SOFTWARE_TEMPLATE_FILE_FORMAT || !isset($data['templates']) || !is_array($data['templates'])) {
        $error = "That isn't a license templates file - export one from Admin > License Templates.";
        return null;
    }
    if (intval($data['version'] ?? 0) > SOFTWARE_TEMPLATE_FILE_VERSION) {
        $error = 'That file was made by a newer version of ITFlow - update this one first.';
        return null;
    }
    if (count($data['templates']) > 500) {
        $error = 'That file holds more than 500 license templates - split it up.';
        return null;
    }

    $templates = [];
    foreach ($data['templates'] as $t) {
        $name = is_array($t) ? softwareTemplateFileText($t['name'] ?? '', 200) : '';
        if ($name === '') {
            continue;
        }
        $vendor = null;
        $vendor_name = is_array($t['vendor'] ?? null) ? softwareTemplateFileText($t['vendor']['name'] ?? '', 200) : '';
        if ($vendor_name !== '') {
            $vendor = ['name' => $vendor_name];
            foreach (SOFTWARE_TEMPLATE_FILE_VENDOR_FIELDS as $field => $max) {
                $vendor[$field] = $max ? softwareTemplateFileText($t['vendor'][$field] ?? '', $max) : softwareTemplateFileNotes($t['vendor'][$field] ?? '');
            }
        }
        $seats = $t['seats'] ?? null;
        $term = is_string($t['term'] ?? null) ? $t['term'] : '';
        $type = softwareTemplateFileText($t['type'] ?? '', 200);
        $templates[] = [
            'name' => $name,
            'version' => softwareTemplateFileText($t['version'] ?? '', 200),
            'description' => softwareTemplateFileText($t['description'] ?? '', 500),
            'type' => $type !== '' ? $type : 'Other',
            'license_type' => softwareTemplateFileText($t['license_type'] ?? '', 200),
            'notes' => softwareTemplateFileNotes($t['notes'] ?? ''),
            'seats' => is_numeric($seats) ? max(0, min(1000000, intval($seats))) : null,
            'term' => isset(SOFTWARE_RENEWAL_TERMS[$term]) ? $term : '',
            'vendor' => $vendor,
            'product' => softwareTemplateFileText($t['product'] ?? '', 200),
        ];
    }
    if (!$templates) {
        $error = 'There are no license templates in that file.';
        return null;
    }
    return $templates;
}

/*
 * The vendor template for a file's vendor - one here by its name (a current
 * one first), or one made from the file ($made says so). Its id.
 */
function softwareTemplateImportVendor(array $vendor, &$made = false) {
    global $mysqli;
    $made = false;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT vendor_template_id FROM vendor_templates WHERE LOWER(vendor_template_name) = LOWER('" . escapeSql($vendor['name']) . "')
        ORDER BY vendor_template_archived_at IS NOT NULL, vendor_template_id ASC LIMIT 1"));
    if ($row) {
        return intval($row[0]);
    }
    $set = ["vendor_template_name = '" . escapeSql($vendor['name']) . "'"];
    foreach (array_keys(SOFTWARE_TEMPLATE_FILE_VENDOR_FIELDS) as $field) {
        $value = (string) ($vendor[$field] ?? '');
        if (in_array($field, ['phone_country_code', 'phone', 'extension'], true)) {
            $value = preg_replace('/[^0-9]/', '', $value);
        }
        if ($field === 'website') {
            $value = preg_replace('#^https?://#i', '', $value);
        }
        $set[] = "vendor_template_$field = '" . escapeSql($value) . "'";
    }
    mysqli_query($mysqli, "INSERT INTO vendor_templates SET " . implode(', ', $set));
    $made = true;
    return intval(mysqli_insert_id($mysqli));
}

/* The product a file's template is sold as - a current one here by its name. Its id, or 0. */
function softwareTemplateImportProduct($name) {
    global $mysqli;
    if ((string) $name === '') {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT product_id FROM products WHERE LOWER(product_name) = LOWER('" . escapeSql($name) . "') AND product_archived_at IS NULL
        ORDER BY product_id ASC LIMIT 1"));
    return intval($row[0] ?? 0);
}

/*
 * Bring in templates read by softwareTemplatesImportRead(). One whose name is
 * already here is skipped ($existing 'skip'), added under a name of its own
 * ('copy') or has what the file says put in its place ('replace') - its name,
 * version, summary, type, license type, notes, seats, renewal term and
 * vendor, and the product it is sold as when the file's is here (else it
 * keeps its own). Its licenses stay made from it, and who should have one
 * stays as it was; a new one is required of no one. $only_existing: bring in
 * only the ones already here (to update them). ['added', 'replaced',
 * 'skipped', 'vendors' made, 'products' - the names of the products not here,
 * 'ids' of those added or replaced].
 */
function softwareTemplatesImport(array $templates, $existing = 'skip', $only_existing = false) {
    global $mysqli;
    $counts = ['added' => 0, 'replaced' => 0, 'skipped' => 0, 'vendors' => 0, 'products' => [], 'ids' => []];
    $here = [];
    $sql = mysqli_query($mysqli, "SELECT software_template_id, software_template_name, software_template_product_id FROM software_templates ORDER BY software_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $key = mb_strtolower($row['software_template_name']);
        $here[$key] = $here[$key] ?? ['id' => intval($row['software_template_id']), 'product_id' => intval($row['software_template_product_id'])];
    }

    foreach ($templates as $t) {
        $id = $here[mb_strtolower($t['name'])]['id'] ?? 0;
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
        $replacing = $id > 0;

        $vendor_template_id = 0;
        if ($t['vendor']) {
            $vendor_template_id = softwareTemplateImportVendor($t['vendor'], $vendor_made);
            $counts['vendors'] += $vendor_made ? 1 : 0;
        }
        $product_id = softwareTemplateImportProduct($t['product']);
        if ($t['product'] !== '' && !$product_id) {
            $counts['products'][mb_strtolower($t['product'])] = $t['product'];
        }
        if (!$product_id && $replacing) {
            $product_id = $here[mb_strtolower($t['name'])]['product_id'];
        }

        $saved = softwareTemplateSave($id, [
            'name' => $name, 'version' => $t['version'], 'description' => $t['description'], 'type' => $t['type'], 'license_type' => $t['license_type'],
            'notes' => $t['notes'], 'seats' => $t['seats'], 'term' => $t['term'], 'vendor_template_id' => $vendor_template_id, 'product_id' => $product_id,
        ]);
        $here[mb_strtolower($name)] = ['id' => $saved, 'product_id' => $product_id];
        $counts[$replacing ? 'replaced' : 'added']++;
        $counts['ids'][] = $saved;
    }
    $counts['products'] = array_values($counts['products']);
    return $counts;
}

/* What an import did, in words - for its message. HTML. */
function softwareTemplatesImportSummary(array $counts) {
    $parts = [];
    foreach (['added' => 'added', 'replaced' => 'replaced', 'skipped' => 'already here, left as they were'] as $key => $word) {
        if ($counts[$key]) {
            $parts[] = '<strong>' . $counts[$key] . '</strong> ' . $word;
        }
    }
    $products = $counts['products'];
    return ucfirst(implode(', ', $parts) ?: 'nothing brought in')
        . ($counts['vendors'] ? '. Added <strong>' . $counts['vendors'] . '</strong> vendor template' . ($counts['vendors'] === 1 ? '' : 's') . ' they come from' : '')
        . ($products ? '. Not sold as ' . escapeHtml(implode(', ', array_slice($products, 0, 5))) . (count($products) > 5 ? ' and ' . (count($products) - 5) . ' more' : '')
            . ' - ' . (count($products) === 1 ? 'that product isn\'t' : 'those products aren\'t') . ' here, so pick what each is sold as on its page' : '')
        . '.';
}
