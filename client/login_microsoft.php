<?php
/*
 * Client Portal
 * OAuth Login via Microsoft IDP
 */

require_once '../config.php';
require_once '../functions.php';

require_once __DIR__ . "/../includes/session_init.php";

// Set Timezone after session starts
require_once "../includes/inc_set_timezone.php";

$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT']);

$sql_settings = mysqli_query($mysqli, "SELECT config_azure_client_id, config_azure_client_secret FROM settings WHERE company_id = 1");
$settings = mysqli_fetch_assoc($sql_settings);

$client_id = $settings['config_azure_client_id'];
$client_secret = $settings['config_azure_client_secret'];

$redirect_uri = "https://$config_base_url/client/login_microsoft.php";

# https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow
$auth_code_url = "https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize";
$token_grant_url = "https://login.microsoftonline.com/organizations/oauth2/v2.0/token";

// Initial Login Request, via Microsoft
// Returns an authorization code if login was successful
if ($_SERVER['REQUEST_METHOD'] == "GET" && !isset($_GET['code']) && !isset($_GET['error'])) {

    // Single-use random state held server side. Never the session ID - that
    // would put it in the URL, browser history and Microsoft's logs.
    try {
        $state = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $state = sha1(uniqid((string) mt_rand(), true));
    }

    $_SESSION['azure_oauth_state'] = $state;
    $_SESSION['azure_oauth_state_expires_at'] = time() + 600;

    $params = array (
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        // Must come back as a top-level GET - a SameSite=Lax session cookie is
        // not sent on the cross-site POST that form_post produces
        'response_mode' => 'query',
        'scope' => 'https://graph.microsoft.com/User.Read',
        'state' => $state);

    header('Location: '.$auth_code_url.'?'.http_build_query($params));
    exit();

}

// Microsoft has redirected back with an authorization code (or an error)
// Request an access token using authorization code (& client secret) (server side)
if (isset($_GET['code']) || isset($_GET['error'])) {

    $state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
    $session_state = $_SESSION['azure_oauth_state'] ?? '';
    $session_state_expires = intval($_SESSION['azure_oauth_state_expires_at'] ?? 0);

    // Single use, consumed whether or not it validates
    unset($_SESSION['azure_oauth_state'], $_SESSION['azure_oauth_state_expires_at']);

    if (!empty($_GET['error'])) {
        $_SESSION['login_message'] = 'Something went wrong with logging you in: Microsoft returned an error. Please try again.';
        header("Location: ../login.php");
        exit();
    }

    if (empty($state) || empty($session_state) || !hash_equals($session_state, $state) || time() > $session_state_expires) {
        $_SESSION['login_message'] = 'Something went wrong with logging you in: the sign-in request could not be verified. Please try again.';
        header("Location: ../login.php");
        exit();
    }

    $params = array (
        'client_id' =>$client_id,
        'code' => is_string($_GET['code'] ?? null) ? $_GET['code'] : '',
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
        'client_secret' => $client_secret
    );

    // Send request via CURL (server side) so user cannot see the client secret
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_grant_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        http_build_query($params)
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    #curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // DEBUG ONLY - WAMP

    $access_token_response = json_decode(curl_exec($ch), 1);

    // Check if we have an access token
    // If we do, send a request to Microsoft Graph API to get user info
    if (isset($access_token_response['access_token'])) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Authorization: Bearer '.$access_token_response['access_token'],
            'Content-type: application/json'));
        curl_setopt($ch, CURLOPT_URL, "https://graph.microsoft.com/v1.0/me/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        #curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // DEBUG ONLY - WAMP

        $msgraph_response = json_decode(curl_exec($ch), 1);

        if (isset($msgraph_response['error'])) {
            // Something went wrong verifying the token/using the Graph API - quit
            error_log("ITFlow: MS Graph API error during client portal Entra login: " . json_encode($msgraph_response['error']));
            $_SESSION['login_message'] = 'Something went wrong with logging you in: could not read your profile from Microsoft. Please try again.';
            header("Location: ../login.php");
            exit();

        } elseif (isset($msgraph_response['id'])) {

            $upn = mysqli_real_escape_string($mysqli, $msgraph_response["userPrincipalName"]);

            $sql = mysqli_query($mysqli, "SELECT * FROM users
                LEFT JOIN contacts ON user_id = contact_user_id
                LEFT JOIN clients ON contact_client_id = client_id
                WHERE user_email = '$upn'
                AND user_archived_at IS NULL
                AND client_archived_at IS NULL
                AND user_type = 2
                AND user_status = 1
                LIMIT 1"
            );
            $row = mysqli_fetch_assoc($sql);
            $client_id = intval($row['contact_client_id']);
            $user_id = intval($row['user_id']);
            $session_user_id = $user_id; // to pass the user_id to logAction function
            $contact_id = intval($row['contact_id']);
            $user_email = escapeSql($row['user_email']);
            $user_auth_method = escapeSql($row['user_auth_method']);

            if ($user_auth_method == 'azure') {

                // New session ID for the authenticated session (CWE-384)
                session_regenerate_id(true);

                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_id'] = $client_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_type'] = 2;
                $_SESSION['contact_id'] = $contact_id;
                $_SESSION['csrf_token'] = randomString(32);
                $_SESSION['login_method'] = "azure";

                // Logging
                logAudit("Client Login", "Success", "Client contact $upn successfully logged in via Entra", $client_id, $user_id);

                header("Location: index.php");

            } else {

                $_SESSION['login_message'] = 'Something went wrong with logging you in: Your account is not configured for Entra SSO. Please ensure you are setup in ITFlow as a contact and have Entra SSO configured.';

                header("Location: ../login.php");
            }

            exit();

        }

        header('Location: index.php');
        exit();

    } else {

        error_log("ITFlow: no access_token returned during client portal Entra login");
        $_SESSION['login_message'] = 'Something went wrong with logging you in: Microsoft did not return an access token. Please try again.';
        header("Location: ../login.php");
        exit();

    }

}

// If the user is just sat on the page, send them back to log in to try again
header("Location: ../login.php");
exit();
