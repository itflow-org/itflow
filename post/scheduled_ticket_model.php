<?php
// HTML Purifier
//require_once "plugins/htmlpurifier/HTMLPurifier.standalone.php";

//$purifier_config = HTMLPurifier_Config::createDefault();
//$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
//$purifier = new HTMLPurifier($purifier_config);

$client_id = intval($_POST['client']);
$subject = sanitizeInput($_POST['subject']);
$priority = sanitizeInput($_POST['priority']);
//$details = trim(mysqli_real_escape_string($mysqli, $purifier->purify(html_entity_decode($_POST['details']))));
$details = mysqli_real_escape_string($mysqli, $_POST['details']);
$frequency = sanitizeInput($_POST['frequency']);


$asset_id = "0";
if (isset($_POST['asset'])) {
    $asset_id = intval($_POST['asset']);
}

$contact_id = "0";
if (isset($_POST['contact'])) {
    $contact_id = intval($_POST['contact']);
}
