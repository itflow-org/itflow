<?php

if(isset($_POST['add_database'])){

    $host = $_POST['host'];
    $database = $_POST['database'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $myfile = fopen("dbconnect.php", "w");

    $txt = "<?php\n\n";

    fwrite($myfile, $txt);

    $txt = "\$dbhost = \"$host\";\n\$dbusername = \"$username\";\n\$dbpassword = \"$password\";\n\$database=\"$database\";\n\n";

    fwrite($myfile, $txt);

    $txt = "\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database);\n\n";

    fwrite($myfile, $txt);

    $txt = "include(\"get_settings.php\");\n\n";

    fwrite($myfile, $txt);

    $txt = "?>";

    fwrite($myfile, $txt);

    fclose($myfile);

    $_SESSION['alert_message'] = "Database successful";

    header("setup_post.php?import_database");

}

include("config.php");

if(isset($_POST['import_database'])){

    // Name of the file
    $filename = 'db.sql';
    // Temporary variable, used to store current query
    $templine = '';
    // Read in entire file
    $lines = file($filename);
    // Loop through each line
    foreach ($lines as $line){
        // Skip it if it's a comment
        if(substr($line, 0, 2) == '--' || $line == '')
            continue;

        // Add this line to the current segment
        $templine .= $line;
        // If it has a semicolon at the end, it's the end of the query
        if(substr(trim($line), -1, 1) == ';'){
            // Perform the query
            mysqli_query($mysqli,$templine) or print('Error performing query \'<strong>' . $templine . '\': ' . mysqli_error() . '<br /><br />');
            // Reset temp variable to empty
            $templine = '';
        }
    }
    echo "Tables imported successfully";

    header("setup.php");

}

if(isset($_POST['add_user'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $password = md5(mysqli_real_escape_string($mysqli,$_POST['password']));

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/users/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"INSERT INTO users SET name = '$name', email = '$email', password = '$password', avatar = '$path', created_at = NOW()");

    $_SESSION['alert_message'] = "User added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_company_settings'])){

    $config_company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_name']));
    $config_company_address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_address']));
    $config_company_city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_city']));
    $config_company_state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_state']));
    $config_company_zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_zip']));
    $config_company_phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_phone']));
    $config_company_site = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_site']));
   
    mysqli_query($mysqli,"INSERT INTO settings SET config_company_name = '$config_company_name', config_company_address = '$config_company_address', config_company_city = '$config_company_city', config_company_state = '$config_company_state', config_company_zip = '$config_company_zip', config_company_phone = '$config_company_phone', config_company_site = '$config_company_site'");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>Install CRM</title>

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">

  <!-- Custom Style Sheet -->
  <link href="css/style.css" rel="stylesheet">
  
</head>

<body id="page-top">

  <!-- Top Nav -->
  <nav class="navbar navbar-expand navbar-dark bg-primary static-top">

    <button class="btn btn-link btn-sm text-white order-1 order-sm-0" id="sidebarToggle" href="#">
      <i class="fas fa-bars"></i>
    </button>

    <!-- Navbar -->
    <ul class="navbar-nav ml-auto ml-md-0">
    </ul>

  </nav>

  <div id="wrapper">
    
    <!-- Sidebar -->
    <ul class="sidebar navbar-nav d-print-none">
      <li class="nav-item">
        <h2 class="text-white text-center my-3">Setup</h2>
      </li>
      <li class="nav-item <?php if(isset($_GET['database'])) { echo "active"; } ?>">
        <a class="nav-link" href="?database">
          <i class="fas fa-fw fa-database mx-2"></i>
          <span>Database</span>
        </a>
      </li>
      <li class="nav-item <?php if(isset($_GET['user'])) { echo "active"; } ?>">
        <a class="nav-link" href="?user">
          <i class="fas fa-fw fa-user mx-2"></i>
          <span>User</span>
        </a>
      </li>
      <li class="nav-item <?php if(isset($_GET['company'])) { echo "active"; } ?>">
        <a class="nav-link" href="?company">
          <i class="fas fa-fw fa-building mx-2"></i>
          <span>Company</span>
        </a>
      </li>
    </ul>
    
    <div id="content-wrapper">
      
      <div class="container">
        
        <?php if(isset($_GET['database'])){ ?>
    
          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mt-1"><i class="fa fa-database"></i> Setup Database</h6>
            </div>
            <div class="card-body">
              <form class="p-3" action="setup_post.php" method="post" autocomplete="off">
                
                <div class="form-group">
                  <label>MySQL Host</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-server"></i></span>
                    </div>
                    <input type="text" class="form-control" name="host" placeholder="Usually localhost" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>MySQL Username</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="username" placeholder="Username to access the database" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>MySQL Password</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" name="password" placeholder="Enter the password" required>
                  </div>
                </div>
                
                <div class="form-group mb-5">
                  <label>MySQL Database Name</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-database"></i></span>
                    </div>
                    <input type="text" class="form-control" name="database" placeholder="Name of the database" required>
                  </div>
                </div>
                <hr>
                <button type="submit" name="add_database" class="btn btn-primary">Save</button>
              </form>
            </div>
          </div>

        <?php }?>
        

        <?php if(isset($_GET['user'])){ ?>

          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mt-1"><i class="fa fa-user"></i> Create your first user</h6>
            </div>
            <div class="card-body">
        
              <form class="p-3" action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="form-group">
                  <label>Name</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="name" placeholder="Full Name" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Email</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Password</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" name="password" placeholder="Enter a Password" required>
                  </div>
                </div>
                <div class="form-group mb-5">
                  <label>Avatar</label>
                  <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
                </div>
                <hr>
                <button type="submit" name="add_user" class="btn btn-primary">Save</button>
              </form>
            </div>
          </div>
        <?php } ?>


        <?php if(isset($_GET['company'])){ ?>

          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mt-1"><i class="fa fa-building"></i> Company Settings</h6>
            </div>
            <div class="card-body">
              <form class="p-3" action="post.php" method="post"  autocomplete="off">
                <div class="form-group">
                  <label>Company Name</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-building"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_name" placeholder="Company Name" required>  
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Address</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-map-marker"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_address" placeholder="Street Address">
                  </div>
                </div>

                <div class="form-group">
                  <label>City</label>
                  <input type="text" class="form-control" name="config_company_city" placeholder="City">
                </div>

                <div class="form-group">
                  <label>State</label>
                  <select class="form-control" name="config_company_state">
                    <option value="">Select a state...</option>
                      <?php foreach($states_array as $state_abbr => $state_name) { ?>
                      <option value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                      <?php } ?>
                  </select>
                </div>

                <div class="form-group">
                  <label>Zip</label>
                  <input type="text" class="form-control" name="config_company_zip" placeholder="Zip Code">
                </div>

                <div class="form-group">
                  <label>Phone</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-phone"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'"> 
                  </div>
                </div>

                <div class="form-group mb-5">
                  <label>Website</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-globe"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_site" placeholder="Website address https://">
                  </div>
                </div>
                
                <hr>
                
                <button type="submit" name="add_company_settings" class="btn btn-primary">Save</button>
                  
              </form>
            </div>
          </div>

        <?php } ?>

      </div>
      <!-- /.container-fluid -->

    </div>
    <!-- /.content-wrapper -->

  </div>
  <!-- /#wrapper -->

  <!-- Scroll to Top Button-->
  <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>

  <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

  <!-- Custom scripts for all pages-->
  <script src="js/sb-admin.min.js"></script>

  <!-- Custom js-->
  <script src="js/app.js"></script>

</body>

</html>