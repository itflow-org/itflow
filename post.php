<?php

include("config.php");
include("check_login.php");
//include("functions.php");

$todays_date = date('Y-m-d');

if(isset($_POST['add_client'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));

    mysqli_query($mysqli,"INSERT INTO clients SET client_name = '$name', client_address = '$address', client_city = '$city', client_state = '$state', client_zip = '$zip', client_phone = '$phone', client_email = '$email', client_website = '$website', client_created_at = UNIX_TIMESTAMP()");

    $_SESSION['alert_message'] = "Client added";
    
    header("Location: clients.php");

}

if(isset($_POST['edit_client'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));

    mysqli_query($mysqli,"UPDATE clients SET client_name = '$name', client_address = '$address', client_city = '$city', client_state = '$state', client_zip = '$zip', client_phone = '$phone', client_email = '$email', client_website = '$website', client_updated_at = UNIX_TIMESTAMP() WHERE client_id = $client_id");

    $_SESSION['alert_message'] = "Client updated";
    
    header("Location: clients.php");

}

if(isset($_GET['delete_client'])){
    $client_id = intval($_GET['delete_client']);

    mysqli_query($mysqli,"DELETE FROM clients WHERE client_id = $client_id");

    $_SESSION['alert_message'] = "Client deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_vendor'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));

    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_address = '$address', vendor_city = '$city', vendor_state = '$state', vendor_zip = '$zip', vendor_phone = '$phone', vendor_email = '$email', vendor_website = '$website', vendor_created_at = UNIX_TIMESTAMP()");

    $_SESSION['alert_message'] = "Vendor added";
    
    header("Location: vendors.php");

}

