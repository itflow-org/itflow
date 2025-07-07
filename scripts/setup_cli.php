#!/usr/bin/env php
<?php

// Example
//php setup_cli.php --help
//php setup_cli.php --host=localhost --username=itflow --password=secret --database=itflow --base-url=example.com/itflow --locale=en_US --timezone=UTC --currency=USD --company-name="My Company" --country="United States" --user-name="John Doe" --user-email="john@example.com" --user-password="admin123" --non-interactive

// Change to the directory of this script so that all shell commands run here
chdir(__DIR__);

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Define required arguments
$required_args = [
    'host'         => 'Database host',
    'username'     => 'Database username',
    'password'     => 'Database password',
    'database'     => 'Database name',
    'base-url'     => 'Base URL (without protocol, e.g. example.com/itflow)',
    'locale'       => 'Locale (e.g. en_US)',
    'timezone'     => 'Timezone (e.g. UTC)',
    'currency'     => 'Currency code (e.g. USD)',
    'company-name' => 'Company name',
    'country'      => 'Company country (e.g. United States)',
    'user-name'    => 'Admin user full name',
    'user-email'   => 'Admin user email',
    'user-password'=> 'Admin user password (min 8 chars)'
];

// Additional optional arguments
// address, city, state, zip, phone, company-email, website
// These are optional and don't need error checks if missing.
$optional_args = [
    'address'       => 'Company address (optional)',
    'city'          => 'Company city (optional)',
    'state'         => 'Company state (optional)',
    'zip'           => 'Company postal code (optional)',
    'phone'         => 'Company phone (optional)',
    'company-email' => 'Company email (optional)',
    'website'       => 'Company website (optional)'
];

// Parse command line options
$shortopts = "";
$longopts = [
    "help",
    "host:",
    "username:",
    "password:",
    "database:",
    "base-url:",
    "locale:",
    "timezone:",
    "currency:",
    "company-name:",
    "country:",
    "address::",
    "city::",
    "state::",
    "zip::",
    "phone::",
    "company-email::",
    "website::",
    "user-name:",
    "user-email:",
    "user-password:",
    "non-interactive"
];

$options = getopt($shortopts, $longopts);

// If --help is set, print usage and exit
if (isset($options['help'])) {
    echo "ITFlow CLI Setup Script\n\n";
    echo "Usage:\n";
    echo "  php setup_cli.php [options]\n\n";
    echo "Options:\n";
    foreach ($required_args as $arg => $desc) {
        echo "  --$arg\t$desc (required)\n";
    }
    foreach ($optional_args as $arg => $desc) {
        echo "  --$arg\t$desc\n";
    }
    echo "  --non-interactive\tRun in non-interactive mode (fail if required args missing)\n";
    echo "  --help\t\tShow this help message\n\n";
    echo "If running interactively (without --non-interactive), any missing required arguments will be prompted.\n";
    echo "If running non-interactively, all required arguments must be provided.\n\n";
    exit(0);
}

if (file_exists("../config.php")) {
    include_once "../config.php";
}

require_once "../functions.php";
require_once "../includes/database_version.php";

if (!isset($config_enable_setup)) {
    $config_enable_setup = 1;
}

if ($config_enable_setup == 0) {
    echo "Setup is disabled. Please delete or modify config.php if you need to re-run the setup.\n";
    exit;
}

$errorLog = ini_get('error_log') ?: "/var/log/apache2/error.log";

$timezones = DateTimeZone::listIdentifiers();

function prompt($message) {
    echo $message . ": ";
    return trim(fgets(STDIN));
}

$non_interactive = isset($options['non-interactive']);

function getOptionOrPrompt($key, $promptMessage, $required = false, $default = '', $optionsGlobal = []) {
    global $options, $non_interactive;
    if (isset($options[$key])) {
        return $options[$key];
    } else {
        if ($non_interactive && $required) {
            die("Missing required argument: --$key\n");
        }
        $val = prompt($promptMessage . (strlen($default) ? " [$default]" : ''));
        if (empty($val) && !empty($default)) {
            $val = $default;
        }
        if ($required && empty($val)) {
            die("Error: $promptMessage is required.\n");
        }
        return $val;
    }
}

// Start setup
echo "Welcome to the ITFlow CLI Setup.\n";

// If config exists, abort
if (file_exists('../config.php')) {
    echo "Database is already configured in config.php.\n";
    echo "To re-run the setup, remove config.php and run this script again.\n";
    exit;
}

// If non-interactive is set, ensure all required arguments are present
if ($non_interactive) {
    foreach (array_keys($required_args) as $arg) {
        if (!isset($options[$arg])) {
            die("Missing required argument: --$arg\n");
        }
    }
}

