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
    // mysqli_query($mysqli, "ALTER TABLE .....");


    // Then, update the database to the next sequential version
    //mysqli_query($mysqli, "UPDATE settings SET config_current_database_version = '0.0.2' WHERE company_id = '1'");

  }


  if(CURRENT_DATABASE_VERSION == '0.0.2'){
    // Insert queries here required to update to DB version 0.0.3
    // mysqli_query($mysqli, "ALTER TABLE .....");


    // Then, update the database to the next sequential version
    //mysqli_query($mysqli, "UPDATE settings SET config_current_database_version = '0.0.3' WHERE company_id = '1'");

  }

  // etc

}
else{
  // Up-to-date
}