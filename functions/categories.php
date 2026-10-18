<?php

/*
 * Categories - what expenses, income, tickets, clients' referrals, assets'
 * statuses and more are sorted by. Set up in Admin > Categories
 * (admin/categories.php); the handlers are admin/post/category.php.
 *
 * Each kind is a category_type. Expense, income and ticket categories go on
 * records by id (tickets.ticket_category holds it as text); the other kinds
 * by name - a client's referral, an asset's status, an interface's type - so
 * renaming one of those renames it on every record that has it, and merging
 * moves the records by name. categoryKinds() says which, and every table and
 * column that uses each kind: what the counts, merging and the safe delete
 * work from.
 *
 * Colours are stored as #rrggbb, or NULL for none (an asset status without
 * one keeps its built-in colour). Icons as "fa-name", or NULL.
 */

/*
 * The kinds, by category_type. Each:
 *   label    what Admin > Categories calls the kind
 *   one/many the category itself, in a sentence - "an asset status"
 *   icon     the kind's icon (Font Awesome class)
 *   about    what the kind is for, under the page's heading
 *   by       'id' or 'name' - how records hold it
 *   ordered  whether pickers list them in the order set here (else by name)
 *   max      the longest name the records' columns hold
 *   modules  the modules whose records hold it - a user with any of them
 *            can read the kind (the API's categories/read.php)
 *   uses     where records hold it - table, column, one/many (the record,
 *            in a sentence), url (the list showing them, {id} or {name}
 *            filled in; null when there is no such list), archived and
 *            updated (columns, or null), text (an id held as text), and for
 *            records that take several of a kind through a table of their
 *            own (canned_response_categories), link - that table's column
 *            for the record (owner), the records' table (records) and the
 *            join to it (join); records is what the API counts them as
 */