if(isset($_POST['edit_vendor'])){

    $vendor_id = intval($_POST['vendor_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));

    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_address = '$address', vendor_city = '$city', vendor_state = '$state', vendor_zip = '$zip', vendor_phone = '$phone', vendor_email = '$email', vendor_website = '$website', vendor_updated_at = UNIX_TIMESTAMP() WHERE vendor_id = $vendor_id");

    $_SESSION['alert_message'] = "Vendor modified";
    
    header("Location: vendors.php");

}

if(isset($_GET['delete_vendor'])){
    $vendor_id = intval($_GET['delete_vendor']);

    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_id = $vendor_id");

    $_SESSION['alert_message'] = "Vendor deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_mileage'])){

    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $starting_location = strip_tags(mysqli_real_escape_string($mysqli,$_POST['starting_location']));
    $destination = strip_tags(mysqli_real_escape_string($mysqli,$_POST['destination']));
    $miles = intval($_POST['miles']);
    $purpose = strip_tags(mysqli_real_escape_string($mysqli,$_POST['purpose']));

    mysqli_query($mysqli,"INSERT INTO mileage SET mileage_date = '$date', mileage_starting_location = '$starting_location', mileage_destination = '$destination', mileage_miles = $miles, mileage_purpose = '$purpose'");

    $_SESSION['alert_message'] = "Mileage added";
    
    header("Location: mileage.php");

}

if(isset($_POST['edit_mileage'])){

    $mileage_id = intval($_POST['mileage_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $starting_location = strip_tags(mysqli_real_escape_string($mysqli,$_POST['starting_location']));
    $destination = strip_tags(mysqli_real_escape_string($mysqli,$_POST['destination']));
    $miles = intval($_POST['miles']);
    $purpose = strip_tags(mysqli_real_escape_string($mysqli,$_POST['purpose']));

    mysqli_query($mysqli,"UPDATE mileage SET mileage_date = '$date', mileage_starting_location = '$starting_location', mileage_destination = '$destination', mileage_miles = $miles, mileage_purpose = '$purpose' WHERE mileage_id = $mileage_id");

    $_SESSION['alert_message'] = "Mileage modified";
    
    header("Location: mileage.php");

}

if(isset($_GET['delete_mileage'])){
    $mileage_id = intval($_GET['delete_mileage']);

    mysqli_query($mysqli,"DELETE FROM mileage WHERE mileage_id = $mileage_id");

    $_SESSION['alert_message'] = "Mileage deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_account'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $opening_balance = $_POST['opening_balance'];

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = '$opening_balance'");

    $_SESSION['alert_message'] = "Account added";
    
    header("Location: accounts.php");

}

if(isset($_POST['edit_account'])){

    $account_id = intval($_POST['account_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name' WHERE account_id = $account_id");

    $_SESSION['alert_message'] = "Account modified";
    
    header("Location: accounts.php");

}

if(isset($_GET['delete_account'])){
    $account_id = intval($_GET['delete_account']);

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id");

    $_SESSION['alert_message'] = "Account deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_category'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type'");

    $_SESSION['alert_message'] = "Category added";
    
    header("Location: categories.php");

}

if(isset($_POST['edit_category'])){

    $category_id = intval($_POST['category_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));

    mysqli_query($mysqli,"UPDATE categories SET category_name = '$name', category_type = '$type' WHERE category_id = $category_id");

    $_SESSION['alert_message'] = "Category modified";
    
    header("Location: categories.php");

}

if(isset($_GET['delete_category'])){
    $category_id = intval($_GET['delete_category']);

    mysqli_query($mysqli,"DELETE FROM categories WHERE category_id = $category_id");

    $_SESSION['alert_message'] = "Category deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_expense'])){

    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $category = intval($_POST['category']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', account_id = $account, vendor_id = $vendor, category_id = $category, expense_description = '$description'");

    $_SESSION['alert_message'] = "Expense added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_expense'])){

    $expense_id = intval($_POST['expense_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $category = intval($_POST['category']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', account_id = $account, vendor_id = $vendor, category_id = $category, expense_description = '$description' WHERE expense_id = $expense_id");

    $_SESSION['alert_message'] = "Expense modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_expense'])){
    $expense_id = intval($_GET['delete_expense']);

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    $_SESSION['alert_message'] = "Expense deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_transfer'])){

    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account_from = intval($_POST['account_from']);
    $account_to = intval($_POST['account_to']);

    mysqli_query($mysqli,"INSERT INTO transfers SET transfer_date = '$date', transfer_amount = '$amount', transfer_account_from = $account_from, transfer_account_to = $account_to");

    $_SESSION['alert_message'] = "Transfer added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_transfer'])){

    $transfer_id = intval($_POST['transfer_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account_from = intval($_POST['account_from']);
    $account_to = intval($_POST['account_to']);

    mysqli_query($mysqli,"UPDATE transfers SET transfer_date = '$date', transfer_amount = '$amount', transfer_account_from = $account_from, transfer_account_to = $account_to WHERE transfer_id = $transfer_id");

    $_SESSION['alert_message'] = "Transfer added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_transfer'])){
    $transfer_id = intval($_GET['delete_transfer']);

    mysqli_query($mysqli,"DELETE FROM transfers WHERE transfer_id = $transfer_id");

    $_SESSION['alert_message'] = "Transfer deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_invoice'])){

    $client = intval($_POST['client']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $due = strip_tags(mysqli_real_escape_string($mysqli,$_POST['due']));
    $category = intval($_POST['category']);
    
    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_date = '$date', invoice_due = '$due', category_id = $category, invoice_status = 'Draft', client_id = $client");

    $invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO invoice_history SET invoice_history_date = '$todays_date', invoice_history_status = 'Draft', invoice_history_description = 'INVOICE added!', invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Invoice added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice'])){
    $invoice_id = intval($_GET['delete_invoice']);

    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Invoice deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_invoice_item'])){

    $invoice_id = intval($_POST['invoice_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $qty = $_POST['qty'];
    $price = $_POST['price'];
    $tax = $_POST['tax'];
    
    $subtotal = $price * $qty;
    $tax = $subtotal * $tax;
    $total = $subtotal + $tax;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET invoice_item_name = '$name', invoice_item_description = '$description', invoice_item_quantity = $qty, invoice_item_price = '$price', invoice_item_subtotal = '$subtotal', invoice_item_tax = '$tax', invoice_item_total = '$total', invoice_id = $invoice_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);

    $invoice_subtotal = $row['invoice_subtotal'] + $subtotal;
    $invoice_tax = $row['invoice_tax'] + $tax; 
    $invoice_total = $row['invoice_total'] + $total;
    $invoice_balance = $row['invoice_balance'] + $total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_subtotal = '$invoice_subtotal', invoice_tax = '$invoice_tax', invoice_total = '$invoice_total', invoice_balance = '$invoice_balance' WHERE invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Item added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice_item'])){
    $invoice_item_id = intval($_GET['delete_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_item_id = $invoice_item_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $invoice_item_subtotal = $row['invoice_item_subtotal'];
    $invoice_item_tax = $row['invoice_item_tax'];
    $invoice_item_total = $row['invoice_item_total'];

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_balance = $row['invoice_balance'] - $invoice_item_total;
    $invoice_subtotal = $row['invoice_subtotal'] - $invoice_item_subtotal;
    $invoice_tax = $row['invoice_tax'] - $invoice_item_tax;
    $invoice_total = $row['invoice_total'] - $invoice_item_total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_subtotal = '$invoice_subtotal', invoice_tax = '$invoice_tax', invoice_total = '$invoice_total', invoice_balance = '$invoice_balance' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE invoice_item_id = $invoice_item_id");

    $_SESSION['alert_message'] = "Item deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_invoice_payment'])){

    $invoice_id = intval($_POST['invoice_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account = intval($_POST['account']);
    $payment_method = strip_tags(mysqli_real_escape_string($mysqli,$_POST['payment_method']));

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_balance = $row['invoice_balance'] - $amount;
    $invoice_paid = $row['invoice_paid'] + $paid;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_balance = '$invoice_balance', invoice_paid = '$invoice_paid' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO invoice_payments SET invoice_payment_date = '$date', invoice_payment_amount = '$amount', account_id = $account, invoice_payment_method = '$payment_method', invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Payment added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice_payment'])){
    $invoice_payment_id = intval($_GET['delete_invoice_payment']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_payments WHERE invoice_payment_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $invoice_payment_amount = $row['invoice_payment_amount'];

    $invoice_balance = $row['invoice_balance'] - $invoice_payment_amount;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_balance = '$invoice_balance' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM invoice_payments WHERE invoice_payment_id = $invoice_payment_id");

    $_SESSION['alert_message'] = "Payment deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_client_contact'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));

    mysqli_query($mysqli,"INSERT INTO client_contacts SET client_contact_name = '$name', client_contact_title = '$title', client_contact_phone = '$phone', client_contact_email = '$email', client_id = $client_id");

    $_SESSION['alert_message'] = "Contact added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_client_contact'])){

    $client_contact_id = intval($_POST['client_contact_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));

    mysqli_query($mysqli,"UPDATE client_contacts SET client_contact_name = '$name', client_contact_title = '$title', client_contact_phone = '$phone', client_contact_email = '$email' WHERE client_contact_id = $client_contact_id");

    $_SESSION['alert_message'] = "Contact updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client_contact'])){
    $client_contact_id = intval($_GET['delete_client_contact']);

    mysqli_query($mysqli,"DELETE FROM client_contacts WHERE client_contact_id = $client_contact_id");

    $_SESSION['alert_message'] = "Contact deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_client_location'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);

    mysqli_query($mysqli,"INSERT INTO client_locations SET client_location_name = '$name', client_location_address = '$address', client_location_city = '$city', client_location_state = '$state', client_location_zip = '$zip', client_location_phone = '$phone', client_id = $client_id");

    $_SESSION['alert_message'] = "Location added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_client_location'])){

    $client_location_id = intval($_POST['client_location_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);

    mysqli_query($mysqli,"UPDATE client_locations SET client_location_name = '$name', client_location_address = '$address', client_location_city = '$city', client_location_state = '$state', client_location_zip = '$zip', client_location_phone = '$phone' WHERE client_location_id = $client_location_id");

    $_SESSION['alert_message'] = "Location updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client_location'])){
    $client_location_id = intval($_GET['delete_client_location']);

    mysqli_query($mysqli,"DELETE FROM client_locations WHERE client_location_id = $client_location_id");

    $_SESSION['alert_message'] = "Location deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_client_asset'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $make = strip_tags(mysqli_real_escape_string($mysqli,$_POST['make']));
    $model = strip_tags(mysqli_real_escape_string($mysqli,$_POST['model']));
    $serial = strip_tags(mysqli_real_escape_string($mysqli,$_POST['serial']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO client_assets SET client_asset_name = '$name', client_asset_type = '$type', client_asset_make = '$make', client_asset_model = '$model', client_asset_serial = '$serial', client_asset_note = '$note', client_id = $client_id");

    $_SESSION['alert_message'] = "Asset added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_client_asset'])){

    $client_asset_id = intval($_POST['client_asset_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $make = strip_tags(mysqli_real_escape_string($mysqli,$_POST['make']));
    $model = strip_tags(mysqli_real_escape_string($mysqli,$_POST['model']));
    $serial = strip_tags(mysqli_real_escape_string($mysqli,$_POST['serial']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"UPDATE client_assets SET client_asset_name = '$name', client_asset_type = '$type', client_asset_make = '$make', client_asset_model = '$model', client_asset_serial = '$serial', client_asset_note = '$note' WHERE client_asset_id = $client_asset_id");

    $_SESSION['alert_message'] = "Asset updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client_asset'])){
    $client_asset_id = intval($_GET['delete_client_asset']);

    mysqli_query($mysqli,"DELETE FROM client_assets WHERE client_asset_id = $client_asset_id");

    $_SESSION['alert_message'] = "Asset deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_client_vendor'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO client_vendors SET client_vendor_name = '$name', client_vendor_description = '$description', client_vendor_note = '$note', client_id = $client_id");

    $_SESSION['alert_message'] = "Vendor added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_client_vendor'])){

    $client_vendor_id = intval($_POST['client_vendor_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"UPDATE client_vendors SET client_vendor_name = '$name', client_vendor_description = '$description', client_vendor_note = '$note' WHERE client_vendor_id = $client_vendor_id");

    $_SESSION['alert_message'] = "Vendor updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client_vendor'])){
    $client_vendor_id = intval($_GET['delete_client_vendor']);

    mysqli_query($mysqli,"DELETE FROM client_vendors WHERE client_vendor_id = $client_vendor_id");

    $_SESSION['alert_message'] = "Vendor deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_client_login'])){

    $client_id = intval($_POST['client_id']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
    $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO client_logins SET client_login_description = '$description', client_login_username = '$username', client_login_password = '$password', client_login_note = '$note', client_id = $client_id");

    $_SESSION['alert_message'] = "Login added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_client_login'])){

    $client_login_id = intval($_POST['client_login_id']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
    $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"UPDATE client_logins SET client_login_description = '$description', client_login_username = '$username', client_login_password = '$password', client_login_note = '$note' WHERE client_login_id = $client_login_id");

    $_SESSION['alert_message'] = "Login updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client_login'])){
    $client_login_id = intval($_GET['delete_client_login']);

    mysqli_query($mysqli,"DELETE FROM client_logins WHERE client_login_id = $client_login_id");

    $_SESSION['alert_message'] = "Login deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_client_note'])){

    $client_id = intval($_POST['client_id']);
    $subject = strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO client_notes SET client_note_subject = '$subject', client_note_body = '$note', client_id = $client_id");

    $_SESSION['alert_message'] = "Note added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_client_note'])){

    $client_note_id = intval($_POST['client_note_id']);
    $subject = strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"UPDATE client_notes SET client_note_subject = '$subject', client_note_body = '$note' WHERE client_note_id = $client_note_id");

    $_SESSION['alert_message'] = "Note updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client_note'])){
    $client_note_id = intval($_GET['delete_client_note']);

    mysqli_query($mysqli,"DELETE FROM client_notes WHERE client_note_id = $client_note_id");

    $_SESSION['alert_message'] = "Note deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_user'])){
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $password = mysqli_real_escape_string($mysqli,$_POST['password']);
    $first_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['first_name']));
    $last_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['last_name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $location = intval($_POST['location']);
    $user_access = intval($_POST['user_access']);
    $hash_password = md5($password);

    mysqli_query($mysqli,"INSERT INTO users SET email = '$email', password = '$hash_password', first_name = '$first_name', last_name = '$last_name', title = '$title', phone = '$phone', current_location_id = $location, user_created = UNIX_TIMESTAMP(), user_access = $user_access");
    
    $user_id = mysqli_insert_id($mysqli);

    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check !== false) {
        $avatar_path = "uploads/user_avatars/";
        $avatar_path = $avatar_path . $user_id . '_' . time() . '_' . basename( $_FILES['avatar']['name']);
        move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path);
    }else{
        $avatar_path = "img/default_user_avatar.png";
    }

    mysqli_query($mysqli,"UPDATE users SET avatar = '$avatar_path' WHERE user_id = $user_id");

    $event_description = "User $first_name $last_name $email created.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add User', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "User Added";
    
    header("Location: admin.php?tab=users");

}

if(isset($_POST['edit_user'])){
    $user_id = intval($_POST['user_id']);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $current_password_hash = mysqli_real_escape_string($mysqli,$_POST['current_password_hash']);
    $new_password = mysqli_real_escape_string($mysqli,$_POST['new_password']);
    if($current_password_hash == $new_password){
        $hash_password = $current_password_hash;
    }else{
        $hash_password = md5($new_password);
    }
    $first_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['first_name']));
    $last_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['last_name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = mysqli_real_escape_string($mysqli,$_POST['phone']);
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $user_access = intval($_POST['user_access']);
    $location = intval($_POST['location']);
    $avatar_path = $_POST['current_avatar_path'];
    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check !== false) {
        if($avatar_path != "img/default_user_avatar.png"){
            unlink($avatar_path);
        }
        $avatar_path = "uploads/user_avatars/";
        $avatar_path =  $avatar_path . $user_id . '_' . time() . '_' . basename( $_FILES['avatar']['name']);
        move_uploaded_file($_FILES['avatar']['tmp_name'], "$avatar_path");
    }

    mysqli_query($mysqli,"UPDATE users SET email = '$email', password = '$hash_password', first_name = '$first_name', last_name = '$last_name', title = '$title', phone = '$phone', avatar = '$avatar_path', user_modified = UNIX_TIMESTAMP(), current_location_id = $location, user_access = $user_access WHERE user_id = $user_id");
    
    $event_description = "User $first_name $last_name $email modified.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit User', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");
    
    $_SESSION['alert_message'] = "User Updated.";
    
    header("Location: admin.php?tab=users");

}

if(isset($_POST['change_password'])){
    $current_url = $_POST['current_url'];
    $new_password = mysqli_real_escape_string($mysqli,$_POST['new_password']);

    $sql = mysqli_query($mysqli,"SELECT password FROM users WHERE user_id = $session_user_id");
    $row = mysqli_fetch_array($sql);
    $old_password = $row['password'];

    if($old_password == $new_password){
        $hash_password = $old_password;
    }else{
        $hash_password = md5($new_password);
    }

    mysqli_query($mysqli,"UPDATE users SET password = '$hash_password', user_modified = UNIX_TIMESTAMP() WHERE user_id = $session_user_id");
    
    $event_description = "User changed their own password.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'User Password Change', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Password Changed";
    
    header("Location: $current_url");

}

if(isset($_POST['change_location'])){
    $current_url = $_POST['current_url'];
    $new_location = intval($_POST['new_location']);

    mysqli_query($mysqli,"UPDATE users SET current_location_id = $new_location WHERE user_id = $session_user_id");
    
    $event_description = "User changed their location.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'User Location Change', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Location Changed";
    
    header("Location: $current_url");

}

if(isset($_POST['change_avatar'])){
    $avatar_path = $_POST['current_avatar_path'];
    $current_url = $_POST['current_url'];
    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check !== false) {
        if($avatar_path != "img/default_user_avatar.png"){
            unlink($avatar_path);
        }
        $avatar_path = "uploads/user_avatars/";
        $avatar_path =  $avatar_path . $session_user_id . '_' . time() . '_' . basename( $_FILES['avatar']['name']);
        move_uploaded_file($_FILES['avatar']['tmp_name'], "$avatar_path");
    }

    mysqli_query($mysqli,"UPDATE users SET avatar = '$avatar_path' WHERE user_id = $session_user_id");

    $event_description = "User changed their avatar.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'User Avatar Change', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");
    
    $_SESSION['alert_message'] = "User Avatar Changed";
    
    header("Location: $current_url");

  }

if(isset($_POST['edit_candidate'])){
    $candidate_id = intval($_POST['candidate_id']);
    $first_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['first_name']));
    $last_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['last_name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $social_security = strip_tags(mysqli_real_escape_string($mysqli,$_POST['social_security']));
    $birth_day = strip_tags(mysqli_real_escape_string($mysqli,$_POST['birth_day']));
    $location = intval($_POST['location']);
    $old_password = mysqli_real_escape_string($mysqli,$_POST['old_password']);
    $new_password = mysqli_real_escape_string($mysqli,$_POST['new_password']);
    if($old_password == $new_password){
        $hash_password = $old_password;
    }else{
        $hash_password = md5($new_password);
    }
    $avatar_path = $_POST['current_avatar_path'];
    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check !== false) {
        if($avatar_path != "img/default_candidate_avatar.png"){
            unlink($avatar_path);
        }
        $avatar_path = "uploads/candidate_avatars/";
        $avatar_path =  $avatar_path . $candidate_id . '_' . time() . '_' . basename( $_FILES['avatar']['name']);
        move_uploaded_file($_FILES['avatar']['tmp_name'], "$avatar_path");
    }


    mysqli_query($mysqli,"UPDATE candidates SET first_name = '$first_name', last_name = '$last_name', address = '$address', city = '$city', state = '$state', zip = '$zip', phone = '$phone', email = '$email', password = '$hash_password', birth_day = '$birth_day', social_security = '$social_security', candidate_avatar = '$avatar_path', location_applied_at = $location, candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $event_description = "Candidate <a href=''candidate.php?candidate_id=$candidate_id''>$first_name $last_name</a> modified.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Candidate', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");
    
    $_SESSION['alert_message'] = "Candidate Updated";
    
    header("Location: candidate.php?candidate_id=$candidate_id");
}

if(isset($_GET['delete_candidate'])){
    $candidate_id = intval($_GET['delete_candidate']);

    $sql = mysqli_query($mysqli,"DELETE FROM candidates WHERE candidate_id = $candidate_id");

    $event_description = "Candidate Deleted.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Candidate', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    removeDirectory("uploads/candidate_files/$candidate_id");

    $_SESSION['alert_message'] = "Candidate Deleted";
    
    header("Location: candidates.php");
  
}

if(isset($_POST['change_candidate_avatar'])){
    $candidate_id = intval($_POST['candidate_id']);
    $avatar_path = $_POST['current_avatar_path'];
    $current_url = $_POST['current_url'];
    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check !== false) {
        if($avatar_path != "img/default_candidate_avatar.png"){
            unlink($avatar_path);
        }
        $avatar_path = "uploads/candidate_avatars/";
        $avatar_path =  $avatar_path . $candidate_id . '_' . time() . '_' . basename( $_FILES['avatar']['name']);
        move_uploaded_file($_FILES['avatar']['tmp_name'], "$avatar_path");
    }

    mysqli_query($mysqli,"UPDATE candidates SET candidate_avatar = '$avatar_path', candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $event_description = "Candidate $candidate_id avatar modifed.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Candidate Avatar', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");
    
    $_SESSION['alert_message'] = "Candidate Avatar Updated";
    
    header("Location: $current_url");

}

if(isset($_POST['snapshot_candidate'])){
    $candidate_id = intval($_POST['candidate_id']);
    $avatar_path = $_POST['current_avatar_path'];
    $current_url = $_POST['current_url'];
    $img = $_POST['image'];

    $folderPath = "uploads/candidate_avatars/";
  
    $image_parts = explode(";base64,", $img);
    $image_type_aux = explode("image/", $image_parts[0]);
    $image_type = $image_type_aux[1];
  
    $image_base64 = base64_decode($image_parts[1]);
    $fileName = "$candidate_id.jpg";
  
    $file = $folderPath . $fileName;
    file_put_contents($file, $image_base64);
  
    print_r($fileName);

    mysqli_query($mysqli,"UPDATE candidates SET candidate_avatar = '$folderPath$fileName', candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $event_description = "Candidate $candidate_id got photo snapped.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Candidate Avatar', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");
    
    $_SESSION['alert_message'] = "Candidate Photo Updated";
    
    header("Location: $current_url");

}


if(isset($_POST['add_company'])){
    $company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_name']));
    $company_address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_address']));
    $company_city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_city']));
    $company_state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_state']));
    $company_zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_zip']));

    mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$company_name', company_address = '$company_address', company_city = '$company_city', company_state = '$company_state', company_zip = '$company_zip', company_created_at = UNIX_TIMESTAMP()");
    
    $company_id = mysqli_insert_id($mysqli);

    $event_description = "Company <a href=''company.php?company_id=$company_id''>$company_name</a> created.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Company', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mkdir("uploads/company_files/$company_id");

    $_SESSION['alert_message'] = "Company Added";

    header("Location: companies.php");
}

if(isset($_POST['edit_company'])){
    $company_id = intval($_POST['company_id']);
    $company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_name']));
    $company_address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_address']));
    $company_city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_city']));
    $company_state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_state']));
    $company_zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_zip']));

    mysqli_query($mysqli,"UPDATE companies SET company_name = '$company_name', company_address = '$company_address', company_city = '$company_city', company_state = '$company_state', company_zip = '$company_zip', company_updated_at = UNIX_TIMESTAMP() WHERE company_id = $company_id");
    
    $event_description = "Company <a href=''company.php?company_id=$company_id''>$company_name</a> modified.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Company', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Company Added";
    
    header('Location: companies.php');
}

if(isset($_POST['add_location'])){
    $location_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_name']));
    $location_address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_address']));
    $location_city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_city']));
    $location_state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_state']));
    $location_zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_zip']));
    $location_timezone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_timezone']));

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$location_name', location_address = '$location_address', location_city = '$location_city', location_state = '$location_state', location_zip = '$location_zip', location_timezone = '$location_timezone', location_created_at = UNIX_TIMESTAMP()");

    $event_description = "Location $location_name created.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Location', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");
    
    $_SESSION['alert_message'] = "Location Added";
    
    header("Location: admin.php?tab=locations");
}

if(isset($_POST['edit_location'])){
    $location_id = intval($_POST['location_id']);
    $location_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_name']));
    $location_address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_address']));
    $location_city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_city']));
    $location_state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_state']));
    $location_zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_zip']));
    $location_timezone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['location_timezone']));

    mysqli_query($mysqli,"UPDATE locations SET location_name = '$location_name', location_address = '$location_address', location_city = '$location_city', location_state = '$location_state', location_zip = '$location_zip', location_timezone = '$location_timezone' WHERE location_id = $location_id");

    $event_description = "Location $location_name modified.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Location', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Location Edited";
    
    header("Location: admin.php?tab=locations");
}

if(isset($_POST['add_contact'])){
    $company_id = intval($_POST['company_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_email = '$email', contact_created_at = UNIX_TIMESTAMP(), company_id = $company_id");
    
    $sql = mysqli_query($mysqli,"SELECT company_name FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];

    $event_description = "Contact $name created for Company <a href=''company.php?company_id=$company_id''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Company Contact', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact Added";
    
    header("Location: company.php?company_id=$company_id&tab=contacts");
}

if(isset($_POST['edit_contact'])){
    $contact_id = intval($_POST['contact_id']);
    $company_id = intval($_POST['company_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_name']));

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_email = '$email' WHERE contact_id = $contact_id");
    
    $event_description = "Contact $name edited for company <a href=''company.php?company_id=$company_id''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Company Contact', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact edited";
    
    header("Location: company.php?company_id=$company_id&tab=contacts");
}

if(isset($_GET['delete_contact'])){
    $contact_id = intval($_GET['delete_contact']);
    $company_id = intval($_GET['company_id']);
    
    $event_description = "Contact Deleted";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Candidate File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id");

    $_SESSION['alert_message'] = "Contact deleted";
    
    header("Location: company.php?company_id=$company_id&tab=contacts");
  
}


if(isset($_POST['add_company_note'])){
    $company_id = intval($_POST['company_id']);
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO notes SET note = '$note', note_created_at = UNIX_TIMESTAMP(), note_created_by = $session_user_id, company_id = $company_id");
    
    $sql = mysqli_query($mysqli,"SELECT company_name FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];

    $event_description = "Note created for Company <a href=''company.php?company_id=$company_id&tab=notes''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Company Note', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Note Added";
    
    header("Location: company.php?company_id=$company_id&tab=notes");
}

if(isset($_POST['edit_company_note'])){
    $note_id = intval($_POST['note_id']);
    $company_id = intval($_POST['company_id']);
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));
    $company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_name']));

    mysqli_query($mysqli,"UPDATE notes SET note = '$note' WHERE note_id = $note_id");

    $event_description = "Note edited for company <a href=''company.php?company_id=$company_id&tab=notes''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Company Note', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Note Edited";
    
    header("Location: company.php?company_id=$company_id&tab=notes");
}

if(isset($_GET['delete_company_note'])){
    $note_id = intval($_GET['delete_company_note']);
    $company_id = intval($_GET['company_id']);

    $sql = mysqli_query($mysqli,"SELECT company_name FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
                     
    $company_name = $row['company_name'];

    $event_description = "Note deleted for company <a href=''company.php?company_id=$company_id&tab=notes''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Company Note', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM notes WHERE note_id = $note_id");

    $_SESSION['alert_message'] = "Note Deleted";
    
    header("Location: company.php?company_id=$company_id&tab=notes");
  
}

if(isset($_POST['upload_company_file'])){
    $company_id = intval($_POST['company_id']);

    if(!empty($_FILES['file'])){
        $path = "uploads/company_files/$company_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"INSERT INTO files SET file_name = '$file_name', file_location = '$path', uploaded_at = UNIX_TIMESTAMP(), uploaded_by = $session_user_id, company_id = $company_id");

    $sql = mysqli_query($mysqli,"SELECT company_name FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];

    $event_description = "File <a href=''$path''>$file_name</a> uploaded for company <a href=''company.php?company_id=$company_id&tab=files''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Company File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "File Uploaded";
    
    header("Location: company.php?company_id=$company_id&tab=files");
}

if(isset($_GET['delete_company_file'])){
    $file_id = intval($_GET['delete_company_file']);

    $sql = mysqli_query($mysqli,"SELECT company_id, file_location FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
     
    $company_id = $row['company_id'];                 
    $file_location = $row['file_location'];
    $file_name = basename("$file_location");
    

    $sql = mysqli_query($mysqli,"SELECT company_name FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
                     
    $company_name = $row['company_name'];


    $event_description = "File $file_name deleted for company <a href=''company.php?company_id=$company_id&tab=files''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Company File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

    unlink($file_location);

    $_SESSION['alert_message'] = "File Deleted";
    
    header("Location: company.php?company_id=$company_id&tab=files");
  
}

if(isset($_POST['add_job'])){
    $company_id = intval($_POST['company_id']);
    $job_type = strip_tags(mysqli_escape_string($mysqli,$_POST['job_type']));
    $job_title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['job_title']));
    $job_openings = intval($_POST['job_openings']);
    $job_description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['job_description']));

    mysqli_query($mysqli,"INSERT INTO jobs SET job_title = '$job_title', job_type = '$job_type', job_openings = $job_openings, job_description = '$job_description', job_created_at = UNIX_TIMESTAMP(), job_created_by = $session_user_id, company_id = $company_id");

    $sql = mysqli_query($mysqli,"SELECT company_name FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];
    $event_description = "Job $job_title created for company <a href=''company.php?company_id=$company_id&tab=jobs''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Job', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Job Added";
    
    header("Location: jobs.php");
}

if(isset($_POST['edit_job'])){
    $job_id = intval($_POST['job_id']);
    $company_id = intval($_POST['company_id']);
    $job_type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['job_type']));
    $job_title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['job_title']));
    $job_openings = intval($_POST['job_openings']);
    $job_description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['job_description']));

    mysqli_query($mysqli,"UPDATE jobs SET job_type = '$job_type', job_title = '$job_title', job_openings = $job_openings, job_description = '$job_description', company_id = $company_id WHERE job_id = $job_id");

    $sql = mysqli_query($mysqli,"SELECT company_name FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];

    $event_description = "Job <a href=''edit_job.php?job_id=$job_id''>$job_title</a> edited for company <a href=''company.php?company_id=$company_id&tab=jobs''>$company_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Job', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Job Edited";
    
    header("Location: jobs.php");
}

if(isset($_GET['delete_job'])){
    $job_id = intval($_GET['delete_job']);

    mysqli_query($mysqli,"DELETE FROM jobs WHERE job_id = $job_id");

    $event_description = "Job ID $job_id Deleted.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Job', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Job Deleted";
    
    header("Location: jobs.php");
}

if(isset($_POST['add_education'])){
    $candidate_id = intval($_POST['candidate_id']);
    $education_type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['education_type']));
    $education_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['education_name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $date_from = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_from']));
    $date_to = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_to']));
    $graduate = strip_tags(mysqli_real_escape_string($mysqli,$_POST['graduate']));
    $major = strip_tags(mysqli_real_escape_string($mysqli,$_POST['major']));

    mysqli_query($mysqli,"INSERT INTO candidate_education SET education_type = '$education_type', education_name = '$education_name', education_address = '$address', education_city = '$city', education_state = '$state', education_zip = '$zip', education_date_from = '$date_from', education_date_to = '$date_to', graduate = '$graduate', major = '$major', candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "Added education $education_name $education_type to candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=education''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Education', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Education Added";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=education");
}

if(isset($_GET['delete_education'])){
    $education_id = intval($_GET['delete_education']);
    $candidate_id = intval($_GET['candidate_id']);

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM candidate_education WHERE education_id = $education_id");
    $row = mysqli_fetch_array($sql);

    $education_type = $row['education_type'];
    $education_name = $row['education_name'];

    $event_description = "Deleted education $education_name $education_type from candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=education''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Education', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM candidate_education WHERE education_id = $education_id");

    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $_SESSION['alert_message'] = "Education Deleted";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=education");
  
}

if(isset($_POST['add_employment'])){
    $candidate_id = intval($_POST['candidate_id']);
    $company = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $supervisor = strip_tags(mysqli_real_escape_string($mysqli,$_POST['supervisor']));
    $job_title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['job_title']));
    $starting_salary = strip_tags(mysqli_real_escape_string($mysqli,$_POST['starting_salary']));
    $ending_salary = strip_tags(mysqli_real_escape_string($mysqli,$_POST['ending_salary']));
    $responsibilities = strip_tags(mysqli_real_escape_string($mysqli,$_POST['responsibilities']));
    $date_from = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_from']));
    $date_to = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_to']));
    $reason_for_leave = strip_tags(mysqli_real_escape_string($mysqli,$_POST['reason_for_leave']));
    $allow_contact = strip_tags(mysqli_real_escape_string($mysqli,$_POST['allow_contact']));

    mysqli_query($mysqli,"INSERT INTO candidate_employment SET employment_company = '$company', employment_address = '$address', employment_city = '$city', employment_state = '$state', employment_zip = '$zip', employment_phone = '$phone', employment_supervisor = '$supervisor', employment_job_title = '$job_title', employment_starting_salary = '$starting_salary', employment_ending_salary = '$ending_salary', employment_responsibilities = '$responsibilities', employment_date_from = '$date_from', employment_date_to = '$date_to', employment_reason_for_leave = '$reason_for_leave', employment_allow_contact = '$allow_contact', candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "Added employer $company to candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=employment''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Employer', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");


    $_SESSION['alert_message'] = "Employment Added";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=employment");
}

if(isset($_GET['delete_employment'])){
    $employment_id = intval($_GET['delete_employment']);
    $candidate_id = intval($_GET['candidate_id']);

    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $sql = mysqli_query($mysqli,"SELECT employment_company FROM candidate_employment WHERE employment_id = $employment_id");
    $row = mysqli_fetch_array($sql);

    $company = $row['employment_company'];

    $event_description = "Deleted employer $company from candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=employment''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Employer', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM candidate_employment WHERE employment_id = $employment_id");

    $_SESSION['alert_message'] = "Employment Deleted";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=employment");
  
}

if(isset($_POST['add_reference'])){
    $candidate_id = intval($_POST['candidate_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $relationship = strip_tags(mysqli_real_escape_string($mysqli,$_POST['relationship']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $company = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);

    mysqli_query($mysqli,"INSERT INTO candidate_references SET reference_name = '$name', reference_relationship = '$relationship', reference_address = '$address', reference_city = '$city', reference_state = '$state', reference_zip = '$zip', reference_company = '$company', reference_phone = '$phone', candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "Added reference $name to candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=references''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Reference', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Reference Added";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=references");
}

if(isset($_GET['delete_reference'])){
    $reference_id = intval($_GET['delete_reference']);
    $candidate_id = intval($_GET['candidate_id']);

    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $sql = mysqli_query($mysqli,"SELECT reference_name FROM candidate_references WHERE reference_id = $reference_id");
    $row = mysqli_fetch_array($sql);

    $reference_name = $row['reference_name'];

    $event_description = "Deleted reference $reference_name from candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=references''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Reference', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM candidate_references WHERE reference_id = $reference_id");

    $_SESSION['alert_message'] = "Reference Deleted";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=references");
  
}

if(isset($_POST['hire_candidate'])){
    $candidate_id = intval($_POST['candidate_id']);
    $job_id = intval($_POST['job_id']);
    $company_id = intval($_POST['company_id']);
    $start_date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['start_date']));
    $start_time = strip_tags(mysqli_real_escape_string($mysqli,$_POST['start_time']));
    $start_date_time = $start_date . $start_time;
    $start_date_time_conv = strtotime($start_date_time);
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));
    $first_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['first_name']));
    $last_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['last_name']));
    $company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_name']));
    $job_openings = intval($_POST['job_openings']);
    $job_title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['job_title']));
    $new_job_openings = $job_openings - 1;

    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    mysqli_query($mysqli,"INSERT INTO candidate_work_history SET work_history_job = '$job_title', hired_date = UNIX_TIMESTAMP(), start_date = '$start_date_time_conv', user_id = $session_user_id, company_id = $company_id, candidate_id = $candidate_id");

    mysqli_query($mysqli,"INSERT INTO candidate_notes SET candidate_note = '$note', note_created_at = UNIX_TIMESTAMP(), note_created_by = $session_user_id, candidate_id = $candidate_id");

    mysqli_query($mysqli,"UPDATE candidates SET current_status = 'Hired - Followup', candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    mysqli_query($mysqli,"UPDATE jobs SET job_openings = $new_job_openings WHERE job_id = $job_id");

    $event_description = "Hired <a href=''candidate.php?candidate_id=$candidate_id&tab=work_history''>$first_name $last_name</a> for $job_title at the company <a href=''company.php?company_id=$company_id&tab=history''>$company_name</a>. The number of available positions changed from $job_openings to $new_job_openings";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Hired Candidate', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Candidate Hired";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=work_history");
}

if(isset($_POST['upload_candidate_file'])){
    $candidate_id = intval($_POST['candidate_id']);

    if(!empty($_FILES['file'])){
        $path = "uploads/candidate_files/$candidate_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        if(move_uploaded_file($_FILES['file']['tmp_name'], $path)) {
          $_SESSION['alert_message'] = "The file ".  basename( $_FILES['file']['name']). 
          " has been uploaded";
        } else{
            $_SESSION['alert_message'] = "There was an error uploading the file, please try again!";
        }
    }

    mysqli_query($mysqli,"INSERT INTO files SET file_location = '$path', uploaded_at = UNIX_TIMESTAMP(), uploaded_by = $session_user_id, candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "File <a href=''$path''>$file_name</a> uploaded for candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=files''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Candidate File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    header("Location: candidate.php?candidate_id=$candidate_id&tab=files");
}

if(isset($_GET['delete_candidate_file'])){
    $file_id = intval($_GET['delete_candidate_file']);

    $sql = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
     
    $candidate_id = $row['candidate_id'];                 
    $file_location = $row['file_location'];
    $file_name = basename("$file_location");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);
                     
    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "File $file_name deleted for candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=files''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Candidate File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

    unlink($file_location);

    $_SESSION['alert_message'] = "Note Deleted";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=files");
  
}

if(isset($_POST['add_candidate_note'])){
    $candidate_id = intval($_POST['candidate_id']);
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO notes SET note = '$note', note_created_at = UNIX_TIMESTAMP(), note_created_by = $session_user_id, candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    
    $event_description = "Note added to candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=notes''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Candidate Note', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Note Added.";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=notes");
}

if(isset($_POST['edit_candidate_note'])){
    $note_id = intval($_POST['note_id']);
    $candidate_id = intval($_POST['candidate_id']);
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));
    $first_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['first_name']));
    $last_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['last_name']));
    
    mysqli_query($mysqli,"UPDATE notes SET note = '$note' WHERE note_id = $note_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");
    
    $event_description = "Note edited for candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=notes''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Edit Candidate Note', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Note Edited";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=notes");
}

