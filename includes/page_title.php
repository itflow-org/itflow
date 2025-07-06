<?php

// Set Page Title 

// Get the current page name without the .php extension
$page_title = basename($_SERVER['PHP_SELF'], '.php');

// Remove 'client_' from the page name
$page_title = str_replace('client_', '', $page_title);

// Remove 'report_' from the page name
$page_title = str_replace('report_', '', $page_title);

// Remove 'admin_' from the page name
$page_title = str_replace('admin_', '', $page_title);

// Remove 'admin_' from the page name
$page_title = str_replace('settings_', '', $page_title);

// Replace any underscores with spaces
$page_title = str_replace('_', ' ', $page_title);

// Capitize
$page_title = ucwords($page_title);

// Sanitize title for SQL input such as logging
$page_title_sanitized = sanitizeInput($page_title);

// Sanitize the page title to prevent XSS for output
$page_title = nullable_htmlentities($page_title);

$tab_title = $session_company_name;
