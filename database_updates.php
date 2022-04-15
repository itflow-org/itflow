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


    // Then, update the database to the next sequential version
    //mysqli_query($mysqli, "UPDATE settings SET config_current_database_version = '0.0.3'");

  }

  // etc

}
else{
  // Up-to-date
}