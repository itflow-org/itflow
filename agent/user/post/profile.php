<?php

/*
 * ITFlow - GET/POST request handler for an agent's own account
 *
 * Their details, picture and signature; their password and MFA; their
 * preferences. Changing what signs them in - the email, the password - and
 * turning MFA off take their current password, so a session left open is not
 * enough to take the account over; the old address is told when the email
 * changes, and they are emailed when the password changes or MFA goes off.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** Is this the signed in agent's current password? */
function userCurrentPasswordOk($password) {
    global $mysqli, $session_user_id;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_password FROM users WHERE user_id = " . intval($session_user_id)));
    return $row && password_verify((string) $password, $row['user_password']);
}

if (isset($_POST['edit_your_user_details'])) {

    validateCSRFToken();

    $me = userRow($session_user_id);

    $error = '';
    $identity = userCheckIdentity($_POST, $session_user_id, $error);
    if (!$identity) {
        flashAlert($error, 'error');
        redirect();
    }
    [$name, $email] = $identity;

    $email_changed = strcasecmp($email, $me['user_email']) !== 0;
    if ($email_changed && !userCurrentPasswordOk($_POST['current_password'] ?? '')) {
        flashAlert('Your email signs you in - enter your current password to change it.', 'error');
        redirect();
    }

    // The signature goes on emailed replies - kept to HTML that is safe to send
    $signature = mysqli_real_escape_string($mysqli, documentHtmlPurify((string) ($_POST['signature'] ?? '')));

    mysqli_query($mysqli, "UPDATE users SET user_name = '" . escapeSql($name) . "', user_email = '" . escapeSql($email) . "' WHERE user_id = $session_user_id");
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $session_user_id, user_config_signature = '$signature' ON DUPLICATE KEY UPDATE user_config_signature = '$signature'");

    $changes = [];
    if ($email_changed) {
        userNotifyChange($name, $me['user_email'], 'its email address is now ' . $email);
        $changes[] = 'email changed';
    }
    $avatar_error = '';
    if (!empty($_POST['remove_avatar'])) {
        userRemoveAvatar($session_user_id);
        $changes[] = 'picture removed';
    } elseif (!empty($_FILES['avatar']['tmp_name']) && userSaveAvatar($session_user_id, $_FILES['avatar'], $avatar_error)) {
        $changes[] = 'picture updated';
    }

    logAudit("User Account", "Edit", "$session_name edited their account" . ($changes ? ' - ' . escapeSql(implode(', ', $changes)) : ''));

    flashAlert("Your details are saved" . ($avatar_error ? " - $avatar_error" : ''), $avatar_error ? 'warning' : 'success');

    redirect();

}

if (isset($_GET['clear_your_user_avatar'])) {

    validateCSRFToken();

    userRemoveAvatar($session_user_id);

    logAudit("User Account", "Edit", "$session_name cleared their avatar");

    flashAlert("Picture removed");

    redirect();

}

if (isset($_POST['edit_your_user_password'])) {

    validateCSRFToken();

    $new_password = (string) ($_POST['new_password'] ?? '');

    if (!userCurrentPasswordOk($_POST['current_password'] ?? '')) {
        flashAlert('That is not your current password - nothing was changed.', 'error');
        redirect('user_security.php');
    }
    if ($problem = userPasswordProblem($new_password)) {
        flashAlert($problem, 'error');
        redirect('user_security.php');
    }
    if ($new_password !== (string) ($_POST['confirm_password'] ?? '')) {
        flashAlert('The new password and its confirmation are not the same.', 'error');
        redirect('user_security.php');
    }

    $me = userRow($session_user_id);
    userSetPassword($session_user_id, $new_password);
    userNotifyChange($me['user_name'], $me['user_email'], 'its password was changed');

    logAudit("User Account", "Edit", "$session_name changed their password");

    flashAlert("Your password was changed - sign in with the new one");

    redirect('post.php?logout');
}

