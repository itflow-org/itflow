<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-2">

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

        <li class="nav-item mb-2">
          <a href="dashboard_financial.php" class="nav-link">
            <i class="nav-icon fas fa-arrow-left"></i>
            <p class="h5">Back | <strong>Settings</strong></p>
          </a>
        </li>

        <li class="nav-header">ACCESS</li>

        <li class="nav-item">
          <a href="users.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "users.php") { echo "active"; } ?>">
            <i class="nav-icon far fa-user"></i>
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

        <li class="nav-header mt-3">TAGS & CATEGORIES</li>

        <li class="nav-item">
          <a href="settings_taxes.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_taxes.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-balance-scale"></i>
            <p>Taxes</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="categories.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "categories.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-list"></i>
            <p>Categories</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="settings_custom_fields.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_custom_fields.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-list"></i>
            <p>Custom Fields</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="settings_tags.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_tags.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tag"></i>
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
            <i class="nav-icon far fa-building"></i>
            <p>Company</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_theme.php") { echo "active"; } ?>"
            href="settings_theme.php">
            <i class="nav-icon fa fa-palette"></i>
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

        <?php if ($config_module_enable_accounting) { ?>
        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_defaults.php") { echo "active"; } ?>"
            href="settings_defaults.php">
            <i class="nav-icon fas fa-cog"></i>
            <p>Defaults</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_invoice.php") { echo "active"; } ?>"
            href="settings_invoice.php">
            <i class="nav-icon fas fa-file"></i>
            <p>Invoice</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_quote.php") { echo "active"; } ?>"
            href="settings_quote.php">
            <i class="nav-icon far fa-file"></i>
            <p>Quote</p>
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
            <i class="nav-icon fas fa-puzzle-piece"></i>
            <p>Integrations</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_update.php") { echo "active"; } ?>"
            href="settings_update.php">
            <i class="nav-icon fas fa-arrow-alt-circle-up"></i>
            <p>Update</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_telemetry.php") { echo "active"; } ?>"
            href="settings_telemetry.php">
            <i class="nav-icon fas fa-broadcast-tower"></i>
            <p>Telemetry</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_backup.php") { echo "active"; } ?>"
            href="settings_backup.php">
            <i class="nav-icon fas fa-database"></i>
            <p>Backup</p>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_modules.php") { echo "active"; } ?>"
             href="settings_modules.php">
              <i class="nav-icon fas fa-puzzle-piece"></i>
              <p>Modules</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="logs.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "logs.php") { echo "active"; } ?>">
            <i class="nav-icon far fa-eye"></i>
            <p>Audit Logs</p>
          </a>
        </li>
      </ul>

    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
