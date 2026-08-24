<?php
defined('FROM_POST_HANDLER') || defined('FROM_STARTER_CONTENT') || die("Direct file access is not allowed");

/*
 * ITFlow - Demo data library
 *
 * A fictional book of business for demos, training and screenshots - ten
 * clients with the people, kit, documentation, tickets and billing a typical
 * MSP would be carrying for them. Loaded from Maintenance > Starter Content.
 *
 * This is NOT starter content. Starter content is configuration you keep;
 * this is disposable sample data that has no business sitting in a live
 * install, so everything it creates hangs off a demo client and every demo
 * client carries the 'Demo Data' client tag. Removal works off that tag and
 * nothing else - untag a client and it stops being demo data.
 *
 * Dates are generated relative to today so the dashboard, aging, SLA clocks
 * and reports have something live to show rather than a flat wall of rows.
 *
 * Named _model so admin/post.php does not glob it in on every admin request.
 */

require_once __DIR__ . '/starter_content_model.php';

// The one marker. Everything else keys off which clients carry it.
const DEMO_DATA_TAG = 'Demo Data';

// ------------------------------
// demoDataTagId
// The 'Demo Data' client tag (tag_type 1), created on demand.
// ------------------------------
function demoDataTagId($mysqli, $create = false) {
    $name = escapeSql(DEMO_DATA_TAG);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_name = '$name' AND tag_type = 1 LIMIT 1"));
    $tag_id = intval($row['tag_id'] ?? 0);

    if (!$tag_id && $create) {
        $tag_id = starterInsert($mysqli, 'tags', [
            'tag_name' => DEMO_DATA_TAG,
            'tag_type' => 1,
            'tag_color' => '#e83e8c',
            'tag_icon' => 'flask',
        ]);
    }

    return $tag_id;
}

// ------------------------------
// demoDataClients
// Client IDs currently carrying the demo tag, in name order.
// ------------------------------
function demoDataClients($mysqli) {
    $clients = [];
    $tag_id = demoDataTagId($mysqli);
    if (!$tag_id) {
        return $clients;
    }

    $sql = mysqli_query($mysqli, "SELECT clients.client_id, client_name FROM clients LEFT JOIN client_tags ON client_tags.client_id = clients.client_id WHERE client_tags.tag_id = $tag_id ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])] = $row['client_name'];
    }

    return $clients;
}

// ------------------------------
// demoDataStatus
// What the page needs: how many demo clients the library holds, how many are
// already here, and whether a real book of business is already in the install.
// ------------------------------
function demoDataStatus($mysqli) {
    $loaded = demoDataClients($mysqli);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(client_id) AS total FROM clients WHERE client_archived_at IS NULL"));
    $all_clients = intval($row['total'] ?? 0);

    return [
        'total' => count(demoDataProfiles()),
        'loaded' => $loaded,
        'other_clients' => $all_clients - count($loaded),
    ];
}

// ------------------------------
// demoNextNumber
// The same atomic increment the real handlers use, so demo rows do not leave
// ticket/invoice/quote numbering sitting on a number that is already taken.
// ------------------------------
function demoNextNumber($mysqli, $setting) {
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            $setting = LAST_INSERT_ID($setting),
            $setting = $setting + 1
        WHERE company_id = 1
    ");
    return intval(mysqli_insert_id($mysqli));
}

// ------------------------------
// demoTicketStatusId
// Built-in statuses are IDs 1-5 on an install seeded by setup, but look them
// up by name anyway - an install that renamed or reordered them still works.
// ------------------------------
function demoTicketStatusId($mysqli, $name, $fallback) {
    $name = escapeSql($name);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_name = '$name' LIMIT 1"));
    return intval($row['ticket_status_id'] ?? 0) ?: $fallback;
}

// ------------------------------
// demoFirstId
// First live row in a table, or 0. Used for the account payments post to and
// the calendar events land on - demo data never creates either of those.
// ------------------------------
function demoFirstId($mysqli, $table, $id_column, $archived_column = '') {
    $where = $archived_column ? "WHERE $archived_column IS NULL" : '';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT $id_column FROM $table $where ORDER BY $id_column ASC LIMIT 1"));
    return intval($row[$id_column] ?? 0);
}

// ------------------------------
// demoDateTime / demoDate
// Everything is generated relative to now.
// ------------------------------
function demoDateTime($days_ago, $hour = 9, $minute = 0) {
    return date('Y-m-d H:i:s', mktime($hour, $minute, 0, date('n'), date('j') - $days_ago, date('Y')));
}

function demoDate($days_ago) {
    return date('Y-m-d', mktime(12, 0, 0, date('n'), date('j') - $days_ago, date('Y')));
}

// ------------------------------
// demoSerial
// Deterministic stand-in for a serial number - same input, same serial, so a
// reload after a purge does not produce a whole new set of numbers.
// ------------------------------
function demoSerial($seed, $length = 10) {
    return strtoupper(substr(md5('itflow-demo-' . $seed), 0, $length));
}

// ------------------------------
// demoDataLoad
// Builds the whole book of business. Clients already present by name are
// skipped, so a second run adds nothing and a run after a partial purge only
// fills the gaps. Returns a per-area count for the flash message.
// ------------------------------
function demoDataLoad($mysqli) {
    global $session_user_id, $session_company_currency;

    $user_id = intval($session_user_id ?? 0);
    $currency = $session_company_currency ?? '';

    $counts = [
        'clients' => 0,
        'contacts' => 0,
        'assets' => 0,
        'documentation' => 0,
        'tickets' => 0,
        'projects' => 0,
        'billing' => 0,
        'company' => 0,
        'skipped_credentials' => 0,
    ];

    $existing = starterExistingNames($mysqli, 'clients', 'client_name');
    $tag_id = demoDataTagId($mysqli, true);

    // Shared lookups - one query each rather than one per client
    $context = [
        'user_id' => $user_id,
        'currency' => $currency,
        'tag_id' => $tag_id,
        'account_id' => demoFirstId($mysqli, 'accounts', 'account_id', 'account_archived_at'),
        'accounts' => demoEnsureAccounts($mysqli, $currency),
        'calendar_id' => demoFirstId($mysqli, 'calendars', 'calendar_id'),
        'status_new' => demoTicketStatusId($mysqli, 'New', 1),
        'status_open' => demoTicketStatusId($mysqli, 'Open', 2),
        'status_hold' => demoTicketStatusId($mysqli, 'On Hold', 3),
        'status_resolved' => demoTicketStatusId($mysqli, 'Resolved', 4),
        'status_closed' => demoTicketStatusId($mysqli, 'Closed', 5),
        'vault_open' => encryptCredentialEntry('itflow-demo-probe') !== false,
    ];

    // One transaction for the whole load - several thousand rows go in a great
    // deal faster, and a failure part way through does not leave half a demo
    mysqli_begin_transaction($mysqli);

    foreach (demoDataProfiles() as $index => $profile) {

        if (isset($existing[mb_strtolower($profile['name'])])) {
            continue;
        }

        demoBuildClient($mysqli, $profile, $index, $context, $counts);
        $counts['clients']++;
    }

    // The MSP's own accounts, operating costs and transfers
    if ($counts['clients']) {
        demoBuildCompanyFinancials($mysqli, $context, $counts);
    }

    mysqli_commit($mysqli);

    if (!$context['vault_open'] && $counts['clients']) {
        $counts['skipped_credentials'] = 1;
    }

    return $counts;
}

// ------------------------------
// demoDataRemove
// Purges every client carrying the demo tag and everything hanging off it.
// This deliberately goes further than the client delete handler, which leaves
// tags, notes, tasks, ticket history, projects and expenses behind.
// ------------------------------
function demoDataRemove($mysqli) {
    $clients = demoDataClients($mysqli);
    if (!$clients) {
        return 0;
    }

    foreach (array_keys($clients) as $client_id) {

        $client_id = intval($client_id);

        // Tickets and everything under them
        $ticket_ids = [];
        $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $ticket_ids[] = intval($row['ticket_id']);
        }
        if ($ticket_ids) {
            $ticket_ids = implode(',', $ticket_ids);
            mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_history WHERE ticket_history_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM tasks WHERE task_ticket_id IN ($ticket_ids)");
            mysqli_query($mysqli, "DELETE FROM sla_history WHERE sla_history_ticket_id IN ($ticket_ids)");
        }
        mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT recurring_ticket_id FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $recurring_ticket_id = intval($row['recurring_ticket_id']);
            mysqli_query($mysqli, "DELETE FROM recurring_ticket_tasks WHERE recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id");
            mysqli_query($mysqli, "DELETE FROM recurring_ticket_assets WHERE recurring_ticket_id = $recurring_ticket_id");
        }
        mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id");

        // Billing
        $sql = mysqli_query($mysqli, "SELECT invoice_id FROM invoices WHERE invoice_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $invoice_id = intval($row['invoice_id']);
            mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
        }
        mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT quote_id FROM quotes WHERE quote_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $quote_id = intval($row['quote_id']);
            mysqli_query($mysqli, "DELETE FROM quote_items WHERE item_quote_id = $quote_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_quote_id = $quote_id");
        }
        mysqli_query($mysqli, "DELETE FROM quotes WHERE quote_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $recurring_invoice_id = intval($row['recurring_invoice_id']);
            mysqli_query($mysqli, "DELETE FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_recurring_invoice_id = $recurring_invoice_id");
        }
        mysqli_query($mysqli, "DELETE FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");

        mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM trips WHERE trip_client_id = $client_id");

        // Documentation and the join tables the client delete handler misses
        $sql = mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $asset_id = intval($row['asset_id']);
            mysqli_query($mysqli, "DELETE FROM asset_tags WHERE asset_tag_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_notes WHERE asset_note_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_history WHERE asset_history_asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_credentials WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_documents WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM asset_files WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM contact_assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM service_assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli, "DELETE FROM software_assets WHERE asset_id = $asset_id");
        }
        mysqli_query($mysqli, "DELETE FROM assets WHERE asset_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $contact_id = intval($row['contact_id']);
            mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_notes WHERE contact_note_contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_credentials WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_documents WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_files WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM service_contacts WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM software_contacts WHERE contact_id = $contact_id");
        }
        mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT credential_id FROM credentials WHERE credential_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $credential_id = intval($row['credential_id']);
            mysqli_query($mysqli, "DELETE FROM credential_tags WHERE credential_id = $credential_id");
            mysqli_query($mysqli, "DELETE FROM service_credentials WHERE credential_id = $credential_id");
            mysqli_query($mysqli, "DELETE FROM software_credentials WHERE credential_id = $credential_id");
            mysqli_query($mysqli, "DELETE FROM vendor_credentials WHERE credential_id = $credential_id");
        }
        mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $location_id = intval($row['location_id']);
            mysqli_query($mysqli, "DELETE FROM location_tags WHERE location_id = $location_id");
        }
        mysqli_query($mysqli, "DELETE FROM locations WHERE location_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT service_id FROM services WHERE service_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $service_id = intval($row['service_id']);
            mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = $service_id");
            mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = $service_id");
            mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = $service_id");
            mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = $service_id");
        }
        mysqli_query($mysqli, "DELETE FROM services WHERE service_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT software_id FROM software WHERE software_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $software_id = intval($row['software_id']);
            mysqli_query($mysqli, "DELETE FROM software_keys WHERE software_key_software_id = $software_id");
            mysqli_query($mysqli, "DELETE FROM software_documents WHERE software_id = $software_id");
            mysqli_query($mysqli, "DELETE FROM software_files WHERE software_id = $software_id");
        }
        mysqli_query($mysqli, "DELETE FROM software WHERE software_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT document_id FROM documents WHERE document_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $document_id = intval($row['document_id']);
            mysqli_query($mysqli, "DELETE FROM document_files WHERE document_id = $document_id");
            mysqli_query($mysqli, "DELETE FROM document_versions WHERE document_version_document_id = $document_id");
        }
        mysqli_query($mysqli, "DELETE FROM documents WHERE document_client_id = $client_id");

        $sql = mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_client_id = $client_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $vendor_id = intval($row['vendor_id']);
            mysqli_query($mysqli, "DELETE FROM vendor_credentials WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli, "DELETE FROM vendor_documents WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli, "DELETE FROM vendor_files WHERE vendor_id = $vendor_id");
        }
        mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_client_id = $client_id");

        mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM domains WHERE domain_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM networks WHERE network_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM projects WHERE project_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM contracts WHERE contract_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM client_notes WHERE client_note_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM logs WHERE log_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM notifications WHERE notification_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM clients WHERE client_id = $client_id");

        // Never leave the company's own-client designation pointing at a client that is gone
        mysqli_query($mysqli, "UPDATE settings SET config_internal_client_id = 0 WHERE config_internal_client_id = $client_id");

    }

    // Company level demo rows carry a DEMO- reference because they hang off no
    // client. Transfers go first - each one owns an expense and a revenue.
    $sql = mysqli_query($mysqli, "SELECT transfer_id, transfer_expense_id, transfer_revenue_id FROM transfers
        LEFT JOIN expenses ON expense_id = transfer_expense_id
        WHERE expense_reference LIKE 'DEMO-%'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $transfer_id = intval($row['transfer_id']);
        $expense_id = intval($row['transfer_expense_id']);
        $revenue_id = intval($row['transfer_revenue_id']);
        mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = $expense_id");
        mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_id = $revenue_id");
        mysqli_query($mysqli, "DELETE FROM transfers WHERE transfer_id = $transfer_id");
    }

    mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_client_id = 0 AND expense_reference LIKE 'DEMO-%'");
    mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_client_id = 0 AND revenue_reference LIKE 'DEMO-%'");

    // Accounts are shared company config, so one only goes if nothing at all is
    // left pointing at it - an account that was already here keeps its history
    foreach (demoDataAccounts() as $account) {
        $account_name = escapeSql($account[0]);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT account_id FROM accounts WHERE account_name = '$account_name' LIMIT 1"));
        $account_id = intval($row['account_id'] ?? 0);
        if (!$account_id) {
            continue;
        }
        $in_use = 0;
        foreach (['payments' => 'payment_account_id', 'expenses' => 'expense_account_id', 'revenues' => 'revenue_account_id'] as $table => $column) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM $table WHERE $column = $account_id"));
            $in_use = $in_use + intval($row['total'] ?? 0);
        }
        if (!$in_use) {
            mysqli_query($mysqli, "DELETE FROM accounts WHERE account_id = $account_id");
        }
    }

    // Tidy up the marker tag itself once nothing is wearing it
    $tag_id = demoDataTagId($mysqli);
    if ($tag_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(tag_id) AS total FROM client_tags WHERE tag_id = $tag_id"));
        if (!intval($row['total'] ?? 0)) {
            mysqli_query($mysqli, "DELETE FROM tags WHERE tag_id = $tag_id");
        }
    }

    return count($clients);
}

