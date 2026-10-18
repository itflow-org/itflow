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
            'description' => 'Expense, income, referral and ticket categories for an MSP. Income categories are also what products are filed under.',
        ],
        'tags' => [
            'label' => 'Tags',
            'icon' => 'fa-tags',
            'description' => 'Tags for clients, locations, contacts, credentials, assets, vendors, domains, licenses, services, networks, documents and tickets.',
        ],
        'ticket_templates' => [
            'label' => 'Ticket Templates',
            'icon' => 'fa-life-ring',
            'description' => 'Onboarding, offboarding, deployments, maintenance, incident response and client requests - each with its tasks, what it asks for and how it sets the ticket up, and a request form for the client portal on the ones clients raise.',
        ],
        'project_templates' => [
            'label' => 'Project Templates',
            'icon' => 'fa-project-diagram',
            'description' => 'Common MSP projects, built out of the ticket templates above.',
            'requires' => 'ticket_templates',
        ],
        'vendor_templates' => [
            'label' => 'Vendor Templates',
            'icon' => 'fa-building',
            'description' => 'Vendors most clients end up with. Account numbers and support numbers are left blank.',
        ],
        'document_templates' => [
            'label' => 'Document Templates',
            'icon' => 'fa-file-alt',
            'description' => 'Runbooks, build sheets, checklists and plans as fill-in-the-blank skeletons.',
        ],
        'products' => [
            'label' => 'Products & Services',
            'icon' => 'fa-cubes',
            'description' => 'Recurring services, labor rates, project work and hardware lines. Prices are starting points - hardware and resold SKUs come in at zero.',
            'requires' => 'categories',
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
// anything, which is what the page uses to show the missing column.
// Returns the number of top level rows added (or that would be added).
// ------------------------------
function starterContentLoad($mysqli, $pack, $dry_run = false) {
    switch ($pack) {
        case 'categories':
            return starterLoadCategories($mysqli, $dry_run);
        case 'tags':
            return starterLoadTags($mysqli, $dry_run);
        case 'ticket_templates':
            return starterLoadTicketTemplates($mysqli, $dry_run);
        case 'project_templates':
            return starterLoadProjectTemplates($mysqli, $dry_run);
        case 'vendor_templates':
            return starterLoadVendorTemplates($mysqli, $dry_run);
        case 'document_templates':
            return starterLoadDocumentTemplates($mysqli, $dry_run);
        case 'products':
            return starterLoadProducts($mysqli, $dry_run);
    }
    return 0;
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
        case 'vendor_templates':
            return count(starterContentVendorTemplates());
        case 'document_templates':
            return count(starterContentDocumentTemplates());
        case 'products':
            return count(starterContentProducts());
    }
    return 0;
}

