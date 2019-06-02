<?php

include("config.php");
include("functions.php");

$mpdf_path = (getenv('MPDF_ROOT')) ? getenv('MPDF_ROOT') : __DIR__;
require_once $mpdf_path . '/vendor/autoload.php';

if(isset($_GET['pdf_invoice'], $_GET['url_key'])){

    $invoice_id = intval($_GET['pdf_invoice']);
    $url_key = $_GET['url_key'];

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $invoice_id
    AND invoices.invoice_url_key = '$url_key'"
    );

    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $invoice_number = $row['invoice_number'];
    $invoice_status = $row['invoice_status'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_note = $row['invoice_note'];
    $invoice_category_id = $row['category_id'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_address = $row['client_address'];
    $client_city = $row['client_city'];
    $client_state = $row['client_state'];
    $client_zip = $row['client_zip'];
    $client_email = $row['client_email'];
    $client_phone = $row['client_phone'];
    if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
    }
    $client_website = $row['client_website'];

    if(mysqli_num_rows($sql) == 1){

        //Mark downloaded in history
        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$invoice_status', history_description = 'Invoice downloaded', invoice_id = $invoice_id");

        $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payments.account_id = accounts.account_id AND payments.invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_amount_paid);
        $amount_paid = $row['amount_paid'];

        $balance = $invoice_amount - $amount_paid;

        $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY item_id ASC");

        while($row = mysqli_fetch_array($sql_items)){
            $item_id = $row['item_id'];
            $item_name = $row['item_name'];
            $item_description = $row['item_description'];
            $item_quantity = $row['item_quantity'];
            $item_price = $row['item_price'];
            $item_subtotal = $row['item_price'];
            $item_tax = $row['item_tax'];
            $item_total = $row['item_total'];
            $total_tax = $item_tax + $total_tax;
            $sub_total = $item_price * $item_quantity + $sub_total;

            $invoice_items .= "
            <tr>
                <td align='center'>$item_name</td>
                <td>$item_description</td>
                <td align='center'>$item_quantity</td>
                <td class='cost'>$$item_price</td>
                <td class='cost'>$$item_tax</td>
                <td class='cost'>$$item_total</td>
            </tr>
            ";

        }

        $html = '
            <html>
            <head>
            <style>
            body {font-family: sans-serif;
              font-size: 10pt;
            }
            p { margin: 0pt; }
            table.items {
              border: 0.1mm solid #000000;
            }
            td { vertical-align: top; }
            .items td {
              border-left: 0.1mm solid #000000;
              border-right: 0.1mm solid #000000;
            }
            table thead td { background-color: #EEEEEE;
              text-align: center;
              border: 0.1mm solid #000000;
              font-variant: small-caps;
            }
            .items td.blanktotal {
              background-color: #EEEEEE;
              border: 0.1mm solid #000000;
              background-color: #FFFFFF;
              border: 0mm none #000000;
              border-top: 0.1mm solid #000000;
              border-right: 0.1mm solid #000000;
            }
            .items td.totals {
              text-align: right;
              border: 0.1mm solid #000000;
            }
            .items td.cost {
              text-align: "." center;
            }
            </style>
            </head>
            <body>
            <!--mpdf
            <htmlpageheader name="myheader">
            <table width="100%"><tr>
            <td width="15%"><img width="75" height="75" src=" '.$config_invoice_logo.' "></img></td>
            <td width="50%"><span style="font-weight: bold; font-size: 14pt;"> '.$config_company_name.' </span><br />' .$config_company_address.' <br /> '.$config_company_city.' '.$config_company_state.' '.$config_company_zip.'<br /> '.$config_company_phone.' </td>
            <td width="35%" style="text-align: right;">Invoice No.<br /><span style="font-weight: bold; font-size: 12pt;"> INV-'.$invoice_number.' </span></td>
            </tr></table>
            </htmlpageheader>
            <htmlpagefooter name="myfooter">
            <div style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; ">
            Page {PAGENO} of {nb}
            </div>
            </htmlpagefooter>
            <sethtmlpageheader name="myheader" value="on" show-this-page="1" />
            <sethtmlpagefooter name="myfooter" value="on" />
            mpdf-->
            <div style="text-align: right">Date: '.$invoice_date.'</div>
            <div style="text-align: right">Due: '.$invoice_due.'</div>
            <table width="100%" style="font-family: serif;" cellpadding="10"><tr>
            <td width="45%" style="border: 0.1mm solid #888888; "><span style="font-size: 7pt; color: #555555; font-family: sans;">BILL TO:</span><br /><br /><b> '.$client_name.' </b><br />'.$client_address.'<br />'.$client_city.' '.$client_state.' '.$client_zip.' <br /><br> '.$client_email.' <br /> '.$client_phone.'</td>
            <td width="65%">&nbsp;</td>

            </tr></table>
            <br />
            <table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse; " cellpadding="8">
            <thead>
            <tr>
            <td width="28%">Product</td>
            <td width="28%">Description</td>
            <td width="10%">Qty</td>
            <td width="10%">Price</td>
            <td width="12%">Tax</td>
            <td width="12%">Total</td>
            </tr>
            </thead>
            <tbody>
            '.$invoice_items.'
            <tr>
            <td class="blanktotal" colspan="4" rowspan="5"><h4>Notes</h4> '.$invoice_note.' </td>
            <td class="totals">Subtotal:</td>
            <td class="totals cost">$ '.number_format($sub_total,2).' </td>
            </tr>
            <tr>
            <td class="totals">Tax:</td>
            <td class="totals cost">$ '.number_format($total_tax,2).' </td>
            </tr>
            <tr>
            <td class="totals">Total:</td>
            <td class="totals cost">$ '.number_format($invoice_amount,2).' </td>
            </tr>
            <tr>
            <td class="totals">Paid:</td>
            <td class="totals cost">$ '.number_format($amount_paid,2).' </td>
            </tr>
            <tr>
            <td class="totals"><b>Balance:</b></td>
            <td class="totals cost"><b>$ '.number_format($balance,2).' </b></td>
            </tr>
            </tbody>
            </table>
            <div style="text-align: center; font-style: italic;"> '.$config_invoice_footer.' </div>
            </body>
            </html>
        ';

        $mpdf = new \Mpdf\Mpdf([
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 48,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10
        ]);

        $mpdf->SetProtection(array('print'));
        $mpdf->SetTitle("$config_company_name - Invoice");
        $mpdf->SetAuthor("$config_company_name");
        if($invoice_status == 'Paid'){
            $mpdf->SetWatermarkText("Paid");
        }
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'DejaVuSansCondensed';
        $mpdf->watermarkTextAlpha = 0.1;
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);
        $mpdf->Output("$invoice_date-$config_company_name-Invoice$invoice_number.pdf",'D');

    }else{
        echo "GTFO!!!";
    }
}