// ------------------------------
// demoBuildClient
// One client and everything that hangs off it. Order matters - locations and
// contacts come first because assets, tickets and networks point at them.
// ------------------------------
function demoBuildClient($mysqli, $profile, $index, $context, &$counts) {

    $currency = $context['currency'];
    $user_id = $context['user_id'];

    $client_id = starterInsert($mysqli, 'clients', [
        'client_name' => $profile['name'],
        'client_type' => $profile['type'],
        'client_website' => 'https://www.' . $profile['domain'],
        'client_referral' => $profile['referral'],
        'client_rate' => $profile['rate'],
        'client_currency_code' => $currency,
        'client_net_terms' => $profile['net_terms'],
        'client_abbreviation' => $profile['abbreviation'],
        'client_notes' => $profile['notes'],
        'client_created_at' => demoDateTime($profile['age_days'], 9, 14),
        'client_accessed_at' => demoDateTime($index % 5, 11, 20),
    ]);

    // The marker - this is what removal works off
    mysqli_query($mysqli, "INSERT INTO client_tags SET client_id = $client_id, tag_id = {$context['tag_id']}");
    demoAttachTags($mysqli, 'client_tags', 'client_id', 'tag_id', $client_id, 1, $profile['tags']);

    // Locations
    $location_ids = [];
    foreach ($profile['sites'] as $site_index => $site) {
        $location_ids[] = starterInsert($mysqli, 'locations', [
            'location_name' => $site['name'],
            'location_description' => $site['description'],
            'location_country' => 'United States',
            'location_address' => $site['address'],
            'location_city' => $site['city'],
            'location_state' => $site['state'],
            'location_zip' => $site['zip'],
            'location_phone_country_code' => '1',
            'location_phone' => $site['phone'],
            'location_hours' => $site['hours'],
            'location_primary' => $site_index === 0 ? 1 : 0,
            'location_client_id' => $client_id,
            'location_created_at' => demoDateTime($profile['age_days'], 9, 20),
        ]);
        demoAttachTags($mysqli, 'location_tags', 'location_id', 'tag_id', end($location_ids), 2, $site['tags']);
    }
    $primary_location_id = $location_ids[0] ?? 0;
    $counts['documentation'] += count($location_ids);

    // Contacts
    $contact_ids = [];
    foreach ($profile['people'] as $person_index => $person) {
        $email = demoContactEmail($person[0], $profile['domain']);
        $contact_id = starterInsert($mysqli, 'contacts', [
            'contact_name' => $person[0],
            'contact_title' => $person[1],
            'contact_email' => $email,
            'contact_phone_country_code' => '1',
            'contact_phone' => $profile['sites'][0]['phone'],
            'contact_extension' => (string)(100 + ($person_index * 7)),
            'contact_mobile_country_code' => '1',
            'contact_mobile' => demoMobileNumber($profile['area_code'], $index, $person_index),
            'contact_department' => $person[2],
            'contact_primary' => $person_index === 0 ? 1 : 0,
            'contact_important' => $person[3],
            'contact_billing' => $person[4],
            'contact_technical' => $person[5],
            'contact_notes' => $person[6],
            'contact_location_id' => $primary_location_id,
            'contact_client_id' => $client_id,
            'contact_created_at' => demoDateTime($profile['age_days'] - $person_index, 10, 5),
        ]);
        $contact_ids[] = $contact_id;
        demoAttachTags($mysqli, 'contact_tags', 'contact_id', 'tag_id', $contact_id, 3, $person[7]);
        $counts['contacts']++;
    }
    $primary_contact_id = $contact_ids[0] ?? 0;
    $technical_contact_id = $contact_ids[2] ?? $primary_contact_id;

    // A note on the client and on the technical contact
    starterInsert($mysqli, 'client_notes', [
        'client_note_type' => 'General',
        'client_note' => $profile['account_note'],
        'client_note_created_by' => $user_id,
        'client_note_client_id' => $client_id,
        'client_note_created_at' => demoDateTime(max(2, $profile['age_days'] - 30), 15, 40),
    ]);
    if ($technical_contact_id) {
        starterInsert($mysqli, 'contact_notes', [
            'contact_note_type' => 'General',
            'contact_note' => 'Main day to day contact for approvals and site access.',
            'contact_note_created_by' => $user_id,
            'contact_note_contact_id' => $technical_contact_id,
            'contact_note_created_at' => demoDateTime(max(2, $profile['age_days'] - 45), 11, 15),
        ]);
    }

    // Vendor the client owns (ISP, line of business software, etc)
    $vendor_id = starterInsert($mysqli, 'vendors', [
        'vendor_name' => $profile['vendor'][0],
        'vendor_description' => $profile['vendor'][1],
        'vendor_contact_name' => 'Business Support',
        'vendor_phone_country_code' => '1',
        'vendor_phone' => $profile['vendor'][2],
        'vendor_email' => 'support@' . $profile['vendor'][3],
        'vendor_website' => 'https://www.' . $profile['vendor'][3],
        'vendor_hours' => '24/7',
        'vendor_sla' => $profile['vendor'][4],
        'vendor_account_number' => demoSerial($profile['abbreviation'] . 'vendor', 8),
        'vendor_client_id' => $client_id,
        'vendor_created_at' => demoDateTime($profile['age_days'] - 5, 13, 0),
    ]);
    $counts['documentation']++;

    // Assets
    $asset_ids = demoBuildAssets($mysqli, $profile, $client_id, $primary_location_id, $contact_ids, $vendor_id, $counts);

    // Networks
    $network_index = 0;
    foreach ($profile['networks'] as $network) {
        starterInsert($mysqli, 'networks', [
            'network_name' => $network[0],
            'network_description' => $network[1],
            'network_vlan' => $network[2],
            'network' => $network[3],
            'network_subnet' => '24',
            'network_gateway' => $network[4],
            'network_primary_dns' => $network[5],
            'network_secondary_dns' => '1.1.1.1',
            'network_dhcp_range' => $network[6],
            'network_location_id' => $primary_location_id,
            'network_client_id' => $client_id,
            'network_created_at' => demoDateTime($profile['age_days'] - 8, 14, 30),
        ]);
        $network_index++;
        $counts['documentation']++;
    }

    // Domain and its certificate
    $domain_id = starterInsert($mysqli, 'domains', [
        'domain_name' => $profile['domain'],
        'domain_description' => 'Primary domain - website and email',
        'domain_expire' => demoDate(-1 * (60 + ($index * 21))),
        'domain_ip' => '203.0.113.' . (10 + $index),
        'domain_name_servers' => 'ns1.demo-registrar.example, ns2.demo-registrar.example',
        'domain_mail_servers' => $profile['mail_platform'],
        'domain_registrar' => $vendor_id,
        'domain_client_id' => $client_id,
        'domain_created_at' => demoDateTime($profile['age_days'] - 3, 9, 45),
    ]);

    starterInsert($mysqli, 'certificates', [
        'certificate_name' => 'www.' . $profile['domain'],
        'certificate_description' => 'Public website certificate',
        'certificate_domain' => 'www.' . $profile['domain'],
        'certificate_issued_by' => $index % 2 === 0 ? "Let's Encrypt" : 'DigiCert',
        'certificate_expire' => demoDate(-1 * (20 + ($index * 9))),
        'certificate_domain_id' => $domain_id,
        'certificate_client_id' => $client_id,
        'certificate_created_at' => demoDateTime($profile['age_days'] - 3, 9, 50),
    ]);
    $counts['documentation'] += 2;

    // Credentials - only when the vault actually opened for this session
    if ($context['vault_open']) {
        foreach ($profile['credentials'] as $credential) {
            $credential_id = starterInsert($mysqli, 'credentials', [
                'credential_name' => $credential[0],
                'credential_description' => $credential[1],
                'credential_uri' => $credential[2],
                'credential_username' => encryptCredentialEntry($credential[3]),
                'credential_password' => encryptCredentialEntry(demoSerial($profile['abbreviation'] . $credential[0], 14) . '!aA1'),
                'credential_note' => $credential[4],
                'credential_client_id' => $client_id,
                'credential_created_at' => demoDateTime($profile['age_days'] - 6, 16, 10),
                'credential_password_changed_at' => demoDateTime(45 + $index, 16, 10),
            ]);
            demoAttachTags($mysqli, 'credential_tags', 'credential_id', 'tag_id', $credential_id, 4, $credential[5]);
            $counts['documentation']++;
        }
    }

    // Services
    foreach ($profile['services'] as $service) {
        starterInsert($mysqli, 'services', [
            'service_name' => $service[0],
            'service_description' => $service[1],
            'service_category' => $service[2],
            'service_importance' => $service[3],
            'service_backup' => $service[4],
            'service_notes' => $service[5],
            'service_review_due' => demoDate(-1 * (90 + ($index * 5))),
            'service_client_id' => $client_id,
            'service_created_at' => demoDateTime($profile['age_days'] - 10, 10, 30),
        ]);
        $counts['documentation']++;
    }

    // Software and licensing
    foreach ($profile['software'] as $software) {
        $software_id = starterInsert($mysqli, 'software', [
            'software_name' => $software[0],
            'software_description' => $software[1],
            'software_version' => $software[2],
            'software_type' => $software[3],
            'software_license_type' => $software[4],
            'software_seats' => $software[5],
            'software_purchase_reference' => 'PO-' . demoSerial($profile['abbreviation'] . $software[0], 6),
            'software_purchase' => demoDate(300 + ($index * 3)),
            'software_expire' => demoDate(-1 * (65 + ($index * 11))),
            'software_notes' => $software[6],
            'software_vendor_id' => $vendor_id,
            'software_client_id' => $client_id,
            'software_created_at' => demoDateTime($profile['age_days'] - 12, 11, 45),
        ]);
        starterInsert($mysqli, 'software_keys', [
            'software_key' => demoLicenseKey($profile['abbreviation'] . $software[0]),
            'software_key_software_id' => $software_id,
        ]);
        $counts['documentation']++;
    }

    // Documentation, written at different points rather than all on day one
    $document_months = demoClientMonths($profile);
    foreach ($profile['documents'] as $document_index => $document) {
        $written_months_ago = max(0, $document_months - 1 - ($document_index * 6));
        starterInsert($mysqli, 'documents', [
            'document_name' => $document[0],
            'document_description' => $document[1],
            'document_content' => $document[2],
            'document_content_raw' => strip_tags($document[2]),
            'document_client_visible' => 0,
            'document_created_by' => $user_id,
            'document_updated_by' => $user_id,
            'document_client_id' => $client_id,
            'document_created_at' => demoMonthDateTime($written_months_ago, 12 + $document_index, 15, 20),
        ], ['document_content', 'document_content_raw']);
        $counts['documentation']++;
    }

    // Support and billing
    $project_id = demoBuildProjects($mysqli, $profile, $client_id, $context, $counts);
    demoBuildTickets($mysqli, $profile, $index, $client_id, $contact_ids, $asset_ids, $primary_location_id, $project_id, $context, $counts);
    demoBuildBilling($mysqli, $profile, $index, $client_id, $vendor_id, $context, $counts);

    // Calendar and mileage
    if ($context['calendar_id']) {
        starterInsert($mysqli, 'calendar_events', [
            'event_title' => $profile['abbreviation'] . ' - ' . $profile['onsite_visit'],
            'event_location' => $profile['sites'][0]['address'] . ', ' . $profile['sites'][0]['city'],
            'event_description' => 'Scheduled onsite visit',
            'event_start' => demoDateTime(-1 * (3 + $index), 10, 0),
            'event_end' => demoDateTime(-1 * (3 + $index), 12, 30),
            'event_client_id' => $client_id,
            'event_location_id' => $primary_location_id,
            'event_calendar_id' => $context['calendar_id'],
            'event_created_at' => demoDateTime(5, 9, 0),
        ]);
    }

    starterInsert($mysqli, 'trips', [
        'trip_date' => demoDate(12 + $index),
        'trip_purpose' => 'Onsite - ' . $profile['onsite_visit'],
        'trip_source' => 'Office',
        'trip_destination' => $profile['sites'][0]['city'] . ', ' . $profile['sites'][0]['state'],
        'trip_miles' => 8.5 + ($index * 2.5),
        'round_trip' => 1,
        'trip_user_id' => $user_id,
        'trip_client_id' => $client_id,
        'trip_created_at' => demoDateTime(12 + $index, 17, 30),
    ]);

}

// ------------------------------
// demoAttachTags
// Tags are matched to the library by name - anything not present is skipped
// rather than created, so the Tags starter pack stays the single source.
// NOTE asset_tags does not follow the convention the other four join tables
// use, hence the explicit tag column rather than a hard coded tag_id.
// ------------------------------
function demoAttachTags($mysqli, $join_table, $join_column, $tag_column, $row_id, $tag_type, $tag_names) {
    foreach ($tag_names as $tag_name) {
        $tag_name = escapeSql($tag_name);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_name = '$tag_name' AND tag_type = $tag_type LIMIT 1"));
        $tag_id = intval($row['tag_id'] ?? 0);
        if ($tag_id) {
            mysqli_query($mysqli, "INSERT INTO $join_table SET $join_column = $row_id, $tag_column = $tag_id");
        }
    }
}

// ------------------------------
// demoContactEmail
// ------------------------------
function demoContactEmail($name, $domain) {
    $parts = explode(' ', mb_strtolower($name));
    $first = $parts[0] ?? 'user';
    $last = end($parts);
    return mb_substr($first, 0, 1) . preg_replace('/[^a-z]/', '', $last) . '@' . $domain;
}

// ------------------------------
// demoMobileNumber
// 555-01xx is the reserved fictional range - nothing here can dial a real desk.
// ------------------------------
function demoMobileNumber($area_code, $index, $offset) {
    return $area_code . '-555-' . str_pad((string)(100 + ($index * 9) + $offset), 4, '0', STR_PAD_LEFT);
}

// ------------------------------
// demoLicenseKey
// ------------------------------
function demoLicenseKey($seed) {
    $raw = demoSerial($seed, 20);
    return implode('-', str_split($raw, 5));
}

