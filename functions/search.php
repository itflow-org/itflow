<?php

/*
 * Global search.
 *
 * One search behind two surfaces:
 *   - the live search palette in the top bar (press Ctrl+K), which asks
 *     agent/ajax.php?global_search as you type
 *   - the full results page, agent/global_search.php, reached by pressing Enter
 *
 * Both call globalSearch(), so the two can never disagree about what a user is
 * allowed to find. Every group is gated on the module its own list page enforces
 * and runs through clientScopeSql(), the same two checks the record's own page
 * applies.
 *
 * The palette is for getting somewhere: it matches names, numbers, emails,
 * phone numbers, IPs, MACs and serials, which is cheap enough to run on every
 * keystroke. The results page adds $deep, which also searches the long text -
 * ticket details, ticket conversations and document contents.
 *
 * Values come back RAW, not HTML-escaped. The palette renders them with
 * textContent; the results page escapes them at output.
 */

/**
 * The query, prepared once for every group: equality, LIKE (with % and _
 * matched literally, so searching "100%" does not match everything), and the
 * digits of a phone number when the query looks like one.
 */
function globalSearchTerms($raw) {
    // escapeSql() strips tags, so do it first: the length check, the
    // highlighting and the SQL then all agree on what was searched for
    $raw = trim(strip_tags((string) $raw));
    $raw = mb_substr($raw, 0, 100);

    $digits = preg_replace('/\D/', '', $raw);

    return [
        'raw'   => $raw,
        'eq'    => escapeSql($raw),
        'like'  => escapeSql(addcslashes($raw, '%_\\')),
        'phone' => (strlen($digits) >= 3 && preg_match('/^[\d\s().+-]+$/', $raw)) ? $digits : null,
    ];
}

/**
 * A record number typed the way people actually type them: "1042", "#1042",
 * "TCK-1042", "tck1042". The letters only count when they are this record type's
 * own prefix, otherwise "srv01" would match ticket 1.
 */
function globalSearchNumber($raw, $prefix) {
    $prefix = rtrim((string) $prefix, '-');
    $prefix_pattern = $prefix === '' ? '' : '|' . preg_quote($prefix, '/') . '-?';

    if (preg_match('/^(?:#' . $prefix_pattern . ')?\s*0*(\d{1,9})$/i', $raw, $match)) {
        return intval($match[1]);
    }

    return null;
}

/** Plain text around the first match in a long HTML field, for the results page. */
function globalSearchSnippet($html, $raw, $radius = 80) {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $position = mb_stripos($text, $raw);

    if ($position === false) {
        return mb_substr($text, 0, $radius * 2) . (mb_strlen($text) > $radius * 2 ? '…' : '');
    }

    $start = max(0, $position - $radius);
    $snippet = mb_substr($text, $start, mb_strlen($raw) + $radius * 2);

    return ($start > 0 ? '…' : '') . $snippet . ($start + mb_strlen($snippet) < mb_strlen($text) ? '…' : '');
}

function globalSearchMoney($amount, $currency_code) {
    global $currency_format;

    if (!$currency_format) {
        return number_format(floatval($amount), 2);
    }

    return numfmt_format_currency($currency_format, floatval($amount), $currency_code ?: 'USD');
}

/** Where a client-scoped record lives: the client's own copy of a page, or the global one. */
function globalSearchClientUrl($page, $client_id, $params = []) {
    if (intval($client_id) > 0) {
        $params = array_merge(['client_id' => intval($client_id)], $params);
    }

    return "/agent/$page" . ($params ? '?' . http_build_query($params) : '');
}

/**
 * Every searchable record type, in display order. A group whose module the
 * user cannot read, or whose feature is switched off, is left out entirely.
 */
