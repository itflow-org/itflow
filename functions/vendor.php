<?php

/*
 * Vendors - who a client (or we) buy from and call for support.
 *
 * A vendor with a client is that client's: their ISP, phone provider, copier
 * company, line-of-business software maker. A tech calls them on the client's
 * behalf, so the vendor is built around making that call (support line, hours,
 * SLA, the account number and PIN they will ask for), the cases open with them
 * (tickets with their own reference), and what came from them - licenses,
 * assets, domains, services and the portal logins.
 *
 * A vendor with no client is one of ours - a distributor, the colo, the
 * accountant - what expenses and recurring expenses are booked against. Ours
 * are the financial module's to change; a client's are the client module's.
 */

/**
 * What can point at a vendor, by a column on its own table. 'module' is the
 * permission it takes to see them. Domains point at a vendor in four roles.
 */
const VENDOR_USES = [
    'ticket' => ['table' => 'tickets', 'id' => 'ticket_id', 'columns' => ['ticket_vendor_id'], 'client' => 'ticket_client_id', 'archived' => 'ticket_archived_at', 'module' => 'module_support', 'label' => 'case', 'plural' => 'Cases', 'icon' => 'fa-life-ring'],
    'software' => ['table' => 'software', 'id' => 'software_id', 'columns' => ['software_vendor_id'], 'client' => 'software_client_id', 'archived' => 'software_archived_at', 'module' => 'module_support', 'label' => 'license', 'plural' => 'Licenses', 'icon' => 'fa-cube'],
    'asset' => ['table' => 'assets', 'id' => 'asset_id', 'columns' => ['asset_vendor_id'], 'client' => 'asset_client_id', 'archived' => 'asset_archived_at', 'module' => 'module_support', 'label' => 'asset', 'plural' => 'Assets', 'icon' => 'fa-desktop'],
    'domain' => ['table' => 'domains', 'id' => 'domain_id', 'columns' => ['domain_registrar', 'domain_webhost', 'domain_dnshost', 'domain_mailhost'], 'client' => 'domain_client_id', 'archived' => 'domain_archived_at', 'module' => 'module_support', 'label' => 'domain', 'plural' => 'Domains', 'icon' => 'fa-globe'],
];

/** What each domain column makes the vendor - shown beside the domain. */
const VENDOR_DOMAIN_ROLES = ['domain_registrar' => 'Registrar', 'domain_webhost' => 'Web host', 'domain_dnshost' => 'DNS', 'domain_mailhost' => 'Mail'];

/** The module a vendor belongs to - a client's are the client module's, ours the financial module's. */
function vendorModule($client_id) {
    return intval($client_id) ? 'module_client' : 'module_financial';
}

/**
 * May this agent do this to a vendor of this client? 1 is to see it, 2 to
 * change it, 3 to delete it. Anyone may look up one of our own vendors -
 * their number is no secret - as it has always been.
 */
function vendorCan($client_id, $level) {
    $client_id = intval($client_id);
    if (!$client_id && $level <= 1) {
        return true;
    }
    return lookupUserPermission(vendorModule($client_id)) >= $level && (!$client_id || hasClientAccess($client_id));
}

/** Stop here unless this agent may do this to a vendor of this client. */
function vendorEnforce($client_id, $level) {
    $client_id = intval($client_id);
    if ($client_id) {
        enforceUserPermission('module_client', $level);
        enforceClientAccess($client_id);
    } elseif ($level >= 2) {
        enforceUserPermission('module_financial', $level);
    }
}

