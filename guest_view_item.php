<?php
header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once("guest_header.php");

//Initialize the HTML Purifier to prevent XSS
require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

?>

    <br>
    <h1> <?php echo htmlentities($config_app_name); ?> Guest sharing </h1>
    <hr>

<?php
if (!isset($_GET['id']) || !isset($_GET['key'])) {
    echo "<div class='alert alert-danger'>Incorrect URL.</div>";
    include("guest_footer.php");
    exit();
}

$item_id = intval($_GET['id']);
$item_key = sanitizeInput($_GET['key']);

$sql = mysqli_query($mysqli, "SELECT * FROM shared_items WHERE item_id = $item_id AND item_key = '$item_key' AND item_expire_at > NOW() LIMIT 1");
$row = mysqli_fetch_array($sql);

// Check we got a result
if (mysqli_num_rows($sql) !== 1 || !$row) {
    echo "<div class='alert alert-danger' >No item to view. Check with the person that sent you this link to ensure it is correct and has not expired.</div>";
    include("guest_footer.php");
    exit();
}

// Check item share is active & hasn't been viewed too many times
if ($row['item_active'] !== "1" || $row['item_views'] >= $row['item_view_limit']) {
    echo "<div class='alert alert-danger'>Item cannot be viewed at this time. Check with the person that sent you this link to ensure it is correct and has not expired.</div>";
    include("guest_footer.php");
    exit();
}

// If we got here, we have valid information

echo "<div class='alert alert-warning'>You may only be able to view this information for a limited time! Be sure to copy/download what you need.</div>";

$item_type = htmlentities($row['item_type']);
$item_related_id = intval($row['item_related_id']);
$item_encrypted_credential = htmlentities($row['item_encrypted_credential']);
$item_note = htmlentities($row['item_note']);
$item_views = intval($row['item_views']);
$item_created = htmlentities($row['item_created_at']);
$item_expire = htmlentities($row['item_expire_at']);
$client_id = intval($row['item_client_id']);

if ($item_type == "Document") {
    $doc_sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = $item_related_id AND document_client_id = $client_id LIMIT 1");
    $doc_row = mysqli_fetch_array($doc_sql);

    if (mysqli_num_rows($doc_sql) !== 1 || !$doc_row) {
        echo "<div class='alert alert-danger'>Error retrieving document to view.</div>";
        require_once("guest_footer.php");
        exit();
    }

    $doc_title = htmlentities($doc_row['document_name']);
    $doc_title_escaped = sanitizeInput($doc_row['document_name']);
    $doc_content = $purifier->purify($row['document_content']);

    echo "<h3>A document has been shared with you</h3>";
    if (!empty($item_note)) {
        echo "<p class='lead'>Note: <i>$item_note</i></p>";
    }
    echo "<br>";
    echo "<h2>$doc_title</h2>";
    echo $doc_content;

    // Update document view count
    $new_item_views = $item_views + 1;
    mysqli_query($mysqli, "UPDATE shared_items SET item_views = $new_item_views WHERE item_id = $item_id");

    // Logging
    $name = mysqli_real_escape_string($mysqli, $doc_title);
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Sharing', log_action = 'View', log_description = 'Viewed shared $item_type $doc_title_escaped via link', log_client_id = $client_id, log_ip = '$ip', log_user_agent = '$user_agent'");

} elseif ($item_type == "File") {
    $file_sql = mysqli_query($mysqli, "SELECT * FROM files WHERE file_id = $item_related_id AND file_client_id = $client_id LIMIT 1");
    $file_row = mysqli_fetch_array($file_sql);

    if (mysqli_num_rows($file_sql) !== 1 || !$file_row) {
        echo "<div class='alert alert-danger'>Error retrieving file.</div>";
        include("guest_footer.php");
        exit();
    }

    $file_name = htmlentities($file_row['file_name']);

    echo "<h3>A file has been shared with you</h3>";
    if (!empty($item_note)) {
        echo "<p class='lead'>Note: <i>$item_note</i></p>";
    }
    echo "<a href='guest_download_file.php?id=$item_id&key=$item_key' download='$file_name'>Download $file_name</a>";


} elseif ($item_type == "Login") {
    $encryption_key = $_GET['ek'];

    $login_sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_id = $item_related_id AND login_client_id = $client_id LIMIT 1");
    $login_row = mysqli_fetch_array($login_sql);
    if (mysqli_num_rows($login_sql) !== 1 || !$login_row) {
        echo "<div class='alert alert-danger'>Error retrieving login.</div>";
        include("guest_footer.php");
        exit();
    }

    $login_name = htmlentities($login_row['login_name']);
    $login_uri = htmlentities($login_row['login_uri']);

    $username_iv = substr($row['item_encrypted_username'], 0, 16);
    $username_ciphertext = substr($row['item_encrypted_username'], 16);
    $login_username = htmlentities(openssl_decrypt($username_ciphertext, 'aes-128-cbc', $encryption_key, 0, $username_iv));

    $password_iv = substr($row['item_encrypted_credential'], 0, 16);
    $password_ciphertext = substr($row['item_encrypted_credential'], 16);
    $login_password = htmlentities(openssl_decrypt($password_ciphertext, 'aes-128-cbc', $encryption_key, 0, $password_iv));

    $login_otp = htmlentities($login_row['login_otp_secret']);
    $login_notes = htmlentities($login_row['login_note']);

    echo "<h3>A login entry has been shared with you</h3>";
    if (!empty($item_note)) {
        echo "<p class='lead'>Note: <i>$item_note</i></p>";
    }
    echo "<br>";

    echo "<p>Name: $login_name</p>";
    echo "<p>URL: $login_uri</p>";
    echo "<p>Username: $login_username</p>";
    echo "<p>Password: $login_password</p>";
    echo "<p>OTP: $login_otp</p>";
    echo "<p>Notes: $login_notes</p>";

    // Update login view count
    $new_item_views = $item_views + 1;
    mysqli_query($mysqli, "UPDATE shared_items SET item_views = $new_item_views WHERE item_id = $item_id");

    // Logging
    $name = sanitizeInput($login_row['login_name']);
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Sharing', log_action = 'View', log_description = 'Viewed shared $item_type $name via link', log_client_id = $client_id, log_ip = '$ip', log_user_agent = '$ua'");

}

echo "<br><hr>";
echo $config_app_name;
require_once("guest_footer.php");
