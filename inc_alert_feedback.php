<?php

//Alert Feedback
if(!empty($_SESSION['alert_message'])){
    if (!isset($_SESSION['alert_type'])){
        $_SESSION['alert_type'] = "info";
    }
  ?>
    <div class="alert alert-<?php echo $_SESSION['alert_type']; ?>" id="alert">
      <?php echo $_SESSION['alert_message']; ?>
      <button class='close' data-dismiss='alert'>&times;</button>
    </div>
  <?php
  
  unset($_SESSION['alert_type']);
  unset($_SESSION['alert_message']);

}

//Set Records Per Page
if(empty($_SESSION['records_per_page'])){
  $_SESSION['records_per_page'] = 10;
}

?>