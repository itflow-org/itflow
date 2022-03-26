<?php

/*
 * ajax.php
 * Similar to post.php, but for requests using Asynchronous JavaScript
 * Always returns data in JSON format, unless otherwise specified
 */

include("config.php");
include("functions.php");
include("check_login.php");

/*
 * Fetches SSL certificates from remote hosts & returns the relevant info (issuer, expiry, public key)
 */
if(isset($_GET['certificate_fetch_parse_json_details'])){
  // PHP doesn't appreciate attempting SSL sockets to non-existent domains
  if(empty($_GET['domain'])){
    exit();
  }
  $domain = $_GET['domain'];

  // FQDNs in database shouldn't have a URL scheme, adding one
  $domain = "https://".$domain;

  // Parse host and port
  $url = parse_url($domain, PHP_URL_HOST);
  $port = parse_url($domain, PHP_URL_PORT);
  // Default port
  if(!$port){
    $port = "443";
  }

  // Get certificate (using verify peer false to allow for self-signed certs)
  $socket = "ssl://$url:$port";
  $get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE, "verify_peer" => FALSE,)));
  $read = stream_socket_client($socket, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
  $cert = stream_context_get_params($read);
  $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
  openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

  // Process data
  if($cert_public_key_obj){
    $response['success'] = "TRUE";
    $response['expire'] = date('Y-m-d', $cert_public_key_obj['validTo_time_t']);
    $response['issued_by'] = strip_tags($cert_public_key_obj['issuer']['O']);
    $response['public_key'] = $export; //nl2br
  }
  else{
    $response['success'] = "FALSE";
  }

  echo json_encode($response);

}

/*
 * Looks up info for a given certificate ID from the database, used to dynamically populate modal fields
 */
if(isset($_GET['certificate_get_json_details'])){
  $certificate_id = intval($_GET['certificate_id']);
  $client_id = intval($_GET['client_id']);

  // Individual certificate lookup
  $cert_sql = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id");
  while($row = mysqli_fetch_array($cert_sql)){
    $response['certificate'][] = $row;
  }

  // Get all domains for this client that could be linked to this certificate
  $domains_sql = mysqli_query($mysqli, "SELECT domain_id, domain_name FROM domains WHERE domain_client_id = '$client_id' AND company_id = '$session_company_id'");
  while($row = mysqli_fetch_array($domains_sql)){
    $response['domains'][] = $row;
  }

  echo json_encode($response);
}

/*
 * Looks up info for a given domain ID from the database, used to dynamically populate modal fields
 */
if(isset($_GET['domain_get_json_details'])){
  $domain_id = intval($_GET['domain_id']);
  $client_id = intval($_GET['client_id']);

  // Individual domain lookup
  $cert_sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id");
  while($row = mysqli_fetch_array($cert_sql)){
    $response['domain'][] = $row;
  }

  // Get all registrars/webhosts (vendors) for this client that could be linked to this domain
  $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id");
  while($row = mysqli_fetch_array($vendor_sql)){
    $response['vendors'][] = $row;
  }

  echo json_encode($response);
}

/*
 * Looks up info on the ticket number provided, used to populate the ticket merge modal
 */
if(isset($_GET['merge_ticket_get_json_details'])){
  $merge_into_ticket_number = intval($_GET['merge_into_ticket_number']);

  $sql = mysqli_query($mysqli,"SELECT * FROM tickets
      LEFT JOIN clients ON ticket_client_id = client_id 
      LEFT JOIN contacts ON ticket_contact_id = contact_id
      WHERE ticket_number = '$merge_into_ticket_number' AND tickets.company_id = '$session_company_id'");

  if(mysqli_num_rows($sql) == 0){
    //Do nothing.
  }
  else {
    //Return ticket, client and contact details for the given ticket number
    $response = mysqli_fetch_array($sql);
    echo json_encode($response);
  }
}

/*
 * Looks up info for a given network ID from the database, used to dynamically populate modal fields
 */
if(isset($_GET['network_get_json_details'])){
  $network_id = intval($_GET['network_id']);
  $client_id = intval($_GET['client_id']);

  // Individual network lookup
  $network_sql = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_id = $network_id AND network_client_id = $client_id");
  while($row = mysqli_fetch_array($network_sql)){
    $response['network'][] = $row;
  }

  // Lookup all client locations, as networks can be associated with any client location
  $locations_sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations 
        WHERE location_client_id = '$client_id' AND company_id = '$session_company_id'"
  );
  while($row = mysqli_fetch_array($locations_sql)){
    $response['locations'][] = $row;
  }

  echo json_encode($response);
}

if(isset($_POST['client_set_notes'])){
  $client_id = intval($_POST['client_id']);
  $notes = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['notes'])));

  // Update notes
  mysqli_query($mysqli, "UPDATE clients SET client_notes = '$notes' WHERE client_id = '$client_id'");

  // Logging
  mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Modify', log_description = '$session_name modified client notes', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

}

if(isset($_GET['ticket_add_view'])){
  $ticket_id = intval($_GET['ticket_id']);

 $a = mysqli_query($mysqli, "INSERT INTO ticket_views SET view_ticket_id = '$ticket_id', view_user_id = '$session_user_id', view_timestamp = NOW()");
}

if(isset($_GET['ticket_query_views'])){
  $ticket_id = intval($_GET['ticket_id']);

  $query = mysqli_query($mysqli, "SELECT user_name FROM ticket_views LEFT JOIN users ON view_user_id = user_id WHERE view_ticket_id = '$ticket_id' AND view_user_id != '$session_user_id' AND view_timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
  while($row = mysqli_fetch_array($query)){
    $users[] = $row['user_name'];
  }
  if(!empty($users)){
    $users = array_unique($users);
    if(count($users) > 1){
      // Multiple viewers
      $response['message'] = implode(", ", $users) . " are viewing this ticket.";
    }
    else{
      // Single viewer
      $response['message'] = implode("", $users) . " is viewing this ticket.";
    }
  }
  else{
    // No viewers
    $response['message'] = "";
  }
  echo json_encode($response);

}