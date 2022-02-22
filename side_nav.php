<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-3 d-print-none">

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="">
      <?php
      $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id IN ($session_user_company_access)");
      
      if(mysqli_num_rows($sql) > 1){ 

      ?>

      <div class="dropdown brand-link">
        <a class="" href="#" data-toggle="dropdown">
          <h3 class="brand-text text-light mb-0"><?php echo $session_company_name; ?> <small><i class="fa fa-caret-down"></i></small></h3>
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

        <h3 class="brand-text text-light"><?php echo $session_company_name; ?></h3>

      <?php } ?>

      <form class="form-inline mb-2" action="global_search.php">
        <div class="input-group">
          <input class="form-control form-control-sidebar" type="search" placeholder="Search" name="query" value="<?php if(isset($_GET['query'])){ echo $_GET['query']; } ?>">
          <div class="input-group-append">
            <button class="btn btn-sidebar" type="submit">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </div>
      </form>


      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

        <li class="nav-item">
          <a href="dashboard_financial.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "dashboard_financial.php") { echo "active"; } ?>">
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
        
        <?php if($session_user_role > 2){ ?>

        <li class="nav-header mt-3">SUPPORT</li>
        <li class="nav-item">
          <a href="tickets.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "tickets.php" OR basename($_SERVER["PHP_SELF"]) == "ticket.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-tags"></i>
            <p>Tickets</p>
          </a>
        </li>
        <li class="nav-item">
            <a href="scheduled_tickets.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "scheduled_tickets.php") { echo "active"; } ?>">
               <i class="nav-icon fas fa-sync"></i>
                <p>Scheduled Tickets</p>
            </a>
        </li>
        <li class="nav-item">
          <a href="calendar_events.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "calendar_events.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-calendar"></i>
            <p>Calendar</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="campaigns.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "campaigns.php" OR basename($_SERVER["PHP_SELF"]) == "campaign.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-envelope"></i>
            <p>Campaigns</p>
          </a>
        </li>

        <?php } ?>

        <?php if($session_user_role == 1 OR $session_user_role > 2){ ?> 

        <li class="nav-header mt-3">SALES</li>
        <li class="nav-item">
          <a href="quotes.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "quotes.php" OR basename($_SERVER["PHP_SELF"]) == "quote.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file-invoice"></i>
            <p>Quotes</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="invoices.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "invoices.php" OR basename($_SERVER["PHP_SELF"]) == "invoice.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file-invoice-dollar"></i>
            <p>Invoices</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="revenues.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "revenues.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-credit-card"></i>
            <p>Revenues</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="recurring_invoices.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "recurring_invoices.php" OR basename($_SERVER["PHP_SELF"]) == "recurring_invoices.php") { echo "active"; } ?>">
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
          <a href="vendors.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "vendors.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-building"></i>
            <p>Vendors</p>
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

        <li class="nav-item has-treeview <?php if(basename($_SERVER["PHP_SELF"]) == "report_income_summary.php" OR basename($_SERVER["PHP_SELF"]) == "report_expense_summary.php" OR basename($_SERVER["PHP_SELF"]) == "report_tax_summary.php" OR basename($_SERVER["PHP_SELF"]) == "report_profit_loss.php") { echo "menu-open"; } ?>">
          <a href="#" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_income_summary.php" OR basename($_SERVER["PHP_SELF"]) == "report_expense_summary.php" OR basename($_SERVER["PHP_SELF"]) == "report_tax_summary.php" OR basename($_SERVER["PHP_SELF"]) == "report_profit_loss.php") { echo "active"; } ?>">
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

        <?php if($session_user_role > 2){ ?>

        <li class="nav-header mt-3">SETTINGS</li>
        
        <li class="nav-item has-treeview <?php if(basename($_SERVER["PHP_SELF"]) == "settings-general.php" OR basename($_SERVER["PHP_SELF"]) == "categories.php" OR basename($_SERVER["PHP_SELF"]) == "tags.php" OR basename($_SERVER["PHP_SELF"]) == "custom_links.php" OR basename($_SERVER["PHP_SELF"]) == "taxes.php" OR basename($_SERVER["PHP_SELF"]) == "users.php" OR basename($_SERVER["PHP_SELF"]) == "companies.php" OR basename($_SERVER["PHP_SELF"]) == "logs.php") { echo "menu-open"; } ?>">
          <a href="#" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "settings-general.php" OR basename($_SERVER["PHP_SELF"]) == "categories.php" OR basename($_SERVER["PHP_SELF"]) == "tags.php" OR basename($_SERVER["PHP_SELF"]) == "custom_links.php" OR basename($_SERVER["PHP_SELF"]) == "taxes.php" OR basename($_SERVER["PHP_SELF"]) == "users.php" OR basename($_SERVER["PHP_SELF"]) == "companies.php" OR basename($_SERVER["PHP_SELF"]) == "logs.php") { echo "active"; } ?>">
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
              <a href="tags.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "tags.php") { echo "active"; } ?>">
                <i class="fa fa-tag nav-icon"></i>
                <p>Tags</p>
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
                <i class="far fa-user nav-icon"></i>
                <p>Users</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="api_keys.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "api_keys.php") { echo "active"; } ?>">
                <i class="fas fa-key nav-icon"></i>
                <p>API Keys</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="companies.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "companies.php") { echo "active"; } ?>">
                <i class="far fa-building nav-icon"></i>
                <p>Companies</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="logs.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "logs.php") { echo "active"; } ?>">
                <i class="far fa-eye nav-icon"></i>
                <p>Audit Logs</p>
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
