<?php
require('../validate_api_key.php');

if($_SERVER['REQUEST_METHOD'] !== "GET"){
  header("HTTP/1.1 405 Method Not Allowed");
  $return_arr['success'] = "False";
  $return_arr['message'] = "Can only send GET requests to this endpoint.";
  echo json_encode($return_arr);
  exit();
}

// Specific ticket via ID (single)
if(isset($_GET['ticket_id'])){
  $id = intval($_GET['ticket_id']);
  $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$id' AND company_id = '$company_id'");
}

// All tickets
else{
  $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE company_id = '$company_id' ORDER BY ticket_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");