// Database Setup
echo "\n=== Database Setup ===\n";
$database = getOptionOrPrompt('database', "Enter the database name", true);
$host = getOptionOrPrompt('host', "Enter the database host", true, 'localhost');
if (empty($host)) $host = "localhost";
$username = getOptionOrPrompt('username', "Enter the database username", true);
$password = getOptionOrPrompt('password', "Enter the database password", true);

// Base URL
$base_url = getOptionOrPrompt('base-url', "Enter the base URL (e.g. example.com/itflow)", true);
$base_url = rtrim($base_url, '/');

// Locale, Timezone, Currency
echo "\n=== Localization ===\n";
$locale = getOptionOrPrompt('locale', "Enter the locale (e.g. en_US)", true);
$timezone = getOptionOrPrompt('timezone', "Enter the timezone (e.g. UTC or America/New_York)", true);
$currency_code = getOptionOrPrompt('currency', "Enter the currency code (e.g. USD)", true);

// Company Details
echo "\n=== Company Details ===\n";
$company_name = getOptionOrPrompt('company-name', "Company Name", true);
$country = getOptionOrPrompt('country', "Country (e.g. United States)", true);
$address = getOptionOrPrompt('address', "Address (optional)", false);
$city = getOptionOrPrompt('city', "City (optional)", false);
$state = getOptionOrPrompt('state', "State/Province (optional)", false);
$zip = getOptionOrPrompt('zip', "Postal Code (optional)", false);
$phone = getOptionOrPrompt('phone', "Phone (optional)", false);
$phone = preg_replace("/[^0-9]/", '', $phone);
$company_email = getOptionOrPrompt('company-email', "Company Email (optional)", false);
$website = getOptionOrPrompt('website', "Website (optional)", false);

// User Setup
echo "\n=== Create First User ===\n";
$user_name = getOptionOrPrompt('user-name', "Full Name", true);
$user_email = getOptionOrPrompt('user-email', "Email Address", true);
while (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email.\n";
    if ($non_interactive) {
        die("Invalid email address: $user_email\n");
    }
    $user_email = prompt("Email Address");
}
$user_password_plain = getOptionOrPrompt('user-password', "Password (at least 8 chars)", true);
if (strlen($user_password_plain) < 8) {
    if ($non_interactive) {
        die("Password must be at least 8 characters.\n");
    }
    while (strlen($user_password_plain) < 8) {
        echo "Password too short. Try again.\n";
        $user_password_plain = prompt("Password");
    }
}

if (!preg_match('/^[a-zA-Z0-9.\-\/]+$/', $host)) {
    die("Invalid host format.\n");
}

// Test Database
$conn = @mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Database connection failed - " . mysqli_connect_error() . "\n");
}

$installation_id = randomString(32);

$new_config = "<?php\n\n";
$new_config .= "\$dbhost = " . var_export($host, true) . ";\n";
$new_config .= "\$dbusername = " . var_export($username, true) . ";\n";
$new_config .= "\$dbpassword = " . var_export($password, true) . ";\n";
$new_config .= "\$database = " . var_export($database, true) . ";\n";
$new_config .= "\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database) or die('Database Connection Failed');\n";
$new_config .= "\$config_app_name = 'ITFlow';\n";
$new_config .= "\$config_base_url = '" . addslashes($base_url) . "';\n";
$new_config .= "\$config_https_only = TRUE;\n";
$new_config .= "\$repo_branch = 'master';\n";
$new_config .= "\$installation_id = '$installation_id';\n";

if (file_put_contents("../config.php", $new_config) === false) {
    die("Failed to write config.php. Check file permissions.\n");
}

if (!file_exists('../config.php')) {
    die("config.php does not exist after write attempt.\n");
}

require "../config.php";

// Import DB Schema
echo "Importing database schema...\n";
$filename = '../db.sql';
if (!file_exists($filename)) {
    die("db.sql file not found.\n");
}
$templine = '';
$lines = file($filename);
foreach ($lines as $line) {
    if (substr($line, 0, 2) == '--' || trim($line) == '')
        continue;
    $templine .= $line;
    if (substr(trim($line), -1, 1) == ';') {
        mysqli_query($mysqli, $templine) or die("Error performing query: $templine\n" . mysqli_error($mysqli) . "\n");
        $templine = '';
    }
}
echo "Database imported successfully.\n";

// Create User
$password_hash = password_hash(trim($user_password_plain), PASSWORD_DEFAULT);
$site_encryption_master_key = randomString();
$user_specific_encryption_ciphertext = setupFirstUserSpecificKey($user_password_plain, $site_encryption_master_key);

mysqli_query($mysqli,"INSERT INTO users SET user_name = '$user_name', user_email = '$user_email', user_password = '$password_hash', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext', user_role_id = 3");
mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = 1");
echo "User $user_name created successfully.\n";

// Company Details
mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$company_name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$company_email', company_website = '$website', company_locale = '$locale', company_currency = '$currency_code'");

