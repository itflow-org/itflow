<?php

require_once('../validate_api_key.php');
require_once('../require_get_method.php');

if (isset($_GET['document_id'])) {
    // Document via ID (single)

    $id = intval($_GET['document_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = '$id' AND document_client_id LIKE '$client_id' AND company_id = '$company_id'");

} else {
    // All documents

    $sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY document_id LIMIT $limit OFFSET $offset");
}

// Output
require_once("../read_output.php");
