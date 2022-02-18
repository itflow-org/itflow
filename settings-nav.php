<ul class="nav nav-pills nav-fill mb-3">
  
  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-general.php") { echo "active"; } ?>" 
      href="settings-general.php">
      <i class="fa fa-fw fa-2x fa-cog"></i><br>
      General
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-mail.php") { echo "active"; } ?>" 
      href="settings-mail.php">
      <i class="fa fa-fw fa-2x fa-envelope"></i><br>
      Mail
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-defaults.php") { echo "active"; } ?>" 
      href="settings-defaults.php">
      <i class="fa fa-fw fa-2x fa-cog"></i><br>
      Defaults
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-invoice.php") { echo "active"; } ?>" 
      href="settings-invoice.php">
      <i class="fa fa-fw fa-2x fa-file"></i><br>
      Invoice
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-quote.php") { echo "active"; } ?>" 
      href="settings-quote.php">
      <i class="fa fa-fw fa-2x fa-file"></i><br>
      Quote
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-ticket.php") { echo "active"; } ?>" 
      href="settings-ticket.php">
      <i class="fa fa-fw fa-2x fa-tag"></i><br>
      Ticket
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-alerts.php") { echo "active"; } ?>" 
      href="settings-alerts.php">
      <i class="fa fa-fw fa-2x fa-bell"></i><br>
      Alerts
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-online-payment.php") { echo "active"; } ?>" 
      href="settings-online-payment.php">
      <i class="fa fa-fw fa-2x fa-credit-card"></i><br>
      Online Payment
    </a> 
  </li>
 
  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-update.php") { echo "active"; } ?>" 
      href="settings-update.php">
      <i class="fa fa-fw fa-2x fa-wrench"></i><br>
      Update
    </a> 
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if(basename($_SERVER["REQUEST_URI"]) == "settings-backup.php") { echo "active"; } ?>" 
      href="settings-backup.php">
      <i class="fa fa-fw fa-2x fa-database"></i><br>
      Backup
    </a> 
  </li>

</ul>