function categoryKinds() {
    $use = function ($table, $column, $one, $many, $url = null, $archived = null, $updated = null, $text = false, $link = null) {
        return ['table' => $table, 'column' => $column, 'one' => $one, 'many' => $many, 'url' => $url, 'archived' => $archived, 'updated' => $updated, 'text' => $text,
            'link' => $link, 'records' => $link['records'] ?? $table, 'join' => $link['join'] ?? ''];
    };
    return [
        'Expense' => [
            'label' => 'Expense', 'one' => 'expense category', 'many' => 'expense categories', 'icon' => 'fa-shopping-cart',
            'about' => 'What money goes out on. Every expense, recurring expense and budget line has one, and the expense reports add up by them.',
            'by' => 'id', 'ordered' => false, 'max' => 200, 'modules' => ['module_financial'],
            'uses' => [
                $use('expenses', 'expense_category_id', 'expense', 'expenses', '/agent/expenses.php?category={id}', 'expense_archived_at', 'expense_updated_at'),
                $use('recurring_expenses', 'recurring_expense_category_id', 'recurring expense', 'recurring expenses', '/agent/recurring_expenses.php?category={id}&status=all', 'recurring_expense_archived_at', 'recurring_expense_updated_at'),
                $use('budget', 'budget_category_id', 'budget month', 'budget months', '/agent/reports/budget.php', null, 'budget_updated_at'),
                $use('expense_import_rules', 'import_rule_category_id', 'import rule', 'import rules', null, null, 'import_rule_updated_at'),
                $use('payment_providers', 'payment_provider_expense_category', 'payment provider\'s fees', 'payment providers\' fees', '/admin/payment_providers.php', null, 'payment_provider_updated_at'),
                $use('settings', 'config_mileage_category_id', 'mileage setting', 'mileage settings', '/admin/settings_invoice.php#mileage'),
            ],
        ],
        'Income' => [
            'label' => 'Income', 'one' => 'income category', 'many' => 'income categories', 'icon' => 'fa-hand-holding-usd',
            'about' => 'What money comes in for. Invoices, quotes, recurring invoices, products and other income each have one, and the income reports add up by them.',
            'by' => 'id', 'ordered' => false, 'max' => 200, 'modules' => ['module_sales', 'module_financial'],
            'uses' => [
                $use('invoices', 'invoice_category_id', 'invoice', 'invoices', '/agent/invoices.php?category={id}', 'invoice_archived_at', 'invoice_updated_at'),
                $use('quotes', 'quote_category_id', 'quote', 'quotes', '/agent/quotes.php?category={id}', 'quote_archived_at', 'quote_updated_at'),
                $use('recurring_invoices', 'recurring_invoice_category_id', 'recurring invoice', 'recurring invoices', '/agent/recurring_invoices.php?category={id}&status=all', 'recurring_invoice_archived_at', 'recurring_invoice_updated_at'),
                $use('revenues', 'revenue_category_id', 'other income entry', 'other income entries', '/agent/income.php?category={id}&show=other', 'revenue_archived_at', 'revenue_updated_at'),
                $use('products', 'product_category_id', 'product', 'products', '/agent/products.php?category={id}', 'product_archived_at', 'product_updated_at'),
                $use('income_import_rules', 'import_rule_category_id', 'import rule', 'import rules', null, null, 'import_rule_updated_at'),
            ],
        ],
        'Referral' => [
            'label' => 'Referral', 'one' => 'referral', 'many' => 'referrals', 'icon' => 'fa-share-alt',
            'about' => 'How a client found you - picked on the client, and a filter on the clients list.',
            'by' => 'name', 'ordered' => false, 'max' => 200, 'modules' => ['module_client'],
            'uses' => [
                $use('clients', 'client_referral', 'client', 'clients', '/agent/clients.php?referral={name}', 'client_archived_at', 'client_updated_at'),
            ],
        ],
        'Ticket' => [
            'label' => 'Ticket', 'one' => 'ticket category', 'many' => 'ticket categories', 'icon' => 'fa-life-ring',
            'about' => 'What a ticket is about. Tickets and recurring tickets have one, canned responses are offered by them, and the ticket reports add up by them.',
            'by' => 'id', 'ordered' => false, 'max' => 200, 'modules' => ['module_support'],
            'uses' => [
                $use('tickets', 'ticket_category', 'ticket', 'tickets', '/agent/tickets.php?category={id}&show=all', 'ticket_archived_at', 'ticket_updated_at', true),
                $use('recurring_tickets', 'recurring_ticket_category', 'recurring ticket', 'recurring tickets', '/agent/recurring_tickets.php?category={id}', null, 'recurring_ticket_updated_at', true),
                $use('canned_response_categories', 'category_id', 'canned response', 'canned responses', '/admin/canned_responses.php?category={id}', 'canned_response_archived_at', null, false,
                    ['owner' => 'canned_response_id', 'records' => 'canned_responses', 'join' => 'JOIN canned_responses USING (canned_response_id)']),
            ],
        ],
        'network_interface' => [
            'label' => 'Network Interface', 'one' => 'interface type', 'many' => 'interface types', 'icon' => 'fa-ethernet',
            'about' => 'The kinds of port on an asset - Ethernet, SFP+, WiFi - picked on each of its interfaces.',
            'by' => 'name', 'ordered' => true, 'max' => 50, 'modules' => ['module_support'],
            'uses' => [
                $use('asset_interfaces', 'interface_type', 'interface', 'interfaces', null, 'interface_archived_at', 'interface_updated_at'),
            ],
        ],
        'asset_status' => [
            'label' => 'Asset Status', 'one' => 'asset status', 'many' => 'asset statuses', 'icon' => 'fa-heartbeat',
            'about' => 'Where an asset stands - deployed, out for repair, retired. Its colour is the status badge\'s; without one the built-in statuses keep theirs.',
            'by' => 'name', 'ordered' => true, 'max' => 200, 'modules' => ['module_support'],
            'uses' => [
                $use('assets', 'asset_status', 'asset', 'assets', '/agent/assets.php?status={name}', 'asset_archived_at', 'asset_updated_at'),
            ],
        ],
        'software_type' => [
            'label' => 'Software Type', 'one' => 'software type', 'many' => 'software types', 'icon' => 'fa-cube',
            'about' => 'What kind of software a license is for - SaaS, security, operating system. Its icon is the license\'s.',
            'by' => 'name', 'ordered' => true, 'max' => 200, 'modules' => ['module_support'],
            'uses' => [
                $use('software', 'software_type', 'license', 'licenses', '/agent/software.php?software_type={name}', 'software_archived_at', 'software_updated_at'),
                $use('software_templates', 'software_template_type', 'license template', 'license templates', '/admin/software_templates.php', 'software_template_archived_at', 'software_template_updated_at'),
            ],
        ],
        'rack_type' => [
            'label' => 'Rack Type', 'one' => 'rack type', 'many' => 'rack types', 'icon' => 'fa-server',
            'about' => 'The kinds of rack - open frame, enclosed cabinet, wall mount - picked on each rack.',
            'by' => 'name', 'ordered' => true, 'max' => 50, 'modules' => ['module_support'],
            'uses' => [
                $use('racks', 'rack_type', 'rack', 'racks', '/agent/racks.php?type={name}', 'rack_archived_at', 'rack_updated_at'),
            ],
        ],
        'contact_note_type' => [
            'label' => 'Contact Note Type', 'one' => 'contact note type', 'many' => 'contact note types', 'icon' => 'fa-address-book',
            'about' => 'What a note on a contact records - a call, an email, a meeting. Its icon marks the note.',
            'by' => 'name', 'ordered' => true, 'max' => 200, 'modules' => ['module_client'],
            'uses' => [
                $use('contact_notes', 'contact_note_type', 'contact note', 'contact notes', null, 'contact_note_archived_at', 'contact_note_updated_at'),
            ],
        ],
        'asset_note_type' => [
            'label' => 'Asset Note Type', 'one' => 'asset note type', 'many' => 'asset note types', 'icon' => 'fa-desktop',
            'about' => 'What a note on an asset records - maintenance, a repair, an upgrade. Its icon marks the note.',
            'by' => 'name', 'ordered' => true, 'max' => 200, 'modules' => ['module_support'],
            'uses' => [
                $use('asset_notes', 'asset_note_type', 'asset note', 'asset notes', null, 'asset_note_archived_at', 'asset_note_updated_at'),
            ],
        ],
    ];
}

/* One kind's settings, with 'kind' (its category_type) - or null. */
function categoryKind($kind) {
    $kinds = categoryKinds();
    $kind = (string) $kind;
    return isset($kinds[$kind]) ? $kinds[$kind] + ['kind' => $kind] : null;
}