// ------------------------------
// demoBuildAssets
// Builds the fleet from the profile's counts, named the way an MSP names kit.
// ------------------------------
function demoBuildAssets($mysqli, $profile, $client_id, $location_id, $contact_ids, $vendor_id, &$counts) {

    $models = demoAssetModels();
    $prefixes = demoAssetPrefixes();
    $asset_ids = [];
    $seed = 0;

    foreach ($profile['fleet'] as $asset_type => $quantity) {

        $type_models = $models[$asset_type] ?? [['Generic', 'Standard']];
        $prefix = $prefixes[$asset_type] ?? 'AST';

        for ($number = 1; $number <= $quantity; $number++) {

            $model = $type_models[($number - 1) % count($type_models)];
            $seed++;

            // Workstations and laptops belong to a person, infrastructure does not
            $contact_id = 0;
            if (in_array($asset_type, ['Desktop', 'Laptop', 'Mobile Phone', 'Tablet']) && $contact_ids) {
                $contact_id = $contact_ids[($number - 1) % count($contact_ids)];
            }

            $purchase_days = 400 + ($seed * 17);
            $status = 'Deployed';
            if ($number === $quantity && $quantity > 3) {
                $status = 'Ready to Deploy';
            }

            $asset_id = starterInsert($mysqli, 'assets', [
                'asset_type' => $asset_type,
                'asset_name' => $profile['abbreviation'] . '-' . $prefix . '-' . str_pad((string)$number, 2, '0', STR_PAD_LEFT),
                'asset_description' => demoAssetDescription($asset_type),
                'asset_make' => $model[0],
                'asset_model' => $model[1],
                'asset_serial' => demoSerial($profile['abbreviation'] . $asset_type . $number),
                'asset_os' => demoAssetOs($asset_type, $number),
                'asset_status' => $status,
                'asset_purchase_reference' => 'PO-' . demoSerial($profile['abbreviation'] . 'po' . $seed, 6),
                'asset_purchase_date' => demoDate($purchase_days),
                'asset_warranty_expire' => demoDate($purchase_days - 1095),
                'asset_install_date' => demoDate($purchase_days - 14),
                'asset_physical_location' => demoAssetPhysicalLocation($asset_type),
                'asset_vendor_id' => $vendor_id,
                'asset_location_id' => $location_id,
                'asset_contact_id' => $contact_id,
                'asset_client_id' => $client_id,
                'asset_created_at' => demoDateTime(max(1, $profile['age_days'] - 20), 12, 0),
            ]);

            $asset_ids[] = $asset_id;
            demoAttachTags($mysqli, 'asset_tags', 'asset_tag_asset_id', 'asset_tag_tag_id', $asset_id, 5, demoAssetTags($asset_type));
            $counts['assets']++;

        }
    }

    return $asset_ids;
}

// ------------------------------
// demoAssetDescription / demoAssetOs / demoAssetPhysicalLocation / demoAssetTags
// ------------------------------
function demoAssetDescription($asset_type) {
    $descriptions = [
        'Firewall/Router' => 'Edge firewall - site to site VPN and content filtering',
        'Switch' => 'Access layer switch - PoE for phones and access points',
        'Access Point' => 'Ceiling mounted wireless access point',
        'Server' => 'Line of business application and file server',
        'Desktop' => 'Standard staff workstation',
        'Laptop' => 'Mobile staff laptop',
        'Printer' => 'Shared multifunction printer',
        'Virtual Machine' => 'Virtual server guest',
        'Phone' => 'Desk handset',
        'Tablet' => 'Shared tablet',
        'Mobile Phone' => 'Company mobile handset',
        'Camera' => 'Site security camera',
    ];
    return $descriptions[$asset_type] ?? 'Client equipment';
}

function demoAssetOs($asset_type, $number) {
    $systems = [
        'Firewall/Router' => 'FortiOS 7.4.4',
        'Switch' => 'Managed switch firmware',
        'Access Point' => 'UniFi 7.0',
        'Server' => 'Windows Server 2022 Standard',
        'Desktop' => 'Windows 11 Pro 24H2',
        'Laptop' => 'Windows 11 Pro 24H2',
        'Printer' => 'Vendor firmware',
        'Virtual Machine' => 'Ubuntu Server 24.04 LTS',
        'Phone' => 'Vendor firmware',
        'Tablet' => 'iPadOS 18',
        'Mobile Phone' => 'iOS 18',
        'Camera' => 'Vendor firmware',
    ];
    if ($asset_type === 'Desktop' && $number % 4 === 0) {
        return 'Windows 10 Pro 22H2';
    }
    return $systems[$asset_type] ?? '';
}

function demoAssetPhysicalLocation($asset_type) {
    if (in_array($asset_type, ['Firewall/Router', 'Switch', 'Server', 'Virtual Machine'])) {
        return 'Server room rack';
    }
    if ($asset_type === 'Access Point') {
        return 'Ceiling - open office';
    }
    if ($asset_type === 'Printer') {
        return 'Copy room';
    }
    return 'Staff desk';
}

function demoAssetTags($asset_type) {
    $tags = [
        'Firewall/Router' => ['Firewall', 'Business Critical', 'Monitored'],
        'Switch' => ['Switch', 'Monitored'],
        'Access Point' => ['Access Point', 'Monitored'],
        'Server' => ['Server', 'Business Critical', 'Backed Up', 'Monitored'],
        'Desktop' => ['Workstation', 'Endpoint Protection'],
        'Laptop' => ['Laptop', 'Endpoint Protection'],
        'Printer' => ['Printer'],
        'Virtual Machine' => ['Server', 'Backed Up'],
        'Phone' => ['VoIP Handset'],
        'Tablet' => ['Mobile'],
        'Mobile Phone' => ['Mobile'],
    ];
    return $tags[$asset_type] ?? [];
}

// ------------------------------
// demoMonthDateTime / demoMonthDate
// Anchored to a month rather than a day count, so a two year run lands on
// sensible calendar months instead of drifting.
// ------------------------------
function demoMonthDateTime($months_ago, $day, $hour = 9, $minute = 0) {
    return date('Y-m-d H:i:s', mktime($hour, $minute, 0, date('n') - $months_ago, $day, date('Y')));
}

function demoMonthDate($months_ago, $day) {
    return date('Y-m-d', mktime(12, 0, 0, date('n') - $months_ago, $day, date('Y')));
}

// ------------------------------
// demoIsFuture
// Month anchored rows can land past today depending on what day of the month
// the load is run - a payroll run dated the 28th when it is the 24th. Money
// that has not happened yet has no business in the ledger, so it is skipped.
// ------------------------------
function demoIsFuture($date) {
    return strtotime($date) > time();
}

// ------------------------------
// demoClientMonths
// How many months of history a client gets - their whole life with us, capped
// at the two year window. Newer clients ramp up rather than appearing fully
// formed, which is what makes the trend charts worth looking at.
// ------------------------------
function demoClientMonths($profile) {
    return max(3, min(24, (int)floor($profile['age_days'] / 30)));
}

// ------------------------------
// demoBuildProjects
// A finished project behind them and, for some, one still running. Returns
// the live project ID so tickets can be filed against it.
// ------------------------------
function demoBuildProjects($mysqli, $profile, $client_id, $context, &$counts) {

    global $config_project_prefix;

    $user_id = $context['user_id'];
    $prefix = $config_project_prefix ?? '';
    $months = demoClientMonths($profile);
    $active_project_id = 0;

    // Something delivered last year, for the history
    if ($months >= 14) {
        starterInsert($mysqli, 'projects', [
            'project_prefix' => $prefix,
            'project_number' => demoNextNumber($mysqli, 'config_project_next_number'),
            'project_name' => 'Server and network refresh',
            'project_description' => 'Replaced end of life server hardware, rebuilt the switching and brought the site onto the standard build.',
            'project_due' => demoMonthDate(13, 20),
            'project_manager' => $user_id,
            'project_client_id' => $client_id,
            'project_created_at' => demoMonthDateTime(15, 4, 9, 30),
            'project_completed_at' => demoMonthDateTime(13, 18, 16, 45),
        ]);
        $counts['projects']++;
    }

    // And whatever is live now
    if (!empty($profile['project'])) {
        $active_project_id = starterInsert($mysqli, 'projects', [
            'project_prefix' => $prefix,
            'project_number' => demoNextNumber($mysqli, 'config_project_next_number'),
            'project_name' => $profile['project'][0],
            'project_description' => $profile['project'][1],
            'project_due' => demoMonthDate(-2, 15),
            'project_manager' => $user_id,
            'project_client_id' => $client_id,
            'project_created_at' => demoMonthDateTime(1, 8, 10, 0),
        ]);
        $counts['projects']++;
    }

    return $active_project_id;
}

