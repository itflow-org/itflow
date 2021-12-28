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
  "Afghanistan",
  "Albania",
  "Algeria",
  "Andorra",
  "Angola",
  "Antigua and Barbuda",
  "Argentina",
  "Armenia",
  "Australia",
  "Austria",
  "Azerbaijan",
  "Bahamas",
  "Bahrain",
  "Bangladesh",
  "Barbados",
  "Belarus",
  "Belgium",
  "Belize",
  "Benin",
  "Bhutan",
  "Bolivia",
  "Bosnia and Herzegovina",
  "Botswana",
  "Brazil",
  "Brunei",
  "Bulgaria",
  "Burkina Faso",
  "Burundi",
  "Cambodia",
  "Cameroon",
  "Canada",
  "Cape Verde",
  "Central African Republic",
  "Chad",
  "Chile",
  "China",
  "Colombi",
  "Comoros",
  "Congo (Brazzaville)",
  "Congo",
  "Costa Rica",
  "Cote d'Ivoire",
  "Croatia",
  "Cuba",
  "Cyprus",
  "Czech Republic",
  "Denmark",
  "Djibouti",
  "Dominica",
  "Dominican Republic",
  "East Timor (Timor Timur)",
  "Ecuador",
  "Egypt",
  "El Salvador",
  "Equatorial Guinea",
  "Eritrea",
  "Estonia",
  "Ethiopia",
  "Fiji",
  "Finland",
  "France",
  "Gabon",
  "Gambia, The",
  "Georgia",
  "Germany",
  "Ghana",
  "Greece",
  "Grenada",
  "Guatemala",
  "Guinea",
  "Guinea-Bissau",
  "Guyana",
  "Haiti",
  "Honduras",
  "Hungary",
  "Iceland",
  "India",
  "Indonesia",
  "Iran",
  "Iraq",
  "Ireland",
  "Israel",
  "Italy",
  "Jamaica",
  "Japan",
  "Jordan",
  "Kazakhstan",
  "Kenya",
  "Kiribati",
  "Korea, North",
  "Korea, South",
  "Kuwait",
  "Kyrgyzstan",
  "Laos",
  "Latvia",
  "Lebanon",
  "Lesotho",
  "Liberia",
  "Libya",
  "Liechtenstein",
  "Lithuania",
  "Luxembourg",
  "Macedonia",
  "Madagascar",
  "Malawi",
  "Malaysia",
  "Maldives",
  "Mali",
  "Malta",
  "Marshall Islands",
  "Mauritania",
  "Mauritius",
  "Mexico",
  "Micronesia",
  "Moldova",
  "Monaco",
  "Mongolia",
  "Morocco",
  "Mozambique",
  "Myanmar",
  "Namibia",
  "Nauru",
  "Nepal",
  "Netherlands",
  "New Zealand",
  "Nicaragua",
  "Niger",
  "Nigeria",
  "Norway",
  "Oman",
  "Pakistan",
  "Palau",
  "Panama",
  "Papua New Guinea",
  "Paraguay",
  "Peru",
  "Philippines",
  "Poland",
  "Portugal",
  "Qatar",
  "Romania",
  "Russia",
  "Rwanda",
  "Saint Kitts and Nevis",
  "Saint Lucia",
  "Saint Vincent",
  "Samoa",
  "San Marino",
  "Sao Tome and Principe",
  "Saudi Arabia",
  "Senegal",
  "Serbia and Montenegro",
  "Seychelles",
  "Sierra Leone",
  "Singapore",
  "Slovakia",
  "Slovenia",
  "Solomon Islands",
  "Somalia",
  "South Africa",
  "Spain",
  "Sri Lanka",
  "Sudan",
  "Suriname",
  "Swaziland",
  "Sweden",
  "Switzerland",
  "Syria",
  "Taiwan",
  "Tajikistan",
  "Tanzania",
  "Thailand",
  "Togo",
  "Tonga",
  "Trinidad and Tobago",
  "Tunisia",
  "Turkey",
  "Turkmenistan",
  "Tuvalu",
  "Uganda",
  "Ukraine",
  "United Arab Emirates",
  "United Kingdom",
  "United States",
  "Uruguay",
  "Uzbekistan",
  "Vanuatu",
  "Vatican City",
  "Venezuela",
  "Vietnam",
  "Yemen",
  "Zambia",
  "Zimbabwe"
);

