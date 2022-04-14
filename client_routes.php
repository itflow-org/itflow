<?php

if(isset($_GET['tab'])){

  include('pagination_head.php');

  if($_GET['tab'] == "overview"){
    include("client_overview.php");
  }
  elseif($_GET['tab'] == "contacts"){
    include("client_contacts.php");
  }
  elseif($_GET['tab'] == "locations"){
    include("client_locations.php");
  }
  if($_GET['tab'] == "departments"){
    include("client_departments.php");
  }
  elseif($_GET['tab'] == "assets"){
    if($session_user_role > 1) {
      include("client_assets.php");
    }
  }
  elseif($_GET['tab'] == "workstations"){
    if($session_user_role > 1) {
      include("client_assets_workstations.php");
    }
  }
  elseif($_GET['tab'] == "tickets"){
    if($session_user_role > 1) {
      include("client_tickets.php");
    }
  }
  elseif($_GET['tab'] == "vendors"){
    include("client_vendors.php");
  }
  elseif($_GET['tab'] == "logins"){
    if($session_user_role > 1) {
      include("client_logins.php");
    }
  }
  elseif($_GET['tab'] == "networks"){
    if($session_user_role > 1) {
      include("client_networks.php");
    }
  }
  elseif($_GET['tab'] == "domains"){
    if($session_user_role > 1) {
      include("client_domains.php");
    }
  }
  elseif($_GET['tab'] == "certificates"){
    if($session_user_role > 1) {
      include("client_certificates.php");
    }
  }
  elseif($_GET['tab'] == "software"){
    if($session_user_role > 1) {
      include("client_software.php");
    }
  }
  elseif($_GET['tab'] == "invoices"){
    if($session_user_role == 1 || $session_user_role == 3) {
      include("client_invoices.php");
    }
  }
  elseif($_GET['tab'] == "recurring_invoices"){
    if($session_user_role == 1 || $session_user_role == 3) {
      include("client_recurring_invoices.php");
    }
  }
  elseif($_GET['tab'] == "payments"){
    if($session_user_role == 1 || $session_user_role == 3) {
      include("client_payments.php");
    }
  }
  elseif($_GET['tab'] == "quotes"){
    if($session_user_role == 1 || $session_user_role == 3) {
      include("client_quotes.php");
    }
  }
  elseif($_GET['tab'] == "trips"){
    if($session_user_role == 1 || $session_user_role == 3) {
      include("client_trips.php");
    }
  }
  elseif($_GET['tab'] == "events"){
    include("client_events.php");
  }
  elseif($_GET['tab'] == "files"){
    if($session_user_role > 1) {
      include("client_files.php");
    }
  }
  elseif($_GET['tab'] == "documents"){
    if($session_user_role > 1) {
      include("client_documents.php");
    }
  }
  elseif($_GET['tab'] == "services"){
    if($session_user_role > 1) {
      include("client_services.php");
    }
  }
  elseif($_GET['tab'] == "logs"){
      include("client_logs.php");
  }
  elseif($_GET['tab'] == "shared-items") {
    if ($session_user_role > 1) {
      include("client_shared_items.php");
    }
  }
  elseif($_GET['tab'] == "scheduled-tickets") {
    if ($session_user_role > 1) {
      include("client_scheduled_tickets.php");
    }
  }
}
else{
  include("client_overview.php");
}
