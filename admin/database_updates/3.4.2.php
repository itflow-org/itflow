<?php

/*
 * ITFlow - Database update to version 3.4.2 (from 3.4.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * Client contracts, stage 1. Contracts and contract templates take their
 * response and resolution targets from Admin > SLAs - an SLA for each ticket
 * priority - instead of hours of their own; a template's hours become the
 * SLA with the same targets, or a new one named for the template. The
 * renewal frequency becomes a term in months with auto-renew on or off, and
 * a notice period; net terms become a number of days; the types become keys
 * (functions/contract.php). A contract also has a contact at the client, an
 * owner who is reminded before it ends, internal notes, and when it was
 * renewed, reminded about and cancelled; its status is Draft, Active, Ended
 * or Cancelled. Deleting a client deletes its contracts - the foreign key
 * used to stop the client being deleted.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    // The new columns - on both tables, the contract's after its template's
    foreach (['contract_templates' => 'contract_template', 'contracts' => 'contract'] as $itflow_table => $itflow_prefix) {
        $itflow_adds = [
            "{$itflow_prefix}_term_months" => "int(11) NOT NULL DEFAULT 12",
            "{$itflow_prefix}_auto_renew" => "tinyint(1) NOT NULL DEFAULT 1",
            "{$itflow_prefix}_notice_days" => "int(11) NOT NULL DEFAULT 30",
            "{$itflow_prefix}_sla_low_id" => "int(11) NOT NULL DEFAULT 0",
            "{$itflow_prefix}_sla_medium_id" => "int(11) NOT NULL DEFAULT 0",
            "{$itflow_prefix}_sla_high_id" => "int(11) NOT NULL DEFAULT 0",
            "{$itflow_prefix}_sla_urgent_id" => "int(11) NOT NULL DEFAULT 0",
        ];
        if ($itflow_table === 'contracts') {
            $itflow_adds += [
                'contract_template_id' => "int(11) NOT NULL DEFAULT 0",
                'contract_contact_id' => "int(11) NOT NULL DEFAULT 0",
                'contract_owner_id' => "int(11) NOT NULL DEFAULT 0",
                'contract_notes' => "text DEFAULT NULL",
                'contract_renewed_at' => "datetime DEFAULT NULL",
                'contract_reminded_at' => "datetime DEFAULT NULL",
                'contract_cancelled_at' => "datetime DEFAULT NULL",
                'contract_cancel_reason' => "varchar(255) DEFAULT NULL",
                'contract_created_by' => "int(11) NOT NULL DEFAULT 0",
            ];
        }
        foreach ($itflow_adds as $itflow_name => $itflow_definition) {
            if (!$itflow_column($itflow_table, $itflow_name)) {
                mysqli_query($mysqli, "ALTER TABLE `$itflow_table` ADD `$itflow_name` $itflow_definition");
            }
        }
    }

    // An SLA with these targets - one already in Admin > SLAs, or a new one named for the template
    $itflow_sla_for = function ($response_hours, $resolution_hours, $name) use ($mysqli) {
        $response = intval($response_hours) * 60;
        $resolution = intval($resolution_hours) * 60;
        if ($response <= 0) {
            return 0;
        }
        $found = mysqli_fetch_row(mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_archived_at IS NULL AND sla_response_minutes = $response
            AND IFNULL(sla_resolution_minutes, 0) = $resolution ORDER BY sla_id LIMIT 1"));
        if ($found) {
            return intval($found[0]);
        }
        $name = mysqli_real_escape_string($mysqli, mb_substr($name, 0, 200));
        mysqli_query($mysqli, "INSERT INTO slas SET sla_name = '$name', sla_description = 'Made from a contract template\\'s response and resolution hours',
            sla_response_minutes = $response, sla_resolution_minutes = " . ($resolution > 0 ? $resolution : 'NULL'));
        return intval(mysqli_insert_id($mysqli));
    };

    // The old type names, as keys
    $itflow_types = ['fully managed' => 'managed', 'partialy managed' => 'comanaged', 'partially managed' => 'comanaged', 'co-managed' => 'comanaged', 'break/fix' => 'break_fix'];
    // The old renewal frequencies, as months and whether it renews itself
    $itflow_frequencies = ['manual' => [12, 0], 'annually' => [12, 1], '2 year' => [24, 1], '3 year' => [36, 1], '5 year' => [60, 1], '7 year' => [84, 1]];

    foreach (['contract_templates' => 'contract_template', 'contracts' => 'contract'] as $itflow_table => $itflow_prefix) {
        $itflow_has_hours = $itflow_column($itflow_table, "{$itflow_prefix}_sla_low_response_time");
        $itflow_has_frequency = $itflow_column($itflow_table, "{$itflow_prefix}_renewal_frequency");
        $itflow_name_column = $itflow_table === 'contract_templates' ? 'contract_template_name' : 'contract_name';
        $itflow_rows = mysqli_query($mysqli, "SELECT * FROM `$itflow_table`");
        while ($itflow_row = mysqli_fetch_assoc($itflow_rows)) {
            $itflow_set = [];
            if ($itflow_has_hours) {
                foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $itflow_level => $itflow_label) {
                    $itflow_set["{$itflow_prefix}_sla_{$itflow_level}_id"] = $itflow_sla_for($itflow_row["{$itflow_prefix}_sla_{$itflow_level}_response_time"],
                        $itflow_row["{$itflow_prefix}_sla_{$itflow_level}_resolution_time"], $itflow_row[$itflow_name_column] . " - $itflow_label");
                }
                // There were no urgent targets - urgent takes high's
                $itflow_set["{$itflow_prefix}_sla_urgent_id"] = $itflow_set["{$itflow_prefix}_sla_high_id"];
            }
            if ($itflow_has_frequency) {
                [$itflow_months, $itflow_auto] = $itflow_frequencies[strtolower(trim((string) $itflow_row["{$itflow_prefix}_renewal_frequency"]))] ?? [12, 1];
                $itflow_set["{$itflow_prefix}_term_months"] = $itflow_months;
                $itflow_set["{$itflow_prefix}_auto_renew"] = $itflow_auto;
            }
            $itflow_type = strtolower(trim((string) $itflow_row["{$itflow_prefix}_type"]));
            if ($itflow_type !== '') {
                $itflow_set["{$itflow_prefix}_type"] = $itflow_types[$itflow_type] ?? (in_array($itflow_type, ['managed', 'comanaged', 'block', 'time_materials', 'break_fix', 'project', 'other'], true) ? $itflow_type : 'other');
            }
            // Net terms as days - "Net 30" is 30, anything without a number none
            $itflow_terms = preg_replace('/\D/', '', (string) $itflow_row["{$itflow_prefix}_net_terms"]);
            $itflow_set["{$itflow_prefix}_net_terms"] = $itflow_terms !== '' ? min(intval($itflow_terms), 365) : null;
            // A contract's status is one of four now - other words for them become theirs, anything else Active
            if ($itflow_table === 'contracts') {
                $itflow_status = strtolower(trim((string) $itflow_row['contract_status']));
                $itflow_set['contract_status'] = in_array($itflow_status, ['draft', 'pending', 'new', 'proposed', 'quote', 'quoted'], true) ? 'Draft'
                    : (in_array($itflow_status, ['ended', 'expired', 'inactive', 'closed', 'complete', 'completed', 'lapsed'], true) ? 'Ended'
                    : (in_array($itflow_status, ['cancelled', 'canceled', 'terminated', 'void'], true) ? 'Cancelled' : 'Active'));
            }
            $itflow_sql = [];
            foreach ($itflow_set as $itflow_column_name => $itflow_value) {
                $itflow_sql[] = "`$itflow_column_name` = " . ($itflow_value === null ? 'NULL' : (is_int($itflow_value) ? $itflow_value : "'" . mysqli_real_escape_string($mysqli, (string) $itflow_value) . "'"));
            }
            $itflow_id_column = $itflow_table === 'contract_templates' ? 'contract_template_id' : 'contract_id';
            mysqli_query($mysqli, "UPDATE `$itflow_table` SET " . implode(', ', $itflow_sql) . " WHERE `$itflow_id_column` = " . intval($itflow_row[$itflow_id_column]));
        }

        // The hours and the frequency are now SLAs and a term - their columns go
        foreach (['sla_low_response_time', 'sla_low_resolution_time', 'sla_medium_response_time', 'sla_medium_resolution_time', 'sla_high_response_time', 'sla_high_resolution_time', 'renewal_frequency'] as $itflow_old) {
            if ($itflow_column($itflow_table, "{$itflow_prefix}_$itflow_old")) {
                mysqli_query($mysqli, "ALTER TABLE `$itflow_table` DROP COLUMN `{$itflow_prefix}_$itflow_old`");
            }
        }
        // Net terms are days now
        mysqli_query($mysqli, "ALTER TABLE `$itflow_table` MODIFY `{$itflow_prefix}_net_terms` int(11) DEFAULT NULL");
    }

    // Deleting a client deletes its contracts - the foreign key used to stop the client being deleted at all
    $itflow_keys = mysqli_query($mysqli, "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND REFERENCED_TABLE_NAME = 'clients' AND DELETE_RULE <> 'CASCADE'");
    while ($itflow_key = mysqli_fetch_row($itflow_keys)) {
        mysqli_query($mysqli, "ALTER TABLE `contracts` DROP FOREIGN KEY `" . $itflow_key[0] . "`");
        mysqli_query($mysqli, "ALTER TABLE `contracts` ADD CONSTRAINT `" . $itflow_key[0] . "` FOREIGN KEY (`contract_client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE");
    }

    unset($itflow_column, $itflow_table, $itflow_prefix, $itflow_adds, $itflow_name, $itflow_definition, $itflow_sla_for, $itflow_types, $itflow_frequencies, $itflow_has_hours,
        $itflow_has_frequency, $itflow_name_column, $itflow_rows, $itflow_row, $itflow_set, $itflow_level, $itflow_label, $itflow_months, $itflow_auto, $itflow_type, $itflow_terms,
        $itflow_status, $itflow_sql, $itflow_column_name, $itflow_value, $itflow_id_column, $itflow_old, $itflow_keys, $itflow_key);
