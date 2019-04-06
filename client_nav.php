<ul class="nav nav-pills nav-fill">
  <?php if($num_contacts > 0){ ?>
  <li class="nav-item">
    <a class="nav-link active" href="?client_id=<?php echo $client_id; ?>&tab=contacts">
      Contacts<br>
      <span class="badge badge-pill badge-light"><?php echo $num_contacts; ?></span>
    </a> 
  </li>
  <?php } ?>
  <?php if($num_locations > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=locations">
      Locations<br>
      <span class="badge badge-pill badge-fark"><?php echo $num_locations; ?></span>
      
    </a>
  </li>
  <?php } ?>
  <?php if($num_assets > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=assets">
      Assets<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_assets; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_vendors > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=vendors">
      Vendors<br>
      <span class="badge badge-pill badge-secondary"><?php echo $num_vendors; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_logins > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=logins">
      Logins<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_logins; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_networks > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=networks">
      Networks<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_networks; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_domains > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=domains">
      Domains<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_domains; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_applications > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=applications">
      Applications<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_applications; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_invoices > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=invoices">
      Invoices<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_invoices; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_quotes > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=quotes">
      Quotes<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_quotes; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_attachments > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=attachments">
      Attachments<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_attachments; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_notes > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=notes">
      Notes<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_notes; ?></span>
    </a>
  </li>
  <?php } ?>
</ul>