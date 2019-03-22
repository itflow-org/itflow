<?php
	
	$dbhost = "localhost";
	$dbusername = "root";
	$dbpassword = "password";
	$database = "pittpc";

	$mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database);
	$config_date_format = "Y-m-d";
	$config_time_format = "h:ia";
	$config_no_records = "There is nothing here!";

	$config_start_page = "clients.php";

	$config_company_name = "PittPC";
	$config_company_address = "";
	$config_company_city = "";
	$config_company_state = "";
	$config_company_zip = "";

	$_SESSION['alert_message'] = '';
	$_SESSION['alert_type'] = "warning";

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
