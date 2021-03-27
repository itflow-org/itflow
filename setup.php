<?php

include("config.php");
include("functions.php");

if(!isset($config_enable_setup)){
  $config_enable_setup = 1;
}

if($config_enable_setup == 0){
  header("Location: login.php");
}

$countries_array = array(
    'USA',
    'Canada'
);

$currencies_array = array(
    'USD'=>'US Dollars',
    'EUR'=>'Euro',
    'GBP'=>'British Pounds',
    'TRY'=>'Turkish Lira'
);

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
    'WY'=>'Wyoming',
    'ON'=>'Ontario',
    'QC'=>'Quebec',
    'NS'=>'Nova Scotia',
    'NB'=>'New Brunswick',
    'MB'=>'Manitoba',
    'BC'=>'British Columbia',
    'PE'=>'Prince Edward Island',
    'SK'=>'Saskatchewan',
    'AB'=>'Alberta',
    'NL'=>'Newfoundland and Labrador'
);

if(isset($_POST['add_database'])){

  $host = $_POST['host'];
  $database = $_POST['database'];
  $username = $_POST['username'];
  $password = $_POST['password'];
  $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

  $myfile = fopen("config.php", "w");

  $txt = "<?php\n\n";

  fwrite($myfile, $txt);

  $txt = "\$dbhost = \"$host\";\n\$dbusername = \"$username\";\n\$dbpassword = \"$password\";\n\$database = \"$database\";\n\n";

  fwrite($myfile, $txt);

  $txt = "\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database) or die('Database Connection Failed');\n\n";

  fwrite($myfile, $txt);

  $txt = "\$config_app_name = 'ITFlow';\n";

  fwrite($myfile, $txt);

  $txt = "\$config_base_url = '$config_base_url';\n";

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

  $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
  $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
  $password = md5($_POST['password']);

  mysqli_query($mysqli,"INSERT INTO users SET name = '$name', email = '$email', password = '$password', created_at = NOW()");

  $user_id = mysqli_insert_id($mysqli);

  mkdir("uploads/users/$user_id");

  if($_FILES['file']['tmp_name']!='') {
      $path = "uploads/users/$user_id/";
      $path = $path . time() . basename( $_FILES['file']['name']);
      $file_name = basename($path);
      move_uploaded_file($_FILES['file']['tmp_name'], $path);
  }

  mysqli_query($mysqli,"UPDATE users SET avatar = '$path' WHERE user_id = $user_id");
  
  $_SESSION['alert_message'] = "User <strong>$name</strong> created!";

  header("Location: setup.php?company");

}

if(isset($_POST['add_company_settings'])){

  $sql = mysqli_query($mysqli,"SELECT user_id FROM users");
  $row = mysqli_fetch_array($sql);
  $user_id = $row['user_id'];

  $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
  $country = strip_tags(mysqli_real_escape_string($mysqli,$_POST['country']));
  $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
  $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
  $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
  $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
  $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
  $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
  $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));
  $currency_code = strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code']));

  mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_logo = '$path', company_created_at = NOW()");

  $company_id = mysqli_insert_id($mysqli);
  $config_api_key = keygen();
  $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

  mkdir("uploads/clients/$company_id");
  mkdir("uploads/expenses/$company_id");
  mkdir("uploads/settings/$company_id");
  mkdir("uploads/tmp/$company_id");

  if($_FILES['file']['tmp_name']!='') {
    $path = "uploads/settings/$company_id/";
    $path = $path . time() . basename( $_FILES['file']['name']);
    $file_name = basename($path);
    move_uploaded_file($_FILES['file']['tmp_name'], $path);

    mysqli_query($mysqli,"UPDATE companies SET company_logo = '$path' WHERE company_id = $company_id");
  }

  //Create Permissions
  mysqli_query($mysqli,"INSERT INTO permissions SET permission_level = 5, permission_default_company = $company_id, permission_companies = $company_id, user_id = $user_id");
 
  mysqli_query($mysqli,"INSERT INTO settings SET company_id = $company_id, config_default_country = '$country', config_default_currency = '$currency_code', config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_prefix = 'REC-', config_recurring_next_number = 1, config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_api_key = '$config_api_key', config_recurring_auto_send_invoice = 1, config_default_net_terms = 7, config_send_invoice_reminders = 0, config_enable_cron = 0, config_ticket_next_number = 1, config_base_url = '$config_base_url'");

  //Create Some Data

  mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', account_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Office Supplies', category_type = 'Expense', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Travel', category_type = 'Expense', category_color = 'red', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Advertising', category_type = 'Expense', category_color = 'green', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Service', category_type = 'Income', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Cash', category_type = 'Payment Method', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Check', category_type = 'Payment Method', category_color = 'red', category_created_at = NOW(), company_id = $company_id");

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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">

  <title>ITFlow Setup</title>

  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- Custom Style Sheet -->
  <link href="plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css">
  <link href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">
  
