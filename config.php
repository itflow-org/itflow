<?php
	//DB Settings

	$dbhost = "localhost";
	$dbusername = "root";
	$dbpassword = "password";
	$database = "pittpc";

	$mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database);
	
	//General Settings

	$config_date_format = "Y-m-d";
	$config_time_format = "h:ia";
	$config_no_records = "There is nothing here!";
	$config_default_expenes_account = "";
	$config_default_invoice_account = "";
	$config_default_net_terms = "";
	$config_default_starting_location = "";



	$config_start_page = "clients.php";

	$config_company_name = "PittPC";
	$config_company_address = "123 PittPC Street";
	$config_company_city = "Pittsburgh";
	$config_company_state = "PA";
	$config_company_zip = "15205";
	$config_company_phone = "412-500-9434";
	$config_company_site = "pittpc.com";

	$config_invoice_logo = "/uploads/invoice_logo.png";
	$config_invoice_footer = "Please make checks payable to PittPC<br>Visit us at pittpc.com";

	//Mail Settings (Host must require TLS Support)
	$config_smtp_host = "";
	$config_smtp_username = "";
	$config_smtp_password = "";
	$config_smtp_port = 587;
	$config_mail_from_email = "";
	$config_mail_from_name = "Automated Billing Department";

	$_SESSION['alert_message'] = '';
	$_SESSION['alert_type'] = "warning";

	

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
		'Laptop',
		'Desktop',
		'Mobile Phone',
		'Tablet',
		'Firewall/Router',
		'Switch',
		'Access Point',
		'Printer',
		'TV',
		'Camera',
		'TV',
		'Virtual Machine',
		'Other'
	);

	$application_types_array = array(
		'Web App',
		'Desktop App',
		'Other'
	);
	
?>
