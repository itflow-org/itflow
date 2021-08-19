<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-3">

      <div class="dropdown mb-4 ml-3">
        <a class="" href="#" data-toggle="dropdown">
          <h3><?php echo $session_company_name; ?> <small><i class="fa fa-caret-down"></i></small></h3>
        </a>

        <ul class="dropdown-menu">
          
          <?php
          
          $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id IN ($session_permission_companies)");
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

      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">
        <li class="nav-header mt-3">SETTINGS</li>
        
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
    
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