if(isset($_GET['delete_candidate_note'])){
    $note_id = intval($_GET['delete_candidate_note']);
    $candidate_id = intval($_GET['candidate_id']);

    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "Deleted note from candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=notes''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Delete Candidate Note', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    mysqli_query($mysqli,"DELETE FROM notes WHERE note_id = $note_id");

    $_SESSION['alert_message'] = "Note Deleted";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=notes");
}

if(isset($_GET['interview_candidate'])){
    $candidate_id = intval($_GET['interview_candidate']);

    mysqli_query($mysqli,"UPDATE candidates SET current_status = 'Interviewing', candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $_SESSION['alert_message'] = "Candidate is now being Interviewed";
    
    header("Location: candidate.php?candidate_id=$candidate_id");
  
}

if(isset($_POST['followup_candidate'])){
    $work_history_id = intval($_POST['work_history_id']);
    $candidate_id = intval($_POST['candidate_id']);
    $showed_up = intval($_POST['showed_up']);
    $company_id = intval($_POST['company_id']);
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));
    $first_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['first_name']));
    $last_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['last_name']));
    $company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_name']));
    $work_history_job = strip_tags(mysqli_real_escape_string($mysqli,$_POST['work_history_job']));
    if($showed_up == 1){
        $status = "Do Not Hire";
    }else{
        $status = "Placed";
    }

    mysqli_query($mysqli,"UPDATE candidates SET current_status = '$status', candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    mysqli_query($mysqli,"UPDATE candidate_work_history SET showed_up = $showed_up WHERE work_history_id = $work_history_id");

    mysqli_query($mysqli,"INSERT INTO candidate_notes SET candidate_note = '$note', note_created_at = UNIX_TIMESTAMP(), note_created_by = $session_user_id, candidate_id = $candidate_id");

    $event_description = "Candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=work_history''>$first_name $last_name</a> status changed to $status on followup for company <a href=''company.php?company_id=$company_id&tab=history''>$company_name</a> job $work_history_job.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Candidate Status Change', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Candidate status changed to $status";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=work_history");
  
}

