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
	$config_default_expense_account = "";
	$config_default_payment_account = "";
	$config_default_net_terms = 7;

	$sql = mysqli_query($mysqli,"SELECT * FROM settings");
	$row = mysqli_fetch_array($sql);

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
	$config_next_invoice_number = $row['config_next_invoice_number'];
	$config_invoice_logo = $row['config_invoice_logo'];
	$config_invoice_footer = $row['config_invoice_footer'];
	$config_quote_footer = $row['config_quote_footer'];
	$config_smtp_host = $row['config_smtp_host'];
	$config_smtp_username = $row['config_smtp_username'];
	$config_smtp_password = $row['config_smtp_password'];
	$config_smtp_port = $row['config_smtp_port'];
	$config_mail_from_email = $row['config_mail_from_email'];
	$config_mail_from_name = $row['config_mail_from_name'];
	$config_account_balance_threshold = $row['config_account_balance_threshold'];

	$config_api_key = $row['config_api_key'];

	$_SESSION['alert_message'] = '';
	$_SESSION['alert_type'] = "warning";

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
		'Laptop',
		'Desktop',
		'Server',
		'Mobile Phone',
		'Tablet',
		'Firewall/Router',
		'Switch',
		'Access Point',
		'Printer',
		'Camera',
		'TV',
		'Virtual Machine',
		'Other'
	);

	$software_types_array = array(
		'Operating System',
		'Web App',
		'Desktop App',
		'Other'
	);
	
?>