$currencies_array = array(
  'ALL' => 'Albania Lek',
  'AFN' => 'Afghanistan Afghani',
  'ARS' => 'Argentina Peso',
  'AWG' => 'Aruba Guilder',
  'AUD' => 'Australia Dollar',
  'AZN' => 'Azerbaijan New Manat',
  'BSD' => 'Bahamas Dollar',
  'BBD' => 'Barbados Dollar',
  'BDT' => 'Bangladeshi taka',
  'BYR' => 'Belarus Ruble',
  'BZD' => 'Belize Dollar',
  'BMD' => 'Bermuda Dollar',
  'BOB' => 'Bolivia Boliviano',
  'BAM' => 'Bosnia and Herzegovina Convertible Marka',
  'BWP' => 'Botswana Pula',
  'BGN' => 'Bulgaria Lev',
  'BRL' => 'Brazil Real',
  'BND' => 'Brunei Darussalam Dollar',
  'KHR' => 'Cambodia Riel',
  'CAD' => 'Canada Dollar',
  'KYD' => 'Cayman Islands Dollar',
  'CLP' => 'Chile Peso',
  'CNY' => 'China Yuan Renminbi',
  'COP' => 'Colombia Peso',
  'CRC' => 'Costa Rica Colon',
  'HRK' => 'Croatia Kuna',
  'CUP' => 'Cuba Peso',
  'CZK' => 'Czech Republic Koruna',
  'DKK' => 'Denmark Krone',
  'DOP' => 'Dominican Republic Peso',
  'XCD' => 'East Caribbean Dollar',
  'EGP' => 'Egypt Pound',
  'SVC' => 'El Salvador Colon',
  'EEK' => 'Estonia Kroon',
  'EUR' => 'Euro Member Countries',
  'FKP' => 'Falkland Islands (Malvinas) Pound',
  'FJD' => 'Fiji Dollar',
  'GHC' => 'Ghana Cedis',
  'GIP' => 'Gibraltar Pound',
  'GTQ' => 'Guatemala Quetzal',
  'GGP' => 'Guernsey Pound',
  'GYD' => 'Guyana Dollar',
  'HNL' => 'Honduras Lempira',
  'HKD' => 'Hong Kong Dollar',
  'HUF' => 'Hungary Forint',
  'ISK' => 'Iceland Krona',
  'INR' => 'India Rupee',
  'IDR' => 'Indonesia Rupiah',
  'IRR' => 'Iran Rial',
  'IMP' => 'Isle of Man Pound',
  'ILS' => 'Israel Shekel',
  'JMD' => 'Jamaica Dollar',
  'JPY' => 'Japan Yen',
  'JEP' => 'Jersey Pound',
  'KZT' => 'Kazakhstan Tenge',
  'KPW' => 'Korea (North) Won',
  'KRW' => 'Korea (South) Won',
  'KGS' => 'Kyrgyzstan Som',
  'LAK' => 'Laos Kip',
  'LVL' => 'Latvia Lat',
  'LBP' => 'Lebanon Pound',
  'LRD' => 'Liberia Dollar',
  'LTL' => 'Lithuania Litas',
  'MKD' => 'Macedonia Denar',
  'MYR' => 'Malaysia Ringgit',
  'MUR' => 'Mauritius Rupee',
  'MXN' => 'Mexico Peso',
  'MNT' => 'Mongolia Tughrik',
  'MZN' => 'Mozambique Metical',
  'NAD' => 'Namibia Dollar',
  'NPR' => 'Nepal Rupee',
  'ANG' => 'Netherlands Antilles Guilder',
  'NZD' => 'New Zealand Dollar',
  'NIO' => 'Nicaragua Cordoba',
  'NGN' => 'Nigeria Naira',
  'NOK' => 'Norway Krone',
  'OMR' => 'Oman Rial',
  'PKR' => 'Pakistan Rupee',
  'PAB' => 'Panama Balboa',
  'PYG' => 'Paraguay Guarani',
  'PEN' => 'Peru Nuevo Sol',
  'PHP' => 'Philippines Peso',
  'PLN' => 'Poland Zloty',
  'QAR' => 'Qatar Riyal',
  'RON' => 'Romania New Leu',
  'RUB' => 'Russia Ruble',
  'SHP' => 'Saint Helena Pound',
  'SAR' => 'Saudi Arabia Riyal',
  'RSD' => 'Serbia Dinar',
  'SCR' => 'Seychelles Rupee',
  'SGD' => 'Singapore Dollar',
  'SBD' => 'Solomon Islands Dollar',
  'SOS' => 'Somalia Shilling',
  'ZAR' => 'South Africa Rand',
  'LKR' => 'Sri Lanka Rupee',
  'SEK' => 'Sweden Krona',
  'CHF' => 'Switzerland Franc',
  'SRD' => 'Suriname Dollar',
  'SYP' => 'Syria Pound',
  'TWD' => 'Taiwan New Dollar',
  'THB' => 'Thailand Baht',
  'TTD' => 'Trinidad and Tobago Dollar',
  'TRY' => 'Turkey Lira',
  'TRL' => 'Turkey Lira',
  'TVD' => 'Tuvalu Dollar',
  'UAH' => 'Ukraine Hryvna',
  'GBP' => 'United Kingdom Pound',
  'USD' => 'United States Dollar',
  'UYU' => 'Uruguay Peso',
  'UZS' => 'Uzbekistan Som',
  'VEF' => 'Venezuela Bolivar',
  'VND' => 'Viet Nam Dong',
  'YER' => 'Yemen Rial',
  'ZWD' => 'Zimbabwe Dollar'
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

  $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
  $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

  mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_created_at = NOW()");

  $user_id = mysqli_insert_id($mysqli);

  mkdir("uploads/users/$user_id");

  //Check to see if a file is attached
  if($_FILES['file']['tmp_name'] != ''){
      
    // get details of the uploaded file
    $file_error = 0;
    $file_tmp_path = $_FILES['file']['tmp_name'];
    $file_name = $_FILES['file']['name'];
    $file_size = $_FILES['file']['size'];
    $file_type = $_FILES['file']['type'];
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

    // sanitize file-name
    $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

    // check if file has one of the following extensions
    $allowed_file_extensions = array('jpg', 'gif', 'png');
 
    if(in_array($file_extension,$allowed_file_extensions) === false){
        $file_error = 1;
    }

    //Check File Size
    if($file_size > 2097152){
      $file_error = 1;
    }

    if($file_error == 0){
      // directory in which the uploaded file will be moved
      $upload_file_dir = "uploads/users/$user_id/";
      $dest_path = $upload_file_dir . $new_file_name;

      move_uploaded_file($file_tmp_path, $dest_path);

      //Set Avatar
      mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");

      $_SESSION['alert_message'] = 'File successfully uploaded.';
    }else{
        
      $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
    }
  }

  //Create Settings
  mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = $user_id, user_role = 6, user_default_company = 1");
  
  $_SESSION['alert_message'] = "User <strong>$name</strong> created!";

  header("Location: setup.php?company");

}

