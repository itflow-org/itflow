<?php
defined('FROM_POST_HANDLER') || defined('FROM_STARTER_CONTENT') || die("Direct file access is not allowed");

/*
 * ITFlow - Starter content library
 *
 * A catalog of opinionated defaults for a typical MSP, loaded on demand from
 * Maintenance > Starter Content rather than at install time. Every pack is
 * idempotent - anything already present by name is skipped - so a pack can be
 * loaded on a brand new install or a five year old one and only ever adds what
 * is missing. Nothing here updates or deletes an existing row.
 *
 * The file is deliberately named _model so admin/post.php does not glob it in
 * on every admin request. It is required on demand by the page and the handler.
 */

// ------------------------------
// starterContentPacks
// Registry - the key is what the buttons post back, and the only accepted value.
// Order is load order. A pack with a 'requires' still loads on its own, it just
// produces less - products land uncategorised, project templates land with no stages.
// ------------------------------
function starterContentPacks() {
    return [
        'categories' => [
            'label' => 'Categories',
            'icon' => 'fa-list-ul',
            'description' => 'Expense, income, referral, ticket and Knowledge Base categories for an MSP. Income categories are also what products are filed under.',
        ],
        'tags' => [
            'label' => 'Tags',
            'icon' => 'fa-tags',
            'description' => 'Tags for clients, locations, contacts, credentials, assets, vendors, domains, licenses, services, networks, documents, tickets and Knowledge Base articles.',
        ],
        'ticket_templates' => [
            'label' => 'Ticket Templates',
            'icon' => 'fa-life-ring',
            'description' => 'Onboarding, offboarding, deployments, maintenance, incident response and client requests - each with its tasks, what it asks for and how it sets the ticket up, and a request form for the client portal on the ones clients raise.',
            'update' => [
                'button' => 'update_starter_ticket_templates',
                'confirm' => 'Bring the %d starter ticket templates you have up to date? Their subject, details, setup, tasks, fill-ins and request form are replaced with this version - changes you made to them are lost. Their tickets, who they go to and whether their request form is on stay as they are.',
            ],
        ],
        'project_templates' => [
            'label' => 'Project Templates',
            'icon' => 'fa-project-diagram',
            'description' => 'Common MSP projects, built out of the ticket templates above.',
            'requires' => 'ticket_templates',
        ],
        'canned_responses' => [
            'label' => 'Canned Responses',
            'icon' => 'fa-comment-dots',
            'description' => 'Replies agents put into a ticket in one go - received, waiting on you, resolved, password reset done, phishing reported and more - filled in from the ticket, setting the reply up, and offered on the ticket categories they are for.',
            'update' => [
                'button' => 'update_starter_canned_responses',
                'confirm' => 'Bring the %d starter canned responses you have up to date? What they say, where they are offered and what they set up are replaced with this version - changes you made to them are lost. How often they have been used stays.',
            ],
        ],
        'kb_articles' => [
            'label' => 'Knowledge Base Articles',
            'icon' => 'fa-book',
            'description' => "How-tos and fixes clients ask for again and again - resetting a password, setting up MFA, reporting phishing, Outlook, OneDrive, the VPN, printers, Wi-Fi - each filed under its Knowledge Base category with its tags, and reviewed once a year. They come in as drafts only your team can see, ending with how to reach you from Company Details: read each, make it say what your clients use, then publish it to the clients it suits.",
            'requires' => 'categories',
            'update' => [
                'button' => 'update_starter_kb_articles',
                'confirm' => 'Bring the %d starter Knowledge Base articles you have up to date? Their summary, what they say, category and review period are replaced with this version - what they said before is kept as a version, so changes you made can be put back. Whether they are published, who can read them, their tags, reads and feedback stay as they are.',
            ],
        ],
        'vendor_templates' => [
            'label' => 'Vendor Templates',
            'icon' => 'fa-building',
            'description' => "Vendors most clients end up with - each with its support site, status page and the portal cases are opened in, and what their support asks for. Phone numbers, hours and SLAs differ by region and contract, and account numbers and PINs by account, so they are yours to add.",
            'update' => [
                'button' => 'update_starter_vendor_templates',
                'confirm' => 'Bring the %d starter vendor templates you have up to date? Their description, support site, status page, portal and notes are replaced with this version - changes you made to those are lost, and the vendors made from them that still said what they did change with them. Their support line, hours, SLA, email, account details and who should have them stay as they are.',
            ],
        ],
        'document_templates' => [
            'label' => 'Document Templates',
            'icon' => 'fa-file-alt',
            'description' => "Runbooks, build sheets, plans and checklists - filled in from the client as each document is made, with its networks, servers, licenses and contacts as tables, asking for the rest, and the ones that go stale reviewed on a schedule. Who should have each is yours to set on its page.",
            'update' => [
                'button' => 'update_starter_document_templates',
                'confirm' => 'Bring the %d starter document templates you have up to date? Their summary, what they say, their fill-ins and how often their documents are reviewed are replaced with this version - changes you made to them are lost. The documents made from them, and who should have one, stay as they are.',
            ],
        ],
        'products' => [
            'label' => 'Products & Services',
            'icon' => 'fa-cubes',
            'description' => 'Recurring services, labor rates, project work and hardware lines. Prices are starting points - hardware and resold SKUs come in at zero.',
            'requires' => 'categories',
        ],
        'license_templates' => [
            'label' => 'License Templates',
            'icon' => 'fa-cube',
            'description' => "The licenses an MSP sells and looks after - Microsoft 365, Google Workspace, endpoint protection, email security, backup, password management and the desktop apps clients run - each with its type, how it is licensed, how often it renews and what to know about it, its vendor, and the product it is sold as once Products & Services is here. Who should have each is yours to set on its page.",
            'update' => [
                'button' => 'update_starter_license_templates',
                'confirm' => 'Bring the %d starter license templates you have up to date? Their summary, type, license type, seats, renewal term, vendor and notes are replaced with this version - changes you made to them are lost. Their licenses and who should have them stay as they are.',
            ],
        ],
    ];
}

// ------------------------------
// starterInsert
// Builds an "INSERT INTO <table> SET col = val" from a column => value map.
// Integers pass through unquoted, everything else is escaped and quoted.
// Columns named in $html_columns hold rich text and are escaped without
// strip_tags, matching how the post handlers store TinyMCE content.
// Returns the new row ID.
// ------------------------------
function starterInsert($mysqli, $table, $fields, $html_columns = []) {
    $set = [];
    foreach ($fields as $column => $value) {
        if (is_int($value)) {
            $set[] = "$column = $value";
        } elseif (in_array($column, $html_columns)) {
            $value = mysqli_real_escape_string($mysqli, $value);
            $set[] = "$column = '$value'";
        } else {
            $value = escapeSql($value);
            $set[] = "$column = '$value'";
        }
    }
    $set = implode(', ', $set);
    mysqli_query($mysqli, "INSERT INTO $table SET $set");
    return intval(mysqli_insert_id($mysqli));
}

// ------------------------------
// starterExistingNames
// One query per pack rather than one per row. Keys are lower cased so a pack
// does not re-add a name that differs only in case.
// ------------------------------
function starterExistingNames($mysqli, $table, $name_column, $key_column = null) {
    $existing = [];
    $columns = $key_column ? "$name_column, $key_column" : $name_column;
    $sql = mysqli_query($mysqli, "SELECT $columns FROM $table");
    while ($row = mysqli_fetch_assoc($sql)) {
        $key = mb_strtolower($row[$name_column]);
        if ($key_column) {
            $key = $row[$key_column] . '|' . $key;
        }
        $existing[$key] = true;
    }
    return $existing;
}

// ------------------------------
// starterRenamed / starterExistingRenamed
// Starter items whose name has changed - to US spelling, in 26.12 - as the key
// starterExistingNames() gives the old name => the name now. An install that
// loaded one under its old name has it: it isn't added again under the new
// one, and bringing the pack up to date renames it.
// ------------------------------
function starterRenamed($pack) {
    $renamed = [
        'tags' => ['6|authorised contacts only' => 'Authorized Contacts Only'],
        'canned_responses' => ['licence assigned' => 'License assigned'],
    ];
    return $renamed[$pack] ?? [];
}

function starterExistingRenamed($pack, array $existing) {
    foreach (starterRenamed($pack) as $old => $new) {
        if (isset($existing[$old])) {
            $type = strpos($old, '|') !== false ? substr($old, 0, strpos($old, '|') + 1) : '';
            $existing[$type . mb_strtolower($new)] = true;
        }
    }
    return $existing;
}

// ------------------------------
// starterCannedResponseNames
// The shared canned responses here, by lower cased name - and the new name of
// a starter one kept under its old one (starterRenamed()).
// ------------------------------
function starterCannedResponseNames($mysqli) {
    $existing = [];
    $sql = mysqli_query($mysqli, "SELECT canned_response_name FROM canned_responses WHERE canned_response_user_id = 0");
    while ($row = mysqli_fetch_row($sql)) {
        $existing[mb_strtolower($row[0])] = true;
    }
    return starterExistingRenamed('canned_responses', $existing);
}

// ------------------------------
// starterCategoryId
// ------------------------------
function starterCategoryId($mysqli, $name, $type) {
    $name = escapeSql($name);
    $type = escapeSql($type);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_name = '$name' AND category_type = '$type' LIMIT 1"));
    return intval($row['category_id'] ?? 0);
}

// ------------------------------
// starterTicketTemplateId
// ------------------------------
function starterTicketTemplateId($mysqli, $name) {
    $name = escapeSql($name);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_template_id FROM ticket_templates WHERE ticket_template_name = '$name' LIMIT 1"));
    return intval($row['ticket_template_id'] ?? 0);
}

// ------------------------------
// starterContentLoad
// Single entry point. $dry_run counts what would be added without touching
// anything, which is what the page uses to show the missing column. $only
// limits it to some of the pack's items, by starterItemKey() - null is the
// whole pack (Get started's Pick which, admin/getting_started/content.php).
// Returns the number of top level rows added (or that would be added).
// ------------------------------
function starterContentLoad($mysqli, $pack, $dry_run = false, $only = null) {
    if ($only !== null && !$only) {
        return 0;
    }
    switch ($pack) {
        case 'categories':
            return starterLoadCategories($mysqli, $dry_run, $only);
        case 'tags':
            return starterLoadTags($mysqli, $dry_run, $only);
        case 'ticket_templates':
            return starterLoadTicketTemplates($mysqli, $dry_run, $only);
        case 'project_templates':
            return starterLoadProjectTemplates($mysqli, $dry_run, $only);
        case 'canned_responses':
            return starterLoadCannedResponses($mysqli, $dry_run, $only);
        case 'kb_articles':
            return starterLoadKbArticles($mysqli, $dry_run, $only);
        case 'vendor_templates':
            return starterLoadVendorTemplates($mysqli, $dry_run, $only);
        case 'document_templates':
            return starterLoadDocumentTemplates($mysqli, $dry_run, $only);
        case 'products':
            return starterLoadProducts($mysqli, $dry_run, $only);
        case 'license_templates':
            return starterLoadLicenseTemplates($mysqli, $dry_run, $only);
    }
    return 0;
}

// ------------------------------
// starterContentRows
// A pack's items as this library has them, in order.
// ------------------------------
function starterContentRows($pack) {
    switch ($pack) {
        case 'categories':
            return starterContentCategories();
        case 'tags':
            return starterContentTags();
        case 'ticket_templates':
            return starterContentTicketTemplates();
        case 'project_templates':
            return starterContentProjectTemplates();
        case 'canned_responses':
            return starterContentCannedResponses();
        case 'kb_articles':
            return starterContentKbArticles();
        case 'vendor_templates':
            return starterContentVendorTemplates();
        case 'document_templates':
            return starterContentDocumentTemplates();
        case 'products':
            return starterContentProducts();
        case 'license_templates':
            return starterContentLicenseTemplates();
    }
    return [];
}

// ------------------------------
// starterItemKey / starterItemName / starterItemGroup
// One item of a pack - what picks it out (a category or tag with its type,
// as two can share a name), what it is called, and what it is listed under:
// a category's type, a tag's kind, a product's category, or nothing.
// ------------------------------
function starterItemKey($pack, $row) {
    switch ($pack) {
        case 'categories':
            return $row['type'] . '|' . $row['name'];
        case 'tags':
            return $row[0] . '|' . $row[1];
        case 'products':
            return (string) $row[0];
    }
    return (string) $row['name'];
}

function starterItemName($pack, $row) {
    switch ($pack) {
        case 'tags':
            return (string) $row[1];
        case 'products':
            return (string) $row[0];
    }
    return (string) $row['name'];
}

function starterItemGroup($pack, $row) {
    switch ($pack) {
        case 'categories':
            return (string) $row['type'];
        case 'tags':
            return function_exists('tagEntityByType') ? (string) (tagEntityByType($row[0])['title'] ?? '') : '';
        case 'products':
            return (string) $row[4];
        case 'kb_articles':
            return (string) $row['category'];
    }
    return '';
}

// ------------------------------
// starterOnly
// The rows of a pack that $only names (starterItemKey()) - all of them when
// $only is null.
// ------------------------------
function starterOnly($pack, array $rows, $only) {
    if ($only === null) {
        return $rows;
    }
    $only = array_flip(array_map('strval', (array) $only));
    return array_values(array_filter($rows, function ($row) use ($pack, $only) {
        return isset($only[starterItemKey($pack, $row)]);
    }));
}

// ------------------------------
// starterContentItems
// What loading a pack would add - its items not here yet, each as key, name,
// group and the library's row - for picking which. Here is as each loader
// judges it: the same name (and type, for a category or tag), whatever its
// case.
// ------------------------------
function starterContentItems($mysqli, $pack) {
    switch ($pack) {
        case 'categories':
            $existing = starterExistingNames($mysqli, 'categories', 'category_name', 'category_type');
            $here = function ($row) use ($existing) {
                return isset($existing[$row['type'] . '|' . mb_strtolower($row['name'])]);
            };
            break;
        case 'tags':
            $existing = starterExistingRenamed('tags', starterExistingNames($mysqli, 'tags', 'tag_name', 'tag_type'));
            $here = function ($row) use ($existing) {
                return isset($existing[$row[0] . '|' . mb_strtolower($row[1])]);
            };
            break;
        case 'canned_responses':
            $existing = starterCannedResponseNames($mysqli);
            break;
        default:
            $tables = ['ticket_templates' => ['ticket_templates', 'ticket_template_name'], 'project_templates' => ['project_templates', 'project_template_name'],
                'vendor_templates' => ['vendor_templates', 'vendor_template_name'], 'document_templates' => ['document_templates', 'document_template_name'],
                'products' => ['products', 'product_name'], 'license_templates' => ['software_templates', 'software_template_name'],
                'kb_articles' => ['kb_articles', 'kb_article_title']];
            if (!isset($tables[$pack])) {
                return [];
            }
            $existing = starterExistingNames($mysqli, $tables[$pack][0], $tables[$pack][1]);
    }
    $here = $here ?? function ($row) use ($pack, $existing) {
        return isset($existing[mb_strtolower(starterItemName($pack, $row))]);
    };
    $items = [];
    foreach (starterContentRows($pack) as $row) {
        if (!$here($row)) {
            $items[] = ['key' => starterItemKey($pack, $row), 'name' => starterItemName($pack, $row), 'group' => starterItemGroup($pack, $row), 'row' => $row];
        }
    }
    return $items;
}

// ------------------------------
// starterPackRecords
// Where a pack's items land - the table, its id, name and archived columns,
// what narrows it to the pack's kind of record, and what a library row is
// matched on: kind and name, as Add finds them, and a product's code too, as
// an install may well have a line of its own by that name.
// ------------------------------
function starterPackRecords($pack) {
    $packs = [
        'categories' => ['table' => 'categories', 'id' => 'category_id', 'name' => 'category_name', 'archived' => 'category_archived_at', 'kind' => 'category_type'],
        'tags' => ['table' => 'tags', 'id' => 'tag_id', 'name' => 'tag_name', 'archived' => 'tag_archived_at', 'kind' => 'tag_type'],
        'ticket_templates' => ['table' => 'ticket_templates', 'id' => 'ticket_template_id', 'name' => 'ticket_template_name', 'archived' => 'ticket_template_archived_at'],
        'project_templates' => ['table' => 'project_templates', 'id' => 'project_template_id', 'name' => 'project_template_name', 'archived' => 'project_template_archived_at'],
        'canned_responses' => ['table' => 'canned_responses', 'id' => 'canned_response_id', 'name' => 'canned_response_name', 'archived' => 'canned_response_archived_at', 'where' => 'canned_response_user_id = 0'],
        'vendor_templates' => ['table' => 'vendor_templates', 'id' => 'vendor_template_id', 'name' => 'vendor_template_name', 'archived' => 'vendor_template_archived_at'],
        'document_templates' => ['table' => 'document_templates', 'id' => 'document_template_id', 'name' => 'document_template_name', 'archived' => 'document_template_archived_at'],
        'products' => ['table' => 'products', 'id' => 'product_id', 'name' => 'product_name', 'archived' => 'product_archived_at', 'kind' => 'product_code'],
        'license_templates' => ['table' => 'software_templates', 'id' => 'software_template_id', 'name' => 'software_template_name', 'archived' => 'software_template_archived_at'],
        'kb_articles' => ['table' => 'kb_articles', 'id' => 'kb_article_id', 'name' => 'kb_article_title', 'archived' => 'kb_article_archived_at'],
    ];
    return $packs[$pack] ?? null;
}

/* A library row as starterPackHere() keys what is here - kind (a product's code)|name, lower cased. */
function starterPackMatchKey($pack, $row) {
    switch ($pack) {
        case 'categories':
            return mb_strtolower($row['type'] . '|' . $row['name']);
        case 'tags':
            return mb_strtolower($row[0] . '|' . $row[1]);
        case 'products':
            return mb_strtolower($row[2] . '|' . $row[0]);
    }
    return mb_strtolower(starterItemName($pack, $row));
}

// ------------------------------
// starterPackHere
// The records here, not archived, that a pack's rows match - key as
// starterPackMatchKey() => record id. One kept under the name it had
// (starterRenamed()) is the same item.
// ------------------------------
function starterPackHere($mysqli, $pack) {
    $r = starterPackRecords($pack);
    if (!$r) {
        return [];
    }
    $kind = $r['kind'] ?? '';
    $sql = mysqli_query($mysqli, "SELECT {$r['id']}, {$r['name']}" . ($kind ? ", $kind" : '') . " FROM {$r['table']}
        WHERE {$r['archived']} IS NULL" . (isset($r['where']) ? " AND {$r['where']}" : '') . " ORDER BY {$r['id']}");
    $here = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $key = mb_strtolower(($kind ? $row[$kind] . '|' : '') . $row[$r['name']]);
        $here[$key] = $here[$key] ?? intval($row[$r['id']]);
    }
    foreach (starterRenamed($pack) as $old => $new) {
        $type = strpos($old, '|') !== false ? substr($old, 0, strpos($old, '|') + 1) : '';
        $new_key = mb_strtolower($type . $new);
        if (!isset($here[$new_key]) && isset($here[$old])) {
            $here[$new_key] = $here[$old];
        }
    }
    return $here;
}

// ------------------------------
// starterPackUsed
// Of these record ids, the ones something uses - id => true. Used is
// anything that points at it: a category on a ticket, invoice, expense,
// product, budget or template, or with categories under it; a tag on any
// record, template or seat count; a ticket template a ticket, recurring
// ticket or project template was made from or uses, or with its request form
// on; a project template a project was raised from (its tickets' history says
// so); a canned response ever put in a reply; a vendor, document or license
// template something was made from; a product on a line, an asset, an order
// or a license template, or with stock or prices of its own.
// ------------------------------
function starterPackUsed($mysqli, $pack, array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $in = implode(',', $ids);
    $used = [];
    $queries = [];
    switch ($pack) {
        case 'categories':
            // What Admin > Categories counts for each kind, and what it doesn't
            foreach (['Expense', 'Income', 'Referral', 'Ticket', 'kb'] as $kind) {
                foreach (array_keys(categoryUsage($kind)) as $category_id) {
                    $used[intval($category_id)] = true;
                }
            }
            $queries = [
                "SELECT ticket_template_category_id FROM ticket_templates WHERE ticket_template_category_id IN ($in)",
                "SELECT config_quick_ticket_category FROM settings WHERE config_quick_ticket_category IN ($in)",
                "SELECT rmm_provider_alert_category_id FROM rmm_providers WHERE rmm_provider_alert_category_id IN ($in)",
                "SELECT ai_triage_category_id FROM ai_ticket_triage WHERE ai_triage_category_id IN ($in)",
                "SELECT category_parent FROM categories WHERE category_parent IN ($in) AND category_archived_at IS NULL",
            ];
            break;
        case 'tags':
            // Every table that links a tag to something
            $sql = mysqli_query($mysqli, "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME <> 'tags' AND (COLUMN_NAME = 'tag_id' OR COLUMN_NAME LIKE '%\\_tag\\_id')");
            while ($row = mysqli_fetch_row($sql)) {
                $queries[] = "SELECT `{$row[1]}` FROM `{$row[0]}` WHERE `{$row[1]}` IN ($in)";
            }
            foreach (array_keys(tagSeatLineCounts()) as $tag_id) {
                $used[intval($tag_id)] = true;
            }
            break;
        case 'ticket_templates':
            $queries = [
                "SELECT ticket_template_id FROM tickets WHERE ticket_template_id IN ($in)",
                "SELECT recurring_ticket_ticket_template_id FROM recurring_tickets WHERE recurring_ticket_ticket_template_id IN ($in)",
                "SELECT ticket_template_id FROM project_template_ticket_templates WHERE ticket_template_id IN ($in)",
                "SELECT ticket_template_id FROM ticket_templates WHERE ticket_template_portal = 1 AND ticket_template_id IN ($in)",
            ];
            break;
        case 'project_templates':
            // A project doesn't keep its template - the tickets it raised say which in their history
            $names = [];
            $sql = mysqli_query($mysqli, "SELECT project_template_id, project_template_name FROM project_templates WHERE project_template_id IN ($in)");
            while ($row = mysqli_fetch_row($sql)) {
                $names[intval($row[0])] = $row[1];
            }
            foreach ($names as $project_template_id => $name) {
                $like = escapeSql(addcslashes("raised the ticket from project template $name", '%_'));
                if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM ticket_history WHERE ticket_history_description LIKE '%$like' LIMIT 1"))) {
                    $used[$project_template_id] = true;
                }
            }
            break;
        case 'canned_responses':
            $queries = ["SELECT canned_response_use_canned_response_id FROM canned_response_uses WHERE canned_response_use_canned_response_id IN ($in)"];
            break;
        case 'vendor_templates':
            $queries = [
                "SELECT vendor_template_id FROM vendors WHERE vendor_template_id IN ($in)",
                "SELECT software_template_vendor_template_id FROM software_templates WHERE software_template_vendor_template_id IN ($in) AND software_template_archived_at IS NULL",
            ];
            break;
        case 'document_templates':
            $queries = ["SELECT document_template_id FROM documents WHERE document_template_id IN ($in)"];
            break;
        case 'products':
            $queries = [
                "SELECT item_product_id FROM invoice_items WHERE item_product_id IN ($in)",
                "SELECT item_product_id FROM quote_items WHERE item_product_id IN ($in)",
                "SELECT item_product_id FROM recurring_invoice_items WHERE item_product_id IN ($in)",
                "SELECT asset_product_id FROM assets WHERE asset_product_id IN ($in)",
                "SELECT order_item_product_id FROM order_items WHERE order_item_product_id IN ($in)",
                "SELECT po_item_product_id FROM po_items WHERE po_item_product_id IN ($in)",
                "SELECT stock_product_id FROM product_stock WHERE stock_product_id IN ($in)",
                "SELECT price_product_id FROM product_prices WHERE price_product_id IN ($in)",
                "SELECT software_template_product_id FROM software_templates WHERE software_template_product_id IN ($in) AND software_template_archived_at IS NULL",
            ];
            break;
        case 'license_templates':
            $queries = ["SELECT software_template_id FROM software WHERE software_template_id IN ($in)"];
            break;
        case 'kb_articles':
            // Published, read, reviewed, reworded (it has versions) or answered - anything more than the draft it came in as
            $queries = [
                "SELECT kb_article_id FROM kb_articles WHERE kb_article_id IN ($in) AND (kb_article_published_at IS NOT NULL OR kb_article_views > 0 OR kb_article_reviewed_at IS NOT NULL)",
                "SELECT kb_article_version_kb_article_id FROM kb_article_versions WHERE kb_article_version_kb_article_id IN ($in)",
                "SELECT kb_article_feedback_kb_article_id FROM kb_article_feedback WHERE kb_article_feedback_kb_article_id IN ($in)",
            ];
            break;
    }
    foreach ($queries as $query) {
        $sql = mysqli_query($mysqli, str_replace('SELECT ', 'SELECT DISTINCT ', $query));
        while ($sql && ($row = mysqli_fetch_row($sql))) {
            $used[intval($row[0])] = true;
        }
    }
    return array_intersect_key($used, array_flip($ids));
}

