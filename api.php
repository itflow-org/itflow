<?php

include("config.php");

if($_GET['api_key'] == $config_api_key){

    if(isset($_GET['cid'])){

        $cid = intval($_GET['cid']);

        $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_phone = $cid");

        $row = mysqli_fetch_array($sql);
        $client_name = $row['client_name'];

        echo $client_name;

    }

    if(isset($_GET['client_numbers'])){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients;");

        while($row = mysqli_fetch_array($sql)){
            $client_name = $row['client_name'];
            $client_phone = $row['client_phone'];

            echo "$client_name - $client_phone<br>";
        }

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
    echo "<h1> Ma!! You've been BAAAAADDDDD!! </h1>";
}

?>