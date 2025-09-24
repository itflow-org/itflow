<?php

/*
 * ITFlow - Logout
 */

if (isset($_GET['logout'])) {

    // Logging
    logAction("Logout", "Success", "$session_name logged out");
    
    mysqli_query($mysqli, "UPDATE users SET user_php_session = '' WHERE user_id = $session_user_id");

    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);

    setcookie("user_encryption_session_key", '', time() - 3600, "/");
    unset($_COOKIE['user_encryption_session_key']);

    setcookie("user_extension_key", '', time() - 3600, "/");
    unset($_COOKIE['user_extension_key']);

    session_unset();
    session_destroy();

    if ($config_login_key_required == 1) {
        header('Location: ../../login.php?key=' . $config_login_key_secret);
    } else {
        header('Location: ../../login.php');
    }
}

?>
