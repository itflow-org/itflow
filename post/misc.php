<?php

/*
 * ITFlow - GET/POST request handler for misc (functionality that doesn't quite fit elsewhere)
 */

// Records to show per page

if(isset($_POST['change_records_per_page'])){

    validateCSRFToken();

    // One of the sizes the list footer offers (includes/filter_footer.php) - 0 would break every list
    $records_per_page = intval($_POST['change_records_per_page']);
    if (!in_array($records_per_page, [5, 10, 20, 50, 100, 500], true)) {
        redirect();
    }

    mysqli_query($mysqli,"UPDATE user_settings SET user_config_records_per_page = $records_per_page WHERE user_id = $session_user_id");

    redirect();

}
