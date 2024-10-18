<?php

/*
 * ITFlow - Logout
 */

if (isset($_GET['logout'])) {
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Logout', log_action = 'Success', log_description = '$session_name logged out', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
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
        header('Location: login.php?key=' . $config_login_key_secret);
    } else {
        header('Location: login.php');
    }
}

?>
