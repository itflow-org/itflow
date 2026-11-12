<?php

/*
 * ITFlow - Database update to version 3.6.5 (from 3.6.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // One collation for every table: utf8mb4_general_ci, as db.sql has it.
    //
    // 2.0.6 converted every table there was to utf8mb4_general_ci. The tables made by the
    // updates after it, up to 2.4.1, were created without a COLLATE - some with DEFAULT
    // CHARSET=utf8mb4 alone, some with nothing - so they took whatever the server gives a
    // table by default: utf8mb4_0900_ai_ci on MySQL 8, utf8mb4_uca1400_ai_ci on MariaDB
    // 11.5 and later, the database's own default for the ones with nothing. Those are
    // document_versions, document_templates, software_templates, vendor_templates,
    // ai_providers, ai_models, payment_methods, payment_providers,
    // client_saved_payment_methods, client_payment_provider, credits, discount_codes,
    // product_stock, software_keys and its two assignment tables, contract_templates,
    // contracts, asset_tags, task_approvals, quote_items and recurring_invoice_items. An
    // install set up from db.sql has all of them right, and so does one upgraded on a
    // server whose default is utf8mb4_general_ci.
    //
    // Two collations in one database work until a query puts text from both side by side.
    // 26.11.4's count of lines at an old price did - a UNION over invoice_items, quote_items
    // and recurring_invoice_items - and failed with "Illegal mix of collations", which took
    // the dashboard down with it. That query now names its collation; this brings the tables
    // themselves into line, so the next query that mixes them needs nothing.
    //
    // It looks at what is there, not at a list of suspects: every table of ITFlow's whose
    // default, or any text column, is something other than utf8mb4_general_ci is changed,
    // in one ALTER a table. A table already right is not touched, so on most installs this
    // does nothing at all, and on the rest it rebuilds the tables above - small ones.
    //
    // Each column is changed with MODIFY, from its own line of SHOW CREATE TABLE with only
    // the collation replaced, where 2.0.6 used CONVERT TO CHARACTER SET. CONVERT TO takes
    // every text column in the table, and two must stay as they are: calendars.
    // calendar_feed_key and user_calendar_feeds.feed_key are utf8mb4_bin on purpose, so
    // that two keys differing only by case are two keys. Tried on MySQL 8 and MariaDB, a
    // MODIFY beside CONVERT TO in the same ALTER does not hold the column back. CONVERT TO
    // also turns a utf8mb3 TEXT into a MEDIUMTEXT; MODIFY leaves every type as it is. A
    // column with a binary collation is left alone, whichever it is.
    //
    // Only ITFlow's own tables, by name - anything else kept in the same database is not
    // ours to change.
    //
    // A table that can't be changed is left as it was and the update carries on: its ALTER
    // changes all of it or none of it, the reason goes to the PHP error log and to App
    // Logs, and nothing else waits on it. What would stop one is two rows of a unique text
    // column that differ only in a way utf8mb4_general_ci does not see - a space at the
    // end, which utf8mb4_0900_ai_ci counts and utf8mb4_general_ci does not.
    //
    // records.record_updated_at has a zero date for its default. MySQL 8 as it comes
    // (strict, NO_ZERO_DATE) keeps such a column but refuses any ALTER of its table,
    // whatever the ALTER changes, with "Invalid default value". Zero dates are allowed on
    // this connection while the tables are changed, and the mode is put back after;
    // strict mode itself stays on, so text that would not fit is still an error and never
    // cut short.
    //
    // Re-runnable: a second run finds nothing to change.

    $itflow_collation = 'utf8mb4_general_ci';

    $itflow_tables = [
        'account_reconciliations', 'accounts', 'ai_models', 'ai_providers', 'ai_ticket_triage', 'ai_usage',
        'api_key_usage', 'api_keys', 'app_logs', 'asset_credentials', 'asset_custom', 'asset_documents',
        'asset_files', 'asset_history', 'asset_interface_links', 'asset_interfaces', 'asset_notes', 'asset_specs',
        'asset_tags', 'assets', 'backups', 'budget', 'business_holidays', 'calendar_event_attendees',
        'calendar_events', 'calendars', 'canned_response_categories', 'canned_response_files', 'canned_response_uses',
        'canned_responses', 'categories', 'certificate_history', 'certificates', 'client_notes', 'client_onboardings',
        'client_payment_provider', 'client_saved_payment_methods', 'client_tags', 'clients', 'companies',
        'contact_assets', 'contact_credentials', 'contact_documents', 'contact_files', 'contact_notes',
        'contact_tags', 'contacts', 'contract_block_uses', 'contract_blocks', 'contract_signatures',
        'contract_templates', 'contracts', 'credential_tags', 'credentials', 'credits', 'cron_jobs', 'custom_fields',
        'custom_links', 'custom_values', 'discount_codes', 'document_files', 'document_tags',
        'document_template_fill_ins', 'document_template_required_tags', 'document_templates', 'document_versions',
        'documents', 'domain_history', 'domain_tags', 'domains', 'email_queue', 'expense_import_rules', 'expenses',
        'files', 'folders', 'history', 'income_import_rules', 'invoice_items', 'invoices', 'location_documents',
        'location_files', 'location_tags', 'locations', 'logs', 'managed_instance_commands', 'managed_instances',
        'modules', 'network_ips', 'network_tags', 'networks', 'notifications', 'payment_methods', 'payment_providers',
        'payments', 'portal_announcements', 'portal_invites', 'product_prices', 'product_stock', 'products',
        'project_template_ticket_templates', 'project_templates', 'projects', 'quote_files', 'quote_items', 'quotes',
        'rack_units', 'racks', 'records', 'recurring_expenses', 'recurring_invoice_items', 'recurring_invoices',
        'recurring_payments', 'recurring_ticket_assets', 'recurring_ticket_fill_ins', 'recurring_ticket_tasks',
        'recurring_tickets', 'reimbursements', 'remember_tokens', 'revenues', 'service_assets',
        'service_certificates', 'service_contacts', 'service_credentials', 'service_documents', 'service_domains',
        'service_tags', 'service_tickets', 'service_vendors', 'services', 'settings', 'shared_items',
        'sla_assignments', 'sla_history', 'slas', 'sms_messages', 'sms_numbers', 'sms_providers', 'software',
        'software_assets', 'software_contacts', 'software_credentials', 'software_documents', 'software_files',
        'software_key_asset_assignments', 'software_key_contact_assignments', 'software_keys', 'software_tags',
        'software_template_required_tags', 'software_templates', 'tags', 'task_approvals', 'task_templates', 'tasks',
        'tax_components', 'taxes', 'ticket_assets', 'ticket_attachments', 'ticket_fill_ins', 'ticket_history',
        'ticket_replies', 'ticket_reply_mentions', 'ticket_statuses', 'ticket_tags', 'ticket_template_fill_ins',
        'ticket_template_portal_clients', 'ticket_template_tags', 'ticket_templates', 'ticket_views',
        'ticket_watchers', 'tickets', 'transfers', 'trips', 'user_calendar_feeds', 'user_client_permissions',
        'user_invites', 'user_role_permissions', 'user_roles', 'user_settings', 'users', 'vendor_credentials',
        'vendor_documents', 'vendor_files', 'vendor_tags', 'vendor_template_required_tags', 'vendor_templates',
        'vendors'
    ];

    // Each table's default collation, and its text columns that aren't $itflow_collation
    $itflow_table_collations = [];
    $itflow_table_columns = [];

    $itflow_result = mysqli_query($mysqli, "SELECT TABLE_NAME AS table_name, TABLE_COLLATION AS table_collation
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
    while ($itflow_result && $itflow_row = mysqli_fetch_assoc($itflow_result)) {
        $itflow_table_collations[$itflow_row['table_name']] = (string) $itflow_row['table_collation'];
    }

    $itflow_result = mysqli_query($mysqli, "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLLATION_NAME AS collation_name
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND COLLATION_NAME IS NOT NULL AND COLLATION_NAME <> '$itflow_collation'
        ORDER BY TABLE_NAME, ORDINAL_POSITION");
    while ($itflow_result && $itflow_row = mysqli_fetch_assoc($itflow_result)) {
        // Binary on purpose
        if (substr((string) $itflow_row['collation_name'], -4) === '_bin') {
            continue;
        }
        $itflow_table_columns[$itflow_row['table_name']][] = $itflow_row['column_name'];
    }

    // A text column's type as SHOW CREATE TABLE writes it. An enum's values are quoted,
    // with a quote inside one doubled.
    $itflow_text_type = "(?:var)?char\\(\\d+\\)|(?:tiny|medium|long)?text|(?:enum|set)\\('(?:[^']|'')*'(?:,'(?:[^']|'')*')*\\)";

    try {
        mysqli_query($mysqli, "SET @itflow_sql_mode = @@SESSION.sql_mode");
        mysqli_query($mysqli, "SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', '')");
    } catch (Throwable $itflow_error) {
        // Carry on with the mode as it is - records may then be one of the tables left
    }

    foreach ($itflow_tables as $itflow_table) {

        // Not on this install
        if (!isset($itflow_table_collations[$itflow_table])) {
            continue;
        }

        $itflow_columns = $itflow_table_columns[$itflow_table] ?? [];
        $itflow_default_differs = $itflow_table_collations[$itflow_table] !== $itflow_collation;

        if (!$itflow_columns && !$itflow_default_differs) {
            continue;
        }

        try {
            $itflow_changes = [];

            if ($itflow_columns) {
                $itflow_result = mysqli_query($mysqli, "SHOW CREATE TABLE `$itflow_table`");
                $itflow_row = $itflow_result ? mysqli_fetch_row($itflow_result) : null;
                if (!$itflow_row || empty($itflow_row[1])) {
                    throw new RuntimeException("its definition could not be read");
                }

                foreach ($itflow_columns as $itflow_column) {
                    // `name` type [CHARACTER SET x] [COLLATE y] and the rest - NOT NULL, DEFAULT,
                    // COMMENT - which goes back exactly as the server wrote it
                    if (!preg_match('/^[A-Za-z0-9_]+$/', $itflow_column)
                        || !preg_match('/^\s*[`"]?' . $itflow_column . '[`"]?\s+(' . $itflow_text_type . ')(?:\s+CHARACTER SET\s+\w+)?(?:\s+COLLATE\s+\w+)?(.*?),?\s*$/mi', $itflow_row[1], $itflow_match)) {
                        throw new RuntimeException("the definition of its column $itflow_column was not understood");
                    }
                    $itflow_changes[] = "MODIFY `$itflow_column` {$itflow_match[1]} CHARACTER SET utf8mb4 COLLATE $itflow_collation{$itflow_match[2]}";
                }
            }

            if ($itflow_default_differs) {
                $itflow_changes[] = "DEFAULT CHARACTER SET utf8mb4 COLLATE $itflow_collation";
            }

            if (!mysqli_query($mysqli, "ALTER TABLE `$itflow_table` " . implode(', ', $itflow_changes))) {
                throw new RuntimeException(mysqli_error($mysqli));
            }

        } catch (Throwable $itflow_error) {
            $itflow_message = "Database update 3.6.5 left the table $itflow_table with the collation it had (the rest of the update went on): " . $itflow_error->getMessage();
            error_log("ITFlow: $itflow_message");
            if (function_exists('logApp')) {
                try {
                    logApp('Database Update', 'warning', $itflow_message);
                } catch (Throwable $itflow_error) {
                    // The PHP error log has it
                }
            }
        }
    }

    try {
        mysqli_query($mysqli, "SET SESSION sql_mode = IFNULL(@itflow_sql_mode, @@SESSION.sql_mode)");
    } catch (Throwable $itflow_error) {
        // The connection ends with the update
    }

    unset($itflow_collation, $itflow_tables, $itflow_table_collations, $itflow_table_columns, $itflow_result, $itflow_row,
        $itflow_text_type, $itflow_table, $itflow_columns, $itflow_default_differs, $itflow_changes, $itflow_column,
        $itflow_match, $itflow_error, $itflow_message);