// ------------------------------
// demoBuildTickets
// Two years of closed work behind a live queue. The history is what makes the
// ticket, time and technician reports worth opening; the six staged tickets at
// the front are what the queue and kanban look like today.
// ------------------------------
function demoBuildTickets($mysqli, $profile, $index, $client_id, $contact_ids, $asset_ids, $location_id, $project_id, $context, &$counts) {

    global $config_ticket_prefix;

    $user_id = $context['user_id'];
    $pool = demoTicketPool();
    $pool_size = count($pool);
    $prefix = $config_ticket_prefix ?? '';
    $months = demoClientMonths($profile);
    $sources = ['Email', 'Agent', 'Portal'];

    // Category IDs are the same handful over and over - look them up once
    $category_ids = [];
    foreach ($pool as $entry) {
        if (!isset($category_ids[$entry['category']])) {
            $category_ids[$entry['category']] = starterCategoryId($mysqli, $entry['category'], 'Ticket');
        }
    }

    // ---- Closed history, month by month ----
    $per_month = max(2, (int)floor($profile['seats'] / 10));
    $sequence = 0;

    for ($month = $months; $month >= 1; $month--) {
        for ($n = 0; $n < $per_month; $n++) {

            $sequence++;
            $ticket = $pool[(($index * 5) + $sequence) % $pool_size];
            $day = 2 + ((($index + $month + $n) * 7) % 25);
            $hour = 8 + (($sequence + $n) % 9);

            $contact_id = $contact_ids ? $contact_ids[$sequence % count($contact_ids)] : 0;
            $asset_id = $asset_ids ? $asset_ids[$sequence % count($asset_ids)] : 0;

            $ticket_id = starterInsert($mysqli, 'tickets', [
                'ticket_prefix' => $prefix,
                'ticket_number' => demoNextNumber($mysqli, 'config_ticket_next_number'),
                'ticket_source' => $sources[$sequence % 3],
                'ticket_category' => (string)$category_ids[$ticket['category']],
                'ticket_subject' => $ticket['subject'],
                'ticket_details' => $ticket['details'],
                'ticket_priority' => $ticket['priority'],
                'ticket_status' => $context['status_closed'],
                'ticket_billable' => $ticket['billable'],
                'ticket_url_key' => randomString(32),
                'ticket_created_at' => demoMonthDateTime($month, $day, $hour, 20),
                'ticket_first_response_at' => demoMonthDateTime($month, $day, $hour + 1, 5),
                'ticket_resolved_at' => demoMonthDateTime($month, $day + 1, 14, 30),
                'ticket_closed_at' => demoMonthDateTime($month, $day + 2, 9, 15),
                'ticket_created_by' => $user_id,
                'ticket_assigned_to' => $user_id,
                'ticket_closed_by' => $user_id,
                'ticket_contact_id' => $contact_id,
                'ticket_location_id' => $location_id,
                'ticket_asset_id' => $asset_id,
                'ticket_client_id' => $client_id,
            ], ['ticket_details']);

            if ($asset_id) {
                mysqli_query($mysqli, "INSERT INTO ticket_assets SET ticket_id = $ticket_id, asset_id = $asset_id");
            }

            // The agent reply carries the time, which is what the time reports read
            $worked = '';
            foreach ($ticket['replies'] as $reply) {
                if ($reply[0] !== 'Client' && !empty($reply[2])) {
                    $worked = $reply[2];
                    starterInsert($mysqli, 'ticket_replies', [
                        'ticket_reply' => $reply[1],
                        'ticket_reply_type' => $reply[0],
                        'ticket_reply_time_worked' => $reply[2],
                        'ticket_reply_by' => $user_id,
                        'ticket_reply_ticket_id' => $ticket_id,
                        'ticket_reply_created_at' => demoMonthDateTime($month, $day + 1, 13, 45),
                    ], ['ticket_reply']);
                    break;
                }
            }

            starterInsert($mysqli, 'ticket_history', [
                'ticket_history_status' => 'New',
                'ticket_history_description' => 'Ticket created',
                'ticket_history_ticket_id' => $ticket_id,
                'ticket_history_created_at' => demoMonthDateTime($month, $day, $hour, 20),
            ]);
            starterInsert($mysqli, 'ticket_history', [
                'ticket_history_status' => 'Resolved',
                'ticket_history_description' => 'Ticket resolved',
                'ticket_history_ticket_id' => $ticket_id,
                'ticket_history_created_at' => demoMonthDateTime($month, $day + 1, 14, 30),
            ]);
            starterInsert($mysqli, 'ticket_history', [
                'ticket_history_status' => 'Closed',
                'ticket_history_description' => 'Ticket closed',
                'ticket_history_ticket_id' => $ticket_id,
                'ticket_history_created_at' => demoMonthDateTime($month, $day + 2, 9, 15),
            ]);

            $counts['tickets']++;

        }
    }

    // ---- The live queue ----
    // created / first response / resolved / closed, in days back from today
    $stages = [
        ['status' => $context['status_closed'], 'created' => 74, 'response' => 74, 'resolved' => 71, 'closed' => 68],
        ['status' => $context['status_closed'], 'created' => 41, 'response' => 41, 'resolved' => 39, 'closed' => 36],
        ['status' => $context['status_resolved'], 'created' => 17, 'response' => 17, 'resolved' => 3, 'closed' => 0],
        ['status' => $context['status_open'], 'created' => 6, 'response' => 6, 'resolved' => 0, 'closed' => 0],
        ['status' => $index % 2 === 0 ? $context['status_hold'] : $context['status_open'], 'created' => 2, 'response' => 2, 'resolved' => 0, 'closed' => 0],
        ['status' => $context['status_new'], 'created' => 0, 'response' => 0, 'resolved' => 0, 'closed' => 0],
    ];

    foreach ($stages as $slot => $stage) {

        $ticket = $pool[(($index * 6) + $slot) % $pool_size];

        $contact_id = $contact_ids ? $contact_ids[$slot % count($contact_ids)] : 0;
        $asset_id = $asset_ids ? $asset_ids[($index + $slot) % count($asset_ids)] : 0;

        // The newest ticket is deliberately left unassigned - a real queue has one
        $assigned_to = $stage['status'] === $context['status_new'] ? 0 : $user_id;

        $fields = [
            'ticket_prefix' => $prefix,
            'ticket_number' => demoNextNumber($mysqli, 'config_ticket_next_number'),
            'ticket_source' => $sources[$slot % 3],
            'ticket_category' => (string)$category_ids[$ticket['category']],
            'ticket_subject' => $ticket['subject'],
            'ticket_details' => $ticket['details'],
            'ticket_priority' => $ticket['priority'],
            'ticket_status' => $stage['status'],
            'ticket_billable' => $ticket['billable'],
            'ticket_url_key' => randomString(32),
            'ticket_created_at' => demoDateTime($stage['created'], 8 + $slot, 25),
            'ticket_created_by' => $user_id,
            'ticket_assigned_to' => $assigned_to,
            'ticket_contact_id' => $contact_id,
            'ticket_location_id' => $location_id,
            'ticket_asset_id' => $asset_id,
            'ticket_client_id' => $client_id,
        ];

        // Hang a couple of the live tickets off the running project
        if ($project_id && in_array($slot, [3, 4])) {
            $fields['ticket_project_id'] = $project_id;
        }

        if ($stage['status'] !== $context['status_new']) {
            $fields['ticket_first_response_at'] = demoDateTime($stage['response'], 9 + $slot, 40);
        }
        if ($stage['resolved']) {
            $fields['ticket_resolved_at'] = demoDateTime($stage['resolved'], 15, 10);
        }
        if ($stage['closed']) {
            $fields['ticket_closed_at'] = demoDateTime($stage['closed'], 15, 30);
            $fields['ticket_closed_by'] = $user_id;
        }

        $ticket_id = starterInsert($mysqli, 'tickets', $fields, ['ticket_details']);
        $counts['tickets']++;

        if ($asset_id) {
            mysqli_query($mysqli, "INSERT INTO ticket_assets SET ticket_id = $ticket_id, asset_id = $asset_id");
        }

        // History - opened, worked, and where it ended up
        starterInsert($mysqli, 'ticket_history', [
            'ticket_history_status' => 'New',
            'ticket_history_description' => 'Ticket created',
            'ticket_history_ticket_id' => $ticket_id,
            'ticket_history_created_at' => demoDateTime($stage['created'], 8 + $slot, 25),
        ]);
        if ($stage['status'] !== $context['status_new']) {
            starterInsert($mysqli, 'ticket_history', [
                'ticket_history_status' => 'Open',
                'ticket_history_description' => 'Assigned and first response sent',
                'ticket_history_ticket_id' => $ticket_id,
                'ticket_history_created_at' => demoDateTime($stage['response'], 9 + $slot, 40),
            ]);
        }
        if ($stage['resolved']) {
            starterInsert($mysqli, 'ticket_history', [
                'ticket_history_status' => 'Resolved',
                'ticket_history_description' => 'Ticket resolved',
                'ticket_history_ticket_id' => $ticket_id,
                'ticket_history_created_at' => demoDateTime($stage['resolved'], 15, 10),
            ]);
        }

        // Replies - the client raising it, the agent working it, and internal notes
        $reply_day = $stage['created'];
        foreach ($ticket['replies'] as $reply_index => $reply) {

            $reply_day = max($stage['resolved'], $reply_day - 1);
            $reply_by = $reply[0] === 'Client' ? $contact_id : $user_id;

            $reply_fields = [
                'ticket_reply' => $reply[1],
                'ticket_reply_type' => $reply[0],
                'ticket_reply_by' => $reply_by,
                'ticket_reply_ticket_id' => $ticket_id,
                'ticket_reply_created_at' => demoDateTime($reply_day, 10 + $reply_index, 15),
            ];

            // Only agent time is billable time - client replies never book time
            if ($reply[0] !== 'Client' && !empty($reply[2])) {
                $reply_fields['ticket_reply_time_worked'] = $reply[2];
            }

            starterInsert($mysqli, 'ticket_replies', $reply_fields, ['ticket_reply']);
        }

        // Tasks on the live tickets only
        if (!empty($ticket['tasks']) && !$stage['closed']) {
            foreach ($ticket['tasks'] as $task_order => $task) {
                $task_fields = [
                    'task_name' => $task,
                    'task_order' => $task_order + 1,
                    'task_completion_estimate' => 15,
                    'task_ticket_id' => $ticket_id,
                    'task_created_at' => demoDateTime($stage['created'], 8 + $slot, 30),
                ];
                if ($task_order === 0) {
                    $task_fields['task_completed_at'] = demoDateTime(max(0, $stage['created'] - 1), 11, 0);
                    $task_fields['task_completed_by'] = $user_id;
                }
                starterInsert($mysqli, 'tasks', $task_fields);
            }
        }

    }

    // A standing recurring ticket - the monthly maintenance visit
    $recurring_category_id = starterCategoryId($mysqli, 'Maintenance', 'Ticket');
    $recurring_ticket_id = starterInsert($mysqli, 'recurring_tickets', [
        'recurring_ticket_category' => (string)$recurring_category_id,
        'recurring_ticket_subject' => $profile['recurring_ticket'],
        'recurring_ticket_details' => '<p>Scheduled maintenance visit. Work the task list, log time against this ticket, and note anything needing quoting.</p>',
        'recurring_ticket_priority' => 'Low',
        'recurring_ticket_frequency' => 'Monthly',
        'recurring_ticket_billable' => 0,
        'recurring_ticket_start_date' => demoMonthDate($months, 1),
        'recurring_ticket_next_run' => demoDate(-1 * (5 + ($index % 20))),
        'recurring_ticket_created_by' => $user_id,
        'recurring_ticket_assigned_to' => $user_id,
        'recurring_ticket_client_id' => $client_id,
        'recurring_ticket_contact_id' => $contact_ids[0] ?? 0,
        'recurring_ticket_created_at' => demoMonthDateTime($months, 1, 9, 0),
    ], ['recurring_ticket_details']);

    $recurring_tasks = ['Check backup job results', 'Review patch compliance', 'Check disk and memory headroom', 'Walk the site for anything obviously broken'];
    foreach ($recurring_tasks as $task_order => $task) {
        starterInsert($mysqli, 'recurring_ticket_tasks', [
            'recurring_ticket_task_name' => $task,
            'recurring_ticket_task_order' => $task_order + 1,
            'recurring_ticket_task_completion_estimate' => 15,
            'recurring_ticket_task_recurring_ticket_id' => $recurring_ticket_id,
        ]);
    }
    $counts['tickets']++;

}

// ------------------------------
// demoAssetModels / demoAssetPrefixes
// ------------------------------
function demoAssetModels() {
    return [
        'Firewall/Router' => [['Fortinet', 'FortiGate 60F'], ['SonicWall', 'TZ 470'], ['Ubiquiti', 'Dream Machine Pro']],
        'Switch' => [['Cisco', 'CBS350-24P'], ['Ubiquiti', 'USW-Pro-24-PoE'], ['NETGEAR', 'GS724TPv2']],
        'Access Point' => [['Ubiquiti', 'U6-Pro'], ['Cisco', 'CBW150AX'], ['RUCKUS', 'R550']],
        'Server' => [['Dell', 'PowerEdge R650'], ['HPE', 'ProLiant DL360 Gen10'], ['Dell', 'PowerEdge T440']],
        'Virtual Machine' => [['Proxmox', 'QEMU Guest'], ['VMware', 'vSphere Guest']],
        'Desktop' => [['Dell', 'OptiPlex 7010'], ['HP', 'ProDesk 600 G9'], ['Lenovo', 'ThinkCentre M70q']],
        'Laptop' => [['Dell', 'Latitude 5450'], ['HP', 'EliteBook 840 G10'], ['Lenovo', 'ThinkPad T14 Gen 4']],
        'Printer' => [['HP', 'LaserJet Pro M404dn'], ['Brother', 'MFC-L8900CDW'], ['Canon', 'imageRUNNER 1643i']],
        'Phone' => [['Yealink', 'T46U'], ['Poly', 'VVX 450']],
        'Tablet' => [['Apple', 'iPad 10th Gen'], ['Samsung', 'Galaxy Tab A9']],
        'Mobile Phone' => [['Apple', 'iPhone 14'], ['Samsung', 'Galaxy S23']],
        'Camera' => [['Ubiquiti', 'G4 Bullet'], ['Axis', 'M2036-LE']],
    ];
}

function demoAssetPrefixes() {
    return [
        'Firewall/Router' => 'FW',
        'Switch' => 'SW',
        'Access Point' => 'AP',
        'Server' => 'SRV',
        'Virtual Machine' => 'VM',
        'Desktop' => 'WS',
        'Laptop' => 'LT',
        'Printer' => 'PRN',
        'Phone' => 'PH',
        'Tablet' => 'TAB',
        'Mobile Phone' => 'MOB',
        'Camera' => 'CAM',
    ];
}

// ------------------------------
// demoTicketPool
// The work an MSP actually sees. Each client draws six at a rolling offset, so
// the same issue turning up at more than one client is intentional.
// ------------------------------
function demoTicketPool() {
    return [
        [
            'subject' => 'Outlook stuck on disconnected after password reset',
            'category' => 'Microsoft 365',
            'priority' => 'Medium',
            'billable' => 1,
            'details' => '<p>User reset their password this morning and Outlook has been sat on Disconnected since. Webmail works fine.</p>',
            'replies' => [
                ['Client', '<p>Still not connecting after a reboot. Can someone take a look today?</p>', ''],
                ['Public', '<p>Cleared the cached credential in Credential Manager and re-authenticated the profile. Mail is flowing again - let me know if it drops.</p>', '00:25:00'],
                ['Internal', '<p>Stale credential left over from the reset. Worth adding to the password change checklist.</p>', '00:05:00'],
            ],
            'tasks' => ['Clear cached credentials', 'Re-authenticate profile', 'Confirm mail flow with user'],
        ],
        [
            'subject' => 'Workstation running very slowly since last week',
            'category' => 'Workstation',
            'priority' => 'Low',
            'billable' => 1,
            'details' => '<p>Machine takes several minutes to get to a usable desktop and browsing is sluggish once it does.</p>',
            'replies' => [
                ['Public', '<p>Remoted on - disk was at 100% with a stalled update. Cleared the update cache, applied pending patches and rebooted. Boot time is back to normal.</p>', '00:45:00'],
                ['Internal', '<p>Drive is a spinning disk and is on the refresh list. Flagged for the next hardware quote.</p>', '00:10:00'],
            ],
            'tasks' => ['Check resource usage', 'Clear update cache', 'Confirm with user'],
        ],
        [
            'subject' => 'Cannot print to the front office printer',
            'category' => 'Printer',
            'priority' => 'Medium',
            'billable' => 0,
            'details' => '<p>Nothing comes out from any of the front desk machines. The printer display shows ready.</p>',
            'replies' => [
                ['Client', '<p>It was working yesterday afternoon.</p>', ''],
                ['Public', '<p>The printer had picked up a new DHCP address. Set a reservation on the firewall and repointed the print queues. Test pages came out on all three machines.</p>', '00:35:00'],
            ],
            'tasks' => ['Check printer IP', 'Set DHCP reservation', 'Repoint print queues'],
        ],
        [
            'subject' => 'Site internet dropping intermittently',
            'category' => 'Network',
            'priority' => 'High',
            'billable' => 1,
            'details' => '<p>Connection drops for a minute or two several times an hour. Affecting card processing and phones.</p>',
            'replies' => [
                ['Public', '<p>Logged into the firewall - WAN interface is flapping. Opened a ticket with the ISP and gave them the timestamps.</p>', '00:40:00'],
                ['Internal', '<p>ISP found a bad line card at the pole. Replacement scheduled. Monitor for another 48 hours before closing.</p>', '00:15:00'],
            ],
            'tasks' => ['Pull firewall WAN logs', 'Raise ISP ticket', 'Monitor for 48 hours'],
        ],
        [
            'subject' => 'New starter setup - workstation, mailbox and access',
            'category' => 'Onboarding',
            'priority' => 'Medium',
            'billable' => 1,
            'details' => '<p>New hire starting a week Monday. Needs the standard build, a mailbox and access to the shared drives.</p>',
            'replies' => [
                ['Public', '<p>Machine is built and joined, mailbox is licensed, and the group memberships match the department template. Credentials will be handed over on their first morning.</p>', '01:30:00'],
            ],
            'tasks' => ['Build and join workstation', 'Create mailbox and assign licence', 'Apply department group memberships', 'Handover on day one'],
        ],
        [
            'subject' => 'Suspicious invoice email reported by staff',
            'category' => 'Phishing Report',
            'priority' => 'High',
            'billable' => 0,
            'details' => '<p>Staff member forwarded an emailed invoice from what looks like a supplier address but with a changed bank account.</p>',
            'replies' => [
                ['Client', '<p>Nobody clicked anything as far as I know. Wanted to flag it straight away.</p>', ''],
                ['Public', '<p>Confirmed a spoofed display name from an external domain. Blocked the sender, checked mail rules on the mailbox, and confirmed no forwarding rules were added. Good catch reporting it.</p>', '00:50:00'],
                ['Internal', '<p>Worth a short reminder to the finance team about verifying bank detail changes by phone.</p>', '00:10:00'],
            ],
            'tasks' => ['Confirm sender and headers', 'Block sender', 'Audit mailbox rules', 'Confirm no credential entry'],
        ],
        [
            'subject' => 'Backup job reporting failures overnight',
            'category' => 'Backup and Recovery',
            'priority' => 'High',
            'billable' => 0,
            'details' => '<p>Backup console has flagged three consecutive failed runs on the file server.</p>',
            'replies' => [
                ['Public', '<p>Snapshot was failing on a locked database file. Excluded the live file and added the database dump to the job. Last two runs are green and a test restore completed.</p>', '01:10:00'],
                ['Internal', '<p>Restore test evidence saved with the client documentation for the compliance file.</p>', '00:15:00'],
            ],
            'tasks' => ['Review job logs', 'Adjust job scope', 'Run a test restore', 'File restore evidence'],
        ],
        [
            'subject' => 'Wireless dropping in the back offices',
            'category' => 'Wireless',
            'priority' => 'Medium',
            'billable' => 1,
            'details' => '<p>Staff at the far end of the building lose wireless when they move between rooms.</p>',
            'replies' => [
                ['Public', '<p>Survey shows a dead spot behind the store room. Adjusted transmit power on the nearest access point as a stopgap and quoted an additional unit for proper coverage.</p>', '01:00:00'],
            ],
            'tasks' => ['Survey coverage', 'Adjust AP power', 'Quote additional access point'],
        ],
        [
            'subject' => 'Shared drive permissions wrong after department move',
            'category' => 'Account and Access',
            'priority' => 'Medium',
            'billable' => 1,
            'details' => '<p>Two staff moved departments and can still see their old folders but not the new ones.</p>',
            'replies' => [
                ['Public', '<p>Group memberships were updated but the sessions had not refreshed. Corrected the groups, removed the leftover membership and confirmed access after sign out and back in.</p>', '00:30:00'],
            ],
            'tasks' => ['Audit group membership', 'Correct permissions', 'Confirm access with both users'],
        ],
        [
            'subject' => 'Server disk space warning',
            'category' => 'Monitoring Alert',
            'priority' => 'High',
            'billable' => 0,
            'details' => '<p>Monitoring raised a low disk space alert on the system volume - under 8% free.</p>',
            'replies' => [
                ['Public', '<p>Cleared shadow copies and old update files, which recovered enough headroom for now. Recommend extending the volume at the next maintenance window.</p>', '00:40:00'],
                ['Internal', '<p>Volume extension needs a short reboot - put it on the next scheduled visit.</p>', '00:05:00'],
            ],
            'tasks' => ['Free immediate space', 'Identify growth', 'Schedule volume extension'],
        ],
        [
            'subject' => 'Staff member leaving - offboarding',
            'category' => 'Offboarding',
            'priority' => 'Medium',
            'billable' => 1,
            'details' => '<p>Leaving at the end of the week. Mailbox needs to be delegated to their manager and the laptop collected.</p>',
            'replies' => [
                ['Public', '<p>Account disabled at close of business, mailbox converted and delegated, licence reclaimed and the laptop wiped and returned to spare stock.</p>', '01:15:00'],
            ],
            'tasks' => ['Disable account', 'Delegate mailbox', 'Reclaim licence', 'Collect and wipe laptop'],
        ],
        [
            'subject' => 'Phones not ringing at the front desk',
            'category' => 'Phone and VoIP',
            'priority' => 'High',
            'billable' => 1,
            'details' => '<p>Inbound calls go straight to voicemail on the front desk handsets.</p>',
            'replies' => [
                ['Public', '<p>The ring group had lost one of its members after a handset was swapped. Re-added the extension and tested inbound from an outside line.</p>', '00:35:00'],
            ],
            'tasks' => ['Check ring group', 'Re-add extension', 'Test inbound call'],
        ],
        [
            'subject' => 'Line of business application will not launch',
            'category' => 'Line of Business Application',
            'priority' => 'High',
            'billable' => 1,
            'details' => '<p>Application closes immediately on launch for two users since this morning.</p>',
            'replies' => [
                ['Client', '<p>Everyone else seems fine. It is just the two of us.</p>', ''],
                ['Public', '<p>Vendor client version was behind the server after their update. Updated both machines to the matching build and confirmed login.</p>', '00:55:00'],
                ['Internal', '<p>Vendor pushes server side updates without notice - worth asking them to add us to the release list.</p>', '00:10:00'],
            ],
            'tasks' => ['Confirm client version', 'Update to matching build', 'Confirm login with both users'],
        ],
        [
            'subject' => 'Laptop screen cracked - needs replacement',
            'category' => 'Hardware Failure',
            'priority' => 'Medium',
            'billable' => 1,
            'details' => '<p>Laptop was dropped in transit. Screen is cracked and the display is unusable.</p>',
            'replies' => [
                ['Public', '<p>Unit is out of warranty. Quoted a replacement and issued a loaner from spare stock so they can keep working.</p>', '00:45:00'],
            ],
            'tasks' => ['Check warranty status', 'Issue loaner', 'Quote replacement'],
        ],
        [
            'subject' => 'Multi factor prompts looping on mobile',
            'category' => 'Account and Access',
            'priority' => 'Medium',
            'billable' => 0,
            'details' => '<p>Authenticator prompts keep repeating and never complete on the user mobile.</p>',
            'replies' => [
                ['Public', '<p>Removed and re-registered the authenticator entry, then confirmed sign in on both mobile and desktop.</p>', '00:30:00'],
            ],
            'tasks' => ['Re-register authenticator', 'Confirm sign in'],
        ],
        [
            'subject' => 'Quote request - workstation refresh',
            'category' => 'Procurement',
            'priority' => 'Low',
            'billable' => 0,
            'details' => '<p>Client has asked for pricing on replacing the oldest machines in the office.</p>',
            'replies' => [
                ['Public', '<p>Pulled the asset list and put together pricing for the machines past five years old. Quote sent for review.</p>', '00:40:00'],
            ],
            'tasks' => ['Pull asset age report', 'Build quote', 'Send for approval'],
        ],
    ];
}

