<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-primary navbar-dark">

  <!-- Left navbar links -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
    </li>
  </ul>
  
  <!-- SEARCH FORM -->
  <form class="form-inline ml-auto mr-5" action="clients.php">
    <div class="input-group input-group-sm">
      <input class="form-control form-control-navbar" type="search" placeholder="Search" name="q">
      <div class="input-group-append">
        <button class="btn btn-navbar" type="submit">
          <i class="fas fa-search"></i>
        </button>
      </div>
    </div>
  </form>

  <!-- Right navbar links -->
  <ul class="navbar-nav">
    <!-- Notifications -->
    <li class="nav-item">
      <a class="nav-link" href="alerts.php">
        <i class="fas fa-bell"></i>
        <?php if($num_alerts > 0){ ?>
        <span class="badge badge-danger navbar-badge"><?php echo $num_alerts; ?></span>
        <?php } ?>
      </a>
    </li>
    
    <li class="nav-item dropdown user-menu">
      <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
        <img src="<?php echo $session_avatar; ?>" class="user-image img-circle" alt="User Image">
        <span class="d-none d-md-inline"><?php echo $session_name; ?></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <!-- User image -->
        <li class="user-header bg-gray-dark">
          <img src="<?php echo "$session_avatar"; ?>" class="img-circle" alt="User Image">

          <p>
            <?php echo $session_name; ?>
            <small><?php echo $session_company_name; ?></small>
          </p>
        </li>
        
        <!-- Menu Footer-->
        <li class="user-footer">
          <a href="settings-user.php" class="btn btn-default btn-flat">Profile</a>
          <a href="logout.php" class="btn btn-default btn-flat float-right">Sign out</a>
        </li>
      </ul>
    </li>
    
  </ul>
</nav>
<!-- /.navbar -->