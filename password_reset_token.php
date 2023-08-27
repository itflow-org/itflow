<?php
if (!file_exists('config.php')) {
    header("Location: setup.php");
    exit;
}

include("config.php");

include("functions.php");

if($_GET['key'] && $_GET['email'] && $_GET['action'])
{

$email = $_GET['email'];
$token = $_GET['key'];
$query = mysqli_query($mysqli,"SELECT * FROM `password_resets` WHERE `password_reset_token`='".$token."' and `password_reset_email`='".$email."'");

if (mysqli_num_rows($query) > 0) {
$row= mysqli_fetch_array($query);
$curDate = date("Y-m-d H:i:s");
$expiredAt = $row['password_reset_expired_at'];

// Convert both dates to DateTime objects for comparison
$currentDateTime = new DateTime($curDate);
$expiredDateTime = new DateTime($expiredAt);

// Calculate the time difference
$timeDifference = $currentDateTime->diff($expiredDateTime);

// Convert the time difference to hours
$hoursDifference = $timeDifference->h + ($timeDifference->days * 24);

// Check if the time difference is greater than 1 hour
if ($hoursDifference > 1) {
    
    $status = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>Whooops!</strong> The token has expired.
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>';

} 

    
    ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $config_app_name; ?> | Password Reset</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        CREATE NEW PASSWORD
    </div>

    <!-- /.login-logo -->
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg"><?php if(isset($status)) { echo $status; } ?></p>
            <form action="update_forgot_password.php" method="post">
              <input type="hidden" name="email" value="<?php echo $email;?>">
              <input type="hidden" name="token" value="<?php echo $token;?>">


                <div class="input-group mb-3">
                    <input type="password" class="form-control" placeholder="New Password" name="new_password" <?php if (isset($status)) {
                        echo 'disabled';
                    } else {
                        echo 'required'; } ?> >
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" class="form-control" placeholder="New Password" name="repeat_new_password" <?php if (isset($status)) {
                        echo 'disabled';
                    } else {
                        echo 'required'; } ?>  >
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>

                
               

                <button type="submit" class="btn btn-primary btn-block mb-3" name="update_forgot_password">Create New Password</button>

                <hr><br>

               
            </form>

        </div>
        <!-- /.login-card-body -->
    </div>
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>

<script src="plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>

<!-- Prevents resubmit on refresh or back -->
<script>

    if(window.history.replaceState){
        window.history.replaceState(null,null,window.location.href);
    }

</script>

</body>
</html>
<?php } } else{
     header("Location: login.php");
}

?>
</div>
</div>
</div>
</body>
</html>