// ------------------------------
// demoDataProfiles
// Ten fictional clients. Domains sit on the RFC 2606 reserved .example TLD on
// purpose - demo contacts must never be deliverable and a demo domain must
// never resolve to somebody's real business if a refresh gets clicked.
// The compact spec below is expanded into full profiles underneath it.
// ------------------------------
function demoDataProfiles() {

    $specs = [
        [
            'name' => 'Ravenwood Dental Group', 'abbreviation' => 'RDG', 'type' => 'Healthcare',
            'domain' => 'ravenwooddental.example', 'referral' => 'Client', 'rate' => 145.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15212', 'area' => '412', 'street' => '1140 Ridge Avenue',
            'second_site' => ['Wexford Practice', '3025 Church Road', 'Wexford', 'PA', '15090'],
            'age_days' => 880, 'seats' => 26, 'servers' => 2,
            'tags' => ['Managed', 'Key Account', 'Compliance', 'Multi Site'],
            'notes' => 'Two practices sharing one patient system. Any work touching the imaging server needs to be out of clinic hours.',
            'account_note' => 'Practice owner signs off anything over $500. Quarterly review is booked with the office manager, not the owner.',
            'onsite_visit' => 'Quarterly review and maintenance',
            'recurring_ticket' => 'Monthly maintenance visit - Ravenwood Dental',
            'project' => ['Imaging server replacement', 'Replace the end of life imaging server and migrate the patient imaging database with no clinic downtime.'],
            'vendor' => ['Keystone Business Fiber', 'Primary internet and failover circuit', '412-555-0140', 'keystonefiber.example', '4 hour response'],
            'mail_platform' => 'Microsoft 365',
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 4, 'Server' => 2, 'Desktop' => 14, 'Laptop' => 3, 'Printer' => 3, 'Phone' => 8],
            'people' => [
                ['Marla Whitfield', 'Practice Owner', 'Management', 1, 1, 0, 'Signs off spend. Prefers a phone call over email.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Dana Prescott', 'Office Manager', 'Administration', 1, 1, 0, 'Day to day contact for scheduling and invoices.', ['Billing', 'Onsite Point of Contact']],
                ['Owen Castellano', 'Operations Lead', 'Operations', 0, 0, 1, 'Knows the imaging system better than the vendor does.', ['Technical', 'After Hours']],
                ['Priya Raghavan', 'Front Desk Supervisor', 'Reception', 0, 0, 0, 'First to report anything broken at the front desk.', []],
            ],
        ],
        [
            'name' => 'Kestrel Precision Manufacturing', 'abbreviation' => 'KPM', 'type' => 'Manufacturing',
            'domain' => 'kestrelprecision.example', 'referral' => 'Networking Group', 'rate' => 155.00, 'terms' => 30,
            'city' => 'Erie', 'state' => 'PA', 'zip' => '16510', 'area' => '814', 'street' => '4400 Foundry Way',
            'second_site' => ['Plant Two', '820 Industrial Parkway', 'Erie', 'PA', '16511'],
            'age_days' => 1240, 'seats' => 42, 'servers' => 4,
            'tags' => ['Managed', 'Key Account', 'Multi Site', 'After Hours Support'],
            'notes' => 'Shop floor runs two shifts. Anything touching the ERP has to wait for the Sunday window.',
            'account_note' => 'Plant manager owns the maintenance window. ERP vendor must be on any call involving the production database.',
            'onsite_visit' => 'Shop floor equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Kestrel Precision',
            'project' => ['Plant Two network rebuild', 'Replace the unmanaged switching in Plant Two, add VLAN separation for shop floor equipment and extend wireless to the yard.'],
            'vendor' => ['Lakeshore Telecom', 'Fibre circuits for both plants', '814-555-0175', 'lakeshoretelecom.example', 'Next business day'],
            'mail_platform' => 'Microsoft 365',
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 6, 'Access Point' => 8, 'Server' => 3, 'Virtual Machine' => 4, 'Desktop' => 22, 'Laptop' => 9, 'Printer' => 4, 'Phone' => 14, 'Camera' => 6],
            'people' => [
                ['Grant Holloway', 'Operations Director', 'Management', 1, 0, 0, 'Approves project work and downtime windows.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Renata Vasquez', 'Finance Manager', 'Finance', 0, 1, 0, 'Handles purchase orders - invoices need a PO number.', ['Billing']],
                ['Tobias Lindqvist', 'Maintenance Supervisor', 'Operations', 1, 0, 1, 'Site contact for anything on the shop floor.', ['Technical', 'Onsite Point of Contact', 'After Hours']],
                ['Alicia Freeman', 'Quality Coordinator', 'Quality', 0, 0, 0, 'Owns the audit paperwork that our documentation feeds.', ['Emergency']],
            ],
        ],
        [
            'name' => 'Blackburn and Meyer LLP', 'abbreviation' => 'BML', 'type' => 'Legal',
            'domain' => 'blackburnmeyer.example', 'referral' => 'Partner', 'rate' => 165.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15222', 'area' => '412', 'street' => '600 Grant Street, Suite 1900',
            'second_site' => null,
            'age_days' => 700, 'seats' => 18, 'servers' => 2,
            'tags' => ['Managed', 'Compliance', 'Cyber Insurance', 'Key Account'],
            'notes' => 'Cyber insurance renewal drives most of the security work here. Everything is documented for the questionnaire.',
            'account_note' => 'Managing partner wants a written summary after any security incident, however small.',
            'onsite_visit' => 'Security review and staff walkthrough',
            'recurring_ticket' => 'Monthly maintenance visit - Blackburn and Meyer',
            'project' => null,
            'vendor' => ['Meridian Legal Systems', 'Practice management platform', '412-555-0188', 'meridianlegal.example', '8x5 support'],
            'mail_platform' => 'Microsoft 365',
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 3, 'Server' => 2, 'Desktop' => 11, 'Laptop' => 8, 'Printer' => 2, 'Phone' => 12],
            'people' => [
                ['Eleanor Blackburn', 'Managing Partner', 'Management', 1, 1, 0, 'Final say on anything security related.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Curtis Meyer', 'Partner', 'Management', 1, 0, 0, 'Travels constantly - mobile is the reliable route.', ['Executive']],
                ['Sylvia Nakamura', 'Practice Administrator', 'Administration', 1, 1, 1, 'Runs the office and the practice management system.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Devon Ashcroft', 'Paralegal', 'Legal', 0, 0, 0, 'Reports issues on behalf of the paralegal team.', []],
            ],
        ],
        [
            'name' => 'Harbor Point Credit Union', 'abbreviation' => 'HPCU', 'type' => 'Financial Services',
            'domain' => 'harborpointcu.example', 'referral' => 'Chamber of Commerce', 'rate' => 175.00, 'terms' => 30,
            'city' => 'Cleveland', 'state' => 'OH', 'zip' => '44113', 'area' => '216', 'street' => '1250 Lakeside Avenue',
            'second_site' => ['Parma Branch', '7300 Ridge Road', 'Parma', 'OH', '44129'],
            'age_days' => 1500, 'seats' => 34, 'servers' => 3,
            'tags' => ['Managed', 'Compliance', 'Cyber Insurance', 'After Hours Support', 'Multi Site'],
            'notes' => 'Regulated environment. Change control paperwork is required before anything touches the core banking network.',
            'account_note' => 'Annual examination in the autumn - documentation and restore evidence must be current before then.',
            'onsite_visit' => 'Branch equipment and compliance check',
            'recurring_ticket' => 'Monthly maintenance visit - Harbor Point CU',
            'project' => ['Branch firewall standardisation', 'Bring both branches onto the same firewall platform with central logging and documented rule sets for the examination.'],
            'vendor' => ['Northcoast Data Services', 'Core banking platform hosting', '216-555-0122', 'northcoastdata.example', '24/7 with 1 hour response'],
            'mail_platform' => 'Microsoft 365',
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 4, 'Access Point' => 5, 'Server' => 3, 'Virtual Machine' => 3, 'Desktop' => 24, 'Laptop' => 6, 'Printer' => 4, 'Phone' => 20, 'Camera' => 8],
            'people' => [
                ['Vernon Ashby', 'Chief Executive', 'Management', 1, 0, 0, 'Sees the quarterly summary, not the day to day.', ['Primary', 'Executive']],
                ['Karen Doyle', 'Controller', 'Finance', 0, 1, 0, 'Owns the budget and the invoices.', ['Billing', 'Authorized Approver']],
                ['Miguel Santoro', 'Operations Manager', 'Operations', 1, 0, 1, 'Coordinates change control and branch access.', ['Technical', 'Onsite Point of Contact', 'After Hours']],
                ['Hannah Brightwell', 'Branch Manager - Parma', 'Operations', 0, 0, 0, 'Point of contact for the Parma branch.', ['Emergency']],
            ],
        ],
        [
            'name' => 'Sinclair Property Group', 'abbreviation' => 'SPG', 'type' => 'Real Estate',
            'domain' => 'sinclairproperty.example', 'referral' => 'Website', 'rate' => 135.00, 'terms' => 30,
            'city' => 'Columbus', 'state' => 'OH', 'zip' => '43215', 'area' => '614', 'street' => '88 East Broad Street',
            'second_site' => null,
            'age_days' => 520, 'seats' => 15, 'servers' => 1,
            'tags' => ['Co-Managed', 'Managed'],
            'notes' => 'Client has an internal person who handles first line. We pick up escalations and all infrastructure work.',
            'account_note' => 'Internal IT contact triages first - tickets should come through them rather than direct from staff.',
            'onsite_visit' => 'Office equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Sinclair Property',
            'project' => null,
            'vendor' => ['Buckeye Broadband Business', 'Office internet', '614-555-0166', 'buckeyebusiness.example', 'Next business day'],
            'mail_platform' => 'Google Workspace',
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 3, 'Server' => 1, 'Desktop' => 8, 'Laptop' => 7, 'Printer' => 2, 'Mobile Phone' => 6],
            'people' => [
                ['Roland Sinclair', 'Principal', 'Management', 1, 1, 0, 'Owner. Wants short answers and no jargon.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Beatrix Nolan', 'Office Manager', 'Administration', 0, 1, 0, 'Handles invoices and supplier accounts.', ['Billing']],
                ['Jeremy Okafor', 'Internal IT Coordinator', 'Operations', 1, 0, 1, 'Co-managed counterpart - handles first line internally.', ['Technical', 'Onsite Point of Contact']],
                ['Nadia Kellerman', 'Lettings Coordinator', 'Operations', 0, 0, 0, 'Field staff - mostly mobile issues.', []],
            ],
        ],
        [
            'name' => 'Northgate Veterinary Clinic', 'abbreviation' => 'NVC', 'type' => 'Veterinary',
            'domain' => 'northgatevet.example', 'referral' => 'Friend', 'rate' => 125.00, 'terms' => 15,
            'city' => 'Wexford', 'state' => 'PA', 'zip' => '15090', 'area' => '724', 'street' => '910 Perry Highway',
            'second_site' => null,
            'age_days' => 400, 'seats' => 12, 'servers' => 1,
            'tags' => ['Break Fix', 'Managed'],
            'notes' => 'Small practice, price sensitive. Moved onto a light agreement after a hardware failure took them down for a day.',
            'account_note' => 'Was break fix until the server failure. Watch for scope creep against the light agreement.',
            'onsite_visit' => 'Practice equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Northgate Veterinary',
            'project' => null,
            'vendor' => ['Allegheny Cable Business', 'Internet and phone', '724-555-0119', 'alleghenybusiness.example', 'Best effort'],
            'mail_platform' => 'Microsoft 365',
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 1, 'Access Point' => 2, 'Server' => 1, 'Desktop' => 7, 'Laptop' => 2, 'Printer' => 2, 'Tablet' => 3],
            'people' => [
                ['Dr Imogen Hartley', 'Practice Owner', 'Management', 1, 1, 0, 'Owner and lead vet. Best reached between appointments.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Cassidy Boyle', 'Practice Manager', 'Administration', 1, 1, 1, 'Handles everything administrative and technical on site.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Elliot Marsh', 'Veterinary Nurse', 'Clinical', 0, 0, 0, 'Reports issues from the treatment rooms.', []],
                ['Georgia Pemberton', 'Reception Lead', 'Reception', 0, 0, 0, 'Front desk and booking system.', []],
            ],
        ],
        [
            'name' => 'Delaney Construction', 'abbreviation' => 'DLC', 'type' => 'Construction',
            'domain' => 'delaneyconstruction.example', 'referral' => 'Client', 'rate' => 140.00, 'terms' => 30,
            'city' => 'Greensburg', 'state' => 'PA', 'zip' => '15601', 'area' => '724', 'street' => '2200 Route 30 East',
            'second_site' => ['Yard and Workshop', '145 Depot Street', 'Latrobe', 'PA', '15650'],
            'age_days' => 640, 'seats' => 22, 'servers' => 1,
            'tags' => ['Managed', 'Multi Site'],
            'notes' => 'Field staff work off laptops and tablets with patchy connectivity. Site offices come and go.',
            'account_note' => 'Field kit takes a beating - replacements are expected rather than exceptional. Keep spares on the shelf.',
            'onsite_visit' => 'Office and yard equipment check',
            'recurring_ticket' => 'Monthly maintenance visit - Delaney Construction',
            'project' => null,
            'vendor' => ['Westmoreland Wireless', 'Site office connectivity', '724-555-0154', 'westmorelandwireless.example', 'Next business day'],
            'mail_platform' => 'Microsoft 365',
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 2, 'Access Point' => 4, 'Server' => 1, 'Desktop' => 6, 'Laptop' => 14, 'Printer' => 2, 'Tablet' => 6, 'Mobile Phone' => 10],
            'people' => [
                ['Fiona Delaney', 'Owner', 'Management', 1, 1, 0, 'Runs the business. Usually on a site rather than at a desk.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Warren Tiller', 'Commercial Manager', 'Finance', 0, 1, 0, 'Approves invoices and equipment purchases.', ['Billing']],
                ['Sasha Nowak', 'Site Systems Coordinator', 'Operations', 1, 0, 1, 'Sets up and strips down site office kit.', ['Technical', 'Onsite Point of Contact']],
                ['Bram Whitaker', 'Project Supervisor', 'Operations', 0, 0, 0, 'Field escalation for laptops and tablets.', ['After Hours']],
            ],
        ],
        [
            'name' => 'Aurora Wellness Center', 'abbreviation' => 'AWC', 'type' => 'Healthcare',
            'domain' => 'aurorawellness.example', 'referral' => 'Social Media', 'rate' => 130.00, 'terms' => 15,
            'city' => 'Morgantown', 'state' => 'WV', 'zip' => '26505', 'area' => '304', 'street' => '415 Chestnut Ridge Road',
            'second_site' => null,
            'age_days' => 300, 'seats' => 16, 'servers' => 1,
            'tags' => ['Managed', 'Onboarding', 'Compliance'],
            'notes' => 'Recently onboarded. Documentation is still being built out as we find things.',
            'account_note' => 'Onboarding discovery is not finished - expect gaps in the asset list for another month.',
            'onsite_visit' => 'Onboarding discovery visit',
            'recurring_ticket' => 'Monthly maintenance visit - Aurora Wellness',
            'project' => ['Onboarding and documentation', 'Complete discovery, document the environment, standardise endpoint protection and get backups verified.'],
            'vendor' => ['Mountaineer Connect', 'Internet and managed wifi', '304-555-0133', 'mountaineerconnect.example', '4 hour response'],
            'mail_platform' => 'Google Workspace',
            'fleet' => ['Firewall/Router' => 1, 'Switch' => 2, 'Access Point' => 4, 'Server' => 1, 'Desktop' => 9, 'Laptop' => 4, 'Printer' => 2, 'Tablet' => 4],
            'people' => [
                ['Talia Mendoza', 'Clinic Director', 'Management', 1, 1, 0, 'New client contact - still learning what we do.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Ruth Ellery', 'Business Manager', 'Administration', 0, 1, 0, 'Invoices and supplier accounts.', ['Billing']],
                ['Colm Bradigan', 'Facilities Coordinator', 'Operations', 1, 0, 1, 'Holds the keys and the cupboard the switch lives in.', ['Technical', 'Onsite Point of Contact']],
                ['Yusuf Karim', 'Therapist', 'Clinical', 0, 0, 0, 'Reports issues in the treatment rooms.', []],
            ],
        ],
        [
            'name' => 'Pinnacle Freight Systems', 'abbreviation' => 'PFS', 'type' => 'Logistics',
            'domain' => 'pinnaclefreight.example', 'referral' => 'Cold Outreach', 'rate' => 150.00, 'terms' => 30,
            'city' => 'Youngstown', 'state' => 'OH', 'zip' => '44505', 'area' => '330', 'street' => '3300 Logistics Drive',
            'second_site' => ['Distribution Warehouse', '760 Terminal Road', 'Youngstown', 'OH', '44506'],
            'age_days' => 1050, 'seats' => 30, 'servers' => 3,
            'tags' => ['Managed', 'After Hours Support', 'Multi Site', 'Past Due'],
            'notes' => 'Warehouse runs around the clock. Scanner and label printer issues are the bulk of the ticket volume.',
            'account_note' => 'Account has run past terms twice this year - finance chases rather than the account manager.',
            'onsite_visit' => 'Warehouse scanner and network check',
            'recurring_ticket' => 'Monthly maintenance visit - Pinnacle Freight',
            'project' => null,
            'vendor' => ['Mahoning Valley Fiber', 'Warehouse and office circuits', '330-555-0147', 'mahoningfiber.example', '24/7 with 2 hour response'],
            'mail_platform' => 'Microsoft 365',
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 5, 'Access Point' => 9, 'Server' => 3, 'Virtual Machine' => 2, 'Desktop' => 16, 'Laptop' => 6, 'Printer' => 5, 'Phone' => 12, 'Tablet' => 8],
            'people' => [
                ['Desmond Iverson', 'General Manager', 'Management', 1, 0, 0, 'Approves spend and downtime.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Lorraine Pike', 'Accounts Payable', 'Finance', 0, 1, 0, 'Pays on their own schedule - chase politely.', ['Billing']],
                ['Ike Ferrante', 'Warehouse Systems Lead', 'Operations', 1, 0, 1, 'Night shift contact for scanner and label printer problems.', ['Technical', 'After Hours', 'Onsite Point of Contact']],
                ['Simone Vasser', 'Dispatch Supervisor', 'Operations', 0, 0, 0, 'Raises most of the day to day tickets.', ['Emergency']],
            ],
        ],
        [
            'name' => 'Copperline Coffee Roasters', 'abbreviation' => 'CCR', 'type' => 'Retail',
            'domain' => 'copperlinecoffee.example', 'referral' => 'Event', 'rate' => 120.00, 'terms' => 15,
            'city' => 'Pittsburgh', 'state' => 'PA', 'zip' => '15201', 'area' => '412', 'street' => '2115 Butler Street',
            'second_site' => ['Roastery', '48 Preble Avenue', 'Pittsburgh', 'PA', '15233'],
            'age_days' => 180, 'seats' => 9, 'servers' => 0,
            'tags' => ['Break Fix', 'Onboarding', 'Multi Site'],
            'notes' => 'Two retail sites and a roastery. Point of sale and card processing are the only things that really matter to them.',
            'account_note' => 'Still deciding whether to move onto a full agreement - keep the value visible on every invoice.',
            'onsite_visit' => 'Point of sale and network check',
            'recurring_ticket' => 'Monthly maintenance visit - Copperline Coffee',
            'project' => null,
            'vendor' => ['Steel City Point of Sale', 'Till system and card terminals', '412-555-0198', 'steelcitypos.example', '8x5 support'],
            'mail_platform' => 'Google Workspace',
            'fleet' => ['Firewall/Router' => 2, 'Switch' => 2, 'Access Point' => 4, 'Desktop' => 3, 'Laptop' => 2, 'Tablet' => 5, 'Printer' => 2],
            'people' => [
                ['Jonah Rickerby', 'Owner', 'Management', 1, 1, 0, 'Hands on owner - usually behind the counter.', ['Primary', 'Executive', 'Authorized Approver']],
                ['Mira Lachlan', 'Operations Manager', 'Operations', 1, 1, 1, 'Runs both sites and the roastery.', ['Technical', 'Billing', 'Onsite Point of Contact']],
                ['Felix Oyelaran', 'Store Lead - Butler Street', 'Retail', 0, 0, 0, 'Front of house point of contact.', []],
                ['Suzanne Kirby', 'Roastery Supervisor', 'Production', 0, 0, 0, 'Reports issues from the roastery.', []],
            ],
        ],
    ];

    $profiles = [];
    foreach ($specs as $spec) {
        $profiles[] = demoExpandProfile($spec);
    }

    return $profiles;
}

