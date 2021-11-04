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

$colors_array = array(
    'green',
    'olive',
    'yellowgreen',
    'lime',
    'blue',
    'darkblue',
    'cadetblue',
    'cyan',
    'purple',
    'indigo',
    'magenta',
    'red',
    'crimson',
    'indianred',
    'pink',
    'orange',
    'teal',
    'black',
    'gray-dark',
    'gray' 
);

$net_terms_array = array(
    '0'=>'On Reciept',
    '7'=>'7 Days',
    '14'=>'14 Days',
    '30'=>'30 Days'    
);

$records_per_page_array = array('5','10','15','20','30','50','100');

$countries_array = array(
    "Afghanistan",
    "Albania",
    "Algeria",
    "Andorra",
    "Angola",
    "Antigua and Barbuda",
    "Argentina",
    "Armenia",
    "Australia",
    "Austria",
    "Azerbaijan",
    "Bahamas",
    "Bahrain",
    "Bangladesh",
    "Barbados",
    "Belarus",
    "Belgium",
    "Belize",
    "Benin",
    "Bhutan",
    "Bolivia",
    "Bosnia and Herzegovina",
    "Botswana",
    "Brazil",
    "Brunei",
    "Bulgaria",
    "Burkina Faso",
    "Burundi",
    "Cambodia",
    "Cameroon",
    "Canada",
    "Cape Verde",
    "Central African Republic",
    "Chad",
    "Chile",
    "China",
    "Colombi",
    "Comoros",
    "Congo (Brazzaville)",
    "Congo",
    "Costa Rica",
    "Cote d'Ivoire",
    "Croatia",
    "Cuba",
    "Cyprus",
    "Czech Republic",
    "Denmark",
    "Djibouti",
    "Dominica",
    "Dominican Republic",
    "East Timor (Timor Timur)",
    "Ecuador",
    "Egypt",
    "El Salvador",
    "Equatorial Guinea",
    "Eritrea",
    "Estonia",
    "Ethiopia",
    "Fiji",
    "Finland",
    "France",
    "Gabon",
    "Gambia, The",
    "Georgia",
    "Germany",
    "Ghana",
    "Greece",
    "Grenada",
    "Guatemala",
    "Guinea",
    "Guinea-Bissau",
    "Guyana",
    "Haiti",
    "Honduras",
    "Hungary",
    "Iceland",
    "India",
    "Indonesia",
    "Iran",
    "Iraq",
    "Ireland",
    "Israel",
    "Italy",
    "Jamaica",
    "Japan",
    "Jordan",
    "Kazakhstan",
    "Kenya",
    "Kiribati",
    "Korea, North",
    "Korea, South",
    "Kuwait",
    "Kyrgyzstan",
    "Laos",
    "Latvia",
    "Lebanon",
    "Lesotho",
    "Liberia",
    "Libya",
    "Liechtenstein",
    "Lithuania",
    "Luxembourg",
    "Macedonia",
    "Madagascar",
    "Malawi",
    "Malaysia",
    "Maldives",
    "Mali",
    "Malta",
    "Marshall Islands",
    "Mauritania",
    "Mauritius",
    "Mexico",
    "Micronesia",
    "Moldova",
    "Monaco",
    "Mongolia",
    "Morocco",
    "Mozambique",
    "Myanmar",
    "Namibia",
    "Nauru",
    "Nepal",
    "Netherlands",
    "New Zealand",
    "Nicaragua",
    "Niger",
    "Nigeria",
    "Norway",
    "Oman",
    "Pakistan",
    "Palau",
    "Panama",
    "Papua New Guinea",
    "Paraguay",
    "Peru",
    "Philippines",
    "Poland",
    "Portugal",
    "Qatar",
    "Romania",
    "Russia",
    "Rwanda",
    "Saint Kitts and Nevis",
    "Saint Lucia",
    "Saint Vincent",
    "Samoa",
    "San Marino",
    "Sao Tome and Principe",
    "Saudi Arabia",
    "Senegal",
    "Serbia and Montenegro",
    "Seychelles",
    "Sierra Leone",
    "Singapore",
    "Slovakia",
    "Slovenia",
    "Solomon Islands",
    "Somalia",
    "South Africa",
    "Spain",
    "Sri Lanka",
    "Sudan",
    "Suriname",
    "Swaziland",
    "Sweden",
    "Switzerland",
    "Syria",
    "Taiwan",
    "Tajikistan",
    "Tanzania",
    "Thailand",
    "Togo",
    "Tonga",
    "Trinidad and Tobago",
    "Tunisia",
    "Turkey",
    "Turkmenistan",
    "Tuvalu",
    "Uganda",
    "Ukraine",
    "United Arab Emirates",
    "United Kingdom",
    "United States",
    "Uruguay",
    "Uzbekistan",
    "Vanuatu",
    "Vatican City",
    "Venezuela",
    "Vietnam",
    "Yemen",
    "Zambia",
    "Zimbabwe"
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