/** Up to two letters for a vendor's tile - "Keystone Business Fiber" is KB, "Netgate" is NE. */
function vendorInitials($name) {
    $name = trim(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'));
    $words = array_values(array_filter(preg_split('/[\s\-\/&,.]+/u', $name, -1, PREG_SPLIT_NO_EMPTY), function ($word) {
        return !in_array(mb_strtolower($word), ['the', 'and', 'of', 'inc', 'llc', 'ltd'], true) && preg_match('/^[\p{L}\p{N}]/u', $word);
    }));
    if (!$words) {
        return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
    }
    if (count($words) === 1) {
        return mb_strtoupper(mb_substr($words[0], 0, 2));
    }
    return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
}

/** A vendor's colour, by name - so a vendor many clients use is the same colour for all of them. */
function vendorTone($name) {
    $tones = ['primary', 'success', 'info', 'warning', 'secondary'];
    $key = mb_strtolower(trim(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8')));
    return $tones[crc32($key) % count($tones)];
}

/** The vendor's initials on its colour, in the same rounded square as the asset and license tiles. $size 'xs' | 'sm' | '' | 'lg'. */
function vendorTile($name, $size = '') {
    $size_class = in_array($size, ['xs', 'sm', 'lg'], true) ? " itflow-asset-icon-$size itflow-vendor-tile-$size" : '';
    $tone = vendorTone($name);
    return '<span class="itflow-asset-icon itflow-vendor-tile' . $size_class . ' bg-' . $tone . '-subtle text-' . $tone . '-emphasis" aria-hidden="true">' . escapeHtml(vendorInitials($name)) . '</span>';
}

/** A vendor in a picker - its tile, and a quieter line under it. */
function selectOptionVendor($name, $description = '') {
    return selectOptionData(['initials' => vendorInitials($name), 'tone' => vendorTone($name), 'soft' => '1', 'description' => $description]);
}

/**
 * A vendor wherever a page mentions one: its tile, then its name, opening
 * its quick look - or its page with 'page' => true (then 'client_id' too).
 * 'archived' strikes the name through; 'class' goes on the link.
 * Takes the name raw or already escaped.
 */
function vendorLink($vendor_id, $name, $options = []) {
    $vendor_id = intval($vendor_id);
    $name = html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8');
    $label = escapeHtml($name !== '' ? $name : 'Unnamed vendor');
    $archived = !empty($options['archived']);
    $class = trim('text-truncate ' . ($options['class'] ?? '') . ($archived ? ' text-decoration-line-through text-body-secondary' : ''));
    $title = $archived ? ' title="Archived vendor"' : '';
    if (!empty($options['page'])) {
        $client_id = intval($options['client_id'] ?? 0);
        $href = 'vendor.php?' . ($client_id ? "client_id=$client_id&amp;" : '') . "vendor_id=$vendor_id";
        $link = "<a class=\"$class\" href=\"$href\"$title>$label</a>";
    } else {
        $link = "<a class=\"ajax-modal $class\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/vendor/vendor.php?id=$vendor_id\"$title>$label</a>";
    }
    return '<span class="itflow-contact-ref">' . vendorTile($name, 'xs') . $link . '</span>';
}

/** The vendor's website as a link - with https:// when it was saved without a scheme. Raw in, raw out; '' without one. */
function vendorWebsiteUrl($website) {
    $website = trim(html_entity_decode((string) $website, ENT_QUOTES, 'UTF-8'));
    if ($website === '') {
        return '';
    }
    return preg_match('#^[a-z][a-z0-9+.-]*://#i', $website) ? $website : "https://$website";
}

/** The website without its scheme or a trailing slash - how it reads on a page. Raw in, raw out. */
function vendorWebsiteLabel($website) {
    $website = trim(html_entity_decode((string) $website, ENT_QUOTES, 'UTF-8'));
    return rtrim(preg_replace('#^https?://#i', '', $website), '/');
}

/** The support line to dial, with the country code - for a Call button. '' without one. */
function vendorDialNumber($phone, $country_code) {
    $digits = preg_replace('/[^0-9]/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }
    $country = preg_replace('/[^0-9]/', '', (string) $country_code);
    return ($country !== '' ? "+$country" : '') . $digits;
}

/**
 * A PIN or code as dots, with an eye to show it and a copy button that works
 * either way - so it is not read off a shared screen. '' when there is none.
 * Takes the value raw or already escaped.
 */
function vendorSecretHtml($value, $what = 'PIN') {
    $value = escapeHtml(trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8')));
    if ($value === '') {
        return '';
    }
    return '<span class="itflow-secret"><span class="itflow-secret-mask" aria-hidden="true">&bull;&bull;&bull;&bull;&bull;&bull;</span><span class="itflow-secret-value">' . $value . '</span>'
        . '<button type="button" class="itflow-secret-toggle" title="Show" aria-label="Show ' . $what . '" aria-pressed="false"><i class="fas fa-eye"></i></button>'
        . '<button type="button" class="itflow-copy clipboardjs" title="Copy ' . $what . '" data-clipboard-text="' . $value . '"><i class="far fa-copy"></i></button></span>';
}

/** One vendor by id with its client and template names - raw, or null. */
function vendorRow($vendor_id) {
    global $mysqli;
    $vendor_id = intval($vendor_id);
    if (!$vendor_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT vendors.*, client_name, vendor_template_name
        FROM vendors
        LEFT JOIN clients ON client_id = vendor_client_id
        LEFT JOIN vendor_templates ON vendor_templates.vendor_template_id = vendors.vendor_template_id
        WHERE vendor_id = $vendor_id
        LIMIT 1"));
    return $row ?: null;
}

/**
 * The vendor fields a New / Edit form posts, as a SQL SET list - phone
 * numbers as digits, the website without its scheme, only a template that
 * exists. Null when there is no name. Fields missing from the post are blank.
 */
function vendorFormSet($post) {
    global $mysqli;
    $text = function ($field, $length = 200) use ($post) {
        $value = trim((string) ($post[$field] ?? ''));
        return "'" . escapeSql($length ? mb_substr($value, 0, $length) : $value) . "'";
    };
    $digits = function ($field, $length = 200) use ($post) {
        return "'" . substr(preg_replace('/[^0-9]/', '', (string) ($post[$field] ?? '')), 0, $length) . "'";
    };

    if (trim((string) ($post['name'] ?? '')) === '') {
        return null;
    }

    $template_id = intval($post['vendor_template_id'] ?? 0);
    if ($template_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM vendor_templates WHERE vendor_template_id = $template_id"))) {
        $template_id = 0;
    }
    $website = mb_substr(preg_replace('#^https?://#i', '', trim((string) ($post['website'] ?? ''))), 0, 200);

    $fields = [
        'vendor_name' => $text('name'),
        'vendor_description' => $text('description'),
        'vendor_account_number' => $text('account_number'),
        'vendor_code' => $text('code'),
        'vendor_contact_name' => $text('contact_name'),
        'vendor_contact_phone_country_code' => $digits('contact_phone_country_code', 10),
        'vendor_contact_phone' => $digits('contact_phone'),
        'vendor_contact_email' => $text('contact_email'),
        'vendor_phone_country_code' => $digits('phone_country_code', 10),
        'vendor_phone' => $digits('phone'),
        'vendor_extension' => $digits('extension'),
        'vendor_email' => $text('email'),
        'vendor_website' => "'" . escapeSql($website) . "'",
        'vendor_hours' => $text('hours'),
        'vendor_sla' => $text('sla'),
        'vendor_notes' => $text('notes', 0),
        'vendor_favorite' => intval($post['favorite'] ?? 0) ? 1 : 0,
        'vendor_template_id' => $template_id,
    ];
    $set = [];
    foreach ($fields as $column => $value) {
        $set[] = "$column = $value";
    }
    return implode(', ', $set);
}

/** Replace a vendor's tags with these - vendor tags (type 6) only. */
function vendorSetTags($vendor_id, $tag_ids) {
    global $mysqli;
    $vendor_id = intval($vendor_id);
    mysqli_query($mysqli, "DELETE FROM vendor_tags WHERE vendor_id = $vendor_id");
    $tag_ids = array_values(array_unique(array_filter(array_map('intval', (array) $tag_ids))));
    if (!$tag_ids) {
        return;
    }
    mysqli_query($mysqli, "INSERT IGNORE INTO vendor_tags (vendor_id, tag_id)
        SELECT $vendor_id, tag_id FROM tags WHERE tag_type = 6 AND tag_id IN (" . implode(',', $tag_ids) . ")");
}

/** A template's fields as they go on a vendor - for New from template. Raw, or null. */
function vendorTemplateRow($template_id) {
    global $mysqli;
    $template_id = intval($template_id);
    if (!$template_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM vendor_templates WHERE vendor_template_id = $template_id AND vendor_template_archived_at IS NULL LIMIT 1"));
    return $row ?: null;
}

/** A template as the fields of the vendor form - what New from template fills in. */
function vendorTemplateFormFields($template) {
    return [
        'name' => $template['vendor_template_name'] ?? '',
        'description' => $template['vendor_template_description'] ?? '',
        'account_number' => $template['vendor_template_account_number'] ?? '',
        'code' => $template['vendor_template_code'] ?? '',
        'contact_name' => $template['vendor_template_contact_name'] ?? '',
        'phone_country_code' => $template['vendor_template_phone_country_code'] ?? '',
        'phone' => $template['vendor_template_phone'] ?? '',
        'extension' => $template['vendor_template_extension'] ?? '',
        'email' => $template['vendor_template_email'] ?? '',
        'website' => $template['vendor_template_website'] ?? '',
        'hours' => $template['vendor_template_hours'] ?? '',
        'sla' => $template['vendor_template_sla'] ?? '',
        'notes' => $template['vendor_template_notes'] ?? '',
        'vendor_template_id' => intval($template['vendor_template_id'] ?? 0),
    ];
}

/**
 * How much each vendor is used, for the list's chips and the page's tabs:
 * open cases and cases, licenses, assets, domains, services, logins,
 * documents and files, and - for our own vendors - expenses, what was spent
 * with them this year, and recurring expenses. Only what belongs to the vendor's own client counts, which is
 * also what lets these use the client indexes.
 */
function vendorCounts($vendors) {
    global $mysqli;
    $counts = [];
    $clients = [];
    foreach ((array) $vendors as $vendor) {
        $id = intval($vendor['vendor_id']);
        if (!$id) {
            continue;
        }
        $counts[$id] = ['open' => 0, 'ticket' => 0, 'software' => 0, 'asset' => 0, 'domain' => 0, 'service' => 0, 'credential' => 0, 'document' => 0, 'file' => 0, 'expense' => 0, 'recurring' => 0, 'spend_year' => 0.0];
        $clients[intval($vendor['vendor_client_id'])] = true;
    }
    if (!$counts) {
        return $counts;
    }
    $in = implode(',', array_keys($counts));
    $client_in = implode(',', array_keys($clients));

    $sql = mysqli_query($mysqli, "SELECT ticket_vendor_id, COUNT(*), COALESCE(SUM(ticket_resolved_at IS NULL AND ticket_closed_at IS NULL), 0)
        FROM tickets WHERE ticket_client_id IN ($client_in) AND ticket_vendor_id IN ($in) GROUP BY ticket_vendor_id");
    while ($row = mysqli_fetch_row($sql)) {
        $counts[intval($row[0])]['ticket'] = intval($row[1]);
        $counts[intval($row[0])]['open'] = intval($row[2]);
    }
    foreach (['software', 'asset'] as $kind) {
        $use = VENDOR_USES[$kind];
        $column = $use['columns'][0];
        $sql = mysqli_query($mysqli, "SELECT $column, COUNT(*) FROM {$use['table']}
            WHERE {$use['client']} IN ($client_in) AND $column IN ($in) AND {$use['archived']} IS NULL GROUP BY $column");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])][$kind] = intval($row[1]);
        }
    }
    // A domain counts once for a vendor however many of its roles the vendor has
    $sql = mysqli_query($mysqli, "SELECT domain_registrar, domain_webhost, domain_dnshost, domain_mailhost FROM domains
        WHERE domain_client_id IN ($client_in) AND domain_archived_at IS NULL
        AND (domain_registrar IN ($in) OR domain_webhost IN ($in) OR domain_dnshost IN ($in) OR domain_mailhost IN ($in))");
    while ($row = mysqli_fetch_row($sql)) {
        foreach (array_unique(array_map('intval', $row)) as $vendor_id) {
            if (isset($counts[$vendor_id])) {
                $counts[$vendor_id]['domain']++;
            }
        }
    }
    $joins = [
        'service' => ['service_vendors', 'services', 'service_id', 'service_client_id IN (' . $client_in . ')'],
        'credential' => ['vendor_credentials', 'credentials', 'credential_id', 'credential_archived_at IS NULL'],
        'document' => ['vendor_documents', 'documents', 'document_id', 'document_archived_at IS NULL'],
        'file' => ['vendor_files', 'files', 'file_id', 'file_archived_at IS NULL'],
    ];
    foreach ($joins as $kind => [$link_table, $table, $id, $where]) {
        $sql = mysqli_query($mysqli, "SELECT l.vendor_id, COUNT(*) FROM $link_table l JOIN $table t ON t.$id = l.$id
            WHERE l.vendor_id IN ($in) AND $where GROUP BY l.vendor_id");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])][$kind] = intval($row[1]);
        }
    }
    if (isset($clients[0])) {
        $year = intval(date('Y'));
        $sql = mysqli_query($mysqli, "SELECT expense_vendor_id, COUNT(*), COALESCE(SUM(IF(YEAR(expense_date) = $year, expense_amount, 0)), 0)
            FROM expenses WHERE expense_vendor_id IN ($in) AND expense_archived_at IS NULL GROUP BY expense_vendor_id");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])]['expense'] = intval($row[1]);
            $counts[intval($row[0])]['spend_year'] = floatval($row[2]);
        }
        $sql = mysqli_query($mysqli, "SELECT recurring_expense_vendor_id, COUNT(*) FROM recurring_expenses WHERE recurring_expense_vendor_id IN ($in) AND recurring_expense_archived_at IS NULL GROUP BY recurring_expense_vendor_id");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])]['recurring'] = intval($row[1]);
        }
    }
    return $counts;
}

/**
 * Why this vendor cannot be deleted, or '' if it can. Expenses, recurring
 * expenses and a payment provider's fees are the books - those vendors are
 * archived instead, so the books keep saying who was paid.
 */
function vendorDeleteBlocker($vendor_id) {
    global $mysqli;
    $vendor_id = intval($vendor_id);
    $reasons = [];
    $expenses = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM expenses WHERE expense_vendor_id = $vendor_id"))[0]);
    if ($expenses) {
        $reasons[] = $expenses === 1 ? '1 expense' : "$expenses expenses";
    }
    $recurring = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM recurring_expenses WHERE recurring_expense_vendor_id = $vendor_id"))[0]);
    if ($recurring) {
        $reasons[] = $recurring === 1 ? '1 recurring expense' : "$recurring recurring expenses";
    }
    $providers = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM payment_providers WHERE payment_provider_expense_vendor = $vendor_id"))[0]);
    if ($providers) {
        $reasons[] = 'a payment provider\'s fees';
    }
    return $reasons ? 'it has ' . implode(' and ', $reasons) . ' booked to it - archive it instead' : '';
}

/**
 * Before a vendor is deleted for good: everything that pointed at it points
 * at no vendor instead of an id that no longer exists. Its tags, links to
 * documents, files, logins and services go with it (the link tables cascade).
 */
function vendorClearReferences($vendor_id) {
    global $mysqli;
    $vendor_id = intval($vendor_id);
    foreach (VENDOR_USES as $use) {
        foreach ($use['columns'] as $column) {
            mysqli_query($mysqli, "UPDATE {$use['table']} SET $column = 0 WHERE $column = $vendor_id");
        }
    }
}

/**
 * The same vendor at the agent's other clients - by name, or made from the
 * same template - so an outage at an ISP can be checked against every
 * client that uses it. Archived vendors and clients are left out.
 */
function vendorElsewhere($vendor, $limit = 50) {
    global $mysqli;
    $vendor_id = intval($vendor['vendor_id']);
    $name = escapeSql(mb_strtolower(trim((string) $vendor['vendor_name'])));
    $template_id = intval($vendor['vendor_template_id'] ?? 0);
    $match = "LOWER(TRIM(vendor_name)) = '$name'" . ($template_id ? " OR vendor_template_id = $template_id" : '');
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_account_number, vendor_client_id, client_name
        FROM vendors
        JOIN clients ON client_id = vendor_client_id
        WHERE vendor_id != $vendor_id AND vendor_client_id > 0 AND vendor_archived_at IS NULL AND client_archived_at IS NULL
        AND ($match) " . clientScopeSql('vendor_client_id') . "
        ORDER BY client_name ASC
        LIMIT " . max(1, intval($limit)));
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** What we have spent with one of our vendors: this year, last year and in all, from unarchived expenses. */
function vendorSpend($vendor_id) {
    global $mysqli;
    $vendor_id = intval($vendor_id);
    $year = intval(date('Y'));
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(IF(YEAR(expense_date) = $year, expense_amount, 0)), 0),
            COALESCE(SUM(IF(YEAR(expense_date) = " . ($year - 1) . ", expense_amount, 0)), 0),
            COALESCE(SUM(expense_amount), 0),
            COUNT(*),
            MAX(expense_date)
        FROM expenses WHERE expense_vendor_id = $vendor_id AND expense_archived_at IS NULL"));
    return ['year' => floatval($row[0]), 'last_year' => floatval($row[1]), 'total' => floatval($row[2]), 'count' => intval($row[3]), 'last' => $row[4]];
}

/** Who did what to a vendor - its audit log entries, newest first. */
function vendorHistory($vendor_id, $limit = 50) {
    global $mysqli;
    $vendor_id = intval($vendor_id);
    $limit = max(1, intval($limit));
    $sql = mysqli_query($mysqli, "SELECT log_action, log_description, log_created_at, user_name FROM logs
        LEFT JOIN users ON user_id = log_user_id
        WHERE log_type = 'Vendor' AND log_entity_id = $vendor_id
        ORDER BY log_id DESC LIMIT $limit");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}
