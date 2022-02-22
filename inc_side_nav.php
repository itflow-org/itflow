<?php 
    
if(basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) == "client.php"){
  include("client_side_nav.php");
//}elseif(basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) == "settings-general.php"){
  //include("admin_side_nav.php");
}else{
  include("side_nav.php");
} 

?>