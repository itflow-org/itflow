<?php

/*
 * ITFlow - client-wide actions: archiving a client (Archive Client and Offboard), and what
 * offboarding one stops (agent/modals/client/client_offboard.php, offboard_client).
 */
/*
 * ---------- offboarding (agent/modals/client/client_offboard.php, offboard_client) ----------
 * Everything that keeps working for a client after you stop working for them, counted - so the
 * Offboard window can say what each step will do, and the handler do just those.
 */
function clientOffboardCounts($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $count = function ($sql) use ($mysqli) {
        $row = mysqli_fetch_row(mysqli_query($mysqli, $sql));
        return intval($row[0] ?? 0);
    };
    return [
        'portal' => $count("SELECT COUNT(contact_id) FROM contacts JOIN users ON user_id = contact_user_id AND user_type = 2
            WHERE contact_client_id = $client_id AND user_auth_method != ''"),
        'invites' => portalInvitesReady() ? count(portalInvitesWaiting($client_id)) : 0,
        'shares' => $count("SELECT COUNT(item_id) FROM shared_items WHERE item_client_id = $client_id AND item_ended_at IS NULL AND item_active = 1 AND item_expire_at > NOW()"),
        'recurring_invoices' => $count("SELECT COUNT(recurring_invoice_id) FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL"),
        'recurring_tickets' => $count("SELECT COUNT(recurring_ticket_id) FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id AND recurring_ticket_paused_at IS NULL"),
        'open_tickets' => $count("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_client_id = $client_id AND " . TICKET_OPEN_SQL),
    ];
}

/**
 * Archive a client - as Archive Client and Offboard do: archived, its recurring invoices stopped,
 * and contract signing requests still out to it withdrawn. Returns how many were withdrawn.
 */
function clientArchiveNow($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");
    clientStopRecurringInvoices($client_id, 'Recurring Invoice inactive as client archived');
    // Nothing more is signed on their contracts - a request still out, or waiting on our countersignature, is withdrawn
    return contractSignatureWithdrawStale($client_id);
}

/** Stop a client's recurring invoices, each with a line in its history. Returns how many. */
function clientStopRecurringInvoices($client_id, $why) {
    global $mysqli;
    $client_id = intval($client_id);
    $why = escapeSql($why);
    $stopped = 0;
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1");
    while ($row = mysqli_fetch_assoc($sql)) {
        $recurring_invoice_id = intval($row['recurring_invoice_id']);
        mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_status = 0 WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 0, history_description = '$why', history_recurring_invoice_id = $recurring_invoice_id");
        $stopped++;
    }
    return $stopped;
}

/*
 * ---------- merging duplicate clients (agent/modals/client/client_merge.php, merge_client) ----------
 * The same company added twice: everything one has goes to the other, and the one left is
 * archived, empty, as "Name (merged into Other)".
 */

/** [table, column] for every record that belongs to a client and simply moves - what exists here. */
function clientMergeTables() {
    global $mysqli;
    $tables = [
        ['contacts', 'contact_client_id'], ['locations', 'location_client_id'], ['assets', 'asset_client_id'],
        ['tickets', 'ticket_client_id'], ['recurring_tickets', 'recurring_ticket_client_id'], ['projects', 'project_client_id'],
        ['invoices', 'invoice_client_id'], ['recurring_invoices', 'recurring_invoice_client_id'], ['quotes', 'quote_client_id'],
        ['credits', 'credit_client_id'], ['contracts', 'contract_client_id'], ['revenues', 'revenue_client_id'],
        ['expenses', 'expense_client_id'], ['recurring_expenses', 'recurring_expense_client_id'], ['trips', 'trip_client_id'],
        ['documents', 'document_client_id'], ['folders', 'folder_client_id'], ['files', 'file_client_id'],
        ['credentials', 'credential_client_id'], ['domains', 'domain_client_id'], ['certificates', 'certificate_client_id'],
        ['networks', 'network_client_id'], ['racks', 'rack_client_id'], ['software', 'software_client_id'],
        ['services', 'service_client_id'], ['vendors', 'vendor_client_id'], ['calendar_events', 'event_client_id'],
        ['client_notes', 'client_note_client_id'], ['shared_items', 'item_client_id'], ['portal_invites', 'invite_client_id'],
        ['portal_announcements', 'announcement_client_id'], ['sms_messages', 'sms_message_client_id'],
        ['kb_article_feedback', 'kb_article_feedback_client_id'], ['order_items', 'order_item_client_id'], ['po_items', 'po_item_client_id'],
        ['expense_import_rules', 'import_rule_client_id'], ['income_import_rules', 'import_rule_client_id'],
        ['rmm_clients', 'rmm_client_client_id'], ['rmm_devices', 'rmm_device_client_id'], ['rmm_alerts', 'rmm_alert_client_id'], ['rmm_networks', 'rmm_network_client_id'],
        ['phone_domains', 'phone_domain_client_id'], ['phone_calls', 'phone_call_client_id'], ['phone_devices', 'phone_device_client_id'], ['phone_events', 'phone_event_client_id'],
        ['managed_instances', 'managed_instance_client_id'], ['ai_usage', 'ai_usage_client_id'],
        ['logs', 'log_client_id'], ['notifications', 'notification_client_id'],
    ];
    $have = [];
    $sql = mysqli_query($mysqli, "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
    while ($row = mysqli_fetch_row($sql)) {
        $have[$row[0] . '.' . $row[1]] = true;
    }
    return array_values(array_filter($tables, function ($t) use ($have) {
        return isset($have[$t[0] . '.' . $t[1]]);
    }));
}

/** What a client has, for the Merge window - ["3 contacts" label => count], only what it has some of. */
function clientMergeSummary($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $labels = [
        'contacts' => 'contact', 'locations' => 'location', 'assets' => 'asset', 'tickets' => 'ticket', 'recurring_tickets' => 'recurring ticket',
        'projects' => 'project', 'invoices' => 'invoice', 'recurring_invoices' => 'recurring invoice', 'quotes' => 'quote', 'credits' => 'credit',
        'contracts' => 'contract', 'expenses' => 'expense', 'documents' => 'document', 'files' => 'file', 'credentials' => 'credential',
        'domains' => 'domain', 'certificates' => 'certificate', 'networks' => 'network', 'software' => 'license', 'services' => 'service',
        'vendors' => 'vendor', 'calendar_events' => 'calendar event', 'trips' => 'trip',
    ];
    $summary = [];
    foreach (clientMergeTables() as [$table, $column]) {
        if (!isset($labels[$table])) {
            continue;
        }
        $n = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM `$table` WHERE `$column` = $client_id"))[0]);
        if ($n) {
            $summary[$labels[$table] . ($n === 1 ? '' : 's')] = $n;
        }
    }
    return $summary;
}

/**
 * Agents whose client access the merge changes - allowed to see $from_id but not $into_id (they
 * lose sight of its records), or kept off $from_id but not $into_id (they gain it). [name => 'loses'|'gains'].
 */
function clientMergeAccessChanges($from_id, $into_id) {
    global $mysqli;
    $from_id = intval($from_id);
    $into_id = intval($into_id);
    $changes = [];
    $sql = mysqli_query($mysqli, "SELECT user_name, f.permission_type FROM user_client_permissions f
        JOIN users ON users.user_id = f.user_id AND user_archived_at IS NULL
        LEFT JOIN user_client_permissions t ON t.user_id = f.user_id AND t.client_id = $into_id AND t.permission_type = f.permission_type
        WHERE f.client_id = $from_id AND t.user_id IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $changes[$row['user_name']] = $row['permission_type'] === 'deny' ? 'gains' : 'loses';
    }
    return $changes;
}

/**
 * Merge $from_id into $into_id - every record moves, the kept client's own settings stay, and
 * $from_id is left archived and empty. Returns a list of notes on what needed a choice.
 */
function clientMerge($from_id, $into_id) {
    global $mysqli;
    $from = intval($from_id);
    $into = intval($into_id);
    $notes = [];

    foreach (clientMergeTables() as [$table, $column]) {
        mysqli_query($mysqli, "UPDATE `$table` SET `$column` = $into WHERE `$column` = $from");
    }

    // One primary contact and one primary location - the kept client's
    foreach ([['contacts', 'contact'], ['locations', 'location']] as [$table, $prefix]) {
        $has = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM $table WHERE {$prefix}_client_id = $into AND {$prefix}_primary = 1 AND {$prefix}_archived_at IS NULL"));
        if ($has > 1) {
            $keep = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$prefix}_id FROM $table WHERE {$prefix}_client_id = $into AND {$prefix}_primary = 1 ORDER BY {$prefix}_id LIMIT 1"))[0]);
            mysqli_query($mysqli, "UPDATE $table SET {$prefix}_primary = 0 WHERE {$prefix}_client_id = $into AND {$prefix}_id != $keep");
        }
    }

    // Lists a client is on - the kept client is on each that either was
    foreach ([['client_tags', 'client_id', 'tag_id'], ['kb_article_clients', 'client_id', 'kb_article_id'], ['ticket_template_portal_clients', 'client_id', 'ticket_template_id']] as [$table, $column, $other]) {
        if (mysqli_num_rows(mysqli_query($mysqli, "SHOW TABLES LIKE '$table'"))) {
            mysqli_query($mysqli, "INSERT IGNORE INTO `$table` (`$column`, `$other`) SELECT $into, `$other` FROM `$table` WHERE `$column` = $from");
            mysqli_query($mysqli, "DELETE FROM `$table` WHERE `$column` = $from");
        }
    }

    // One of each per client - the kept client's wins
    mysqli_query($mysqli, "UPDATE IGNORE sla_assignments SET sla_assignment_client_id = $into WHERE sla_assignment_client_id = $from");
    mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id = $from");
    if (mysqli_num_rows(mysqli_query($mysqli, "SHOW TABLES LIKE 'kb_search_misses'"))) {
        mysqli_query($mysqli, "UPDATE IGNORE kb_search_misses SET kb_search_miss_client_id = $into WHERE kb_search_miss_client_id = $from");
        mysqli_query($mysqli, "DELETE FROM kb_search_misses WHERE kb_search_miss_client_id = $from");
    }
    if (mysqli_num_rows(mysqli_query($mysqli, "SHOW TABLES LIKE 'client_onboardings'"))) {
        mysqli_query($mysqli, "UPDATE IGNORE client_onboardings SET onboarding_client_id = $into WHERE onboarding_client_id = $from");
        mysqli_query($mysqli, "DELETE FROM client_onboardings WHERE onboarding_client_id = $from");
    }

    // Card payments: a provider only the merged client was set up with comes across with its saved
    // cards; where both were, the kept client's customer stays and the other's saved cards can't follow
    $sql = mysqli_query($mysqli, "SELECT payment_provider_id FROM client_payment_provider WHERE client_id = $from");
    while ($row = mysqli_fetch_row($sql)) {
        $provider_id = intval($row[0]);
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM client_payment_provider WHERE client_id = $into AND payment_provider_id = $provider_id"))) {
            $dropped = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM client_saved_payment_methods WHERE saved_payment_client_id = $from AND saved_payment_provider_id = $provider_id"))[0]);
            mysqli_query($mysqli, "DELETE FROM client_saved_payment_methods WHERE saved_payment_client_id = $from AND saved_payment_provider_id = $provider_id");
            mysqli_query($mysqli, "DELETE FROM client_payment_provider WHERE client_id = $from AND payment_provider_id = $provider_id");
            if ($dropped) {
                $notes[] = "$dropped saved card" . ($dropped === 1 ? '' : 's') . " of the merged client couldn't come across - both had a card customer - and autopay on them is off";
            }
        } else {
            mysqli_query($mysqli, "UPDATE client_payment_provider SET client_id = $into WHERE client_id = $from AND payment_provider_id = $provider_id");
            mysqli_query($mysqli, "UPDATE client_saved_payment_methods SET saved_payment_client_id = $into WHERE saved_payment_client_id = $from AND saved_payment_provider_id = $provider_id");
        }
    }

    // Their files, contact photos and the like live in a folder per client
    $from_dir = dirname(__DIR__) . "/uploads/clients/$from";
    $into_dir = dirname(__DIR__) . "/uploads/clients/$into";
    if (is_dir($from_dir)) {
        if (!is_dir($into_dir)) {
            mkdir($into_dir, 0755, true);
        }
        foreach (scandir($from_dir) as $name) {
            if ($name === '.' || $name === '..' || file_exists("$into_dir/$name")) {
                continue;
            }
            rename("$from_dir/$name", "$into_dir/$name");
        }
    }

    // Quick notes, after the kept client's own
    $names = mysqli_fetch_all(mysqli_query($mysqli, "SELECT client_id, client_name, client_notes FROM clients WHERE client_id IN ($from, $into)"), MYSQLI_ASSOC);
    $rows = array_column($names, null, 'client_id');
    $from_notes = trim((string) ($rows[$from]['client_notes'] ?? ''));
    if ($from_notes !== '') {
        $into_notes = trim((string) ($rows[$into]['client_notes'] ?? ''));
        $merged_notes = ($into_notes !== '' ? $into_notes . "\n\n" : '') . 'From ' . $rows[$from]['client_name'] . ":\n" . $from_notes;
        mysqli_query($mysqli, "UPDATE clients SET client_notes = '" . escapeSql($merged_notes) . "' WHERE client_id = $into");
    }

    // Your own company's record, if it was this one
    mysqli_query($mysqli, "UPDATE settings SET config_internal_client_id = $into WHERE config_internal_client_id = $from");

    // What's left: archived and empty, its name saying where it went
    $left_name = mb_substr($rows[$from]['client_name'] . ' (merged into ' . $rows[$into]['client_name'] . ')', 0, 200);
    mysqli_query($mysqli, "UPDATE clients SET client_name = '" . escapeSql($left_name) . "', client_notes = NULL, client_favorite = 0,
        client_archived_at = COALESCE(client_archived_at, NOW()) WHERE client_id = $from");

    return $notes;
}
