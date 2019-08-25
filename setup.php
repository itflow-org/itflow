<?php

include("config.php");
include("functions.php");

if(!isset($config_enable_setup)){
  $config_enable_setup = 1;
}

if($config_enable_setup == 0){
  header("Location: login.php");
}

$states_array = array(
    'AL'=>'Alabama',
    'AK'=>'Alaska',
    'AZ'=>'Arizona',
    'AR'=>'Arkansas',
    'CA'=>'California',
    'CO'=>'Colorado',
    'CT'=>'Connecticut',
    'DE'=>'Delaware',
    'DC'=>'District of Columbia',
    'FL'=>'Florida',
    'GA'=>'Georgia',
    'HI'=>'Hawaii',
    'ID'=>'Idaho',
    'IL'=>'Illinois',
    'IN'=>'Indiana',
    'IA'=>'Iowa',
    'KS'=>'Kansas',
    'KY'=>'Kentucky',
    'LA'=>'Louisiana',
    'ME'=>'Maine',
    'MD'=>'Maryland',
    'MA'=>'Massachusetts',
    'MI'=>'Michigan',
    'MN'=>'Minnesota',
    'MS'=>'Mississippi',
    'MO'=>'Missouri',
    'MT'=>'Montana',
    'NE'=>'Nebraska',
    'NV'=>'Nevada',
    'NH'=>'New Hampshire',
    'NJ'=>'New Jersey',
    'NM'=>'New Mexico',
    'NY'=>'New York',
    'NC'=>'North Carolina',
    'ND'=>'North Dakota',
    'OH'=>'Ohio',
    'OK'=>'Oklahoma',
    'OR'=>'Oregon',
    'PA'=>'Pennsylvania',
    'RI'=>'Rhode Island',
    'SC'=>'South Carolina',
    'SD'=>'South Dakota',
    'TN'=>'Tennessee',
    'TX'=>'Texas',
    'UT'=>'Utah',
    'VT'=>'Vermont',
    'VA'=>'Virginia',
    'WA'=>'Washington',
    'WV'=>'West Virginia',
    'WI'=>'Wisconsin',
    'WY'=>'Wyoming'
);

$_SESSION['alert_message'] = '';
$_SESSION['alert_type'] = "warning";

if(isset($_POST['add_database'])){

  $host = $_POST['host'];
  $database = $_POST['database'];
  $username = $_POST['username'];
  $password = $_POST['password'];

  $myfile = fopen("config.php", "w");

  $txt = "<?php\n\n";

  fwrite($myfile, $txt);

  $txt = "\$dbhost = \"$host\";\n\$dbusername = \"$username\";\n\$dbpassword = \"$password\";\n\$database = \"$database\";\n\n";

  fwrite($myfile, $txt);

  $txt = "\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database);\n\n";

  fwrite($myfile, $txt);

  $txt = "\$config_app_name = 'IT CRM';\n";

  fwrite($myfile, $txt);

  fclose($myfile);

  include("config.php");

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
        mysqli_query($mysqli,$templine);
        // Reset temp variable to empty
        $templine = '';
    }
  }

  $_SESSION['alert_message'] = "Database successfully added";

  header("Location: setup.php?user");

}

if(isset($_POST['add_user'])){

  include("config.php");

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

  header("Location: setup.php?company");

}

