<?php

/*
 * ITFlow - Database update to version 3.5.2 (from 3.5.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * Client onboarding (functions/client_onboarding.php, agent/client_onboarding.php):
 * a client being taken on, a step at a time - its company, locations,
 * people, agreement, billing, IT basics and a kickoff project. One row per
 * client onboarded; a client added before, or the quick way, has none.
 *
 *   onboarding_saved      the steps saved in the wizard, comma separated -
 *                         most also tick themselves off from what the client has
 *   onboarding_skipped    the steps put off for now, comma separated
 *   onboarding_contract_id, _recurring_invoice_id, _project_id
 *                         what its Agreement, Billing and Kickoff steps made
 *   onboarding_finished_at  Finish pressed on its summary
 *   onboarding_dismissed_at the client overview's card closed
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `client_onboardings` (
        `onboarding_id` int(11) NOT NULL AUTO_INCREMENT,
        `onboarding_client_id` int(11) NOT NULL,
        `onboarding_saved` varchar(255) NOT NULL DEFAULT '',
        `onboarding_skipped` varchar(255) NOT NULL DEFAULT '',
        `onboarding_contract_id` int(11) NOT NULL DEFAULT 0,
        `onboarding_recurring_invoice_id` int(11) NOT NULL DEFAULT 0,
        `onboarding_project_id` int(11) NOT NULL DEFAULT 0,
        `onboarding_started_by` int(11) NOT NULL DEFAULT 0,
        `onboarding_started_at` datetime NOT NULL DEFAULT current_timestamp(),
        `onboarding_finished_at` datetime DEFAULT NULL,
        `onboarding_dismissed_at` datetime DEFAULT NULL,
        PRIMARY KEY (`onboarding_id`),
        UNIQUE KEY `onboarding_client_id` (`onboarding_client_id`),
        CONSTRAINT `client_onboardings_ibfk_1` FOREIGN KEY (`onboarding_client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
