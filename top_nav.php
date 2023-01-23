<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-<?php echo $config_theme; ?> navbar-dark">

  <!-- Left navbar links -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" data-enable-remember="TRUE" href="#"><i class="fas fa-bars"></i></a>
    </li>
  </ul>

  <!-- Right navbar links -->

  <ul class="navbar-nav ml-auto">

    <!-- SEARCH FORM -->
    <form class="form-inline" action="global_search.php">
      <div class="input-group input-group-sm">
        <input class="form-control form-control-navbar" type="search" placeholder="Search" name="query" value="<?php if (isset($_GET['query'])) { echo htmlentities($_GET['query']); } ?>">
        <div class="input-group-append">
          <button class="btn btn-navbar" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </div>
    </form>

    <!-- Notifications -->
    <li class="nav-item">
      <a class="nav-link" href="notifications.php">
        <i class="fa fa-fw fa-bell mx-1"></i>
        <?php if ($num_notifications > 0) { ?>
        <span class="badge badge-danger navbar-badge"><?php echo $num_notifications; ?></span>
        <?php } ?>
      </a>
    </li>
    
    <li class="nav-item dropdown user-menu">
      <a href="#" class="nav-link" data-toggle="dropdown">
        <?php if (empty($session_avatar)) { ?>
        	<i class="fa fa-fw fa-user"></i>
        <?php }else{ ?>
        <img src="<?php echo "uploads/users/$session_user_id/$session_avatar"; ?>" class="user-image img-circle">
        <?php } ?>
        <span class="d-none d-md-inline dropdown-toggle"><?php echo htmlentities($session_name); ?></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <!-- User image -->
        <li class="user-header bg-gray-dark">
          <?php if (empty($session_avatar)) { ?>
          	<i class="fas fa-user-circle fa-6x"></i>
          <?php }else{ ?>
          
          	<img src="<?php echo "uploads/users/$session_user_id/$session_avatar"; ?>" class="img-circle">
					<?php } ?>
          <p>
            <?php echo htmlentities($session_name); ?>
            <small><?php echo htmlentities($session_user_role_display); ?></small>
          </p>
        </li>
        <!-- Menu Footer-->
        <li class="user-footer">
          <a href="user_profile.php" class="btn btn-default btn-flat">Profile</a>
          <a href="post.php?logout" class="btn btn-default btn-flat float-right">Sign out</a>
        </li>
      </ul>
    </li>
    
  </ul>
</nav>
<!-- /.navbar -->
