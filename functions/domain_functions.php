<?php 

// Domain related functions

// Get domain expiration date
function getDomainExpirationDate($name)
{

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        return "NULL";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://lookup.itflow.org:8080/$name");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = json_decode(curl_exec($ch), 1);

    if ($response) {
        if (is_array($response['expiration_date'])) {
            $expiry = new DateTime($response['expiration_date'][1]);
        } elseif (isset($response['expiration_date'])) {
            $expiry = new DateTime($response['expiration_date']);
        } else {
            return "NULL";
        }

        return $expiry->format('Y-m-d');
    }

    // Default return
    return "NULL";
}

// Get domain general info (whois + NS/A/MX records)
function getDomainRecords($name)
{

    $records = array();

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $records['a'] = '';
        $records['ns'] = '';
        $records['mx'] = '';
        $records['whois'] = '';
        return $records;
    }

    $domain = escapeshellarg(str_replace('www.', '', $name));
    $records['a'] = substr(trim(strip_tags(shell_exec("dig +short $domain"))), 0, 254);
    $records['ns'] = substr(trim(strip_tags(shell_exec("dig +short NS $domain"))), 0, 254);
    $records['mx'] = substr(trim(strip_tags(shell_exec("dig +short MX $domain"))), 0, 254);
    $records['txt'] = substr(trim(strip_tags(shell_exec("dig +short TXT $domain"))), 0, 254);
    $records['whois'] = substr(trim(strip_tags(shell_exec("whois -H $domain | sed 's/   //g' | head -30"))), 0, 254);

    return $records;
}

// Used to automatically attempt to get SSL certificates as part of adding domains
// The logic for the fetch (sync) button on the client_certificates page is in ajax.php, and allows ports other than 443
function getSSL($name)
{

    $certificate = array();
    $certificate['success'] = false;

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $certificate['expire'] = '';
        $certificate['issued_by'] = '';
        $certificate['public_key'] = '';
        return $certificate;
    }

    // Get SSL/TSL certificate (using verify peer false to allow for self-signed certs) for domain on default port
    $socket = "ssl://$name:443";
    $get = stream_context_create(array("ssl" => array("capture_peer_cert" => true, "verify_peer" => false,)));
    $read = stream_socket_client($socket, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $get);

    // If the socket connected
    if ($read) {
        $cert = stream_context_get_params($read);
        $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
        openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

        if ($cert_public_key_obj) {
            $certificate['success'] = true;
            $certificate['expire'] = date('Y-m-d', $cert_public_key_obj['validTo_time_t']);
            $certificate['issued_by'] = strip_tags($cert_public_key_obj['issuer']['O']);
            $certificate['public_key'] = $export;
        }
    }

    return $certificate;
}