/* "an asset status", "a ticket category" - for messages. */
function categoryKindPhrase($kind) {
    $one = categoryKind($kind)['one'] ?? 'category';
    return (in_array($one[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ') . $one;
}

/* "12 assets" / "1 asset" - a count of records of one use. */
function categoryUseText($n, array $use) {
    return number_format($n) . ' ' . ($n === 1 ? $use['one'] : $use['many']);
}

/* The colours offered in the category window - any other can be picked too. */
const CATEGORY_COLORS = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0', '#795548', '#6c757d', '#343a40', '#001f3f'];

/* Icons offered in the category window, by what they suit - any Font Awesome solid icon's name works. */
const CATEGORY_ICONS = [
    'shopping-cart', 'receipt', 'file-invoice-dollar', 'money-bill-wave', 'coins', 'credit-card', 'piggy-bank', 'hand-holding-usd', 'dollar-sign', 'percent',
    'university', 'landmark', 'balance-scale', 'briefcase', 'building', 'store', 'industry', 'warehouse', 'truck', 'shipping-fast',
    'plane', 'car', 'gas-pump', 'route', 'utensils', 'coffee', 'bullhorn', 'ad', 'gift', 'graduation-cap',
    'user', 'user-tie', 'users', 'user-plus', 'user-minus', 'user-secret', 'handshake', 'share-alt', 'globe', 'thumbs-up',
    'life-ring', 'question-circle', 'exclamation-triangle', 'bug', 'fire', 'bell', 'shield-alt', 'lock', 'key', 'fish',
    'desktop', 'laptop', 'server', 'hdd', 'database', 'print', 'mobile-alt', 'phone-alt', 'fax', 'microchip',
    'network-wired', 'ethernet', 'wifi', 'cloud', 'plug', 'satellite-dish', 'project-diagram', 'code', 'terminal', 'cube',
    'layer-group', 'window-maximize', 'cogs', 'tools', 'wrench', 'sliders-h', 'arrow-circle-up', 'sync-alt', 'clipboard-check', 'calendar-check',
    'heartbeat', 'check-circle', 'pause-circle', 'times-circle', 'ban', 'archive', 'box', 'recycle', 'search', 'map-marker-alt',
    'envelope', 'comments', 'people-arrows', 'sticky-note', 'book', 'file-alt', 'star', 'flag', 'tag', 'circle',
];

/* A colour as #rrggbb - or '' for none (anything that isn't a colour). */
function categoryColor($color) {
    $color = strtolower(trim((string) $color));
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m)) {
        return "#$m[1]$m[1]$m[2]$m[2]$m[3]$m[3]";
    }
    if (preg_match('/^#[0-9a-f]{8}$/', $color)) {
        return substr($color, 0, 7);
    }
    return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '';
}

/* An icon's bare Font Awesome name ("phone-alt") from "fa-phone-alt", "fas fa-phone-alt" or the name - or '' for none. */
function categoryIcon($icon) {
    $icon = preg_replace('/^(fa[srb]? +)?fa-/', '', strtolower(trim(strip_tags((string) $icon))));
    $icon = preg_replace('/[^a-z0-9-]/', '', $icon);
    return mb_substr(trim($icon, '-'), 0, 50);
}

/* A colour for a new category - the next one round the palette. */
function categoryNextColor($kind) {
    global $mysqli;
    $palette = ['#0d6efd', '#198754', '#6f42c1', '#fd7e14', '#d63384', '#20c997', '#dc3545', '#0dcaf0', '#6610f2', '#ffc107'];
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(category_id) FROM categories WHERE category_type = '" . escapeSql($kind) . "'"));
    return $palette[intval($row[0]) % count($palette)];
}

/*
 * A category as it looks on records - an asset status as its badge,
 * anything else its icon (or a dot) in its colour, then the name. With
 * nowhere to go - for Admin > Categories and the windows. $row: category_name,
 * category_type, category_color, category_icon, category_description.
 */
function categoryChip(array $row) {
    $name = escapeHtml((string) ($row['category_name'] ?? ''));
    $about = trim((string) ($row['category_description'] ?? ''));
    $title = $about !== '' ? ' title="' . escapeHtml($about) . '"' : '';
    $color = categoryColor($row['category_color'] ?? '');
    if (($row['category_type'] ?? '') === 'asset_status') {
        // Its colour with text that reads on it, or the built-in status's tone
        $badge = assetStatusBadge((string) ($row['category_name'] ?? ''), $color);
        $look = $color ? "\" style=\"background-color: $color; color: " . tagTextColor($color) : ' text-bg-' . ($badge['class'] ?: 'secondary');
        return "<span class=\"badge rounded-pill fw-normal itflow-category-badge$look\"$title>$name</span>";
    }
    $icon = categoryIcon($row['category_icon'] ?? '');
    $style = $color ? " style=\"color: $color\"" : '';
    $mark = $icon !== ''
        ? "<i class=\"fas fa-fw fa-$icon itflow-category-mark\"$style aria-hidden=\"true\"></i>"
        : '<span class="itflow-category-dot itflow-category-mark"' . ($color ? " style=\"background-color: $color\"" : '') . ' aria-hidden="true"></span>';
    return "<span class=\"itflow-category\"$title>$mark<span class=\"itflow-category-name\">$name</span></span>";
}

/* One category, or null. */
function categoryRow($category_id) {
    global $mysqli;
    $category_id = intval($category_id);
    return $category_id ? (mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_id, category_name, category_description, category_type, category_color, category_icon,
            category_order, category_created_at, category_archived_at
        FROM categories WHERE category_id = $category_id LIMIT 1")) ?: null) : null;
}

/* A kind's categories - id => row, archived ones too. In the order pickers list them. */
function categoryRows($kind) {
    global $mysqli;
    $config = categoryKind($kind);
    if (!$config) {
        return [];
    }
    $order = $config['ordered'] ? 'category_order ASC, category_name ASC' : 'category_name ASC';
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT category_id, category_name, category_description, category_type, category_color, category_icon, category_order,
            category_created_at, category_archived_at
        FROM categories WHERE category_type = '" . escapeSql($config['kind']) . "' ORDER BY $order");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['category_id'])] = $row;
    }
    return $rows;
}

/* How a by-name value is matched to a category - as the database compares them, case and trailing spaces aside. */
function categoryNameKey($name) {
    return mb_strtolower(rtrim((string) $name));
}

/*
 * What uses each of a kind's categories - id => ['all' => n, 'archived' => n,
 * 'uses' => [use index => ['all' => n, 'archived' => n]]]. Archived records
 * count too; 'archived' says how many of them are. A category nothing uses
 * isn't in it.
 */
function categoryUsage($kind) {
    global $mysqli;
    $config = categoryKind($kind);
    if (!$config) {
        return [];
    }

    // A by-name value belongs to every category of the kind with that name (only ever one, but for duplicates made before names were unique)
    $by_name = [];
    if ($config['by'] === 'name') {
        foreach (categoryRows($kind) as $category_id => $row) {
            $by_name[categoryNameKey($row['category_name'])][] = $category_id;
        }
    }

    $usage = [];
    foreach ($config['uses'] as $index => $use) {
        $archived = $use['archived'] ? "SUM({$use['archived']} IS NOT NULL)" : '0';
        $sql = mysqli_query($mysqli, "SELECT {$use['column']}, COUNT(*), $archived FROM {$use['table']} {$use['join']}
            WHERE {$use['column']} IS NOT NULL AND {$use['column']} <> " . ($config['by'] === 'name' || $use['text'] ? "''" : '0') . "
            GROUP BY {$use['column']}");
        while ($row = mysqli_fetch_row($sql)) {
            $ids = $config['by'] === 'name' ? ($by_name[categoryNameKey($row[0])] ?? []) : [intval($row[0])];
            foreach ($ids as $category_id) {
                if (!$category_id) {
                    continue;
                }
                $usage[$category_id] = $usage[$category_id] ?? ['all' => 0, 'archived' => 0, 'uses' => []];
                $usage[$category_id]['all'] += intval($row[1]);
                $usage[$category_id]['archived'] += intval($row[2]);
                $usage[$category_id]['uses'][$index] = [
                    'all' => ($usage[$category_id]['uses'][$index]['all'] ?? 0) + intval($row[1]),
                    'archived' => ($usage[$category_id]['uses'][$index]['archived'] ?? 0) + intval($row[2]),
                ];
            }
        }
    }
    return $usage;
}

/*
 * For a kind held by name: the names records have that aren't one of its
 * categories (typed in before there was a picker, or a category since
 * deleted) - name => ['all' => n, 'archived' => n, 'uses' => [...]], as
 * categoryUsage(), most used first. Spelt as most records have it.
 */
function categoryLooseNames($kind) {
    global $mysqli;
    $config = categoryKind($kind);
    if (!$config || $config['by'] !== 'name') {
        return [];
    }
    $known = [];
    foreach (categoryRows($kind) as $row) {
        $known[categoryNameKey($row['category_name'])] = true;
    }
    $loose = [];
    $spelling = [];
    foreach ($config['uses'] as $index => $use) {
        $archived = $use['archived'] ? "SUM({$use['archived']} IS NOT NULL)" : '0';
        $sql = mysqli_query($mysqli, "SELECT {$use['column']}, COUNT(*), $archived FROM {$use['table']} {$use['join']}
            WHERE {$use['column']} IS NOT NULL AND TRIM({$use['column']}) <> '' GROUP BY {$use['column']} ORDER BY COUNT(*) DESC");
        while ($row = mysqli_fetch_row($sql)) {
            $key = categoryNameKey($row[0]);
            if (isset($known[$key])) {
                continue;
            }
            $spelling[$key] = $spelling[$key] ?? trim($row[0]);
            $loose[$key] = $loose[$key] ?? ['all' => 0, 'archived' => 0, 'uses' => []];
            $loose[$key]['all'] += intval($row[1]);
            $loose[$key]['archived'] += intval($row[2]);
            $loose[$key]['uses'][$index] = [
                'all' => ($loose[$key]['uses'][$index]['all'] ?? 0) + intval($row[1]),
                'archived' => ($loose[$key]['uses'][$index]['archived'] ?? 0) + intval($row[2]),
            ];
        }
    }
    uasort($loose, function ($a, $b) {
        return $b['all'] <=> $a['all'];
    });
    $named = [];
    foreach ($loose as $key => $used) {
        $named[$spelling[$key]] = $named[$spelling[$key]] ?? $used;
    }
    return $named;
}

/* What uses one category - as categoryUsage(), all zero when nothing does. */
function categoryUsageFor($category_id) {
    $row = categoryRow($category_id);
    $empty = ['all' => 0, 'archived' => 0, 'uses' => []];
    return $row ? (categoryUsage($row['category_type'])[intval($row['category_id'])] ?? $empty) : $empty;
}

/*
 * What uses a category, in words - "42 expenses (3 archived) · 2 budget
 * months", each a link to the list showing them when there is one ($links).
 * HTML. '' when nothing does.
 */
function categoryUsageHtml(array $row, array $used, $links = true) {
    $config = categoryKind($row['category_type']);
    if (!$config || empty($used['uses'])) {
        return '';
    }
    $parts = [];
    foreach ($config['uses'] as $index => $use) {
        if (empty($used['uses'][$index]['all'])) {
            continue;
        }
        $n = $used['uses'][$index]['all'];
        $text = escapeHtml(categoryUseText($n, $use));
        if ($links && $use['url']) {
            $url = str_replace(['{id}', '{name}'], [intval($row['category_id']), rawurlencode((string) $row['category_name'])], $use['url']);
            $text = '<a href="' . escapeHtml($url) . '">' . $text . '</a>';
        }
        $archived = $used['uses'][$index]['archived'] ?? 0;
        $parts[] = '<span class="text-nowrap">' . $text . ($archived ? ' <span class="text-secondary">(' . number_format($archived) . ' archived)</span>' : '') . '</span>';
    }
    return implode(' <span class="text-secondary" aria-hidden="true">&middot;</span> ', $parts);
}

/* What uses a category, as plain text for the audit log and messages - "42 expenses and 2 budget months". */
function categoryUsageText(array $row, array $used) {
    $config = categoryKind($row['category_type']);
    $parts = [];
    foreach ($config['uses'] ?? [] as $index => $use) {
        if (!empty($used['uses'][$index]['all'])) {
            $parts[] = categoryUseText($used['uses'][$index]['all'], $use);
        }
    }
    if (count($parts) > 1) {
        $last = array_pop($parts);
        return implode(', ', $parts) . ' and ' . $last;
    }
    return $parts[0] ?? '';
}

/*
 * A category from the category window - [$fields, $error]. $in: name,
 * description, color ('' for none), icon ('' for none), and for a new one
 * type. Raw values; categoryCreate() and categoryUpdate() escape them. The
 * name is unique within its kind - archived ones too - and no longer than
 * the records' columns hold.
 */
function categoryFromInput(array $in, $existing = null) {
    global $mysqli;
    $kind = $existing ? (string) $existing['category_type'] : (string) ($in['type'] ?? '');
    $config = categoryKind($kind);
    if (!$config) {
        return [null, 'Pick what the category is for'];
    }
    // Stored without markup (escapeSql() strips it), so checked without it
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['name'] ?? ''))));
    $description = trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['description'] ?? ''))), 0, 255));
    if ($name === '') {
        return [null, ucfirst(categoryKindPhrase($kind)) . ' needs a name'];
    }
    if (mb_strlen($name) > $config['max']) {
        return [null, 'Keep the name to ' . $config['max'] . ' characters - that is all ' . $config['uses'][0]['many'] . ' hold'];
    }
    // A category already sharing its name with another (made before names were unique) can still be saved under it
    $kept_name = $existing && categoryNameKey($existing['category_name']) === categoryNameKey($name);
    $exclude = $existing ? 'AND category_id != ' . intval($existing['category_id']) : '';
    $same = $kept_name ? null : mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_name, category_archived_at FROM categories
        WHERE category_type = '" . escapeSql($kind) . "' AND category_name = '" . escapeSql($name) . "' $exclude LIMIT 1"));
    if ($same) {
        return [null, 'There is already ' . ($same['category_archived_at'] ? 'an archived ' . $config['one'] : categoryKindPhrase($kind)) . ' called ' . $same['category_name']
            . ($same['category_archived_at'] ? ' - restore it instead' : '')];
    }
    $color = trim((string) ($in['color'] ?? ''));
    $color = $color === '' ? '' : categoryColor($color);
    return [['type' => $kind, 'name' => $name, 'description' => $description, 'color' => $color, 'icon' => categoryIcon($in['icon'] ?? '')], ''];
}

