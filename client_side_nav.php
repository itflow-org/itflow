<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4 d-print-none">
  
  <!-- Brand Logo -->
  <a href="index3.html" class="brand-link">
    <img src="dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3"
         style="opacity: .8">
    <span class="brand-text font-weight-light"><?php echo $config_app_name; ?></span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="clients.php" class="nav-link">
            <i class="nav-icon fas fa-arrow-left"></i>
            <p>Back</p>
          </a>
        </li>
        <li class="nav-header">CLIENT</li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=contacts" class="nav-link <?php if($_GET['tab'] == "contacts") { echo "active"; } ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>
              Contacts
              <?php 
              if($num_contacts > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_contacts; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=locations" class="nav-link <?php if($_GET['tab'] == "locations") { echo "active"; } ?>">
            <i class="nav-icon fas fa-map-marker-alt"></i>
            <p>
              Locations
              <?php 
              if($num_locations > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_locations; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=assets" class="nav-link <?php if($_GET['tab'] == "assets") { echo "active"; } ?>">
            <i class="nav-icon fas fa-laptop"></i>
            <p>
              Assets
              <?php 
              if($num_assets > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_assets; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=tickets" class="nav-link <?php if($_GET['tab'] == "tickets") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tags"></i>
            <p>
              Tickets
              <?php 
              if($num_tickets > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_tickets; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=vendors" class="nav-link <?php if($_GET['tab'] == "vendors") { echo "active"; } ?>">
            <i class="nav-icon fas fa-building"></i>
            <p>
              Vendors
              <?php 
              if($num_vendors > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_vendors; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-header">ASSETS</li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=logins" class="nav-link <?php if($_GET['tab'] == "logins") { echo "active"; } ?>">
            <i class="nav-icon fas fa-key"></i>
            <p>
              Logins
              <?php 
              if($num_logins > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_logins; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=networks" class="nav-link <?php if($_GET['tab'] == "networks") { echo "active"; } ?>">
            <i class="nav-icon fas fa-network-wired"></i>
            <p>
              Networks
              <?php 
              if($num_networks > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_networks; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=domains" class="nav-link <?php if($_GET['tab'] == "domains") { echo "active"; } ?>">
            <i class="nav-icon fas fa-globe"></i>
            <p>
              Domains
              <?php 
              if($num_domains > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_domains; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=software" class="nav-link <?php if($_GET['tab'] == "software") { echo "active"; } ?>">
            <i class="nav-icon fas fa-rocket"></i>
            <p>
              Software
              <?php 
              if($num_software > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_software; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-header">PAYMENTS</li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=invoices" class="nav-link <?php if($_GET['tab'] == "invoices") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file"></i>
            <p>
              Invoices
              <?php 
              if($num_invoices > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_invoices; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=recurring" class="nav-link <?php if($_GET['tab'] == "recurring") { echo "active"; } ?>">
            <i class="nav-icon fas fa-copy"></i>
            <p>
              Recurring
              <?php 
              if($num_recurring > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_recurring; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=quotes" class="nav-link <?php if($_GET['tab'] == "quotes") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file"></i>
            <p>
              Quotes
              <?php 
              if($num_quotes > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_quotes; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=payments" class="nav-link <?php if($_GET['tab'] == "payments") { echo "active"; } ?>">
            <i class="nav-icon fas fa-credit-card"></i>
            <p>
              Payments
              <?php 
              if($num_payments > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_payments; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-header">MORE</li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=trips" class="nav-link <?php if($_GET['tab'] == "trips") { echo "active"; } ?>">
            <i class="nav-icon fas fa-bicycle"></i>
            <p>
              Trips
              <?php 
              if($num_trips > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_trips; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=events" class="nav-link <?php if($_GET['tab'] == "events") { echo "active"; } ?>">
            <i class="nav-icon fas fa-calendar"></i>
            <p>
              Events
              <?php 
              if($num_events > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_events; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=files" class="nav-link <?php if($_GET['tab'] == "files") { echo "active"; } ?>">
            <i class="nav-icon fas fa-paperclip"></i>
            <p>
              Files
              <?php 
              if($num_files > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_files; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=notes" class="nav-link <?php if($_GET['tab'] == "notes") { echo "active"; } ?>">
            <i class="nav-icon fas fa-edit"></i>
            <p>
              Notes
              <?php 
              if($num_notes > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_notes; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>