// ------------------------------
// starterContentUnused
// A pack's items that are here and nothing uses - for Clear out unused, in
// starterContentItems()' shape with the record's 'id' - and how many of the
// pack that are here are in use: ['items' => [...], 'in_use' => n].
// ------------------------------
function starterContentUnused($mysqli, $pack) {
    $here = starterPackHere($mysqli, $pack);
    $found = [];
    foreach (starterContentRows($pack) as $row) {
        $id = $here[starterPackMatchKey($pack, $row)] ?? 0;
        if ($id && !isset($found[$id])) {
            $found[$id] = $row;
        }
    }
    $used = starterPackUsed($mysqli, $pack, array_keys($found));
    $items = [];
    foreach ($found as $id => $row) {
        if (!isset($used[$id])) {
            $items[] = ['key' => starterItemKey($pack, $row), 'name' => starterItemName($pack, $row), 'group' => starterItemGroup($pack, $row), 'row' => $row, 'id' => $id];
        }
    }
    return ['items' => $items, 'in_use' => count($found) - count($items)];
}

// ------------------------------
// starterClearUnused
// Archives a pack's unused items - those of $only (starterItemKey()s) when
// given - checked again as it goes. Archived, not deleted: each comes back
// from its own list's Archived view. Add still counts an archived one as
// here, as it does any other, so it isn't made again beside it.
// Returns [record ids archived, undoSnapshot() of them before].
// ------------------------------
function starterClearUnused($mysqli, $pack, $only = null) {
    $r = starterPackRecords($pack);
    if (!$r) {
        return [[], null];
    }
    $ids = [];
    foreach (starterContentUnused($mysqli, $pack)['items'] as $item) {
        if ($only === null || in_array($item['key'], $only, true)) {
            $ids[] = intval($item['id']);
        }
    }
    if (!$ids) {
        return [[], null];
    }
    $before = undoSnapshot($r['table'], $r['id'], $ids, [$r['archived']]);
    mysqli_query($mysqli, "UPDATE {$r['table']} SET {$r['archived']} = NOW() WHERE {$r['archived']} IS NULL AND {$r['id']} IN (" . implode(',', $ids) . ")");
    return [$ids, $before];
}

// ------------------------------
// starterContentStatus
// Per pack totals for the page - how many the library holds, how many are
// already present, and how many loading would add.
// ------------------------------
function starterContentStatus($mysqli) {
    $status = [];
    foreach (starterContentPacks() as $pack => $details) {
        $total = starterContentTotal($pack);
        $missing = starterContentLoad($mysqli, $pack, true);
        $status[$pack] = [
            'total' => $total,
            'missing' => $missing,
            'present' => $total - $missing,
        ];
    }
    return $status;
}

// ------------------------------
// starterContentTotal
// ------------------------------
function starterContentTotal($pack) {
    switch ($pack) {
        case 'categories':
            return count(starterContentCategories());
        case 'tags':
            return count(starterContentTags());
        case 'ticket_templates':
            return count(starterContentTicketTemplates());
        case 'project_templates':
            return count(starterContentProjectTemplates());
        case 'canned_responses':
            return count(starterContentCannedResponses());
        case 'kb_articles':
            return count(starterContentKbArticles());
        case 'vendor_templates':
            return count(starterContentVendorTemplates());
        case 'document_templates':
            return count(starterContentDocumentTemplates());
        case 'products':
            return count(starterContentProducts());
        case 'license_templates':
            return count(starterContentLicenseTemplates());
    }
    return 0;
}

// ------------------------------
// starterLoadCategories
// ------------------------------
function starterLoadCategories($mysqli, $dry_run = false, $only = null) {
    $existing = starterExistingNames($mysqli, 'categories', 'category_name', 'category_type');
    $added = 0;

    foreach (starterOnly('categories', starterContentCategories(), $only) as $category) {
        if (isset($existing[$category['type'] . '|' . mb_strtolower($category['name'])])) {
            continue;
        }
        $added++;
        if ($dry_run) {
            continue;
        }
        $fields = [
            'category_name' => $category['name'],
            'category_type' => $category['type'],
            'category_color' => $category['color'],
            'category_description' => $category['description'],
        ];
        if ($category['icon']) {
            $fields['category_icon'] = $category['icon'];
        }
        if ($category['order']) {
            $fields['category_order'] = $category['order'];
        }
        starterInsert($mysqli, 'categories', $fields);
    }

    return $added;
}

// ------------------------------
// starterLoadTags
// ------------------------------
function starterLoadTags($mysqli, $dry_run = false, $only = null) {
    $existing = starterExistingRenamed('tags', starterExistingNames($mysqli, 'tags', 'tag_name', 'tag_type'));
    $added = 0;

    foreach (starterOnly('tags', starterContentTags(), $only) as $tag) {
        if (isset($existing[$tag[0] . '|' . mb_strtolower($tag[1])])) {
            continue;
        }
        $added++;
        if ($dry_run) {
            continue;
        }
        starterInsert($mysqli, 'tags', [
            'tag_type' => $tag[0],
            'tag_name' => $tag[1],
            'tag_color' => $tag[2],
            'tag_icon' => $tag[3],
        ]);
    }

    return $added;
}

// ------------------------------
// starterLoadTicketTemplates
// A template is added whole or not at all - an existing name is left alone
// rather than having its task list topped up.
// ------------------------------
function starterLoadTicketTemplates($mysqli, $dry_run = false, $only = null) {
    $templates = ticketTemplatesImportRead(['format' => TICKET_TEMPLATE_FILE_FORMAT, 'version' => TICKET_TEMPLATE_FILE_VERSION, 'templates' => starterOnly('ticket_templates', starterContentTicketTemplates(), $only)]);
    if ($dry_run) {
        $existing = starterExistingNames($mysqli, 'ticket_templates', 'ticket_template_name');
        return count(array_filter($templates, function ($template) use ($existing) {
            return !isset($existing[mb_strtolower($template['name'])]);
        }));
    }
    return ticketTemplatesImport($templates, 'skip')['added'];
}

// ------------------------------
// starterUpdateTicketTemplates
// The starter templates already here, brought up to this library's version -
// their subject, details, setup, tasks, fill-ins and request form replaced.
// Their tickets, who they go to and whether their form is on stay. Returns
// the import's counts (ticketTemplatesImport()).
// ------------------------------
function starterUpdateTicketTemplates() {
    $templates = ticketTemplatesImportRead(['format' => TICKET_TEMPLATE_FILE_FORMAT, 'version' => TICKET_TEMPLATE_FILE_VERSION, 'templates' => starterContentTicketTemplates()]);
    return ticketTemplatesImport($templates, 'replace', true);
}

// ------------------------------
// starterLoadCannedResponses
// Through the canned response import, as shared responses. One whose name a
// shared one here already has is left alone.
// ------------------------------
function starterLoadCannedResponses($mysqli, $dry_run = false, $only = null) {
    $responses = cannedResponsesImportRead(['format' => CANNED_RESPONSE_FILE_FORMAT, 'version' => CANNED_RESPONSE_FILE_VERSION, 'responses' => starterOnly('canned_responses', starterContentCannedResponses(), $only)]);
    // Ones here already, under their name or the one they had (starterRenamed()), are left alone
    $existing = starterCannedResponseNames($mysqli);
    $responses = array_values(array_filter($responses, function ($response) use ($existing) {
        return !isset($existing[mb_strtolower($response['name'])]);
    }));
    if ($dry_run) {
        return count($responses);
    }
    return $responses ? cannedResponsesImport($responses, 'skip')['added'] : 0;
}

// ------------------------------
// starterUpdateCannedResponses
// The starter canned responses already here, brought up to this library's
// version - what they say, where they are offered and what they set up
// replaced. Their record of use, files and whether they are archived stay.
// Returns the import's counts (cannedResponsesImport()).
// ------------------------------
function starterUpdateCannedResponses() {
    global $mysqli;

    // One still under the name it had takes its new name first, so it is the one brought up to date
    foreach (starterRenamed('canned_responses') as $old => $new) {
        $new_sql = escapeSql(mb_strtolower($new));
        if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM canned_responses WHERE canned_response_user_id = 0 AND LOWER(canned_response_name) = '$new_sql' LIMIT 1"))) {
            mysqli_query($mysqli, "UPDATE canned_responses SET canned_response_name = '" . escapeSql($new) . "'
                WHERE canned_response_user_id = 0 AND LOWER(canned_response_name) = '" . escapeSql($old) . "' ORDER BY canned_response_id LIMIT 1");
        }
    }

    $responses = cannedResponsesImportRead(['format' => CANNED_RESPONSE_FILE_FORMAT, 'version' => CANNED_RESPONSE_FILE_VERSION, 'responses' => starterContentCannedResponses()]);
    return cannedResponsesImport($responses, 'replace', true);
}

// ------------------------------
// starterLoadProjectTemplates
// Links are resolved by ticket template name - one that is not present is
// skipped, so loading this pack before the ticket templates still works and
// simply produces a project template with fewer stages.
// ------------------------------
function starterLoadProjectTemplates($mysqli, $dry_run = false, $only = null) {
    $existing = starterExistingNames($mysqli, 'project_templates', 'project_template_name');
    $added = 0;

    foreach (starterOnly('project_templates', starterContentProjectTemplates(), $only) as $project_template) {
        if (isset($existing[mb_strtolower($project_template['name'])])) {
            continue;
        }
        $added++;
        if ($dry_run) {
            continue;
        }

        $project_template_id = starterInsert($mysqli, 'project_templates', [
            'project_template_name' => $project_template['name'],
            'project_template_description' => $project_template['description'],
        ]);

        $order = 1;
        foreach ($project_template['ticket_templates'] as $ticket_template_name) {
            $ticket_template_id = starterTicketTemplateId($mysqli, $ticket_template_name);
            if ($ticket_template_id) {
                starterInsert($mysqli, 'project_template_ticket_templates', [
                    'project_template_id' => $project_template_id,
                    'ticket_template_id' => $ticket_template_id,
                    'ticket_template_order' => $order,
                ]);
                $order++;
            }
        }
    }

    return $added;
}

// ------------------------------
// starterLoadVendorTemplates
// Through the vendor template import. One whose name a template here already
// has, archived or not, is left alone.
// ------------------------------
function starterLoadVendorTemplates($mysqli, $dry_run = false, $only = null) {
    $templates = vendorTemplatesImportRead(['format' => VENDOR_TEMPLATE_FILE_FORMAT, 'version' => VENDOR_TEMPLATE_FILE_VERSION, 'templates' => starterOnly('vendor_templates', starterContentVendorTemplates(), $only)]);
    if ($dry_run) {
        $existing = starterExistingNames($mysqli, 'vendor_templates', 'vendor_template_name');
        return count(array_filter($templates, function ($template) use ($existing) {
            return !isset($existing[mb_strtolower($template['name'])]);
        }));
    }
    return vendorTemplatesImport($templates, 'skip')['added'];
}

// ------------------------------
// starterUpdateVendorTemplates
// The starter vendor templates already here, brought up to this library's
// version - only what this library says: what they are, their support site,
// status page, portal and notes. Their support line, hours, SLA, email,
// account details and who should have them stay; the vendors made from them
// that still said what they did change with them (vendorTemplateFollow()).
// Returns the import's counts (vendorTemplatesImport()).
// ------------------------------
function starterUpdateVendorTemplates() {
    $templates = vendorTemplatesImportRead(['format' => VENDOR_TEMPLATE_FILE_FORMAT, 'version' => VENDOR_TEMPLATE_FILE_VERSION, 'templates' => starterContentVendorTemplates()]);
    return vendorTemplatesImport($templates, 'replace', true, ['description', 'website', 'status_page', 'portal', 'notes']);
}

// ------------------------------
// starterLoadDocumentTemplates
// Through the document template import. One whose name a template here
// already has, archived or not, is left alone.
// ------------------------------
function starterLoadDocumentTemplates($mysqli, $dry_run = false, $only = null) {
    $templates = documentTemplatesImportRead(['format' => DOCUMENT_TEMPLATE_FILE_FORMAT, 'version' => DOCUMENT_TEMPLATE_FILE_VERSION, 'templates' => starterOnly('document_templates', starterContentDocumentTemplates(), $only)]);
    if ($dry_run) {
        $existing = starterExistingNames($mysqli, 'document_templates', 'document_template_name');
        return count(array_filter($templates, function ($template) use ($existing) {
            return !isset($existing[mb_strtolower($template['name'])]);
        }));
    }
    return documentTemplatesImport($templates, 'skip')['added'];
}

// ------------------------------
// starterUpdateDocumentTemplates
// The starter document templates already here, brought up to this library's
// version - their summary, what they say, fill-ins and review period
// replaced. The documents made from them, and who should have one, stay.
// Returns the import's counts (documentTemplatesImport()).
// ------------------------------
function starterUpdateDocumentTemplates() {
    $templates = documentTemplatesImportRead(['format' => DOCUMENT_TEMPLATE_FILE_FORMAT, 'version' => DOCUMENT_TEMPLATE_FILE_VERSION, 'templates' => starterContentDocumentTemplates()]);
    return documentTemplatesImport($templates, 'replace', true);
}

// ------------------------------
// starterLoadLicenseTemplates
// Through the license template import. One whose name a template here
// already has, archived or not, is left alone. Its vendor is the vendor
// template of that name - added from here when there is none - and it is
// sold as the product of that name when there is one.
// ------------------------------
function starterLoadLicenseTemplates($mysqli, $dry_run = false, $only = null) {
    $templates = softwareTemplatesImportRead(['format' => SOFTWARE_TEMPLATE_FILE_FORMAT, 'version' => SOFTWARE_TEMPLATE_FILE_VERSION, 'templates' => starterOnly('license_templates', starterContentLicenseTemplates(), $only)]);
    if ($dry_run) {
        $existing = starterExistingNames($mysqli, 'software_templates', 'software_template_name');
        return count(array_filter($templates, function ($template) use ($existing) {
            return !isset($existing[mb_strtolower($template['name'])]);
        }));
    }
    return softwareTemplatesImport($templates, 'skip')['added'];
}

// ------------------------------
// starterUpdateLicenseTemplates
// The starter license templates already here, brought up to this library's
// version - their summary, type, license type, seats, renewal term, vendor
// and notes replaced, and the product they are sold as when it is here. Their
// licenses, and who should have them, stay.
// Returns the import's counts (softwareTemplatesImport()).
// ------------------------------
function starterUpdateLicenseTemplates() {
    $templates = softwareTemplatesImportRead(['format' => SOFTWARE_TEMPLATE_FILE_FORMAT, 'version' => SOFTWARE_TEMPLATE_FILE_VERSION, 'templates' => starterContentLicenseTemplates()]);
    return softwareTemplatesImport($templates, 'replace', true);
}

// ------------------------------
// starterLoadKbArticles
// As drafts only agents can read, each with its Knowledge Base category and
// tags - those of the Categories and Tags packs, so one that isn't here
// leaves the article without it. One whose title an article here already
// has, archived or not, is left alone. How to reach you is filled in from
// Company Details as they are now (starterKbFill()).
// ------------------------------
function starterLoadKbArticles($mysqli, $dry_run = false, $only = null) {
    $existing = starterExistingNames($mysqli, 'kb_articles', 'kb_article_title');
    $added = 0;

    foreach (starterOnly('kb_articles', starterContentKbArticles(), $only) as $article) {
        if (isset($existing[mb_strtolower($article['name'])])) {
            continue;
        }
        $added++;
        if ($dry_run) {
            continue;
        }
        $kb_article_id = kbArticleSave(starterKbFields($mysqli, $article) + ['published' => false, 'visibility' => 'agents', 'all_clients' => false, 'client_tag_ids' => [], 'client_ids' => []]);
        starterKbTags($mysqli, $kb_article_id, $article['tags']);
        $existing[mb_strtolower($article['name'])] = true;
    }

    return $added;
}

// ------------------------------
// starterUpdateKbArticles
// The starter articles here, not archived, brought up to this library's
// version - their summary, what they say, category (when it is here) and
// review period. What one said before is kept as a version, as any edit's
// is; one that already says this is left untouched. Whether they are
// published, who can read them, their reads and feedback stay, and they gain
// the library's tags they haven't got. Returns ['replaced' => n].
// ------------------------------
function starterUpdateKbArticles() {
    global $mysqli;

    $here = starterPackHere($mysqli, 'kb_articles');
    $replaced = 0;
    foreach (starterContentKbArticles() as $article) {
        $row = kbArticleRow($here[mb_strtolower($article['name'])] ?? 0);
        if (!$row) {
            continue;
        }
        $fields = starterKbFields($mysqli, $article);
        $fields['title'] = $row['kb_article_title'];
        $fields['category_id'] = $fields['category_id'] ?: intval($row['kb_article_category_id']);
        if ($fields['summary'] !== (string) $row['kb_article_summary'] || $fields['content'] !== (string) $row['kb_article_content']
            || $fields['category_id'] !== intval($row['kb_article_category_id']) || $fields['review_months'] !== intval($row['kb_article_review_months'])) {
            kbArticleSave($fields + ['published' => !empty($row['kb_article_published_at'])] + kbAudienceFields($row), $row);
        }
        starterKbTags($mysqli, $row['kb_article_id'], $article['tags']);
        $replaced++;
    }

    return ['replaced' => $replaced];
}

// ------------------------------
// starterKbFields
// A library article as kbArticleSave() takes it - its category this
// install's Knowledge Base category of that name (0 when there is none), and
// what it says filled in (starterKbFill()).
// ------------------------------
function starterKbFields($mysqli, array $article) {
    return [
        'title' => $article['name'],
        'summary' => $article['description'],
        'category_id' => starterCategoryId($mysqli, $article['category'], 'kb'),
        'review_months' => intval($article['review_months']),
        'content' => starterKbFill($article['content']),
    ];
}

// ------------------------------
// starterKbTags
// Puts the Knowledge Base article tags of these names on an article - the
// ones here and not archived, and not on it already.
// ------------------------------
function starterKbTags($mysqli, $kb_article_id, array $names) {
    $kb_article_id = intval($kb_article_id);
    if (!$kb_article_id || !$names) {
        return;
    }
    $names_sql = implode(', ', array_map(function ($name) {
        return "'" . escapeSql(mb_strtolower($name)) . "'";
    }, $names));
    $sql = mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_type = 13 AND tag_archived_at IS NULL AND LOWER(tag_name) IN ($names_sql)");
    while ($row = mysqli_fetch_row($sql)) {
        mysqli_query($mysqli, "INSERT IGNORE INTO kb_article_tags SET kb_article_id = $kb_article_id, tag_id = " . intval($row[0]));
    }
}

// ------------------------------
// starterKbFill
// A starter article's [Portal] and [Contact us] filled in from this install:
// a link to the client portal (words alone when the portal is off), and a
// closing line saying how to reach you - open a ticket in the portal, the
// address tickets are emailed from (or the company's), and the company's
// phone, from Company Details as they are now.
// ------------------------------
function starterKbFill($html) {
    global $config_base_url, $config_ticket_from_email;

    $company = billingCompany();
    $portal_on = function_exists('moduleOn') && moduleOn('portal') && !empty($config_base_url);
    $portal_url = $portal_on ? 'https://' . $config_base_url . '/client/' : '';
    $portal = $portal_on ? '<a href="' . escapeHtml($portal_url) . '">the client portal</a>' : 'the client portal';

    $ways = [];
    if ($portal_on) {
        $ways[] = 'open a ticket in ' . $portal;
    }
    $email = trim((string) ($config_ticket_from_email ?? '')) ?: trim((string) ($company['company_email'] ?? ''));
    if ($email !== '') {
        $ways[] = 'email <a href="mailto:' . escapeHtml($email) . '">' . escapeHtml($email) . '</a>';
    }
    if (($company['phone_display'] ?? '') !== '') {
        $ways[] = 'call ' . escapeHtml($company['phone_display']);
    }
    if ($ways) {
        $last = array_pop($ways);
        $contact = '<p><strong>Still stuck?</strong> ' . ucfirst($ways ? implode(', ', $ways) . ' or ' . $last : $last) . ' and we will take it from there.</p>';
    } else {
        $contact = '<p><strong>Still stuck?</strong> Get in touch with us and we will take it from there.</p>';
    }

    return str_replace(['[Portal]', '[Contact us]'], [$portal, $contact], (string) $html);
}

