<?php
require('../validate_api_key.php');

require('../require_get_method.php');

// Specific domain via ID (single)
if(isset($_GET['domain_id'])){
  $id = intval($_GET['domain_id']);
  $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_id = '$id' AND domain_client_id LIKE '$client_id' AND company_id = '$company_id'");
}

// Domain by name
elseif(isset($_GET['domain_name'])){
  $name = mysqli_real_escape_string($mysqli,$_GET['domain_name']);
  $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_name = '$name' AND domain_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Domain via client ID (if allowed)
elseif(isset($_GET['client_id']) && $client_id == "%"){
  $client_id = intval($_GET['client_id']);
  $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY domain_id LIMIT $limit OFFSET $offset");
}

// All domains
else{
  $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY domain_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");