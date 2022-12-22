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
          <h3 class="brand-text text-light mb-0"><?php echo htmlentities($session_company_name); ?> <small><i class="fa fa-caret-down"></i></small></h3>
        </a>

        <ul class="dropdown-menu">
          
          <?php
          
          while($row = mysqli_fetch_array($sql)){

            $company_id = $row['company_id'];
            $company_name = htmlentities($row['company_name']);

          ?>
          
            <li><a class="dropdown-item text-dark" href="post.php?switch_company=<?php echo $company_id; ?>"><?php echo $company_name; ?><?php if($company_id == $session_company_id){ echo "<i class='fa fa-check text-secondary ml-2'></i>"; } ?></a></li>

          <?php

          }
          
          ?>

        </ul>
      </div>

      <?php }else{ ?>

        <h2 class="brand-text text-light my-3"><i class="fas fa-cloud"></i> <?php echo htmlentities($session_company_name); ?></h2>

      <?php } ?>

      <form class="form-inline mb-3" action="global_search.php">
        <div class="input-group">
          <input class="form-control form-control-sidebar" type="search" placeholder="Search" name="query" value="<?php if(isset($_GET['query'])){ echo htmlentities($_GET['query']); } ?>">
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
        
        <?php if($session_user_role >= 2 && $config_module_enable_ticketing == 1){ ?>

        <li class="nav-header mt-3">SUPPORT</li>
        <li class="nav-item">
          <a href="tickets.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "tickets.php" || basename($_SERVER["PHP_SELF"]) == "ticket.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-life-ring"></i>
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

        <?php } ?>

        <?php if($session_user_role == 1 OR $session_user_role == 3 && $config_module_enable_accounting == 1){ ?>

        <li class="nav-header mt-3">SALES</li>
        <li class="nav-item">
          <a href="quotes.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "quotes.php" || basename($_SERVER["PHP_SELF"]) == "quote.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-file-invoice"></i>
            <p>Quotes</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="invoices.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "invoices.php" || basename($_SERVER["PHP_SELF"]) == "invoice.php") { echo "active"; } ?>">
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
          <a href="recurring_invoices.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "recurring_invoices.php") { echo "active"; } ?>">
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
          <a href="transfers.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "transfers.php") { echo "active"; } ?>">
            <i class="nav-icon fas fa-exchange-alt"></i>
            <p>Transfers</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="report_income_summary.php" class="nav-link <?php if(basename($_SERVER["PHP_SELF"]) == "report_income_summary.php") { echo "active"; } ?>">
            <i class="fas fa-chart-bar nav-icon"></i>
            <p>Reports</p>
            <i class="fas fa-angle-right nav-icon float-right"></i>
          </a>
        </li>

        <?php } ?>

        <?php if($session_user_role == 3){ ?>

        <li class="nav-item mt-3">
          <a href="users.php" class="nav-link">
            <i class="nav-icon fas fa-cog"></i>
            <p>Settings</p>
            <i class="fas fa-angle-right nav-icon float-right"></i>
          </a>
        </li>

        <?php } ?>

      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->

</aside>
