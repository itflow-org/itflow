<?php include("config.php"); ?>

<?php

// Check API key is provided in GET request as 'api_key'
if(!isset($_GET['api_key']) OR empty($_GET['api_key'])) {
    // Missing key
    header("HTTP/1.1 401 Unauthorized");
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'No Key', log_description = 'No API Key specified', log_created_at = NOW()");

    echo "Missing the API Key.";
    exit();
}

// Validate API key from GET request
$api_key = mysqli_real_escape_string($mysqli,$_GET['api_key']);
$sql = mysqli_query($mysqli,"SELECT * FROM api_keys, companies WHERE api_keys.company_id = companies.company_id AND api_keys.api_key_secret = '$api_key' AND api_key_expire > NOW()");
if(mysqli_num_rows($sql) != 1){
    // Invalid Key
    header("HTTP/1.1 401 Unauthorized");
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Incorrect Key', log_description = 'Failed', log_created_at = NOW()");

    echo "Incorrect or expired API Key.";
    exit();
}

// API Key is valid.

$row = mysqli_fetch_array($sql);
$company_id = $row['company_id'];

if(isset($_GET['cid'])){

    $cid = intval($_GET['cid']);

    $sql = mysqli_query($mysqli,"SELECT contact_name AS name FROM contacts WHERE contact_phone = $cid AND company_id = $company_id UNION SELECT contact_name AS name FROM contacts WHERE contact_mobile = $cid AND company_id = $company_id UNION SELECT location_name AS name FROM locations WHERE location_phone = $cid AND company_id = $company_id UNION SELECT vendor_name AS name FROM vendors WHERE vendor_phone = $cid AND company_id = $company_id");

    $row = mysqli_fetch_array($sql);
    $name = $row['name'];

    echo "$name - $cid";
    //Alert when call comes through
    mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Inbound Call', alert_message = 'Inbound call from $name - $cid', alert_date = NOW(), company_id = $company_id");
    //Log When call comes through
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Call', log_action = 'Inbound', log_description = 'Inbound call from $name - $cid', log_created_at = NOW(), company_id = $company_id");

}

if(isset($_GET['incoming_call'])){

    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'call', log_description = 'incoming', log_created_at = NOW(), company_id = $company_id");

}

if(isset($_GET['primary_contact_numbers'])){

    $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN contacts ON clients.primary_contact = contacts.contact_id WHERE clients.company_id = $company_id");

    while($row = mysqli_fetch_array($sql)){
        $client_name = $row['client_name'];
        $contact_name = $row['contact_name'];
        $contact_phone = $row['contact_phone'];
        $contact_mobile = $row['contact_mobile'];

        echo "$client_name - $contact_name - $contact_phone - $contact_mobile<br>";
    }

    //Log
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Client Numbers', log_description = 'Client Phone Numbers were pulled', log_created_at = NOW(), company_id = $company_id");

}

if(isset($_GET['phonebook'])){

    header('Content-type: text/xml');
    header('Pragma: public');
    header('Cache-control: private');
    header('Expires: -1');
    echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
    echo '<AddressBook>';

    $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN contacts ON clients.primary_contact = contacts.contact_id WHERE clients.company_id = $company_id");

    while($row = mysqli_fetch_array($sql)){
        $client_name = $row['client_name'];
        $contact_name = $row['contact_name'];
        $contact_phone = $row['contact_phone'];
        $contact_mobile = $row['contact_mobile'];

        ?>
        <Contact>
            <LastName><?php echo $contact_name; ?></LastName>
            <Phone>
                <phonenumber><?php echo $contact_phone; ?></phonenumber>
            </Phone>
            <Groups>
                <groupid>0</groupid>
            </Groups>
        </Contact>
        <?php
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE company_id = $company_id");

    while($row = mysqli_fetch_array($sql)){
        $vendor_name = $row['vendor_name'];
        $vendor_phone = $row['vendor_phone'];

        ?>
        <Contact>
            <LastName><?php echo $vendor_name; ?></LastName>
            <Phone>
                <phonenumber><?php echo $vendor_phone; ?></phonenumber>
            </Phone>
            <Groups>
                <groupid>1</groupid>
            </Groups>
        </Contact>

        <?php
    }

    echo '</AddressBook>';

    //Log
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Phonebook', log_description = 'XML Phonebook Downloaded', log_created_at = NOW(), company_id = $company_id");


}

if(isset($_GET['primary_contact_emails'])){

    $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN contacts ON clients.primary_contact = contacts.contact_id WHERE clients.company_id = $company_id");

    while($row = mysqli_fetch_array($sql)){
        $client_name = $row['client_name'];
        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];

        echo "$client_name - $contact_name - $contact_email<br>";
    }

    //Log
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Client Emails', log_description = 'Client Emails were pulled', log_created_at = NOW(), company_id = $company_id");


}

if(isset($_GET['account_balance'])){

    $client_id = intval($_GET['account_balance']);

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_invoice_amounts = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' AND company_id = $company_id");
    $row = mysqli_fetch_array($sql_invoice_amounts);

    $invoice_amounts = $row['invoice_amounts'];

    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoices.invoice_id AND invoice_client_id = $client_id AND payments.company_id = $company_id");
    $row = mysqli_fetch_array($sql_amount_paid);

    $amount_paid = $row['amount_paid'];

    $balance = $invoice_amounts - $amount_paid;

    echo $balance;

    //Log
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Account Balance', log_description = 'Client $client_id checked their balance which had a balance of $balance', log_created_at = NOW(), company_id = $company_id");

}

if(isset($_GET['add_asset']) && isset($_GET['client_id'])) {
    $client_id = intval($_GET['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['add_asset'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['type'])));
    $make = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['make'])));
    $model = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['model'])));
    $serial = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['serial'])));
    $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['os'])));

    // Add
    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_created_at = NOW(), asset_client_id = $client_id, company_id = $company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Asset Created', log_description = '$name', log_created_at = NOW(), company_id = $company_id");

    echo "Asset added!";
}


?>