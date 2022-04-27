<?php
/*
 * ITFlow
 * This file defines the SQL queries required to update the database to the "latest" database version
 * It is used in conjunction with database_version.php
 */

// Check if our database versions are defined
// If undefined, the file is probably being accessed directly rather than called via post.php?update_db
if(!defined("LATEST_DATABASE_VERSION") || !defined("CURRENT_DATABASE_VERSION") || !isset($mysqli)){
  echo "Cannot access this file directly.";
  exit();
}

// Check if we need an update
if(LATEST_DATABASE_VERSION > CURRENT_DATABASE_VERSION){

  // We need updates!

  if(CURRENT_DATABASE_VERSION == '0.0.1'){
    // Insert queries here required to update to DB version 0.0.2

    mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_module_enable_itdoc` TINYINT(1) DEFAULT 1 AFTER `config_backup_path`");
    mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_module_enable_ticketing` TINYINT(1) DEFAULT 1 AFTER `config_module_enable_itdoc`");
    mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_module_enable_accounting` TINYINT(1) DEFAULT 1 AFTER `config_module_enable_ticketing`");

    // Update the database to the next sequential version
    mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.2'");
  }

  if(CURRENT_DATABASE_VERSION == '0.0.2'){
    // Insert queries here required to update to DB version 0.0.3

    // Add document content raw column & index
    mysqli_query($mysqli, "ALTER TABLE `documents` ADD `document_content_raw` LONGTEXT NOT NULL AFTER `document_content`, ADD FULLTEXT `document_content_raw` (`document_content_raw`)");

    // Populate content raw column with existing document data
    $documents_sql = mysqli_query($mysqli, "SELECT * FROM `documents`");
    while($row = mysqli_fetch_array($documents_sql)){
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

  if(CURRENT_DATABASE_VERSION == '0.0.3'){
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

  if(CURRENT_DATABASE_VERSION == '0.0.4'){
    // Queries here required to update to DB version 0.0.5

    mysqli_query($mysqli, "ALTER TABLE `assets` DROP `asset_meshcentral_id`;");
    mysqli_query($mysqli, "ALTER TABLE `clients` DROP `client_meshcentral_group`;");
    mysqli_query($mysqli, "ALTER TABLE `settings` DROP `config_meshcentral_uri`, DROP `config_meshcentral_user`, DROP `config_meshcentral_secret`;");

    // Then, update the database to the next sequential version
    mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.5'");
  }

  if(CURRENT_DATABASE_VERSION == '0.0.5'){
    // Insert queries here required to update to DB version 0.0.6

    mysqli_query($mysqli, "UPDATE documents SET document_folder_id = 0");

    mysqli_query($mysqli, "DROP TABLE documents_tagged");
    mysqli_query($mysqli, "DROP TABLE document_tags");


    // Then, update the database to the next sequential version
    mysqli_query($mysqli, "UPDATE settings SET config_current_database_version = '0.0.6'");
  }

  if(CURRENT_DATABASE_VERSION == '0.0.6'){
    // Insert queries here required to update to DB version 0.0.7

    // ALTER queries.....

    // Then, update the database to the next sequential version
    // mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '0.0.6'");
  }

  // etc

}
else{
  // Up-to-date
}