/* The SET list for a category's fields. */
function categorySetSql(array $f) {
    return "category_name = '" . escapeSql($f['name']) . "', category_description = " . ($f['description'] !== '' ? "'" . escapeSql($f['description']) . "'" : 'NULL') . ",
        category_color = " . ($f['color'] !== '' ? "'" . escapeSql($f['color']) . "'" : 'NULL') . ", category_icon = " . ($f['icon'] !== '' ? "'fa-" . escapeSql($f['icon']) . "'" : 'NULL');
}

/* Make a category - last in its kind's order. Its id. */
function categoryCreate(array $fields) {
    global $mysqli;
    $kind = escapeSql($fields['type']);
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(category_order), 0) FROM categories WHERE category_type = '$kind'"))[0]) + 1;
    mysqli_query($mysqli, "INSERT INTO categories SET category_type = '$kind', category_order = $order, " . categorySetSql($fields));
    return intval(mysqli_insert_id($mysqli));
}

/*
 * Save a category's name, description, colour and icon. A kind held by name
 * takes a new name onto every record that has the old one - unless another
 * category of the kind still has the old name. How many records were renamed.
 */
function categoryUpdate(array $row, array $fields) {
    global $mysqli;
    $category_id = intval($row['category_id']);
    mysqli_query($mysqli, "UPDATE categories SET " . categorySetSql($fields) . " WHERE category_id = $category_id");

    $config = categoryKind($row['category_type']);
    if (!$config || $config['by'] !== 'name' || $row['category_name'] === $fields['name']) {
        return 0;
    }
    $kind = escapeSql($config['kind']);
    $old = escapeSql($row['category_name']);
    if (mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_type = '$kind' AND category_name = '$old' AND category_id != $category_id LIMIT 1"))) {
        return 0;
    }
    return categoryMoveRecords($config, "= '$old'", "'" . escapeSql($fields['name']) . "'");
}

