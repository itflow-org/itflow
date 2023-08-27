<?php
if (!file_exists('config.php')) {
    header("Location: setup.php");
    exit;
}

include("config.php");
include("functions.php");
if(isset($_POST['update_forgot_password']) && $_POST['token'] && $_POST['email'])
{

$email = $_POST['email'];
$token = $_POST['token'];
$new_password = $_POST['new_password'];
$repeat_new_password = $_POST['repeat_new_password'];

if ($new_password === $repeat_new_password) {
$password = password_hash($new_password, PASSWORD_DEFAULT);

    $query = mysqli_query($mysqli, "SELECT * FROM `users` WHERE `user_email`='".$email."' ");
    $row = mysqli_num_rows($query);
    if ($row) {
        mysqli_query($mysqli, "UPDATE users set user_password='" . $password . "' WHERE user_email='" . $email . "'");

        // Redirect to Login with Success Status

       header("Location: login.php?status=password changed successfully");
    } 
    
   
    
}

if($new_password != $repeat_new_password){
    
        // Redirect to Login with Error Status       
     
      header("Location: login.php?status=Whooops! password does not match");


          
}
}
else{
    header("Location: login.php");
}
?>