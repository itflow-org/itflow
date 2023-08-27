<?php
if (file_exists("config.php")) {
    include("config.php");
}

include("functions.php");
if (isset($_POST['forgot_password']) && $_POST['email']) {

    $email = $_POST['email'];

    $result = mysqli_query($mysqli, "SELECT * FROM users WHERE user_email='" . $email . "'");

    $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

    $row= mysqli_fetch_array($result);

    if(!$row){
        $status = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
        <strong>Whooops something went wrong!</strong> We cant find a user with that email address.
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>';
    }

    if ($row) {
        

        $user_id = $row['user_id'];
    
        $company_name = "ITFlow";
        
        $token = md5($email).rand(10, 9999);

        $expFormat = mktime(
            date("H")+1,
            date("i"),
            date("s"),
            date("m"),
            date("d"),
            date("Y")
        );

        $expDate = date("Y-m-d H:i:s", $expFormat);
       
        $update = mysqli_query($mysqli, "INSERT INTO password_resets set password_rest_token='" . $token . "' , password_rest_email='" . $email . "',password_rest_expired_at='" . $expDate . "' ");

        $link = "<a href='http://".$base_url."/password_reset_token.php?key=".$token."&email=".$email."&action=reset"."'>Click To Reset password</a>";

        
        

        $output = '<p>Dear user,</p>';
        $output .= '<p>Please click on the following link to reset your password.</p>';
        $output .= '<p>-------------------------------------------------------------</p>';
        $output .= $link;
        $output .= '<p>-------------------------------------------------------------</p>';
        $output .= '<p>Please be sure to copy the entire link into your browser.
        The link will expire after 1 hour for security reasons.</p>';
        $output .= '<p>If you did not request this forgotten password email, no action 
        is needed, your password will not be reset. However, you may want to log into 
        your account and change your security password as someone may have guessed it.</p>';
        $output .= '<p>Thanks,</p>';
        $output .= '<p>' . $company_name . '</p>';
        $body = $output;
        $subject = "Password Recovery ";


       

        // Get Email settings Now
        $fetch_email_setup = mysqli_query($mysqli, "SELECT * FROM `settings` WHERE company_id='" . $company_id . "'");
        $company_email = mysqli_fetch_array($fetch_email_setup);
        $config_smtp_host = $company_email['config_smtp_host'] ?? '';
        ($config_smtp_host);
        $config_smtp_username = $company_email['config_smtp_username'] ?? '';
        $config_smtp_password = $company_email['config_smtp_password'] ?? '';
        $config_smtp_port = $company_email['config_smtp_port'] ?? '';
        $config_smtp_encryption = $company_email['config_smtp_encryption'] ?? ''; 
        $config_mail_from_email = $company_email['config_mail_from_email'] ?? '';
        $config_mail_from_name = $company_email['config_mail_from_name'] ?? '';




            $mail = sendSingleEmail(
            $config_smtp_host,
            $config_smtp_username,
            $config_smtp_password,
            $config_smtp_encryption,
            $config_smtp_port,
            $config_mail_from_email,
            $config_mail_from_name,
            $email,
            $contact_name = $company_name,
            $subject,
            $body
        );

        if($mail){
            $status = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Done!</strong> We have sent you a password reset Link to your email address.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>';
        } else {
            $status = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Whooops! Something went wrong</strong> Please try again.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>';
        }
    
    }
}       
   ?>



<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $config_app_name; ?> | Forgot Password</title>
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
    <?=$company_name?>
    </div>

    <!-- /.login-logo -->
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg"><?php if(isset($status)) { echo $status; } ?></p>
            <form method="post">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="Enter Email" name="email"  required >
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>
                
               

                <button type="submit" class="btn btn-primary btn-block mb-3" name="forgot_password">Reset Password</button>

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






