<ul class="nav nav-pills nav-fill">
  <?php if($num_contacts > 0){ ?>
  <li class="nav-item">
    <a class="nav-link active" href="?client_id=<?php echo $client_id; ?>&tab=contacts">
      <i class="fa fa-users"></i><br>
      Contacts<br>
      <span class="badge badge-pill badge-light"><?php echo $num_contacts; ?></span>
    </a> 
  </li>
  <?php } ?>
  <?php if($num_locations > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=locations">
      <i class="fa fa-map-marker"></i><br>
      Locations<br>
      <span class="badge badge-pill badge-fark"><?php echo $num_locations; ?></span>
      
    </a>
  </li>
  <?php } ?>
  <?php if($num_assets > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=assets">
      <i class="fa fa-tag"></i><br>
      Assets<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_assets; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_vendors > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=vendors">
      <i class="fa fa-building"></i><br>
      Vendors<br>
      <span class="badge badge-pill badge-secondary"><?php echo $num_vendors; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_logins > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=logins">
      <i class="fa fa-key"></i><br>
      Logins<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_logins; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_networks > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=networks">
      <i class="fa fa-network-wired"></i><br>
      Networks<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_networks; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_domains > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=domains">
      <i class="fa fa-globe"></i><br>
      Domains<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_domains; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_applications > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=applications">
      <i class="fa fa-box"></i><br>
      Applications<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_applications; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_invoices > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=invoices">
      <i class="fa fa-file"></i><br>
      Invoices<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_invoices; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_recurring > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=recurring">
      <i class="fa fa-copy"></i><br>
      Recurring<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_recurring; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_quotes > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=quotes">
      <i class="fa fa-file-o"></i><br>
      Quotes<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_quotes; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_attachments > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=attachments">
      <i class="fa fa-paperclip"></i><br>
      Attachments<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_attachments; ?></span>
    </a>
  </li>
  <?php } ?>
  <?php if($num_notes > 0){ ?>
  <li class="nav-item">
    <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=notes">
      <i class="fa fa-edit"></i><br>
      Notes<br>
      <span class="badge badge-pill badge-dark"><?php echo $num_notes; ?></span>
    </a>
  </li>
  <?php } ?>
</ul>