if(isset($_POST['add_company_settings'])){

  include("config.php");

  $sql = mysqli_query($mysqli,"SELECT user_id FROM users");
  $row = mysqli_fetch_array($sql);
  $user_id = $row['user_id'];

  $config_company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_name']));
  $config_company_address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_address']));
  $config_company_city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_city']));
  $config_company_state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_state']));
  $config_company_zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_zip']));
  $config_company_phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_phone']));
  $config_company_phone = preg_replace("/[^0-9]/", '',$config_company_phone);
  $config_company_site = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_site']));
  $config_api_key = keygen();
  $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

  mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$config_company_name', company_created_at = NOW()");

  $company_id = mysqli_insert_id($mysqli);

  mkdir("uploads/clients/$company_id");
  mkdir("uploads/expenses/$company_id");
  mkdir("uploads/settings/$company_id");
  mkdir("uploads/tmp/$company_id");

  mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $user_id, company_id = $company_id");
 
  mysqli_query($mysqli,"INSERT INTO settings SET company_id = $company_id, config_company_name = '$config_company_name', config_company_address = '$config_company_address', config_company_city = '$config_company_city', config_company_state = '$config_company_state', config_company_zip = '$config_company_zip', config_company_phone = $config_company_phone, config_company_site = '$config_company_site', config_start_page = 'dashboard.php', config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_api_key = '$config_api_key', config_recurring_auto_send_invoice = 1, config_default_net_terms = 7, config_send_invoice_reminders = 0, config_enable_cron = 0, config_ticket_next_number = 1, config_base_url = '$config_base_url'");

  //Create Some Data

  mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', account_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Office Supplies', category_type = 'Expense', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Travel', category_type = 'Expense', category_color = 'red', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Advertising', category_type = 'Expense', category_color = 'green', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Service', category_type = 'Income', category_color = 'orange', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Cash', category_type = 'Payment Method', category_color = 'purple', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Check', category_type = 'Payment Method', category_color = 'brown', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = 'Default', calendar_color = 'blue', calendar_created_at = NOW(), company_id = $company_id");

  $myfile = fopen("config.php", "a");

  $txt = "\$config_enable_setup = 0;\n\n";

  fwrite($myfile, $txt);

  $txt = "?>\n";

  fwrite($myfile, $txt);

  fclose($myfile);

  header("Location: login.php");

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
  <link href="vendor/bootstrap-select/css/bootstrap-select.min.css" rel="stylesheet" type="text/css">
  
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
    <ul class="sidebar navbar-nav">
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
        <?php include("config.php"); ?>
        
        <?php 
        //Alert Feedback
        if(!empty($_SESSION['alert_message'])){
        ?>
          <div class="alert alert-info" id="alert">
            <?php echo $_SESSION['alert_message']; ?>
            <button class='close' data-dismiss='alert'>&times;</button>
          </div>
          <?php
          $_SESSION['alert_type'] = '';
          $_SESSION['alert_message'] = '';
        }
        ?>

        <?php if(isset($_GET['database'])){ ?>

          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mt-1"><i class="fa fa-fw fa-database"></i> Setup Database</h6>
            </div>
            <div class="card-body">
              <form class="p-3" method="post" autocomplete="off">
                
                <div class="form-group">
                  <label>Database</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-database"></i></span>
                    </div>
                    <input type="text" class="form-control" name="database" placeholder="Name of the database" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Username</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="username" placeholder="Username to access the database" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Password</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" name="password" placeholder="Enter the password" required>
                  </div>
                </div>

                <div class="form-group mb-5">
                  <label>Host</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                    </div>
                    <input type="text" class="form-control" name="host" value="localhost" placeholder="Hostname of the server" required>
                  </div>
                </div>

                <hr>
                <button type="submit" name="add_database" class="btn btn-primary">Save</button>
              </form>
            </div>
          </div>

        <?php }elseif(isset($_GET['user'])){ ?>

          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mt-1"><i class="fa fa-fw fa-user"></i> Create your first user</h6>
            </div>
            <div class="card-body">
        
              <form class="p-3" method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="form-group">
                  <label>Name</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="name" placeholder="Full Name" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Email</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Password</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
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

        <?php }elseif(isset($_GET['company'])){ ?>

          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mt-1"><i class="fa fa-fw fa-building"></i> Company Settings</h6>
            </div>
            <div class="card-body">
              <form class="p-3" method="post"  autocomplete="off">
                <div class="form-group">
                  <label>Company Name</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_name" placeholder="Company Name" required>  
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Address</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_address" placeholder="Street Address">
                  </div>
                </div>

                <div class="form-group">
                  <label>City</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_city" placeholder="City">
                  </div>
                </div>

                <div class="form-group">
                  <label>State</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                    </div>
                    <select class="form-control selectpicker show-tick" data-live-search="true" name="config_company_state">
                      <option value="">Select a state...</option>
                        <?php foreach($states_array as $state_abbr => $state_name) { ?>
                        <option value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                        <?php } ?>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label>Zip</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_zip" placeholder="Zip Code" data-inputmask="'mask': '99999'">
                  </div>
                </div>

                <div class="form-group">
                  <label>Phone</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'"> 
                  </div>
                </div>

                <div class="form-group mb-5">
                  <label>Website</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                    </div>
                    <input type="text" class="form-control" name="config_company_site" placeholder="Website address">
                  </div>
                </div>
                
                <hr>
                
                <button type="submit" name="add_company_settings" class="btn btn-primary">Save</button>
                  
              </form>
            </div>
          </div>

        <?php }else{ ?>

          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mt-1"><i class="fa fa-fw fa-download"></i> Start Install <?php echo $database; ?></h6>
            </div>
            <div class="card-body">
              <p>Click on the install button to start the install process, you must create a database before proceeding</p>
              <p>This process will accomplish the following</p>
              <ul class="mb-4">
                <li>Create a config.php</li>
                <li>Creates the following expense cataegories (Office Supplies, Advertising, Travel)</li>
                <li>Creates the following payment methods (Cash, Check)</li>
                <li>Creates an account named Cash</li>
                <li>Creates an income category</li>
              </ul>
              <p>After install add cron.php to your cron and set it to run once everyday at 12AM. This is so recurring invoices will automatically be sent out and created. This will also trigger late payment reminders, along with alerts such as domains expiration, etc.</p>
              <p>An API is present to allow integration with other third pary apps. An API Key will be auto generated, and can be changed at a later date. The API will give you the following capabilities</p>
              <ul class="mb-4">
                <li>Address book XML for VOIP Phones</li>
                <li>Caller ID Lookup</li>
                <li>Get List of Emails in CSV to export to a mailing list</li>
                <li>Get Balance, can be useful for customer's to get balances by phone</li>
              </ul>
              <center><a href="?database" class="btn btn-lg btn-primary mb-5">Install</a></center>
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
  <script src='vendor/bootstrap-select/js/bootstrap-select.min.js'></script>
  <script src='vendor/Inputmask/dist/inputmask.min.js'></script>
  <script src='vendor/Inputmask/dist/bindings/inputmask.binding.js'></script>

  <!-- Custom js-->
  <script src="js/app.js"></script>

</body>

</html>