<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Specific credential/login via ID (single)
if (isset($_GET['login_id'])) {
    $id = intval($_GET['login_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_id = '$id' AND login_client_id LIKE '$client_id'");

} else {
    // All credentials ("logins")
    $sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_client_id LIKE '$client_id' ORDER BY login_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

