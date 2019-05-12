<!-- Sidebar -->
<ul class="sidebar navbar-nav d-print-none">
  <li class="nav-item">
    <h2 class="text-white text-center my-3">Setup</h2>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "setup.php") { echo "active"; } ?>">
    <a class="nav-link" href="setup.php">
      <i class="fas fa-fw fa-database mx-2"></i>
      <span>Database</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "clients.php") { echo "active"; } ?>">
    <a class="nav-link" href="setup.php">
      <i class="fas fa-fw fa-user mx-2"></i>
      <span>User</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "calendar_events.php") { echo "active"; } ?>">
    <a class="nav-link" href="calendar_events.php">
      <i class="fas fa-fw fa-building mx-2"></i>
      <span>Company</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "products.php") { echo "active"; } ?>">
    <a class="nav-link" href="products.php">
      <i class="fas fa-fw fa-university mx-2"></i>
      <span>Account</span></a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "vendors.php") { echo "active"; } ?>">
    <a class="nav-link" href="vendors.php">
      <i class="fas fa-fw fa-th mx-2"></i>
      <span>Categories</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "invoices.php") { echo "active"; } ?>">
    <a class="nav-link" href="invoices.php">
      <i class="fas fa-fw fa-envelope mx-2"></i>
      <span>Mail</span>
    </a>
  </li>
</ul>