// ------------------------------
// demoExpandProfile
// Turns a compact client spec into the full profile the builders consume. The
// documentation, agreement lines and quotes are derived from the spec so the
// numbers hang together - seat counts match licence counts match invoice lines.
// ------------------------------
function demoExpandProfile($spec) {

    $name = $spec['name'];
    $short = $spec['abbreviation'];
    $domain = $spec['domain'];
    $seats = $spec['seats'];
    $servers = $spec['servers'];
    $mail = $spec['mail_platform'];
    $phone = $spec['area'] . '-555-0100';

    // Sites
    $sites = [[
        'name' => 'Main Office',
        'description' => 'Primary site - server room, main office space and reception',
        'address' => $spec['street'],
        'city' => $spec['city'],
        'state' => $spec['state'],
        'zip' => $spec['zip'],
        'phone' => $phone,
        'hours' => 'Mon-Fri 8:00 - 17:00',
        'tags' => ['Head Office', 'Server Room'],
    ]];
    if (!empty($spec['second_site'])) {
        $sites[] = [
            'name' => $spec['second_site'][0],
            'description' => 'Secondary site connected back to the main office',
            'address' => $spec['second_site'][1],
            'city' => $spec['second_site'][2],
            'state' => $spec['second_site'][3],
            'zip' => $spec['second_site'][4],
            'phone' => $spec['area'] . '-555-0101',
            'hours' => 'Mon-Fri 8:00 - 17:00',
            'tags' => ['Branch'],
        ];
    }

    // Networks
    $networks = [
        ['Office LAN', 'Staff workstations, printers and handsets', 10, '192.168.10.0', '192.168.10.1', '192.168.10.1', '192.168.10.50 - 192.168.10.220'],
        ['Guest Wireless', 'Isolated guest access - no route to the office LAN', 40, '192.168.40.0', '192.168.40.1', '192.168.40.1', '192.168.40.20 - 192.168.40.200'],
    ];
    if ($servers > 1) {
        $networks[] = ['Server VLAN', 'Servers and management interfaces', 20, '192.168.20.0', '192.168.20.1', '192.168.20.1', 'Static addressing only'];
    }
    if (in_array($spec['type'], ['Manufacturing', 'Logistics'])) {
        $networks[] = ['Equipment VLAN', 'Shop floor and warehouse equipment - no internet access', 30, '192.168.30.0', '192.168.30.1', '192.168.30.1', '192.168.30.40 - 192.168.30.240'];
    }

    // Credentials
    $mail_admin_uri = $mail === 'Google Workspace' ? 'https://admin.google.com' : 'https://admin.microsoft.com';
    $credentials = [
        ['Firewall Administrator', 'Edge firewall management interface', 'https://192.168.10.1', 'msp-admin', 'Local administrator on the edge firewall. Rotate at every quarterly review.', ['Firewall', 'Local Admin', 'Rotate Quarterly']],
        [$mail . ' Administrator', 'Tenant administration for mail and identity', $mail_admin_uri, 'msp-admin@' . $domain, 'Break glass administrator. MFA enforced - the token lives with the on call phone.', ['Microsoft 365', 'Domain Admin', 'Break Glass']],
        ['Backup Console', 'Backup platform for servers and endpoints', 'https://backup.example', 'msp-' . strtolower($short), 'Console login for restore requests and job monitoring.', ['Backup Console', 'Service Account']],
        ['Switch Management', 'Access layer switch management', 'https://192.168.10.2', 'msp-admin', 'Shared across the access switches at this site.', ['Switch', 'Shared']],
    ];

    // Services
    $services = [
        [$mail, 'Company mail, calendars and identity for ' . $seats . ' users', 'Email', 'High', 'Third party mailbox backup', 'Tenant administered by us. Licence count is reconciled against the monthly invoice.'],
        ['Managed Backup', 'Image level backup of ' . max(1, $servers) . ' servers with offsite copy', 'Backup', 'High', 'Offsite replication', 'Nightly job with a monthly test restore. Evidence is filed against the client documentation.'],
        ['Endpoint Protection and Monitoring', 'Agent based protection and monitoring across the fleet', 'Security', 'High', 'Vendor cloud console', 'Alerts raise tickets automatically. Monthly patch compliance is reviewed at the maintenance visit.'],
    ];

    // Software and licensing
    $software = [
        [$mail . ' Business Premium', 'Per user licensing resold and managed', 'Current', 'Software as a Service (SaaS)', 'Subscription', $seats, 'Licence count is reviewed monthly against staff numbers.'],
        ['Endpoint Protection', 'Managed endpoint protection agent', 'Current', 'Security Software', 'Subscription', $seats + max(1, $servers), 'Covers workstations, laptops and servers.'],
        [$spec['vendor'][0] . ' Platform', $spec['vendor'][1], 'Vendor managed', 'Web Application', 'Subscription', $seats, 'Vendor supported - we hold the administrative access only.'],
    ];

    // Documentation
    $documents = [
        [
            $short . ' - Site Runbook',
            'How this site is put together and what to do first when something breaks',
            '<h2>Site overview</h2><p>' . $name . ' operates from ' . count($sites) . ' site(s) with the main office at ' . $spec['street'] . ', ' . $spec['city'] . '.</p>'
                . '<h2>Network</h2><p>Office LAN on 192.168.10.0/24 behind the edge firewall. Guest wireless is isolated on VLAN 40 with no route to the office LAN.</p>'
                . '<h2>Identity and mail</h2><p>' . $mail . ' tenant administered by us. Break glass credentials are held in the credential vault against this client.</p>'
                . '<h2>First response</h2><ol><li>Confirm whether the problem is site wide or one user.</li><li>Check the firewall WAN status before anything else if more than one person is affected.</li><li>Check the monitoring console for alerts raised in the last hour.</li><li>Escalate to the vendor only after the internal checks are done.</li></ol>'
                . '<h2>Access</h2><p>Site access is arranged through the onsite point of contact. Work outside opening hours must be agreed in advance.</p>',
        ],
        [
            $short . ' - Backup and Recovery Plan',
            'What is protected, how often, and how to get it back',
            '<h2>Scope</h2><p>Image level backup of ' . max(1, $servers) . ' server(s) plus mailbox level protection for ' . $seats . ' users.</p>'
                . '<h2>Schedule</h2><p>Nightly incremental with a weekly synthetic full. Offsite copy replicates daily.</p>'
                . '<h2>Retention</h2><p>30 days local, 12 months offsite.</p>'
                . '<h2>Restore procedure</h2><ol><li>Raise a ticket recording who asked and what is being restored.</li><li>Confirm the restore point with the client before overwriting anything.</li><li>Restore to an alternate location first where possible.</li><li>Record the result against this document and against the ticket.</li></ol>'
                . '<h2>Test restores</h2><p>Monthly. Evidence is attached to the maintenance ticket and referenced in the compliance file.</p>',
        ],
        [
            $short . ' - Vendors and Escalation',
            'Who to call, in what order, and what they are responsible for',
            '<h2>Escalation order</h2><ol><li>Confirm the fault and record it on a ticket.</li><li>Check the internal runbook for this site.</li><li>Escalate to the vendor responsible for the failing component.</li><li>Tell the client contact before the vendor call, not after.</li></ol>'
                . '<h2>Vendors</h2><p><strong>' . $spec['vendor'][0] . '</strong> - ' . $spec['vendor'][1] . '. Response target: ' . $spec['vendor'][4] . '.</p>'
                . '<p><strong>' . $mail . '</strong> - identity and mail. Raised through the tenant administration portal with our partner credentials.</p>'
                . '<h2>Client approvals</h2><p>Spend and downtime are approved by the authorised approver on the contact list. Do not take verbal approval from anyone else for work that will be billed.</p>',
        ],
    ];

    // The monthly agreement, which the invoices are generated from
    $agreement = [
        ['Managed Services - Per User', $seats, 89.00, 'Monitoring, patching, endpoint protection and remote support'],
    ];
    if ($servers > 0) {
        $agreement[] = ['Managed Services - Per Server', $servers, 145.00, 'Server monitoring, patching and backup verification'];
    }
    $agreement[] = [$mail . ' Business Premium', $seats, 22.00, 'Per user licence, resold and managed'];
    $agreement[] = ['Cloud Backup', max(1, $servers), 65.00, 'Offsite backup with monthly test restore'];

    // Quotes in flight
    $refresh_count = max(2, (int)floor($seats / 5));
    $quotes = [
        [
            'Workstation refresh - machines past five years old',
            [
                ['Business Desktop', $refresh_count, 985.00, 'Small form factor desktop, 16GB memory, 512GB solid state drive'],
                ['Deployment and Data Migration', $refresh_count, 145.00, 'Build, migrate and hand over per machine'],
                ['Recycling and Data Destruction', $refresh_count, 25.00, 'Certified disposal of the replaced machines'],
            ],
            'Sent',
        ],
    ];
    if (!empty($spec['project'])) {
        $quotes[] = [
            $spec['project'][0],
            [
                ['Project Labor', 24, 145.00, 'Design, build, migration and handover'],
                ['Project Hardware', 1, 4250.00, 'Hardware supplied as part of the project'],
                ['Out of Hours Cutover', 6, 195.00, 'Cutover work outside operating hours'],
            ],
            'Accepted',
        ];
    }

    // Client attributable costs
    $expenses = [
        ['Hardware supplied to ' . $name, 1450.00 + ($seats * 12), 'Hardware - Cost of Goods', 47],
        [$mail . ' licensing for ' . $name, $seats * 14.50, 'Licensing - Cost of Goods', 18],
    ];

    return [
        'name' => $name,
        'abbreviation' => $short,
        'type' => $spec['type'],
        'domain' => $domain,
        'referral' => $spec['referral'],
        'rate' => number_format($spec['rate'], 2, '.', ''),
        'net_terms' => $spec['terms'],
        'seats' => $seats,
        'servers' => $servers,
        'notes' => $spec['notes'],
        'account_note' => $spec['account_note'],
        'age_days' => $spec['age_days'],
        'area_code' => $spec['area'],
        'tags' => $spec['tags'],
        'sites' => $sites,
        'people' => $spec['people'],
        'vendor' => $spec['vendor'],
        'fleet' => $spec['fleet'],
        'networks' => $networks,
        'mail_platform' => $mail,
        'credentials' => $credentials,
        'services' => $services,
        'software' => $software,
        'documents' => $documents,
        'agreement' => $agreement,
        'quotes' => $quotes,
        'expenses' => $expenses,
        'onsite_visit' => $spec['onsite_visit'],
        'recurring_ticket' => $spec['recurring_ticket'],
        'project' => $spec['project'],
    ];

}

