<?php
header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once "guest_header.php";


//Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
$row = mysqli_fetch_array($sql);

$company_name = nullable_htmlentities($row['company_name']);
$company_address = nullable_htmlentities($row['company_address']);
$company_city = nullable_htmlentities($row['company_city']);
$company_state = nullable_htmlentities($row['company_state']);
$company_zip = nullable_htmlentities($row['company_zip']);
$company_phone = formatPhoneNumber($row['company_phone']);
$company_email = nullable_htmlentities($row['company_email']);
$company_website = nullable_htmlentities($row['company_website']);
$company_logo = nullable_htmlentities($row['company_logo']);
$company_locale = nullable_htmlentities($row['company_locale']);
$config_invoice_footer = nullable_htmlentities($row['config_invoice_footer']);

//Set Currency Format
$currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

?>

<?php
if (!isset($_GET['id']) || !isset($_GET['key'])) {
    echo "<div class='alert alert-danger'>Incorrect URL.</div>";
    include "guest_footer.php";

    exit();
}

$item_id = intval($_GET['id']);
$item_key = sanitizeInput($_GET['key']);

$sql = mysqli_query($mysqli, "SELECT * FROM shared_items WHERE item_id = $item_id AND item_key = '$item_key' AND item_expire_at > NOW() LIMIT 1");
$row = mysqli_fetch_array($sql);

// Check we got a result
if (mysqli_num_rows($sql) !== 1 || !$row) {
    echo "<div class='alert alert-danger' >No item to view. Check with the person that sent you this link to ensure it is correct and has not expired.</div>";
    include "guest_footer.php";

    exit();
}

// Check item share is active & hasn't been viewed too many times but allow 0 views as that is consider infinite views
if ($row['item_active'] !== "1" || ($row['item_view_limit'] > 0 && $row['item_views'] >= $row['item_view_limit'])) {
    echo "<div class='alert alert-danger'>Item cannot be viewed at this time. Check with the person that sent you this link to ensure it is correct and has not expired.</div>";
    include "guest_footer.php";

    exit();
}

// If we got here, we have valid information

$item_type = nullable_htmlentities($row['item_type']);
$item_related_id = intval($row['item_related_id']);
$item_encrypted_credential = nullable_htmlentities($row['item_encrypted_credential']);
$item_note = nullable_htmlentities($row['item_note']);
$item_views = intval($row['item_views']);
$item_created = nullable_htmlentities($row['item_created_at']);
$item_expire = nullable_htmlentities($row['item_expire_at']);
$client_id = intval($row['item_client_id']);
?>

<?php
    if (!empty($company_logo)) { ?>
            <img alt="<?=nullable_htmlentities($company_name)?> logo" height="40" width="80" class="img-fluid" src="<?php echo "uploads/settings/$company_logo"; ?>">
        <?php
        } else {
            echo "<h3>$company_name</h3>";
        }
?>

<div class="card mt-2">
    <div class="card-body">

