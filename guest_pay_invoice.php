<?php 

// Still in development, for use with Stripe Pay - exit
exit();

include("config.php");

session_start();

if(isset($_POST['pay_invoice'])){
  
  $email = mysqli_real_escape_string($mysqli,$_POST['email']);
  $password = md5(mysqli_real_escape_string($mysqli,$_POST['password']));

  $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE email = '$email' AND password = '$password'");
  
  if(mysqli_num_rows($sql) == 1){
    $row = mysqli_fetch_array($sql);
    $_SESSION['logged'] = TRUE;
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['name'] = $row['name'];
    
    header("Location: $config_start_page");
  }else{
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
  <meta name="description" content="">
  <meta name="author" content="">

  <title><?php echo $config_company_name; ?> | Pay Invoice</title>

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">

  <link href="css/style.css" rel="stylesheet">

</head>

<body class="bg-secondary">

  <div class="container">
    <div class="card card-login mx-auto mt-5 bg-dark">
      <div class="card-header mt-2 text-white text-center"><h3>Invoice 103</h3></div>
      <div class="card-body bg-white">
        <center class="mb-3">
          <i class="fab fa-fw fa-3x fa-cc-visa"></i> 
          <i class="fab fa-fw fa-3x fa-cc-mastercard"></i> 
          <i class="fab fa-fw fa-3x fa-cc-discover"></i> 
          <i class="fab fa-fw fa-3x fa-cc-amex"></i>
        </center>
        <?php if(isset($response)) { echo $response; } ?>
        <form method="post">
          <div class="form-group">      
            <label>Name on card</label>
            <input type="text" name="name" class="form-control" placeholder="" required autofocus="autofocus">
          </div>
          <div class="form-group">      
            <label>Card number</label>
            <input type="text" name="card_number" class="form-control" placeholder="" required>
          </div>
          <div class="form-row">
            <div class="form-group col">      
              <label>Expiration</label>
              <input type="text" name="expiration" class="form-control" placeholder="MM / YY" required>
            </div>

            <div class="form-group col">      
              <label>Security code</label>
              <input type="text" name="security_code" class="form-control" placeholder="" required>
            </div>
          </div>
          <div class="form-group">      
            <label>Postal code</label>
            <input type="text" name="postal_code" class="form-control" placeholder="" required>
          </div>
          <button class="btn btn-success btn-block" type="submit" name="pay_invoice">Pay <strong>$100.00</strong></button>
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