// ------------------------------
// starterLoadProducts
// Products are filed under income categories. A category that is not present
// leaves the product uncategorised rather than blocking the load, so this pack
// works whether or not the categories pack has been loaded.
// ------------------------------
function starterLoadProducts($mysqli, $dry_run = false, $only = null) {
    global $session_company_currency;

    $existing = starterExistingNames($mysqli, 'products', 'product_name');
    $added = 0;
    $category_ids = [];

    foreach (starterOnly('products', starterContentProducts(), $only) as $product) {
        if (isset($existing[mb_strtolower($product[0])])) {
            continue;
        }
        $added++;
        if ($dry_run) {
            continue;
        }

        if (!isset($category_ids[$product[4]])) {
            $category_ids[$product[4]] = starterCategoryId($mysqli, $product[4], 'Income');
        }

        // How it is billed by default - "month", or "month:contacts" for per user
        [$frequency, $seat] = array_pad(explode(':', (string) ($product[6] ?? '')), 2, '');
        starterInsert($mysqli, 'products', [
            'product_name' => $product[0],
            'product_type' => $product[1],
            'product_code' => $product[2],
            'product_price' => $product[3],
            'product_category_id' => $category_ids[$product[4]],
            'product_description' => $product[5],
            'product_currency_code' => $session_company_currency ?? '',
            'product_tax_id' => 0,
        ] + ($frequency !== '' ? ['product_frequency' => $frequency] : []) + ($seat !== '' ? ['product_seat_source' => $seat] : [])
          + (!empty($product[7]) ? ['product_asset_type' => $product[7], 'product_warranty_months' => intval($product[8] ?? 0)] : []));
    }

    return $added;
}

// ------------------------------
// starterContentCategories
// Flattened to one row per category so the loader and the counter share a shape.
// ------------------------------
function starterContentCategories() {

    $expense_categories = [
        ['Office Supplies', '#007bff', 'Consumables and general office purchases'],
        ['Travel', '#6f42c1', 'Airfare, lodging and out of town travel'],
        ['Advertising', '#fd7e14', 'Marketing, campaigns and sponsorships'],
        ['Processing Fee', '#6c757d', 'Card and payment gateway processing fees'],
        ['Shipping and Postage', '#20c997', 'Freight, courier and postage'],
        ['Software', '#17a2b8', 'Internal software and tooling subscriptions'],
        ['Bank Fees', '#ffc107', 'Account, wire and merchant service charges'],
        ['Payroll', '#28a745', 'Wages, payroll taxes and benefits'],
        ['Professional Services', '#001f3f', 'Legal, accounting and consulting fees'],
        ['Contractor', '#795548', 'Subcontracted labor and 1099 work'],
        ['Insurance', '#dc3545', 'General liability, errors and omissions, cyber'],
        ['Infrastructure', '#3d9970', 'Colocation, racks, power and connectivity'],
        ['Equipment', '#adb5bd', 'Internal hardware and capital equipment'],
        ['Education', '#e83e8c', 'Training, certifications and exam fees'],
        ['Hardware - Cost of Goods', '#343a40', 'Hardware bought for resale to a client'],
        ['Licensing - Cost of Goods', '#17a2b8', 'Licenses and subscriptions resold to a client'],
        ['Cloud and Hosting', '#6610f2', 'Cloud compute, storage and hosting spend'],
        ['Telecom and Internet', '#fd7e14', 'Circuits, mobile plans and phone service'],
        ['Rent and Utilities', '#795548', 'Office rent, power, water and refuse'],
        ['Vehicle and Fuel', '#d81b60', 'Fuel, maintenance and vehicle costs'],
        ['Meals', '#e83e8c', 'Business meals and client entertainment'],
        ['Tools and Test Equipment', '#6c757d', 'Hand tools, testers and shop equipment'],
        ['Dues and Subscriptions', '#007bff', 'Memberships, associations and publications'],
        ['Taxes', '#dc3545', 'Business, sales and franchise taxes'],
        ['Owner Distribution', '#28a745', 'Owner draw or distribution of profit'],
    ];

    $income_categories = [
        ['Managed Services', '#28a745', 'Recurring managed service agreements'],
        ['Consulting', '#007bff', 'Advisory, vCIO and strategy work'],
        ['Projects', '#6f42c1', 'Fixed scope project work'],
        ['Hardware Sales', '#adb5bd', 'Resale of hardware and peripherals'],
        ['Software Sales', '#17a2b8', 'Resale of software and perpetual licenses'],
        ['Cloud Services', '#6610f2', 'Hosted servers, storage and platforms'],
        ['Support', '#ffc107', 'Reactive help desk and support labor'],
        ['Training', '#e83e8c', 'End user and administrator training'],
        ['Telecom Services', '#fd7e14', 'Voice, SIP and connectivity services'],
        ['Backup', '#001f3f', 'Backup, replication and disaster recovery'],
        ['Security', '#dc3545', 'Security tooling, monitoring and response'],
        ['Licensing', '#20c997', 'Recurring per seat license billing'],
        ['Monitoring', '#3d9970', 'Remote monitoring and alerting'],
        ['Labor', '#795548', 'Hourly and after hours labor'],
        ['Web and Hosting', '#6c757d', 'Websites, domains, certificates and hosting'],
        ['Onboarding', '#d81b60', 'One off onboarding and setup fees'],
        ['Reimbursable Expenses', '#343a40', 'Pass through costs rebilled to a client'],
        ['Late Fees', '#dc3545', 'Interest and late payment charges'],
    ];

    $referral_categories = [
        ['Friend', '#007bff', 'Personal recommendation'],
        ['Search', '#fd7e14', 'Found through a search engine'],
        ['Social Media', '#28a745', 'Came from a social platform'],
        ['Email', '#ffc107', 'Responded to an email campaign'],
        ['Partner', '#6f42c1', 'Referred by a channel partner'],
        ['Event', '#dc3545', 'Met at a trade show or event'],
        ['Affiliate', '#e83e8c', 'Referred by an affiliate'],
        ['Client', '#17a2b8', 'Referred by an existing client'],
        ['Website', '#20c997', 'Enquiry submitted through the website'],
        ['Networking Group', '#001f3f', 'Referred through a networking group'],
        ['Chamber of Commerce', '#3d9970', 'Referred through a chamber or association'],
        ['Vendor', '#adb5bd', 'Referred by a vendor or distributor'],
        ['Cold Outreach', '#6c757d', 'Result of outbound prospecting'],
        ['Direct Mail', '#795548', 'Responded to a mailer'],
        ['Acquisition', '#6610f2', 'Came across with an acquired book of business'],
        ['Other', '#343a40', 'Source does not fit any standard category'],
    ];

    $ticket_categories = [
        ['Workstation', '#007bff', 'fa-desktop', 'Desktop and laptop issues'],
        ['Server', '#001f3f', 'fa-server', 'Physical and virtual server issues'],
        ['Network', '#6610f2', 'fa-network-wired', 'Switching, routing and cabling'],
        ['Firewall', '#dc3545', 'fa-shield-alt', 'Firewall, VPN and perimeter security'],
        ['Wireless', '#17a2b8', 'fa-wifi', 'Access points, coverage and roaming'],
        ['Printer', '#6c757d', 'fa-print', 'Printers, scanners and copiers'],
        ['Email', '#fd7e14', 'fa-envelope', 'Mail flow, delivery and client issues'],
        ['Microsoft 365', '#0078d4', 'fa-cloud', 'Tenant, licensing and cloud app issues'],
        ['Account and Access', '#ffc107', 'fa-user-lock', 'Passwords, MFA and permissions'],
        ['Software', '#20c997', 'fa-window-restore', 'Standard application issues'],
        ['Line of Business Application', '#6f42c1', 'fa-cubes', 'Client specific business applications'],
        ['Backup and Recovery', '#3d9970', 'fa-database', 'Backup jobs, restores and replication'],
        ['Security Incident', '#d81b60', 'fa-user-secret', 'Suspected or confirmed compromise'],
        ['Phishing Report', '#e83e8c', 'fa-fish', 'User reported suspicious email'],
        ['Phone and VoIP', '#28a745', 'fa-phone-alt', 'Handsets, extensions and call routing'],
        ['Mobile Device', '#795548', 'fa-mobile-alt', 'Phones, tablets and mobile management'],
        ['Hardware Failure', '#343a40', 'fa-tools', 'Failed hardware, RMA and replacement'],
        ['Onboarding', '#28a745', 'fa-user-plus', 'New user and new device setup'],
        ['Offboarding', '#dc3545', 'fa-user-minus', 'Departing user and device recovery'],
        ['Procurement', '#adb5bd', 'fa-shopping-cart', 'Quotes, orders and purchasing'],
        ['Website and DNS', '#17a2b8', 'fa-globe', 'Hosting, domains and certificates'],
        ['Maintenance', '#6c757d', 'fa-calendar-check', 'Scheduled and preventative maintenance'],
        ['Monitoring Alert', '#ffc107', 'fa-bell', 'Alerts raised by monitoring tooling'],
        ['Project Work', '#6f42c1', 'fa-project-diagram', 'Work carried out under a project'],
        ['Billing', '#001f3f', 'fa-file-invoice-dollar', 'Invoice and account questions'],
        ['Training', '#e83e8c', 'fa-graduation-cap', 'How to questions and user training'],
        ['Vendor Coordination', '#795548', 'fa-handshake', 'Work being driven with a third party'],
        ['Other', '#343a40', 'fa-question-circle', 'Does not fit any standard category'],
    ];

    // How Knowledge Base articles are grouped - an article's icon is its category's
    $kb_categories = [
        ['Getting Help', '#007bff', 'fa-life-ring', 'Reaching the help desk, the client portal and support hours'],
        ['Accounts and Passwords', '#ffc107', 'fa-key', 'Signing in, passwords and multi-factor authentication'],
        ['Email and Calendar', '#fd7e14', 'fa-envelope', 'Outlook, mailboxes, calendars and shared mail'],
        ['Files and Sharing', '#20c997', 'fa-folder-open', 'OneDrive, SharePoint, shared drives and sending files'],
        ['Security', '#dc3545', 'fa-shield-alt', 'Phishing, lost devices and keeping data safe'],
        ['Remote Work', '#6f42c1', 'fa-laptop-house', 'The VPN, working from home and traveling'],
        ['Printing and Scanning', '#6c757d', 'fa-print', 'Printers, scanners and copiers'],
        ['Phones and Meetings', '#28a745', 'fa-headset', 'Desk phones, voicemail and video meetings'],
        ['Devices', '#17a2b8', 'fa-laptop', 'Computers, phones and tablets, and keeping them up to date'],
        ['Network and Wi-Fi', '#6610f2', 'fa-wifi', 'The office and guest Wi-Fi, and the internet connection'],
        ['Policies', '#001f3f', 'fa-clipboard-check', 'How we work together - new starters, leavers and acceptable use'],
        ['Internal Procedures', '#343a40', 'fa-user-shield', 'How your team does things - kept to agents'],
    ];

    $categories = [];

    $simple_types = [
        'Expense' => $expense_categories,
        'Income' => $income_categories,
        'Referral' => $referral_categories,
    ];
    foreach ($simple_types as $type => $rows) {
        foreach ($rows as $row) {
            $categories[] = [
                'type' => $type,
                'name' => $row[0],
                'color' => $row[1],
                'icon' => '',
                'description' => $row[2],
                'order' => 0,
            ];
        }
    }

    foreach (['Ticket' => $ticket_categories, 'kb' => $kb_categories] as $type => $rows) {
        $order = 1;
        foreach ($rows as $row) {
            $categories[] = [
                'type' => $type,
                'name' => $row[0],
                'color' => $row[1],
                'icon' => $row[2],
                'description' => $row[3],
                'order' => $order,
            ];
            $order++;
        }
    }

    return $categories;

}

// ------------------------------
// starterContentTags
// tag_type 1 Client, 2 Location, 3 Contact, 4 Credential, 5 Asset, 6 Vendor, 7 Domain,
// 8 License, 9 Service, 10 Network, 11 Document, 12 Ticket, 13 Knowledge Base article - tagKinds()
// in functions/tags.php.
// tag_icon is stored without the fa- prefix, the views add it.
// ------------------------------
function starterContentTags() {

    $tags = [
        // Client
        [1, 'Managed', '#28a745', 'handshake'],
        [1, 'Co-Managed', '#20c997', 'people-arrows'],
        [1, 'Break Fix', '#fd7e14', 'wrench'],
        [1, 'Block Hours', '#6f42c1', 'hourglass-half'],
        [1, 'Prospect', '#17a2b8', 'binoculars'],
        [1, 'Onboarding', '#007bff', 'user-plus'],
        [1, 'Offboarding', '#dc3545', 'user-minus'],
        [1, 'Key Account', '#ffc107', 'star'],
        [1, 'At Risk', '#d81b60', 'exclamation-triangle'],
        [1, 'Past Due', '#dc3545', 'file-invoice-dollar'],
        [1, 'Multi Site', '#001f3f', 'map-marked-alt'],
        [1, 'Non Profit', '#3d9970', 'hand-holding-heart'],
        [1, 'Hosting', '#20c997', 'server'],
        [1, 'Seasonal', '#fd7e14', 'calendar-alt'],
        [1, 'After Hours Support', '#343a40', 'moon'],
        [1, 'Compliance', '#795548', 'balance-scale'],
        [1, 'Cyber Insurance', '#6610f2', 'shield-alt'],
        [1, 'Service Hold', '#6c757d', 'pause-circle'],

        // Location
        [2, 'Head Office', '#007bff', 'building'],
        [2, 'Branch', '#17a2b8', 'store'],
        [2, 'Warehouse', '#795548', 'warehouse'],
        [2, 'Retail', '#e83e8c', 'cash-register'],
        [2, 'Home Office', '#20c997', 'house-user'],
        [2, 'Data Center', '#001f3f', 'server'],
        [2, 'Server Room', '#6f42c1', 'network-wired'],
        [2, 'Restricted Access', '#dc3545', 'lock'],
        [2, 'Onsite Spares', '#6c757d', 'boxes'],

        // Contact
        [3, 'Primary', '#007bff', 'user-tie'],
        [3, 'Technical', '#17a2b8', 'laptop-code'],
        [3, 'Billing', '#28a745', 'file-invoice-dollar'],
        [3, 'Authorized Approver', '#6f42c1', 'check-double'],
        [3, 'Executive', '#001f3f', 'crown'],
        [3, 'Emergency', '#dc3545', 'phone-volume'],
        [3, 'After Hours', '#343a40', 'moon'],
        [3, 'Onsite Point of Contact', '#20c997', 'map-marker-alt'],
        [3, 'Not Authorized', '#6c757d', 'ban'],
        [3, 'Departed', '#795548', 'user-slash'],

        // Credential
        [4, 'Domain Admin', '#dc3545', 'user-shield'],
        [4, 'Local Admin', '#fd7e14', 'user-cog'],
        [4, 'Service Account', '#6c757d', 'robot'],
        [4, 'Break Glass', '#d81b60', 'fire-extinguisher'],
        [4, 'Firewall', '#001f3f', 'shield-alt'],
        [4, 'Switch', '#6610f2', 'network-wired'],
        [4, 'Wireless', '#17a2b8', 'wifi'],
        [4, 'Hypervisor', '#343a40', 'server'],
        [4, 'Backup Console', '#3d9970', 'database'],
        [4, 'Microsoft 365', '#0078d4', 'cloud'],
        [4, 'Registrar and DNS', '#20c997', 'globe'],
        [4, 'Hosting', '#795548', 'hdd'],
        [4, 'Vendor Portal', '#adb5bd', 'external-link-alt'],
        [4, 'Shared', '#ffc107', 'users'],
        [4, 'Rotate Quarterly', '#6f42c1', 'sync-alt'],

        // Asset
        [5, 'Workstation', '#007bff', 'desktop'],
        [5, 'Laptop', '#17a2b8', 'laptop'],
        [5, 'Server', '#001f3f', 'server'],
        [5, 'Hypervisor Host', '#343a40', 'layer-group'],
        [5, 'Storage', '#795548', 'hdd'],
        [5, 'Firewall', '#dc3545', 'shield-alt'],
        [5, 'Switch', '#6610f2', 'network-wired'],
        [5, 'Access Point', '#20c997', 'wifi'],
        [5, 'Printer', '#6c757d', 'print'],
        [5, 'UPS', '#ffc107', 'car-battery'],
        [5, 'VoIP Handset', '#28a745', 'phone-alt'],
        [5, 'Mobile', '#e83e8c', 'mobile-alt'],
        [5, 'Business Critical', '#d81b60', 'exclamation-circle'],
        [5, 'Monitored', '#3d9970', 'heartbeat'],
        [5, 'Backed Up', '#001f3f', 'database'],
        [5, 'Endpoint Protection', '#6f42c1', 'user-shield'],
        [5, 'Under Warranty', '#28a745', 'certificate'],
        [5, 'Warranty Expired', '#fd7e14', 'calendar-times'],
        [5, 'End of Life', '#adb5bd', 'skull-crossbones'],
        [5, 'Leased', '#6c757d', 'file-contract'],
        [5, 'Spare Stock', '#795548', 'boxes'],
        [5, 'Patch Excluded', '#ffc107', 'ban'],

        // Vendor
        [6, 'ISP', '#007bff', 'wifi'],
        [6, 'Telecom', '#28a745', 'phone-alt'],
        [6, 'Line of Business', '#6f42c1', 'cubes'],
        [6, 'Software', '#20c997', 'window-restore'],
        [6, 'Hardware', '#001f3f', 'server'],
        [6, 'Cloud', '#0078d4', 'cloud'],
        [6, 'Security', '#dc3545', 'shield-alt'],
        [6, 'Distributor', '#795548', 'truck'],
        [6, 'Printing', '#6c757d', 'print'],
        [6, 'Hosting', '#17a2b8', 'globe'],
        [6, 'Professional Services', '#343a40', 'briefcase'],
        [6, 'Authorized Contacts Only', '#d81b60', 'user-lock'],

        // Domain
        [7, 'Primary', '#007bff', 'star'],
        [7, 'Client-owned', '#6f42c1', 'user-tie'],
        [7, 'Managed DNS', '#20c997', 'sitemap'],
        [7, 'Parked', '#adb5bd', 'parking'],
        [7, 'Marketing', '#fd7e14', 'bullhorn'],
        [7, 'Do Not Renew', '#dc3545', 'ban'],

        // License
        [8, 'We Resell', '#007bff', 'handshake'],
        [8, 'Client-paid', '#6f42c1', 'user-tie'],
        [8, 'Annual True-up', '#fd7e14', 'sync-alt'],
        [8, 'Core Stack', '#001f3f', 'layer-group'],
        [8, 'Do Not Renew', '#dc3545', 'ban'],

        // Service
        [9, 'Managed', '#28a745', 'handshake'],
        [9, 'Client-managed', '#6f42c1', 'user-tie'],
        [9, 'Cloud', '#0dcaf0', 'cloud'],
        [9, 'On-premises', '#343a40', 'server'],
        [9, 'Business Hours Only', '#ffc107', 'clock'],

        // Network
        [10, 'Managed', '#28a745', 'handshake'],
        [10, 'Segmented', '#6610f2', 'network-wired'],
        [10, 'PCI', '#dc3545', 'credit-card'],
        [10, 'IoT', '#20c997', 'microchip'],

        // Document
        [11, 'Procedure', '#007bff', 'list-ol'],
        [11, 'Runbook', '#001f3f', 'book'],
        [11, 'Policy', '#6f42c1', 'gavel'],
        [11, 'Client-facing', '#20c997', 'eye'],
        [11, 'Needs Review', '#fd7e14', 'exclamation-circle'],

        // Ticket
        [12, 'Onboarding', '#007bff', 'user-plus'],
        [12, 'Offboarding', '#dc3545', 'user-minus'],
        [12, 'Escalated', '#d81b60', 'fire'],
        [12, 'Warranty Claim', '#28a745', 'certificate'],
        [12, 'Waiting on Vendor', '#ffc107', 'hourglass-half'],
        [12, 'Security', '#343a40', 'shield-alt'],

        // Knowledge Base article
        [13, 'How-To', '#007bff', 'list-ol'],
        [13, 'Troubleshooting', '#fd7e14', 'wrench'],
        [13, 'Quick Fix', '#ffc107', 'bolt'],
        [13, 'Self-Service', '#28a745', 'hand-pointer'],
        [13, 'Security', '#dc3545', 'shield-alt'],
        [13, 'Policy', '#001f3f', 'gavel'],
        [13, 'Microsoft 365', '#0078d4', 'cloud'],
        [13, 'Mobile', '#17a2b8', 'mobile-alt'],
        [13, 'New Starter', '#20c997', 'user-plus'],
        [13, 'Known Issue', '#d81b60', 'exclamation-triangle'],
    ];

    return $tags;

}

