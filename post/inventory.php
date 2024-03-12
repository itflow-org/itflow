<?php

/*
 * ITFlow - GET/POST request handler for AI Functions
 */


if (isset($_POST['move_inventory'])) {

    validateTechRole();
    
    require_once("inventory_model.php");

    // Move Inventory

    $sql = mysqli_query(
        $mysqli,
        "UPDATE inventory SET inventory_location_id = $inventory_new_location_id WHERE inventory_location_id = $inventory_location_id AND inventory_product_id = $inventory_product_id LIMIT $inventory_quantity"
    );
    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Inventory', log_action = 'Move', log_description = '$session_name moved $inventory_quantity items of product $inventory_product_id from location $inventory_location_id to location $inventory_new_location_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");


    $_SESSION['alert_message'] .= "Inventory item $inventory_product_id moved successfully, $inventory_quantity items moved to new location $inventory_new_location_id from $inventory_location_id <br>";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_inventory_locations'])) {

    validateAdminRole();

    require_once("inventory_model.php");

    // Add Inventory Location
    $sql = mysqli_query(
        $mysqli,
        "INSERT INTO inventory_locations SET inventory_locations_name = '$inventory_location_name', inventory_locations_description = '$inventory_location_description', inventory_locations_address = '$inventory_location_address', inventory_locations_city = '$inventory_location_city', inventory_locations_state = '$inventory_location_state', inventory_locations_zip = '$inventory_location_zip', inventory_locations_country = '$inventory_location_country', inventory_locations_user_id = $inventory_location_user_id"
    );

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Inventory', log_action = 'Create', log_description = '$session_name created inventory location $inventory_location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION["alert_message"] = "Inventory location $inventory_location_name added successfully";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_inventory_locations'])) {

    validateAdminRole();

    require_once("inventory_model.php");

    // Edit Inventory Location
    $sql = mysqli_query(
        $mysqli,
        "UPDATE inventory_locations SET inventory_locations_name = '$inventory_location_name', inventory_locations_description = '$inventory_location_description', inventory_locations_address = '$inventory_location_address', inventory_locations_city = '$inventory_location_city', inventory_locations_state = '$inventory_location_state', inventory_locations_zip = '$inventory_location_zip', inventory_locations_country = '$inventory_location_country', inventory_locations_user_id = $inventory_location_user_id WHERE inventory_locations_id = $inventory_location_id"
    );

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Inventory', log_action = 'Edit', log_description = '$session_name edited inventory location $inventory_location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION["alert_message"] = "Inventory location $inventory_location_name edited successfully";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_inventory_location'])) {

    validateAdminRole();

    $inventory_location_id = intval($_GET['archive_inventory_location']);

    // Archive Inventory Location
    $sql = mysqli_query(
        $mysqli,
        "UPDATE inventory_locations SET inventory_locations_archived_at = NOW(), inventory_locations_user_id = 0 WHERE inventory_locations_id = $inventory_location_id"
    );

    // Move all inventory to default location
    $sql = mysqli_query(
        $mysqli,
        "UPDATE inventory SET inventory_location_id = 1 WHERE inventory_location_id = $inventory_location_id"
    );
    $num_rows = mysqli_affected_rows($mysqli);

    if ($num_rows > 0) {
        $items_moved_message = ", $num_rows items moved to default location";
    } else {
        $items_moved_message = ", No items were in archived location";
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Inventory', log_action = 'Archive', log_description = '$session_name archived inventory location $inventory_location_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION["alert_message"] = "Inventory location $inventory_location_id archived successfully$items_moved_message";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}