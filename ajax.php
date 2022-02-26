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