/*
 * Point every record of a kind whose category matches $where_sql (" = 'x'",
 * " IN (1, 2)") at $to_sql instead - leaving when each was last changed as
 * it was, as the record itself didn't change. A record that takes several
 * (a use with a link) has the new one added - once, if it had it already -
 * and the old ones taken off. How many records moved.
 */
function categoryMoveRecords(array $config, $where_sql, $to_sql) {
    global $mysqli;
    $moved = 0;
    foreach ($config['uses'] as $use) {
        if (!empty($use['link'])) {
            mysqli_query($mysqli, "INSERT IGNORE INTO {$use['table']} ({$use['link']['owner']}, {$use['column']})
                SELECT {$use['link']['owner']}, $to_sql FROM {$use['table']} WHERE {$use['column']} $where_sql");
            mysqli_query($mysqli, "DELETE FROM {$use['table']} WHERE {$use['column']} $where_sql");
            $moved += max(0, mysqli_affected_rows($mysqli));
            continue;
        }
        $keep = $use['updated'] ? ", {$use['updated']} = {$use['updated']}" : '';
        mysqli_query($mysqli, "UPDATE {$use['table']} SET {$use['column']} = $to_sql$keep WHERE {$use['column']} $where_sql");
        $moved += max(0, mysqli_affected_rows($mysqli));
    }
    return $moved;
}

/* Archive a category - it stays on its records, and isn't offered for new ones. */
function categoryArchive($category_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE categories SET category_archived_at = NOW() WHERE category_archived_at IS NULL AND category_id = " . intval($category_id));
}

/* Offer an archived category again. */
function categoryRestore($category_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE categories SET category_archived_at = NULL WHERE category_id = " . intval($category_id));
}

/*
 * Whether a category can be deleted - only while nothing uses it, so no
 * record is left pointing at nothing (or, for a kind held by name, at a name
 * that can't be picked). One made before names were unique can go while
 * another of its kind has its name, as its records are that one's.
 * '' when it can, else why not.
 */
function categoryDeleteBlocker(array $row, array $used) {
    global $mysqli;
    if (!$used['all']) {
        return '';
    }
    $config = categoryKind($row['category_type']);
    if ($config && $config['by'] === 'name' && mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM categories
            WHERE category_type = '" . escapeSql($config['kind']) . "' AND category_name = '" . escapeSql($row['category_name']) . "' AND category_id != " . intval($row['category_id']) . " LIMIT 1"))) {
        return '';
    }
    return 'In use on ' . categoryUsageText($row, $used) . ' - merge it into another, or archive it';
}

/* Delete a category, if nothing uses it (categoryDeleteBlocker()). Whether it went; $error says why not. */
function categoryDelete($category_id, &$error = '') {
    global $mysqli;
    $row = categoryRow($category_id);
    if (!$row) {
        $error = 'That category is gone';
        return false;
    }
    $error = categoryDeleteBlocker($row, categoryUsageFor($row['category_id']));
    if ($error !== '') {
        return false;
    }
    mysqli_query($mysqli, "DELETE FROM categories WHERE category_id = " . intval($row['category_id']));
    return mysqli_affected_rows($mysqli) > 0;
}

/*
 * Merge categories into one of the same kind: every record with one of them
 * gets $into_id instead (by id or by name, as the kind is held), and they
 * are deleted. A month budgeted under more than one ends with one line, of
 * their total. How many records moved.
 */
function categoryMerge(array $from_ids, $into_id) {
    global $mysqli;
    $into = categoryRow($into_id);
    $config = $into ? categoryKind($into['category_type']) : null;
    if (!$config) {
        return 0;
    }
    $into_id = intval($into['category_id']);

    // Only categories of the same kind
    $from = [];
    $from_list = implode(',', array_map('intval', array_diff(array_unique(array_filter(array_map('intval', $from_ids))), [$into_id])) ?: [0]);
    $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_id IN ($from_list) AND category_type = '" . escapeSql($config['kind']) . "'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $from[intval($row['category_id'])] = $row['category_name'];
    }
    if (!$from) {
        return 0;
    }

    if ($config['by'] === 'name') {
        // Names the kept one has are already its
        $names = array_filter($from, function ($name) use ($into) {
            return categoryNameKey($name) !== categoryNameKey($into['category_name']);
        });
        $moved = $names ? categoryMoveRecords($config, "IN ('" . implode("', '", array_map('escapeSql', $names)) . "')", "'" . escapeSql($into['category_name']) . "'") : 0;
    } else {
        // An id held as text (tickets) is matched as text - comparing it to a number can fail on a value that isn't one
        $moved = 0;
        foreach ($config['uses'] as $use) {
            $list = $use['text'] ? "'" . implode("', '", array_keys($from)) . "'" : implode(', ', array_keys($from));
            $moved += categoryMoveRecords(['uses' => [$use]], "IN ($list)", $use['text'] ? "'$into_id'" : $into_id);
        }
        if ($config['kind'] === 'Expense') {
            categoryFoldBudget($into_id);
        }
    }

    mysqli_query($mysqli, "DELETE FROM categories WHERE category_id IN (" . implode(',', array_keys($from)) . ")");
    return $moved;
}