function globalSearchGroups($terms, $deep) {
    global $config_ticket_prefix, $config_invoice_prefix, $config_quote_prefix;
    global $config_module_enable_ticketing, $config_module_enable_accounting;

    $eq = $terms['eq'];
    $like = $terms['like'];
    $phone = $terms['phone'];
    $q = $terms['raw'];

    $ticketing = intval($config_module_enable_ticketing) === 1;
    $accounting = intval($config_module_enable_accounting) === 1;

    $groups = [];

    $groups[] = [
        'key' => 'clients', 'label' => 'Clients', 'icon' => 'users', 'module' => 'module_client',
        'select' => 'client_id, client_name, client_type, client_abbreviation',
        'from' => 'clients',
        'where' => "client_archived_at IS NULL
            AND (client_name LIKE '%$like%' OR client_abbreviation LIKE '%$like%' OR client_website LIKE '%$like%')",
        'scope' => 'clients.client_id',
        'order' => "(client_name = '$eq') DESC, (client_name LIKE '$like%') DESC, client_accessed_at DESC, client_name",
        'list_url' => '/agent/clients.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['client_name'],
                'subtitle' => $row['client_type'],
                'url' => '/agent/client_overview.php?client_id=' . intval($row['client_id']),
            ];
        },
    ];

    $ticket_number = globalSearchNumber($q, $config_ticket_prefix);
    $ticket_number_match = $ticket_number !== null ? "OR ticket_number = $ticket_number" : '';
    $ticket_deep_match = $deep ? "OR ticket_details LIKE '%$like%'" : '';
    $groups[] = [
        'key' => 'tickets', 'label' => 'Tickets', 'icon' => 'life-ring', 'module' => 'module_support', 'enabled' => $ticketing,
        'select' => 'ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_closed_at,
            client_name, ticket_status_name, ticket_status_color',
        'from' => 'tickets
            LEFT JOIN clients ON ticket_client_id = client_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id',
        'where' => "ticket_archived_at IS NULL
            AND (ticket_subject LIKE '%$like%' OR CONCAT(ticket_prefix, ticket_number) LIKE '%$like%' $ticket_number_match $ticket_deep_match)",
        'scope' => 'ticket_client_id',
        'order' => ($ticket_number !== null ? "(ticket_number = $ticket_number) DESC, " : '') . "(ticket_closed_at IS NULL) DESC, ticket_id DESC",
        'exact' => $ticket_number !== null ? "ticket_number = $ticket_number" : null,
        'list_url' => '/agent/tickets.php?' . http_build_query(['state' => 'all', 'q' => $q]),
        'item' => function ($row) {
            return [
                'ref' => $row['ticket_prefix'] . $row['ticket_number'],
                'title' => $row['ticket_subject'],
                'context' => $row['client_name'],
                'badge' => $row['ticket_status_name'] ? ['text' => $row['ticket_status_name'], 'hex' => $row['ticket_status_color']] : null,
                'url' => globalSearchClientUrl('ticket.php', $row['ticket_client_id'], ['ticket_id' => intval($row['ticket_id'])]),
            ];
        },
    ];

    // Stored numbers may or may not carry punctuation, so compare digits to digits
    $contact_phone_match = $phone
        ? "OR REGEXP_REPLACE(contact_phone, '[^0-9]', '') LIKE '%$phone%' OR REGEXP_REPLACE(contact_mobile, '[^0-9]', '') LIKE '%$phone%'"
        : '';
    $groups[] = [
        'key' => 'contacts', 'label' => 'Contacts', 'icon' => 'address-book', 'module' => 'module_client',
        'select' => 'contact_id, contact_name, contact_title, contact_email, contact_photo, contact_client_id, client_name',
        'from' => 'contacts LEFT JOIN clients ON contact_client_id = client_id',
        'where' => "contact_archived_at IS NULL
            AND (contact_name LIKE '%$like%' OR contact_email LIKE '%$like%' OR contact_title LIKE '%$like%' $contact_phone_match)",
        'scope' => 'contact_client_id',
        'order' => "(contact_name = '$eq') DESC, (contact_name LIKE '$like%') DESC, contact_name",
        'list_url' => '/agent/contacts.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['contact_name'],
                'subtitle' => implode(' · ', array_filter([$row['contact_title'], $row['contact_email']])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('contact.php', $row['contact_client_id'], ['contact_id' => intval($row['contact_id'])]),
                // Drawn in place of the group icon - the person's avatar, as everywhere else
                'avatar' => contactAvatarData($row['contact_client_id'], $row['contact_id'], $row['contact_name'], $row['contact_photo']),
            ];
        },
    ];

    // Sites - by name, street, town or postcode, or the site's phone number
    $location_phone_match = $phone ? "OR location_phone LIKE '%$phone%'" : '';
    $groups[] = [
        'key' => 'locations', 'label' => 'Locations', 'icon' => 'map-marker-alt', 'module' => 'module_client',
        'select' => 'location_id, location_name, location_address, location_city, location_state, location_zip, location_primary, location_client_id, client_name',
        'from' => 'locations LEFT JOIN clients ON location_client_id = client_id',
        'where' => "location_archived_at IS NULL
            AND (location_name LIKE '%$like%' OR location_address LIKE '%$like%' OR location_city LIKE '%$like%' OR location_zip LIKE '%$like%' $location_phone_match)",
        'scope' => 'location_client_id',
        'order' => "(location_name = '$eq') DESC, (location_name LIKE '$like%') DESC, location_primary DESC, location_name",
        'list_url' => '/agent/locations.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['location_name'],
                'subtitle' => implode(' · ', array_filter([intval($row['location_primary']) ? 'Primary' : '', locationAddressText($row)])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('location.php', $row['location_client_id'], ['location_id' => intval($row['location_id'])]),
            ];
        },
    ];

    // Any interface counts, not just the primary one - the address someone has
    // in hand is as likely to be the iDRAC as the OS NIC.
    $groups[] = [
        'key' => 'assets', 'label' => 'Assets', 'icon' => 'desktop', 'module' => 'module_support',
        'select' => 'asset_id, asset_name, asset_type, asset_make, asset_model, asset_client_id, client_name,
            primary_interface.interface_ip AS asset_ip',
        'from' => 'assets
            LEFT JOIN clients ON asset_client_id = client_id
            LEFT JOIN asset_interfaces AS primary_interface
                ON primary_interface.interface_asset_id = asset_id AND primary_interface.interface_primary = 1',
        'where' => "asset_archived_at IS NULL
            AND (asset_name LIKE '%$like%' OR asset_serial LIKE '%$like%' OR asset_type LIKE '%$like%'
                OR asset_make LIKE '%$like%' OR asset_model LIKE '%$like%' OR asset_os LIKE '%$like%'
                OR asset_description LIKE '%$like%'
                OR EXISTS (SELECT 1 FROM asset_interfaces
                    WHERE interface_asset_id = asset_id AND interface_archived_at IS NULL
                    AND (interface_ip LIKE '%$like%' OR interface_nat_ip LIKE '%$like%' OR interface_ipv6 LIKE '%$like%' OR interface_mac LIKE '%$like%')))",
        'scope' => 'asset_client_id',
        // An exact address beats every partial one: 192.168.10.1 before 192.168.10.14
        'order' => "EXISTS (SELECT 1 FROM asset_interfaces WHERE interface_asset_id = asset_id
                AND (interface_ip = '$eq' OR interface_nat_ip = '$eq')) DESC,
            (asset_name = '$eq') DESC, (asset_name LIKE '$like%') DESC, asset_name",
        'list_url' => '/agent/assets.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['asset_name'],
                'subtitle' => implode(' · ', array_filter([$row['asset_type'], trim($row['asset_make'] . ' ' . $row['asset_model']), $row['asset_ip']])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('asset.php', $row['asset_client_id'], ['asset_id' => intval($row['asset_id'])]),
            ];
        },
    ];

    // Licenses by name, vendor and order reference - and by key, so a key read
    // off a sticker or a screen finds the license it belongs to
    $groups[] = [
        'key' => 'licenses', 'label' => 'Licenses', 'icon' => 'cube', 'module' => 'module_support',
        'select' => 'software.software_id, software_name, software_version, software_license_type, software_expire, software_client_id, client_name, vendor_name',
        'from' => 'software
            LEFT JOIN clients ON software_client_id = client_id
            LEFT JOIN vendors ON vendor_id = software_vendor_id',
        'where' => "software_archived_at IS NULL
            AND (software_name LIKE '%$like%' OR software_description LIKE '%$like%' OR software_key LIKE '%$like%'
                OR software_purchase_reference LIKE '%$like%' OR vendor_name LIKE '%$like%'
                OR software.software_id IN (SELECT software_key_software_id FROM software_keys WHERE software_key LIKE '%$like%'))",
        'scope' => 'software_client_id',
        'order' => "(software_key = '$eq') DESC, (software_name = '$eq') DESC, (software_name LIKE '$like%') DESC, software_name",
        'list_url' => '/agent/software.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            $renewal = softwareRenewal($row['software_expire']);
            return [
                'title' => trim($row['software_name'] . ' ' . $row['software_version']),
                'subtitle' => implode(' · ', array_filter([$row['software_license_type'], $row['vendor_name'], $renewal && $renewal['state'] !== 'active' ? $renewal['text'] : ''])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('license.php', $row['software_client_id'], ['software_id' => intval($row['software_id'])]),
            ];
        },
    ];

    // Name and description only. Usernames and passwords are encrypted at rest,
    // and the results page reveals passwords through the audited endpoint, never
    // in the markup.
    $groups[] = [
        'key' => 'credentials', 'label' => 'Credentials', 'icon' => 'key', 'module' => 'module_credential',
        'select' => 'credential_id, credential_name, credential_description, credential_client_id, client_name',
        'from' => 'credentials LEFT JOIN clients ON credential_client_id = client_id',
        'where' => "credential_archived_at IS NULL
            AND (credential_name LIKE '%$like%' OR credential_description LIKE '%$like%' OR credential_uri LIKE '%$like%' OR credential_uri_2 LIKE '%$like%')",
        'scope' => 'credential_client_id',
        'order' => "(credential_name = '$eq') DESC, (credential_name LIKE '$like%') DESC, credential_name",
        'list_url' => '/agent/credentials.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'id' => intval($row['credential_id']),
                'title' => $row['credential_name'],
                'subtitle' => $row['credential_description'],
                'context' => $row['client_name'],
                // Straight to its quick look on the client's credentials page
                'url' => globalSearchClientUrl('credentials.php', $row['credential_client_id'], ['open' => intval($row['credential_id'])]),
            ];
        },
    ];

    $groups[] = [
        'key' => 'domains', 'label' => 'Domains', 'icon' => 'globe', 'module' => 'module_support',
        'select' => 'domain_id, domain_name, domain_expire, domain_client_id, client_name',
        'from' => 'domains LEFT JOIN clients ON domain_client_id = client_id',
        'where' => "domain_archived_at IS NULL AND domain_name LIKE '%$like%'",
        'scope' => 'domain_client_id',
        'order' => "(domain_name = '$eq') DESC, (domain_name LIKE '$like%') DESC, domain_name",
        'list_url' => '/agent/domains.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['domain_name'],
                'subtitle' => $row['domain_expire'] ? 'Expires ' . $row['domain_expire'] : '',
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('domains.php', $row['domain_client_id'], ['q' => $row['domain_name']]),
            ];
        },
    ];

    $document_deep_match = $deep ? "OR MATCH(document_content_raw) AGAINST ('$eq')" : '';
    $groups[] = [
        'key' => 'documents', 'label' => 'Documents', 'icon' => 'file-alt', 'module' => 'module_support',
        'select' => 'document_id, document_name, document_description, document_client_id, client_name',
        'from' => 'documents LEFT JOIN clients ON document_client_id = client_id',
        'where' => "document_archived_at IS NULL
            AND (document_name LIKE '%$like%' OR document_description LIKE '%$like%' $document_deep_match)",
        'scope' => 'document_client_id',
        'order' => "(document_name LIKE '$like%') DESC, document_updated_at DESC",
        'list_url' => null,
        'item' => function ($row) {
            return [
                'title' => $row['document_name'],
                'subtitle' => $row['document_description'],
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('document.php', $row['document_client_id'], ['document_id' => intval($row['document_id'])]),
                'tile' => ['tone' => 'document', 'icon' => 'file-alt', 'label' => 'Document'],
            ];
        },
    ];

    $groups[] = [
        'key' => 'files', 'label' => 'Files', 'icon' => 'paperclip', 'module' => 'module_support',
        'select' => 'file_id, file_name, file_ext, file_description, file_client_id, file_folder_id, client_name, folder_name',
        'from' => 'files
            LEFT JOIN clients ON file_client_id = client_id
            LEFT JOIN folders ON file_folder_id = folder_id',
        'where' => "file_archived_at IS NULL AND (file_name LIKE '%$like%' OR file_description LIKE '%$like%')",
        'scope' => 'file_client_id',
        'order' => "(file_name LIKE '$like%') DESC, file_id DESC",
        'list_url' => null,
        'item' => function ($row) {
            return [
                'title' => $row['file_name'],
                'subtitle' => implode(' · ', array_filter([$row['folder_name'], $row['file_description']])),
                'context' => $row['client_name'],
                // Its folder, narrowed to it - one click from its quick look
                'url' => globalSearchClientUrl('files.php', $row['file_client_id'], ['folder_id' => intval($row['file_folder_id']), 'q' => $row['file_name']]),
                'tile' => ['tone' => fileTypeInfo($row['file_ext'])['tone'], 'icon' => fileTypeInfo($row['file_ext'])['icon'], 'label' => fileTypeInfo($row['file_ext'])['label']],
            ];
        },
    ];

    $invoice_number = globalSearchNumber($q, $config_invoice_prefix);
    $invoice_number_match = $invoice_number !== null ? "OR invoice_number = $invoice_number" : '';
    $groups[] = [
        'key' => 'invoices', 'label' => 'Invoices', 'icon' => 'file-invoice-dollar', 'module' => 'module_sales', 'enabled' => $accounting,
        'select' => 'invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_status, invoice_amount,
            invoice_currency_code, invoice_client_id, client_name',
        'from' => 'invoices LEFT JOIN clients ON invoice_client_id = client_id',
        'where' => "invoice_archived_at IS NULL
            AND (CONCAT(invoice_prefix, invoice_number) LIKE '%$like%' OR invoice_scope LIKE '%$like%' $invoice_number_match)",
        'scope' => 'invoice_client_id',
        'order' => ($invoice_number !== null ? "(invoice_number = $invoice_number) DESC, " : '') . 'invoice_number DESC',
        'exact' => $invoice_number !== null ? "invoice_number = $invoice_number" : null,
        'list_url' => '/agent/invoices.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'ref' => $row['invoice_prefix'] . $row['invoice_number'],
                'title' => $row['invoice_scope'] ?: 'Invoice',
                'subtitle' => globalSearchMoney($row['invoice_amount'], $row['invoice_currency_code']),
                'context' => $row['client_name'],
                'badge' => ['text' => $row['invoice_status'], 'variant' => getInvoiceBadgeColor($row['invoice_status'])],
                'url' => globalSearchClientUrl('invoice.php', $row['invoice_client_id'], ['invoice_id' => intval($row['invoice_id'])]),
            ];
        },
    ];

    $quote_number = globalSearchNumber($q, $config_quote_prefix);
    $quote_number_match = $quote_number !== null ? "OR quote_number = $quote_number" : '';
    $groups[] = [
        'key' => 'quotes', 'label' => 'Quotes', 'icon' => 'comment-dollar', 'module' => 'module_sales', 'enabled' => $accounting,
        'select' => 'quote_id, quote_prefix, quote_number, quote_scope, quote_status, quote_amount,
            quote_currency_code, quote_client_id, client_name',
        'from' => 'quotes LEFT JOIN clients ON quote_client_id = client_id',
        'where' => "quote_archived_at IS NULL
            AND (CONCAT(quote_prefix, quote_number) LIKE '%$like%' OR quote_scope LIKE '%$like%' $quote_number_match)",
        'scope' => 'quote_client_id',
        'order' => ($quote_number !== null ? "(quote_number = $quote_number) DESC, " : '') . 'quote_number DESC',
        'exact' => $quote_number !== null ? "quote_number = $quote_number" : null,
        'list_url' => '/agent/quotes.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            $quote_badge_variants = ['Sent' => 'warning', 'Viewed' => 'primary', 'Accepted' => 'success', 'Declined' => 'danger', 'Invoiced' => 'info'];
            return [
                'ref' => $row['quote_prefix'] . $row['quote_number'],
                'title' => $row['quote_scope'] ?: 'Quote',
                'subtitle' => globalSearchMoney($row['quote_amount'], $row['quote_currency_code']),
                'context' => $row['client_name'],
                'badge' => ['text' => $row['quote_status'], 'variant' => $quote_badge_variants[$row['quote_status']] ?? 'secondary'],
                'url' => globalSearchClientUrl('quote.php', $row['quote_client_id'], ['quote_id' => intval($row['quote_id'])]),
            ];
        },
    ];

    $vendor_phone_match = $phone ? "OR REGEXP_REPLACE(vendor_phone, '[^0-9]', '') LIKE '%$phone%'" : '';
    $groups[] = [
        'key' => 'vendors', 'label' => 'Vendors', 'icon' => 'building', 'module' => 'module_client',
        'select' => 'vendor_id, vendor_name, vendor_description, vendor_phone, vendor_phone_country_code, vendor_client_id, client_name',
        'from' => 'vendors LEFT JOIN clients ON vendor_client_id = client_id',
        'where' => "vendor_archived_at IS NULL
            AND (vendor_name LIKE '%$like%' OR vendor_description LIKE '%$like%' OR vendor_account_number LIKE '%$like%'
                OR vendor_contact_name LIKE '%$like%' OR vendor_email LIKE '%$like%' OR vendor_contact_email LIKE '%$like%' $vendor_phone_match)",
        'scope' => 'vendor_client_id',
        'order' => "(vendor_name = '$eq') DESC, (vendor_name LIKE '$like%') DESC, vendor_favorite DESC, vendor_name",
        'list_url' => '/agent/vendors.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['vendor_name'],
                'subtitle' => implode(' · ', array_filter([$row['vendor_description'], $row['vendor_phone'] ? formatPhoneNumber($row['vendor_phone'], $row['vendor_phone_country_code']) : ''])),
                'context' => intval($row['vendor_client_id']) ? $row['client_name'] : 'Our vendor',
                'url' => globalSearchClientUrl('vendor.php', $row['vendor_client_id'], ['vendor_id' => intval($row['vendor_id'])]),
            ];
        },
    ];

    $recurring_deep_match = $deep ? "OR recurring_ticket_details LIKE '%$like%'" : '';
    $groups[] = [
        'key' => 'recurring_tickets', 'label' => 'Recurring Tickets', 'icon' => 'redo-alt', 'module' => 'module_support', 'enabled' => $ticketing,
        'select' => 'recurring_ticket_id, recurring_ticket_subject, recurring_ticket_frequency, recurring_ticket_next_run,
            recurring_ticket_client_id, client_name',
        'from' => 'recurring_tickets LEFT JOIN clients ON recurring_ticket_client_id = client_id',
        'where' => "(recurring_ticket_subject LIKE '%$like%' $recurring_deep_match)",
        'scope' => 'recurring_ticket_client_id',
        'order' => "(recurring_ticket_subject LIKE '$like%') DESC, recurring_ticket_next_run",
        'list_url' => '/agent/recurring_tickets.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['recurring_ticket_subject'],
                'subtitle' => implode(' · ', array_filter([$row['recurring_ticket_frequency'], $row['recurring_ticket_next_run'] ? 'Next ' . $row['recurring_ticket_next_run'] : ''])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('recurring_tickets.php', $row['recurring_ticket_client_id'], ['q' => $row['recurring_ticket_subject']]),
            ];
        },
    ];

    $groups[] = [
        'key' => 'products', 'label' => 'Products', 'icon' => 'box-open', 'module' => 'module_sales', 'enabled' => $accounting,
        'select' => 'product_id, product_name, product_code, product_price, product_currency_code',
        'from' => 'products',
        'where' => "product_archived_at IS NULL
            AND (product_name LIKE '%$like%' OR product_code LIKE '%$like%' OR product_description LIKE '%$like%')",
        'scope' => null,
        'order' => "(product_name = '$eq') DESC, (product_name LIKE '$like%') DESC, product_name",
        'list_url' => '/agent/products.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['product_name'],
                'subtitle' => implode(' · ', array_filter([$row['product_code'], globalSearchMoney($row['product_price'], $row['product_currency_code'])])),
                'url' => '/agent/products.php?' . http_build_query(['q' => $row['product_name']]),
            ];
        },
    ];

    // Ticket conversations: matched inside reply bodies, so results page only.
    if ($deep) {
        $groups[] = [
            'key' => 'ticket_replies', 'label' => 'Ticket Conversations', 'icon' => 'comments', 'module' => 'module_support', 'enabled' => $ticketing,
            'select' => 'ticket_reply_id, ticket_reply, ticket_reply_created_at, ticket_id, ticket_prefix, ticket_number,
                ticket_subject, ticket_client_id, client_name',
            'from' => 'ticket_replies
                JOIN tickets ON ticket_reply_ticket_id = ticket_id
                LEFT JOIN clients ON ticket_client_id = client_id',
            'where' => "ticket_reply_archived_at IS NULL AND ticket_archived_at IS NULL AND ticket_reply LIKE '%$like%'",
            'scope' => 'ticket_client_id',
            'order' => 'ticket_reply_id DESC',
            'list_url' => null,
            'item' => function ($row) use ($q) {
                return [
                    'ref' => $row['ticket_prefix'] . $row['ticket_number'],
                    'title' => $row['ticket_subject'],
                    'snippet' => globalSearchSnippet($row['ticket_reply'], $q),
                    'context' => $row['client_name'],
                    'url' => globalSearchClientUrl('ticket.php', $row['ticket_client_id'], ['ticket_id' => intval($row['ticket_id'])]),
                ];
            },
        ];
    }

    return $groups;
}