// ------------------------------
// starterContentTicketTemplates
// In the ticket template file format (functions/ticket_template_transfer.php),
// loaded through the same import. Details are HTML - the modals edit them
// through TinyMCE - and task estimates are in minutes. Categories and tags
// are this library's own, so they carry their colour and icon and are made
// when the Categories or Tags pack has not been added. Request forms come in
// turned off; the admin turns each on in its Client portal tab.
// ------------------------------
function starterContentTicketTemplates() {

    // This library's ticket categories and ticket tags, by name
    $category = function ($name) {
        foreach (starterContentCategories() as $row) {
            if ($row['type'] === 'Ticket' && $row['name'] === $name) {
                return ['name' => $row['name'], 'color' => $row['color'], 'icon' => $row['icon'], 'description' => $row['description']];
            }
        }
        return null;
    };
    $tag = function ($name) {
        foreach (starterContentTags() as $row) {
            if ($row[0] === 12 && $row[1] === $name) {
                return ['name' => $row[1], 'color' => $row[2], 'icon' => $row[3]];
            }
        }
        return ['name' => $name, 'color' => '', 'icon' => ''];
    };
    $fill_in = function ($name, $type = 'text', array $o = []) {
        return ['name' => $name, 'type' => $type, 'options' => $o['options'] ?? [], 'required' => !empty($o['required']),
            'client' => $o['client'] ?? true, 'help' => $o['help'] ?? ''];
    };
    $task = function ($name, $minutes, $when = null) {
        return ['name' => $name, 'estimate' => $minutes, 'when' => $when ? ['fill_in' => $when[0], 'value' => $when[1]] : null];
    };

    $ticket_templates = [
        [
            'name' => 'New Hire Onboarding',
            'description' => 'Standard setup for a new employee joining a client',
            'subject' => 'New hire onboarding - [Employee Name]',
            'details' => '<p>[Employee Name] starts on [Start Date] as [Job Title], reporting to [Manager]. Mirror their access from [Copy Access From].</p><ul><li>Computer: [Needs a Computer]</li><li>Phone extension: [Needs a Phone Extension]</li><li>License: [License]</li></ul><p>Do not create anything until an authorized contact has approved the request in writing.</p>',
            'category' => $category('Onboarding'),
            'priority' => 'Medium',
            'tags' => [$tag('Onboarding')],
            'fill_ins' => [
                $fill_in('Employee Name', 'text', ['required' => true, 'help' => 'As it should appear in their email address and display name']),
                $fill_in('Start Date', 'date', ['required' => true]),
                $fill_in('Job Title'),
                $fill_in('Manager'),
                $fill_in('Copy Access From', 'text', ['help' => 'Someone whose access to files, groups and applications they should match']),
                $fill_in('Needs a Computer', 'yesno', ['required' => true, 'help' => 'We order it, set it up and have it on their desk']),
                $fill_in('Needs a Phone Extension', 'yesno'),
                $fill_in('License', 'list', ['options' => ['Business Basic', 'Business Standard', 'Business Premium', 'Exchange Online only'], 'client' => false]),
            ],
            'tasks' => [
                $task('Confirm start date and written approval from an authorized contact', 15),
                $task('Create the directory account and set the initial password', 15),
                $task('Assign the [License] license', 10),
                $task('Create the mailbox, signature and alias', 20),
                $task('Add to security and distribution groups, matching [Copy Access From]', 15),
                $task('Configure file share and application permissions', 20),
                $task('Order the computer', 15, ['Needs a Computer', 'Yes']),
                $task('Image and enroll the workstation', 60, ['Needs a Computer', 'Yes']),
                $task('Install the standard application stack', 30, ['Needs a Computer', 'Yes']),
                $task('Install line of business applications', 30),
                $task('Enroll multi-factor authentication', 15),
                $task('Configure the phone extension and voicemail', 15, ['Needs a Phone Extension', 'Yes']),
                $task('Add to RMM, endpoint protection and backup', 20, ['Needs a Computer', 'Yes']),
                $task('Hand over credentials to [Employee Name] securely', 10),
                $task('Create the contact record and document the setup', 15),
            ],
            'portal' => ['title' => 'Onboard a new employee', 'description' => 'Tell us who is starting and we will have their account and computer ready for their first day', 'icon' => 'user-plus', 'who' => 'managers'],
        ],
        [
            'name' => 'Employee Offboarding',
            'description' => 'Standard process for a departing employee',
            'subject' => 'Employee offboarding - [Employee Name]',
            'details' => '<p>[Employee Name] leaves on [Last Day]. Cut off access straight away: [Cut Off Access Now]. Their email and files go to [Mail and Files Go To]. A company computer to collect: [Has a Company Computer].</p><p>Do not start until written authorization has been received from an authorized contact.</p>',
            'category' => $category('Offboarding'),
            'priority' => 'High',
            'tags' => [$tag('Offboarding')],
            'fill_ins' => [
                $fill_in('Employee Name', 'text', ['required' => true]),
                $fill_in('Last Day', 'date', ['required' => true]),
                $fill_in('Cut Off Access Now', 'yesno', ['required' => true, 'help' => 'Yes and we act today, before their last day']),
                $fill_in('Mail and Files Go To', 'text', ['help' => 'Who takes over their mailbox and files - leave it blank to keep them as they are']),
                $fill_in('Has a Company Computer', 'yesno', ['required' => true]),
            ],
            'tasks' => [
                $task('Obtain written authorization and confirm the effective date', 15),
                $task('Disable the account and reset the password', 10),
                $task('Revoke multi-factor authentication and active sessions', 10),
                $task('Convert the mailbox to shared or set forwarding to [Mail and Files Go To]', 15),
                $task('Remove group, share and application access', 20),
                $task('Reclaim and reassign licenses', 15),
                $task('Collect the computer, keys and access cards', 30, ['Has a Company Computer', 'Yes']),
                $task('Back up and hand over user data', 30),
                $task('Wipe and reimage the computer', 60, ['Has a Company Computer', 'Yes']),
                $task('Remove the device from RMM, endpoint protection and backup', 15, ['Has a Company Computer', 'Yes']),
                $task('Update documentation and contact records', 15),
                $task('Notify billing of the license change', 10),
            ],
            'portal' => ['title' => 'Someone is leaving', 'description' => 'Close down their accounts, hand over their email and files, and get their computer back', 'icon' => 'user-minus', 'who' => 'managers'],
        ],
        [
            'name' => 'Workstation Deployment',
            'description' => 'Build and deploy a new desktop or laptop',
            'subject' => 'Workstation deployment - [Client] - [User]',
            'details' => '<p>New [Kind of Computer] for [User], needed by [Needed By]. Replaces an existing computer: [Replaces One].</p><p>Order reference: [Order Reference]</p>',
            'category' => $category('Workstation'),
            'priority' => 'Medium',
            'fill_ins' => [
                $fill_in('User', 'text', ['required' => true, 'help' => 'Who it is for']),
                $fill_in('Kind of Computer', 'list', ['required' => true, 'options' => ['Laptop', 'Desktop']]),
                $fill_in('Needed By', 'date'),
                $fill_in('Replaces One', 'yesno', ['required' => true, 'help' => 'Yes if it takes the place of a computer they have now']),
                $fill_in('Order Reference', 'text', ['client' => false, 'help' => 'The quote or purchase order']),
            ],
            'tasks' => [
                $task('Confirm the quote or purchase order', 10),
                $task('Receive, inventory and create the asset record', 20),
                $task('Record the serial number and warranty expiry', 10),
                $task('Image the device and apply all updates', 60),
                $task('Join the domain or cloud directory', 15),
                $task('Install the standard application stack', 30),
                $task('Install line of business applications', 30),
                $task('Map printers and network drives', 15),
                $task('Enroll in RMM, endpoint protection and backup', 20),
                $task('Migrate the user profile and data', 60, ['Replaces One', 'Yes']),
                $task('Verify mail, file sync and printing', 20),
                $task('Deliver it and walk [User] through it', 30),
                $task('Wipe and retire the old computer', 30, ['Replaces One', 'Yes']),
            ],
            'portal' => ['title' => 'Ask for a new computer', 'description' => 'A laptop or desktop for someone, set up and ready to use', 'icon' => 'laptop', 'who' => 'managers'],
        ],
        [
            'name' => 'Server Deployment',
            'description' => 'Build and commission a new physical or virtual server',
            'subject' => 'Server deployment - [Client] - [Server Name]',
            'details' => '<p>New [Physical or Virtual] server for [Role]. Maintenance window: [Maintenance Window].</p><p>Confirm the sizing and network placement before starting.</p>',
            'category' => $category('Server'),
            'priority' => 'Medium',
            'fill_ins' => [
                $fill_in('Server Name', 'text', ['required' => true]),
                $fill_in('Role', 'text', ['help' => 'What it is for - file server, domain controller, line of business application']),
                $fill_in('Physical or Virtual', 'list', ['required' => true, 'options' => ['Physical', 'Virtual']]),
                $fill_in('Maintenance Window'),
            ],
            'tasks' => [
                $task('Confirm the specification, role and sizing', 30),
                $task('Rack, cable and label the hardware', 60, ['Physical or Virtual', 'Physical']),
                $task('Configure out of band management', 30, ['Physical or Virtual', 'Physical']),
                $task('Create the virtual machine and assign its resources', 30, ['Physical or Virtual', 'Virtual']),
                $task('Install the operating system and patch fully', 90),
                $task('Configure storage, RAID and volumes', 45),
                $task('Set the static addressing, DNS and time source', 20),
                $task('Install the server role and application software', 60),
                $task('Add to RMM, monitoring and endpoint protection', 20),
                $task('Add to the backup job and run a first full backup', 45),
                $task('Document the build and hand over to the client', 30),
            ],
        ],
        [
            'name' => 'Monthly Maintenance',
            'description' => 'Recurring monthly maintenance pass for a managed client',
            'subject' => 'Monthly maintenance - [Client] - [Month]',
            'details' => '<p>Scheduled monthly maintenance for [Client], [Month]. Work the checklist and send the client a summary when complete.</p>',
            'category' => $category('Maintenance'),
            'priority' => 'Low',
            'due_hours' => 7 * 24,
            'tasks' => [
                $task('Review patch compliance across the fleet', 20),
                $task('Approve and deploy pending patches', 30),
                $task('Reboot within the agreed maintenance window', 30),
                $task('Verify services and applications after reboot', 30),
                $task('Check disk space and clear down where needed', 20),
                $task('Review endpoint protection alerts and quarantine', 20),
                $task('Verify backup jobs completed and spot check a restore', 30),
                $task('Check UPS battery health and runtime', 15),
                $task('Review firewall firmware and license expiry', 15),
                $task('Review certificate and domain expiry', 15),
                $task('Update documentation and send the client a summary', 30),
            ],
        ],
        [
            'name' => 'Backup Failure Investigation',
            'description' => 'Triage and resolve a failed or missed backup job',
            'subject' => 'Backup failure - [Client] - [Job Name]',
            'details' => '<p>Backup job [Job Name] failed or did not report. Last known good backup: [Last Good Backup]. Establish the last restore point first, then work the cause.</p>',
            'category' => $category('Backup and Recovery'),
            'priority' => 'High',
            'due_hours' => 24,
            'fill_ins' => [
                $fill_in('Job Name', 'text', ['required' => true]),
                $fill_in('Last Good Backup', 'date'),
            ],
            'tasks' => [
                $task('Identify the last successful restore point', 15),
                $task('Review the job log and error detail', 20),
                $task('Check source, target and network availability', 20),
                $task('Check free space on the backup target', 10),
                $task('Check agent and service health on the protected system', 20),
                $task('Apply the fix and re-run the job', 30),
                $task('Verify the job completes and the restore point is valid', 20),
                $task('Document the cause and update the client', 20),
            ],
        ],
        [
            'name' => 'Backup Restore Test',
            'description' => 'Scheduled proof that backups can actually be restored',
            'subject' => 'Restore test - [Client] - [Quarter]',
            'details' => '<p>Scheduled restore test for [Client], [Quarter]. The goal is a documented, dated proof of recoverability - record the actual recovery time achieved.</p>',
            'category' => $category('Backup and Recovery'),
            'priority' => 'Low',
            'due_hours' => 14 * 24,
            'tasks' => [
                $task('Agree the restore targets and test window with the client', 20),
                $task('Restore a file level sample and verify contents', 30),
                $task('Perform a full system or virtual machine test restore', 90),
                $task('Verify the restored system boots and services start', 45),
                $task('Verify application and database integrity', 45),
                $task('Record the recovery time actually achieved', 15),
                $task('Tear down the test environment', 20),
                $task('Document the result and report to the client', 30),
            ],
        ],
        [
            'name' => 'Phishing Report',
            'description' => 'User reported a suspicious email',
            'subject' => 'Reported phishing email - [Client] - [User]',
            'details' => '<p>[User] reported a suspicious email: [Email Subject]. Clicked a link or opened an attachment: [Clicked or Opened]. Entered a password: [Entered a Password].</p><p>Treat as credential compromise until proven otherwise.</p>',
            'category' => $category('Phishing Report'),
            'priority' => 'High',
            'due_hours' => 4,
            'tags' => [$tag('Security')],
            'fill_ins' => [
                $fill_in('User', 'text', ['required' => true, 'help' => 'Who got the email']),
                $fill_in('Email Subject', 'text', ['help' => 'Its subject line, or who it says it is from']),
                $fill_in('Clicked or Opened', 'yesno', ['required' => true, 'help' => 'Did anyone click a link or open an attachment in it?']),
                $fill_in('Entered a Password', 'yesno', ['required' => true, 'help' => 'Did anyone type a password into a page it opened?']),
            ],
            'tasks' => [
                $task('Obtain the message with full headers', 15),
                $task('Analyze the sender, links and attachments', 20),
                $task('Force a password reset and revoke sessions for [User]', 20, ['Entered a Password', 'Yes']),
                $task('Scan the computer the attachment was opened on', 30, ['Clicked or Opened', 'Yes']),
                $task('Audit mailbox rules and forwarding', 20),
                $task('Review sign-in logs for anomalous access', 30),
                $task('Block the sender and domain at the mail gateway', 15),
                $task('Purge the message from all affected mailboxes', 20),
                $task('Feed back to the reporting user and management', 15),
                $task('Document the outcome', 15),
            ],
            'portal' => ['title' => 'Report a suspicious email', 'description' => 'Got an email that does not look right? Tell us - and tell us straight away if you clicked anything in it', 'icon' => 'shield-alt', 'who' => 'everyone'],
        ],
        [
            'name' => 'Security Incident Response',
            'description' => 'Suspected or confirmed compromise',
            'subject' => 'Security incident - [Client] - [Summary]',
            'details' => '<p>Suspected or confirmed compromise: [Summary]. First noticed [First Noticed].</p><p>Preserve evidence before remediating, and check the client cyber insurance policy for notification requirements and approved responders before acting.</p>',
            'category' => $category('Security Incident'),
            'priority' => 'Urgent',
            'due_hours' => 4,
            'tags' => [$tag('Security'), $tag('Escalated')],
            'fill_ins' => [
                $fill_in('Summary', 'text', ['required' => true]),
                $fill_in('First Noticed', 'date'),
            ],
            'tasks' => [
                $task('Declare the incident and establish scope and impact', 30),
                $task('Isolate affected systems and accounts', 30),
                $task('Preserve logs, images and evidence', 60),
                $task('Notify the client, insurer and any required authority', 30),
                $task('Contain the spread and close the entry point', 120),
                $task('Eradicate persistence and reset all affected credentials', 120),
                $task('Restore from a known good backup and verify', 180),
                $task('Monitor for reinfection', 60),
                $task('Produce the post incident report and remediation plan', 90),
            ],
        ],
        [
            'name' => 'Client Onboarding',
            'description' => 'Bring a newly signed client under management',
            'subject' => 'Client onboarding - [Client]',
            'details' => '<p>New client onboarding for [Client]. Nothing goes live until the agreement is countersigned. The output of this ticket is a fully documented client that can be supported by anyone on the team.</p>',
            'category' => $category('Project Work'),
            'priority' => 'Medium',
            'tasks' => [
                $task('Confirm the countersigned agreement is on file', 15),
                $task('Hold the kickoff call and agree the escalation path', 60),
                $task('Collect contacts, locations, hours and authorized approvers', 60),
                $task('Discover and document the network', 180),
                $task('Inventory assets and create asset records', 180),
                $task('Deploy RMM and endpoint protection', 120),
                $task('Move all credentials into the vault', 120),
                $task('Configure and verify backups', 120),
                $task('Run a security baseline audit', 120),
                $task('Review DNS, mail records and certificate expiry', 60),
                $task('Set up billing, invoicing and autopay', 45),
                $task('Set up the client portal and its request forms', 30),
                $task('Schedule the review cadence', 20),
                $task('Hold the 30 day review', 60),
            ],
        ],
        [
            'name' => 'Client Offboarding',
            'description' => 'Cleanly exit a departing client',
            'subject' => 'Client offboarding - [Client]',
            'details' => '<p>[Client] is leaving on [Termination Date]. Handing over to: [Incoming Provider].</p><p>Agree in writing what is handed over, what is retained and for how long, and settle the final invoice before access is removed.</p>',
            'category' => $category('Project Work'),
            'priority' => 'Medium',
            'fill_ins' => [
                $fill_in('Termination Date', 'date', ['required' => true]),
                $fill_in('Incoming Provider', 'text', ['help' => 'Who takes over - or the client themselves']),
            ],
            'tasks' => [
                $task('Confirm the notice period and termination date in writing', 20),
                $task('Agree the handover scope with the client or incoming provider', 45),
                $task('Raise and settle the final invoice', 30),
                $task('Export and hand over documentation', 60),
                $task('Hand over credentials securely and confirm receipt', 45),
                $task('Remove RMM, endpoint protection and monitoring agents', 60),
                $task('Release or transfer licenses and subscriptions', 45),
                $task('Agree the backup retention and destruction date', 20),
                $task('Revoke all remaining access and delegated permissions', 45),
                $task('Archive the client record and close out', 30),
            ],
        ],
        [
            'name' => 'Firewall Deployment',
            'description' => 'Replace or deploy a perimeter firewall',
            'subject' => 'Firewall deployment - [Client] - [Site]',
            'details' => '<p>Firewall deployment or replacement at [Site]: [Firewall Model]. Change window: [Change Window]. Remote access users: [Has VPN Users].</p><p>Capture the existing configuration and public addressing before the change window, and agree a rollback plan.</p>',
            'category' => $category('Firewall'),
            'priority' => 'Medium',
            'fill_ins' => [
                $fill_in('Firewall Model'),
                $fill_in('Change Window', 'text', ['help' => 'When the cutover can happen']),
                $fill_in('Has VPN Users', 'yesno', ['required' => true]),
            ],
            'tasks' => [
                $task('Capture the current configuration, rules and public addressing', 60),
                $task('Confirm the license, subscription and support entitlement', 20),
                $task('Stage and update the firmware on the bench', 45),
                $task('Build the base configuration, interfaces and routing', 90),
                $task('Recreate firewall rules, NAT and port forwards', 90),
                $task('Configure VPN tunnels and remote access', 60, ['Has VPN Users', 'Yes']),
                $task('Agree the change window and notify the client', 20),
                $task('Cut over and verify connectivity and services', 60),
                $task('Verify VPN, remote access and any published services', 45, ['Has VPN Users', 'Yes']),
                $task('Document the configuration and store the credentials', 45),
            ],
        ],
        [
            'name' => 'Microsoft 365 Tenant Onboarding',
            'description' => 'Bring a client tenant under management and to a security baseline',
            'subject' => 'Microsoft 365 tenant onboarding - [Client]',
            'details' => '<p>Tenant onboarding for [Client]. Establish delegated access, document the licensing position, then bring the tenant to the agreed security baseline.</p>',
            'category' => $category('Microsoft 365'),
            'priority' => 'Medium',
            'tasks' => [
                $task('Establish delegated administrative access', 30),
                $task('Document the tenant ID, domains and license position', 45),
                $task('Create the break glass administrator account and vault it', 30),
                $task('Enforce multi-factor authentication for all users', 60),
                $task('Review and harden the conditional access policy', 60),
                $task('Review mail flow, connectors and forwarding rules', 45),
                $task('Verify SPF, DKIM and DMARC records', 45),
                $task('Enable and configure audit logging and alerting', 45),
                $task('Configure tenant backup', 60),
                $task('Document the tenant and report the baseline findings', 60),
            ],
        ],
        [
            'name' => 'Quarterly Business Review',
            'description' => 'Prepare and deliver a client review meeting',
            'subject' => 'Quarterly business review - [Client] - [Quarter]',
            'details' => '<p>Review meeting with [Client] for [Quarter], on [Meeting Date]. Pull the numbers before the meeting - the Client Review report has them - and lead with outcomes rather than activity: ticket volume, uptime, risks closed and what is coming next.</p>',
            'priority' => 'Low',
            'fill_ins' => [
                $fill_in('Meeting Date', 'date'),
            ],
            'tasks' => [
                $task('Pull ticket volume, response and resolution figures', 45),
                $task('Review asset age, warranty and end of life exposure', 45),
                $task('Review the security posture and any open risks', 45),
                $task('Review backup and restore test results for the period', 30),
                $task('Review spend against budget and the license position', 30),
                $task('Build the roadmap and budget recommendations', 60),
                $task('Circulate the pack and hold the meeting', 90),
                $task('Log the agreed actions and raise the follow up work', 45),
            ],
        ],
        [
            'name' => 'Software Request',
            'description' => 'Install an application for a user',
            'subject' => 'Software request - [Software] - [Asset]',
            'details' => '<p>[Contact] asked for [Software] on [Asset], for [Who It Is For]. Already licensed: [Already Licensed]. What it is for: [What It Is For].</p>',
            'category' => $category('Software'),
            'priority' => 'Low',
            'due_hours' => 3 * 24,
            'fill_ins' => [
                $fill_in('Software', 'text', ['required' => true, 'help' => 'Its name, and the version if it matters']),
                $fill_in('Who It Is For', 'text', ['help' => 'Leave it blank if it is for you']),
                $fill_in('Already Licensed', 'yesno', ['required' => true, 'help' => 'Do you have a license or subscription for it already?']),
                $fill_in('What It Is For'),
            ],
            'tasks' => [
                $task('Check it is compatible and approved for the client', 10),
                $task('Quote and order the license', 15, ['Already Licensed', 'No']),
                $task('Install and license [Software]', 30),
                $task('Verify it works with the user', 10),
                $task('Record the license and update the asset', 10),
            ],
            'portal' => ['title' => 'Install some software', 'description' => 'An application you need on your computer', 'icon' => 'cube', 'who' => 'everyone'],
        ],
        [
            'name' => 'Access Change Request',
            'description' => 'Give, change or remove someone\'s access to a share, mailbox or application',
            'subject' => 'Access change - [Person] - [Access To]',
            'details' => '<p>[Change] for [Person] to [Access To], needed by [Needed By]. Asked for by [Contact].</p><p>Changes to access need the approval of an authorized contact.</p>',
            'category' => $category('Account and Access'),
            'priority' => 'Medium',
            'due_hours' => 2 * 24,
            'fill_ins' => [
                $fill_in('Person', 'text', ['required' => true]),
                $fill_in('Access To', 'text', ['required' => true, 'help' => 'The shared drive, mailbox, application or system']),
                $fill_in('Change', 'list', ['required' => true, 'options' => ['Give access', 'Change what they can do', 'Remove access']]),
                $fill_in('Needed By', 'date'),
            ],
            'tasks' => [
                $task('Confirm approval from an authorized contact', 10),
                $task('Make the change', 15),
                $task('Confirm with [Person] that it works', 10, ['Change', 'Give access']),
                $task('Document the change', 10),
            ],
            'portal' => ['title' => 'Change someone\'s access', 'description' => 'Give someone access to a shared drive, mailbox or application - or take it away', 'icon' => 'key', 'who' => 'managers'],
        ],
    ];

    return $ticket_templates;

}

