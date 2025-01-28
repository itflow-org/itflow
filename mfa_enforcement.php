<?php
require_once "config.php";
require_once "functions.php";
require_once "check_login.php";
require_once 'plugins/totp/totp.php';


// Only generate the token once and store it in session:
if (empty($_SESSION['mfa_token'])) {
    $token = key32gen();
    $_SESSION['mfa_token'] = $token;
}
$token = $_SESSION['mfa_token'];

// Generate QR Code
$data = "otpauth://totp/ITFlow:$session_email?secret=$token";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title>MFA Enforcement | <?php echo $session_company_name; ?></title>

    <!-- 
    Favicon
    If Fav Icon exists else use the default one 
    -->
    <?php if(file_exists('uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="plugins/adminlte/css/adminlte.min.css">

    <link href="plugins/toastr/toastr.min.css" rel="stylesheet">
    <!-- CSS to allow regular button to show as block button in mobile response view using the class btn-responsive -->
    <style>
        /* 
           For screens below 576px (xs): 
           - Make the button full-width, display:block 
        */
        @media (max-width: 575.98px) {
          .btn-responsive {
            display: block;
            width: 100%;
          }
        }

        /* 
           For screens 576px (sm) and above:
           - Revert to an inline style 
        */
        @media (min-width: 576px) {
          .btn-responsive {
            display: inline-block;
            width: auto;
          }
        }
    </style>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/toastr/toastr.min.js"></script>

</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed accent-<?php echo nullable_htmlentities($config_theme); ?>">
    <div class="wrapper text-sm">
        <div class="container col-md-5">

            <?php require_once 'includes/inc_alert_feedback.php'; ?>

            <div class="card card-dark mt-5">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fas fa-shield-alt mr-2"></i>Multi-Factor Authentication Enforcement</h3>

                    <div class="card-tools">
                        <a href="post.php?logout" class="btn btn-light btn-sm text-dark">
                            <i class="fa fa-door-open mr-2"></i>Logout
                        </a>
                    </div>
                </div>
                <div class="card-body">
                     <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                        
                        <div class="text-center">
                            <h4>Scan this code into your app</h4>
                            <img src='plugins/barcode/barcode.php?f=png&s=qr&d=<?php echo $data; ?>'>
                            <div>Can't Scan? <span class="text-secondary">Copy and paste the secret below</span>
                            </div>
                            <hr>
                            <p><span class='text-secondary'>Secret:</span> <?php echo $token; ?>
                                <button type="button" class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $token; ?>'><i class='far fa-copy text-secondary'></i></button>
                            </p>
                        </div>

                        <hr>

                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" minlength="6" maxlength="6" name="verify_code" placeholder="Enter 6 digit code to verify MFA" required>
                                <div class="input-group-append">
                                    <button type="submit" name="enable_mfa" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Enable</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> <!-- ./wrapper -->


<!-- REQUIRED SCRIPTS -->

<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom js-->
<script src="plugins/clipboardjs/clipboard.min.js"></script>



<!-- AdminLTE App -->
<script src="plugins/adminlte/js/adminlte.min.js"></script>
<script>

//Prevents resubmit on forms
if(window.history.replaceState){
  window.history.replaceState(null, null, window.location.href);
}

// Slide alert up after 4 secs
$("#alert").fadeTo(5000, 500).slideUp(500, function(){
  $("#alert").slideUp(500);
});

// ClipboardJS

//Fix to allow Clipboard Copying within Bootstrap Modals
//For use in Bootstrap Modals or with any other library that changes the focus you'll want to set the focused element as the container value.
$.fn.modal.Constructor.prototype._enforceFocus = function() {};

// Tooltip

$('button').tooltip({
  trigger: 'click',
  placement: 'bottom'
});

function setTooltip(btn, message) {
  $(btn).tooltip('hide')
    .attr('data-original-title', message)
    .tooltip('show');
}

function hideTooltip(btn) {
  setTimeout(function() {
    $(btn).tooltip('hide');
  }, 1000);
}

// Clipboard

var clipboard = new ClipboardJS('.clipboardjs');

clipboard.on('success', function(e) {
  setTooltip(e.trigger, 'Copied!');
  hideTooltip(e.trigger);
});

clipboard.on('error', function(e) {
  setTooltip(e.trigger, 'Failed!');
  hideTooltip(e.trigger);
});

// Enable Popovers
$(function () {
  $('[data-toggle="popover"]').popover()
});
</script>
<script src="js/confirm_modal.js"></script>
</body>
</html>
