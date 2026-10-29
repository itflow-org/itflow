<?php

/*
 * ITFlow - Database update to version 2.8.6 (from 2.8.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Projects: when the work starts, the hours sold for it, and who signs it off at the client
    mysqli_query($mysqli, "ALTER TABLE `projects` ADD COLUMN `project_start` date DEFAULT NULL AFTER `project_description`");
    mysqli_query($mysqli, "ALTER TABLE `projects` ADD COLUMN `project_budget_hours` decimal(8,2) DEFAULT NULL AFTER `project_due`");
    mysqli_query($mysqli, "ALTER TABLE `projects` ADD COLUMN `project_contact_id` int(11) NOT NULL DEFAULT 0 AFTER `project_manager`");

    // A project's tickets are looked up by project on every projects page
    mysqli_query($mysqli, "ALTER TABLE `tickets` ADD INDEX `ticket_project_id` (`ticket_project_id`)");

    // Deleting a client left its projects behind, and deleting a project left
    // its tickets pointing at it - clear out both
    mysqli_query($mysqli, "DELETE `projects` FROM `projects` LEFT JOIN `clients` ON `client_id` = `project_client_id` WHERE `project_client_id` > 0 AND `client_id` IS NULL");
    mysqli_query($mysqli, "UPDATE `tickets` LEFT JOIN `projects` ON `project_id` = `ticket_project_id` SET `ticket_project_id` = 0 WHERE `ticket_project_id` > 0 AND `project_id` IS NULL");

    // A project template's ticket templates whose template is gone
    mysqli_query($mysqli, "DELETE `project_template_ticket_templates` FROM `project_template_ticket_templates`
        LEFT JOIN `project_templates` ON `project_templates`.`project_template_id` = `project_template_ticket_templates`.`project_template_id`
        LEFT JOIN `ticket_templates` ON `ticket_templates`.`ticket_template_id` = `project_template_ticket_templates`.`ticket_template_id`
        WHERE `project_templates`.`project_template_id` IS NULL OR `ticket_templates`.`ticket_template_id` IS NULL");
