<?php

//Alert Feedback
if(!empty($_SESSION['alert_message'])){
    if (!isset($_SESSION['alert_type'])){
        $_SESSION['alert_type'] = "success";
    }
  ?>

    <script type="text/javascript">toastr.<?php echo $_SESSION['alert_type']; ?>("<?php echo $_SESSION['alert_message']; ?>")</script>

  <?php

  unset($_SESSION['alert_type']);
  unset($_SESSION['alert_message']);

}

//Set Records Per Page
if(empty($_SESSION['records_per_page'])){
  $_SESSION['records_per_page'] = 10;
}

?>