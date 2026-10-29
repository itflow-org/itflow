<?php

/*
 * ITFlow - Database update to version 3.5.0 (from 3.4.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * A client has no hourly rate, net terms or SLAs of its own any more: they
 * come from its contract in force, else the defaults (Admin > Settings >
 * Defaults, Admin > SLAs) - functions/contract_billing.php.
 *
 *   settings.config_default_after_hours_rate  the default after-hours rate -
 *                                             NULL, none: the hourly rate
 *                                             applies after hours too
 *   clients.client_rate, client_net_terms     dropped
 *   sla_assignments (client_id > 0)           deleted - client 0's rows are
 *                                             the defaults and stay
 *
 * A client whose own rate, net terms or SLAs differ from the defaults keeps
 * them, on a contract named "Standard terms" made for it here: active,
 * 12-month term renewing itself, no notice, setting only what differs. It
 * starts the day the client was added - before any contract the client
 * already has, so that one's terms still come first where it sets them, as
 * they did over the client's own. Its term is carried on to the one running
 * today. An archived client's is archived with it. A client SLA pointing at
 * no SLA, or at an archived one (the client's tickets got none), can't be
 * kept: a contract sets an SLA or leaves the default.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('settings', 'config_default_after_hours_rate')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_default_after_hours_rate` decimal(15,2) DEFAULT NULL AFTER `config_default_hourly_rate`");
    }

    if ($itflow_column('clients', 'client_rate') && $itflow_column('clients', 'client_net_terms')) {

        $itflow_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_default_hourly_rate, config_default_net_terms FROM settings WHERE company_id = 1")) ?: [];
        $itflow_default_rate = round(floatval($itflow_settings['config_default_hourly_rate'] ?? 0), 2);
        $itflow_default_net = intval($itflow_settings['config_default_net_terms'] ?? 0);

        // The default SLA for each priority - one that is archived counts as none, as it did for tickets
        $itflow_parts = ['Low' => 'low', 'Medium' => 'medium', 'High' => 'high', 'Urgent' => 'urgent'];
        $itflow_live_slas = [];
        $itflow_sql = mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_archived_at IS NULL");
        while ($itflow_sql && ($itflow_row = mysqli_fetch_row($itflow_sql))) {
            $itflow_live_slas[intval($itflow_row[0])] = true;
        }
        $itflow_default_slas = array_fill_keys(array_keys($itflow_parts), 0);
        $itflow_client_slas = [];
        $itflow_sql = mysqli_query($mysqli, "SELECT sla_assignment_client_id, sla_assignment_priority, sla_assignment_sla_id FROM sla_assignments");
        while ($itflow_sql && ($itflow_row = mysqli_fetch_assoc($itflow_sql))) {
            $itflow_priority = (string) $itflow_row['sla_assignment_priority'];
            $itflow_sla_id = intval($itflow_row['sla_assignment_sla_id']);
            if (!isset($itflow_parts[$itflow_priority]) || !isset($itflow_live_slas[$itflow_sla_id])) {
                continue;
            }
            if (intval($itflow_row['sla_assignment_client_id']) === 0) {
                $itflow_default_slas[$itflow_priority] = $itflow_sla_id;
            } else {
                $itflow_client_slas[intval($itflow_row['sla_assignment_client_id'])][$itflow_priority] = $itflow_sla_id;
            }
        }

        $itflow_today = date('Y-m-d');
        // A client given its Standard terms by an earlier run that stopped part way is left as it is
        $itflow_sql = mysqli_query($mysqli, "SELECT client_id, client_rate, client_net_terms, client_created_at, client_archived_at,
                (SELECT MIN(contract_start_date) FROM contracts WHERE contract_client_id = client_id AND contract_start_date IS NOT NULL) AS first_contract_start
            FROM clients
            WHERE NOT EXISTS (SELECT 1 FROM contracts WHERE contract_client_id = client_id AND contract_name = 'Standard terms' AND contract_notes LIKE 'Made by the 26.11 update%')
            ORDER BY client_id");
        while ($itflow_sql && ($itflow_client = mysqli_fetch_assoc($itflow_sql))) {
            $itflow_client_id = intval($itflow_client['client_id']);
            $itflow_set = [];

            // Only what differs from the defaults - a rate of nothing was no rate, the default applied. A
            // contract's rate holds under 100,000,000.00 - a rate past that was typed in by mistake, and
            // is left out (the default applies), or the contract couldn't be made and the update would stop
            $itflow_rate = round(floatval($itflow_client['client_rate']), 2);
            if ($itflow_rate > 0 && $itflow_rate < 100000000 && $itflow_rate != $itflow_default_rate) {
                $itflow_set[] = "contract_rate_standard = " . sprintf('%.2f', $itflow_rate);
            }
            if ($itflow_client['client_net_terms'] !== null && intval($itflow_client['client_net_terms']) !== $itflow_default_net) {
                $itflow_set[] = "contract_net_terms = " . intval($itflow_client['client_net_terms']);
            }
            foreach ($itflow_parts as $itflow_priority => $itflow_part) {
                $itflow_sla_id = intval($itflow_client_slas[$itflow_client_id][$itflow_priority] ?? 0);
                if ($itflow_sla_id && $itflow_sla_id !== $itflow_default_slas[$itflow_priority]) {
                    $itflow_set[] = "contract_sla_{$itflow_part}_id = $itflow_sla_id";
                }
            }
            if (!$itflow_set) {
                continue;
            }

            // From the day the client was added, before any contract it already has
            $itflow_start = substr((string) $itflow_client['client_created_at'], 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $itflow_start) || $itflow_start === '0000-00-00') {
                $itflow_start = $itflow_today;
            }
            $itflow_first = (string) $itflow_client['first_contract_start'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $itflow_first) && $itflow_first !== '0000-00-00' && $itflow_first <= $itflow_start) {
                $itflow_start = date('Y-m-d', strtotime('-1 day', strtotime($itflow_first)));
            }
            // Its 12-month term carried on to the one running today
            $itflow_end = date('Y-m-d', strtotime('+12 months -1 day', strtotime($itflow_start)));
            while ($itflow_end < $itflow_today) {
                $itflow_end = date('Y-m-d', strtotime('+12 months -1 day', strtotime('+1 day', strtotime($itflow_end))));
            }

            $itflow_archived = $itflow_client['client_archived_at'] ? "'" . mysqli_real_escape_string($mysqli, $itflow_client['client_archived_at']) . "'" : 'NULL';
            mysqli_query($mysqli, "INSERT INTO contracts SET contract_name = 'Standard terms', contract_status = 'Active', contract_type = 'other',
                contract_client_id = $itflow_client_id, contract_start_date = '$itflow_start', contract_end_date = '$itflow_end',
                contract_term_months = 12, contract_auto_renew = 1, contract_notice_days = 0, contract_archived_at = $itflow_archived,
                contract_notes = 'Made by the 26.11 update from the hourly rate, net terms and SLAs the client had of its own, where they differed from the defaults - a client has none of its own from 26.11.',
                " . implode(', ', $itflow_set));
        }

        mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id > 0");
        mysqli_query($mysqli, "ALTER TABLE `clients` DROP `client_rate`, DROP `client_net_terms`");
    }