if(isset($_POST['add_company_settings'])){

  $sql = mysqli_query($mysqli,"SELECT user_id FROM users");
  $row = mysqli_fetch_array($sql);
  $user_id = $row['user_id'];

  $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
  $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
  $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
  $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
  $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
  $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
  $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
  $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
  $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
  $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));

  mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_currency = '$currency_code', company_created_at = NOW()");

  $company_id = mysqli_insert_id($mysqli);
  $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
  $config_api_key = keygen();
  $config_aes_key = keygen();


  mkdir("uploads/clients/$company_id");
  mkdir("uploads/expenses/$company_id");
  mkdir("uploads/settings/$company_id");
  mkdir("uploads/tmp/$company_id");

  //Check to see if a file is attached
  if($_FILES['file']['tmp_name'] != ''){
      
    // get details of the uploaded file
    $file_error = 0;
    $file_tmp_path = $_FILES['file']['tmp_name'];
    $file_name = $_FILES['file']['name'];
    $file_size = $_FILES['file']['size'];
    $file_type = $_FILES['file']['type'];
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

    // sanitize file-name
    $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

    // check if file has one of the following extensions
    $allowed_file_extensions = array('jpg', 'gif', 'png');
 
    if(in_array($file_extension,$allowed_file_extensions) === false){
      $file_error = 1;
    }

    //Check File Size
    if($file_size > 2097152){
      $file_error = 1;
    }

    if($file_error == 0){
      // directory in which the uploaded file will be moved
      $upload_file_dir = "uploads/settings/$company_id/";
      $dest_path = $upload_file_dir . $new_file_name;

      move_uploaded_file($file_tmp_path, $dest_path);

      mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = $company_id");

      $_SESSION['alert_message'] = 'File successfully uploaded.';
    }else{
        
      $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
    }
  }

  //Set User Company Permissions
  mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $user_id, company_id = $company_id");
 
  mysqli_query($mysqli,"INSERT INTO settings SET company_id = $company_id, config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_prefix = 'REC-', config_recurring_next_number = 1, config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_api_key = '$config_api_key', config_aes_key = '$config_aes_key', config_recurring_auto_send_invoice = 1, config_default_net_terms = 7, config_send_invoice_reminders = 1, config_enable_cron = 0, config_ticket_next_number = 1, config_base_url = '$config_base_url'");

  //Create Some Data

  mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', opening_balance = 0, account_currency_code = '$currency_code', account_created_at = NOW(), company_id = $company_id");

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
                    <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Enter a Database Password" autocomplete="new-password" required>
                    <div class="input-group-append">
                      <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                    </div>
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
                    <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Enter a Password" autocomplete="new-password" required>
                    <div class="input-group-append">
                      <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                    </div>
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
                  <label>State / Province</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                    </div>
                    <input type="text" class="form-control" name="state" placeholder="State or Province">
                  </div>
                </div>

                <div class="form-group">
                  <label>Zip / Postal Code</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                    </div>
                    <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code">
                  </div>
                </div>

                <div class="form-group">
                  <label>Country <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                    </div>
                    <select class="form-control select2" name="country" required>
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
                    <input type="text" class="form-control" name="phone" placeholder="Phone Number"> 
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
                  <label>Currency <strong class="text-danger">*</strong></label>
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
  <script src='plugins/select2/js/select2.min.js'></script>
  <script src="plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
  <!-- AdminLTE App -->
  <script src="dist/js/adminlte.min.js"></script>

  <!-- Custom js-->
  <script src="js/app.js"></script>

</body>

</html>
