<!-- Sidebar -->
<ul class="sidebar navbar-nav d-print-none">
  <li class="nav-item">
    <h2 class="text-white text-center my-3"><?php echo $config_company_name; ?></h2>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "dashboard.php") { echo "active"; } ?>">
    <a class="nav-link" href="dashboard.php">
      <i class="fas fa-fw fa-tachometer-alt mx-2"></i>
      <span>Dashboard</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "clients.php") { echo "active"; } ?>">
    <a class="nav-link" href="clients.php">
      <i class="fas fa-fw fa-users mx-2"></i>
      <span>Clients</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "calendar_events.php") { echo "active"; } ?>">
    <a class="nav-link" href="calendar_events.php">
      <i class="fas fa-fw fa-calendar mx-2"></i>
      <span>Calendar</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "tickets.php") { echo "active"; } ?>">
    <a class="nav-link" href="tickets.php">
      <i class="fas fa-fw fa-tags mx-2"></i>
      <span>Tickets</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "products.php") { echo "active"; } ?>">
    <a class="nav-link" href="products.php">
      <i class="fas fa-fw fa-box mx-2"></i>
      <span>Products</span></a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "vendors.php") { echo "active"; } ?>">
    <a class="nav-link" href="vendors.php">
      <i class="fas fa-fw fa-building mx-2"></i>
      <span>Vendors</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "invoices.php") { echo "active"; } ?>">
    <a class="nav-link" href="invoices.php">
      <i class="fas fa-fw fa-file-invoice-dollar mx-2"></i>
      <span>Invoices</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "quotes.php") { echo "active"; } ?>">
    <a class="nav-link" href="quotes.php">
      <i class="fas fa-fw fa-file-invoice mx-2"></i>
      <span>Quotes</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "expenses.php") { echo "active"; } ?>">
    <a class="nav-link" href="expenses.php">
      <i class="fas fa-fw fa-shopping-cart mx-2"></i>
      <span>Expenses</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "payments.php") { echo "active"; } ?>">
    <a class="nav-link" href="payments.php">
      <i class="fas fa-fw fa-credit-card mx-2"></i>
      <span>Payments</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "recurring.php") { echo "active"; } ?>">
    <a class="nav-link" href="recurring.php">
      <i class="fas fa-fw fa-copy mx-2"></i>
      <span>Recurring</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "accounts.php") { echo "active"; } ?>">
    <a class="nav-link" href="accounts.php">
      <i class="fas fa-fw fa-piggy-bank mx-2"></i>
      <span>Accounts</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "transfers.php") { echo "active"; } ?>">
    <a class="nav-link" href="transfers.php">
      <i class="fas fa-fw fa-exchange-alt mx-2"></i>
      <span>Transfers</span>
    </a>
  </li>
  <li class="nav-item <?php if(basename($_SERVER["REQUEST_URI"]) == "trips.php") { echo "active"; } ?>">
    <a class="nav-link" href="trips.php">
      <i class="fas fa-fw fa-bicycle mx-2"></i>
      <span>Trips</span>
    </a>
  </li>
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="pagesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <i class="fas fa-fw fa-chart-area mx-2"></i>
      <span>Reports</span>
    </a>
    <div class="dropdown-menu" aria-labelledby="pagesDropdown">
      <a class="dropdown-item" href="report_income_summary.php">Income Summary</a>
      <a class="dropdown-item" href="report_expense_summary.php">Expense Summary</a>
      <a class="dropdown-item" href="report_profit_loss.php">Profit & Loss</a>
    </div>
  </li>

  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="pagesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <i class="fas fa-fw fa-cog mx-2"></i>
      <span>Settings</span>
    </a>
    <div class="dropdown-menu" aria-labelledby="pagesDropdown">
      <a class="dropdown-item" href="settings-general.php">General</a>
      <a class="dropdown-item" href="categories.php">Categories</a>
      <a class="dropdown-item" href="users.php">Users</a>
    </div>
  </li>
</ul>