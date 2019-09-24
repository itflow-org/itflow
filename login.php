<?php

if(!file_exists('config.php')){
  header("Location: setup.php");
}

?>

<?php include("config.php"); ?>
<?php include("functions.php"); ?>

<?php 

$ip = get_ip();
$os = get_os();
$browser = get_web_browser();
$device = get_device();

?>

<?php

session_start();

if(isset($_POST['login'])){
  
  $email = mysqli_real_escape_string($mysqli,$_POST['email']);
  $password = md5($_POST['password']);
  $current_code = mysqli_real_escape_string($mysqli,$_POST['current_code']);

  $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE email = '$email' AND password = '$password'");
  
  if(mysqli_num_rows($sql) == 1){
    $row = mysqli_fetch_array($sql);
    $token = $row['token'];
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['name'] = $row['name'];
    $name = $row['name'];
    $user_id = $row['user_id'];

    if(empty($token)){
      $_SESSION['logged'] = TRUE;
      mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Success', log_description = '$ip - $os - $browser - $device', log_created_at = NOW(), user_id = $user_id");
         
      header("Location: dashboard.php");
    }else{
      require_once("rfc6238.php");

      if(TokenAuth6238::verify($token,$current_code)){
        $_SESSION['logged'] = TRUE;
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login 2FA', log_action = 'Success', log_description = '$ip - $os - $browser - $device', log_created_at = NOW(), user_id = $user_id");
        //header("Location: $config_start_page");
        header("Location: dashboard.php");
      }else{
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = '2FA Failed', log_description = '$ip - $os - $browser - $device', log_created_at = NOW(), user_id = $user_id");

        $response = "
          <div class='alert alert-danger'>
            Invalid Code.
            <button class='close' data-dismiss='alert'>&times;</button>
          </div>
        ";
      }     
    }
  
  }else{
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Failed', log_description = '$email - $ip - $os - $browser - $device', log_created_at = NOW()");

    $response = "
      <div class='alert alert-danger'>
        Incorrect email or password.
        <button class='close' data-dismiss='alert'>&times;</button>
      </div>
    ";
  }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <title><?php echo $config_app_name; ?> | Login</title>

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">

  <link href="css/style.css" rel="stylesheet">

</head>

<body class="bg-secondary">

  <div class="container">
    <div class="card card-login mx-auto mt-5 bg-dark">
      <div class="card-header mt-2 text-white text-center"><h3>Login</h3></div>
      <div class="card-body bg-white">
        <?php if(isset($response)) { echo $response; } ?>
        <form method="post">
          
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="email" placeholder="Username" required autofocus>
            </div>
          </div>

          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="password" class="form-control" name="password" placeholder="Password" required>
            </div>
          </div>

          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
              </div>
              <input type="text" class="form-control" name="current_code" placeholder="Token">
            </div>
          </div>

          <button class="btn btn-primary btn-block" type="submit" name="login">Sign in</button>
        
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

  <!-- Prevents resubmit on refresh or back -->
  <script>
  
  if ( window.history.replaceState ) {
    window.history.replaceState( null, null, window.location.href );
  }
  
  </script>

</body>

</html>