if (isset($_POST['edit_your_user_preferences'])) {

    validateCSRFToken();

    $calendar_first_day = intval($_POST['calendar_first_day'] ?? 0) === 1 ? 1 : 0;
    $dark_mode = intval($_POST['dark_mode'] ?? 0) === 1 ? 1 : 0;
    $per_page = intval($_POST['records_per_page'] ?? 10);
    if (!in_array($per_page, [5, 10, 20, 50, 100, 500], true)) {
        $per_page = 10;
    }

    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $session_user_id, user_config_calendar_first_day = $calendar_first_day, user_config_theme_dark = $dark_mode, user_config_records_per_page = $per_page
        ON DUPLICATE KEY UPDATE user_config_calendar_first_day = $calendar_first_day, user_config_theme_dark = $dark_mode, user_config_records_per_page = $per_page");

    logAudit("User Account", "Edit", "$session_name updated their preferences");

    flashAlert("Your preferences are saved");

    redirect();

}

if (isset($_POST['enable_mfa'])) {

    validateCSRFToken();

    require_once "../../libs/totp/totp.php";

    // Grab the code from the user
    $verify_code = preg_replace('/\s+/', '', (string) ($_POST['verify_code'] ?? ''));
    if (!preg_match('/^[0-9]{6}$/', $verify_code)) {
        $verify_code = '';
    }

    // Grab the secret from the session
    $token = $_SESSION['mfa_token'] ?? '';
    $from_enforcement = isset($_SERVER['HTTP_REFERER']) && basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH)) === 'mfa_enforcement.php';

    if ($token !== '' && $verify_code !== '' && TokenAuth6238::verify($token, $verify_code, 1)) {

        mysqli_query($mysqli, "UPDATE users SET user_token = '" . escapeSql($token) . "' WHERE user_id = $session_user_id");

        // Delete any existing MFA tokens - these browsers should be re-validated
        mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $session_user_id");

        logAudit("User Account", "Edit", "$session_name enabled MFA on their account");

        flashAlert("Multi-factor authentication is on - you will be asked for a code when you sign in");

        // Clear the mfa_token from the session to avoid re-use.
        unset($_SESSION['mfa_token']);

        if ($from_enforcement) {
            redirect("../$config_start_page");
        }

    } else {

        flashAlert("That code did not match - check the time on your phone, and try the newest code.", 'error');

        // Open the set up again, with the same secret
        $_SESSION['show_mfa_modal'] = true;

        if ($from_enforcement) {
            redirect();
        }
    }

    redirect("user_security.php");

}

if (isset($_POST['disable_your_mfa'])) {

    validateCSRFToken();

    if ($session_user_config_force_mfa) {
        flashAlert("Your administrator requires multi-factor authentication on your account - it cannot be turned off.", 'error');
        redirect("user_security.php");
    }
    if (!userCurrentPasswordOk($_POST['current_password'] ?? '')) {
        flashAlert('That is not your current password - MFA is still on.', 'error');
        redirect("user_security.php");
    }

    $me = userRow($session_user_id);
    userResetMfa($session_user_id);
    userNotifyChange($me['user_name'], $me['user_email'], 'multi-factor authentication was turned off');

    logAudit("User Account", "Edit", "$session_name disabled MFA on their account");

    flashAlert("Multi-factor authentication is off", 'error');

    redirect("user_security.php");

}

if (isset($_POST['revoke_your_2fa_remember_tokens'])) {

    validateCSRFToken();

    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $session_user_id");

    logAudit("User Account", "Edit", "$session_name revoked all their remember-me tokens");

    flashAlert("Every remembered browser is forgotten - each asks for your code at the next sign in");

    redirect("user_security.php");

}

if (isset($_POST['revoke_your_remember_token'])) {

    validateCSRFToken();

    $token_id = intval($_POST['revoke_your_remember_token']);
    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_id = $token_id AND remember_token_user_id = $session_user_id");

    if (mysqli_affected_rows($mysqli)) {
        logAudit("User Account", "Edit", "$session_name revoked a remember-me token");
        flashAlert("That browser is forgotten - it asks for your code at the next sign in");
    }

    redirect("user_security.php");

}
