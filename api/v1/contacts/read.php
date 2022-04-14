<?php
require('../validate_api_key.php');

require('../require_get_method.php');

// Specific contact via ID (single)
if(isset($_GET['contact_id'])){
    $id = intval($_GET['contact_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = '$id' AND company_id = '$company_id'");
}

// Specific contact via email (single)
elseif(isset($_GET['contact_email'])){
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['contact_email'])));
    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' AND company_id = '$company_id'");
}

// All contacts
else{
    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE company_id = '$company_id' ORDER BY contact_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");