/**
 * Run the search.
 *
 * Returns the groups that matched, in display order, each as:
 *   key, label, icon, items[], more (bool), total (int, only with $with_totals), list_url
 * A group with an exact record-number hit (ticket 1042 for "TCK-1042") moves to
 * the front, since that is unambiguously what was asked for.
 */
function globalSearch($raw_query, $limit = 5, $deep = false, $with_totals = false) {
    global $mysqli;

    $terms = globalSearchTerms($raw_query);
    if (mb_strlen($terms['raw']) < 2) {
        return [];
    }

    $limit = max(1, intval($limit));
    $results = [];

    foreach (globalSearchGroups($terms, $deep) as $group) {
        if (array_key_exists('enabled', $group) && !$group['enabled']) {
            continue;
        }
        if (lookupUserPermission($group['module']) < 1) {
            continue;
        }

        $scope = $group['scope'] ? clientScopeSql($group['scope']) : '';
        $where = "{$group['where']} $scope";

        $sql = mysqli_query(
            $mysqli,
            "SELECT {$group['select']} FROM {$group['from']} WHERE $where ORDER BY {$group['order']} LIMIT " . ($limit + 1)
        );

        $items = [];
        while ($row = mysqli_fetch_assoc($sql)) {
            $items[] = $group['item']($row);
        }

        if (!$items) {
            continue;
        }

        $more = count($items) > $limit;
        $items = array_slice($items, 0, $limit);

        $exact = false;
        if (!empty($group['exact'])) {
            $exact_row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM {$group['from']} WHERE $where AND {$group['exact']} LIMIT 1"));
            $exact = (bool) $exact_row;
        }

        $total = null;
        if ($with_totals) {
            $total = $more
                ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM {$group['from']} WHERE $where"))[0])
                : count($items);
        }

        $results[] = [
            'key' => $group['key'],
            'label' => $group['label'],
            'icon' => $group['icon'],
            'items' => $items,
            'more' => $more,
            'total' => $total,
            'list_url' => $group['list_url'],
            'exact' => $exact,
        ];
    }

    // Exact record-number hits first; otherwise keep display order (usort is stable in PHP 8)
    usort($results, function ($a, $b) {
        return intval($b['exact']) - intval($a['exact']);
    });

    return $results;
}

