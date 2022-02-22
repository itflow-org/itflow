<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">
  
  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="clients.php" class="nav-link">
            <i class="nav-icon fas fa-arrow-left"></i>
            <p>Back</p> | 
            <p><strong><?php echo $client_name; ?></strong></p>
          </a>
        </li>
        <div class="sidebar-custom">
          <div class="text-wrap"><?php echo $client_tags_display; ?></div>
        </div>

        <li class="nav-header mt-3">CLIENT</li>

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
          <a href="?client_id=<?php echo $client_id; ?>&tab=departments" class="nav-link <?php if($_GET['tab'] == "departments") { echo "active"; } ?>">
            <i class="nav-icon fas fa-building"></i>
            <p>
              Departments
              <?php 
              if($num_departments > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_departments; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-header mt-3">ASSETS</li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=assets" class="nav-link <?php if($_GET['tab'] == "assets") { echo "active"; } ?>">
            <i class="nav-icon fas fa-desktop"></i>
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
          <a href="?client_id=<?php echo $client_id; ?>&tab=software" class="nav-link <?php if($_GET['tab'] == "software") { echo "active"; } ?>">
            <i class="nav-icon fas fa-cube"></i>
            <p>
              Software
              <?php 
              if($num_software > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_software; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

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
          <a href="?client_id=<?php echo $client_id; ?>&tab=certificates" class="nav-link <?php if($_GET['tab'] == "certificates") { echo "active"; } ?>">
            <i class="nav-icon fas fa-lock"></i>
            <p>
              Certificates
              <?php 
              if($num_certificates > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_certificates; ?></span>
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

        <li class="nav-header mt-3">SUPPORT</li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=tickets" class="nav-link <?php if($_GET['tab'] == "tickets") { echo "active"; } ?>">
            <i class="nav-icon fas fa-ticket-alt"></i>
            <p>
              Tickets
              <?php 
              if($num_active_tickets > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_active_tickets; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <li class="nav-item">
            <a href="?client_id=<?php echo $client_id; ?>&tab=services" class="nav-link <?php if($_GET['tab'] == "services") { echo "active"; } ?>">
                <i class="nav-icon fas fa-stream"></i>
                <p>
                    Services
                    <?php
                    if($num_services > 0){ ?>
                        <span class="right badge badge-light"><?php echo $num_services; ?></span>
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
          <a href="?client_id=<?php echo $client_id; ?>&tab=documents" class="nav-link <?php if($_GET['tab'] == "documents") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file-alt"></i>
            <p>
              Documents
              <?php 
              if($num_documents > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_documents; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <?php if($session_user_role == 1 OR $session_user_role > 2){ ?>

        <li class="nav-header mt-3">ACCOUNTING</li>

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
          <a href="?client_id=<?php echo $client_id; ?>&tab=recurring_invoices" class="nav-link <?php if($_GET['tab'] == "recurring_invoices") { echo "active"; } ?>">
            <i class="nav-icon fas fa-sync-alt"></i>
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

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=trips" class="nav-link <?php if($_GET['tab'] == "trips") { echo "active"; } ?>">
            <i class="nav-icon fas fa-route"></i>
            <p>
              Trips
              <?php 
              if($num_trips > 0){ ?>
              <span class="right badge badge-light"><?php echo $num_trips; ?></span>
              <?php } ?>
            </p>
          </a>
        </li>

        <?php } ?>

        <li class="nav-header mt-3">MORE</li>

        <li class="nav-item">
          <a href="?client_id=<?php echo $client_id; ?>&tab=logs" class="nav-link <?php if($_GET['tab'] == "logs") { echo "active"; } ?>">
            <i class="nav-icon fas fa-eye"></i>
            <p>Logs</p>
          </a>
        </li>

      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
