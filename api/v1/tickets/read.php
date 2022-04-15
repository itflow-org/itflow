<?php
require('../validate_api_key.php');

require('../require_get_method.php');

// Specific ticket via ID (single)
if(isset($_GET['ticket_id'])){
  $id = intval($_GET['ticket_id']);
  $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$id' AND ticket_client_id LIKE '$client_id' AND company_id = '$company_id'");
}

// All tickets
else{
  $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY ticket_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");