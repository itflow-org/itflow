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

  <div id="wrapper">
    
    
    <div id="content-wrapper">
      
      <div class="container">
        <h3>Install CRM</h3>
        <div class="card mb-3">
          <div class="card-header">
            <h6>Setup Database</h6>
          </div>
          <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
              
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
              
              <div class="form-group">
                <label>MySQL Database Name</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-database"></i></span>
                  </div>
                  <input type="email" class="form-control" name="database" placeholder="Name of the database" required>
                </div>
              </div>
              <button type="submit" name="add_database" class="btn btn-primary">Save</button>
            </form>
          </div>
        </div>
        <div class="card mb-3">
          <div class="card-header">
            <h6>Create your first user</h6>
          </div>
          <div class="card-body">
      
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
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
              <div class="form-group">
                <label>Avatar</label>
                <input type="file" class="form-control-file" accept="image/*;capture=camera" name="avatar">
              </div>
              <button type="submit" name="add_user" class="btn btn-primary">Save</button>
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