<?php
if ($item_type == "Document") {

    $doc_sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = $item_related_id AND document_client_id = $client_id LIMIT 1");
    $doc_row = mysqli_fetch_array($doc_sql);

    if (mysqli_num_rows($doc_sql) !== 1 || !$doc_row) {
        echo "<div class='alert alert-danger'>Error retrieving document to view.</div>";
        require_once "guest_footer.php";

        exit();
    }

    $doc_title = nullable_htmlentities($doc_row['document_name']);
    $doc_title_escaped = sanitizeInput($doc_row['document_name']);
    $doc_content = $purifier->purify($doc_row['document_content']);

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
        include "guest_footer.php";

        exit();
    }

    $file_name = nullable_htmlentities($file_row['file_name']);

    echo "<h3>A file has been shared with you</h3>";
    if (!empty($item_note)) {
        echo "<p class='lead'>Note: <i>$item_note</i></p>";
    }
    echo "<a href='guest_download_file.php?id=$item_id&key=$item_key'>Download $file_name</a>";


} elseif ($item_type == "Login") {
    $encryption_key = $_GET['ek'];

    $login_sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_id = $item_related_id AND login_client_id = $client_id LIMIT 1");
    $login_row = mysqli_fetch_array($login_sql);
    if (mysqli_num_rows($login_sql) !== 1 || !$login_row) {
        echo "<div class='alert alert-danger'>Error retrieving login.</div>";
        include "guest_footer.php";

        exit();
    }

    $login_id = intval($login_row['login_id']);
    $login_name = nullable_htmlentities($login_row['login_name']);
    $login_uri = nullable_htmlentities($login_row['login_uri']);

    $username_iv = substr($row['item_encrypted_username'], 0, 16);
    $username_ciphertext = substr($row['item_encrypted_username'], 16);
    $login_username = nullable_htmlentities(openssl_decrypt($username_ciphertext, 'aes-128-cbc', $encryption_key, 0, $username_iv));

    $password_iv = substr($row['item_encrypted_credential'], 0, 16);
    $password_ciphertext = substr($row['item_encrypted_credential'], 16);
    $login_password = nullable_htmlentities(openssl_decrypt($password_ciphertext, 'aes-128-cbc', $encryption_key, 0, $password_iv));

    $login_otp = nullable_htmlentities($login_row['login_otp_secret']);

    $login_otp_secret = nullable_htmlentities($login_row['login_otp_secret']);
    $login_id_with_secret = '"' . $login_row['login_id'] . '","' . $login_row['login_otp_secret'] . '"';
    if (empty($login_otp_secret)) {
        $otp_display = "-";
    } else {
        $otp_display = "<span onmouseenter='showOTP($login_id_with_secret)'><i class='far fa-clock'></i> <span id='otp_$login_id'><i>Hover..</i></span></span>";
    }

    $login_notes = nullable_htmlentities($login_row['login_note']);



    ?>

    <h4><?php echo $login_name; ?></h4>
    <table class="table col-md-3">
        <tr>
            <th>URL</th>
            <td><?php echo $login_uri; ?></td>
        </tr>
        <tr>
            <th>Username</th>
            <td><?php echo $login_username ?></td>
        </tr>
        <tr>
            <th>Password</th>
            <td><?php echo $login_password ?></td>
        </tr>
        <?php if(!empty($login_otp_secret)){ ?>
        <tr>
            <th>2FA (TOTP)</th>
            <td><?php echo $otp_display ?></td>
        </tr>
        <?php } ?>

    </table>

    <script>
        function showOTP(id, secret) {
            //Send a GET request to ajax.php as guest_ajax.php?get_totp_token=true&totp_secret=SECRET
            jQuery.get(
                "guest_ajax.php",
                {get_totp_token: 'true', totp_secret: secret},
                function(data) {
                    //If we get a response from post.php, parse it as JSON
                    const token = JSON.parse(data);

                    document.getElementById("otp_" + id).innerText = token

                }
            );
        }

        function generatePassword() {
            document.getElementById("password").value = "<?php echo randomString(); ?>"
        }
    </script>


    <?php

    // Update login view count
    $new_item_views = $item_views + 1;
    mysqli_query($mysqli, "UPDATE shared_items SET item_views = $new_item_views WHERE item_id = $item_id");

    // Logging
    $name = sanitizeInput($login_row['login_name']);
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Sharing', log_action = 'View', log_description = 'Viewed shared $item_type $name via link', log_client_id = $client_id, log_ip = '$ip', log_user_agent = '$user_agent'");

}

?>

</div>
<div class="card-footer">
<?php echo "<i class='fas fa-phone fa-fw mr-2'></i>$company_phone | <i class='fas fa-globe fa-fw mr-2 ml-2'></i>$company_website"; ?>
</div>

<?php
require_once "guest_footer.php";

?>
