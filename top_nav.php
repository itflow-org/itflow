<nav class="navbar navbar-expand navbar-dark bg-primary static-top">

  

  <button class="btn btn-link btn-sm text-white order-1 order-sm-0" id="sidebarToggle" href="#">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Navbar Search -->
  <form class="d-none d-md-inline-block form-inline ml-auto mr-0 mr-md-3 my-2 my-md-0" action="global_search.php">
    <div class="input-group">
      <input type="text" class="form-control" placeholder="Search for..." name="query" value="<?php if(isset($_GET['query'])){ echo $_GET['query']; } ?>">
      <div class="input-group-append">
        <button class="btn btn-dark" type="button">
          <i class="fas fa-search"></i>
        </button>
      </div>
    </div>
  </form>

  <!-- Navbar -->
  <ul class="navbar-nav ml-auto ml-md-0">
    <li class="nav-item dropdown no-arrow mx-2">
      <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <?php if($num_alerts > 0){ ?> <span class="badge badge-danger"><?php echo $num_alerts; ?></span> <?php } ?>
        <i class="fas fa-bell mt-2"></i>
        
      </a>
      <div class="dropdown-menu dropdown-menu-right" aria-labelledby="alertsDropdown">
        <a class="dropdown-item" href="alerts.php">New Alerts</a>
        <a class="dropdown-item" href="alerts.php">Acknowledged</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="post.php?ack_all_alerts">Acknowledge All</a>
      </div>
    </li>
    <li class="nav-item dropdown no-arrow">
      <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <img height="32" width="32" src="<?php echo "$session_avatar"; ?>" class="img-fluid rounded-circle"> <strong><?php echo "$session_name"; ?></strong>
      </a>
      <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">   
        <center>
          <img height="128" width="128" src="<?php echo "$session_avatar"; ?>" class="img-fluid rounded-circle">
        </center>
        <a class="dropdown-item" href="settings-user.php"><i class="fa fa-fw fa-cog"></i> Settings</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="logout.php"><i class="fa fa-fw fa-sign-out-alt"></i> Logout</a>
      </div>
    </li>
  </ul>

</nav>