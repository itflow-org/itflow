<?php
/*
 * Client Portal
 * OAuth Login via Microsoft IDP
 */

require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION)) {
    // HTTP Only cookies
    ini_set("session.cookie_httponly", true);
    if ($config_https_only) {
        // Tell client to only send cookie(s) over HTTPS
        ini_set("session.cookie_secure", true);
    }
    session_start();
}

// Set Timezone after session starts
require_once "../inc_set_timezone.php";

$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);

$sql_settings = mysqli_query($mysqli, "SELECT config_azure_client_id, config_azure_client_secret FROM settings WHERE company_id = 1");
$settings = mysqli_fetch_array($sql_settings);

$client_id = $settings['config_azure_client_id'];
$client_secret = $settings['config_azure_client_secret'];

$redirect_uri = "https://$config_base_url/portal/login_microsoft.php";

# https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow
$auth_code_url = "https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize";
$token_grant_url = "https://login.microsoftonline.com/organizations/oauth2/v2.0/token";

// Initial Login Request, via Microsoft
// Returns an authorization code if login was successful
if ($_SERVER['REQUEST_METHOD'] == "GET") {

    $params = array (
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'response_mode' =>'form_post',
        'scope' => 'https://graph.microsoft.com/User.Read',
        'state' => session_id());

    header('Location: '.$auth_code_url.'?'.http_build_query($params));

}

// Login was successful, Microsoft has returned us an authorization code via POST
// Request an access token using authorization code (& client secret) (server side)
if (isset($_POST['code']) && $_POST['state'] == session_id()) {

    $params = array (
        'client_id' =>$client_id,
        'code' => $_POST['code'],
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
            echo "Error with MS Graph API. Details:";
            var_dump($msgraph_response['error']);
            exit();

        } elseif (isset($msgraph_response['id'])) {

            $upn = mysqli_real_escape_string($mysqli, $msgraph_response["userPrincipalName"]);

            $sql = mysqli_query($mysqli, "SELECT * FROM users LEFT JOIN contacts ON user_id = contact_user_id WHERE user_email = '$upn' AND user_archived_at IS NULL AND user_type = 2 AND user_status = 1 LIMIT 1");
            $row = mysqli_fetch_array($sql);
            $client_id = intval($row['contact_client_id']);
            $user_id = intval($row['user_id']);
            $contact_id = intval($row['contact_id']);
            $user_email = sanitizeInput($row['user_email']);
            $user_auth_method = sanitizeInput($row['user_auth_method']);

            if ($user_auth_method == 'azure') {

                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_id'] = $client_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['contact_id'] = $contact_id;
                $_SESSION['login_method'] = "azure";

                // Logging
                logAction("Client Login", "Success", "Client contact $upn successfully logged in via Entra", $client_id, $user_id);

                header("Location: index.php");

            } else {
                
                $_SESSION['login_message'] = 'Something went wrong with logging you in: Your account is not configured for Azure SSO. Please ensure you are setup in ITFlow as a contact and have Azure SSO configured.';
                
                header("Location: index.php");
            }
        
        }
        
        header('Location: index.php');
    
    } else {
        
        echo "Error getting access_token";
    
    }

}

// If the user is just sat on the page, redirect them to log in to try again
if (empty($_GET)) {
    echo "<script> setTimeout(function() { window.location = \"login.php\"; },1000);</script>";
}
