<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-<?php echo $config_theme; ?> navbar-dark">

  <!-- Left navbar links -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" data-enable-remember="TRUE" href="#"><i class="fas fa-bars"></i></a>
    </li>
  </ul>

   <!-- Center navbar links -->

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
  </ul>

  <!-- Right navbar links -->

  <ul class="navbar-nav ml-auto">

    <!-- New Notifications Dropdown -->
    <?php
    $sql_notifications = mysqli_query($mysqli, "SELECT * FROM notifications LEFT JOIN clients ON notification_client_id = client_id WHERE notification_dismissed_at IS NULL AND (notification_user_id = $session_user_id OR notification_user_id = 0) AND notifications.company_id = $session_company_id ORDER BY notification_id DESC LIMIT 5");
    ?>

    <?php if ($num_notifications > 0) { ?>
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
        <i class="far fa-bell"></i>
        <span class="badge badge-danger navbar-badge"><?php echo $num_notifications; ?></span>
        
      </a>
      <div class="dropdown-menu dropdown-menu-xlg dropdown-menu-right" style="left: inherit; right: 0px;">
        <span class="dropdown-item dropdown-header"><?php echo $num_notifications; ?> Notifications</span>
        <div class="dropdown-divider"></div>
        <?php
        while ($row = mysqli_fetch_array($sql_notifications)) {
            $notification_id = intval($row['notification_id']);
            $notification_type = htmlentities($row['notification_type']);
            $notification = htmlentities($row['notification']);
            $notification_timestamp = htmlentities($row['notification_timestamp']);
        ?>

        <a href="post.php?dismiss_notification=<?php echo $notification_id; ?>" class="dropdown-item">
          <i class="fas fa-bullhorn mr-2"></i> <?php echo $notification; ?>
          <span class="float-right text-muted text-sm"><?php echo $notification_timestamp; ?></span>
        </a>
        
        <?php
        }
        ?>

        <div class="dropdown-divider"></div>
        <a href="notifications.php" class="dropdown-item dropdown-footer text-primary">See All Notifications</a>
        <div class="dropdown-divider"></div>
        <a href="post.php?dismiss_all_notifications" class="dropdown-item dropdown-footer text-success"><i class="fa fa-fw fa-check"></i> Dismiss All Notifications</a>
      </div>
    </li>
    <?php } else { ?>
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
        <i class="far fa-bell"></i>
      </a>
      <div class="dropdown-menu dropdown-menu dropdown-menu-right" style="left: inherit; right: 0px;">
        <span class="dropdown-item dropdown-header">No Notifications</span>
        <div class="dropdown-divider"></div>
        <div class="text-center text-secondary p-3">
          <i class='far fa-fw fa-4x fa-bell-slash'></i>
        </div>
        <div class="dropdown-divider"></div>
        <a href="notifications_dismissed.php" class="dropdown-item dropdown-footer">See Dismissed Notifications</a>
      </div>
    </li>

    <?php } ?>

    <!-- End New Notifications Dropdown -->
    
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