// ------------------------------
// demoDataAccounts
// The bank accounts an MSP actually runs. Matched by name, so an install that
// already has an account called Operating Checking keeps the one it has.
// ------------------------------
function demoDataAccounts() {
    return [
        ['Operating Checking', 'Day to day business banking - client payments in, bills out', 18500.00],
        ['Business Savings', 'Tax and reserve holding', 42000.00],
        ['Merchant Settlement', 'Card and online payments waiting to be paid out', 0.00],
        ['Petty Cash', 'Small cash purchases', 250.00],
    ];
}

// ------------------------------
// demoEnsureAccounts
// Returns name => id for every demo account, creating the missing ones.
// ------------------------------
function demoEnsureAccounts($mysqli, $currency) {

    $accounts = [];
    $existing = [];

    $sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts");
    while ($row = mysqli_fetch_assoc($sql)) {
        $existing[mb_strtolower($row['account_name'])] = intval($row['account_id']);
    }

    foreach (demoDataAccounts() as $account) {
        $key = mb_strtolower($account[0]);
        if (isset($existing[$key])) {
            $accounts[$account[0]] = $existing[$key];
            continue;
        }
        $accounts[$account[0]] = starterInsert($mysqli, 'accounts', [
            'account_name' => $account[0],
            'account_description' => $account[1],
            'opening_balance' => number_format($account[2], 2, '.', ''),
            'account_currency_code' => $currency,
            'account_created_at' => demoMonthDateTime(24, 1, 9, 0),
        ]);
    }

    return $accounts;
}

// ------------------------------
// demoBuildBilling
// The client's money, month by month, for as long as they have been a client.
// The agreement drives a monthly invoice; project and hardware work lands on
// top of it every few months so revenue is not a flat line.
// ------------------------------
function demoBuildBilling($mysqli, $profile, $index, $client_id, $vendor_id, $context, &$counts) {

    global $config_invoice_prefix, $config_quote_prefix, $config_recurring_invoice_prefix;

    $currency = $context['currency'];
    $months = demoClientMonths($profile);
    $income_category_id = starterCategoryId($mysqli, 'Managed Services', 'Income');
    $project_category_id = starterCategoryId($mysqli, 'Projects', 'Income') ?: $income_category_id;
    $hardware_category_id = starterCategoryId($mysqli, 'Hardware Sales', 'Income') ?: $income_category_id;

    // Bank and card money land in different places
    $bank_account_id = $context['accounts']['Operating Checking'] ?? $context['account_id'];
    $card_account_id = $context['accounts']['Merchant Settlement'] ?? $bank_account_id;

    // The agreement itself
    $recurring_total = 0;
    foreach ($profile['agreement'] as $line) {
        $recurring_total = $recurring_total + ($line[1] * $line[2]);
    }

    $recurring_invoice_id = starterInsert($mysqli, 'recurring_invoices', [
        'recurring_invoice_prefix' => $config_recurring_invoice_prefix ?? '',
        'recurring_invoice_number' => demoNextNumber($mysqli, 'config_recurring_invoice_next_number'),
        'recurring_invoice_scope' => 'Monthly managed services agreement',
        'recurring_invoice_frequency' => 'month',
        'recurring_invoice_next_date' => date('Y-m-01', strtotime('+1 month')),
        'recurring_invoice_last_sent' => date('Y-m-01'),
        'recurring_invoice_status' => 1,
        'recurring_invoice_amount' => number_format($recurring_total, 2, '.', ''),
        'recurring_invoice_currency_code' => $currency,
        'recurring_invoice_note' => 'Billed on the first of each month in advance.',
        // Demo contacts must never be mailed, so the cron notification is off
        'recurring_invoice_email_notify' => 0,
        'recurring_invoice_category_id' => $income_category_id,
        'recurring_invoice_client_id' => $client_id,
        'recurring_invoice_created_at' => demoMonthDateTime($months, 1, 9, 30),
    ]);

    foreach ($profile['agreement'] as $item_order => $line) {
        demoInvoiceItem($mysqli, 'recurring_invoice_items', 'item_recurring_invoice_id', $recurring_invoice_id, $line, $item_order, demoMonthDateTime($months, 1, 9, 30));
    }
    $counts['billing']++;

    // Monthly agreement invoices for every month they have been a client
    $payment_methods = ['ACH', 'Credit Card', 'Check'];
    $one_off_pool = demoOneOffInvoicePool();

    for ($month = $months; $month >= 0; $month--) {

        $invoice_date = demoMonthDate($month, 1);
        $created_at = demoMonthDateTime($month, 1, 6, 5);
        $due_date = date('Y-m-d', strtotime($invoice_date . ' +' . $profile['net_terms'] . ' days'));

        // The current month is still out, and one client is deliberately behind
        $outstanding_months = in_array('Past Due', $profile['tags']) ? 3 : 1;
        $paid = $month >= $outstanding_months;
        $partial = $paid && $month === 5;

        $status = 'Paid';
        if (!$paid) {
            $status = $month === 0 ? 'Sent' : 'Viewed';
        } elseif ($partial) {
            $status = 'Partial';
        }

        $invoice_id = starterInsert($mysqli, 'invoices', [
            'invoice_prefix' => $config_invoice_prefix ?? '',
            'invoice_number' => demoNextNumber($mysqli, 'config_invoice_next_number'),
            'invoice_scope' => 'Managed services - ' . date('F Y', strtotime($invoice_date)),
            'invoice_status' => $status,
            'invoice_date' => $invoice_date,
            'invoice_due' => $due_date,
            'invoice_amount' => number_format($recurring_total, 2, '.', ''),
            'invoice_currency_code' => $currency,
            'invoice_url_key' => randomString(32),
            'invoice_category_id' => $income_category_id,
            'invoice_recurring_invoice_id' => $recurring_invoice_id,
            'invoice_client_id' => $client_id,
            'invoice_created_at' => $created_at,
        ]);

        foreach ($profile['agreement'] as $item_order => $line) {
            demoInvoiceItem($mysqli, 'invoice_items', 'item_invoice_id', $invoice_id, $line, $item_order, $created_at);
        }

        starterInsert($mysqli, 'history', [
            'history_status' => 'Sent',
            'history_description' => 'Invoice emailed to the billing contact',
            'history_invoice_id' => $invoice_id,
            'history_created_at' => demoMonthDateTime($month, 1, 6, 10),
        ]);

        if ($paid) {
            $method = $payment_methods[($index + $month) % 3];
            $amount = $partial ? round($recurring_total / 2, 2) : $recurring_total;
            demoPayment($mysqli, $invoice_id, $amount, $currency, $method, $method === 'Credit Card' ? $card_account_id : $bank_account_id, $month, $profile, $index);
            starterInsert($mysqli, 'history', [
                'history_status' => $partial ? 'Partial' : 'Paid',
                'history_description' => $partial ? 'Part payment received' : 'Payment received in full',
                'history_invoice_id' => $invoice_id,
                'history_created_at' => demoMonthDateTime($month, 12, 14, 0),
            ]);
        }

        $counts['billing']++;

        // Project and hardware work on top of the agreement every few months
        if (($month + $index) % 6 === 3) {

            $one_off = $one_off_pool[($index + $month) % count($one_off_pool)];
            $one_off_total = 0;
            foreach ($one_off[1] as $line) {
                $one_off_total = $one_off_total + ($line[1] * $line[2]);
            }
            $one_off_created = demoMonthDateTime($month, 18, 11, 30);

            $one_off_id = starterInsert($mysqli, 'invoices', [
                'invoice_prefix' => $config_invoice_prefix ?? '',
                'invoice_number' => demoNextNumber($mysqli, 'config_invoice_next_number'),
                'invoice_scope' => $one_off[0],
                'invoice_status' => 'Paid',
                'invoice_date' => demoMonthDate($month, 18),
                'invoice_due' => date('Y-m-d', strtotime(demoMonthDate($month, 18) . ' +' . $profile['net_terms'] . ' days')),
                'invoice_amount' => number_format($one_off_total, 2, '.', ''),
                'invoice_currency_code' => $currency,
                'invoice_url_key' => randomString(32),
                'invoice_category_id' => $one_off[2] === 'hardware' ? $hardware_category_id : $project_category_id,
                'invoice_client_id' => $client_id,
                'invoice_created_at' => $one_off_created,
            ]);

            foreach ($one_off[1] as $item_order => $line) {
                demoInvoiceItem($mysqli, 'invoice_items', 'item_invoice_id', $one_off_id, $line, $item_order, $one_off_created);
            }

            demoPayment($mysqli, $one_off_id, $one_off_total, $currency, 'ACH', $bank_account_id, $month - 1, $profile, $index);
            $counts['billing']++;

        }

    }

    // Quotes, roughly one every five months, at every stage of the pipeline
    $quote_statuses = ['Invoiced', 'Accepted', 'Declined', 'Sent', 'Draft'];
    $quote_slot = 0;
    for ($month = $months; $month >= 0; $month = $month - 5) {

        $quote = $profile['quotes'][$quote_slot % count($profile['quotes'])];
        $quote_total = 0;
        foreach ($quote[1] as $line) {
            $quote_total = $quote_total + ($line[1] * $line[2]);
        }

        if (demoIsFuture(demoMonthDate($month, 9))) {
            $quote_slot++;
            continue;
        }

        // Old quotes are settled one way or the other, recent ones are still moving
        $status = $month <= 5 ? $quote_statuses[($index + $quote_slot) % 2 + 3] : $quote_statuses[($index + $quote_slot) % 3];
        $quote_created = demoMonthDateTime($month, 9, 11, 20);

        $quote_id = starterInsert($mysqli, 'quotes', [
            'quote_prefix' => $config_quote_prefix ?? '',
            'quote_number' => demoNextNumber($mysqli, 'config_quote_next_number'),
            'quote_scope' => $quote[0],
            'quote_status' => $status,
            'quote_date' => demoMonthDate($month, 9),
            'quote_expire' => demoMonthDate($month - 1, 9),
            'quote_amount' => number_format($quote_total, 2, '.', ''),
            'quote_currency_code' => $currency,
            'quote_note' => 'Pricing held for 30 days. Lead times quoted at time of order.',
            'quote_url_key' => randomString(32),
            'quote_category_id' => $project_category_id,
            'quote_client_id' => $client_id,
            'quote_created_at' => $quote_created,
        ]);

        foreach ($quote[1] as $item_order => $line) {
            demoInvoiceItem($mysqli, 'quote_items', 'item_quote_id', $quote_id, $line, $item_order, $quote_created);
        }

        starterInsert($mysqli, 'history', [
            'history_status' => 'Sent',
            'history_description' => 'Quote sent for approval',
            'history_quote_id' => $quote_id,
            'history_created_at' => demoMonthDateTime($month, 9, 11, 30),
        ]);

        $quote_slot++;
        $counts['billing']++;

    }

    // Client attributable costs, every other month
    $expense_slot = 0;
    for ($month = $months; $month >= 0; $month = $month - 2) {
        $expense = $profile['expenses'][$expense_slot % count($profile['expenses'])];
        if (demoIsFuture(demoMonthDate($month, 14))) {
            $expense_slot++;
            continue;
        }
        starterInsert($mysqli, 'expenses', [
            'expense_description' => $expense[0],
            'expense_amount' => number_format($expense[1], 2, '.', ''),
            'expense_currency_code' => $currency,
            'expense_date' => demoMonthDate($month, 14),
            'expense_reference' => 'DEMO-' . demoSerial($profile['abbreviation'] . $expense[0] . $month, 8),
            'expense_payment_method' => 'Credit Card',
            'expense_vendor_id' => $vendor_id,
            'expense_client_id' => $client_id,
            'expense_category_id' => starterCategoryId($mysqli, $expense[2], 'Expense'),
            'expense_account_id' => $bank_account_id,
            'expense_created_at' => demoMonthDateTime($month, 14, 16, 45),
        ]);
        $expense_slot++;
        $counts['billing']++;
    }

    // Income that never went through an invoice
    for ($month = $months; $month >= 0; $month = $month - 12) {
        if (demoIsFuture(demoMonthDate($month, 22))) {
            continue;
        }
        starterInsert($mysqli, 'revenues', [
            'revenue_date' => demoMonthDate($month, 22),
            'revenue_amount' => number_format(1200.00 + ($index * 75), 2, '.', ''),
            'revenue_currency_code' => $currency,
            'revenue_payment_method' => 'Check',
            'revenue_reference' => 'DEMO-' . demoSerial($profile['abbreviation'] . 'rev' . $month, 8),
            'revenue_description' => 'Prepaid block of support hours',
            'revenue_category_id' => starterCategoryId($mysqli, 'Support', 'Income') ?: $income_category_id,
            'revenue_account_id' => $bank_account_id,
            'revenue_client_id' => $client_id,
            'revenue_created_at' => demoMonthDateTime($month, 22, 10, 15),
        ]);
        $counts['billing']++;
    }

}

