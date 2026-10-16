<?php

/*
 * ITFlow - GET/POST request handler for misc (functionality that doesn't quite fit elsewhere)
 */

// Records to show per page

if(isset($_POST['change_records_per_page'])){

    $records_per_page = intval($_POST['change_records_per_page']);

    mysqli_query($mysqli,"UPDATE user_settings SET user_config_records_per_page = $records_per_page WHERE user_id = $session_user_id");

    redirect();

}
