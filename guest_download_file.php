<?php
include("config.php");
include("functions.php");
if(isset($_GET['id']) AND isset($_GET['key'])){
    $item_id = intval($_GET['id']);
    $item_key = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['key'])));

    $sql = mysqli_query($mysqli, "SELECT * FROM shared_items WHERE item_id = '$item_id' AND item_key = '$item_key' AND item_expire_at > NOW() LIMIT 1");
    $row = mysqli_fetch_array($sql);

    // Check result
    if(mysqli_num_rows($sql) !== 1 OR !$row){
        exit("No file.");
    }

    // Check it is a file
    if($row['item_type'] !== "File"){
        exit("Bad item type.");
    }

    // Check item share is active & hasn't been viewed too many times
    if($row['item_active'] !== "1" OR $row['item_views'] >= $row['item_view_limit']){
        exit("Item cannot be viewed at this time.");
    }

    $item_related_id = $row['item_related_id'];
    $client_id = $row['item_client_id'];

    if(empty($row['item_views'])){
        $item_views = 0;
    }
    else {
        $item_views = intval($row['item_views']);
    }

    $file_sql = mysqli_query($mysqli, "SELECT * FROM files WHERE file_id = '$item_related_id' AND file_client_id = '$client_id' LIMIT 1");
    $file_row = mysqli_fetch_array($file_sql);

    if(mysqli_num_rows($file_sql) !== 1 OR !$file_row){
        exit("No file.");
    }

    $file_name = $file_row['file_name'];
    $file_ext = $file_row['file_ext'];
    $file_reference_name = $file_row['file_reference_name'];
    $client_id = $file_row['file_client_id'];
    $company_id = $file_row['company_id'];
    $file_path = "uploads/clients/$company_id/$client_id/$file_reference_name";

    // Display file as download
    $mime_type = mime_content_type($file_path);
    header('Content-type: '.$mime_type);
    header('Content-Disposition: attachment; filename=download.' .$file_ext);
    readfile($file_path);


    // Update file view count & logging
    $new_item_views = $item_views + 1;
    mysqli_query($mysqli, "UPDATE shared_items SET item_views = '$new_item_views' WHERE item_id = '$item_id'");




}