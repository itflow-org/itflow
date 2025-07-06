<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


if (isset($_GET['quote_id'])) {
    // quote via ID (single)
    $id = intval($_GET['quote_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM quotes WHERE quote_id LIKE '$id' AND quote_client_id = '$client_id'");

} else {
    // All quotes (by client ID if given, or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM quotes WHERE quote_client_id LIKE '$client_id' ORDER BY quote_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

