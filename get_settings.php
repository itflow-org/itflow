<?php

//General Settings

$sql_settings = mysqli_query($mysqli,"SELECT * FROM settings WHERE company_id = $session_company_id");
$row = mysqli_fetch_array($sql_settings);

$config_start_page = $row['config_start_page'];

$config_company_name = $row['config_company_name'];
$config_company_address = $row['config_company_address'];
$config_company_city = $row['config_company_city'];
$config_company_state = $row['config_company_state'];
$config_company_zip = $row['config_company_zip'];
$config_company_phone = $row['config_company_phone'];
if(strlen($config_company_phone)>2){ 
  $config_company_phone = substr($row['config_company_phone'],0,3)."-".substr($row['config_company_phone'],3,3)."-".substr($row['config_company_phone'],6,4);
}
$config_company_site = $row['config_company_site'];

$config_quote_footer = $row['config_quote_footer'];
$config_quote_email_subject = $row['config_quote_email_subject'];
$config_quote_next_number = $row['config_quote_next_number'];
$config_quote_prefix = $row['config_quote_prefix'];


$config_smtp_host = $row['config_smtp_host'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_smtp_port = $row['config_smtp_port'];
$config_mail_from_email = $row['config_mail_from_email'];
$config_mail_from_name = $row['config_mail_from_name'];

$config_account_balance_threshold = $row['config_account_balance_threshold'];

$config_send_invoice_reminders = $row['config_send_invoice_reminders'];
$config_invoice_logo = $row['config_invoice_logo'];
$config_invoice_footer = $row['config_invoice_footer'];
$config_invoice_overdue_reminders = $row['config_invoice_overdue_reminders'];
$config_invoice_next_number = $row['config_invoice_next_number'];
$config_invoice_prefix = $row['config_invoice_prefix'];

$config_ticket_next_number = $row['config_ticket_next_number'];
$config_ticket_prefix = $row['config_ticket_prefix'];

//Defaults
$config_default_expense_account = $row['config_default_expense_account'];
$config_default_payment_account = $row['config_default_payment_account'];
$config_default_transfer_from_account = $row['config_default_transfer_from_account'];
$config_default_transfer_to_account = $row['config_default_transfer_to_account'];
$config_default_calendar = $row['config_default_calendar'];
$config_default_payment_method = $row['config_default_payment_method'];
$config_default_expense_payment_method = $row['config_default_expense_payment_method'];
$config_default_net_terms = $row['config_default_net_terms'];

$config_recurring_auto_send_invoice = $row['config_recurring_auto_send_invoice'];

$config_api_key = $row['config_api_key'];
$config_enable_cron = $row['config_enable_cron'];

$config_base_url = $row['config_base_url'];

$client_types_array = array(
    'Residential',
   	'Law',
    'Tax and Accounting',
    'General Contractor',
    'Medical',
    'Non Profit',
    'Industrial',
    'Automotive',
    'Retail',
    'Staffing Agency',
    'Photography',
    'Restaurant',
    'Bar',
    'Real Estate',
    'Dental',
    'Farm',
    'Other'
);

$net_terms_array = array(
    '0'=>'On Reciept',
    '7'=>'7 Days',
    '14'=>'14 Days',
    '30'=>'30 Days'    
);

$states_array = array(
    'AL'=>'Alabama',
    'AK'=>'Alaska',
    'AZ'=>'Arizona',
    'AR'=>'Arkansas',
    'CA'=>'California',
    'CO'=>'Colorado',
    'CT'=>'Connecticut',
    'DE'=>'Delaware',
    'DC'=>'District of Columbia',
    'FL'=>'Florida',
    'GA'=>'Georgia',
    'HI'=>'Hawaii',
    'ID'=>'Idaho',
    'IL'=>'Illinois',
    'IN'=>'Indiana',
    'IA'=>'Iowa',
    'KS'=>'Kansas',
    'KY'=>'Kentucky',
    'LA'=>'Louisiana',
    'ME'=>'Maine',
    'MD'=>'Maryland',
    'MA'=>'Massachusetts',
    'MI'=>'Michigan',
    'MN'=>'Minnesota',
    'MS'=>'Mississippi',
    'MO'=>'Missouri',
    'MT'=>'Montana',
    'NE'=>'Nebraska',
    'NV'=>'Nevada',
    'NH'=>'New Hampshire',
    'NJ'=>'New Jersey',
    'NM'=>'New Mexico',
    'NY'=>'New York',
    'NC'=>'North Carolina',
    'ND'=>'North Dakota',
    'OH'=>'Ohio',
    'OK'=>'Oklahoma',
    'OR'=>'Oregon',
    'PA'=>'Pennsylvania',
    'RI'=>'Rhode Island',
    'SC'=>'South Carolina',
    'SD'=>'South Dakota',
    'TN'=>'Tennessee',
    'TX'=>'Texas',
    'UT'=>'Utah',
    'VT'=>'Vermont',
    'VA'=>'Virginia',
    'WA'=>'Washington',
    'WV'=>'West Virginia',
    'WI'=>'Wisconsin',
    'WY'=>'Wyoming'
);

$timezones_array = array(
	'US/Eastern',
	'US/Central',
	'US/Mountain',
	'US/Pacific'
);

$category_types_array = array(
	'Expense',
	'Income',
	'Payment Method'
);

$asset_types_array = array(
	'Laptop'=>'fa-laptop',
	'Desktop'=>'fa-desktop',
	'Server'=>'fa-server',
	'Phone'=>'fa-phone',
    'Mobile Phone'=>'fa-mobile-alt',
	'Tablet'=>'fa-tablet-alt',
	'Firewall/Router'=>'fa-network-wired',
	'Switch'=>'fa-network-wired',
	'Access Point'=>'fa-wifi',
	'Printer'=>'fa-print',
	'Camera'=>'fa-video',
	'TV'=>'fa-tv',
	'Virtual Machine'=>'fa-cloud',
	'Other'=>'fa-tag'
);

$software_types_array = array(
	'Operating System',
	'Web App',
	'Desktop App',
	'Other'
);
?>