<?php

if(isset($_GET['tab'])){
  if($_GET['tab'] == "contacts"){
    include("client_contacts.php");
  }
  elseif($_GET['tab'] == "locations"){
    include("client_locations.php");
  }
  elseif($_GET['tab'] == "assets"){
    include("client_assets.php");
  }
  elseif($_GET['tab'] == "tickets"){
    include("client_tickets.php");
  }
  elseif($_GET['tab'] == "vendors"){
    include("client_vendors.php");
  }
  elseif($_GET['tab'] == "logins"){
    include("client_logins.php");
  }
  elseif($_GET['tab'] == "networks"){
    include("client_networks.php");
  }
  elseif($_GET['tab'] == "domains"){
    include("client_domains.php");
  }
  elseif($_GET['tab'] == "certificates"){
    include("client_certificates.php");
  }
  elseif($_GET['tab'] == "software"){
    include("client_software.php");
  }
  elseif($_GET['tab'] == "invoices"){
    include("client_invoices.php");
  }
  elseif($_GET['tab'] == "recurring"){
    include("client_recurring.php");
  }
  elseif($_GET['tab'] == "payments"){
    include("client_payments.php");
  }
  elseif($_GET['tab'] == "quotes"){
    include("client_quotes.php");
  }
  elseif($_GET['tab'] == "trips"){
    include("client_trips.php");
  }
  elseif($_GET['tab'] == "events"){
    include("client_events.php");
  }
  elseif($_GET['tab'] == "files"){
    include("client_files.php");
  }
  elseif($_GET['tab'] == "notes"){
    include("client_notes.php");
  }
}
else{
  include("client_overview.php");
}

?>