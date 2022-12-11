<?php

include("config.php");
include("functions.php");
include("check_login.php");

$company_id = '1';

// Get the oldest updated domain (MariaDB shows NULLs first when ordering by default)
$row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT certificate_id, certificate_domain FROM `certificates` ORDER BY certificate_updated_at LIMIT 1"));

if(!empty($row)){
  $certificate_id = $row['certificate_id'];
  $certificate_domain = $row['certificate_domain'];

  // FQDNs in database shouldn't have a URL scheme, adding one
  $domain = "https://".$certificate_domain;

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
  $read = stream_socket_client($socket, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $get);

  if($read){
    $cert = stream_context_get_params($read);
    $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

    // Success - process data
    if($cert_public_key_obj){
      $expire = mysqli_real_escape_string($mysqli, date('Y-m-d', $cert_public_key_obj['validTo_time_t']));
      $issued_by = mysqli_real_escape_string($mysqli, strip_tags($cert_public_key_obj['issuer']['O']));
      $public_key = mysqli_real_escape_string($mysqli, $export);

      // Update the record (forcing certificate_created_at field to be updated to ensure we don't try and update the same record every day)
      mysqli_query($mysqli, "UPDATE certificates SET certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key', certificate_updated_at = NOW() WHERE certificate_id = '$certificate_id' LIMIT 1");
      echo "Updated $certificate_domain";
    }
    else{
      // Likely the SSL socket failed, log an error notification
      mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Cron', notification = 'Nightly SSL update for $certificate_domain failed. Please check and manually update this record.', notification_timestamp = NOW(), company_id = $company_id");
      echo "Update $certificate_domain failed";
    }
  }
  else{
    // Likely the SSL socket failed, log an error notification
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Cron', notification = 'Nightly SSL update for $certificate_domain failed. Please check and manually update this record.', notification_timestamp = NOW(), company_id = $company_id");
    echo "Update $certificate_domain failed";
  }

}

echo "Carried on!";

?>