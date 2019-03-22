<ul class="nav nav-pills">
  <li class="nav-item">
    <a class="nav-link active" href="?client_id=<?php echo $client_id; ?>&tab=details">Details</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=contacts">
      Contacts
      <span class="badge badge-pill badge-dark"><?php echo $num_contacts; ?></span>
    </a> 
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=locations">
      Locations
      <span class="badge badge-pill badge-dark"><?php echo $num_locations; ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=assets">
      Assets
      <span class="badge badge-pill badge-dark"><?php echo $num_assets; ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=vendors">
      Vendors
      <span class="badge badge-pill badge-dark"><?php echo $num_vendors; ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=logins">
      Logins
      <span class="badge badge-pill badge-dark"><?php echo $num_logins; ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=invoices">
      Invoices
      <span class="badge badge-pill badge-dark"><?php echo $num_invoices; ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=quotes">
      Quotes
      <span class="badge badge-pill badge-dark"><?php echo $num_quotes; ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=attachments">
      Attachments
      <span class="badge badge-pill badge-dark"><?php echo $num_attachments; ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=notes">
      Notes
      <span class="badge badge-pill badge-dark"><?php echo $num_notes; ?></span>
    </a>
  </li>
</ul>