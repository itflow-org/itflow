<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-3">
      <?php
      $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id IN ($session_permission_companies)");
      
      if(mysqli_num_rows($sql) > 1){ 

      ?>

      <div class="dropdown mb-4 ml-3">
        <a class="" href="#" data-toggle="dropdown">
          <h3 class="text-light"><?php echo $session_company_name; ?> <small><i class="fa fa-caret-down"></i></small></h3>
        </a>

        <ul class="dropdown-menu">
          
          <?php
          
          while($row = mysqli_fetch_array($sql)){

            $company_id = $row['company_id'];
            $company_name = $row['company_name'];

          ?>
          
            <li><a class="dropdown-item text-dark" href="post.php?switch_company=<?php echo $company_id; ?>"><?php echo $company_name; ?><?php if($company_id == $session_company_id){ echo "<i class='fa fa-check text-secondary ml-2'></i>"; } ?></a></li>

          <?php

          }
          
          ?>

        </ul>
      </div>

      <?php }else{ ?>

        <h3 class="mb-4 ml-3 text-light"><?php echo $session_company_name; ?></h3>

      <?php } ?>

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "dashboard.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>
        
        <li class="nav-item">
          <a href="clients.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "clients.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Clients</p>
          </a>
        </li>
        
        <?php if($session_permission_level > 2){ ?>

        <li class="nav-header mt-3">SUPPORT</li>
        <li class="nav-item">
          <a href="tickets.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "tickets.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tags"></i>
            <p>Tickets</p>
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

        <li class="nav-item">
          <a href="campaigns.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "campaigns.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-envelope"></i>
            <p>Mail List</p>
          </a>
        </li>

        <?php } ?>

        <?php if($session_permission_level == 1 OR $session_permission_level > 3){ ?> 

        <li class="nav-header mt-3">SALES</li>
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
            <i class="nav-icon fas fa-sync-alt"></i>
            <p>Recurring</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="products.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "products.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-box"></i>
            <p>Products</p>
          </a>
        </li>
        <li class="nav-header mt-3">ACCOUNTING</li>
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
          <a href="assets.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "assets.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-desktop"></i>
            <p>Assets</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="trips.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "trips.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-route"></i>
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
              <a href="report_tax_summary.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_tax_summary.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Tax Summary</p>
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

        <?php } ?>

        <?php if($session_permission_level > 3){ ?>

        <li class="nav-header mt-3">SETTINGS</li>
        
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
              <a href="custom_links.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "custom_links.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Custom Links</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="taxes.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "taxes.php") { echo "active"; } ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Taxes</p>
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

        <?php } ?>

        <?php
        
        $sql = mysqli_query($mysqli,"SELECT * FROM custom_links WHERE company_id = $session_company_id");

        if(mysqli_num_rows($sql) > 0){

        ?>

        <li class="nav-header mt-3">EXTERNAL LINKS</li>

        <?php
          
          $sql = mysqli_query($mysqli,"SELECT * FROM custom_links WHERE company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){

            $custom_link_id = $row['custom_link_id'];
            $custom_link_name = $row['custom_link_name'];
            $custom_link_icon = $row['custom_link_icon'];
            if(empty($custom_link_icon)){
              $custom_link_icon_display = "far fa-circle";
            }else{
              $custom_link_icon_display = $custom_link_icon;
            }
            $custom_link_url = $row['custom_link_url'];

          ?>
          
            <li class="nav-item">
              <a href="//<?php echo $custom_link_url; ?>" target="_blank" class="nav-link">
                <i class="nav-icon fas fa-<?php echo $custom_link_icon_display; ?>"></i>
                <p><?php echo $custom_link_name; ?></p>
              </a>
            </li>

          <?php

          }
          
        }
          ?>

      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
