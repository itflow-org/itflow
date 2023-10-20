<?php

require_once "config.php";

require_once "functions.php";


$company_id = 1;

        /*
         * ###############################################################################################################
         *  REFRESH DATA 
         * ###############################################################################################################
         */
        // 2023-02-20 JQ Commenting this code out as its intermitently breaking cron executions, investigating
        // ERROR
        // php cron.php 
        // PHP Fatal error:  Uncaught TypeError: mysqli_fetch_array(): Argument #1 ($result) must be of type mysqli_result, bool given in cron.php:141
        // Stack trace:
        //#0 cron.php(141): mysqli_fetch_array()
        //#1 {main}
        //  thrown in cron.php on line 141
        // END ERROR
        // REFRESH DOMAIN WHOIS DATA (1 a day)
        //  Get the oldest updated domain (MariaDB shows NULLs first when ordering by default)
        $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT domain_id, domain_name FROM `domains` WHERE company_id = $company_id ORDER BY domain_updated_at LIMIT 1"));

        if ($row) {
            $domain_id = intval($row['domain_id']);
            $domain_name = sanitizeInput($row['domain_name']);

            $expire = getDomainExpirationDate($domain_name);
            $records = getDomainRecords($domain_name);
            $a = sanitizeInput($records['a']);
            $ns = sanitizeInput($records['ns']);
            $mx = sanitizeInput($records['mx']);
            $txt = sanitizeInput($records['txt']);
            $whois = sanitizeInput($records['whois']);

            // Update the domain
            mysqli_query($mysqli, "UPDATE domains SET domain_name = '$domain_name',  domain_expire = '$expire', domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois' WHERE domain_id = $domain_id");
        }


        // TODO: Re-add the cert refresher



        /*
         * ###############################################################################################################
         *  ACTION DATA
         * ###############################################################################################################
         */

        // GET NOTIFICATIONS

        // DOMAINS EXPIRING

        $domainAlertArray = [1,7,14,30,90,120];

        foreach ($domainAlertArray as $day) {

            //Get Domains Expiring
            $sql = mysqli_query(
                $mysqli,
                "SELECT * FROM domains
                LEFT JOIN clients ON domain_client_id = client_id 
                WHERE domain_expire = CURDATE() + INTERVAL $day DAY
                AND domains.company_id = $company_id"
            );

            while ($row = mysqli_fetch_array($sql)) {
                $domain_id = intval($row['domain_id']);
                $domain_name = sanitizeInput($row['domain_name']);
                $domain_expire = sanitizeInput($row['domain_expire']);
                $client_id = intval($row['client_id']);
                $client_name = sanitizeInput($row['client_name']);

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Domain', notification = 'Domain $domain_name for $client_name will expire in $day Days on $domain_expire', notification_client_id = $client_id, company_id = $company_id");

            }

        }