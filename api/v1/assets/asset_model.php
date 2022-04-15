<?php
$type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_type'])));
$name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_name'])));
$make = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_make'])));
$model = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_model'])));
$serial = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_serial'])));
$os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_os'])));
$asset_ip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_ip'])));
$mac = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_mac'])));
$purchase_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_purchase_date'])));
if(empty($purchase_date)){
  $purchase_date = "0000-00-00";
}
$warranty_expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_warranty_expire'])));
if(empty($warranty_expire)){
  $warranty_expire = "0000-00-00";
}
$install_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_install_date'])));
if(empty($install_date)){
  $install_date = "0000-00-00";
}
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_notes'])));
$meshcentral_id = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_meshcentral_id'])));
$vendor = intval($_POST['asset_vendor_id']);
$location = intval($_POST['asset_location_id']);
$contact = intval($_POST['asset_contact_id']);
$network = intval($_POST['asset_network_id']);