if(isset($_GET['pdf_quote'], $_GET['url_key'])){

    $quote_id = intval($_GET['pdf_quote']);
    $url_key = $_GET['url_key'];

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients
    WHERE quotes.client_id = clients.client_id
    AND quotes.quote_id = $quote_id
    AND quotes.quote_url_key = '$url_key'"
    );

    $row = mysqli_fetch_array($sql);
    $quote_id = $row['quote_id'];
    $quote_number = $row['quote_number'];
    $quote_status = $row['quote_status'];
    $quote_date = $row['quote_date'];
    $quote_amount = $row['quote_amount'];
    $quote_note = $row['quote_note'];
    $quote_url_key = $row['quote_url_key'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_address = $row['client_address'];
    $client_city = $row['client_city'];
    $client_state = $row['client_state'];
    $client_zip = $row['client_zip'];
    $client_email = $row['client_email'];
    $client_phone = $row['client_phone'];
    if(strlen($client_phone)>2){ 
        $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
    }
    $client_website = $row['client_website'];

    if(mysqli_num_rows($sql) == 1){

        $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id ORDER BY item_id ASC");

        while($row = mysqli_fetch_array($sql_items)){
            $item_id = $row['item_id'];
            $item_name = $row['item_name'];
            $item_description = $row['item_description'];
            $item_quantity = $row['item_quantity'];
            $item_price = $row['item_price'];
            $item_subtotal = $row['item_price'];
            $item_tax = $row['item_tax'];
            $item_total = $row['item_total'];
            $total_tax = $item_tax + $total_tax;
            $sub_total = $item_price * $item_quantity + $sub_total;


            $items .= "
              <tr>
                <td align='center'>$item_name</td>
                <td>$item_description</td>
                <td align='center'>$item_quantity</td>
                <td class='cost'>$$item_price</td>
                <td class='cost'>$$item_tax</td>
                <td class='cost'>$$item_total</td>
              </tr>
            ";

        }

        $html = '
        <html>
        <head>
        <style>
        body {font-family: sans-serif;
        font-size: 10pt;
        }
        p { margin: 0pt; }
        table.items {
        border: 0.1mm solid #000000;
        }
        td { vertical-align: top; }
        .items td {
        border-left: 0.1mm solid #000000;
        border-right: 0.1mm solid #000000;
        }
        table thead td { background-color: #EEEEEE;
        text-align: center;
        border: 0.1mm solid #000000;
        font-variant: small-caps;
        }
        .items td.blanktotal {
        background-color: #EEEEEE;
        border: 0.1mm solid #000000;
        background-color: #FFFFFF;
        border: 0mm none #000000;
        border-top: 0.1mm solid #000000;
        border-right: 0.1mm solid #000000;
        }
        .items td.totals {
        text-align: right;
        border: 0.1mm solid #000000;
        }
        .items td.cost {
        text-align: "." center;
        }
        </style>
        </head>
        <body>
        <!--mpdf
        <htmlpageheader name="myheader">
        <table width="100%"><tr>
        <td width="15%"><img width="75" height="75" src=" '.$config_invoice_logo.' "></img></td>
        <td width="50%"><span style="font-weight: bold; font-size: 14pt;"> '.$config_company_name.' </span><br />' .$config_company_address.' <br /> '.$config_company_city.' '.$config_company_state.' '.$config_company_zip.'<br /> '.$config_company_phone.' </td>
        <td width="35%" style="text-align: right;">Quote No.<br /><span style="font-weight: bold; font-size: 12pt;"> QUO-'.$quote_number.' </span></td>
        </tr></table>
        </htmlpageheader>
        <htmlpagefooter name="myfooter">
        <div style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; ">
        Page {PAGENO} of {nb}
        </div>
        </htmlpagefooter>
        <sethtmlpageheader name="myheader" value="on" show-this-page="1" />
        <sethtmlpagefooter name="myfooter" value="on" />
        mpdf-->
        <div style="text-align: right">Date: '.$quote_date.'</div>
        <table width="100%" style="font-family: serif;" cellpadding="10"><tr>
        <td width="45%" style="border: 0.1mm solid #888888; "><span style="font-size: 7pt; color: #555555; font-family: sans;">TO:</span><br /><br /><b> '.$client_name.' </b><br />'.$client_address.'<br />'.$client_city.' '.$client_state.' '.$client_zip.' <br /><br> '.$client_email.' <br /> '.$client_phone.'</td>
        <td width="65%">&nbsp;</td>

        </tr></table>
        <br />
        <table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse; " cellpadding="8">
        <thead>
        <tr>
        <td width="28%">Product</td>
        <td width="28%">Description</td>
        <td width="10%">Qty</td>
        <td width="10%">Price</td>
        <td width="12%">Tax</td>
        <td width="12%">Total</td>
        </tr>
        </thead>
        <tbody>
        '.$items.'
        <tr>
        <td class="blanktotal" colspan="4" rowspan="3"><h4>Notes</h4> '.$quote_note.' </td>
        <td class="totals">Subtotal:</td>
        <td class="totals cost">$ '.number_format($sub_total,2).' </td>
        </tr>
        <tr>
        <td class="totals">Tax:</td>
        <td class="totals cost">$ '.number_format($total_tax,2).' </td>
        </tr>
        <tr>
        <td class="totals">Total:</td>
        <td class="totals cost">$ '.number_format($quote_amount,2).' </td>
        </tr>
        </tbody>
        </table>
        <div style="text-align: center; font-style: italic;"> '.$config_quote_footer.' </div>
        </body>
        </html>
        ';
        
        $mpdf = new \Mpdf\Mpdf([
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 48,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10
        ]);
        $mpdf->SetProtection(array('print'));
        $mpdf->SetTitle("$config_company_name - Quote");
        $mpdf->SetAuthor("$config_company_name");
        $mpdf->SetWatermarkText("Quote");
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'DejaVuSansCondensed';
        $mpdf->watermarkTextAlpha = 0.1;
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);
        $mpdf->Output("$quote_date-$config_company_name-Quote$quote_number.pdf",'D');

    }else{
        echo "GTFO!!!";
    }
}

if(isset($_GET['approve_quote'], $_GET['url_key'])){

    $quote_id = intval($_GET['approve_quote']);
    $url_key = $_GET['url_key'];

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes
    WHERE quotes.quote_id = $quote_id
    AND quotes.quote_url_key = '$url_key'"
    );

    if(mysqli_num_rows($sql) == 1){
    

        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Approved' WHERE quote_id = $quote_id");

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Approved', history_description = 'Client approved Quote!', quote_id = $quote_id");

        $_SESSION['alert_message'] = "Quote approved";
        
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }else{
        echo "GTFO!!";
    }

}

if(isset($_GET['reject_quote'], $_GET['url_key'])){

    $quote_id = intval($_GET['reject_quote']);
    $url_key = $_GET['url_key'];

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes
    WHERE quotes.quote_id = $quote_id
    AND quotes.quote_url_key = '$url_key'"
    );

    if(mysqli_num_rows($sql) == 1){

        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Rejected' WHERE quote_id = $quote_id");

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Rejected', history_description = 'Client rejected Quote!', quote_id = $quote_id");

        $_SESSION['alert_message'] = "Quote rejected";
        
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }else{
        echo "GTFO!!";
    }
    
}

?>	