/* One budget line per month for an expense category - after a merge gave it two, their total on the first. */
function categoryFoldBudget($category_id) {
    global $mysqli;
    $category_id = intval($category_id);
    $sql = mysqli_query($mysqli, "SELECT budget_year, budget_month, MIN(budget_id), SUM(budget_amount) FROM budget
        WHERE budget_category_id = $category_id GROUP BY budget_year, budget_month HAVING COUNT(*) > 1");
    while ($row = mysqli_fetch_row($sql)) {
        $keep = intval($row[2]);
        mysqli_query($mysqli, "UPDATE budget SET budget_amount = " . round(floatval($row[3]), 2) . " WHERE budget_id = $keep");
        mysqli_query($mysqli, "DELETE FROM budget WHERE budget_category_id = $category_id AND budget_year = " . intval($row[0]) . " AND budget_month = " . intval($row[1]) . " AND budget_id != $keep");
    }
}

/*
 * Put a kind's categories in this order - the ids as listed, then the rest
 * (the archived ones, say) after them in the order they had. Numbered from
 * 1. How many moved.
 */
function categoryReorder($kind, array $ids) {
    global $mysqli;
    $rows = categoryRows($kind);
    $ordered = [];
    foreach ($ids as $id) {
        $id = intval($id);
        if (isset($rows[$id]) && !in_array($id, $ordered, true)) {
            $ordered[] = $id;
        }
    }
    foreach (array_keys($rows) as $id) {
        if (!in_array($id, $ordered, true)) {
            $ordered[] = $id;
        }
    }
    $moved = 0;
    foreach ($ordered as $position => $id) {
        if (intval($rows[$id]['category_order']) !== $position + 1) {
            mysqli_query($mysqli, "UPDATE categories SET category_order = " . ($position + 1) . " WHERE category_id = $id");
            $moved++;
        }
    }
    return $moved;
}

/*
 * The categories a picker in a form offers - a kind's current ones, in its
 * order, plus those in $keep (what the record already has: ids, or names
 * for a kind held by name) even when archived. A kept name that isn't one
 * of the kind's categories (typed in before there was a picker, or since
 * deleted) is offered as it is, with no id, so saving the record keeps it.
 * Rows keyed by the value the picker posts - the id, or the name. $opts:
 * 'since' - also the ones archived after then (a billing record offers what
 * was current when it was made); 'exclude' - names never offered unless kept.
 */
function categoryPicker($kind, $keep = [], array $opts = []) {
    $config = categoryKind($kind);
    if (!$config) {
        return [];
    }
    $by_name = $config['by'] === 'name';
    $keep_keys = [];
    foreach ((array) $keep as $value) {
        $value = $by_name ? trim((string) $value) : intval($value);
        if ($value !== '' && $value !== 0) {
            $keep_keys[$by_name ? categoryNameKey($value) : $value] = $value;
        }
    }
    $since = !empty($opts['since']) ? strtotime($opts['since']) : null;
    $exclude = array_map('categoryNameKey', (array) ($opts['exclude'] ?? []));
    $rows = [];
    foreach (categoryRows($kind) as $category_id => $row) {
        $key = $by_name ? categoryNameKey($row['category_name']) : $category_id;
        $kept = isset($keep_keys[$key]);
        if (!$kept && in_array(categoryNameKey($row['category_name']), $exclude, true)) {
            continue;
        }
        if (!$kept && $row['category_archived_at'] && !($since && strtotime($row['category_archived_at']) > $since)) {
            continue;
        }
        $value = $by_name ? $row['category_name'] : $category_id;
        if (!isset($rows[$value])) {
            $rows[$value] = $row;
        }
        unset($keep_keys[$key]);
    }
    // Kept names no category has
    if ($by_name) {
        foreach ($keep_keys as $name) {
            $rows[$name] = ['category_id' => 0, 'category_name' => $name, 'category_description' => null, 'category_type' => $config['kind'],
                'category_color' => null, 'category_icon' => null, 'category_archived_at' => null, 'loose' => true];
        }
    }
    return $rows;
}

/* One picker <option> for a categoryPicker() row - its colour, icon and description, the value as the kind is held, and a note when archived or not set up. */
function categoryOptionHtml(array $row, $selected = false) {
    $config = categoryKind($row['category_type'] ?? '');
    $value = $config && $config['by'] === 'name' ? (string) $row['category_name'] : (string) intval($row['category_id']);
    $note = !empty($row['loose']) ? ' (not set up)' : ($row['category_archived_at'] ? ' (archived)' : '');
    // An asset status in its badge's colour - its own, or the built-in status's
    $color = categoryColor($row['category_color'] ?? '');
    if (($row['category_type'] ?? '') === 'asset_status') {
        $color = assetStatusColor((string) $row['category_name'], $color);
    }
    $icon = categoryIcon($row['category_icon'] ?? '');
    if ($icon === '' && ($row['category_type'] ?? '') === 'software_type') {
        // A software type without one of its own: the icon read from its name, as its licenses show
        $icon = categoryIcon(softwareTypeStyle((string) $row['category_name'])['icon']);
    }
    $data = selectOptionData(['color' => $color, 'icon' => $icon !== '' ? "fas fa-$icon" : '', 'description' => trim((string) ($row['category_description'] ?? ''))]);
    return '<option value="' . escapeHtml($value) . '"' . ($selected ? ' selected' : '') . $data . '>' . escapeHtml($row['category_name'] . $note) . '</option>';
}

/*
 * A picker's <option>s for a kind - categoryPicker() with $selected kept and
 * chosen (an id, or a name for a kind held by name). The form writes its own
 * "nothing picked" option first. $opts as categoryPicker(). HTML.
 */
function categoryOptionsHtml($kind, $selected = null, array $opts = []) {
    $config = categoryKind($kind);
    if (!$config) {
        return '';
    }
    $by_name = $config['by'] === 'name';
    $selected_key = $by_name ? categoryNameKey(trim((string) $selected)) : intval($selected);
    $html = '';
    foreach (categoryPicker($kind, $selected === null ? [] : [$selected], $opts) as $value => $row) {
        $key = $by_name ? categoryNameKey($value) : intval($value);
        $html .= categoryOptionHtml($row, $selected !== null && $selected_key !== '' && $selected_key !== 0 && $key === $selected_key) . "\n";
    }
    return $html;
}

/*
 * The + beside a category picker in a form: it opens the category window
 * over the form, and what is made there is put in the picker and picked
 * (js/category_form.js) - nothing reloads, nothing typed is lost. Admins
 * only, as Admin > Categories is; '' for anyone else. $class matches the
 * form's other buttons.
 */
function categoryAddButtonHtml($kind, $class = 'btn btn-outline-secondary') {
    global $session_is_admin;
    $config = categoryKind($kind);
    if (empty($session_is_admin) || !$config) {
        return '';
    }
    return '<button type="button" class="' . escapeHtml($class) . ' ajax-modal" title="New ' . escapeHtml($config['one']) . '" aria-label="New ' . escapeHtml($config['one']) . '"'
        . ' data-modal-url="/admin/modals/category/category_add.php?category=' . urlencode($config['kind']) . '&amp;inline=1" data-category-add><i class="fas fa-fw fa-plus"></i></button>';
}

/*
 * A name typed into a picker for a kind held by name (a client's referral)
 * as the kind's category: the one with that name, any case, archived or not,
 * or a new one made for it. The name to keep on the record - its
 * category's own spelling - or '' for none.
 */
function categoryEnsure($kind, $name) {
    global $mysqli, $session_name;
    $config = categoryKind($kind);
    $name = trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) $name)), 0, $config['max'] ?? 200));
    if (!$config || $config['by'] !== 'name' || $name === '') {
        return $name;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = '" . escapeSql($config['kind']) . "'
        AND category_name = '" . escapeSql($name) . "' ORDER BY category_archived_at IS NOT NULL, category_id LIMIT 1"));
    if ($row) {
        return $row['category_name'];
    }
    $category_id = categoryCreate(['type' => $config['kind'], 'name' => $name, 'description' => '', 'color' => categoryNextColor($config['kind']), 'icon' => '']);
    logAudit("Category", "Create", ($session_name ?? 'ITFlow') . " created " . $config['one'] . " " . escapeSql($name), 0, $category_id);
    return $name;
}