// ------------------------------
// starterContentCannedResponses
// In the canned response file format (functions/canned_response_transfer.php),
// loaded through the same import, as shared responses. What they say is HTML -
// the window edits it through TinyMCE. [Contact], [First Name], [Ticket],
// [Ticket Link] and the other placeholders are filled in from the ticket as a
// response goes in; anything else in square brackets is left for the agent
// to fill in before sending. Categories are this library's own, so they carry
// their colour and icon and are made when the Categories pack has not been
// added. Statuses are the built-in ones, by name.
// ------------------------------
function starterContentCannedResponses() {

    // This library's ticket categories, by name
    $category = function ($name) {
        foreach (starterContentCategories() as $row) {
            if ($row['type'] === 'Ticket' && $row['name'] === $name) {
                return ['name' => $row['name'], 'color' => $row['color'], 'icon' => $row['icon'], 'description' => $row['description']];
            }
        }
        return ['name' => $name, 'color' => '', 'icon' => '', 'description' => ''];
    };
    $response = function ($name, $body, array $categories = [], $reply_type = 'email', $status = null, $minutes = 0) use ($category) {
        return ['name' => $name, 'body' => $body, 'categories' => array_map($category, $categories), 'reply_type' => $reply_type, 'status' => $status, 'minutes' => $minutes];
    };
    $signed = '<p>Thanks,<br>[Agent]</p>';

    return [

        // Every ticket
        $response('Received - looking into it',
            '<p>Hi [First Name],</p><p>Thanks for getting in touch. We have logged this as [Ticket] - [Subject], and I am looking into it now. I will update you as soon as I know more.</p><p>You can follow it here: [Ticket Link]</p>' . $signed,
            [], 'email', 'Open', 5),
        $response('Waiting on you',
            '<p>Hi [First Name],</p><p>Just checking in on [Ticket] - [Subject]. Before we can go any further we need [What we need from them].</p><p>Reply to this email when you have it, and we will pick it straight back up.</p>' . $signed,
            [], 'email', 'On Hold', 5),
        $response('Following up - no reply yet',
            '<p>Hi [First Name],</p><p>We have not heard back about [Ticket] - [Subject], so I wanted to follow up. Is this still a problem?</p><p>If we do not hear from you by [Date we close it], we will close the ticket - you can reopen it any time by replying to this email.</p>' . $signed,
            [], 'email', 'On Hold', 5),
        $response('Resolved - let us know if not',
            '<p>Hi [First Name],</p><p>Good news - [Subject] should be sorted now. [What was done].</p><p>If anything is not right, just reply to this email and the ticket will reopen.</p>' . $signed,
            [], 'email', 'Resolved', 5),
        $response('Closing - no response',
            '<p>Hi [First Name],</p><p>As we have not heard back, we are closing [Ticket] - [Subject]. If you still need help with it, reply to this email and it will reopen where we left off.</p>' . $signed,
            [], 'email', 'Resolved', 0),
        $response('Remote session - when suits you?',
            '<p>Hi [First Name],</p><p>I would like to connect to your computer to take a look at this. When you are ready, go to [Remote support link] and read me the code it shows, or reply with a time that suits you and I will call.</p><p>You will be able to see everything I do, and you can end the session at any time.</p>' . $signed,
            [], 'email', null, 5),
        $response('Onsite visit booked',
            '<p>Hi [First Name],</p><p>We have booked an engineer to come to [Site] on [Date of visit] at [Time of visit] for [Subject]. They will need [What they need on site].</p><p>If that time no longer works, reply to this email and we will rearrange.</p>' . $signed,
            [], 'email', 'On Hold', 5),
        $response('Escalated to the vendor',
            '<p>Hi [First Name],</p><p>We have raised [Subject] with [Vendor], under their case number [Vendor case number]. We will chase them and update you as soon as they come back to us.</p>' . $signed,
            [], 'email', 'On Hold', 10),
        $response('Escalation - handing over',
            '<p><strong>Escalating to [Escalate to].</strong></p><p>What is wrong: [What is wrong]</p><p>Tried so far: [What has been tried]</p><p>Next step: [Suggested next step]</p>',
            [], 'internal', null, 5),
        $response('Outside the agreement - billable',
            '<p>Hi [First Name],</p><p>[Subject] falls outside what your agreement with us covers, so the time on it would be billed at our standard rate. We estimate it at [Estimate].</p><p>Reply to say you are happy for us to go ahead, and we will get started.</p>' . $signed,
            [], 'email', 'On Hold', 0),

        // Accounts and access
        $response('Password reset done',
            '<p>Hi [First Name],</p><p>I have reset the password for [Account]. The temporary password is coming to you separately - you will be asked to choose a new one when you sign in.</p><p>Make it at least 14 characters, and not one you use anywhere else.</p>' . $signed,
            ['Account and Access'], 'email', 'Resolved', 10),
        $response('Multi-factor sign-in set up again',
            '<p>Hi [First Name],</p><p>I have cleared the sign-in methods on [Account], so you can set up multi-factor sign-in again on your new phone. The next time you sign in, you will be walked through it - have your phone with the Microsoft Authenticator app installed to hand.</p>' . $signed,
            ['Account and Access', 'Microsoft 365'], 'email', 'Resolved', 10),
        $response('Account unlocked',
            '<p>Hi [First Name],</p><p>[Account] was locked after too many wrong passwords, and I have unlocked it - you can sign in again now.</p><p>If it locks again without you mistyping, let us know straight away, as someone else may be trying to sign in as you.</p>' . $signed,
            ['Account and Access'], 'email', 'Resolved', 5),
        $response('License assigned',
            '<p>Hi [First Name],</p><p>I have assigned a [License] license to [Person]. It can take up to an hour for everything to appear - signing out and back in usually speeds it up.</p>' . $signed,
            ['Microsoft 365'], 'email', 'Resolved', 10),

        // Security
        $response('Phishing report - it was phishing',
            '<p>Hi [First Name],</p><p>Thank you for reporting this - it was a phishing email. We have removed it from everyone\'s mailbox and blocked the sender.</p><p>If you clicked a link in it or entered a password, tell us right away and we will secure your account. Reporting it was exactly the right thing to do.</p>' . $signed,
            ['Phishing Report'], 'email', 'Resolved', 15),
        $response('Phishing report - safe',
            '<p>Hi [First Name],</p><p>Thank you for checking with us. We have looked at the email and it is genuine - it is safe to use.</p><p>Always ask if one looks off - it is much better to check than to click.</p>' . $signed,
            ['Phishing Report'], 'email', 'Resolved', 10),
        $response('Possible security incident - first steps',
            '<p>Hi [First Name],</p><p>We are treating this as a possible security incident and are on it now. Until we tell you otherwise:</p><ul><li>Leave the computer switched on, but unplug its network cable or turn off its Wi-Fi.</li><li>Do not delete anything, or try to fix it yourself.</li><li>Change no passwords from that computer.</li></ul><p>I will call you on [Phone number] in the next few minutes.</p>' . $signed,
            ['Security Incident'], 'email', 'Open', 10),

        // Onboarding and offboarding
        $response('New starter ready',
            '<p>Hi [First Name],</p><p>Everything is ready for [New starter] to start on [Start date]: their account, email and the access they need. Their sign-in details are coming to you separately, to hand over on their first day.</p><p>They will be asked to set up multi-factor sign-in the first time they sign in, so they will need their phone.</p>' . $signed,
            ['Onboarding'], 'email', 'Resolved', 15),
        $response('Leaver done',
            '<p>Hi [First Name],</p><p>[Leaver]\'s account is now disabled and they are signed out everywhere. Their mailbox has been shared with [Mailbox shared with], and their files are with [Files moved to].</p><p>Their [Equipment] still needs to come back to us - let us know when it is ready to collect.</p>' . $signed,
            ['Offboarding'], 'email', 'Resolved', 15),

        // Hardware, printing and backups
        $response('Sent for warranty repair',
            '<p>Hi [First Name],</p><p>[Asset] has gone back to the manufacturer under warranty, under their return number [RMA number]. They usually take [How long] - I will let you know as soon as it is back.</p>' . $signed,
            ['Hardware Failure'], 'email', 'On Hold', 15),
        $response('Printer working again',
            '<p>Hi [First Name],</p><p>The printer is working again - [What was done]. Print a test page when you get a moment and let us know if anything still looks wrong.</p>' . $signed,
            ['Printer'], 'email', 'Resolved', 15),
        $response('File restored',
            '<p>Hi [First Name],</p><p>I have restored [File or folder] as it was on [Restore point], to [Where it is now]. Have a look and check it is the version you need.</p>' . $signed,
            ['Backup and Recovery'], 'email', 'Resolved', 15),
        $response('Monitoring alert - cleared, nothing to do',
            '<p>The alert cleared on its own after [How long]. Checked [Asset]: [What was checked]. No action needed.</p>',
            ['Monitoring Alert'], 'internal', 'Resolved', 5),

        // Network and phones
        $response('Internet down - with the provider',
            '<p>Hi [First Name],</p><p>The internet connection at [Site] is down because of a fault on [Provider]\'s side - they have it logged as [Provider reference] and expect it back by [When they expect it back].</p><p>We are keeping an eye on it and will let you know when it is back up.</p>' . $signed,
            ['Network', 'Firewall'], 'email', 'On Hold', 10),
        $response('Voicemail PIN reset',
            '<p>Hi [First Name],</p><p>I have reset the voicemail PIN for extension [Extension] to [Temporary PIN]. You will be asked to choose a new one the next time you check your voicemail.</p>' . $signed,
            ['Phone and VoIP'], 'email', 'Resolved', 5),

        // Purchasing and billing
        $response('Quote sent',
            '<p>Hi [First Name],</p><p>I have sent over the quote for [What it is for]. Accept it from the link in that email and we will place the order - [How long it usually takes] from then.</p>' . $signed,
            ['Procurement'], 'email', 'On Hold', 10),
        $response('Invoice question passed on',
            '<p>Hi [First Name],</p><p>Thank you for your question about invoice [Invoice number]. I have passed it to our accounts team, who will come back to you by [When].</p>' . $signed,
            ['Billing'], 'email', 'On Hold', 0),

    ];

}

// ------------------------------
// starterContentProjectTemplates
// Stages are named ticket templates, resolved at load time.
// ------------------------------
function starterContentProjectTemplates() {

    $project_templates = [
        [
            'name' => 'New Client Onboarding',
            'description' => 'Take a newly signed client from signature to fully managed and documented.',
            'ticket_templates' => ['Client Onboarding', 'Microsoft 365 Tenant Onboarding', 'Backup Restore Test', 'Quarterly Business Review'],
        ],
        [
            'name' => 'Client Offboarding',
            'description' => 'Cleanly exit a departing client with a documented handover.',
            'ticket_templates' => ['Client Offboarding'],
        ],
        [
            'name' => 'Server Refresh',
            'description' => 'Replace aging server hardware and migrate roles and data across.',
            'ticket_templates' => ['Server Deployment', 'Backup Restore Test'],
        ],
        [
            'name' => 'Workstation Refresh',
            'description' => 'Phased replacement of end of life workstations across a client fleet.',
            'ticket_templates' => ['Workstation Deployment'],
        ],
        [
            'name' => 'Network Refresh',
            'description' => 'Replace switching, firewall and wireless and re-document the network.',
            'ticket_templates' => ['Firewall Deployment'],
        ],
        [
            'name' => 'Microsoft 365 Migration',
            'description' => 'Migrate mail and files to a client tenant and decommission the legacy platform.',
            'ticket_templates' => ['Microsoft 365 Tenant Onboarding'],
        ],
        [
            'name' => 'Security Baseline',
            'description' => 'Bring a client up to the agreed security baseline - typically driven by a cyber insurance or compliance requirement.',
            'ticket_templates' => ['Microsoft 365 Tenant Onboarding', 'Backup Restore Test', 'Firewall Deployment'],
        ],
        [
            'name' => 'Backup and Disaster Recovery Implementation',
            'description' => 'Design, deploy and prove a backup and recovery solution.',
            'ticket_templates' => ['Backup Restore Test'],
        ],
        [
            'name' => 'Office Move',
            'description' => 'Relocate a client site including circuits, cabling, network and workstations.',
            'ticket_templates' => ['Firewall Deployment', 'Workstation Deployment', 'Server Deployment'],
        ],
    ];

    return $project_templates;

}

// ------------------------------
// starterContentVendorTemplates
// In the vendor templates file's shape (functions/vendor_template_transfer.php):
// name, what they are, their support site, status page and the portal cases
// are opened in where they have one, and what support asks for. Phone
// numbers, hours and SLAs differ by region and by contract, and account
// numbers and PINs per account, so they are left for whoever adds the vendor
// to a client.
// ------------------------------
function starterContentVendorTemplates() {

    $template = function ($name, $description, $website, $status_page = '', $portal = '', $notes = '') {
        return ['name' => $name, 'description' => $description, 'website' => $website, 'status_page' => $status_page, 'portal' => $portal, 'notes' => $notes];
    };

    return [
        $template('Microsoft', 'Cloud, productivity and operating system vendor', 'support.microsoft.com', 'status.cloud.microsoft', 'admin.microsoft.com',
            "Open a service request from the Microsoft 365 admin center (Support > Help & support), signed in as an admin of the client's tenant - or from Partner Center as their partner.\n\nHave the tenant's domain and the users affected ready. Service health in the admin center shows what Microsoft already knows about; the status page is for when the admin center itself is down."),
        $template('Google', 'Workspace, cloud and domain services', 'support.google.com/a', 'www.google.com/appsstatus/dashboard', 'admin.google.com',
            "Contact support from the Admin console (Help > Contact support), signed in as a super admin of the client's Workspace.\n\nHave the primary domain and the customer ID ready - Account > Account settings in the Admin console."),
        $template('Amazon Web Services', 'Cloud infrastructure and hosting', 'aws.amazon.com/premiumsupport', 'health.aws.amazon.com/health/status', 'console.aws.amazon.com/support',
            "Open a case from the Support Center in the console of the client's AWS account. How fast they answer depends on its support plan - Basic covers account and billing questions only.\n\nHave the account ID and the region ready."),
        $template('Dell', 'Workstation, laptop and server hardware', 'www.dell.com/support', '', 'techdirect.dell.com',
            "Have the service tag ready - it decides the warranty and what support covers. Partners open cases and dispatch parts from TechDirect."),
        $template('HP', 'Workstation, laptop and printer hardware', 'support.hp.com', '', '',
            "Have the serial number and product number ready - on the label, or from HP Support Assistant."),
        $template('Lenovo', 'Workstation and laptop hardware', 'support.lenovo.com', '', '',
            "Have the machine type and serial number ready - on the label, or from Lenovo Vantage."),
        $template('Supermicro', 'Server and storage hardware', 'www.supermicro.com', '', '',
            "Have the system or motherboard serial number ready, and the BMC and BIOS versions."),
        $template('Apple', 'Workstation, laptop and mobile hardware', 'support.apple.com', 'www.apple.com/support/systemstatus', '',
            "Have the device's serial number ready. The system status page covers iCloud, the App Store and Apple's other services."),
        $template('Synology', 'Network attached storage and backup appliances', 'www.synology.com', '', 'account.synology.com',
            "Open a ticket from the Synology Account the device is registered to. Have the serial number and DSM version ready - they usually ask for the debug logs from Support Center on the device."),
        $template('QNAP', 'Network attached storage appliances', 'www.qnap.com', '', '',
            "Have the serial number and QTS or QuTS version ready."),
        $template('Ubiquiti', 'Networking, wireless and surveillance hardware', 'help.ui.com', 'status.ui.com', '',
            "Support is by chat and ticket from the help center, signed in with the UI account the devices are on. Have the device's MAC address and firmware version ready."),
        $template('MikroTik', 'Routing and switching hardware', 'help.mikrotik.com', '', '',
            "Attach a supout.rif from the device to the ticket - Make Supout.rif in WinBox, or /system sup-output - and say which RouterOS version it runs."),
        $template('Cisco', 'Networking, wireless and collaboration hardware', 'www.cisco.com', '', '',
            "A case needs a support contract on the device - have the serial number and contract number ready."),
        $template('Fortinet', 'Firewall and network security appliances', 'www.fortinet.com', '', 'support.fortinet.com',
            "Devices are registered, and tickets opened, in FortiCare. Have the serial number ready."),
        $template('SonicWall', 'Firewall and network security appliances', 'www.sonicwall.com/support', '', 'www.mysonicwall.com',
            "Appliances are registered, and cases opened, in MySonicWall. Have the serial number ready."),
        $template('APC by Schneider Electric', 'Uninterruptible power supplies and power distribution', 'www.apc.com', '', '',
            "Have the model and serial number ready, and when the batteries were last replaced."),
        $template('Brother', 'Printer and scanner hardware', 'support.brother.com', '', '',
            "Have the model and serial number ready."),
        $template('Ingram Micro', 'Hardware and software distribution', 'www.ingrammicro.com', '', '',
            "Your reseller account - quotes, orders and returns go through it and your account rep."),
        $template('TD SYNNEX', 'Hardware and software distribution', 'www.tdsynnex.com', '', '',
            "Your reseller account - quotes, orders and returns go through it and your account rep."),
        $template('Veeam', 'Backup, replication and recovery software', 'www.veeam.com/support.html', '', 'my.veeam.com',
            "Open a case in My Veeam with the license or support ID, and attach the logs the console exports for support."),
        $template('Acronis', 'Backup and cyber protection software', 'www.acronis.com', '', '',
            "Have the account or tenant name ready, and the agent version."),
        $template('Backblaze', 'Cloud backup and object storage', 'help.backblaze.com', 'status.backblaze.com', 'secure.backblaze.com',
            "Have the account's email address ready, and the bucket or computer the case is about."),
        $template('Wasabi', 'Cloud object storage', 'docs.wasabi.com', '', 'console.wasabisys.com',
            "Have the account, the bucket and its region ready."),
        $template('Bitdefender', 'Endpoint protection and security software', 'www.bitdefender.com', '', '',
            "Have the GravityZone company the client is under ready, and the endpoint's name."),
        $template('ESET', 'Endpoint protection and security software', 'www.eset.com', '', '',
            "Have the license or ESET business account ready, and the product version."),
        $template('Proofpoint', 'Email security and filtering', 'www.proofpoint.com', '', '',
            "Have the organization or domain ready, and the message ID of the email the case is about."),
        $template('Bitwarden', 'Password management', 'bitwarden.com/help', 'status.bitwarden.com', 'vault.bitwarden.com',
            "Have the organization's name and the email address of the account the case is about ready."),
        $template('Cloudflare', 'DNS, content delivery and security', 'developers.cloudflare.com', 'www.cloudflarestatus.com', 'dash.cloudflare.com',
            "Cases are opened from the dashboard of the client's Cloudflare account - what support is offered depends on its plan. Have the domain ready."),
        $template('Namecheap', 'Domain registration and hosting', 'www.namecheap.com/support', 'www.namecheap.com/status-updates', '',
            "Have the domain and the account's username ready."),
        $template('GoDaddy', 'Domain registration and hosting', 'www.godaddy.com/help', '', '',
            "Have the customer number and support PIN ready - support asks for them before it helps."),
        $template('Adobe', 'Creative and document software', 'helpx.adobe.com', 'status.adobe.com', 'adminconsole.adobe.com',
            "Admins open cases from the Admin Console (Support). Have the organization and the user's email address ready."),
        $template('Intuit', 'Accounting and payroll software', 'www.intuit.com', '', '',
            "Have the product, the company file or account, and the license number for desktop products ready."),
        $template('Zoom', 'Video conferencing and collaboration', 'support.zoom.com', 'status.zoom.us', '',
            "Have the account number ready, and the meeting ID the case is about."),
        $template('RingCentral', 'Hosted voice and unified communications', 'support.ringcentral.com', '', 'service.ringcentral.com',
            "Have the account's main number ready - admins manage it in the admin portal."),
        $template('Stripe', 'Card and online payment processing', 'support.stripe.com', 'status.stripe.com', 'dashboard.stripe.com',
            "Support is from the Dashboard of the client's Stripe account. Have the account ID (acct_...) ready, and the payment or payout the case is about."),
    ];

}

