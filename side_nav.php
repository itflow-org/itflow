<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">
  
  <!-- Brand Logo -->
  <a href="index.php" class="brand-link bg-primary">
    <span class="ml-3"><i class="fa fa-network-wired"></i> <?php echo $config_app_name; ?></span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-2">

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "dashboard.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-header">MAIN</li>
        <li class="nav-item">
          <a href="clients.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "clients.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Clients</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="tickets.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "tickets.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tags"></i>
            <p>Tickets</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="products.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "products.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-box"></i>
            <p>Products</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="vendors.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "vendors.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-building"></i>
            <p>Vendors</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="calendar_events.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "calendar_events.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-calendar"></i>
            <p>Calendar</p>
          </a>
        </li>
        <li class="nav-header">INCOME</li>
        <li class="nav-item">
          <a href="quotes.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "quotes.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file-invoice"></i>
            <p>Quotes</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="revenues.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "revenues.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-credit-card"></i>
            <p>Revenues</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="invoices.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "invoices.php" OR basename($_SERVER["PHP_SELF"]) == "invoice.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file-invoice-dollar"></i>
            <p>Invoices</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="recurring.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "recurring.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tags"></i>
            <p>Recurring</p>
          </a>
        </li>
        <li class="nav-header">ACCOUNTING</li>
        <li class="nav-item">
          <a href="payments.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "payments.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-credit-card"></i>
            <p>Payments</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="expenses.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "expenses.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-shopping-cart"></i>
            <p>Expenses</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="trips.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "trips.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-bicycle"></i>
            <p>Trips</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="accounts.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "accounts.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-piggy-bank"></i>
            <p>Accounts</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="transfers.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "transfers.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-exchange-alt"></i>
            <p>Transfers</p>
          </a>
        </li>
        <li class="nav-header">MORE</li>
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-chart-area"></i>
            <p>
              Reports
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="report_income_summary.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_income_summary.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Income</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="report_expense_summary.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_expense_summary.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Expense</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="report_profit_loss.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_profit_loss.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Profit & Loss</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-cog"></i>
            <p>
              Settings
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="settings-general.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-general.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>General</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="categories.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "categories.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Categories</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="users.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "users.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Users</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="companies.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "companies.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Companies</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="logs.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "logs.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Logs</p>
              </a>
            </li>
          </ul>
        </li>

      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>