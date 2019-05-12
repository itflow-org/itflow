<?php 

  //include("config.php");
  //include("check_login.php");
  //include("functions.php");

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

  <?php include("setup_top_nav.php"); ?>

  <div id="wrapper">
    <?php
    include("setup_side_nav.php");
    ?>    
    
    <div id="content-wrapper">
      
      <div class="container">
        <div class="card mb-3">
          <div class="card-header">
            <h6 class="mt-1"><i class="fa fa-database"></i> Setup Database</h6>
          </div>
          <div class="card-body">
            <form class="p-3" action="post.php" method="post" autocomplete="off">
              
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
                  <input type="email" class="form-control" name="username" placeholder="Username to access the database" required>
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
                  <input type="email" class="form-control" name="database" placeholder="Name of the database" required>
                </div>
              </div>
              <hr>
              <button type="submit" name="add_database" class="btn btn-primary">Save</button>
            </form>
          </div>
        </div>
        
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
                <input type="file" class="form-control-file" accept="image/*;capture=camera" name="avatar">
              </div>
              <hr>
              <button type="submit" name="add_user" class="btn btn-primary">Save</button>
            </form>
          </div>
        </div>

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
              
              <button type="submit" name="edit_company_settings" class="btn btn-primary">Save</button>
                
            </form>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">
            <h6 class="mt-1"><i class="fa fa-university"></i> Create an Account</h6>
          </div>
          <div class="card-body">
      
            <form class="p-3" action="post.php" method="post" autocomplete="off">
              <div class="form-group">
                <label>Account Name</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-university"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Account name" required>
                </div>
              </div>
              <div class="form-group">
                <label>Opening Balance</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-dollar-sign"></i></span>
                  </div>
                  <input type="number" class="form-control" step="0.01" min="0" name="opening_balance" placeholder="Opening Balance" required>
                </div>
              </div>
              <div class="custom-control custom-checkbox mb-5">
                <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="auto_create_accounts" value="1" >
                <label class="custom-control-label" for="customControlAutosizing">Auto Create</label>
              </div>
              <hr>
              <button type="submit" name="add_account" class="btn btn-primary">Save</button>
            </form>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">
            <h6 class="mt-1"><i class="fa fa-tag"></i> Create some categories</h6>
          </div>
          <div class="card-body">
      
            <form class="p-3" action="post.php" method="post" autocomplete="off">
              <label>Expense Category</label>
              <div class="form-group row">
                
                <div class="input-group col-10">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-shopping-cart"></i></span>
                  </div>
                  <input type="text" class="form-control" name="expense_category" placeholder="Expense category name" required>
                </div>  
                <div class="input-group col-2">  
                  <input type="color" class="form-control" name="expense_category_color" placeholder="Pick a color" required>
                </div>
              </div>

              <label>Invoice Category</label>
              <div class="form-group row">
                
                <div class="input-group col-10">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-file"></i></span>
                  </div>
                  <input type="text" class="form-control" name="income_category" placeholder="Invoice category name" required>
                </div>  
                <div class="input-group col-2">  
                  <input type="color" class="form-control" name="income_category_color" placeholder="Pick a color" required>
                </div>
              </div>

              <label>Payment Type</label>
              <div class="form-group row">
                
                <div class="input-group col-10">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-credit-card"></i></span>
                  </div>
                  <input type="text" class="form-control" name="payment_category" placeholder="Payment type eg. check, cash, credit card etc" required>
                </div>  
                <div class="input-group col-2">  
                  <input type="color" class="form-control" name="payment_category_color" placeholder="Pick a color" required>
                </div>
              </div>
              <div class="custom-control custom-checkbox mb-5">
                <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="auto_create_categories" value="1" >
                <label class="custom-control-label" for="customControlAutosizing">Auto Create</label>
              </div>
              <hr>
              <button type="submit" name="add_categories" class="btn btn-primary">Save</button>
            </form>
          </div>
        </div>
        <div class="card mb-3">
          <div class="card-header">
            <h6 class="float-left mt-1"><i class="fa fa-envelope"></i> Mail Settings</h6>
          </div>
          <div class="card-body">
            <form class="p-3" action="post.php" method="post" autocomplete="off">
              <div class="form-group">
                <label>SMTP Host</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-server"></i></span>
                  </div>
                  <input type="text" class="form-control" name="config_smtp_host" placeholder="Mail Server Address" required>
                </div>
              </div>

              <div class="form-group">
                <label>SMTP Port</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-plug"></i></span>
                  </div>
                  <input type="text" class="form-control" name="config_smtp_port" placeholder="Mail Server Port Number"  required>
                </div>
              </div>
              
              <div class="form-group">
                <label>SMTP Username</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="config_smtp_username" placeholder="Username" required>
                </div>
              </div>

              <div class="form-group mb-5">
                <label>SMTP Password</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                  </div>
                  <input type="password" class="form-control" name="config_smtp_password" placeholder="Password" required>
                </div>
              </div>

              <hr>
              <button type="submit" name="edit_mail_settings" class="btn btn-primary">Save</button>
                
            </form>
          </div>
        </div>

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