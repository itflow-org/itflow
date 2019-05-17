<!-- Client Sidebar -->

<ul class="sidebar navbar-nav d-print-none">
  
  <li class="nav-item my-3">
    <h2 class="text-white text-center"><?php echo $client_name; ?></h2>
    <h6 class="text-secondary text-center"><?php echo $client_type; ?></h6>
  </li>

  <li class="nav-item">
    <a class="nav-link" href="clients.php">
      <button class="btn btn-outline-light btn-block">
      <i class="fas fa-fw fa-arrow-left"></i>
      <span>Back</span>
    </button>
    </a>
  </li>

  <li class="nav-item <?php if($_GET['tab'] == "overview") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=overview">
      <i class="fas fa-fw fa-chart-bar mx-2"></i>
      <span>Overview</span>
    </a>
  </li>

  <li class="nav-item <?php if($_GET['tab'] == "contacts") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=contacts">
      <i class="fas fa-fw fa-users mx-2"></i>
      <span>Contacts 
        <?php 
        if($num_contacts > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_contacts; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>

  <li class="nav-item <?php if($_GET['tab'] == "locations") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=locations">
      <i class="fas fa-fw fa-map-marker-alt mx-2"></i>
      <span>Locations
        <?php 
        if($num_locations > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_locations; ?></small>
        <?php
        }
        ?>
      </span> 
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "assets") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=assets">
      <i class="fas fa-fw fa-laptop mx-2"></i>
      <span>Assets
        <?php 
        if($num_assets > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_assets; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>

  <li class="nav-item <?php if($_GET['tab'] == "tickets") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=tickets">
      <i class="fas fa-fw fa-tags mx-2"></i>
      <span>Tickets
        <?php 
        if($num_tickets > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_tickets; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "vendors") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=vendors">
      <i class="fas fa-fw fa-building mx-2"></i>
      <span>Vendors
        <?php 
        if($num_vendors > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_vendors; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "logins") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=logins">
      <i class="fas fa-fw fa-key mx-2"></i>
      <span>Logins
        <?php 
        if($num_logins > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_logins; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "networks") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=networks">
      <i class="fas fa-fw fa-network-wired mx-2"></i>
      <span>Networks
        <?php 
        if($num_networks > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_networks; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "domains") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=domains">
      <i class="fas fa-fw fa-globe mx-2"></i>
      <span>Domains
        <?php 
        if($num_domains > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_domains; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "software") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=software">
      <i class="fas fa-fw fa-rocket mx-2"></i>
      <span>Software
        <?php 
        if($num_software > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_software; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "invoices") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=invoices">
      <i class="fas fa-fw fa-file mx-2"></i>
      <span>Invoices
        <?php 
        if($num_invoices > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_invoices; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "recurring") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=recurring">
      <i class="fas fa-fw fa-copy mx-2"></i>
      <span>Recurring
        <?php 
        if($num_recurring > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_recurring; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "quotes") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=quotes">
      <i class="fas fa-fw fa-file mx-2"></i>
      <span>Quotes
        <?php 
        if($num_quotes > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_quotes; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "payments") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=payments">
      <i class="fas fa-fw fa-credit-card mx-2"></i>
      <span>Payments
        <?php 
        if($num_payments > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_payments; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>

  <li class="nav-item <?php if($_GET['tab'] == "files") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=files">
      <i class="fas fa-fw fa-paperclip mx-2"></i>
      <span>Files
        <?php 
        if($num_files > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_files; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
  
  <li class="nav-item <?php if($_GET['tab'] == "notes") { echo "active"; } ?>">
    <a class="nav-link" 
      href="?client_id=<?php echo $client_id; ?>&tab=notes">
      <i class="fas fa-fw fa-edit mx-2"></i>
      <span>Notes
        <?php 
        if($num_notes > 0){ ?> 
          <small class="float-right badge-secondary badge-pill mt-1"><?php echo $num_notes; ?></small>
        <?php
        }
        ?>
      </span>
    </a>
  </li>
</ul>