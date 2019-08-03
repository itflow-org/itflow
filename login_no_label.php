<?php include("config.php"); ?>

<?php

session_start();

if(isset($_POST['login'])){
  
  $email = mysqli_real_escape_string($mysqli,$_POST['email']);
  $password = md5(mysqli_real_escape_string($mysqli,$_POST['password']));
  $current_code = $_POST['current_code'];

  $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE email = '$email' AND password = '$password'");
  
  if(mysqli_num_rows($sql) == 1){
    $row = mysqli_fetch_array($sql);
    $token = $row['token'];
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['name'] = $row['name'];
    $_SESSION['client_id'] = $row['client_id'];
    $client_id = $row['client_id'];
    
    if(empty($token)){
      $_SESSION['logged'] = TRUE;
         
      if($client_id > 0){
        header("Location: client.php?client_id=$client_id");
      }else{
        header("Location: $config_start_page");
      }
    
    }else{
      require_once("rfc6238.php");

      if(TokenAuth6238::verify($token,$current_code)){
        $_SESSION['logged'] = TRUE;
        header("Location: $config_start_page");
      }
    }
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

  <title></title>

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">

  <link href="css/style.css" rel="stylesheet">

</head>

<body class="bg-secondary">

  <div class="container">
    <div class="card card-login mx-auto mt-5 bg-dark">
      
      <div class="card-body bg-white">
        <form method="post">
          <div class="form-group">
           
            <input type="email" name="email" class="form-control" required autofocus>
           
          </div>
          <div class="form-group">
           
            <input type="password" name="password" class="form-control" required>
            
          </div>
          <div class="form-group">
           
            <input type="text" name="current_code" class="form-control">
           
          </div>
          <button class="btn btn-dark btn-block p-4" type="submit" name="login"></button>
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