if(isset($_POST['modify_work_history'])){
    $work_history_id = intval($_POST['work_history_id']);
    $candidate_id = intval($_POST['candidate_id']);
    $company_id = intval($_POST['company_id']);
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));
    $first_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['first_name']));
    $last_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['last_name']));
    $company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['company_name']));
    $work_history_job = strip_tags(mysqli_real_escape_string($mysqli,$_POST['work_history_job']));
    $start_date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['start_date']));
    $start_time = strip_tags(mysqli_real_escape_string($mysqli,$_POST['start_time']));
    $start_date_time = $start_date . $start_time;
    $start_date_time_conv = strtotime($start_date_time);

    mysqli_query($mysqli,"UPDATE candidate_work_history SET start_date = '$start_date_time_conv' WHERE work_history_id = $work_history_id");

    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    mysqli_query($mysqli,"INSERT INTO candidate_notes SET candidate_note = '$note', note_created_at = UNIX_TIMESTAMP(), note_created_by = $session_user_id, candidate_id = $candidate_id");

    $event_description = "Candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=work_history''>$first_name $last_name</a> start time for job at company <a href=''company.php?company_id=$company_id&tab=history''>$company_name</a> job $work_history_job changed to $start_date_time_conv.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Candidate Status Change', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Candidate status changed to $status";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=work_history");
  
}

