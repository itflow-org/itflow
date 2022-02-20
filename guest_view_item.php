<?php
header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');

include("guest_header.php"); ?>

<h1> <?php echo $config_app_name ?> Guest sharing </h1>
<hr>

<?php
if(!isset($_GET['id']) OR !isset($_GET['key'])){
    echo "<div class=\"alert alert-danger\" role=\"alert\">Incorrect URL.</div>";
    include("guest_footer.php");
    exit();
}

$item_id = intval($_GET['id']);
$item_key = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['key'])));

$sql = mysqli_query($mysqli, "SELECT * FROM shared_items WHERE item_id = '$item_id' AND item_key = '$item_key' AND item_expire_at > NOW() LIMIT 1");
$row = mysqli_fetch_array($sql);

// Check we got a result
if(mysqli_num_rows($sql) !== 1 OR !$row){
    echo "<div class=\"alert alert-danger\" role=\"alert\">No item to view. Check with the person that sent you this link to ensure it is correct and has not expired.</div>";
    include("guest_footer.php");
    exit();
}

// Check item share is active & hasn't been viewed too many times
if($row['item_active'] !== "1" OR $row['item_views'] >= $row['item_view_limit']){
    echo "<div class=\"alert alert-danger\" role=\"alert\">Item cannot be viewed at this time. Check with the person that sent you this link to ensure it is correct and has not expired.</div>";
    include("guest_footer.php");
    exit();
}

// If we got here, we have valid information

echo "<div class=\"alert alert-warning\" role=\"alert\">You may only be able to view this information for a limited time! Be sure to copy/download what you need.</div>";

$item_type = $row['item_type'];
$item_related_id = $row['item_related_id'];
$item_encrypted_credential = $row['item_encrypted_credential'];
$item_note = $row['item_note'];
$item_views = intval($row['item_views']);
$item_created = $row['item_created_at'];
$item_expire = $row['item_expire_at'];
$client_id = $row['item_client_id'];

if($item_type == "Document"){
    $doc_sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = '$item_related_id' AND document_client_id = '$client_id' LIMIT 1");
    $doc_row = mysqli_fetch_array($doc_sql);

    if(mysqli_num_rows($doc_sql) !== 1 OR !$doc_row){
        echo "<div class=\"alert alert-danger\" role=\"alert\">Error retrieving document to view.</div>";
        include("guest_footer.php");
        exit();
    }

    $doc_title = $doc_row['document_name'];
    $doc_content = $doc_row['document_content'];

    echo "<h3>$doc_title has been shared with you</h3>";
    if(!empty($item_note)){
        echo "<p class=\"lead\">$item_note</p>";
    }
    echo "<br>";
    echo $doc_content;

    // Update document view count
    $new_item_views = $item_views + 1;
    mysqli_query($mysqli, "UPDATE shared_items SET item_views = '$new_item_views' WHERE item_id = '$item_id'");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Sharing', log_action = 'View', log_description = 'Viewed shared $item_type via link - Item ID: $item_id', log_client_id = '$client_id', log_created_at = NOW(), log_ip = '$ip', log_user_agent = '$user_agent', company_id = '1'");

}
elseif($item_type == "File"){
    $file_sql = mysqli_query($mysqli, "SELECT * FROM files WHERE file_id = '$item_related_id' AND file_client_id = '$client_id' LIMIT 1");
    $file_row = mysqli_fetch_array($file_sql);

    if(mysqli_num_rows($file_sql) !== 1 OR !$file_row){
        echo "<div class=\"alert alert-danger\" role=\"alert\">Error retrieving file.</div>";
        include("guest_footer.php");
        exit();
    }

    $file_name = $file_row['file_name'];

    echo "<h3>$file_name has been shared with you</h3>";
    if(!empty($item_note)){
        echo "<p class=\"lead\">$item_note</p>";
    }
    echo "<a href=\"guest_download_file.php?id=$item_id&key=$item_key\" download=\"$file_name;\">Download</a>";


}
elseif($item_type == "Login"){
    $encryption_key = $_GET['ek'];

    $login_sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_id = '$item_related_id' AND login_client_id = '$client_id' LIMIT 1");
    $login_row = mysqli_fetch_array($login_sql);
    if(mysqli_num_rows($login_sql) !== 1 OR !$login_row){
        echo "<div class=\"alert alert-danger\" role=\"alert\">Error retrieving login.</div>";
        include("guest_footer.php");
        exit();
    }

    $login_name = $login_row['login_name'];
    $login_uri = $login_row['login_uri'];
    $login_username = $login_row['login_username'];
    $login_iv = substr($row['item_encrypted_credential'], 0, 16);
    $login_ciphertext = substr($row['item_encrypted_credential'], 16);
    $login_password = openssl_decrypt($login_ciphertext, 'aes-128-cbc', $encryption_key,0, $login_iv);
    $login_otp = $login_row['login_otp_secret'];
    $login_notes = $login_row['login_note'];

    echo "<h3>$login_name has been shared with you</h3>";
    if(!empty($item_note)){
        echo "<p class=\"lead\">$item_note</p>";
    }

    echo "<p>Name: $login_name</p>";
    echo "<p>URL: $login_uri</p>";
    echo "<p>Username: $login_username</p>";
    echo "<p>Password: $login_password</p>";
    echo "<p>OTP: $login_otp</p>";
    echo "<p>Notes: $login_notes</p>";

    // Update login view count
    $new_item_views = $item_views + 1;
    mysqli_query($mysqli, "UPDATE shared_items SET item_views = '$new_item_views' WHERE item_id = '$item_id'");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Sharing', log_action = 'View', log_description = 'Viewed shared $item_type via link - Item ID: $item_id', log_client_id = '$client_id', log_created_at = NOW(), log_ip = '$ip', log_user_agent = '$user_agent', company_id = '1'");

}

echo "<hr>";

include("guest_footer.php");