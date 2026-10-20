<?php

/*
 * What the server needs for ITFlow, and what it should have - the Checks
 * step of the setup wizard (setup/index.php). Each check is a label, whether
 * it passed, what was found, and what to do when it didn't. Required ones
 * stop setup until they pass; recommended ones are what ITFlow works better
 * with - HTTPS, a real domain name, room to upload a backup, and what
 * one-click updates use.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

/** The PHP version ITFlow needs. */
const SETUP_PHP_MINIMUM = '8.2.0';

/** What a backup or a large file upload needs, in bytes - 500 MB. */
const SETUP_UPLOAD_RECOMMENDED = 500 * 1024 * 1024;

/*
 * The PHP extensions ITFlow can't run without - name => what it is for.
 * mysqli the database, intl money and dates, curl mail and outside calls,
 * mbstring text, gd images, xml mail and documents, openssl the encrypted
 * passwords and 2FA codes, fileinfo checking what an upload really is.
 */
function setupRequiredExtensions() {
    return [
        'mysqli' => 'the database',
        'intl' => 'money, numbers and dates in your language',
        'curl' => 'mail and the services ITFlow talks to',
        'mbstring' => 'text in any language',
        'gd' => 'logos, avatars and images',
        'xml' => 'mail and documents',
        'openssl' => 'the encrypted passwords and 2FA codes',
        'fileinfo' => 'checking what an uploaded file really is',
    ];
}

/* One check - its label, whether it passed, what was found, and how to fix it. */
function setupCheck($label, $passed, $value, $fix = '') {
    return ['label' => $label, 'passed' => (bool) $passed, 'value' => (string) $value, 'fix' => $passed ? '' : $fix];
}

/*
 * The site's address as the browser asked for it - its host without a port,
 * and whether it came over HTTPS (directly, or through a proxy that says so).
 */
function setupRequestHost() {
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    return preg_replace('/:\d+$/', '', $host);
}

function setupRequestIsHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || intval($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/*
 * Whether the certificate this host serves is current - true, false, or null
 * when it can't be reached from here (behind a proxy, say). A few seconds at
 * most, so a closed port doesn't hold the page up.
 */
function setupCertificateValid($host) {
    if ($host === '') {
        return null;
    }
    $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $socket = @stream_socket_client("ssl://$host:443", $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return null;
    }
    $params = stream_context_get_params($socket);
    fclose($socket);
    $cert = $params['options']['ssl']['peer_certificate'] ?? null;
    $info = $cert ? openssl_x509_parse($cert) : false;
    if (!$info) {
        return null;
    }
    return time() >= intval($info['validFrom_time_t']) && time() <= intval($info['validTo_time_t']);
}

/*
 * Every check - ['required' => [...], 'recommended' => [...]], each a list of
 * setupCheck(). $root is ITFlow's folder: config.php is written into it, and
 * files go into its uploads folder. From the command line ($from_web false)
 * there is no request to say how the site is reached, so those are left out.
 */
function setupChecks($root, $from_web = true) {
    $required = [];
    $recommended = [];

    $required[] = setupCheck('PHP ' . SETUP_PHP_MINIMUM . ' or later', version_compare(PHP_VERSION, SETUP_PHP_MINIMUM, '>='), 'PHP ' . PHP_VERSION,
        'Upgrade PHP to ' . SETUP_PHP_MINIMUM . ' or later - your distribution\'s php8.x packages, or the ondrej/php repository on Debian and Ubuntu.');
    foreach (setupRequiredExtensions() as $extension => $what) {
        $loaded = extension_loaded($extension);
        $required[] = setupCheck("PHP $extension extension", $loaded, $loaded ? 'Installed' : 'Not installed',
            "Install it - <code>apt install php-$extension</code> on Debian and Ubuntu - and restart the web server. ITFlow uses it for $what.");
    }

    // config.php is written by the Database step, unless it is there already
    $config = $root . '/config.php';
    if (file_exists($config)) {
        $required[] = setupCheck('config.php', true, 'Already there');
    } else {
        $writable = is_writable($root);
        $required[] = setupCheck('ITFlow\'s folder can be written to', $writable, $writable ? 'Writable' : 'Not writable',
            'Setup writes config.php into <code>' . escapeHtml($root) . '</code>. Make the folder writable by the web server - <code>chown -R www-data:www-data ' . escapeHtml($root) . '</code> on Debian and Ubuntu.');
    }
    $uploads = $root . '/uploads';
    $writable = is_dir($uploads) && is_writable($uploads);
    $required[] = setupCheck('The uploads folder can be written to', $writable, $writable ? 'Writable' : (is_dir($uploads) ? 'Not writable' : 'Missing'),
        'Logos, avatars, attachments and files are kept in <code>' . escapeHtml($uploads) . '</code>. Create it if it is missing, and make it writable by the web server.');

    // How it is reached, and what the web server takes - neither known from the command line
    if ($from_web) {
        $host = setupRequestHost();
        $https = setupRequestIsHttps();
        $recommended[] = setupCheck('Reached over HTTPS', $https, $https ? 'Yes' : 'No',
            'Sign-ins, passwords and client data cross the network - put a certificate on the site (Let\'s Encrypt is free) and come back to it on https://. ITFlow only lets you sign in over HTTPS unless config.php says otherwise.');
        if ($https) {
            $valid = setupCertificateValid($host);
            if ($valid !== null) {
                $recommended[] = setupCheck('The certificate is current', $valid, $valid ? 'Valid' : 'Expired or not yet valid', 'Renew the site\'s certificate.');
            }
        }
        $fqdn = (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $host);
        $recommended[] = setupCheck('A domain name', $fqdn, $host !== '' ? $host : 'Unknown',
            'Clients follow links in ITFlow\'s emails and use the client portal - give it a domain name, like itflow.example.com, rather than an IP address or a local name.');

        // Room to upload a backup or a large file
        $upload_max = backupParseIniBytes(ini_get('upload_max_filesize'));
        $post_max = backupParseIniBytes(ini_get('post_max_size'));
        $recommended[] = setupCheck('Uploads of 500 MB', $upload_max >= SETUP_UPLOAD_RECOMMENDED && $post_max >= SETUP_UPLOAD_RECOMMENDED,
            'upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size'),
            'Restoring a backup and uploading large files need room - in php.ini set <code>upload_max_filesize = 500M</code> and <code>post_max_size = 500M</code>, and restart the web server.');
    }

    $zip = class_exists('ZipArchive');
    $recommended[] = setupCheck('PHP zip extension', $zip, $zip ? 'Installed' : 'Not installed',
        'Backups and client exports are zip files - install it, <code>apt install php-zip</code> on Debian and Ubuntu.');

    // One-click updates (Maintenance > Update) run git, through exec and shell_exec
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $exec_off = array_values(array_filter(['exec', 'shell_exec'], function ($function) use ($disabled) {
        return !function_exists($function) || in_array($function, $disabled, true);
    }));
    $git = commandPath('git');
    $recommended[] = setupCheck('git, for one-click updates', !$exec_off && $git !== '', $exec_off ? implode(' and ', $exec_off) . ' turned off' : ($git !== '' ? $git : 'Not found'),
        'Maintenance > Update fetches new versions with git. Install git' . ($exec_off ? ' and take ' . implode(' and ', $exec_off) . ' off disable_functions in php.ini' : '') . ' - or update by hand as the docs say.');

    return ['required' => $required, 'recommended' => $recommended];
}

/* How many of a list of checks failed. */
function setupChecksFailed(array $checks) {
    return count(array_filter($checks, function ($check) {
        return !$check['passed'];
    }));
}