</head>

<body class="hold-transition sidebar-mini">

  <div class="wrapper text-sm">

       <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-primary navbar-dark">

      <!-- Left navbar links -->
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
        </li>
      </ul>
      
      <!-- Right navbar links -->
      <ul class="navbar-nav">
      </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
      
      <!-- Brand Logo -->
      <a href="https://itflow.org" class="brand-link">
        <h3 class="brand-text font-weight-light">ITFlow</h3>
      </a>

      <!-- Sidebar -->
      <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav class="mt-2">
          <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
            <li class="nav-item">
              <a href="?database" class="nav-link <?php if(isset($_GET['database'])) { echo "active"; } ?>">
                <i class="nav-icon fas fa-database"></i>
                <p>Database</p>
              </a>
            </li>
    
            <li class="nav-item">
              <a href="?user" class="nav-link <?php if(isset($_GET['user'])) { echo "active"; } ?>">
                <i class="nav-icon fas fa-user"></i>
                <p>User</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="?company" class="nav-link <?php if(isset($_GET['company'])) { echo "active"; } ?>">
                <i class="nav-icon fas fa-building"></i>
                <p>Company</p>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

      <!-- Main content -->
      <div class="content mt-3">
        <div class="container-fluid">
        
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
        <?php if(isset($_GET['setup_checks'])){ ?>

        <div class="card mb-3">
          <div class="card-header">
            <h6 class="mt-1"><i class="fa fa-fw fa-checkmark"></i> Setup Checks</h6>
          </div>
          <div class="card-body">
            <ul class="mb-4">
              <li>Upload is readable and writeable</li>
              <li>PHP 7+ Installed</li>
            </ul>
            <center><a href="?database" class="btn btn-lg btn-primary mb-5">Install</a></center>
          </div>
        </div>

        <?php } ?>

        <?php if(isset($_GET['database'])){ ?>

          <div class="card card-dark">
            <div class="card-header">
              <h3 class="card-title"><i class="fa fa-fw fa-database"></i> Connect your Database</h3>
            </div>
            <div class="card-body">
              <form method="post" autocomplete="off">
                
                <div class="form-group">
                  <label>Database User <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="username" placeholder="Database User" autofocus required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Database Password <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" name="password" placeholder="Database Password" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Database Name <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-database"></i></span>
                    </div>
                    <input type="text" class="form-control" name="database" placeholder="Database Name" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Database Host <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                    </div>
                    <input type="text" class="form-control" name="host" value="localhost" placeholder="Database Host" required>
                  </div>
                </div>

                <hr>
                <button type="submit" name="add_database" class="btn btn-primary">Next <i class="fa fa-fw fa-arrow-circle-right"></i></button>
              </form>
            </div>
          </div>

        <?php }elseif(isset($_GET['user'])){ ?>

          <div class="card card-dark">
            <div class="card-header">
              <h3 class="card-title"><i class="fa fa-fw fa-user"></i> Create your first user</h3>
            </div>
            <div class="card-body">
        
              <form method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="form-group">
                  <label>Name <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="name" placeholder="Full Name" autofocus required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Email <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                  </div>
                </div>

                <div class="form-group">
                  <label>Password <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" name="password" placeholder="Enter a Password" required>
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Avatar</label>
                  <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
                </div>
                
                <hr>
                
                <button type="submit" name="add_user" class="btn btn-primary">Next <i class="fa fa-fw fa-arrow-circle-right"></i></button>
              </form>
            </div>
          </div>

        <?php }elseif(isset($_GET['company'])){ ?>

          <div class="card card-dark">
            <div class="card-header">
              <h3 class="card-title"><i class="fa fa-fw fa-building"></i> Company Details</h3>
            </div>
            <div class="card-body">
              <form method="post" enctype="multipart/form-data" autocomplete="off">
                
                <div class="form-group">
                  <label>Company Name <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    </div>
                    <input type="text" class="form-control" name="name" placeholder="Company Name" autofocus required>  
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Address</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                    </div>
                    <input type="text" class="form-control" name="address" placeholder="Street Address">
                  </div>
                </div>

                <div class="form-group">
                  <label>City</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                    </div>
                    <input type="text" class="form-control" name="city" placeholder="City">
                  </div>
                </div>

                <div class="form-group">
                  <label>State</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                    </div>
                    <select class="form-control select2" name="state">
                      <option value="">- State -</option>
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
                    <input type="text" class="form-control" name="zip" placeholder="Postal Code">
                  </div>
                </div>

                <div class="form-group">
                  <label>Country</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                    </div>
                    <select class="form-control select2" name="country">
                      <option value="">- Country -</option>
                      <?php foreach($countries_array as $country_name) { ?>
                      <option><?php echo $country_name; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label>Phone</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                    </div>
                    <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'"> 
                  </div>
                </div>

                <div class="form-group">
                  <label>Email</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="email" placeholder="Email address"> 
                  </div>
                </div>

                <div class="form-group">
                  <label>Website</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                    </div>
                    <input type="text" class="form-control" name="website" placeholder="Website address">
                  </div>
                </div>

                <div class="form-group">
                  <label>Currency</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                    </div>
                    <select class="form-control select2" name="currency_code" required>
                      <option value="">- Currency -</option>
                      <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                      <option value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label>Logo</label>
                  <input type="file" class="form-control-file" name="file">
                </div>
                
                <hr>
                
                <button type="submit" name="add_company_settings" class="btn btn-primary">Finish and Sign in <i class="fa fa-fw fa-check-circle"></i></button>
                  
              </form>
            </div>
          </div>

        <?php }else{ ?>

          <div class="card card-dark">
            <div class="card-header">
              <h3 class="card-title"><i class="fa fa-fw fa-cube"></i> Welcome to ITFlow Setup</h3>
            </div>
            <div class="card-body">
              <p>A database must be created before proceeding, then click on the Setup button to to get started, </p>
              <hr>
              <p>After the setup is complete add cron.php to your cron and set it to run once everyday at 11:00PM. This is for tasks such as sending out recurring invoices, late payment reminders, alerts, etc</p>
              <hr>
              <p>An API is present to allow integration with other third pary apps. An API Key will be auto generated and can be changed in settings after setup. The API will give you the following capabilities</p>
              <ul class="mb-4">
                <li>Address book XML for VOIP Phones</li>
                <li>Caller ID Lookup</li>
                <li>Get List of Emails in CSV to export to a mailing list</li>
                <li>Acquire balance can be useful for customer's to get their balance by phone</li>
              </ul>
              <center><a href="?database" class="btn btn-primary">Setup <i class="fa fa-fw fa-arrow-alt-circle-right"></i></a></center>
            </div>
          </div>

        <?php } ?>

      </div><!-- /.container-fluid -->
      </div>
      <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
  </div>
  <!-- ./wrapper -->

  <!-- REQUIRED SCRIPTS -->

  <!-- jQuery -->
  <script src="plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- Custom js-->
  <script src='plugins/inputmask/min/jquery.inputmask.bundle.min.js'></script>
  <script src='plugins/inputmask/min/inputmask/bindings/inputmask.binding.min.js'></script>
  <script src='plugins/select2/js/select2.min.js'></script>
  <!-- AdminLTE App -->
  <script src="dist/js/adminlte.min.js"></script>

  <!-- Custom js-->
  <script src="js/app.js"></script>

</body>

</html>
