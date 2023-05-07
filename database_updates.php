<?php
/*
 * ITFlow
 * This file defines the SQL queries required to update the database to the "latest" database version
 * It is used in conjunction with database_version.php
 */

// Check if our database versions are defined
// If undefined, the file is probably being accessed directly rather than called via post.php?update_db
if (!defined("LATEST_DATABASE_VERSION") || !defined("CURRENT_DATABASE_VERSION") || !isset($mysqli)) {
    echo "Cannot access this file directly.";
    exit();
}

// Check if we need an update
if (LATEST_DATABASE_VERSION > CURRENT_DATABASE_VERSION) {

    // We need updates!

    if (CURRENT_DATABASE_VERSION == '0.0.1') {
        // Insert queries here required to update to DB version 0.0.2

        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_module_enable_itdoc` TINYINT(1) DEFAULT 1 AFTER `config_backup_path`");
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_module_enable_ticketing` TINYINT(1) DEFAULT 1 AFTER `config_module_enable_itdoc`");
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_module_enable_accounting` TINYINT(1) DEFAULT 1 AFTER `config_module_enable_ticketing`");

        // Update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.2'");
    }

    if (CURRENT_DATABASE_VERSION == '0.0.2') {
        // Insert queries here required to update to DB version 0.0.3

        // Add document content raw column & index
        mysqli_query($mysqli, "ALTER TABLE `documents` ADD `document_content_raw` LONGTEXT NOT NULL AFTER `document_content`, ADD FULLTEXT `document_content_raw` (`document_content_raw`)");

        // Populate content raw column with existing document data
        $documents_sql = mysqli_query($mysqli, "SELECT * FROM `documents`");
        while($row = mysqli_fetch_array($documents_sql)) {
            $id = $row['document_id'];
            $name = $row['document_name'];
            $content = $row['document_content'];
            $content_raw = trim(mysqli_real_escape_string($mysqli, strip_tags($name . " " . str_replace("<", " <", $content))));

            mysqli_query($mysqli, "UPDATE `documents` SET `document_content_raw` = '$content_raw' WHERE `document_id` = '$id'");
        }

        // Add API key client column
        mysqli_query($mysqli, "ALTER TABLE `api_keys` ADD `api_key_client_id` INT NOT NULL DEFAULT '0' AFTER `api_key_expire`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.3'");
    }

    if (CURRENT_DATABASE_VERSION == '0.0.3') {
        // Insert queries here required to update to DB version 0.0.4
        // mysqli_query($mysqli, "ALTER TABLE .....");

        // Update all tables updated/modified fields to be automatic

        mysqli_query($mysqli, "ALTER TABLE `accounts` CHANGE `account_created_at` `account_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `account_updated_at` `account_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL;");

        mysqli_query($mysqli, "ALTER TABLE `api_keys` CHANGE `api_key_created_at` `api_key_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP; ");

        mysqli_query($mysqli, "ALTER TABLE `assets` CHANGE `asset_created_at` `asset_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `asset_updated_at` `asset_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL;");

        mysqli_query($mysqli, "ALTER TABLE `calendars` CHANGE `calendar_created_at` `calendar_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `calendar_updated_at` `calendar_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `campaigns` CHANGE `campaign_created_at` `campaign_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `campaign_updated_at` `campaign_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `campaign_messages` CHANGE `message_created_at` `message_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `message_updated_at` `message_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `categories` CHANGE `category_created_at` `category_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `category_updated_at` `category_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `certificates` CHANGE `certificate_created_at` `certificate_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `certificate_updated_at` `certificate_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `clients` CHANGE `client_created_at` `client_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `client_updated_at` `client_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `companies` CHANGE `company_created_at` `company_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `company_updated_at` `company_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `contacts` CHANGE `contact_created_at` `contact_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `contact_updated_at` `contact_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `contracts` CHANGE `contract_created_at` `contract_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `contract_updated_at` `contract_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `custom_links` CHANGE `custom_link_created_at` `custom_link_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP; ");

        mysqli_query($mysqli, "ALTER TABLE `departments` CHANGE `department_created_at` `department_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `department_updated_at` `department_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `documents` CHANGE `document_created_at` `document_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `document_updated_at` `document_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `domains` CHANGE `domain_created_at` `domain_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `domain_updated_at` `domain_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `events` CHANGE `event_created_at` `event_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `event_updated_at` `event_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `expenses` CHANGE `expense_created_at` `expense_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `expense_updated_at` `expense_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `files` CHANGE `file_created_at` `file_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `file_updated_at` `file_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL;");

        mysqli_query($mysqli, "ALTER TABLE `history` CHANGE `history_created_at` `history_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP; ");

        mysqli_query($mysqli, "ALTER TABLE `invoices` CHANGE `invoice_created_at` `invoice_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `invoice_updated_at` `invoice_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_created_at` `item_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `item_updated_at` `item_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `locations` CHANGE `location_created_at` `location_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `location_updated_at` `location_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `logins` CHANGE `login_created_at` `login_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `login_updated_at` `login_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `logs` CHANGE `log_created_at` `log_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP; ");

        mysqli_query($mysqli, "ALTER TABLE `networks` CHANGE `network_created_at` `network_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `network_updated_at` `network_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `notifications` CHANGE `notification_timestamp` `notification_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP; ");

        mysqli_query($mysqli, "ALTER TABLE `payments` CHANGE `payment_created_at` `payment_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `payment_updated_at` `payment_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `products` CHANGE `product_created_at` `product_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `product_updated_at` `product_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `quotes` CHANGE `quote_created_at` `quote_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `quote_updated_at` `quote_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `records` CHANGE `record_created_at` `record_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `record_updated_at` `record_updated_at` DATETIME on update CURRENT_TIMESTAMP NOT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `recurring` CHANGE `recurring_created_at` `recurring_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `recurring_updated_at` `recurring_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `scheduled_tickets` CHANGE `scheduled_ticket_created_at` `scheduled_ticket_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `scheduled_ticket_updated_at` `scheduled_ticket_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `services` CHANGE `service_created_at` `service_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `service_updated_at` `service_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `shared_items` CHANGE `item_created_at` `item_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP; ");

        mysqli_query($mysqli, "ALTER TABLE `software` CHANGE `software_created_at` `software_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `software_updated_at` `software_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `tags` CHANGE `tag_created_at` `tag_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `tag_updated_at` `tag_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `taxes` CHANGE `tax_created_at` `tax_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `tax_updated_at` `tax_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `tickets` CHANGE `ticket_created_at` `ticket_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `ticket_updated_at` `ticket_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `ticket_replies` CHANGE `ticket_reply_created_at` `ticket_reply_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `ticket_reply_updated_at` `ticket_reply_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `transfers` CHANGE `transfer_created_at` `transfer_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `transfer_updated_at` `transfer_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `trips` CHANGE `trip_created_at` `trip_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `trip_updated_at` `trip_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `users` CHANGE `user_created_at` `user_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `user_updated_at` `user_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        mysqli_query($mysqli, "ALTER TABLE `vendors` CHANGE `vendor_created_at` `vendor_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `vendor_updated_at` `vendor_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE settings SET config_current_database_version = '0.0.4'");

    }

    if (CURRENT_DATABASE_VERSION == '0.0.4') {
        // Queries here required to update to DB version 0.0.5

        mysqli_query($mysqli, "ALTER TABLE `assets` DROP `asset_meshcentral_id`;");
        mysqli_query($mysqli, "ALTER TABLE `clients` DROP `client_meshcentral_group`;");
        mysqli_query($mysqli, "ALTER TABLE `settings` DROP `config_meshcentral_uri`, DROP `config_meshcentral_user`, DROP `config_meshcentral_secret`;");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.5'");
    }

    if (CURRENT_DATABASE_VERSION == '0.0.5') {
        // Insert queries here required to update to DB version 0.0.6

        mysqli_query($mysqli, "UPDATE documents SET document_folder_id = 0");

        mysqli_query($mysqli, "DROP TABLE documents_tagged");
        mysqli_query($mysqli, "DROP TABLE document_tags");


        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE settings SET config_current_database_version = '0.0.6'");
    }

    if (CURRENT_DATABASE_VERSION == '0.0.6') {
        // Insert queries here required to update to DB version 0.0.7
        mysqli_query($mysqli, "ALTER TABLE contacts ADD contact_department VARCHAR(200) NULL AFTER contact_title");
        mysqli_query($mysqli, "DROP TABLE departments");
        mysqli_query($mysqli, "ALTER TABLE contacts DROP contact_department_id");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.7'");
    }

    if (CURRENT_DATABASE_VERSION == '0.0.7') {
        // Insert queries here required to update to DB version 0.0.8

        // Add contact_department column to tables without it (fresh installs) - this will cause an error if it already exists so catch and discard it
        try{
            mysqli_query($mysqli, "ALTER TABLE contacts ADD contact_department VARCHAR(200) NULL AFTER contact_title");
        } catch(Exception $e) {
            // Nothing
        }

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.8'");
    }

    if (CURRENT_DATABASE_VERSION == '0.0.8') {
        // Insert queries here required to update to DB version 0.0.9

        mysqli_query($mysqli, "ALTER TABLE `revenues` CHANGE `revenue_created_at` `revenue_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE `revenue_updated_at` `revenue_updated_at` DATETIME on update CURRENT_TIMESTAMP NULL DEFAULT NULL; ");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.9'");
    }

    if (CURRENT_DATABASE_VERSION == '0.0.9') {
        // Insert queries here required to update to DB version 0.0.9
        // Remove unused tables
        mysqli_query($mysqli, "DROP TABLE contracts");
        mysqli_query($mysqli, "DROP TABLE messages");
        mysqli_query($mysqli, "DROP TABLE roles");

        //Remove updated at as API keys can only be added or revoked
        mysqli_query($mysqli, "ALTER TABLE `api_keys` DROP `api_key_updated_at`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.0'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.0') {
        // Insert queries here required to update to DB version 0.1.1
        // Logs don't get archived
        mysqli_query($mysqli, "ALTER TABLE `logs` DROP `log_archived_at`");

        // Assets will eventualy have file associatons which could include a receipt.
        mysqli_query($mysqli, "ALTER TABLE `assets` DROP `asset_reciept`");

        mysqli_query($mysqli, "ALTER TABLE `campaign_messages` DROP `message_updated_at`");
        // This will be a seperate table eventually called contact_documents because contact can have several documents
        mysqli_query($mysqli, "ALTER TABLE `documents` DROP `document_contact_id`");

        mysqli_query($mysqli, "ALTER TABLE `expenses` DROP `expense_asset_id`");
        mysqli_query($mysqli, "ALTER TABLE `files` DROP `file_contact_id`");
        mysqli_query($mysqli, "ALTER TABLE `history` DROP `history_archived_at`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.1'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.1') {
        // Insert queries here required to update to DB version 0.1.2
        // Create Many to Many Relationship tables for Assets, Contacts, Software and Vendors

        mysqli_query($mysqli, "CREATE TABLE `asset_documents` (`asset_id` int(11) NOT NULL,`document_id` int(11) NOT NULL, PRIMARY KEY (`asset_id`,`document_id`))");
        mysqli_query($mysqli, "CREATE TABLE `asset_logins` (`asset_id` int(11) NOT NULL,`login_id` int(11) NOT NULL, PRIMARY KEY (`asset_id`,`login_id`))");
        mysqli_query($mysqli, "CREATE TABLE `asset_files` (`asset_id` int(11) NOT NULL,`file_id` int(11) NOT NULL, PRIMARY KEY (`asset_id`,`file_id`))");

        mysqli_query($mysqli, "CREATE TABLE `contact_documents` (`contact_id` int(11) NOT NULL,`document_id` int(11) NOT NULL, PRIMARY KEY (`contact_id`,`document_id`))");
        mysqli_query($mysqli, "CREATE TABLE `contact_logins` (`contact_id` int(11) NOT NULL,`login_id` int(11) NOT NULL, PRIMARY KEY (`contact_id`,`login_id`))");
        mysqli_query($mysqli, "CREATE TABLE `contact_files` (`contact_id` int(11) NOT NULL,`file_id` int(11) NOT NULL, PRIMARY KEY (`contact_id`,`file_id`))");

        mysqli_query($mysqli, "CREATE TABLE `software_documents` (`software_id` int(11) NOT NULL,`document_id` int(11) NOT NULL, PRIMARY KEY (`software_id`,`document_id`))");
        mysqli_query($mysqli, "CREATE TABLE `software_logins` (`software_id` int(11) NOT NULL,`login_id` int(11) NOT NULL, PRIMARY KEY (`software_id`,`login_id`))");
        mysqli_query($mysqli, "CREATE TABLE `software_files` (`software_id` int(11) NOT NULL,`file_id` int(11) NOT NULL, PRIMARY KEY (`software_id`,`file_id`))");

        mysqli_query($mysqli, "CREATE TABLE `vendor_documents` (`vendor_id` int(11) NOT NULL,`document_id` int(11) NOT NULL, PRIMARY KEY (`vendor_id`,`document_id`))");
        mysqli_query($mysqli, "CREATE TABLE `vendor_logins` (`vendor_id` int(11) NOT NULL,`login_id` int(11) NOT NULL, PRIMARY KEY (`vendor_id`,`login_id`))");
        mysqli_query($mysqli, "CREATE TABLE `vendor_files` (`vendor_id` int(11) NOT NULL,`file_id` int(11) NOT NULL, PRIMARY KEY (`vendor_id`,`file_id`))");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.2'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.2') {
        // Insert queries here required to update to DB version 0.1.3
        mysqli_query($mysqli, "ALTER TABLE `logs` ADD `log_entity_id` INT NOT NULL DEFAULT '0' AFTER `log_user_id`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.3'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.3') {
        // Insert queries here required to update to DB version 0.1.4
        mysqli_query($mysqli, "ALTER TABLE assets ADD asset_status VARCHAR(200) NULL AFTER asset_mac");

        ///Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.4'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.4') {
        // Insert queries here required to update to DB version 0.1.5
        mysqli_query($mysqli, "ALTER TABLE `domains` ADD `domain_txt` TEXT NULL DEFAULT NULL AFTER `domain_mail_servers`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.5'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.5') {
        // Insert queries here required to update to DB version 0.1.6
        // Remove Mailing List Tables
        mysqli_query($mysqli, "DROP TABLE campaigns");
        mysqli_query($mysqli, "DROP TABLE campaign_messages");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.6'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.6') {
        // Insert queries here required to update to DB version 0.1.7
        //Remove custom links
        mysqli_query($mysqli, "DROP TABLE custom_links");
        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.7'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.7') {
        // Insert queries here required to update to DB version 0.1.8
        mysqli_query($mysqli, "ALTER TABLE `settings` DROP `config_backup_enable`");
        mysqli_query($mysqli, "ALTER TABLE `settings` DROP `config_backup_path`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.8'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.8') {
        // Insert queries here required to update to DB version 0.1.9
        mysqli_query($mysqli, "ALTER TABLE `settings` DROP `config_base_url`");
        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.1.9'");
    }

    if (CURRENT_DATABASE_VERSION == '0.1.9') {
        // Insert queries here required to update to DB version 0.2.0
        // Allow contacts to reset their portal password
        mysqli_query($mysqli, "ALTER TABLE contacts ADD contact_password_reset_token VARCHAR(200) NULL DEFAULT NULL AFTER contact_password_hash");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.0'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.0') {
        //Insert queries here required to update to DB version 0.2.1

        mysqli_query($mysqli, "ALTER TABLE `vendors` 
      ADD `vendor_hours` VARCHAR(200) NULL DEFAULT NULL AFTER `vendor_website`,
      ADD `vendor_sla` VARCHAR(200) NULL DEFAULT NULL AFTER `vendor_hours`,
      ADD `vendor_code` VARCHAR(200) NULL DEFAULT NULL AFTER `vendor_sla`,
      ADD `vendor_template_id` INT(11) DEFAULT 0 AFTER `vendor_archived_at`
    ");

        mysqli_query($mysqli, "ALTER TABLE `vendors`
      DROP `vendor_country`, 
      DROP `vendor_address`, 
      DROP `vendor_city`, 
      DROP `vendor_state`, 
      DROP `vendor_zip`, 
      DROP `vendor_global`
    ");

        //Create New Vendor Templates Table
        mysqli_query($mysqli, "CREATE TABLE `vendor_templates` (`vendor_template_id` int(11) AUTO_INCREMENT PRIMARY KEY,
      `vendor_template_name` varchar(200) NOT NULL,
      `vendor_template_description` varchar(200) NULL DEFAULT NULL,
      `vendor_template_phone` varchar(200) NULL DEFAULT NULL, 
      `vendor_template_email` varchar(200) NULL DEFAULT NULL,
      `vendor_template_website` varchar(200) NULL DEFAULT NULL,
      `vendor_template_hours` varchar(200) NULL DEFAULT NULL,
      `vendor_template_created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `vendor_template_updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
      `vendor_template_archived_at` datetime NULL DEFAULT NULL,
      `company_id` int(11) NOT NULL
    )");

        //Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.1'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.1') {
        // Insert queries here required to update to DB version 0.2.2
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_email_parse` INT(1) NOT NULL DEFAULT '0' AFTER `config_ticket_from_email`");
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_imap_host` VARCHAR(200) NULL DEFAULT NULL AFTER `config_mail_from_name`, ADD `config_imap_port` INT(5) NULL DEFAULT NULL AFTER `config_imap_host`, ADD `config_imap_encryption` VARCHAR(200) NULL DEFAULT NULL AFTER `config_imap_port`;");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.2'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.2') {
        // Insert queries here required to update to DB version 0.2.3

        // Add contact_important field to those who don't have it (installed before March 2022)
        try {
            mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_important` tinyint(1) NOT NULL DEFAULT 0 AFTER contact_password_reset_token;");
        } catch (Exception $e) {
            // Field already exists - that's fine
        }

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.3'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.3') {

        //Create New interfaces Table
        mysqli_query($mysqli, "CREATE TABLE `interfaces` (`interface_id` int(11) AUTO_INCREMENT PRIMARY KEY,
    `interface_number` int(11) NULL DEFAULT NULL,
    `interface_description` varchar(200) NULL DEFAULT NULL,
    `interface_connected_asset` varchar(200) NULL DEFAULT NULL, 
    `interface_ip` varchar(200) NULL DEFAULT NULL,
    `interface_created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `interface_updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
    `interface_archived_at` datetime NULL DEFAULT NULL,
    `interface_connected_asset_id` int(11) NOT NULL DEFAULT 0,
    `interface_network_id` int(11) NOT NULL DEFAULT 0,
    `interface_asset_id` int(11) NOT NULL,
    `company_id` int(11) NOT NULL
  )");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.4'");

    }

    if (CURRENT_DATABASE_VERSION == '0.2.4') {
        mysqli_query($mysqli, "CREATE TABLE `contact_assets` (`contact_id` int(11) NOT NULL,`asset_id` int(11) NOT NULL, PRIMARY KEY (`contact_id`,`asset_id`))");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.5'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.5') {
        mysqli_query($mysqli, "ALTER TABLE `users` ADD `user_status` TINYINT(1) DEFAULT 1 AFTER `user_password`");
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.6'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.6') {
        // Insert queries here required to update to DB version 0.2.7
        mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_token_expire` DATETIME NULL DEFAULT NULL AFTER `contact_password_reset_token`");

        // Update config.php var with new version var for use with docker
        file_put_contents("config.php", "\$repo_branch = 'master';" . PHP_EOL, FILE_APPEND);


        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.7'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.7') {

        mysqli_query($mysqli, "ALTER TABLE `vendors` ADD `vendor_template` TINYINT(1) DEFAULT 0 AFTER `vendor_notes`");
        mysqli_query($mysqli, "ALTER TABLE `software` ADD `software_template` TINYINT(1) DEFAULT 0 AFTER `software_notes`");
        mysqli_query($mysqli, "ALTER TABLE `vendors` DROP `vendor_template_id`");
        mysqli_query($mysqli, "DROP TABLE vendor_templates");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.8'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.8') {

        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_theme` VARCHAR(200) DEFAULT 'blue' AFTER `config_module_enable_ticketing`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.2.9'");
    }

    if (CURRENT_DATABASE_VERSION == '0.2.9') {

        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_client_general_notifications` INT(1) NOT NULL DEFAULT '1' AFTER `config_ticket_email_parse`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.0'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.0') {
        mysqli_query($mysqli, "ALTER TABLE `notifications` ADD `notification_user_id` TINYINT(1) DEFAULT 0 AFTER `notification_client_id`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.1'");
    }


    if (CURRENT_DATABASE_VERSION == '0.3.1') {

        // Assets

        mysqli_query($mysqli, "UPDATE `assets` SET `asset_login_id` = 0 WHERE `asset_login_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `assets` CHANGE `asset_login_id` `asset_login_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `assets` SET `asset_vendor_id` = 0 WHERE `asset_vendor_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `assets` CHANGE `asset_vendor_id` `asset_vendor_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `assets` SET `asset_location_id` = 0 WHERE `asset_location_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `assets` CHANGE `asset_location_id` `asset_location_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `assets` SET `asset_network_id` = 0 WHERE `asset_network_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `assets` CHANGE `asset_network_id` `asset_network_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `assets` SET `asset_client_id` = 0 WHERE `asset_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `assets` CHANGE `asset_client_id` `asset_client_id` INT(11) NOT NULL DEFAULT 0");

        // Certificates

        mysqli_query($mysqli, "UPDATE `certificates` SET `certificate_domain_id` = 0 WHERE `certificate_domain_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `certificates` CHANGE `certificate_domain_id` `certificate_domain_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `certificates` CHANGE `certificate_client_id` `certificate_client_id` INT(11) NOT NULL DEFAULT 0");

        // Clients

        mysqli_query($mysqli, "UPDATE `clients` SET `primary_location` = 0 WHERE `primary_location` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `clients` CHANGE `primary_location` `primary_location` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `clients` SET `primary_contact` = 0 WHERE `primary_contact` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `clients` CHANGE `primary_contact` `primary_contact` INT(11) NOT NULL DEFAULT 0");

        // Contacts

        mysqli_query($mysqli, "UPDATE `contacts` SET `contact_location_id` = 0 WHERE `contact_location_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `contacts` CHANGE `contact_location_id` `contact_location_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `contacts` CHANGE `contact_client_id` `contact_client_id` INT(11) NOT NULL DEFAULT 0");

        // Documents

        mysqli_query($mysqli, "ALTER TABLE `documents` CHANGE `document_template` `document_template` TINYINT(1) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `documents` SET `document_folder_id` = 0 WHERE `document_folder_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `documents` CHANGE `document_folder_id` `document_folder_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `documents` CHANGE `document_client_id` `document_client_id` INT(11) NOT NULL DEFAULT 0");

        // Domains

        mysqli_query($mysqli, "UPDATE `domains` SET `domain_registrar` = 0 WHERE `domain_registrar` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `domains` CHANGE `domain_registrar` `domain_registrar` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `domains` SET `domain_webhost` = 0 WHERE `domain_webhost` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `domains` CHANGE `domain_webhost` `domain_webhost` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `domains` CHANGE `domain_client_id` `domain_client_id` INT(11) NOT NULL DEFAULT 0");

        // Events

        mysqli_query($mysqli, "UPDATE `events` SET `event_client_id` = 0 WHERE `event_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `events` CHANGE `event_client_id` `event_client_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `events` SET `event_location_id` = 0 WHERE `event_location_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `events` CHANGE `event_location_id` `event_location_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `events` CHANGE `event_calendar_id` `event_calendar_id` INT(11) NOT NULL DEFAULT 0");

        // Expenses

        mysqli_query($mysqli, "UPDATE `expenses` SET `expense_vendor_id` = 0 WHERE `expense_vendor_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `expenses` CHANGE `expense_vendor_id` `expense_vendor_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `expenses` SET `expense_client_id` = 0 WHERE `expense_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `expenses` CHANGE `expense_client_id` `expense_client_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `expenses` SET `expense_category_id` = 0 WHERE `expense_category_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `expenses` CHANGE `expense_category_id` `expense_category_id` INT(11) NOT NULL DEFAULT 0");

        // Files

        mysqli_query($mysqli, "ALTER TABLE `files` CHANGE `file_client_id` `file_client_id` INT(11) NOT NULL DEFAULT 0");

        // Folders

        mysqli_query($mysqli, "UPDATE `folders` SET `parent_folder` = 0 WHERE `parent_folder` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `folders` CHANGE `parent_folder` `parent_folder` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `folders` CHANGE `folder_client_id` `folder_client_id` INT(11) NOT NULL DEFAULT 0");

        // History

        mysqli_query($mysqli, "UPDATE `history` SET `history_invoice_id` = 0 WHERE `history_invoice_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `history` CHANGE `history_invoice_id` `history_invoice_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `history` SET `history_recurring_id` = 0 WHERE `history_recurring_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `history` CHANGE `history_recurring_id` `history_recurring_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `history` SET `history_quote_id` = 0 WHERE `history_quote_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `history` CHANGE `history_quote_id` `history_quote_id` INT(11) NOT NULL DEFAULT 0");

        // Invoices

        mysqli_query($mysqli, "UPDATE `invoices` SET `invoice_amount` = 0.00 WHERE `invoice_amount` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `invoices` CHANGE `invoice_amount` `invoice_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        // Invoice Items

        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_quantity` `item_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_price` `item_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_subtotal` `item_subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        mysqli_query($mysqli, "UPDATE `invoice_items` SET `item_tax` = 0.00 WHERE `item_tax` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_tax` `item_tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_total` `item_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        mysqli_query($mysqli, "UPDATE `invoice_items` SET `item_tax_id` = 0 WHERE `item_tax_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_tax_id` `item_tax_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `invoice_items` SET `item_quote_id` = 0 WHERE `item_quote_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_quote_id` `item_quote_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `invoice_items` SET `item_recurring_id` = 0 WHERE `item_recurring_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_recurring_id` `item_recurring_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `invoice_items` SET `item_invoice_id` = 0 WHERE `item_invoice_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `invoice_items` CHANGE `item_invoice_id` `item_invoice_id` INT(11) NOT NULL DEFAULT 0");

        // Locations

        mysqli_query($mysqli, "UPDATE `locations` SET `location_contact_id` = 0 WHERE `location_contact_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `locations` CHANGE `location_contact_id` `location_contact_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `locations` SET `location_client_id` = 0 WHERE `location_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `locations` CHANGE `location_client_id` `location_client_id` INT(11) NOT NULL DEFAULT 0");

        // Logins

        mysqli_query($mysqli, "UPDATE `logins` SET `login_vendor_id` = 0 WHERE `login_vendor_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `logins` CHANGE `login_vendor_id` `login_vendor_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `logins` SET `login_asset_id` = 0 WHERE `login_asset_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `logins` CHANGE `login_asset_id` `login_asset_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `logins` SET `login_software_id` = 0 WHERE `login_software_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `logins` CHANGE `login_software_id` `login_software_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `logins` SET `login_client_id` = 0 WHERE `login_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `logins` CHANGE `login_client_id` `login_client_id` INT(11) NOT NULL DEFAULT 0");

        // Logs

        mysqli_query($mysqli, "UPDATE `logs` SET `log_client_id` = 0 WHERE `log_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `logs` CHANGE `log_client_id` `log_client_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `logs` DROP `log_invoice_id`");
        mysqli_query($mysqli, "ALTER TABLE `logs` DROP `log_quote_id`");
        mysqli_query($mysqli, "ALTER TABLE `logs` DROP `log_recurring_id`");
        mysqli_query($mysqli, "ALTER TABLE `logs` DROP `log_entity_id`");

        mysqli_query($mysqli, "UPDATE `logs` SET `log_user_id` = 0 WHERE `log_user_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `logs` CHANGE `log_user_id` `log_user_id` INT(11) NOT NULL DEFAULT 0");

        // Networks

        mysqli_query($mysqli, "UPDATE `networks` SET `network_location_id` = 0 WHERE `network_location_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `networks` CHANGE `network_location_id` `network_location_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `networks` CHANGE `network_client_id` `network_client_id` INT(11) NOT NULL DEFAULT 0");

        // Notifications

        mysqli_query($mysqli, "UPDATE `notifications` SET `notification_client_id` = 0 WHERE `notification_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `notifications` CHANGE `notification_client_id` `notification_client_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "ALTER TABLE `notifications` CHANGE `notification_user_id` `notification_user_id` INT(11) NOT NULL DEFAULT 0");

        // Payments

        mysqli_query($mysqli, "UPDATE `payments` SET `payment_invoice_id` = 0 WHERE `payment_invoice_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `payments` CHANGE `payment_invoice_id` `payment_invoice_id` INT(11) NOT NULL DEFAULT 0");

        // Products

        mysqli_query($mysqli, "UPDATE `products` SET `product_tax_id` = 0 WHERE `product_tax_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `products` CHANGE `product_tax_id` `product_tax_id` INT(11) NOT NULL DEFAULT 0");

        // Quotes

        mysqli_query($mysqli, "UPDATE `quotes` SET `quote_amount` = 0.00 WHERE `quote_amount` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `quotes` CHANGE `quote_amount` `quote_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        // Recurring

        mysqli_query($mysqli, "UPDATE `recurring` SET `recurring_amount` = 0.00 WHERE `recurring_amount` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `recurring` CHANGE `recurring_amount` `recurring_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        // Revenues

        mysqli_query($mysqli, "UPDATE `revenues` SET `revenue_amount` = 0.00 WHERE `revenue_amount` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `revenues` CHANGE `revenue_amount` `revenue_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00");

        mysqli_query($mysqli, "UPDATE `revenues` SET `revenue_category_id` = 0 WHERE `revenue_category_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `revenues` CHANGE `revenue_category_id` `revenue_category_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `revenues` SET `revenue_client_id` = 0 WHERE `revenue_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `revenues` CHANGE `revenue_client_id` `revenue_client_id` INT(11) NOT NULL DEFAULT 0");

        // Scheduled Tickets

        mysqli_query($mysqli, "ALTER TABLE `scheduled_tickets` CHANGE `scheduled_ticket_created_by` `scheduled_ticket_created_by` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `scheduled_tickets` SET `scheduled_ticket_client_id` = 0 WHERE `scheduled_ticket_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `scheduled_tickets` CHANGE `scheduled_ticket_client_id` `scheduled_ticket_client_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `scheduled_tickets` SET `scheduled_ticket_contact_id` = 0 WHERE `scheduled_ticket_contact_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `scheduled_tickets` CHANGE `scheduled_ticket_contact_id` `scheduled_ticket_contact_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `scheduled_tickets` SET `scheduled_ticket_asset_id` = 0 WHERE `scheduled_ticket_asset_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `scheduled_tickets` CHANGE `scheduled_ticket_asset_id` `scheduled_ticket_asset_id` INT(11) NOT NULL DEFAULT 0");

        // Settings

        mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_ticket_email_parse` `config_ticket_email_parse` TINYINT(1) NOT NULL DEFAULT 0");
        mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_ticket_client_general_notifications` `config_ticket_client_general_notifications` TINYINT(1) NOT NULL DEFAULT 1");
        mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_enable_cron` `config_enable_cron` TINYINT(1) NOT NULL DEFAULT 0");
        mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_recurring_auto_send_invoice` `config_recurring_auto_send_invoice` TINYINT(1) NOT NULL DEFAULT 1");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_enable_alert_domain_expire` = 1 WHERE `config_enable_alert_domain_expire` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_enable_alert_domain_expire` `config_enable_alert_domain_expire` TINYINT(1) NOT NULL DEFAULT 1");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_send_invoice_reminders` = 1 WHERE `config_send_invoice_reminders` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_send_invoice_reminders` `config_send_invoice_reminders` TINYINT(1) NOT NULL DEFAULT 1");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_stripe_enable` = 0 WHERE `config_stripe_enable` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_stripe_enable` `config_stripe_enable` TINYINT(1) NOT NULL DEFAULT 0");

        // Software

        mysqli_query($mysqli, "UPDATE `software` SET `software_template` = 0 WHERE `software_template` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `software` CHANGE `software_template` `software_template` TINYINT(1) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `software` SET `software_login_id` = 0 WHERE `software_login_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `software` CHANGE `software_login_id` `software_login_id` INT(11) NOT NULL DEFAULT 0");

        // Tags

        mysqli_query($mysqli, "ALTER TABLE `tags` ADD `tag_archived_at` DATETIME NULL DEFAULT NULL AFTER `tag_updated_at`");

        // Tickets

        mysqli_query($mysqli, "UPDATE `tickets` SET `ticket_closed_by` = 0 WHERE `ticket_closed_by` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `tickets` CHANGE `ticket_closed_by` `ticket_closed_by` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `tickets` SET `ticket_vendor_id` = 0 WHERE `ticket_vendor_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `tickets` CHANGE `ticket_vendor_id` `ticket_vendor_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `tickets` SET `ticket_client_id` = 0 WHERE `ticket_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `tickets` CHANGE `ticket_client_id` `ticket_client_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `tickets` SET `ticket_contact_id` = 0 WHERE `ticket_contact_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `tickets` CHANGE `ticket_contact_id` `ticket_contact_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `tickets` SET `ticket_location_id` = 0 WHERE `ticket_location_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `tickets` CHANGE `ticket_location_id` `ticket_location_id` INT(11) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `tickets` SET `ticket_asset_id` = 0 WHERE `ticket_asset_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `tickets` CHANGE `ticket_asset_id` `ticket_asset_id` INT(11) NOT NULL DEFAULT 0");

        //Trips

        mysqli_query($mysqli, "UPDATE `trips` SET `trip_client_id` = 0 WHERE `trip_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `trips` CHANGE `trip_client_id` `trip_client_id` INT(11) NOT NULL DEFAULT 0");

        // Users

        mysqli_query($mysqli, "ALTER TABLE `users` CHANGE `user_status` `user_status` TINYINT(1) NOT NULL DEFAULT 1");

        // Vendors

        mysqli_query($mysqli, "ALTER TABLE `vendors` CHANGE `vendor_template` `vendor_template` TINYINT(1) NOT NULL DEFAULT 0");

        mysqli_query($mysqli, "UPDATE `vendors` SET `vendor_client_id` = 0 WHERE `vendor_client_id` IS NULL");
        mysqli_query($mysqli, "ALTER TABLE `vendors` CHANGE `vendor_client_id` `vendor_client_id` INT(11) NOT NULL DEFAULT 0");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.2'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.2') {
        mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_billing` TINYINT(1) DEFAULT 0 AFTER `contact_important`");
        mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_technical` TINYINT(1) DEFAULT 0 AFTER `contact_billing`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.3'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.3') {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_telemetry` TINYINT(1) DEFAULT 0 AFTER `config_theme`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.4'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.4') {
        // Insert queries here required to update to DB version 0.3.5

        //Get & upgrade user login encryption
        $sql_logins = mysqli_query($mysqli, "SELECT login_id, login_username FROM logins WHERE login_username IS NOT NULL");
        foreach ($sql_logins as $row) {
            $login_id = $row['login_id'];
            $login_username = $row['login_username'];
            $login_encrypted_username = encryptLoginEntry($row['login_username']);
            mysqli_query($mysqli, "UPDATE logins SET login_username = '$login_encrypted_username' WHERE login_id = '$login_id'");
        }

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.5'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.5') {
        $installation_id = randomString(32);

        // Update config.php var with new version var for use with docker
        file_put_contents("config.php", "\n\$installation_id = '$installation_id';" . PHP_EOL, FILE_APPEND);


        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.6'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.6') {
        // Insert queries here required to update to DB version 0.3.7
        mysqli_query($mysqli, "ALTER TABLE `shared_items` ADD `item_encrypted_username` VARCHAR(255) NULL DEFAULT NULL AFTER `item_related_id`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.7'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.7') {

        mysqli_query($mysqli, "ALTER TABLE `logins` ADD `login_important` TINYINT(1) NOT NULL DEFAULT 0 AFTER `login_note`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.8'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.8') {
        mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_accessed_at` DATETIME NULL DEFAULT NULL AFTER `contact_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `locations` ADD `location_accessed_at` DATETIME NULL DEFAULT NULL AFTER `location_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `assets` ADD `asset_accessed_at` DATETIME NULL DEFAULT NULL AFTER `asset_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `software` ADD `software_accessed_at` DATETIME NULL DEFAULT NULL AFTER `software_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `logins` ADD `login_accessed_at` DATETIME NULL DEFAULT NULL AFTER `login_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `networks` ADD `network_accessed_at` DATETIME NULL DEFAULT NULL AFTER `network_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `certificates` ADD `certificate_accessed_at` DATETIME NULL DEFAULT NULL AFTER `certificate_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `domains` ADD `domain_accessed_at` DATETIME NULL DEFAULT NULL AFTER `domain_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `services` ADD `service_accessed_at` DATETIME NULL DEFAULT NULL AFTER `service_updated_at`");
        mysqli_query($mysqli, "ALTER TABLE `vendors` ADD `vendor_accessed_at` DATETIME NULL DEFAULT NULL AFTER `vendor_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `files` ADD `file_accessed_at` DATETIME NULL DEFAULT NULL AFTER `file_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `documents` ADD `document_accessed_at` DATETIME NULL DEFAULT NULL AFTER `document_archived_at`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.3.9'");
    }

    if (CURRENT_DATABASE_VERSION == '0.3.9') {

        mysqli_query($mysqli, "ALTER TABLE `vendors` ADD `vendor_template_id` INT(11) NOT NULL DEFAULT 0 AFTER `vendor_client_id`");
        mysqli_query($mysqli, "ALTER TABLE `software` ADD `software_template_id` INT(11) NOT NULL DEFAULT 0 AFTER `software_client_id`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.0'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.0') {
        mysqli_query($mysqli, "ALTER TABLE `logs` ADD `log_entity_id` INT NOT NULL DEFAULT '0' AFTER `log_user_id`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.1'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.1') {
        mysqli_query($mysqli, "ALTER TABLE settings ADD `config_stripe_account` TINYINT(1) NOT NULL DEFAULT '0' AFTER config_stripe_secret");
        //Insert queries here required to update to DB version 0.4.2

        //Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.2'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.2') {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_timezone` VARCHAR(200) NOT NULL DEFAULT 'America/New_York' AFTER `config_telemetry`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.3'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.3') {
        // Insert queries here required to update to DB version 0.4.4
        mysqli_query($mysqli, "ALTER TABLE `client_tags` CHANGE `client_id` `client_tags_client_id` INT NOT NULL");
        mysqli_query($mysqli, "ALTER TABLE `client_tags` CHANGE `tag_id` `client_tags_tag_id` INT NOT NULL");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.4'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.4') {
        // Insert queries here required to update to DB version 0.4.5
        mysqli_query($mysqli, "ALTER TABLE `client_tags` CHANGE `client_tags_client_id` `client_tag_client_id` INT NOT NULL");
        mysqli_query($mysqli, "ALTER TABLE `client_tags` CHANGE `client_tags_tag_id` `client_tag_tag_id` INT NOT NULL");
        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.5'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.5') {
        // Insert queries here required to update to DB version 0.4.6
        mysqli_query($mysqli, "ALTER TABLE `contacts` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `locations` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `assets` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `software` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `logins` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `networks` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `certificates` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `domains` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `tickets` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `ticket_replies` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `scheduled_tickets` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `services` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `vendors` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `calendars` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `events` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `files` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `documents` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `folders` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `invoices` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `recurring` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `quotes` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `history` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `invoice_items` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `payments` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `trips` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `clients` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `expenses` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `transfers` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `revenues` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `api_keys` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `taxes` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `categories` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `tags` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `accounts` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `interfaces` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `records` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `logs` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `notifications` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `products` DROP `company_id`");
        mysqli_query($mysqli, "ALTER TABLE `companies` DROP `company_archived_at`");
        mysqli_query($mysqli, "ALTER TABLE `user_settings` DROP `user_default_company`");
        mysqli_query($mysqli, "DROP TABLE `user_companies`");
        mysqli_query($mysqli, "DROP TABLE `user_keys`"); //Unused Table

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.6'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.6') {

        mysqli_query($mysqli, "ALTER TABLE `notifications` ADD `notification_entity_id` INT(11) DEFAULT 0 AFTER `notification_user_id`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.7'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.7') {

        mysqli_query($mysqli, "ALTER TABLE `clients` ADD `client_rate` DECIMAL(15,2) NULL DEFAULT NULL AFTER `client_referral`");
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.8'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.8') {
        mysqli_query($mysqli, "ALTER TABLE `tickets` ADD `ticket_source` VARCHAR(255) NULL DEFAULT NULL AFTER `ticket_number`");

        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.4.9'");
    }

    if (CURRENT_DATABASE_VERSION == '0.4.9') {
        // Insert queries here required to update to DB version 0.5.0
        mysqli_query($mysqli, "ALTER TABLE `clients` ADD `client_tax_id_number` VARCHAR(255) NULL DEFAULT NULL AFTER `client_net_terms`");
        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.5.0'");
    }

    if (CURRENT_DATABASE_VERSION == '0.5.0') {
        // Insert queries here required to update to DB version 0.5.1
        mysqli_query($mysqli, "CREATE TABLE `ticket_attachments` (
          `ticket_attachment_id` int(11) NOT NULL AUTO_INCREMENT,
          `ticket_attachment_name` varchar(255) NOT NULL,
          `ticket_attachment_reference_name` varchar(255) NOT NULL,
          `ticket_attachment_created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `ticket_attachment_ticket_id` int(11) NOT NULL,
          `ticket_attachment_reply_id` int(11) DEFAULT NULL,
          PRIMARY KEY (`ticket_attachment_id`)
        )");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.5.1'");
    }

    if (CURRENT_DATABASE_VERSION == '0.5.1') {
        //Insert queries here required to update to DB version 0.5.2
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_autoclose` TINYINT(1) NOT NULL DEFAULT 0 AFTER `config_ticket_client_general_notifications`");

        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_cron_key` VARCHAR(255) NULL DEFAULT NULL AFTER `config_enable_cron`");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.5.2'");
    }

    if (CURRENT_DATABASE_VERSION == '0.5.2') {
        //Insert queries here required to update to DB version 0.5.3
        //Custom Fields and Values

        mysqli_query($mysqli, "CREATE TABLE `custom_fields` (
            `custom_field_id` int(11) NOT NULL AUTO_INCREMENT,
            `custom_field_table` varchar(255) NOT NULL,
            `custom_field_label` varchar(255) NOT NULL,
            `custom_field_type` varchar(255) NOT NULL DEFAULT 'TEXT',
            `custom_field_location` int(11) NOT NULL DEFAULT 0,
            `custom_field_order` int(11) NOT NULL DEFAULT 999,
            PRIMARY KEY (`custom_field_id`),
            UNIQUE KEY (`custom_field_table`),
            UNIQUE KEY (`custom_field_label`),
            UNIQUE KEY (`custom_field_type`)
        )");

        mysqli_query($mysqli, "CREATE TABLE `custom_values` (
            `custom_value_id` int(11) NOT NULL AUTO_INCREMENT,
            `custom_value_value` text NOT NULL,
            `custom_value_field` int(11) NOT NULL,
            PRIMARY KEY (`custom_value_id`)
        )");

        mysqli_query($mysqli, "CREATE TABLE `asset_custom` (
            `asset_custom_id` int(11) NOT NULL AUTO_INCREMENT,
            `asset_custom_field_value` int(11) NOT NULL,
            `asset_custom_field_id` int(11) NOT NULL,
            `asset_custom_asset_id` int(11) NOT NULL,
            PRIMARY KEY (`asset_custom_id`),
            UNIQUE KEY (`asset_custom_field_id`)
        )");

        // Then, update the database to the next sequential version
        mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.5.3'");
    }

    //if (CURRENT_DATABASE_VERSION == '0.5.3') {
        //Insert queries here required to update to DB version 0.5.4

        // Then, update the database to the next sequential version
        //mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.5.4'");
    //}

} else {
    // Up-to-date
}
