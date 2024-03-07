<?php

/*
 * ITFlow - GET/POST request handler for AI Functions
 */


if (isset($_POST['move_inventory'])) {
    $inventory_location_id = intval($_POST['inventory_location_id']);
    $inventory_new_location_id = intval($_POST['inventory_new_location_id']);
    $inventory_product_id = intval($_POST['inventory_product_id']);
    $inventory_quantity = intval($_POST['inventory_quantity']);
    
    // Move Inventory

    $sql = mysqli_query(
        $mysqli,
        "UPDATE inventory SET inventory_location_id = $inventory_new_location_id WHERE inventory_location_id = $inventory_location_id AND inventory_product_id = $inventory_product_id LIMIT $inventory_quantity"
    );

    $_SESSION['alert_message'] .= "Inventory item $inventory_product_id moved successfully, $inventory_quantity items moved to new location $inventory_new_location_id from $inventory_location_id <br>";
    header("Location: " . $_SERVER["HTTP_REFERER"]);


    
}