// ------------------------------
// demoInvoiceItem
// invoice_items, quote_items and recurring_invoice_items are the same shape.
// ------------------------------
function demoInvoiceItem($mysqli, $table, $parent_column, $parent_id, $line, $item_order, $created_at) {
    $subtotal = $line[1] * $line[2];
    starterInsert($mysqli, $table, [
        'item_name' => $line[0],
        'item_description' => $line[3],
        'item_quantity' => $line[1],
        'item_price' => number_format($line[2], 2, '.', ''),
        'item_subtotal' => number_format($subtotal, 2, '.', ''),
        'item_tax' => '0.00',
        'item_total' => number_format($subtotal, 2, '.', ''),
        'item_order' => $item_order + 1,
        $parent_column => $parent_id,
        'item_created_at' => $created_at,
    ]);
}

// ------------------------------
// demoPayment
// ------------------------------
function demoPayment($mysqli, $invoice_id, $amount, $currency, $method, $account_id, $month, $profile, $index) {
    if (!$account_id) {
        return;
    }
    $day = 8 + (($index + $month) % 14);
    if (demoIsFuture(demoMonthDate($month, $day))) {
        return;
    }
    starterInsert($mysqli, 'payments', [
        'payment_date' => demoMonthDate($month, $day),
        'payment_amount' => number_format($amount, 2, '.', ''),
        'payment_currency_code' => $currency,
        'payment_method' => $method,
        'payment_reference' => 'DEMO-' . demoSerial($profile['abbreviation'] . 'pay' . $invoice_id, 8),
        'payment_account_id' => $account_id,
        'payment_invoice_id' => $invoice_id,
        'payment_created_at' => demoMonthDateTime($month, $day, 14, 0),
    ]);
}

// ------------------------------
// demoOneOffInvoicePool
// Project and hardware work billed outside the agreement.
// ------------------------------
function demoOneOffInvoicePool() {
    return [
        ['Workstation refresh - supply and deployment', [
            ['Business Desktop', 4, 985.00, 'Small form factor desktop, 16GB memory, 512GB solid state drive'],
            ['Deployment and Data Migration', 4, 145.00, 'Build, migrate and hand over per machine'],
        ], 'hardware'],
        ['Firewall replacement and rule migration', [
            ['Next Generation Firewall', 1, 1245.00, 'Edge firewall with three years of security subscription'],
            ['Configuration and Cutover', 8, 145.00, 'Rule migration, cutover and post change checks'],
        ], 'project'],
        ['Wireless coverage extension', [
            ['Wireless Access Point', 3, 245.00, 'Wi-Fi 6 ceiling mounted access point'],
            ['Installation and Survey', 6, 145.00, 'Cabling, mounting and post install survey'],
        ], 'project'],
        ['Server storage expansion', [
            ['Enterprise Solid State Drive', 4, 420.00, 'Mixed use enterprise drive'],
            ['Out of Hours Labor', 5, 195.00, 'Installation and array rebuild outside operating hours'],
        ], 'hardware'],
        ['Mailbox migration and tenant cleanup', [
            ['Migration Labor', 16, 145.00, 'Mailbox migration, cutover and post migration support'],
            ['Tenant Hardening', 4, 145.00, 'Conditional access, MFA enforcement and audit logging'],
        ], 'project'],
        ['Backup platform replacement', [
            ['Backup Appliance', 1, 2850.00, 'Local backup appliance with offsite replication'],
            ['Build and Seed', 10, 145.00, 'Build, seed the first full backup and verify a restore'],
        ], 'project'],
    ];
}

// ------------------------------
// demoBuildCompanyFinancials
// The MSP's own money - two years of operating costs, and the transfers that
// move card settlements into the bank and the tax reserve into savings. None
// of this belongs to a client, so it is marked with a DEMO- reference and the
// purge finds it that way.
// ------------------------------
function demoBuildCompanyFinancials($mysqli, $context, &$counts) {

    $currency = $context['currency'];
    $accounts = $context['accounts'];
    $operating_id = $accounts['Operating Checking'] ?? 0;
    $savings_id = $accounts['Business Savings'] ?? 0;
    $merchant_id = $accounts['Merchant Settlement'] ?? 0;

    if (!$operating_id) {
        return;
    }

    // Only ever build this once
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(expense_id) AS total FROM expenses WHERE expense_client_id = 0 AND expense_reference LIKE 'DEMO-%'"));
    if (intval($row['total'] ?? 0)) {
        return;
    }

    // description, amount, category, every N months, day of month
    $operating_costs = [
        ['Office rent', 1850.00, 'Rent and Utilities', 1, 1],
        ['Payroll', 14250.00, 'Payroll', 1, 28],
        ['Software and tooling subscriptions', 640.00, 'Software', 1, 6],
        ['Internet and phone', 285.00, 'Telecom and Internet', 1, 9],
        ['Fuel and vehicle costs', 215.00, 'Vehicle and Fuel', 1, 21],
        ['Colocation and connectivity', 495.00, 'Infrastructure', 1, 3],
        ['Business insurance', 1450.00, 'Insurance', 3, 12],
        ['Accounting and payroll services', 900.00, 'Professional Services', 3, 15],
        ['Advertising and sponsorship', 500.00, 'Advertising', 4, 19],
        ['Training and certification', 750.00, 'Education', 6, 24],
        ['Tools and test equipment', 385.00, 'Tools and Test Equipment', 6, 11],
    ];

    for ($month = 24; $month >= 0; $month--) {
        foreach ($operating_costs as $cost) {

            if ($month % $cost[3] !== 0 || demoIsFuture(demoMonthDate($month, $cost[4]))) {
                continue;
            }

            // Payroll and rent creep up over two years rather than sitting flat
            $drift = 1 + ((24 - $month) * 0.004);

            starterInsert($mysqli, 'expenses', [
                'expense_description' => $cost[0],
                'expense_amount' => number_format($cost[1] * $drift, 2, '.', ''),
                'expense_currency_code' => $currency,
                'expense_date' => demoMonthDate($month, $cost[4]),
                'expense_reference' => 'DEMO-' . demoSerial('company' . $cost[0] . $month, 8),
                'expense_payment_method' => $cost[0] === 'Payroll' ? 'ACH' : 'Credit Card',
                'expense_vendor_id' => 0,
                'expense_client_id' => 0,
                'expense_category_id' => starterCategoryId($mysqli, $cost[2], 'Expense'),
                'expense_account_id' => $operating_id,
                'expense_created_at' => demoMonthDateTime($month, $cost[4], 17, 0),
            ]);
            $counts['company']++;

        }
    }

    // Card settlements landing in the bank, and the quarterly tax reserve
    for ($month = 24; $month >= 0; $month--) {

        if ($merchant_id) {
            demoTransfer($mysqli, $merchant_id, $operating_id, 4250.00 + (($month % 7) * 315), $currency, 'Merchant payout', 'Card settlements paid out to the operating account', $month, 5, $counts);
        }

        if ($savings_id && $month % 3 === 0) {
            demoTransfer($mysqli, $operating_id, $savings_id, 6500.00, $currency, 'Bank Transfer', 'Quarterly tax reserve moved to savings', $month, 16, $counts);
        }

    }

}

// ------------------------------
// demoTransfer
// A transfer is an expense on one account and a revenue on the other, joined
// by a transfers row - the same three writes the transfer handler makes.
// ------------------------------
function demoTransfer($mysqli, $from_account_id, $to_account_id, $amount, $currency, $method, $notes, $month, $day, &$counts) {

    $date = demoMonthDate($month, $day);
    if (demoIsFuture($date)) {
        return;
    }
    $created_at = demoMonthDateTime($month, $day, 10, 30);
    $reference = 'DEMO-' . demoSerial('transfer' . $from_account_id . $to_account_id . $month . $day, 8);

    $expense_id = starterInsert($mysqli, 'expenses', [
        'expense_date' => $date,
        'expense_amount' => number_format($amount, 2, '.', ''),
        'expense_currency_code' => $currency,
        'expense_reference' => $reference,
        'expense_vendor_id' => 0,
        'expense_category_id' => 0,
        'expense_client_id' => 0,
        'expense_account_id' => $from_account_id,
        'expense_created_at' => $created_at,
    ]);

    $revenue_id = starterInsert($mysqli, 'revenues', [
        'revenue_date' => $date,
        'revenue_amount' => number_format($amount, 2, '.', ''),
        'revenue_currency_code' => $currency,
        'revenue_reference' => $reference,
        'revenue_category_id' => 0,
        'revenue_account_id' => $to_account_id,
        'revenue_client_id' => 0,
        'revenue_created_at' => $created_at,
    ]);

    starterInsert($mysqli, 'transfers', [
        'transfer_method' => $method,
        'transfer_notes' => $notes,
        'transfer_expense_id' => $expense_id,
        'transfer_revenue_id' => $revenue_id,
        'transfer_created_at' => $created_at,
    ]);

    $counts['company']++;

}
