<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-2">

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">
        
        <li class="nav-item mb-2">
          <a href="dashboard_financial.php" class="nav-link">
            <i class="nav-icon fas fa-angle-left"></i>
            <p><strong>Reports</strong></p>
          </a>
        </li>

        <li class="nav-header mt-2">FINANCIAL</li>
        
        <li class="nav-item">
          <a href="report_income_summary.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_income_summary.php") { echo "active"; } ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Income</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="report_income_by_client.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_income_by_client.php") { echo "active"; } ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Income By Client</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="report_expense_summary.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_expense_summary.php") { echo "active"; } ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Expense</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="report_expense_by_vendor.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_expense_by_vendor.php") { echo "active"; } ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Expense By Vendor</p>
          </a>
        </li>
         <li class="nav-item">
          <a href="report_tax_summary.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_tax_summary.php") { echo "active"; } ?>">
            <i class="fas fa-percent nav-icon"></i>
            <p>Tax Summary</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="report_profit_loss.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_profit_loss.php") { echo "active"; } ?>">
            <i class="fas fa-balance-scale nav-icon"></i>
            <p>Profit & Loss</p>
          </a>
        </li>

      </ul>
    
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
