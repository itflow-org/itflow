<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

  <a class="brand-link pb-1 mt-1" href="clients.php">    
    <p class="h6"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i> Back | <strong>Administration</strong></p>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav>

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

        <li class="nav-header">ACCESS</li>

        <li class="nav-item">
          <a href="users.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "users.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Users</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_api.php") { echo "active"; } ?>"
             href="settings_api.php">
              <i class="nav-icon fas fa-key"></i>
              <p>API Keys</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_security.php") { echo "active"; } ?>"
             href="settings_security.php">
            <i class="nav-icon fas fa-shield-alt"></i>
            <p>Security</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="settings_mail_queue.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_mail_queue.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-mail-bulk"></i>
            <p>Mail Queue</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="logs.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "logs.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-history"></i>
            <p>Audit Logs</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_backup.php") { echo "active"; } ?>"
            href="settings_backup.php">
            <i class="nav-icon fas fa-cloud-upload-alt"></i>
            <p>Backup</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_update.php") { echo "active"; } ?>"
            href="settings_update.php">
            <i class="nav-icon fas fa-download"></i>
            <p>Update</p>
          </a>
        </li>
        
        <li class="nav-item">
          <a href="settings_debug.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_debug.php") { echo "active"; } ?>">
            <i class="nav-icon fa fa-bug"></i>
            <p>Debug</p>
          </a>
        </li>

        <li class="nav-header mt-3">TAGS & CATEGORIES</li>

        <li class="nav-item">
          <a href="settings_taxes.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_taxes.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-balance-scale"></i>
            <p>Taxes</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="categories.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "categories.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-list-ul"></i>
            <p>Categories</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="settings_custom_fields.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_custom_fields.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-th-list"></i>
            <p>Custom Fields</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="settings_tags.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_tags.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tags"></i>
            <p>Tags</p>
          </a>
        </li>

        <li class="nav-header mt-3">TEMPLATES</li>

        <li class="nav-item">
          <a href="settings_vendor_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_vendor_templates.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-building"></i>
            <p>Vendor Templates</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="settings_software_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_software_templates.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-rocket"></i>
            <p>License Templates</p>
          </a>
        </li>

        <li class="nav-header mt-3">SETTINGS</li>

        <li class="nav-item">
          <a href="settings_company.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_company.php") { echo "active"; } ?>">
            <i class="nav-icon fa fa-briefcase"></i>
            <p>Company Details</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="settings_localization.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_localization.php") { echo "active"; } ?>">
            <i class="nav-icon fa fa-globe"></i>
            <p>Localization</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_theme.php") { echo "active"; } ?>"
            href="settings_theme.php">
            <i class="nav-icon fa fa-paint-brush"></i>
            <p>Theme</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_mail.php") { echo "active"; } ?>"
            href="settings_mail.php">
            <i class="nav-icon far fa-envelope"></i>
            <p>Mail</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_alerts.php") { echo "active"; } ?>"
            href="settings_alerts.php">
            <i class="nav-icon far fa-bell"></i>
            <p>Alerts</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_defaults.php") { echo "active"; } ?>"
            href="settings_defaults.php">
            <i class="nav-icon fas fa-cogs"></i>
            <p>Defaults</p>
          </a>
        </li>

        <?php if ($config_module_enable_accounting) { ?>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_invoice.php") { echo "active"; } ?>"
            href="settings_invoice.php">
            <i class="nav-icon fas fa-file-invoice"></i>
            <p>Invoice</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_quote.php") { echo "active"; } ?>"
            href="settings_quote.php">
            <i class="nav-icon fas fa-comment-dollar"></i>
            <p>Quote</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_online_payment.php") { echo "active"; } ?>"
            href="settings_online_payment.php">
            <i class="nav-icon far fa-credit-card"></i>
            <p>Online Payment</p>
          </a>
        </li>

        <?php } ?>

        <?php if ($config_module_enable_ticketing) { ?>
        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_ticket.php") { echo "active"; } ?>"
            href="settings_ticket.php">
            <i class="nav-icon fas fa-life-ring"></i>
            <p>Ticket</p>
          </a>
        </li>
        <?php } ?>

        <li class="nav-item">
          <a href="settings_integrations.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_integrations.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-plug"></i>
            <p>Integrations</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_telemetry.php") { echo "active"; } ?>"
            href="settings_telemetry.php">
            <i class="nav-icon fas fa-satellite-dish"></i>
            <p>Telemetry</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_modules.php") { echo "active"; } ?>"
             href="settings_modules.php">
              <i class="nav-icon fas fa-cube"></i>
              <p>Modules</p>
          </a>
        </li>
      </ul>
    </nav>
    <!-- /.sidebar-menu -->

    <div class="sidebar-custom mb-3">
    
    </div>
  
  </div>
  <!-- /.sidebar -->
</aside>