/** Recently opened clients, shown in the palette before anything is typed. */
function globalSearchRecentClients($limit = 6) {
    global $mysqli;

    if (lookupUserPermission('module_client') < 1) {
        return [];
    }

    $limit = max(1, intval($limit));

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, client_type FROM clients
        WHERE client_archived_at IS NULL AND client_accessed_at IS NOT NULL
        " . clientScopeSql('clients.client_id') . "
        ORDER BY client_accessed_at DESC LIMIT $limit"
    );

    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = [
            'title' => $row['client_name'],
            'subtitle' => $row['client_type'],
            'url' => '/agent/client_overview.php?client_id=' . intval($row['client_id']),
        ];
    }

    return $items;
}

/**
 * The app's own pages, so "inv" can also take you to Invoices. Same gates as
 * the side nav, which is the only other place these links appear.
 */
function globalSearchPages($raw_query) {
    global $config_module_enable_ticketing, $config_module_enable_accounting, $session_is_admin;

    $query = mb_strtolower(trim((string) $raw_query));
    if (mb_strlen($query) < 2) {
        return [];
    }

    $ticketing = intval($config_module_enable_ticketing) === 1;
    $accounting = intval($config_module_enable_accounting) === 1;
    $support = lookupUserPermission('module_support') >= 1;
    $client = lookupUserPermission('module_client') >= 1;
    $sales = lookupUserPermission('module_sales') >= 1;
    $financial = lookupUserPermission('module_financial') >= 1;

    $pages = [
        ['Dashboard', '/agent/dashboard.php', true],
        ['Clients', '/agent/clients.php', $client],
        ['All Client Docs', '/agent/contacts.php', $client],
        ['Vendors', '/agent/vendors.php', true],
        ['Calendar', '/agent/calendar.php', true],
        ['Tickets', '/agent/tickets.php', $support && $ticketing],
        ['Recurring Tickets', '/agent/recurring_tickets.php', $support && $ticketing],
        ['Projects', '/agent/projects.php', $support && $ticketing],
        ['Quotes', '/agent/quotes.php', $sales && $accounting],
        ['Invoices', '/agent/invoices.php', $sales && $accounting],
        ['Recurring Invoices', '/agent/recurring_invoices.php', $sales && $accounting],
        ['Products', '/agent/products.php', $sales && $accounting],
        ['Income', '/agent/income.php', $financial && $accounting],
        ['Expenses', '/agent/expenses.php', $financial && $accounting],
        ['Recurring Expenses', '/agent/recurring_expenses.php', $financial && $accounting],
        ['Trips', '/agent/trips.php', $financial && $accounting],
        ['Accounts', '/agent/accounts.php', $financial && $accounting],
        ['Transactions', '/agent/transactions.php', $financial && $accounting],
        ['Transfers', '/agent/transfers.php', $financial && $accounting],
        ['Reports', '/agent/reports/', lookupUserPermission('module_reporting') >= 1],
        ['Administration', '/admin/', (bool) $session_is_admin],
        ['My Account', '/agent/user/user_details.php', true],
    ];

    $items = [];
    foreach ($pages as [$label, $url, $allowed]) {
        if (!$allowed) {
            continue;
        }
        // Match the start of any word: "inv" finds Invoices and Recurring Invoices
        foreach (preg_split('/\s+/', mb_strtolower($label)) as $word) {
            if (strpos($word, $query) === 0 || strpos(mb_strtolower($label), $query) === 0) {
                $items[] = ['title' => $label, 'url' => $url];
                break;
            }
        }
    }

    return array_slice($items, 0, 4);
}
