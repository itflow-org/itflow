<?php

require_once('../validate_api_key.php');
require_once('../require_get_method.php');

// Specific contact via ID (single)
if (isset($_GET['contact_id'])) {
    $id = intval($_GET['contact_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = '$id' AND contact_client_id LIKE '$client_id' AND company_id = '$company_id'");

} elseif (isset($_GET['contact_email'])) {
    // Specific contact via email (single)

    $email = mysqli_real_escape_string($mysqli, $_GET['contact_email']);
    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' AND contact_client_id LIKE '$client_id' AND company_id = '$company_id'");

} else {
    // All contacts

    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY contact_id LIMIT $limit OFFSET $offset");
}

// Output
require_once("../read_output.php");
