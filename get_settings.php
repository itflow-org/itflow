<?php

//Query Settings
$sql_settings = mysqli_query($mysqli,"SELECT * FROM settings WHERE company_id = $session_company_id");
$row = mysqli_fetch_array($sql_settings);

//General
$config_api_key = $row['config_api_key'];
$config_aes_key = $row['config_aes_key'];
$config_base_url = $row['config_base_url'];

//Mail
$config_smtp_host = $row['config_smtp_host'];
$config_smtp_port = $row['config_smtp_port'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_mail_from_email = $row['config_mail_from_email'];
$config_mail_from_name = $row['config_mail_from_name'];

//Defaults
$config_default_country = $row['config_default_country'];
$config_default_currency = $row['config_default_currency'];
$config_default_transfer_from_account = $row['config_default_transfer_from_account'];
$config_default_transfer_to_account = $row['config_default_transfer_to_account'];
$config_default_payment_account = $row['config_default_payment_account'];
$config_default_expense_account = $row['config_default_expense_account'];
$config_default_payment_method = $row['config_default_payment_method'];
$config_default_expense_payment_method = $row['config_default_expense_payment_method'];
$config_default_calendar = $row['config_default_calendar'];
$config_default_net_terms = $row['config_default_net_terms'];

//Invoice/Quote
$config_invoice_prefix = $row['config_invoice_prefix'];
$config_invoice_next_number = $row['config_invoice_next_number'];
$config_invoice_footer = $row['config_invoice_footer'];

$config_recurring_prefix = $row['config_recurring_prefix'];
$config_recurring_next_number = $row['config_recurring_next_number'];

$config_quote_prefix = $row['config_quote_prefix'];
$config_quote_next_number = $row['config_quote_next_number'];
$config_quote_footer = $row['config_quote_footer'];

//Tickets
$config_ticket_prefix = $row['config_ticket_prefix'];
$config_ticket_next_number = $row['config_ticket_next_number'];

//Alerts
$config_enable_cron = $row['config_enable_cron'];

$config_enable_alert_low_balance = $row['config_enable_alert_low_balance'];
$config_account_balance_threshold = $row['config_account_balance_threshold'];

$config_recurring_auto_send_invoice = $row['config_recurring_auto_send_invoice'];
$config_enable_alert_domain_expire = $row['config_enable_alert_domain_expire'];
$config_send_invoice_reminders = $row['config_send_invoice_reminders'];
$config_invoice_overdue_reminders = $row['config_invoice_overdue_reminders'];

//Online Payment
$config_stripe_enable = $row['config_stripe_enable'];
$config_stripe_publishable = $row['config_stripe_publishable'];
$config_stripe_secret = $row['config_stripe_secret'];

$net_terms_array = array(
    '0'=>'On Reciept',
    '7'=>'7 Days',
    '14'=>'14 Days',
    '30'=>'30 Days'    
);

$records_per_page_array = array('5','10','15','20','30','50','100');

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
    'WY'=>'Wyoming',
    'ON'=>'Ontario',
    'QC'=>'Quebec',
    'NS'=>'Nova Scotia',
    'NB'=>'New Brunswick',
    'MB'=>'Manitoba',
    'BC'=>'British Columbia',
    'PE'=>'Prince Edward Island',
    'SK'=>'Saskatchewan',
    'AB'=>'Alberta',
    'NL'=>'Newfoundland and Labrador'
);

$canada_provinces_array = array(
    'ON'=>'Ontario',
    'QC'=>'Quebec',
    'NS'=>'Nova Scotia',
    'NB'=>'New Brunswick',
    'MB'=>'Manitoba',
    'BC'=>'British Columbia',
    'PE'=>'Prince Edward Island',
    'SK'=>'Saskatchewan',
    'AB'=>'Alberta',
    'NL'=>'Newfoundland and Labrador'
);

$countries_array = array(
    'USA',
    'Canada'
);

$timezones_array = array(
	'US/Eastern',
	'US/Central',
	'US/Mountain',
	'US/Pacific'
);

$currencies_array = array(
    'USD'=>'US Dollars',
    'EUR'=>'Euro',
    'GBP'=>'British Pounds',
    'TRY'=>'Turkish Lira'
);

$category_types_array = array(
	'Expense',
	'Income',
	'Payment Method',
    'Referral'
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