// Insert default settings and categories
$latest_database_version = LATEST_DATABASE_VERSION;
mysqli_query($mysqli,"INSERT INTO settings SET company_id = 1, config_current_database_version = '$latest_database_version', config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_invoice_prefix = 'REC-', config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_default_net_terms = 30, config_ticket_next_number = 1, config_ticket_prefix = 'TCK-'");

// Categories
mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Office Supplies', category_type = 'Expense', category_color = 'blue'");
mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Travel', category_type = 'Expense', category_color = 'red'");
mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Advertising', category_type = 'Expense', category_color = 'green'");
mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Service', category_type = 'Income', category_color = 'blue'");
mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Friend', category_type = 'Referral', category_color = 'blue'");
mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Search Engine', category_type = 'Referral', category_color = 'red'");

// Payment Methods
mysqli_query($mysqli,"INSERT INTO payment_methods SET category_name = 'Cash'");
mysqli_query($mysqli,"INSERT INTO payment_methods SET category_name = 'Check'");
mysqli_query($mysqli,"INSERT INTO payment_methods SET category_name = 'ACH'");
mysqli_query($mysqli,"INSERT INTO payment_methods SET category_name = 'Credit Card'");

// Calendar
mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = 'Default', calendar_color = 'blue'");

// Ticket Statuses
mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'New', ticket_status_color = '#dc3545'");
mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Open', ticket_status_color = '#007bff'");
mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'On Hold', ticket_status_color = '#28a745'");
mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Resolved', ticket_status_color = '#343a40'");
mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Closed', ticket_status_color = '#343a40'");

// Modules
mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_client', module_description = 'General client & contact management'");
mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_support', module_description = 'Access to ticketing, assets and documentation'");
mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_credential', module_description = 'Access to client credentials - usernames, passwords and 2FA codes'");
mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_sales', module_description = 'Access to quotes, invoices and products'");
mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_financial', module_description = 'Access to payments, accounts, expenses and budgets'");
mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_reporting', module_description = 'Access to all reports'");

// Roles
mysqli_query($mysqli, "INSERT INTO user_roles SET role_id = 1, role_name = 'Accountant', role_description = 'Built-in - Limited access to financial-focused modules'");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 1, user_role_permission_level = 1");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 2, user_role_permission_level = 1");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 4, user_role_permission_level = 1");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 5, user_role_permission_level = 2");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 6, user_role_permission_level = 1");

mysqli_query($mysqli, "INSERT INTO user_roles SET role_id = 2, role_name = 'Technician', role_description = 'Built-in - Limited access to technical-focused modules'");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 1, user_role_permission_level = 2");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 2, user_role_permission_level = 2");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 3, user_role_permission_level = 2");
mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 4, user_role_permission_level = 2");

mysqli_query($mysqli, "INSERT INTO user_roles SET role_id = 3, role_name = 'Administrator', role_description = 'Built-in - Full administrative access', role_is_admin = 1");

// Custom Links
mysqli_query($mysqli,"INSERT INTO custom_links SET custom_link_name = 'Docs', custom_link_uri = 'https://docs.itflow.org', custom_link_new_tab = 1, custom_link_icon = 'question-circle'");

// Finalizing
mysqli_query($mysqli,"UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");
mysqli_query($mysqli,"UPDATE settings SET config_timezone = '$timezone' WHERE company_id = 1");
mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', account_currency_code = '$currency_code'");

// Telemetry (optional if interactive)
if (!$non_interactive) {
    echo "\n=== Telemetry ===\n";
    echo "Would you like to share anonymous usage data with the project maintainers? [y/N]: ";
    $share = strtolower(trim(fgets(STDIN)));
    if ($share === 'y') {
        mysqli_query($mysqli,"UPDATE settings SET config_telemetry = 2");

        echo "Any comments to include? Press Enter if none: ";
        $comments = trim(fgets(STDIN));

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name_db = $row['company_name'];
        $website_db = $row['company_website'];
        $city_db = $row['company_city'];
        $state_db = $row['company_state'];
        $country_db = $row['company_country'];
        $currency_db = $row['company_currency'];

        $postdata = http_build_query([
            'installation_id' => "$installation_id",
            'company_name' => "$company_name_db",
            'website' => "$website_db",
            'city' => "$city_db",
            'state' => "$state_db",
            'country' => "$country_db",
            'currency' => "$currency_db",
            'comments' => "$comments",
            'collection_method' => 1
        ]);

        $opts = ['http' =>
            [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            ]
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents('https://telemetry.itflow.org', false, $context);
        echo "Telemetry response: $result\n";
    }
}

// finalize config
$myfile = fopen("../config.php", "a");
$txt = "\$config_enable_setup = 0;\n\n";
fwrite($myfile, $txt);
fclose($myfile);

echo "\nSetup complete!\n";
echo "You can now log in with the user you created at: https://$base_url/login.php\n";

exit(0);
