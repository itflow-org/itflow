<?php

//Query Settings
$sql_settings = mysqli_query($mysqli,"SELECT * FROM settings WHERE company_id = $session_company_id");
$row = mysqli_fetch_array($sql_settings);

//General
$config_api_key = $row['config_api_key'];
$config_aes_key = $row['config_aes_key']; //Legacy
$config_base_url = $row['config_base_url'];

//Mail
$config_smtp_host = $row['config_smtp_host'];
$config_smtp_port = $row['config_smtp_port'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_mail_from_email = $row['config_mail_from_email'];
$config_mail_from_name = $row['config_mail_from_name'];

//Defaults
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
  'ALL' => 'Albania Lek',
  'AFN' => 'Afghanistan Afghani',
  'ARS' => 'Argentina Peso',
  'AWG' => 'Aruba Guilder',
  'AUD' => 'Australia Dollar',
  'AZN' => 'Azerbaijan New Manat',
  'BSD' => 'Bahamas Dollar',
  'BBD' => 'Barbados Dollar',
  'BDT' => 'Bangladeshi taka',
  'BYR' => 'Belarus Ruble',
  'BZD' => 'Belize Dollar',
  'BMD' => 'Bermuda Dollar',
  'BOB' => 'Bolivia Boliviano',
  'BAM' => 'Bosnia and Herzegovina Convertible Marka',
  'BWP' => 'Botswana Pula',
  'BGN' => 'Bulgaria Lev',
  'BRL' => 'Brazil Real',
  'BND' => 'Brunei Darussalam Dollar',
  'KHR' => 'Cambodia Riel',
  'CAD' => 'Canada Dollar',
  'KYD' => 'Cayman Islands Dollar',
  'CLP' => 'Chile Peso',
  'CNY' => 'China Yuan Renminbi',
  'COP' => 'Colombia Peso',
  'CRC' => 'Costa Rica Colon',
  'HRK' => 'Croatia Kuna',
  'CUP' => 'Cuba Peso',
  'CZK' => 'Czech Republic Koruna',
  'DKK' => 'Denmark Krone',
  'DOP' => 'Dominican Republic Peso',
  'XCD' => 'East Caribbean Dollar',
  'EGP' => 'Egypt Pound',
  'SVC' => 'El Salvador Colon',
  'EEK' => 'Estonia Kroon',
  'EUR' => 'Euro Member Countries',
  'FKP' => 'Falkland Islands (Malvinas) Pound',
  'FJD' => 'Fiji Dollar',
  'GHC' => 'Ghana Cedis',
  'GIP' => 'Gibraltar Pound',
  'GTQ' => 'Guatemala Quetzal',
  'GGP' => 'Guernsey Pound',
  'GYD' => 'Guyana Dollar',
  'HNL' => 'Honduras Lempira',
  'HKD' => 'Hong Kong Dollar',
  'HUF' => 'Hungary Forint',
  'ISK' => 'Iceland Krona',
  'INR' => 'India Rupee',
  'IDR' => 'Indonesia Rupiah',
  'IRR' => 'Iran Rial',
  'IMP' => 'Isle of Man Pound',
  'ILS' => 'Israel Shekel',
  'JMD' => 'Jamaica Dollar',
  'JPY' => 'Japan Yen',
  'JEP' => 'Jersey Pound',
  'KZT' => 'Kazakhstan Tenge',
  'KPW' => 'Korea (North) Won',
  'KRW' => 'Korea (South) Won',
  'KGS' => 'Kyrgyzstan Som',
  'LAK' => 'Laos Kip',
  'LVL' => 'Latvia Lat',
  'LBP' => 'Lebanon Pound',
  'LRD' => 'Liberia Dollar',
  'LTL' => 'Lithuania Litas',
  'MKD' => 'Macedonia Denar',
  'MYR' => 'Malaysia Ringgit',
  'MUR' => 'Mauritius Rupee',
  'MXN' => 'Mexico Peso',
  'MNT' => 'Mongolia Tughrik',
  'MZN' => 'Mozambique Metical',
  'NAD' => 'Namibia Dollar',
  'NPR' => 'Nepal Rupee',
  'ANG' => 'Netherlands Antilles Guilder',
  'NZD' => 'New Zealand Dollar',
  'NIO' => 'Nicaragua Cordoba',
  'NGN' => 'Nigeria Naira',
  'NOK' => 'Norway Krone',
  'OMR' => 'Oman Rial',
  'PKR' => 'Pakistan Rupee',
  'PAB' => 'Panama Balboa',
  'PYG' => 'Paraguay Guarani',
  'PEN' => 'Peru Nuevo Sol',
  'PHP' => 'Philippines Peso',
  'PLN' => 'Poland Zloty',
  'QAR' => 'Qatar Riyal',
  'RON' => 'Romania New Leu',
  'RUB' => 'Russia Ruble',
  'SHP' => 'Saint Helena Pound',
  'SAR' => 'Saudi Arabia Riyal',
  'RSD' => 'Serbia Dinar',
  'SCR' => 'Seychelles Rupee',
  'SGD' => 'Singapore Dollar',
  'SBD' => 'Solomon Islands Dollar',
  'SOS' => 'Somalia Shilling',
  'ZAR' => 'South Africa Rand',
  'LKR' => 'Sri Lanka Rupee',
  'SEK' => 'Sweden Krona',
  'CHF' => 'Switzerland Franc',
  'SRD' => 'Suriname Dollar',
  'SYP' => 'Syria Pound',
  'TWD' => 'Taiwan New Dollar',
  'THB' => 'Thailand Baht',
  'TTD' => 'Trinidad and Tobago Dollar',
  'TRY' => 'Turkey Lira',
  'TRL' => 'Turkey Lira',
  'TVD' => 'Tuvalu Dollar',
  'UAH' => 'Ukraine Hryvna',
  'GBP' => 'United Kingdom Pound',
  'USD' => 'United States Dollar',
  'UYU' => 'Uruguay Peso',
  'UZS' => 'Uzbekistan Som',
  'VEF' => 'Venezuela Bolivar',
  'VND' => 'Viet Nam Dong',
  'YER' => 'Yemen Rial',
  'ZWD' => 'Zimbabwe Dollar'
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