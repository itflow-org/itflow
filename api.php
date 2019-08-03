<?php

include("config.php");

if($_GET['api_key'] == $config_api_key){

    if(isset($_GET['cid'])){

        $cid = intval($_GET['cid']);

        $sql = mysqli_query($mysqli,"SELECT client_name AS name FROM clients WHERE client_phone = $cid UNION SELECT contact_name AS name FROM contacts WHERE contact_phone = $cid UNION SELECT location_name AS name FROM locations WHERE location_phone = $cid UNION SELECT vendor_name AS name FROM vendors WHERE vendor_phone = $cid");

        $row = mysqli_fetch_array($sql);
        $name = $row['name'];

        echo $name;

    }

    if(isset($_GET['incoming_call'])){

        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'call', log_description = 'incoming', log_created_at = NOW()");

    }

    if(isset($_GET['client_numbers'])){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients;");

        while($row = mysqli_fetch_array($sql)){
            $client_name = $row['client_name'];
            $client_phone = $row['client_phone'];

            echo "$client_name - $client_phone<br>";
        }

    }

    if(isset($_GET['phonebook'])){

        header('Content-type: text/xml');
        header('Pragma: public');
        header('Cache-control: private');
        header('Expires: -1');
        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
        echo '<AddressBook>';

        $sql = mysqli_query($mysqli,"SELECT * FROM clients;");

        while($row = mysqli_fetch_array($sql)){
            $client_name = $row['client_name'];
            $client_phone = $row['client_phone'];

        ?>
            <Contact>
                <LastName><?php echo $client_name; ?></LastName>
                <Phone>
                    <phonenumber><?php echo $client_phone; ?></phonenumber>
                </Phone>
                <Groups>
                    <groupid>0</groupid>
                </Groups>
            </Contact>
        <?php
        }

        $sql = mysqli_query($mysqli,"SELECT * FROM contacts;");

        while($row = mysqli_fetch_array($sql)){
            $vendor_name = $row['contact_name'];
            $vendor_phone = $row['contact_phone'];

        ?>
            <Contact>
                <LastName><?php echo $contact_name; ?></LastName>
                <Phone>
                    <phonenumber><?php echo $contact_phone; ?></phonenumber>
                </Phone>
                <Groups>
                    <groupid>1</groupid>
                </Groups>
            </Contact>
        
        <?php
        }

        $sql = mysqli_query($mysqli,"SELECT * FROM vendors;");

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
                    <groupid>2</groupid>
                </Groups>
            </Contact>
        
        <?php
        }

        echo '</AddressBook>';

    }

    if(isset($_GET['client_emails'])){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients;");

        while($row = mysqli_fetch_array($sql)){
            $client_name = $row['client_name'];
            $client_email = $row['client_email'];

            echo "$client_name - $client_email<br>";
        }

    }

    if(isset($_GET['account_balance'])){

        $client_id = intval($_GET['account_balance']);

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_invoice_amounts = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled'");
        $row = mysqli_fetch_array($sql_invoice_amounts);

        $invoice_amounts = $row['invoice_amounts'];

        $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id");
        $row = mysqli_fetch_array($sql_amount_paid);

        $amount_paid = $row['amount_paid'];

        $balance = $invoice_amounts - $amount_paid;

        echo $balance;

    }

}else{
    header("Location: login.php");
}

?>