<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

include('../config.php');
include('../functions.php');
include('check_login.php');

if(!isset($_SESSION)){
  // HTTP Only cookies
  ini_set("session.cookie_httponly", True);
  if($config_https_only){
    // Tell client to only send cookie(s) over HTTPS
    ini_set("session.cookie_secure", True);
  }
  session_start();
}

$contact_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = '$session_contact_id' AND contact_client_id = '$session_client_id' LIMIT 1");
$contact_row = mysqli_fetch_array($contact_sql);

$contact_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_status != 'Closed' AND ticket_contact_id = '$session_contact_id' AND ticket_client_id = '$session_client_id'");
$tickets = mysqli_fetch_array($contact_tickets);
?>


<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $config_app_name; ?> | Client Portal</title>

  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">

  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>
<div class="container">
  <h2>Logged in as <?php echo $contact_row['contact_name'] ?></h2>

  <br>
  <h3>My open tickets</h3>
  <table class="table">
    <thead>
      <tr>
        <th scope="col">Subject</th>
        <th scope="col">State</th>
      </tr>
    </thead>
    <tbody>

        <?php
        while($ticket = mysqli_fetch_array($contact_tickets)){
          echo "<tr>";
          echo "<td> <a href='ticket.php?id=$ticket[ticket_id]'> $ticket[ticket_subject]</a></td>";
          echo "<td>$ticket[ticket_status]</td>";
          echo "</tr>";
        }
        ?>
    </tbody>
  </table>
</div>