/*
 * A list's category filter <option>s - the kind's categories its records
 * have ($used_sql: a query giving the ids, or the names for a kind held by
 * name, on the list's records), archived ones too as records keep them, and
 * a name that is no category marked so; plus $selected, chosen. In the
 * kind's order, each with its colour and icon. Without $used_sql, the
 * current ones. $opts as categoryPicker()'s - names in 'exclude' are left
 * out even when records have them, unless chosen. The list writes its own
 * "any" option first. HTML.
 */
function categoryFilterOptionsHtml($kind, $selected = null, $used_sql = '', array $opts = []) {
    global $mysqli;
    $config = categoryKind($kind);
    if (!$config) {
        return '';
    }
    $by_name = $config['by'] === 'name';
    $used = null;
    if ($used_sql !== '') {
        $used = [];
        $sql = mysqli_query($mysqli, $used_sql);
        while ($row = mysqli_fetch_row($sql)) {
            $value = $by_name ? trim((string) $row[0]) : intval($row[0]);
            if ($value !== '' && $value !== 0) {
                $used[$by_name ? categoryNameKey($value) : $value] = $value;
            }
        }
    }
    $selected_key = $by_name ? categoryNameKey(trim((string) $selected)) : intval($selected);
    $keep = array_values($used ?? []);
    if ($selected_key !== '' && $selected_key !== 0) {
        $keep[] = $selected;
    }
    $exclude = array_map('categoryNameKey', (array) ($opts['exclude'] ?? []));
    $html = '';
    foreach (categoryPicker($kind, $keep, $opts) as $value => $row) {
        $key = $by_name ? categoryNameKey($value) : intval($value);
        if ($key !== $selected_key && (($used !== null && !isset($used[$key])) || in_array(categoryNameKey($row['category_name']), $exclude, true))) {
            continue;
        }
        $html .= categoryOptionHtml($row, $key === $selected_key && $selected_key !== '' && $selected_key !== 0) . "\n";
    }
    return $html;
}

/*
 * A category's mark beside its name in a list or quick look - its icon in
 * its colour, or a dot of its colour (an empty one without). $dot: the
 * page's own dot to use instead when it has no icon - the ticket lists' -
 * written for a colour (%s: a style attribute, or ''). HTML.
 */
function categoryMarkHtml($color, $icon = '', $dot = '') {
    $color = categoryColor($color);
    $icon = categoryIcon($icon);
    if ($icon !== '') {
        return '<i class="fas fa-fw fa-' . $icon . ' itflow-category-icon"' . ($color ? ' style="color: ' . $color . '"' : '') . ' aria-hidden="true"></i>';
    }
    if ($dot !== '') {
        return sprintf($dot, $color ? ' style="color: ' . $color . '"' : '');
    }
    return '<span class="itflow-category-dot"' . ($color ? ' style="background-color: ' . $color . '"' : '') . ' aria-hidden="true"></span>';
}
