<?php

// Request/client detection (IP, UA, browser, OS) and response helpers
// Split from the former monolithic functions.php


function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'];
}

function getIP() {

    // Default way to get IP
    $ip = $_SERVER['REMOTE_ADDR'];

    // Allow overrides via config.php in-case we use a proxy - https://docs.itflow.org/config_php
    if (defined("CONST_GET_IP_METHOD") && CONST_GET_IP_METHOD == "HTTP_X_FORWARDED_FOR") {
        $ip = explode(',', getenv('HTTP_X_FORWARDED_FOR'))[0] ?? $_SERVER['REMOTE_ADDR'];
    } elseif (defined("CONST_GET_IP_METHOD") && CONST_GET_IP_METHOD == "HTTP_CF_CONNECTING_IP") {
        $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER['REMOTE_ADDR'];
    }

    // Abort if something isn't right
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        error_log("ITFlow - Could not validate remote IP address");
        error_log("ITFlow - IP was [$ip] using method " . CONST_GET_IP_METHOD);
        exit("Potential Security Violation");
    }

    return $ip;
}

function getWebBrowser($user_browser) {
    $browser        =   "-";
    $browser_array  =   array(
        '/msie/i'       =>  "<i class='fab fa-fw fa-internet-explorer text-secondary'></i> Internet Explorer",
        '/firefox/i'    =>  "<i class='fab fa-fw fa-firefox text-secondary'></i> Firefox",
        '/safari/i'     =>  "<i class='fab fa-fw fa-safari text-secondary'></i> Safari",
        '/chrome/i'     =>  "<i class='fab fa-fw fa-chrome text-secondary'></i> Chrome",
        '/edg/i'        =>  "<i class='fab fa-fw fa-edge text-secondary'></i> Edge",
        '/opr/i'        =>  "<i class='fab fa-fw fa-opera text-secondary'></i> Opera",
        '/ddg/i'        =>  "<i class='fas fa-fw fa-globe text-secondary'></i> DuckDuckGo"
    );
    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_browser)) {
            $browser    =   $value;
        }
    }
    return $browser;
}

function getOS($user_os) {
    $os_platform    =   "-";
    $os_array       =   array(
        '/windows/i'            =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows",
        '/macintosh|mac os x/i' =>  "<i class='fab fa-fw fa-apple text-secondary'></i> MacOS",
        '/linux/i'              =>  "<i class='fab fa-fw fa-linux text-secondary'></i> Linux",
        '/ubuntu/i'             =>  "<i class='fab fa-fw fa-ubuntu text-secondary'></i> Ubuntu",
        '/fedora/i'             =>  "<i class='fab fa-fw fa-fedora text-secondary'></i> Fedora",
        '/iphone/i'             =>  "<i class='fab fa-fw fa-apple text-secondary'></i> iPhone",
        '/ipad/i'               =>  "<i class='fab fa-fw fa-apple text-secondary'></i> iPad",
        '/android/i'            =>  "<i class='fab fa-fw fa-android text-secondary'></i> Android"
    );
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_os)) {
            $os_platform    =   $value;
        }
    }
    return $os_platform;
}

function isMobile()
{
    // Check if the user agent is a mobile device
    return preg_match('/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|opera mini|palm|phone|pie|tablet|up.browser|up.link|webos|wos)/i', $_SERVER['HTTP_USER_AGENT']);
}

// Redirect Function
function redirect($url = null, $permanent = false) {
    // Use referer if no URL is provided
    if (!$url) {
        $url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    }

    if (!headers_sent()) {
        header('Location: ' . $url, true, $permanent ? 301 : 302);
        exit;
    } else {
        // Fallback for headers already sent
        echo "<script>window.location.href = '" . addslashes($url) . "';</script>";
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
        exit;
    }
}

//Flash Alert Function
function flash_alert(string $message, string $type = 'success'): void {
    $_SESSION['alert_type'] = $type;
    $_SESSION['alert_message'] = $message;
}