// ------------------------------
// starterLoadCategories
// ------------------------------
function starterLoadCategories($mysqli, $dry_run = false) {
    $existing = starterExistingNames($mysqli, 'categories', 'category_name', 'category_type');
    $added = 0;

    foreach (starterContentCategories() as $category) {
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
function starterLoadTags($mysqli, $dry_run = false) {
    $existing = starterExistingNames($mysqli, 'tags', 'tag_name', 'tag_type');
    $added = 0;

    foreach (starterContentTags() as $tag) {
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
function starterLoadTicketTemplates($mysqli, $dry_run = false) {
    $templates = ticketTemplatesImportRead(['format' => TICKET_TEMPLATE_FILE_FORMAT, 'version' => TICKET_TEMPLATE_FILE_VERSION, 'templates' => starterContentTicketTemplates()]);
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
// starterLoadProjectTemplates
// Links are resolved by ticket template name - one that is not present is
// skipped, so loading this pack before the ticket templates still works and
// simply produces a project template with fewer stages.
// ------------------------------
function starterLoadProjectTemplates($mysqli, $dry_run = false) {
    $existing = starterExistingNames($mysqli, 'project_templates', 'project_template_name');
    $added = 0;

    foreach (starterContentProjectTemplates() as $project_template) {
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
// ------------------------------
function starterLoadVendorTemplates($mysqli, $dry_run = false) {
    $existing = starterExistingNames($mysqli, 'vendor_templates', 'vendor_template_name');
    $added = 0;

    foreach (starterContentVendorTemplates() as $vendor_template) {
        if (isset($existing[mb_strtolower($vendor_template[0])])) {
            continue;
        }
        $added++;
        if ($dry_run) {
            continue;
        }
        starterInsert($mysqli, 'vendor_templates', [
            'vendor_template_name' => $vendor_template[0],
            'vendor_template_description' => $vendor_template[1],
            'vendor_template_website' => $vendor_template[2],
        ]);
    }

    return $added;
}

// ------------------------------
// starterLoadDocumentTemplates
// ------------------------------
function starterLoadDocumentTemplates($mysqli, $dry_run = false) {
    global $session_user_id;

    $existing = starterExistingNames($mysqli, 'document_templates', 'document_template_name');
    $added = 0;

    foreach (starterContentDocumentTemplates() as $document_template) {
        if (isset($existing[mb_strtolower($document_template[0])])) {
            continue;
        }
        $added++;
        if ($dry_run) {
            continue;
        }
        starterInsert($mysqli, 'document_templates', [
            'document_template_name' => $document_template[0],
            'document_template_description' => $document_template[1],
            'document_template_content' => $document_template[2],
            'document_template_created_by' => intval($session_user_id ?? 0),
        ], ['document_template_content']);
    }

    return $added;
}

// ------------------------------
// starterLoadProducts
// Products are filed under income categories. A category that is not present
// leaves the product uncategorised rather than blocking the load, so this pack
// works whether or not the categories pack has been loaded.
// ------------------------------
function starterLoadProducts($mysqli, $dry_run = false) {
    global $session_company_currency;

    $existing = starterExistingNames($mysqli, 'products', 'product_name');
    $added = 0;
    $category_ids = [];

    foreach (starterContentProducts() as $product) {
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

    $order = 1;
    foreach ($ticket_categories as $row) {
        $categories[] = [
            'type' => 'Ticket',
            'name' => $row[0],
            'color' => $row[1],
            'icon' => $row[2],
            'description' => $row[3],
            'order' => $order,
        ];
        $order++;
    }

    return $categories;

}

// ------------------------------
// starterContentTags
// tag_type 1 Client, 2 Location, 3 Contact, 4 Credential, 5 Asset, 6 Vendor, 7 Domain,
// 8 License, 9 Service, 10 Network, 11 Document, 12 Ticket - tagKinds() in functions/tags.php.
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
        [6, 'Authorised Contacts Only', '#d81b60', 'user-lock'],

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
            'details' => '<p>[Employee Name] starts on [Start Date] as [Job Title], reporting to [Manager]. Mirror their access from [Copy Access From].</p><ul><li>Computer: [Needs a Computer]</li><li>Phone extension: [Needs a Phone Extension]</li><li>Licence: [Licence]</li></ul><p>Do not create anything until an authorized contact has approved the request in writing.</p>',
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
                $fill_in('Licence', 'list', ['options' => ['Business Basic', 'Business Standard', 'Business Premium', 'Exchange Online only'], 'client' => false]),
            ],
            'tasks' => [
                $task('Confirm start date and written approval from an authorized contact', 15),
                $task('Create the directory account and set the initial password', 15),
                $task('Assign the [Licence] licence', 10),
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
                $fill_in('Already Licensed', 'yesno', ['required' => true, 'help' => 'Do you have a licence or subscription for it already?']),
                $fill_in('What It Is For'),
            ],
            'tasks' => [
                $task('Check it is compatible and approved for the client', 10),
                $task('Quote and order the licence', 15, ['Already Licensed', 'No']),
                $task('Install and license [Software]', 30),
                $task('Verify it works with the user', 10),
                $task('Record the licence and update the asset', 10),
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
            'description' => 'Replace ageing server hardware and migrate roles and data across.',
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
// name, description, website. Phone and account fields differ per region and
// per account, so they are left for whoever adds the vendor to a client.
// ------------------------------
function starterContentVendorTemplates() {

    $vendor_templates = [
        ['Microsoft', 'Cloud, productivity and operating system vendor', 'https://www.microsoft.com'],
        ['Google', 'Workspace, cloud and domain services', 'https://workspace.google.com'],
        ['Amazon Web Services', 'Cloud infrastructure and hosting', 'https://aws.amazon.com'],
        ['Dell', 'Workstation, laptop and server hardware', 'https://www.dell.com'],
        ['HP', 'Workstation, laptop and printer hardware', 'https://www.hp.com'],
        ['Lenovo', 'Workstation and laptop hardware', 'https://www.lenovo.com'],
        ['Supermicro', 'Server and storage hardware', 'https://www.supermicro.com'],
        ['Apple', 'Workstation, laptop and mobile hardware', 'https://www.apple.com'],
        ['Synology', 'Network attached storage and backup appliances', 'https://www.synology.com'],
        ['QNAP', 'Network attached storage appliances', 'https://www.qnap.com'],
        ['Ubiquiti', 'Networking, wireless and surveillance hardware', 'https://www.ui.com'],
        ['MikroTik', 'Routing and switching hardware', 'https://mikrotik.com'],
        ['Cisco', 'Networking, wireless and collaboration hardware', 'https://www.cisco.com'],
        ['Fortinet', 'Firewall and network security appliances', 'https://www.fortinet.com'],
        ['SonicWall', 'Firewall and network security appliances', 'https://www.sonicwall.com'],
        ['APC by Schneider Electric', 'Uninterruptible power supplies and power distribution', 'https://www.apc.com'],
        ['Brother', 'Printer and scanner hardware', 'https://www.brother.com'],
        ['Ingram Micro', 'Hardware and software distribution', 'https://www.ingrammicro.com'],
        ['TD SYNNEX', 'Hardware and software distribution', 'https://www.tdsynnex.com'],
        ['Veeam', 'Backup, replication and recovery software', 'https://www.veeam.com'],
        ['Acronis', 'Backup and cyber protection software', 'https://www.acronis.com'],
        ['Backblaze', 'Cloud backup and object storage', 'https://www.backblaze.com'],
        ['Wasabi', 'Cloud object storage', 'https://wasabi.com'],
        ['Bitdefender', 'Endpoint protection and security software', 'https://www.bitdefender.com'],
        ['ESET', 'Endpoint protection and security software', 'https://www.eset.com'],
        ['Proofpoint', 'Email security and filtering', 'https://www.proofpoint.com'],
        ['Bitwarden', 'Password management', 'https://bitwarden.com'],
        ['Cloudflare', 'DNS, content delivery and security', 'https://www.cloudflare.com'],
        ['Namecheap', 'Domain registration and hosting', 'https://www.namecheap.com'],
        ['GoDaddy', 'Domain registration and hosting', 'https://www.godaddy.com'],
        ['Adobe', 'Creative and document software', 'https://www.adobe.com'],
        ['Intuit', 'Accounting and payroll software', 'https://www.intuit.com'],
        ['Zoom', 'Video conferencing and collaboration', 'https://zoom.us'],
        ['RingCentral', 'Hosted voice and unified communications', 'https://www.ringcentral.com'],
        ['Stripe', 'Card and online payment processing', 'https://stripe.com'],
    ];

    return $vendor_templates;

}

// ------------------------------
// starterContentDocumentTemplates
// name, description, content. Square bracket placeholders are filled in when
// the document is created from the template.
// ------------------------------
function starterContentDocumentTemplates() {

    $document_templates = [
        [
            'Network Overview',
            'Topology, addressing, VLANs and internet circuits for a site',
            '<h3>Network Overview</h3><p><strong>Site:</strong> [Site]<br><strong>Last reviewed:</strong> [Date]</p><h4>Internet Circuits</h4><table style="width:100%" border="1"><tbody><tr><th>Provider</th><th>Type</th><th>Speed</th><th>Public IP</th><th>Account</th><th>Support</th></tr><tr><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Addressing and VLANs</h4><table style="width:100%" border="1"><tbody><tr><th>VLAN</th><th>Name</th><th>Subnet</th><th>Gateway</th><th>DHCP Scope</th></tr><tr><td></td><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Core Equipment</h4><ul><li>Firewall:</li><li>Core switch:</li><li>Access switches:</li><li>Wireless controller and access points:</li></ul><h4>DNS and DHCP</h4><ul><li>Internal DNS servers:</li><li>External forwarders:</li><li>DHCP server and reservations:</li></ul><h4>Remote Access</h4><ul><li>VPN type and endpoint:</li><li>Who has access:</li></ul><h4>Notes</h4><p></p>',
        ],
        [
            'Server Build Sheet',
            'As-built record for a physical or virtual server',
            '<h3>Server Build Sheet</h3><p><strong>Hostname:</strong> [Hostname]<br><strong>Role:</strong> [Role]<br><strong>Built:</strong> [Date]<br><strong>Built by:</strong> [Technician]</p><h4>Hardware or Virtual Specification</h4><table style="width:100%" border="1"><tbody><tr><th>CPU</th><th>Memory</th><th>Storage</th><th>Host or Chassis</th><th>Serial or Service Tag</th></tr><tr><td></td><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Operating System</h4><ul><li>Version and edition:</li><li>Licensing:</li><li>Patch level at handover:</li></ul><h4>Network</h4><ul><li>Addressing:</li><li>Out of band management:</li></ul><h4>Roles and Applications</h4><ul><li></li></ul><h4>Backup</h4><ul><li>Job name and schedule:</li><li>Retention:</li><li>First successful backup:</li></ul><h4>Monitoring</h4><ul><li>Agents installed:</li><li>Alerts configured:</li></ul><h4>Dependencies and Restart Order</h4><p></p>',
        ],
        [
            'Backup and Recovery Runbook',
            'What is protected, how often, and how to restore it',
            '<h3>Backup and Recovery Runbook</h3><p><strong>Client:</strong> [Client]<br><strong>Last restore test:</strong> [Date]</p><h4>Agreed Objectives</h4><ul><li>Recovery point objective:</li><li>Recovery time objective:</li></ul><h4>Protected Systems</h4><table style="width:100%" border="1"><tbody><tr><th>System</th><th>Job</th><th>Schedule</th><th>Retention</th><th>Target</th><th>Offsite Copy</th></tr><tr><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Not Protected</h4><p>Record anything deliberately excluded and who accepted the risk.</p><h4>Restore Procedure - File Level</h4><ol><li></li></ol><h4>Restore Procedure - Full System</h4><ol><li></li></ol><h4>Restore Test History</h4><table style="width:100%" border="1"><tbody><tr><th>Date</th><th>Scope</th><th>Recovery Time Achieved</th><th>Result</th><th>Tested By</th></tr><tr><td></td><td></td><td></td><td></td><td></td></tr></tbody></table>',
        ],
        [
            'Disaster Recovery Plan',
            'What happens when a site or critical system is lost',
            '<h3>Disaster Recovery Plan</h3><p><strong>Client:</strong> [Client]<br><strong>Approved by:</strong> [Contact]<br><strong>Last reviewed:</strong> [Date]</p><h4>Scope and Assumptions</h4><p></p><h4>Critical Systems in Priority Order</h4><table style="width:100%" border="1"><tbody><tr><th>Priority</th><th>System</th><th>Business Function</th><th>Recovery Target</th></tr><tr><td>1</td><td></td><td></td><td></td></tr></tbody></table><h4>Declaration and Authority</h4><ul><li>Who can declare a disaster:</li><li>How the team is mobilised:</li></ul><h4>Communication Plan</h4><table style="width:100%" border="1"><tbody><tr><th>Audience</th><th>Owner</th><th>Method</th></tr><tr><td></td><td></td><td></td></tr></tbody></table><h4>Recovery Procedures</h4><ol><li></li></ol><h4>Alternate Working Arrangements</h4><p></p><h4>Return to Normal Operations</h4><p></p>',
        ],
        [
            'Site and Access Details',
            'Physical access, keyholders and on-site logistics',
            '<h3>Site and Access Details</h3><p><strong>Site:</strong> [Site]<br><strong>Address:</strong> [Address]</p><h4>Access</h4><ul><li>Building access method:</li><li>Server room access method:</li><li>Notice required before attending:</li><li>Escort required:</li></ul><h4>Key Contacts</h4><table style="width:100%" border="1"><tbody><tr><th>Name</th><th>Role</th><th>Phone</th><th>Hours</th></tr><tr><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Hours and Restrictions</h4><ul><li>Normal site hours:</li><li>Agreed maintenance window:</li><li>Restricted periods:</li></ul><h4>Logistics</h4><ul><li>Parking and loading:</li><li>Delivery instructions:</li><li>Health and safety requirements:</li></ul><p><em>Do not record alarm codes, door codes or key safe combinations here - store those in the credential vault.</em></p>',
        ],
        [
            'Microsoft 365 Tenant Details',
            'Tenant identifiers, licensing position and security baseline',
            '<h3>Microsoft 365 Tenant Details</h3><p><strong>Client:</strong> [Client]<br><strong>Tenant domain:</strong> [Tenant]<br><strong>Tenant ID:</strong> [Tenant ID]</p><h4>Domains</h4><table style="width:100%" border="1"><tbody><tr><th>Domain</th><th>Registrar</th><th>Verified</th><th>Primary</th></tr><tr><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Licensing</h4><table style="width:100%" border="1"><tbody><tr><th>SKU</th><th>Assigned</th><th>Purchased</th><th>Renewal</th></tr><tr><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Administrative Access</h4><ul><li>Delegated access model:</li><li>Break glass account location:</li><li>Global administrators:</li></ul><h4>Security Baseline</h4><ul><li>Multi-factor authentication enforced:</li><li>Conditional access policies:</li><li>Legacy authentication blocked:</li><li>Audit logging enabled:</li></ul><h4>Mail Flow</h4><ul><li>Inbound and outbound connectors:</li><li>SPF, DKIM and DMARC status:</li><li>Filtering platform:</li></ul><h4>Tenant Backup</h4><ul><li>Product and retention:</li></ul>',
        ],
        [
            'Line of Business Application Profile',
            'Everything needed to support a client business critical application',
            '<h3>Line of Business Application Profile</h3><p><strong>Application:</strong> [Application]<br><strong>Business owner:</strong> [Contact]<br><strong>Criticality:</strong> [Critical / High / Normal]</p><h4>Vendor and Support</h4><ul><li>Vendor:</li><li>Support portal:</li><li>Support hours and contract level:</li><li>Account or customer number:</li></ul><h4>Architecture</h4><ul><li>Hosting model:</li><li>Servers and services involved:</li><li>Database platform and location:</li><li>Integrations and dependencies:</li></ul><h4>Access</h4><ul><li>How users connect:</li><li>Licensing model and count:</li><li>Who administers it:</li></ul><h4>Maintenance</h4><ul><li>Update process and cadence:</li><li>Known constraints - patching, reboots, versions:</li><li>Backup approach:</li></ul><h4>Common Issues</h4><p></p>',
        ],
        [
            'New Hire Onboarding Checklist',
            'Client facing form for requesting a new user setup',
            '<h3>New Hire Onboarding Request</h3><p>Return this form at least five working days before the start date.</p><h4>Employee</h4><ul><li>Full name:</li><li>Preferred display name:</li><li>Job title:</li><li>Department:</li><li>Manager:</li><li>Start date:</li><li>Working hours and location:</li></ul><h4>Access</h4><ul><li>Mirror access from:</li><li>Shared mailboxes required:</li><li>Distribution groups:</li><li>Applications required:</li></ul><h4>Equipment</h4><ul><li>Laptop or desktop:</li><li>Monitors and peripherals:</li><li>Mobile phone required:</li><li>Desk phone or extension:</li></ul><h4>Authorization</h4><ul><li>Requested by:</li><li>Approved by:</li><li>Date:</li></ul>',
        ],
        [
            'Employee Offboarding Checklist',
            'Client facing form authorizing a user removal',
            '<h3>Employee Offboarding Request</h3><p>This form is the written authorization to disable access. It must come from an authorized contact.</p><h4>Employee</h4><ul><li>Full name:</li><li>Last working day:</li><li>Effective time for access removal:</li></ul><h4>Mailbox and Data</h4><ul><li>Forward mail to:</li><li>Who takes ownership of files:</li><li>Retention period required:</li><li>Auto reply wording:</li></ul><h4>Equipment</h4><ul><li>Equipment to be returned:</li><li>Collected by:</li><li>Return date:</li></ul><h4>Authorization</h4><ul><li>Requested by:</li><li>Approved by:</li><li>Date:</li></ul>',
        ],
        [
            'Change Record',
            'Record of a planned change, its impact and how to roll it back',
            '<h3>Change Record</h3><p><strong>Change:</strong> [Summary]<br><strong>Requested by:</strong> [Contact]<br><strong>Scheduled:</strong> [Date and Window]<br><strong>Risk:</strong> [Low / Medium / High]</p><h4>Reason for Change</h4><p></p><h4>Systems Affected</h4><ul><li></li></ul><h4>Expected Impact</h4><ul><li>User impact:</li><li>Expected downtime:</li><li>Who has been notified:</li></ul><h4>Implementation Steps</h4><ol><li></li></ol><h4>Verification</h4><ol><li></li></ol><h4>Rollback Plan</h4><ol><li></li></ol><h4>Outcome</h4><ul><li>Completed by:</li><li>Result:</li><li>Documentation updated:</li></ul>',
        ],
        [
            'Incident Response Plan',
            'Agreed process and contacts for a security incident',
            '<h3>Incident Response Plan</h3><p><strong>Client:</strong> [Client]<br><strong>Last reviewed:</strong> [Date]</p><h4>Contacts</h4><table style="width:100%" border="1"><tbody><tr><th>Role</th><th>Name</th><th>Contact</th><th>Out of Hours</th></tr><tr><td>Client decision maker</td><td></td><td></td><td></td></tr><tr><td>Technical lead</td><td></td><td></td><td></td></tr><tr><td>Cyber insurer</td><td></td><td></td><td></td></tr><tr><td>Legal counsel</td><td></td><td></td><td></td></tr></tbody></table><h4>Insurance and Notification Requirements</h4><ul><li>Policy number and insurer:</li><li>Notification deadline:</li><li>Approved responders required:</li><li>Regulatory reporting obligations:</li></ul><h4>Severity Definitions</h4><p></p><h4>Response Stages</h4><ol><li>Detect and declare</li><li>Contain and isolate</li><li>Preserve evidence</li><li>Notify</li><li>Eradicate</li><li>Recover and verify</li><li>Review</li></ol><h4>Evidence Handling</h4><p></p>',
        ],
        [
            'Quarterly Business Review Notes',
            'Standing agenda and record of a client review meeting',
            '<h3>Quarterly Business Review</h3><p><strong>Client:</strong> [Client]<br><strong>Period:</strong> [Quarter]<br><strong>Attendees:</strong> [Attendees]<br><strong>Date:</strong> [Date]</p><h4>Service Performance</h4><ul><li>Tickets raised and closed:</li><li>Response and resolution against target:</li><li>Recurring themes:</li></ul><h4>Availability and Incidents</h4><p></p><h4>Security Posture</h4><ul><li>Open risks:</li><li>Risks closed this period:</li><li>Training completion:</li></ul><h4>Backup and Recovery</h4><ul><li>Restore test result:</li><li>Coverage gaps:</li></ul><h4>Asset and License Position</h4><ul><li>End of life exposure:</li><li>Warranty expiry in the next 12 months:</li><li>License changes:</li></ul><h4>Roadmap and Budget</h4><table style="width:100%" border="1"><tbody><tr><th>Item</th><th>Driver</th><th>Indicative Cost</th><th>Target Quarter</th></tr><tr><td></td><td></td><td></td><td></td></tr></tbody></table><h4>Agreed Actions</h4><table style="width:100%" border="1"><tbody><tr><th>Action</th><th>Owner</th><th>Due</th></tr><tr><td></td><td></td><td></td></tr></tbody></table>',
        ],
    ];

    return $document_templates;

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
