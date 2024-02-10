<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

  <a class="brand-link pb-1 mt-1" href="clients.php">
    <p class="h6"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i> Back | <strong>Settings</strong></p>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav>

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

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
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_security.php") { echo "active"; } ?>"
             href="settings_security.php">
            <i class="nav-icon fas fa-shield-alt"></i>
            <p>Security</p>
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
          <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_notifications.php") { echo "active"; } ?>"
            href="settings_notifications.php">
            <i class="nav-icon far fa-bell"></i>
            <p>Notifications</p>
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
          <a href="settings_ai.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_ai.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-robot"></i>
            <p>AI</p>
          </a>
        </li>

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

    <div class="mb-3"></div>

  </div>
  <!-- /.sidebar -->
</aside>