if(isset($_GET['inactive_candidate'])){
    $candidate_id = intval($_GET['inactive_candidate']);

    mysqli_query($mysqli,"UPDATE candidates SET current_status = 'Inactive', candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $_SESSION['alert_message'] = "Candidate marked inactive";
    
    header("Location: candidate.php?candidate_id=$candidate_id&tab=work_history");
  
}

if(isset($_GET['do_not_hire_candidate'])){
    $candidate_id= intval($_GET['do_not_hire_candidate']);

    mysqli_query($mysqli,"UPDATE candidates SET current_status = 'Do Not Hire', candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $event_description = "Candidate <a href=''candidate.php?candidate_id=$candidate_id''>$candidate_id</a> marked Do Not Hire!";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Candidate Status Change', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    $_SESSION['alert_message'] = "Candidate marked Do Not Hire";
    
    header("Location: candidate.php?candidate_id=$candidate_id");
  
}

if(isset($_GET['add_kbs_form'])){
    $candidate_id = intval($_GET['candidate_id']);
    $todays_date = date('m-d-Y',time());
    $sql = mysqli_query($mysqli,"SELECT * FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    $address = $row['address'];
    $city = $row['city'];
    $state = $row['state'];
    $zip = $row['zip'];
    $phone = $row['phone'];
    if(strlen($phone)>2){ $phone = substr($row['phone'],0,3)."-".substr($row['phone'],3,3)."-".substr($row['phone'],6,4);}
    $social_security = $row['social_security'];

    $sql = mysqli_query($mysqli,"SELECT * FROM candidate_emergency_contacts WHERE candidate_id = $candidate_id LIMIT 1");
    $row = mysqli_fetch_array($sql);
    $emergency_contact_name = $row['emergency_contact_name'];
    $emergency_contact_relationship = $row['emergency_contact_relationship'];
    $emergency_contact_phone = $row['emergency_contact_phone'];
    if(strlen($emergency_contact_phone)>2){ 
        $emergency_contact_phone = substr($row['emergency_contact_phone'],0,3)."-".substr($row['emergency_contact_phone'],3,3)."-".substr($row['emergency_contact_phone'],6,4);
    }
    $unix_time = time();
    
    //Set the Content Type
    header('Content-type: image/png');

    // Create Image From Existing File
    $image = imagecreatefrompng("uploads/candidate_files/$candidate_id/kbs-1-candidate-signed.png");
    $image_2 = imagecreatefrompng("uploads/candidate_files/$candidate_id/kbs-5-candidate-signed.png");

    // Allocate A Color For The Text
    $black = imagecolorallocate($image, 0, 0, 0);
    $black_2 = imagecolorallocate($image_2, 0, 0, 0);
    

    // Set Path to Font File and Font Size
    putenv('GDFONTPATH=' . realpath('.'));
    $font = 'font.ttf';
    $font_size = '25';

    // Print Text On Image
    imagettftext($image, $font_size, 0, 405, 725, $black, $font, "$first_name $last_name");
    imagettftext($image, $font_size, 0, 600, 1200, $black, $font, $social_security);
    imagettftext($image, $font_size, 0, 600, 1275, $black, $font, "$address, $city, $state, $zip");
    imagettftext($image, $font_size, 0, 405, 1355, $black, $font, $phone);

    imagettftext($image, $font_size, 0, 205, 1510, $black, $font, $emergency_contact_name);
    imagettftext($image, $font_size, 0, 1400, 1510, $black, $font, $emergency_contact_relationship);
    imagettftext($image, $font_size, 0, 400, 1585, $black, $font, $emergency_contact_phone);

    imagettftext($image_2, $font_size, 0, 110, 1745, $black_2, $font, "$session_first_name $session_last_name");
    imagettftext($image_2, $font_size, 0, 110, 2025, $black_2, $font, "$todays_date");

    //Sends Image to File
    $save = "uploads/candidate_files/$candidate_id/kbs-1-candidate-signed.png";
    imagepng($image, $save, 0, NULL);

    $save = "uploads/candidate_files/$candidate_id/kbs-5-candidate-signed-employer-signed.png";
    imagepng($image_2, $save, 0, NULL);

    // Send Image to Browser
    //imagepng($image);

    shell_exec("convert $config_www_path/uploads/candidate_files/$candidate_id/kbs-1-candidate-signed.png $config_www_path/uploads/candidate_files/$candidate_id/kbs-2-candidate-signed.png $config_www_path/uploads/candidate_files/$candidate_id/kbs-3-candidate-signed.png $config_www_path/uploads/candidate_files/$candidate_id/kbs-4-candidate-signed.png $config_www_path/uploads/candidate_files/$candidate_id/kbs-5-candidate-signed-employer-signed.png $config_www_path/uploads/candidate_files/$candidate_id/KBS-Application_$candidate_id_$todays_date_2.pdf");

    $path = "uploads/candidate_files/$candidate_id/KBS-Application_$candidate_id_$todays_date_2.pdf";

    unlink("uploads/candidate_files/$candidate_id/kbs-1-candidate-signed.png");
    unlink("uploads/candidate_files/$candidate_id/kbs-2-candidate-signed.png");
    unlink("uploads/candidate_files/$candidate_id/kbs-3-candidate-signed.png");
    unlink("uploads/candidate_files/$candidate_id/kbs-4-candidate-signed.png");
    unlink("uploads/candidate_files/$candidate_id/kbs-5-candidate-signed.png");
    unlink("uploads/candidate_files/$candidate_id/kbs-5-candidate-signed-employer-signed.png");

    mysqli_query($mysqli,"INSERT INTO files SET file_location = '$path', uploaded_at = UNIX_TIMESTAMP(), uploaded_by = $session_user_id, candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "File <a href=''$path''>$file_name</a> uploaded for candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=files''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Candidate File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    header("Location: candidate.php?candidate_id=$candidate_id&tab=files");

    // Clear Memory
    imagedestroy($image);

}

if(isset($_POST['add_w4'])){
    $candidate_id = intval($_POST['candidate_id']);
    $first_date_of_employment = strip_tags($_POST['first_date_of_employment']);
    $ein = strip_tags($_POST['ein']);
    $todays_date = date('m-d-Y',time());
    
    //Set the Content Type
    header('Content-type: image/png');

    // Create Image From Existing File
    $image = imagecreatefrompng("uploads/candidate_files/$candidate_id/w4-candidate-filled.png");

    // Allocate A Color For The Text
    $black = imagecolorallocate($image, 0, 0, 0);

    // Set Path to Font File and Font Size
    putenv('GDFONTPATH=' . realpath('.'));
    $font = 'font.ttf';
    $font_size = '25';

    // Print Text On Image

    imagettftext($image, $font_size, 0, 1085, 2095, $black, $font, $first_date_of_employment);
    imagettftext($image, $font_size, 0, 1310, 2095, $black, $font, $ein);

    //Sends Image to File
    $save = "uploads/candidate_files/$candidate_id/w4-candidate-filled.png";
    imagepng($image, $save, 0, NULL);

    // Send Image to Browser
    //imagepng($image);

    shell_exec("convert $config_www_path/uploads/candidate_files/$candidate_id/w4-candidate-filled.png $config_www_path/uploads/candidate_files/$candidate_id/w4_$candidate_id_$todays_date_2.pdf");

    $path = "uploads/candidate_files/$candidate_id/w4_$candidate_id_$todays_date_2.pdf";

    //unlink("uploads/candidate_files/$candidate_id/w4-candidate-filled.png");

    mysqli_query($mysqli,"INSERT INTO files SET file_location = '$path', uploaded_at = UNIX_TIMESTAMP(), uploaded_by = $session_user_id, candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "File <a href=''$path''>$file_name</a> uploaded for candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=files''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Candidate File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    header("Location: candidate.php?candidate_id=$candidate_id&tab=files");

    // Clear Memory
    imagedestroy($image);

}

if(isset($_POST['add_i9'])){
    $i9_list_a_document_title = strip_tags($_POST['i9_list_a_document_title']);
    if(empty($i9_list_a_document_title)){
        $i9_list_a_document_title = "N/A";
    }
    $i9_list_a_issuing_authority = strip_tags($_POST['i9_list_a_issuing_authority']);
    if(empty($i9_list_a_issuing_authority)){
        $i9_list_a_issuing_authority = "N/A";
    }
    $i9_list_a_document_number = strip_tags($_POST['i9_list_a_document_number']);
    if(empty($i9_list_a_document_number)){
        $i9_list_a_document_number = "N/A";
    }
    if(empty($_POST['i9_list_a_expiration_date'])){
        $i9_list_a_expiration_date = "N/A";
    }else{
        $i9_list_a_expiration_date = date('m-d-Y',strtotime(strip_tags($_POST['i9_list_a_expiration_date'])));
    }
    $i9_list_b_document_title = strip_tags($_POST['i9_list_b_document_title']);
    if(empty($i9_list_b_document_title)){
        $i9_list_b_document_title = "N/A";
    }
    $i9_list_b_issuing_authority = strip_tags($_POST['i9_list_b_issuing_authority']);
    if(empty($i9_list_b_issuing_authority)){
        $i9_list_b_issuing_authority = "N/A";
    }
    $i9_list_b_document_number = strip_tags($_POST['i9_list_b_document_number']);
    if(empty($i9_list_b_document_number)){
        $i9_list_b_document_number = "N/A";
    }
    if(empty($_POST['i9_list_b_expiration_date'])){
        $i9_list_b_expiration_date = "N/A";
    }else{
        $i9_list_b_expiration_date = date('m-d-Y',strtotime(strip_tags($_POST['i9_list_b_expiration_date'])));
    }
    $i9_list_c_document_title = strip_tags($_POST['i9_list_c_document_title']);
    if(empty($i9_list_c_document_title)){
        $i9_list_c_document_title = "N/A";
    }
    $i9_list_c_issuing_authority = strip_tags($_POST['i9_list_c_issuing_authority']);
    if(empty($i9_list_c_issuing_authority)){
        $i9_list_c_issuing_authority = "N/A";
    }
    $i9_list_c_document_number = strip_tags($_POST['i9_list_c_document_number']);
    if(empty($i9_list_c_document_number)){
        $i9_list_c_document_number = "N/A";
    }
    if(empty($_POST['i9_list_c_expiration_date'])){
        $i9_list_c_expiration_date = "N/A";
    }else{
        $i9_list_c_expiration_date = date('m-d-Y',strtotime(strip_tags($_POST['i9_list_c_expiration_date'])));
    }
    $i9_first_day_of_employment = date('m-d-Y',strtotime(strip_tags($_POST['i9_first_day_of_employment'])));
    $i9_employer_title = strip_tags($_POST['i9_employer_title']);
    $i9_employer_last_name = strip_tags($_POST['i9_employer_last_name']);
    $i9_employer_first_name = strip_tags($_POST['i9_employer_first_name']);
    $i9_employer_business_name = strip_tags($_POST['i9_employer_business_name']);
    $i9_employer_business_address = strip_tags($_POST['i9_employer_business_address']);
    $i9_employer_business_city_or_town = strip_tags($_POST['i9_employer_business_city_or_town']);
    $i9_employer_business_state = strip_tags($_POST['i9_employer_business_state']);
    $i9_employer_business_zip_code = strip_tags($_POST['i9_employer_business_zip_code']);
    $signature = $_POST['signature_image_base64'];
    $unix_time = time(); 

    $candidate_id = intval($_POST['candidate_id']);
    $todays_date = date('m-d-Y',time());
    $todays_date_2 = date('Y-m-d',time());
    
    //Set the Content Type
    header('Content-type: image/png');

    // Create Image From Existing File
    $image = imagecreatefrompng("uploads/candidate_files/$candidate_id/i9-2-candidate-filled.png");
    $sig = imagecreatefrompng($signature);
   
    $sig = imagescale($sig,380,45);

    // Allocate A Color For The Text
    $white = imagecolorallocate($image, 255, 255, 255);
    $grey = imagecolorallocate($image, 128, 128, 128);
    $black = imagecolorallocate($image, 0, 0, 0);
    

    // Set Path to Font File and Font Size
    putenv('GDFONTPATH=' . realpath('.'));
    $font = 'font.ttf';
    $font_size = '18';

    // Print Text On Image
    imagettftext($image, $font_size, 0, 110, 540, $black, $font, $i9_list_a_document_title);
    imagettftext($image, $font_size, 0, 110, 605, $black, $font, $i9_list_a_issuing_authority);
    imagettftext($image, $font_size, 0, 110, 665, $black, $font, $i9_list_a_document_number);
    imagettftext($image, $font_size, 0, 110, 730, $black, $font, $i9_list_a_expiration_date);

    imagettftext($image, $font_size, 0, 625, 540, $black, $font, $i9_list_b_document_title);
    imagettftext($image, $font_size, 0, 625, 605, $black, $font, $i9_list_b_issuing_authority);
    imagettftext($image, $font_size, 0, 625, 665, $black, $font, $i9_list_b_document_number);
    imagettftext($image, $font_size, 0, 625, 730, $black, $font, $i9_list_b_expiration_date);

    imagettftext($image, $font_size, 0, 1130, 540, $black, $font, $i9_list_c_document_title);
    imagettftext($image, $font_size, 0, 1130, 605, $black, $font, $i9_list_c_issuing_authority);
    imagettftext($image, $font_size, 0, 1130, 665, $black, $font, $i9_list_c_document_number);
    imagettftext($image, $font_size, 0, 1130, 730, $black, $font, $i9_list_c_expiration_date);

    imagettftext($image, $font_size, 0, 795, 1385, $black, $font, $i9_first_day_of_employment);

    imagecopy($image, $sig, 110, 1430 , 0, 0, 380, 45);
    imagettftext($image, $font_size, 0, 750, 1470, $black, $font, $todays_date);
    imagettftext($image, $font_size, 0, 1065, 1470, $black, $font, $i9_employer_title);

    imagettftext($image, $font_size, 0, 110, 1545, $black, $font, $i9_employer_last_name);
    imagettftext($image, $font_size, 0, 620, 1545, $black, $font, $i9_employer_first_name);
    imagettftext($image, $font_size, 0, 1130, 1545, $black, $font, $i9_employer_business_name);

    imagettftext($image, $font_size, 0, 110, 1620, $black, $font, $i9_employer_business_address);
    imagettftext($image, $font_size, 0, 875, 1620, $black, $font, $i9_employer_business_city_or_town);
    imagettftext($image, $font_size, 0, 1245, 1620, $black, $font, $i9_employer_business_state);
    imagettftext($image, $font_size, 0, 1350, 1620, $black, $font, $i9_employer_business_zip_code);


    // Send Image to Browser
    //imagepng($image);

    //Sends Image to File
    $save = "uploads/candidate_files/$candidate_id/i9-2-employer-filled.png";
    imagepng($image, $save, 0, NULL);

    //Convert PNGs and Combine them to PDF
    shell_exec("convert $config_www_path/uploads/candidate_files/$candidate_id/i9-1-candidate-filled.png $config_www_path/uploads/candidate_files/$candidate_id/i9-2-employer-filled.png $config_www_path/uploads/candidate_files/$candidate_id/i9_$candidate_id_$todays_date_2.pdf");

    //Delete PNGs after they been converted to PDF
    unlink("uploads/candidate_files/$candidate_id/i9-1-candidate-filled.png");
    unlink("uploads/candidate_files/$candidate_id/i9-2-candidate-filled.png");
    unlink("uploads/candidate_files/$candidate_id/i9-2-employer-filled.png");

    $path = "uploads/candidate_files/$candidate_id/i9_$candidate_id_$todays_date_2.pdf";

    mysqli_query($mysqli,"INSERT INTO files SET file_location = '$path', uploaded_at = UNIX_TIMESTAMP(), uploaded_by = $session_user_id, candidate_id = $candidate_id");
    
    mysqli_query($mysqli,"UPDATE candidates SET candidate_updated_at = UNIX_TIMESTAMP(), candidate_updated_by = $session_user_id WHERE candidate_id = $candidate_id");

    $sql = mysqli_query($mysqli,"SELECT first_name, last_name FROM candidates WHERE candidate_id = $candidate_id");
    $row = mysqli_fetch_array($sql);

    $first_name = $row['first_name'];
    $last_name = $row['last_name'];

    $event_description = "File <a href=''$path''>$file_name</a> uploaded for candidate <a href=''candidate.php?candidate_id=$candidate_id&tab=files''>$first_name $last_name</a>.";

    mysqli_query($mysqli,"INSERT INTO events SET event_type = 'Add Candidate File', event_description = '$event_description', event_created_at = UNIX_TIMESTAMP(), user_id = $session_user_id");

    header("Location: candidate.php?candidate_id=$candidate_id&tab=files");

    // Clear Memory
    //imagedestroy($image);

}

if(isset($_POST['add_ticket'])){
    $ticket_subject = strip_tags(mysqli_real_escape_string($mysqli,$_POST['ticket_subject']));
    $ticket_body = strip_tags(mysqli_real_escape_string($mysqli,$_POST['ticket_body']));

    mysqli_query($mysqli,"INSERT INTO tickets SET ticket_status = 'New', ticket_subject = '$ticket_subject', ticket_body = '$ticket_body', ticket_created_at = UNIX_TIMESTAMP(), ticket_created_by = $session_user_id");

    $_SESSION['alert_message'] = "Ticket Created";
    
    header("Location: tickets.php");

}
if(isset($_GET['hello'])){
    $hello = $_GET['hello'];
    echo $hello;
}

?>	