<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-3">

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">
        
        <li class="nav-item mb-3">
          <a href="dashboard_financial.php" class="nav-link">
            <i class="nav-icon fas fa-arrow-left"></i>
            <p>Back</p> | 
            <p><strong>Settings</strong></p>
          </a>
        </li>

        <li class="nav-item">
          <a href="users.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "users.php") { echo "active"; } ?>">
            <i class="nav-icon far fa-user"></i>
            <p>Users</p>
          </a>
        </li>
        
        <li class="nav-item">
          <a href="companies.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "companies.php") { echo "active"; } ?>">
            <i class="nav-icon far fa-building"></i>
            <p>Companies</p>
          </a>
        </li>

        <li class="nav-header mt-3">SETTINGS</li>
        
        <li class="nav-item">
          <a href="settings-general.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-general.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-cog"></i>
            <p>General</p>
          </a> 
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-mail.php") { echo "active"; } ?>" 
            href="settings-mail.php">
            <i class="nav-icon far fa-envelope"></i>
            <p>Mail</p>
          </a> 
        </li>

        <?php if($config_module_enable_accounting){ ?>
        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-defaults.php") { echo "active"; } ?>" 
            href="settings-defaults.php">
            <i class="nav-icon fas fa-cog"></i>
            <p>Defaults</p>
          </a> 
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-invoice.php") { echo "active"; } ?>" 
            href="settings-invoice.php">
            <i class="nav-icon fas fa-file"></i>
            <p>Invoice</p>
          </a> 
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-quote.php") { echo "active"; } ?>" 
            href="settings-quote.php">
            <i class="nav-icon far fa-file"></i>
            <p>Quote</p>
          </a> 
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-alerts.php") { echo "active"; } ?>" 
            href="settings-alerts.php">
            <i class="nav-icon far fa-bell"></i>
            <p>Alerts</p>
          </a> 
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-online-payment.php") { echo "active"; } ?>" 
            href="settings-online-payment.php">
            <i class="nav-icon far fa-credit-card"></i>
            <p>Online Payment</p>
          </a> 
        </li>

        <li class="nav-item">
          <a href="categories.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "categories.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-list"></i>
            <p>Categories</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="taxes.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "taxes.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-balance-scale"></i>
            <p>Taxes</p>
          </a>
        </li>

        <?php } ?>

        <?php if($config_module_enable_ticketing){ ?>
        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-ticket.php") { echo "active"; } ?>" 
            href="settings-ticket.php">
            <i class="nav-icon fas fa-ticket-alt"></i>
            <p>Ticket</p>
          </a> 
        </li>
        <?php } ?>

        <li class="nav-item">
          <a href="settings-integrations.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-integrations.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-puzzle-piece"></i>
            <p>Integrations</p>
          </a> 
        </li>
       
        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-update.php") { echo "active"; } ?>" 
            href="settings-update.php">
            <i class="nav-icon fas fa-arrow-alt-circle-up"></i>
            <p>Update</p>
          </a> 
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-backup.php") { echo "active"; } ?>" 
            href="settings-backup.php">
            <i class="nav-icon fas fa-database"></i>
            <p>Backup</p>
          </a> 
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-api.php") { echo "active"; } ?>"
             href="settings-api.php">
              <i class="nav-icon fas fa-key"></i>
              <p>API</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-modules.php") { echo "active"; } ?>"
             href="settings-modules.php">
              <i class="nav-icon fas fa-puzzle-piece"></i>
              <p>Modules</p>
          </a>
        </li>

        <li class="nav-header mt-3">MORE SETTINGS</li>

        <li class="nav-item">
          <a href="custom_links.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "custom_links.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-link"></i>
            <p>Custom Links</p>
          </a>
        </li>
      
        <li class="nav-item">
          <a href="logs.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "logs.php") { echo "active"; } ?>">
            <i class="nav-icon far fa-eye"></i>
            <p>Logs</p>
          </a>
        </li>
      </ul>
    
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