// ------------------------------
// starterContentDocumentTemplates
// In the document templates file format (functions/document_template_transfer.php):
// name, description, content, fill_ins and review_months. [Client], [Site],
// [Contact], [Agent], [Date] and the rest are filled in from the client as a
// document is made; [Networks], [Servers], [Licenses] and the other tables of
// records put in the client's records as they are then; any other [word] is
// a fill-in, asked for as the document is made. Who should have each is left
// to the MSP, on the template's page.
// ------------------------------
function starterContentDocumentTemplates() {

    // A table with its headings and one empty row to fill in
    $table = function (array $headings, array $rows = []) {
        $html = '<table style="width:100%" border="1"><tbody><tr>';
        foreach ($headings as $heading) {
            $html .= "<th>$heading</th>";
        }
        $html .= '</tr>';
        foreach ($rows ?: [array_fill(0, count($headings), '')] as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= "<td>$cell</td>";
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    };
    $list = function (array $items) {
        return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
    };
    $steps = function (array $items) {
        return '<ol><li>' . implode('</li><li>', $items) . '</li></ol>';
    };
    $fill_in = function ($name, $type = 'text', $options = [], $required = false, $help = '') {
        return ['name' => $name, 'type' => $type, 'options' => $options, 'required' => $required, 'help' => $help];
    };

    return [
        [
            'name' => 'Network Overview',
            'description' => 'Sites, internet circuits, networks, equipment and domains for a client',
            'review_months' => 12,
            'fill_ins' => [
                $fill_in('Internet Provider'),
                $fill_in('Circuit Type', 'list', ['Fiber', 'Cable', 'DSL', 'Fixed wireless', 'Cellular']),
                $fill_in('Circuit Speed', 'text', [], false, 'Down and up - 500/500 Mbps'),
                $fill_in('Public IP', 'text', [], false, 'Static address or block, if there is one'),
                $fill_in('Firewall Model'),
            ],
            'content' => '<h3>[Client] - Network Overview</h3>'
                . '<p><strong>Prepared by:</strong> [Agent]<br><strong>Prepared:</strong> [Date]</p>'
                . '<h4>Sites</h4><p>[Locations]</p>'
                . '<h4>Internet Circuits</h4>' . $table(['Site', 'Provider', 'Type', 'Speed', 'Public IP', 'Account', 'Support'], [['[Site]', '[Internet Provider]', '[Circuit Type]', '[Circuit Speed]', '[Public IP]', '', '']])
                . '<h4>Networks and VLANs</h4><p>[Networks]</p>'
                . '<h4>Network Equipment</h4><p>[Network Equipment]</p>'
                . '<h4>Firewall</h4>' . $list(['Model: [Firewall Model]', 'Management access:', 'Remote access VPN:', 'Where its configuration backup is kept:'])
                . '<h4>Wireless</h4>' . $table(['SSID', 'Network', 'Security', 'Purpose'])
                . '<h4>Domains and DNS</h4><p>[Domains]</p>'
                . '<h4>Notes</h4><p></p>',
        ],
        [
            'name' => 'Server Build Sheet',
            'description' => 'As-built record for a physical, virtual or cloud server',
            'review_months' => 12,
            'fill_ins' => [
                $fill_in('Hostname', 'text', [], true),
                $fill_in('Server Role', 'text', [], true, 'Domain controller, file server, line of business application...'),
                $fill_in('Platform', 'list', ['Virtual machine', 'Physical server', 'Cloud instance']),
                $fill_in('Operating System'),
                $fill_in('Build Date', 'date'),
            ],
            'content' => '<h3>Server Build Sheet - [Hostname]</h3>'
                . '<p><strong>Client:</strong> [Client]<br><strong>Role:</strong> [Server Role]<br><strong>Platform:</strong> [Platform]<br><strong>Built:</strong> [Build Date]<br><strong>Built by:</strong> [Agent]</p>'
                . '<h4>Specification</h4>' . $table(['CPU', 'Memory', 'Storage', 'Host or chassis', 'Serial or service tag'])
                . '<h4>Operating System</h4>' . $list(['Version and edition: [Operating System]', 'License:', 'Patching:'])
                . '<h4>Network</h4>' . $table(['Interface', 'IP address', 'Network', 'Gateway', 'DNS'])
                . '<h4>Storage</h4>' . $table(['Volume', 'Size', 'Purpose', 'RAID or datastore'])
                . '<h4>Roles, Features and Applications</h4><ul><li></li></ul>'
                . '<h4>Backup</h4>' . $list(['Product:', 'Schedule:', 'Last restore test:'])
                . '<h4>The Other Servers at [Client]</h4><p>[Servers]</p>'
                . '<h4>Build Notes</h4><p></p>',
        ],
        [
            'name' => 'Backup and Recovery Runbook',
            'description' => 'What is protected, how often, and how to restore it',
            'review_months' => 6,
            'fill_ins' => [
                $fill_in('Backup Product'),
                $fill_in('Recovery Point Objective', 'list', ['15 minutes', '1 hour', '4 hours', '24 hours'], false, 'The most data the client has agreed it can lose'),
                $fill_in('Recovery Time Objective', 'list', ['1 hour', '4 hours', '1 business day', '3 business days'], false, 'How long the client has agreed it can be without a system'),
                $fill_in('Last Restore Test', 'date'),
            ],
            'content' => '<h3>Backup and Recovery Runbook - [Client]</h3>'
                . '<p><strong>Backup product:</strong> [Backup Product]<br><strong>Last restore test:</strong> [Last Restore Test]<br><strong>Prepared by:</strong> [Agent], [Date]</p>'
                . '<h4>Agreed Objectives</h4>' . $list(['Recovery point objective: [Recovery Point Objective]', 'Recovery time objective: [Recovery Time Objective]'])
                . '<h4>Servers</h4><p>[Servers]</p>'
                . '<h4>Protected Systems</h4>' . $table(['System', 'Job', 'Schedule', 'Retention', 'Target', 'Offsite copy'])
                . '<h4>Restore Procedure</h4>' . $steps(['Agree the scope of the loss and the restore point with [Contact].', 'Restore to a separate location first when the original may still be compromised.', '', 'Check the restored data with the user who reported it before closing the ticket.'])
                . '<h4>Restore Test Log</h4>' . $table(['Date', 'System', 'Restored to', 'Time taken', 'Result', 'By'])
                . '<h4>Monitoring</h4>' . $list(['Failures are reported to:', 'Checked daily by:']),
        ],
        [
            'name' => 'Disaster Recovery Plan',
            'description' => 'What happens when a site or a critical system is lost',
            'review_months' => 12,
            'fill_ins' => [
                $fill_in('Alternate Site', 'text', [], false, 'Where people work if the office is lost'),
            ],
            'content' => '<h3>Disaster Recovery Plan - [Client]</h3>'
                . '<p><strong>Approved by:</strong> [Contact]<br><strong>Prepared by:</strong> [Agent]<br><strong>Last reviewed:</strong> [Date]</p>'
                . '<h4>Scope and Assumptions</h4><p></p>'
                . '<h4>Sites</h4><p>[Locations]</p>'
                . '<h4>Services</h4><p>[Services]</p>'
                . '<h4>Recovery Targets in Priority Order</h4>' . $table(['Priority', 'System', 'Business function', 'Recovery target', 'Depends on'], [['1', '', '', '', ''], ['2', '', '', '', ''], ['3', '', '', '', '']])
                . '<h4>Alternate Working Arrangements</h4>' . $list(['Alternate site: [Alternate Site]', 'Remote working:', 'Phones:'])
                . '<h4>Invoking the Plan</h4>' . $steps(['Who can declare a disaster:', 'How staff are told:', 'Where the response team meets:'])
                . '<h4>Who to Call</h4><p>[Contacts]</p>'
                . '<h4>Vendors</h4><p>[Vendors]</p>'
                . '<h4>Test History</h4>' . $table(['Date', 'Scenario', 'Result', 'Actions']),
        ],
        [
            'name' => 'Site and Access Details',
            'description' => 'Getting into a site - access, keyholders, parking and where the equipment is',
            'review_months' => 12,
            'fill_ins' => [
                $fill_in('Access Method', 'list', ['Key', 'Fob or card', 'Door code', 'Reception sign-in']),
                $fill_in('Escort Required', 'yesno'),
                $fill_in('Parking'),
            ],
            'content' => '<h3>Site and Access Details - [Site]</h3>'
                . '<p><strong>Client:</strong> [Client]<br><strong>Address:</strong> [Address]<br><strong>Phone:</strong> [Site Phone]<br><strong>Site contact:</strong> [Contact], [Contact Phone]</p>'
                . '<h4>Access</h4>' . $list(['Building: [Access Method]', 'Server room or comms cupboard:', 'Alarm:', 'Notice needed before a visit:', 'Escort required: [Escort Required]'])
                . '<h4>Hours</h4><p></p>'
                . '<h4>Parking and Arrival</h4><p>[Parking]</p>'
                . '<h4>Where the Equipment Is</h4>' . $table(['Equipment', 'Where in the building', 'Notes'])
                . '<h4>Health and Safety</h4>' . $list(['Sign-in:', 'Hazards:']),
        ],
        [
            'name' => 'Microsoft 365 Tenant Details',
            'description' => 'Tenant identifiers, domains, licensing and the security baseline',
            'review_months' => 12,
            'fill_ins' => [
                $fill_in('Tenant Domain', 'text', [], true, 'The onmicrosoft.com domain'),
                $fill_in('Tenant ID'),
                $fill_in('Licensing Partner', 'text', [], false, 'Who the licenses are bought through'),
            ],
            'content' => '<h3>Microsoft 365 Tenant - [Client]</h3>'
                . '<p><strong>Tenant domain:</strong> [Tenant Domain]<br><strong>Tenant ID:</strong> [Tenant ID]<br><strong>Licensed through:</strong> [Licensing Partner]</p>'
                . '<p>Admin logins are kept in ITFlow as credentials, not here.</p>'
                . '<h4>Domains</h4><p>[Domains]</p>'
                . '<h4>Licenses</h4><p>[Licenses]</p>'
                . '<h4>Security Baseline</h4>' . $table(['Control', 'State', 'Notes'], [['MFA for every user', '', ''], ['Conditional access', '', ''], ['Legacy authentication blocked', '', ''], ['Admin accounts separate from daily ones', '', ''], ['Audit logging', '', ''], ['External sharing', '', '']])
                . '<h4>Mail</h4>' . $list(['MX:', 'SPF:', 'DKIM:', 'DMARC:', 'Filtering:'])
                . '<h4>Backup</h4>' . $list(['Product:', 'Covers mail, OneDrive, SharePoint and Teams:']),
        ],
        [
            'name' => 'Line of Business Application Profile',
            'description' => 'Everything needed to support a business critical application',
            'review_months' => 12,
            'fill_ins' => [
                $fill_in('Application', 'text', [], true),
                $fill_in('Criticality', 'list', ['Critical', 'High', 'Normal']),
                $fill_in('Application Vendor'),
                $fill_in('Hosting', 'list', ['On premises', 'Vendor hosted', 'Cloud']),
            ],
            'content' => '<h3>[Application] - Application Profile</h3>'
                . '<p><strong>Client:</strong> [Client]<br><strong>Business owner:</strong> [Contact]<br><strong>Criticality:</strong> [Criticality]</p>'
                . '<h4>Vendor and Support</h4>' . $list(['Vendor: [Application Vendor]', 'Support portal:', 'Support hours and contract level:', 'Account or customer number:'])
                . '<h4>How It Runs</h4>' . $list(['Hosting: [Hosting]', 'Servers:', 'Database:', 'Installed on:'])
                . '<h4>Licensing</h4>' . $list(['License type and count:', 'Renewal date:'])
                . '<h4>Updates</h4>' . $list(['Who applies updates:', 'Notice the vendor needs:'])
                . '<h4>Backup and Recovery</h4><p></p>'
                . '<h4>Common Issues</h4>' . $table(['Symptom', 'Fix']),
        ],
        [
            'name' => 'New Hire Onboarding Checklist',
            'description' => 'A new user set up - who they are, what they need, and done',
            'review_months' => 0,
            'fill_ins' => [
                $fill_in('Employee Name', 'text', [], true),
                $fill_in('Start Date', 'date', [], true),
                $fill_in('Job Title'),
                $fill_in('Manager'),
                $fill_in('Mirror Access From', 'text', [], false, 'Someone whose access they should match'),
                $fill_in('Device', 'list', ['New laptop', 'New desktop', 'An existing device', 'Their own device']),
            ],
            'content' => '<h3>New Hire Onboarding - [Employee Name]</h3>'
                . '<p><strong>Client:</strong> [Client]<br><strong>Start date:</strong> [Start Date]<br><strong>Requested by:</strong> [Contact]</p>'
                . '<h4>Employee</h4>' . $list(['Job title: [Job Title]', 'Department:', 'Manager: [Manager]', 'Working hours and location:'])
                . '<h4>Access</h4>' . $list(['Mirror access from: [Mirror Access From]', 'Shared mailboxes:', 'Distribution lists:', 'Applications:', 'Building access:'])
                . '<h4>Equipment</h4>' . $list(['Device: [Device]', 'Phone extension:', 'Peripherals:'])
                . '<h4>Done</h4>' . $list(['Account made', 'License assigned', 'MFA set up', 'Device built and handed over', 'Added to ITFlow as a contact']),
        ],
        [
            'name' => 'Employee Offboarding Checklist',
            'description' => 'A user removed - the written authorization, and what was done',
            'review_months' => 0,
            'fill_ins' => [
                $fill_in('Employee Name', 'text', [], true),
                $fill_in('Last Working Day', 'date', [], true),
                $fill_in('Access Removed', 'list', ['At the end of the last working day', 'Immediately', 'On another date - in the notes']),
                $fill_in('Forward Mail To'),
                $fill_in('Keep Mailbox', 'yesno', [], false, 'Kept as a shared mailbox'),
            ],
            'content' => '<h3>Employee Offboarding - [Employee Name]</h3>'
                . '<p><strong>Client:</strong> [Client]<br><strong>Last working day:</strong> [Last Working Day]<br><strong>Authorized by:</strong> [Contact]</p>'
                . '<p>This is the written authorization to remove their access.</p>'
                . '<h4>When</h4>' . $list(['Access removed: [Access Removed]'])
                . '<h4>Mail and Files</h4>' . $list(['Forward mail to: [Forward Mail To]', 'Kept as a shared mailbox: [Keep Mailbox]', 'Who takes over their files:', 'How long it is all kept:'])
                . '<h4>Equipment to Get Back</h4>' . $table(['Item', 'Asset tag or serial', 'Back on'])
                . '<h4>Done</h4>' . $list(['Sign-in blocked and sessions ended', 'Password reset', 'MFA methods removed', 'Taken out of groups and shared mailboxes', 'License removed', 'Device back and wiped', 'Shared passwords they knew changed', 'Contact archived in ITFlow'])
                . '<h4>Notes</h4><p></p>',
        ],
        [
            'name' => 'Change Record',
            'description' => 'A planned change, its impact and how to roll it back',
            'review_months' => 0,
            'fill_ins' => [
                $fill_in('Change Summary', 'text', [], true),
                $fill_in('Scheduled For', 'date'),
                $fill_in('Risk', 'list', ['Low', 'Medium', 'High']),
            ],
            'content' => '<h3>Change Record - [Change Summary]</h3>'
                . '<p><strong>Client:</strong> [Client]<br><strong>Requested by:</strong> [Contact]<br><strong>Scheduled:</strong> [Scheduled For]<br><strong>Risk:</strong> [Risk]<br><strong>Engineer:</strong> [Agent]</p>'
                . '<h4>Why</h4><p></p>'
                . '<h4>Systems Affected</h4><ul><li></li></ul>'
                . '<h4>Impact</h4>' . $list(['On users:', 'Downtime expected:', 'Who has been told:'])
                . '<h4>Plan</h4><ol><li></li></ol>'
                . '<h4>Testing</h4><p></p>'
                . '<h4>Rollback</h4><ol><li></li></ol>'
                . '<h4>Outcome</h4>' . $list(['Done:', 'Problems:']),
        ],
        [
            'name' => 'Incident Response Plan',
            'description' => 'The agreed process and who to call in a security incident',
            'review_months' => 12,
            'fill_ins' => [
                $fill_in('Cyber Insurer'),
                $fill_in('Policy Number'),
            ],
            'content' => '<h3>Incident Response Plan - [Client]</h3>'
                . '<p><strong>Approved by:</strong> [Contact]<br><strong>Last reviewed:</strong> [Date]</p>'
                . '<h4>Who to Call</h4>' . $table(['Role', 'Name', 'Contact', 'Out of hours'], [['Client decision maker', '[Contact]', '[Contact Phone]', ''], ['Technical lead', '[Agent]', '', ''], ['Cyber insurer', '[Cyber Insurer]', '', ''], ['Legal counsel', '', '', ''], ['Law enforcement', '', '', '']])
                . '<h4>Cyber Insurance</h4>' . $list(['Insurer: [Cyber Insurer]', 'Policy number: [Policy Number]', 'Claims line:', 'Has to be called before:'])
                . '<h4>Severity</h4>' . $table(['Level', 'Meaning', 'Response'], [['1', 'Business stopped, or data taken', 'Everyone, now'], ['2', 'A system or account compromised', 'Same day'], ['3', 'Suspicious, contained', 'Next business day']])
                . '<h4>First Steps</h4>' . $steps(['Contain - isolate what is affected, without wiping it.', 'Keep the evidence - logs, emails, screenshots.', 'Call the insurer before anyone else is brought in.', 'Change the credentials that may be known.', 'Record every step, with the time.'])
                . '<h4>Who Has to Be Told</h4><p></p>'
                . '<h4>The Client\'s Contacts</h4><p>[Contacts]</p>'
                . '<h4>Afterwards</h4>' . $list(['What we learned:', 'Changes to this plan:']),
        ],
        [
            'name' => 'Quarterly Business Review Notes',
            'description' => 'Standing agenda and record of a client review meeting',
            'review_months' => 0,
            'fill_ins' => [
                $fill_in('Attendees'),
            ],
            'content' => '<h3>Quarterly Business Review - [Client]</h3>'
                . '<p><strong>Period:</strong> [Quarter]<br><strong>Date:</strong> [Date]<br><strong>Attendees:</strong> [Attendees]</p>'
                . '<h4>Service</h4>' . $list(['Tickets raised and resolved:', 'Response and resolution against target:', 'What keeps coming up:'])
                . '<h4>Availability and Incidents</h4><p></p>'
                . '<h4>Security</h4>' . $list(['MFA:', 'Patching:', 'Backups and the last restore test:', 'Training:'])
                . '<h4>Licenses</h4><p>[Licenses]</p>'
                . '<h4>Hardware Due for Replacement</h4><p></p>'
                . '<h4>Recommendations</h4>' . $table(['Recommendation', 'Priority', 'Budget', 'Decision'])
                . '<h4>Actions</h4>' . $table(['Action', 'Who', 'By']),
        ],
        [
            'name' => 'Asset Inventory',
            'description' => "The client's servers, workstations, network equipment, printers and licenses on a page",
            'review_months' => 6,
            'fill_ins' => [],
            'content' => '<h3>Asset Inventory - [Client]</h3>'
                . '<p><strong>As of:</strong> [Date]<br><strong>Prepared by:</strong> [Agent]</p>'
                . '<h4>Servers</h4><p>[Servers]</p>'
                . '<h4>Workstations</h4><p>[Workstations]</p>'
                . '<h4>Network Equipment</h4><p>[Network Equipment]</p>'
                . '<h4>Printers</h4><p>[Printers]</p>'
                . '<h4>Licenses</h4><p>[Licenses]</p>'
                . '<h4>Not in ITFlow Yet</h4><ul><li></li></ul>',
        ],
        [
            'name' => 'Vendors and Escalation',
            'description' => 'Who to call about what, in what order',
            'review_months' => 12,
            'fill_ins' => [],
            'content' => '<h3>Vendors and Escalation - [Client]</h3>'
                . '<p><strong>Last reviewed:</strong> [Date]</p>'
                . '<h4>Escalation Order</h4>' . $table(['Problem', 'Call first', 'Then', 'Out of hours'], [['Internet down', '', '', ''], ['Phones down', '', '', ''], ['Line of business application', '', '', ''], ['Building or power', '', '', '']])
                . '<h4>Vendors</h4><p>[Vendors]</p>'
                . '<h4>Services</h4><p>[Services]</p>'
                . '<h4>The Client\'s Contacts</h4><p>[Contacts]</p>',
        ],
    ];

}

// ------------------------------
// starterContentProducts
// name, type, code, price, income category, description, billed (a frequency,
// or frequency:contacts per user / frequency:assets per device, empty once),
// and for hardware the client asset it becomes and its warranty in months.
// Hardware and resold SKUs come in at zero - they are quoted per deal or move
// with the vendor price list.
// ------------------------------
function starterContentProducts() {

    $products = [

        // Recurring managed services
        ['Managed Workstation', 'service', 'MS-WKS', '65.00', 'Managed Services', 'Per workstation, per month. Monitoring, patching, endpoint protection and unlimited remote support.', 'month'],
        ['Managed Server', 'service', 'MS-SRV', '195.00', 'Managed Services', 'Per server, per month. Monitoring, patching, maintenance and support.', 'month'],
        ['Managed Network Device', 'service', 'MS-NET', '45.00', 'Managed Services', 'Per switch or access point, per month. Monitoring, firmware and configuration management.', 'month'],
        ['Managed Firewall', 'service', 'MS-FW', '65.00', 'Managed Services', 'Per firewall, per month. Monitoring, firmware, rule management and subscription renewal.', 'month'],
        ['Co-Managed IT', 'service', 'MS-COMG', '35.00', 'Managed Services', 'Per seat, per month. Tooling and escalation support alongside an internal IT team.', 'month'],
        ['Help Desk Support', 'service', 'MS-HD', '45.00', 'Support', 'Per user, per month. Unlimited remote help desk during business hours.', 'month:contacts'],
        ['Virtual CIO', 'service', 'MS-VCIO', '500.00', 'Consulting', 'Per month. Strategic planning, budgeting and quarterly business reviews.', 'month'],
        ['Remote Monitoring', 'service', 'MS-RMM', '15.00', 'Monitoring', 'Per device, per month. Agent based monitoring and alerting.', 'month:assets'],
        ['Patch Management', 'service', 'MS-PATCH', '8.00', 'Monitoring', 'Per endpoint, per month. Operating system and third party patching.', 'month:assets'],

        // Security
        ['Endpoint Protection', 'service', 'SEC-EDR', '7.00', 'Security', 'Per endpoint, per month. Managed endpoint detection and response.', 'month:assets'],
        ['DNS Filtering', 'service', 'SEC-DNS', '3.00', 'Security', 'Per endpoint, per month. Malicious and category based web filtering.', 'month:assets'],
        ['Email Security', 'service', 'SEC-MAIL', '4.00', 'Security', 'Per mailbox, per month. Spam, phishing and malware filtering.', 'month'],
        ['Security Awareness Training', 'service', 'SEC-SAT', '4.00', 'Security', 'Per user, per month. Training campaigns and simulated phishing.', 'month:contacts'],
        ['Password Manager', 'service', 'SEC-PWD', '4.00', 'Security', 'Per user, per month. Managed password vault.', 'month:contacts'],
        ['Dark Web Monitoring', 'service', 'SEC-DWM', '25.00', 'Security', 'Per client, per month. Credential exposure monitoring for client domains.', 'month'],

        // Backup
        ['Managed Backup - Workstation', 'service', 'BU-WKS', '15.00', 'Backup', 'Per workstation, per month. Image based backup with offsite copy.', 'month'],
        ['Managed Backup - Server', 'service', 'BU-SRV', '75.00', 'Backup', 'Per server, per month. Image based backup with offsite copy and restore testing.', 'month'],
        ['Microsoft 365 Backup', 'service', 'BU-M365', '4.00', 'Backup', 'Per user, per month. Mail, calendar, contacts, OneDrive and SharePoint.', 'month:contacts'],
        ['Offsite Backup Storage', 'service', 'BU-STOR', '25.00', 'Backup', 'Per terabyte, per month. Offsite retention beyond the included allowance.', 'month'],

        // Cloud, web and telecom
        ['Cloud Server Hosting', 'service', 'CLD-SRV', '150.00', 'Cloud Services', 'Per virtual server, per month. Compute, storage and backup included.', 'month'],
        ['Web Hosting', 'service', 'WEB-HOST', '25.00', 'Web and Hosting', 'Per site, per month. Hosting, updates and monitoring.', 'month'],
        ['Email Hosting', 'service', 'WEB-MAIL', '5.00', 'Web and Hosting', 'Per mailbox, per month.', 'month'],
        ['Domain Registration', 'service', 'WEB-DOM', '25.00', 'Web and Hosting', 'Per domain, per year. Registration and DNS management.', 'year'],
        ['SSL Certificate', 'service', 'WEB-SSL', '95.00', 'Web and Hosting', 'Per certificate, per year. Issue, installation and renewal.', 'year'],
        ['VoIP Seat', 'service', 'TEL-SEAT', '25.00', 'Telecom Services', 'Per extension, per month. Hosted voice seat with unlimited domestic calling.', 'month'],
        ['VoIP Direct Dial Number', 'service', 'TEL-DID', '3.00', 'Telecom Services', 'Per number, per month.', 'month'],
        ['SIP Trunk Channel', 'service', 'TEL-SIP', '20.00', 'Telecom Services', 'Per concurrent call path, per month.', 'month'],

        // Licensing - priced at zero, these move with the vendor price list
        ['Microsoft 365 Business Basic', 'service', 'LIC-M365BB', '0.00', 'Licensing', 'Per user, per month. Set the price from the current vendor price list.', 'month:contacts'],
        ['Microsoft 365 Business Standard', 'service', 'LIC-M365BS', '0.00', 'Licensing', 'Per user, per month. Set the price from the current vendor price list.', 'month:contacts'],
        ['Microsoft 365 Business Premium', 'service', 'LIC-M365BP', '0.00', 'Licensing', 'Per user, per month. Set the price from the current vendor price list.', 'month:contacts'],
        ['Microsoft 365 Exchange Online Plan 1', 'service', 'LIC-M365EX', '0.00', 'Licensing', 'Per mailbox, per month. Set the price from the current vendor price list.', 'month'],
        ['Third Party Software Subscription', 'service', 'LIC-3P', '0.00', 'Software Sales', 'Generic resold subscription. Set the name and price per client.', ''],

        // Labor
        ['Remote Support', 'service', 'LAB-REM', '125.00', 'Labor', 'Per hour. Remote support outside of an agreement, billed in 15 minute increments.', ''],
        ['Onsite Support', 'service', 'LAB-ONS', '150.00', 'Labor', 'Per hour. Onsite support, one hour minimum.', ''],
        ['After Hours Support', 'service', 'LAB-AH', '225.00', 'Labor', 'Per hour. Outside business hours, one and a half times the standard rate.', ''],
        ['Weekend and Holiday Support', 'service', 'LAB-WKD', '300.00', 'Labor', 'Per hour. Weekends and public holidays, twice the standard rate.', ''],
        ['Project Engineering', 'service', 'LAB-PRJ', '165.00', 'Labor', 'Per hour. Project delivery and implementation work.', ''],
        ['Network Engineering', 'service', 'LAB-NET', '175.00', 'Labor', 'Per hour. Network, firewall and server engineering.', ''],
        ['Consulting', 'service', 'LAB-CON', '200.00', 'Consulting', 'Per hour. Advisory, design and assessment work.', ''],
        ['Data Recovery', 'service', 'LAB-REC', '175.00', 'Labor', 'Per hour. Best effort recovery, no guarantee of success.', ''],
        ['End User Training', 'service', 'LAB-TRN', '125.00', 'Training', 'Per hour. Group or one to one training.', ''],
        ['Web Development', 'service', 'LAB-WEB', '125.00', 'Web and Hosting', 'Per hour. Website build and maintenance work.', ''],
        ['Block Hours - 10 Hours', 'service', 'LAB-BLK10', '1150.00', 'Labor', 'Prepaid block of ten support hours, valid twelve months.', ''],
        ['Block Hours - 20 Hours', 'service', 'LAB-BLK20', '2200.00', 'Labor', 'Prepaid block of twenty support hours, valid twelve months.', ''],
        ['Trip Charge', 'service', 'LAB-TRIP', '45.00', 'Reimbursable Expenses', 'Per visit. Applied to onsite work outside the included travel radius.', ''],
        ['Mileage', 'service', 'LAB-MILE', '0.70', 'Reimbursable Expenses', 'Per mile beyond the included travel radius.', ''],

        // One off project and setup work
        ['Client Onboarding', 'service', 'ONB-CLIENT', '1500.00', 'Onboarding', 'One off. Discovery, documentation, tooling deployment and security baseline.', ''],
        ['Workstation Setup', 'service', 'PRJ-WKS', '150.00', 'Projects', 'Per workstation. Build, data migration and deployment.', ''],
        ['Server Deployment', 'service', 'PRJ-SRV', '1200.00', 'Projects', 'Per server. Build, configuration, migration and documentation.', ''],
        ['Firewall Deployment', 'service', 'PRJ-FW', '850.00', 'Projects', 'Per firewall. Configuration, cutover and documentation.', ''],
        ['Wireless Survey and Deployment', 'service', 'PRJ-WIFI', '950.00', 'Projects', 'Per site. Survey, installation and tuning.', ''],
        ['Email Migration', 'service', 'PRJ-MAIL', '45.00', 'Projects', 'Per mailbox. Migration, cutover and client reconfiguration.', ''],
        ['Data Migration', 'service', 'PRJ-DATA', '500.00', 'Projects', 'Per migration. File share and data platform moves.', ''],
        ['Website Build', 'service', 'PRJ-WEB', '2500.00', 'Web and Hosting', 'Per site. Design, build and launch.', ''],
        ['Equipment Disposal', 'service', 'PRJ-DISP', '25.00', 'Projects', 'Per device. Secure data destruction and certified recycling.', ''],

        // Hardware - quoted per deal, seeded at zero
        ['Desktop', 'product', 'HW-DSK', '0.00', 'Hardware Sales', 'Business class desktop. Priced per quote.', '', 'Desktop', 36],
        ['Laptop', 'product', 'HW-LAP', '0.00', 'Hardware Sales', 'Business class laptop. Priced per quote.', '', 'Laptop', 36],
        ['Docking Station', 'product', 'HW-DOCK', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Monitor', 'product', 'HW-MON', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Display', 36],
        ['Server', 'product', 'HW-SRV', '0.00', 'Hardware Sales', 'Rack or tower server. Priced per quote.', '', 'Server', 36],
        ['Network Attached Storage', 'product', 'HW-NAS', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Server', 36],
        ['Firewall Appliance', 'product', 'HW-FW', '0.00', 'Hardware Sales', 'Priced per quote, excludes subscription.', '', 'Firewall/Router', 12],
        ['Switch - 24 Port', 'product', 'HW-SW24', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Switch', 12],
        ['Switch - 48 Port', 'product', 'HW-SW48', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Switch', 12],
        ['Wireless Access Point', 'product', 'HW-AP', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Access Point', 12],
        ['Uninterruptible Power Supply', 'product', 'HW-UPS', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Other', 24],
        ['Solid State Drive', 'product', 'HW-SSD', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Memory Upgrade', 'product', 'HW-RAM', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Keyboard and Mouse', 'product', 'HW-KBM', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Headset', 'product', 'HW-HS', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Webcam', 'product', 'HW-CAM', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Printer', 'product', 'HW-PRT', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Printer', 12],
        ['Toner Cartridge', 'product', 'HW-TON', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['VoIP Handset', 'product', 'HW-PHONE', '0.00', 'Hardware Sales', 'Priced per quote.', '', 'Phone', 12],
        ['Patch Cable', 'product', 'HW-CBL', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Rack Cabinet', 'product', 'HW-RACK', '0.00', 'Hardware Sales', 'Priced per quote.', ''],
        ['Power Distribution Unit', 'product', 'HW-PDU', '0.00', 'Hardware Sales', 'Priced per quote.', ''],

    ];

    return $products;

}

// ------------------------------
// starterContentLicenseTemplates
// In the license templates file format (functions/software_template_transfer.php):
// name, version, description, type (a software type from the categories every
// install starts with), license_type, notes, seats (none - each client has its
// own), term (how often it renews, from when it was bought), vendor (as in the
// vendor templates pack, made from here when it isn't) and product (the one
// of that name in the Products & Services pack, when it is here). Who should
// have each is left to the MSP, on the template's page.
// ------------------------------
function starterContentLicenseTemplates() {

    $vendors = [];
    foreach (starterContentVendorTemplates() as $vendor) {
        $vendors[$vendor['name']] = $vendor;
    }
    $template = function ($name, $description, $type, $license_type, $term, $vendor, $product, array $notes) use ($vendors) {
        return [
            'name' => $name, 'version' => '', 'description' => $description, 'type' => $type, 'license_type' => $license_type,
            'notes' => implode("\n\n", $notes), 'seats' => null, 'term' => $term, 'vendor' => $vendors[$vendor] ?? null, 'product' => $product,
        ];
    };
    $m365_notes = [
        'Licenses are assigned to users in the Microsoft 365 admin center (admin.microsoft.com), under Billing > Licenses - each user with one takes a seat.',
        'On an annual commitment seats can be added at any time but only taken away at renewal, so true up the seats before the renewal date.',
        "The tenant's global admin login belongs in Credentials, and its domains under Domains.",
    ];

    return [

        // Productivity and email
        $template('Microsoft 365 Business Basic', 'Web and mobile Office apps, Exchange mail, Teams and OneDrive', 'Productivity Suite', 'User', '1y', 'Microsoft', 'Microsoft 365 Business Basic', $m365_notes),
        $template('Microsoft 365 Business Standard', 'Desktop Office apps, Exchange mail, Teams and OneDrive', 'Productivity Suite', 'User', '1y', 'Microsoft', 'Microsoft 365 Business Standard', $m365_notes),
        $template('Microsoft 365 Business Premium', 'Business Standard with Intune, Entra ID P1 and Defender for Business', 'Productivity Suite', 'User', '1y', 'Microsoft', 'Microsoft 365 Business Premium', array_merge($m365_notes, [
            "Devices are enrolled in Intune - keep the enrollment, compliance and conditional access policies with the client's documentation.",
        ])),
        $template('Exchange Online (Plan 1)', 'A mailbox, without the Office apps - for staff who only need mail', 'Software as a Service (SaaS)', 'User', '1y', 'Microsoft', 'Microsoft 365 Exchange Online Plan 1', [
            'Assigned to users in the Microsoft 365 admin center, under Billing > Licenses. Shared mailboxes need no license until they pass their size limit or need an archive.',
            "The tenant's global admin login belongs in Credentials.",
        ]),
        $template('Google Workspace Business Standard', 'Gmail, Drive, Meet and the Docs editors', 'Productivity Suite', 'User', '1y', 'Google', null, [
            'Licenses are assigned in the Google Admin console (admin.google.com), under Billing. Every active user takes one.',
            'On the Annual plan seats can be added at any time but not taken away until it renews; the Flexible plan is billed monthly for the users there are.',
            "The super admin login belongs in Credentials, and the client's domain under Domains.",
        ]),

        // Security
        $template('Bitdefender GravityZone Business Security', 'Endpoint protection for workstations and servers', 'Security Software', 'Device', '1y', 'Bitdefender', 'Endpoint Protection', [
            'Agents are installed from a package made in the GravityZone console - each device with an agent takes a seat.',
            'Removing the agent needs the uninstall password set in its policy - keep it in Credentials.',
        ]),
        $template('ESET PROTECT', 'Endpoint protection managed from the ESET PROTECT console', 'Security Software', 'Device', '1y', 'ESET', 'Endpoint Protection', [
            'Devices are deployed and managed from the ESET PROTECT console - each activated device takes a seat.',
            'The license is in ESET PRO (or the MSP portal); keep that login and the console login in Credentials.',
        ]),
        $template('Proofpoint Essentials', 'Email security - spam, phishing and malware filtering in front of the mailboxes', 'Software as a Service (SaaS)', 'User', '1y', 'Proofpoint', 'Email Security', [
            "Licensed per protected mailbox. The client's MX records point at Proofpoint, and the mail server only takes mail from it - keep the connector settings with the client's documentation.",
            'Users added in the Proofpoint console, or synced from the directory, take a seat.',
        ]),
        $template('Bitwarden Teams', 'Shared password vault for the business', 'Software as a Service (SaaS)', 'User', '1y', 'Bitwarden', 'Password Manager', [
            "Each member of the client's organization takes a seat. Collections decide who sees which logins.",
            "The organization owner's login, and its recovery code, belong in Credentials.",
        ]),
        $template('Fortinet FortiGate Security Bundle', 'Threat protection, web filtering and support for a FortiGate firewall', 'Security Software', 'Device', '1y', 'Fortinet', null, [
            "Registered to the firewall's serial number in FortiCare - one per firewall. When it lapses the firewall keeps passing traffic, but its threat and web filtering signatures stop updating.",
            'Renewal is by the contract code applied in FortiCare - keep the FortiCare login in Credentials.',
        ]),

        // Backup
        $template('Veeam Backup & Replication', 'Image based backup and replication for servers and virtual machines', 'System Software', 'Device', '1y', 'Veeam', 'Managed Backup - Server', [
            'Licensed per protected workload - each server or virtual machine backed up takes an instance. The license file is installed on the backup server.',
            'Keep the Veeam account login, and the encryption password for the backup jobs, in Credentials - without it encrypted backups cannot be restored.',
        ]),
        $template('Veeam Backup for Microsoft 365', "Backup of the client's Exchange Online, OneDrive, SharePoint and Teams", 'System Software', 'User', '1y', 'Veeam', 'Microsoft 365 Backup', [
            'Licensed per protected user. It reaches the tenant through an app registration in Entra ID, and backs up to its own repository.',
            "Keep the repository location and the app registration with the client's documentation.",
        ]),
        $template('Acronis Cyber Protect Cloud', 'Backup and protection for workstations and servers, billed on use', 'System Software', 'Usage-based', '1m', 'Acronis', 'Managed Backup - Workstation', [
            'Billed monthly on what is protected and stored, from the Acronis partner portal - the seats here are what was protected at the last count.',
            'Agents are installed from the client tenant in the Acronis console.',
        ]),
        $template('Synology Active Backup for Business', 'Backup of PCs, servers and Microsoft 365 to a Synology NAS', 'System Software', 'Site', '', 'Synology', null, [
            'Comes with a Synology NAS - there is no key and nothing to renew. Agents installed on each device back up to the NAS.',
            "Keep the NAS login in Credentials, and the NAS itself in the client's assets.",
        ]),

        // Business applications
        $template('Adobe Acrobat Pro', 'Create, edit, sign and convert PDFs', 'Desktop Application', 'User', '1y', 'Adobe', null, [
            'Named user licensing - assigned to users in the Adobe Admin Console (adminconsole.adobe.com). A user signs in to Acrobat to use it.',
        ]),
        $template('Adobe Creative Cloud All Apps', 'Photoshop, Illustrator, InDesign and the rest of the Creative Cloud apps', 'Desktop Application', 'User', '1y', 'Adobe', null, [
            'Named user licensing - assigned to users in the Adobe Admin Console. Each user can sign in on two computers at once.',
        ]),
        $template('QuickBooks Online Plus', 'Accounting, invoicing and bill paying', 'Software as a Service (SaaS)', 'Subscription', '1m', 'Intuit', null, [
            'Usually the client\'s own subscription, billed to them by Intuit each month - the plan sets how many users it has.',
            "The primary admin is normally the client's owner or accountant; note who it is here.",
        ]),
        $template('Zoom Workplace Pro', 'Meetings, chat and whiteboards', 'Software as a Service (SaaS)', 'User', '1y', 'Zoom', null, [
            "Licensed users are assigned in the Zoom admin portal; basic users don't take a seat.",
        ]),

        // Operating systems
        $template('Windows Server Standard', 'The server operating system, licensed by core', 'Operating System', 'Perpetual', '', 'Microsoft', null, [
            'Licensed by the physical cores in the server - at least 8 cores per processor and 16 per server. Standard covers two virtual machines on the licensed server; licensing its cores again covers two more.',
            'Users and devices that use the server need Windows Server CALs as well.',
        ]),
        $template('Windows Server CAL', 'Client access licenses for users or devices that use a Windows Server', 'Operating System', 'User', '', 'Microsoft', null, [
            'One per user, or per device, that reaches a Windows Server - not per server. Remote Desktop sessions need Remote Desktop Services CALs on top.',
        ]),

    ];

}

// ------------------------------
// starterContentKbArticles
// How-tos and fixes for an MSP's clients - name (the title), description (the
// line under it), category (a Knowledge Base category of the Categories
// pack), tags (Knowledge Base article tags of the Tags pack), review_months
// and content (HTML). Written as the MSP, to its clients' staff, for
// Microsoft 365 and Windows as most clients have them - each says where to
// put what yours differs in. [Portal] and [Contact us] are filled in as each
// comes in (starterKbFill()).
// ------------------------------
function starterContentKbArticles() {

    $steps = function (array $items) {
        return '<ol><li>' . implode('</li><li>', $items) . '</li></ol>';
    };
    $list = function (array $items) {
        return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
    };
    $article = function ($name, $description, $category, array $tags, $content, $review_months = 12) {
        return ['name' => $name, 'description' => $description, 'category' => $category, 'tags' => $tags, 'review_months' => $review_months, 'content' => $content . '[Contact us]'];
    };

    return [

        // Getting Help
        $article('Getting help from the help desk', 'How to reach us, what to tell us so we can help straight away, and what happens next.', 'Getting Help', ['How-To'],
            '<p>The quickest way to get help is to open a ticket in [Portal] - it reaches the whole team, and you can follow it as we work on it. Email works too, and for anything stopping you from working, call us.</p>'
            . '<h5>Tell us</h5>' . $list([
                'What you were trying to do, and what happened instead - the exact wording of any error helps a lot (a screenshot is even better)',
                'When it started, and whether anything changed around then',
                'Whether it is just you, or others too',
                'The best number to reach you on, and when you are free',
            ])
            . '<h5>What happens next</h5><p>You get an email with the ticket\'s number straight away. Reply to that email to add anything - keep the number in the subject so it lands on the same ticket. We let you know when someone has picked it up, when we need something from you, and when it is fixed.</p>'
            . '<p>If something is urgent - you can\'t work, or several people are affected - say so, and call as well as opening the ticket.</p>'),

        $article('Raising a ticket in the client portal', 'Sign in to the client portal, open a ticket and follow it as we work on it.', 'Getting Help', ['How-To', 'Self-Service'],
            '<p>The client portal is where you open tickets, see what is happening with them and reply to us. It is quicker than email, and nothing gets lost in an inbox.</p>'
            . $steps([
                'Go to [Portal] and sign in with your work email address. The first time, use <strong>Forgot your password?</strong> to set one.',
                'Choose <strong>New Ticket</strong>.',
                'Give it a subject that says what is wrong - "Outlook won\'t open on my laptop" helps more than "Problem".',
                'Say what happened, when it started and anything you have tried. Add a screenshot if you have one.',
                'Choose how urgent it is, then <strong>Submit</strong>.',
            ])
            . '<p>While you type the subject, the portal suggests articles that might answer it - worth a look, you may not have to wait for us at all.</p>'
            . '<p>Your tickets are under <strong>Tickets</strong>. Open one to see where it is up to and to reply. When we mark it resolved, you can reply to reopen it if it isn\'t fixed.</p>'),

        // Accounts and Passwords
        $article('Changing or resetting your Microsoft 365 password', 'Change your password yourself, reset it when you have forgotten it, and what to do when you are locked out.', 'Accounts and Passwords', ['How-To', 'Self-Service', 'Microsoft 365'],
            '<h5>Change it while you know it</h5>' . $steps([
                'Sign in at <a href="https://myaccount.microsoft.com">myaccount.microsoft.com</a>.',
                'Choose <strong>Password</strong> (or <strong>Change password</strong>).',
                'Type your old password, then the new one twice, and save.',
            ])
            . '<h5>Forgotten it</h5>' . $steps([
                'Go to <a href="https://passwordreset.microsoftonline.com">passwordreset.microsoftonline.com</a>.',
                'Enter your work email address and the characters shown.',
                'Prove it is you with your authenticator app, a text or a call, as you set up when you registered.',
                'Choose a new password.',
            ])
            . '<p>On a work computer, sign in with the new password straight away. Your phone and other devices will ask for it next time they check email - type it in when they do.</p>'
            . '<h5>Locked out</h5><p>After too many wrong attempts your account locks for a while. Wait 15 minutes and try again, or use the reset above. If self-service reset isn\'t set up for you, we can reset it - we will check it is really you first, so expect a call back.</p>'
            . '<p>Never send your password to anyone by email or in a ticket - we will never ask for it.</p>'),

        $article('Setting up multi-factor authentication', 'Register the Microsoft Authenticator app so signing in needs your phone as well as your password.', 'Accounts and Passwords', ['How-To', 'Security', 'Microsoft 365', 'Mobile'],
            '<p>Multi-factor authentication (MFA) means that signing in takes your password and a tap on your phone. Even if someone gets your password, they can\'t get in without your phone.</p>'
            . '<h5>You need</h5>' . $list(['Your phone, with the <strong>Microsoft Authenticator</strong> app installed from the App Store or Google Play', 'A computer, signed in to your work account'])
            . '<h5>Set it up</h5>' . $steps([
                'On the computer, go to <a href="https://aka.ms/mfasetup">aka.ms/mfasetup</a> and sign in.',
                'When asked to keep your account secure, choose <strong>Next</strong> until a QR code shows.',
                'In the Authenticator app, tap <strong>+</strong>, choose <strong>Work or school account</strong>, then <strong>Scan a QR code</strong>, and scan it.',
                'Back on the computer, choose <strong>Next</strong>. A test notification arrives on your phone - type the number shown on screen into the app and approve it.',
                'Add your phone number as a backup method when asked.',
            ])
            . '<p>From now on, when you sign in somewhere new you are asked to approve it on your phone.</p>'
            . '<p><strong>Only approve a sign-in you have just started yourself.</strong> If your phone asks you to approve one you didn\'t start, deny it and tell us straight away - someone has your password.</p>'),

        $article('Moving your authenticator to a new phone', 'Get multi-factor sign-in onto a new phone before you wipe or trade in the old one.', 'Accounts and Passwords', ['How-To', 'Mobile', 'Microsoft 365'],
            '<p>Do this while you still have the old phone - without it, you won\'t be able to sign in until we reset your methods.</p>'
            . $steps([
                'Install <strong>Microsoft Authenticator</strong> on the new phone.',
                'On a computer, go to <a href="https://mysignins.microsoft.com/security-info">mysignins.microsoft.com/security-info</a> and sign in, approving on the old phone.',
                'Choose <strong>Add sign-in method</strong>, then <strong>Authenticator app</strong>, and follow the steps with the new phone to scan the QR code.',
                'Make the new phone the default method if asked.',
                'Delete the old phone\'s entry from the list, then remove the account from the app on the old phone.',
                'If your phone number changed, update it on the same page.',
            ])
            . '<h5>Already lost or wiped the old phone</h5><p>Contact us. We will check it is really you, clear your old methods, and you set the new phone up the next time you sign in.</p>'),

        $article('Choosing a strong password and using a password manager', 'How to pick passwords that are hard to guess and easy to remember, and why a password manager helps.', 'Accounts and Passwords', ['Security'],
            '<p>Most account break-ins use passwords that were guessed or leaked from another website. Two habits stop nearly all of them.</p>'
            . '<h5>Use a passphrase</h5><p>Three or four random words make a password that is long, strong and easy to remember - <em>copper-lantern-river-oak</em> is far harder to crack than <em>P@ssw0rd1</em>. Don\'t use names, dates, or anything someone could find on your social media.</p>'
            . '<h5>Never reuse it</h5><p>Every website gets its own password. When one site is breached, criminals try the same email and password everywhere else.</p>'
            . '<h5>Let a password manager remember them</h5>' . $list([
                'It remembers a different strong password for every site, and fills them in for you',
                'You only remember one passphrase - the one that unlocks it',
                'It only fills a password in on the real website, so it helps you spot fake ones',
            ])
            . '<p>Ask us which password manager we support for your business before you put work passwords in a personal one.</p>'),

        // Security
        $article('Spotting and reporting a phishing email', 'The signs of a phishing email, and how to report one without clicking anything.', 'Security', ['Security', 'How-To'],
            '<p>Phishing emails pretend to be from someone you trust to get you to click a link, open an attachment, enter your password or pay something.</p>'
            . '<h5>Warning signs</h5>' . $list([
                'It is urgent or threatening - your account will be closed, a payment is overdue, act now',
                'The sender\'s address doesn\'t match who it says it is from - hover over the name to see the real address',
                'A link\'s address, shown when you hover over it, goes somewhere unexpected',
                'It asks you to sign in, pay, change bank details or buy gift cards',
                'An attachment you weren\'t expecting, even from someone you know',
                'A warning banner saying it is from outside the company, when it claims to be from inside',
            ])
            . '<h5>Report it</h5>' . $steps([
                'Don\'t click links, open attachments or reply.',
                'In Outlook, use <strong>Report</strong> (or <strong>Report Phishing</strong>) on the toolbar - or forward it to us as an attachment.',
                'Delete it.',
            ])
            . '<p>Not sure? Ask us before you click - we would much rather check ten genuine emails than clean up after one bad one. If someone asks you to change bank details or make an urgent payment, call them on a number you already have to check.</p>'),

        $article('I clicked a link or entered my password - what now?', 'What to do straight away if you think you fell for a phishing email.', 'Security', ['Security', 'Quick Fix'],
            '<p>It happens to everyone. Acting quickly makes the difference, so don\'t wait to be sure.</p>'
            . $steps([
                'If you typed your password into a page, change it now - see <em>Changing or resetting your Microsoft 365 password</em>. Change it anywhere else you used the same one.',
                'If you opened an attachment or ran something, unplug the network cable or turn off Wi-Fi on that computer, but leave it switched on.',
                'Call us straight away and tell us what you clicked, when, and what happened. Keep the email - don\'t delete it.',
                'Deny any multi-factor sign-in request you didn\'t start.',
            ])
            . '<p>We will check your account for anything changed - forwarding rules, new sign-ins, sent email - and tell you if anything else needs doing. Nobody is in trouble for reporting it; the only mistake is keeping quiet.</p>'),

        $article('Lost or stolen laptop or phone', 'What to do straight away when a device with work email or files on it goes missing.', 'Security', ['Security'],
            '<p>Tell us as soon as you notice - day or night. The sooner we know, the less anyone can do with it.</p>'
            . $steps([
                'Call us, and say which device it is and when you last had it.',
                'If it was stolen, report it to the police and keep the reference number.',
                'Change your Microsoft 365 password from another device.',
            ])
            . '<p>We sign the device out of your work account, and for devices we manage we can lock or wipe it remotely. If it turns up, let us know before you use it again.</p>'),

        // Devices
        $article('Keeping your computer up to date', 'Why updates matter, what the restart reminders mean, and how to get them done without losing work.', 'Devices', ['How-To', 'Self-Service'],
            '<p>Updates fix security holes that attackers use. We send them out to managed computers and schedule most of them for outside working hours - but many only finish when the computer restarts.</p>'
            . '<h5>When it asks you to restart</h5>' . $list([
                'Save your work and restart at the end of the day, or choose a time that suits you',
                'Don\'t keep putting it off - after a few days it restarts on its own',
                'Leave the computer on and plugged in at night when you can - most updates run then',
            ])
            . '<h5>To check yourself</h5><p>On Windows, open <strong>Settings &gt; Windows Update</strong> and choose <strong>Check for updates</strong>. On a Mac, open <strong>System Settings &gt; General &gt; Software Update</strong>.</p>'
            . '<p>Keep your phone and tablet up to date too - install updates when they appear.</p>'),

        $article('Restarting your computer properly', 'Why restarting fixes so much, and why shutting down isn\'t the same.', 'Devices', ['Quick Fix', 'Troubleshooting'],
            '<p>A restart clears out programs that have stopped responding, finishes updates and reconnects to the network. It fixes a surprising number of problems, so it is the first thing to try.</p>'
            . $steps([
                'Save your work and close your programs.',
                'Choose <strong>Start</strong>, the power button, then <strong>Restart</strong> - not Shut down.',
                'Wait for it to start again and sign in.',
            ])
            . '<p>On Windows, <strong>Shut down</strong> keeps part of the system as it was so it starts faster - it doesn\'t clear things out the way <strong>Restart</strong> does. Closing a laptop\'s lid only puts it to sleep.</p>'
            . '<p>If it still isn\'t right after a restart, tell us what you were doing and what you see - and that you have restarted, which saves a step.</p>'),

        // Email and Calendar
        $article('Setting an out of office reply', 'Tell people who email you that you are away, in Outlook or on the web.', 'Email and Calendar', ['How-To', 'Self-Service', 'Microsoft 365'],
            '<h5>Outlook on the web, or the new Outlook</h5>' . $steps([
                'Open <strong>Settings</strong> (the gear), then <strong>Account &gt; Automatic replies</strong>.',
                'Turn on <strong>Turn on automatic replies</strong>.',
                'Tick <strong>Send replies only during a time period</strong> and choose when you leave and when you are back.',
                'Write the message - one for people in your company, and one for everyone else if you want it to differ.',
                'Choose <strong>Save</strong>.',
            ])
            . '<h5>Classic Outlook on Windows</h5><p>Choose <strong>File &gt; Automatic Replies</strong>, then as above.</p>'
            . '<p>Say when you are back and who to contact meanwhile - but keep personal details, like being on holiday abroad, out of the reply that goes outside the company.</p>'),

        $article('Sharing your calendar or opening someone else\'s', 'Let colleagues see when you are free, and add their calendars beside yours.', 'Email and Calendar', ['How-To', 'Microsoft 365'],
            '<h5>Share yours</h5>' . $steps([
                'In Outlook, open your calendar.',
                'Choose <strong>Share</strong> (or right-click your calendar and choose <strong>Sharing permissions</strong>).',
                'Add the person, and choose what they see - just when you are busy, the titles and places, or everything - or let them edit it.',
                'Choose <strong>Share</strong>. They get an email to accept.',
            ])
            . '<h5>Open someone else\'s</h5><p>In your calendar choose <strong>Add calendar</strong>, then <strong>Add from directory</strong> (or <strong>Open shared calendar</strong>), and pick the person. You see what they have shared with you.</p>'
            . '<p>To book a meeting without needing their calendar, use the <strong>Scheduling Assistant</strong> in a new meeting - it shows when everyone is free.</p>'),

        $article('Adding a shared mailbox in Outlook', 'Open a shared mailbox like info@ or accounts@ alongside your own.', 'Email and Calendar', ['How-To', 'Microsoft 365'],
            '<p>Once we have given you access, a shared mailbox usually appears in Outlook on its own within an hour or so - restart Outlook if it hasn\'t.</p>'
            . '<h5>If it doesn\'t appear</h5><p><strong>Outlook on the web or the new Outlook:</strong> right-click <strong>Folders</strong> in the folder list, choose <strong>Add shared folder or mailbox</strong> and type the mailbox\'s address.</p>'
            . '<p><strong>Classic Outlook on Windows:</strong> choose <strong>File &gt; Account Settings &gt; Account Settings</strong>, select your account, <strong>Change &gt; More Settings &gt; Advanced</strong>, then <strong>Add</strong> the mailbox\'s address.</p>'
            . '<h5>Sending as the shared mailbox</h5><p>In a new email, show the <strong>From</strong> field (under <strong>Options</strong>), choose <strong>Other email address</strong> and pick the shared mailbox. If you get an error saying you don\'t have permission, ask us - sending as a mailbox is a separate permission from reading it.</p>'
            . '<p>Need access to a shared mailbox you don\'t have? Open a ticket saying which one - we check with whoever looks after it first.</p>'),

        $article('Outlook keeps asking for your password or won\'t sync', 'Quick fixes when Outlook prompts for a password over and over, or new email stops arriving.', 'Email and Calendar', ['Troubleshooting', 'Microsoft 365'],
            '<p>Try these in order, checking after each one.</p>'
            . $steps([
                'Check you can sign in at <a href="https://outlook.office.com">outlook.office.com</a>. If you can\'t, your password may have expired or your account may be locked - see <em>Changing or resetting your Microsoft 365 password</em>.',
                'If you changed your password recently, type the new one when Outlook asks.',
                'Close Outlook completely and open it again.',
                'Restart the computer.',
                'Check the bottom of the Outlook window - <strong>Working Offline</strong> means it isn\'t connecting. On the <strong>Send / Receive</strong> tab, turn <strong>Work Offline</strong> off.',
                'Check other websites load - if not, it is your internet connection, not Outlook.',
            ])
            . '<p>If email works on the web but not in Outlook, tell us - your Outlook profile may need rebuilding, which we can do remotely.</p>'),

        // Files and Sharing
        $article('Sending large files securely', 'Share files too big for email, or too sensitive for an attachment, with a OneDrive link.', 'Files and Sharing', ['How-To', 'Security', 'Microsoft 365'],
            '<p>Email attachments are limited in size and stay in inboxes forever. A sharing link from OneDrive is better for both: no size limit, and you can stop sharing at any time.</p>'
            . $steps([
                'Save the file to OneDrive (or find it in SharePoint or Teams).',
                'Right-click it and choose <strong>Share</strong>.',
                'Choose who the link works for - <strong>Specific people</strong> is safest - and type their email addresses.',
                'Untick <strong>Allow editing</strong> unless they need to change it, and set an expiry date for anything sensitive.',
                'Choose <strong>Send</strong>, or <strong>Copy link</strong> to paste it into your own email.',
            ])
            . '<p>To stop sharing later, choose <strong>Manage access</strong> on the file and remove the link.</p>'
            . '<p>For bank details, ID documents or health information, check with us how your business has agreed to send them before you do.</p>'),

        $article('Syncing SharePoint and Teams files to your computer', 'Work on shared files from File Explorer, the same as files on your computer.', 'Files and Sharing', ['How-To', 'Microsoft 365'],
            $steps([
                'In Teams, open the channel and its <strong>Files</strong> tab, and choose <strong>Open in SharePoint</strong> - or go to the SharePoint site directly.',
                'Choose <strong>Sync</strong> (or <strong>Add shortcut to My files</strong>, which works on every computer you sign in to).',
                'Allow the browser to open OneDrive if it asks.',
                'The library appears in File Explorer under your organization\'s name, or in your OneDrive.',
            ])
            . '<h5>What the icons mean</h5>' . $list([
                'A cloud - the file is online only, and downloads when you open it',
                'A green tick - it is on this computer too',
                'Red with a cross - it didn\'t sync. Click the OneDrive cloud by the clock to see why',
            ])
            . '<p>Two people editing the same Word, Excel or PowerPoint file at once is fine - open it from the shared library rather than a copy you emailed around.</p>'),

        $article('Getting back a deleted or overwritten file', 'Restore a file from the recycle bin, or an earlier version of one, from OneDrive or SharePoint.', 'Files and Sharing', ['How-To', 'Self-Service', 'Microsoft 365'],
            '<h5>Deleted</h5>' . $steps([
                'Go to <a href="https://www.office.com">office.com</a>, open OneDrive (or the SharePoint site the file was in).',
                'Choose <strong>Recycle bin</strong>.',
                'Select the file and choose <strong>Restore</strong>.',
            ])
            . '<p>Files stay in the recycle bin for 93 days.</p>'
            . '<h5>Saved over by mistake</h5>' . $steps([
                'Find the file in OneDrive or SharePoint on the web.',
                'Right-click it and choose <strong>Version history</strong>.',
                'Find the version from before the change and choose <strong>Restore</strong>.',
            ])
            . '<p>Can\'t find it, or it was on a shared drive or a computer rather than OneDrive? Tell us the file\'s name, where it was and roughly when it was last right - we can restore it from backup.</p>'),

        // Remote Work
        $article('Connecting to the VPN when you work away from the office', 'Reach the office\'s shared drives and systems securely from home or on the road.', 'Remote Work', ['How-To'],
            '<p>The VPN connects your work computer to the office network over the internet, securely. You need it for shared drives and systems that only run in the office - email, Teams and OneDrive work without it.</p>'
            . $steps([
                'Connect to the internet first - home Wi-Fi, or your phone\'s hotspot.',
                'Open the VPN app from the system tray by the clock, or from the Start menu.',
                'Choose <strong>Connect</strong> and sign in with your work account, approving it on your phone.',
                'When it says connected, open your shared drives as usual.',
            ])
            . '<p>Disconnect when you have finished, especially on a hotel or other shared Wi-Fi.</p>'
            . '<h5>If it won\'t connect</h5>' . $list(['Check other websites load', 'Disconnect, wait a minute and connect again', 'Restart the computer', 'Tell us the exact error message - a screenshot is best'])
            . '<p><em>Your VPN\'s name, and anything different about it, go here.</em></p>'),

        $article('Working securely from home or on the road', 'Simple habits that keep work data safe away from the office.', 'Remote Work', ['Security', 'Policy'],
            $list([
                'Lock your screen whenever you step away - press <strong>Windows + L</strong>, or <strong>Control + Command + Q</strong> on a Mac',
                'Use your work computer for work, and don\'t let family members use it',
                'Keep work files in OneDrive or SharePoint, not on the desktop or a USB stick',
                'On public or hotel Wi-Fi, connect to the VPN, or use your phone\'s hotspot instead',
                'Don\'t take calls about sensitive matters where others can hear, and watch who can see your screen',
                'Keep your home router updated and change its default password',
                'Print work documents only when you need to, and shred them afterwards',
            ])
            . '<p>If a work device is lost or stolen, tell us straight away - see <em>Lost or stolen laptop or phone</em>.</p>'),

        // Printing and Scanning
        $article('Adding a printer', 'Add an office printer to your computer, or add it again after it disappears.', 'Printing and Scanning', ['How-To'],
            '<p>On managed computers the office printers are added for you. If one is missing:</p>'
            . '<h5>Windows</h5>' . $steps([
                'Open <strong>Settings &gt; Bluetooth &amp; devices &gt; Printers &amp; scanners</strong>.',
                'Choose <strong>Add device</strong> and wait for the list.',
                'Choose the printer by its name - often written on a label on the printer - and <strong>Add device</strong>.',
            ])
            . '<h5>Mac</h5><p>Open <strong>System Settings &gt; Printers &amp; Scanners</strong>, choose <strong>Add Printer, Scanner or Fax</strong>, pick it from the list and choose <strong>Add</strong>.</p>'
            . '<p>If it isn\'t listed, make sure you are on the office network (not guest Wi-Fi), then tell us the printer\'s name and where it is.</p>'),

        $article('Printer not printing', 'Quick checks before you open a ticket about a printer.', 'Printing and Scanning', ['Troubleshooting', 'Quick Fix'],
            $steps([
                'Look at the printer\'s screen - paper jams, empty trays and low toner show there.',
                'Check you sent it to the right printer - the name is in the print window.',
                'Open the print queue - <strong>Settings &gt; Printers &amp; scanners</strong>, the printer, <strong>Open print queue</strong> - and cancel anything stuck at the top.',
                'Turn the printer off, wait 30 seconds and turn it on again.',
                'Restart your computer.',
                'Try printing from another computer - if that works, the problem is on yours.',
            ])
            . '<p>Still not printing? Tell us the printer\'s name, what its screen says, and whether it is just you or everyone.</p>'),

        $article('Scanning to email or a folder', 'Scan documents on the office copier straight to your inbox or a shared folder.', 'Printing and Scanning', ['How-To'],
            $steps([
                'Put the pages face up in the feeder on top, or face down on the glass.',
                'On the copier\'s screen, choose <strong>Scan</strong> (or <strong>Send</strong>).',
                'Choose <strong>Email</strong> and pick yourself from the address book - or choose the folder you scan to.',
                'Check the settings - PDF, color or black and white, one or both sides.',
                'Press <strong>Start</strong>.',
            ])
            . '<p>The scan arrives in a minute or two. If it doesn\'t, check your junk email folder, then tell us the copier\'s name and the time you scanned.</p>'
            . '<p><em>Your copier\'s name, and where scans to a folder land, go here.</em></p>'),

        // Phones and Meetings
        $article('Fixing sound and video in Teams meetings', 'What to check when people can\'t hear or see you in a Teams meeting, or you can\'t hear them.', 'Phones and Meetings', ['Troubleshooting', 'Microsoft 365'],
            '<h5>Before you join</h5><p>On the joining screen, check the camera preview and choose <strong>Device settings</strong> (the gear) to pick the right microphone, speaker and camera - a headset, not the laptop\'s own, if you use one.</p>'
            . '<h5>In the meeting</h5>' . $steps([
                'Check you aren\'t muted in Teams, and that your headset doesn\'t have its own mute switch on.',
                'Choose <strong>More &gt; Settings &gt; Device settings</strong> and check the microphone and speaker - make a test call from there.',
                'Unplug the headset and plug it in again, then pick it again in Device settings.',
                'Close other apps that might be using the camera or microphone.',
                'Leave the meeting and join again.',
            ])
            . '<p>Choppy sound or frozen video is usually the connection - turn your camera off, move closer to the Wi-Fi, or plug in a network cable.</p>'),

        $article('Setting up voicemail and forwarding on your desk phone', 'Record your voicemail greeting, check messages and forward calls when you are away from your desk.', 'Phones and Meetings', ['How-To', 'Self-Service'],
            '<h5>Voicemail</h5>' . $steps([
                'Press the <strong>Messages</strong> (envelope) button, or dial your voicemail number.',
                'Enter your PIN - the first time, use the one we gave you and choose a new one when asked.',
                'Follow the prompts to record your name and greeting.',
            ])
            . '<h5>Forwarding</h5><p>Press <strong>Forward</strong> on the phone\'s screen, enter the number to forward to, and confirm. Press it again to turn it off. Some systems let you forward from the phone\'s web portal or app instead.</p>'
            . '<p>Forgotten your voicemail PIN, or want calls to ring your mobile too? Open a ticket - we can set both up.</p>'
            . '<p><em>Your phone system\'s voicemail number and the exact buttons go here.</em></p>'),

        // Network and Wi-Fi
        $article('Connecting to the office or guest Wi-Fi', 'Which Wi-Fi to use for work devices, and for visitors and personal phones.', 'Network and Wi-Fi', ['How-To'],
            '<p>The office has two Wi-Fi networks, kept apart on purpose:</p>'
            . $list([
                '<strong>The staff network</strong> - for work computers and work phones. It reaches the printers and shared drives. Managed devices connect to it on their own.',
                '<strong>The guest network</strong> - for visitors and personal phones. It reaches the internet only.',
            ])
            . '<p>Please don\'t connect personal devices to the staff network, or give its password to visitors. The guest network\'s password is posted at reception, and changes from time to time.</p>'
            . '<h5>Not connecting</h5>' . $list(['Turn Wi-Fi off and on again', 'Choose the network, <strong>Forget</strong> it, and connect again with the password', 'Restart the device', 'Try a spot nearer the access point - is it just you, or everyone in that area?'])
            . '<p><em>Your network names go here.</em></p>'),

        $article('Slow or no internet - checks before you call', 'Work out whether it is your computer, the Wi-Fi or the office\'s connection.', 'Network and Wi-Fi', ['Troubleshooting', 'Quick Fix'],
            $steps([
                'Try a couple of different websites - is it everything, or one site? One site may be down for everyone.',
                'Ask a colleague - is it just you, or everyone? If everyone, tell us straight away.',
                'If it is just you, turn Wi-Fi off and on, or unplug and replug the network cable.',
                'Restart your computer.',
                'At home, restart your router: unplug it, wait 30 seconds and plug it back in. Give it a few minutes.',
            ])
            . '<p>If the whole office is down, we usually know already from our monitoring - but call so we know it affects you. Working from home and only your connection is slow? That is your internet provider, but tell us if it stops you working and we will help.</p>'),

        // Policies
        $article('New starters and leavers', 'What we need to know before someone joins or leaves, and how far ahead.', 'Policies', ['Policy', 'New Starter'],
            '<h5>Someone joining</h5><p>Let us know at least five working days before their first day - longer if they need a new computer, which can take a couple of weeks to arrive. Tell us:</p>'
            . $list([
                'Their name, job title and start date',
                'Who they report to, and which team they are in',
                'Who they will work like - we give them the same access, mailboxes and software',
                'Whether they need a computer, phone or desk phone, and where they will sit',
            ])
            . '<h5>Someone leaving</h5><p>Tell us as soon as you know their last day, and straight away if they are leaving sooner than planned. Say:</p>'
            . $list([
                'Their last day, and when their access should stop - for an awkward exit, ask us to stop it at a set time',
                'Who should get their email and files, and for how long',
                'Which equipment is coming back, and when',
            ])
            . '<p>Only the people allowed to ask for these changes should - we will check with you if a request comes from someone else.</p>'),

        $article('Acceptable use of work computers and accounts', 'The ground rules for using company computers, email and accounts - a starting point for your own policy.', 'Policies', ['Policy'],
            '<p><em>A starting point: make it match your organization\'s own policy before publishing it.</em></p>'
            . $list([
                'Work computers, phones and accounts are for work. Reasonable personal use is fine, as long as it doesn\'t get in the way of work or break these rules.',
                'Your account is yours alone. Don\'t share your password, or let anyone else use your account or approve sign-ins for them.',
                'Only install software we have approved - ask us first.',
                'Keep work files in the company\'s systems, not on personal accounts, personal devices or USB sticks.',
                'Lock your screen when you step away.',
                'Report anything suspicious straight away - a strange email, a sign-in request you didn\'t start, a lost device. You won\'t be in trouble for reporting it.',
                'What you send and store on company systems isn\'t private, and may be monitored to keep them secure.',
            ])),

    ];

}
