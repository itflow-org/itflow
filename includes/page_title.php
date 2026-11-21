<?php

/*
 * The page's name for the browser tab (includes/footer.php puts it after the
 * company or client) and for the empty state's "No ... match". A report takes
 * its title from reportCatalog(), a settings page from settingsPages(), and a
 * few pages whose file name isn't what the menu calls them are named here;
 * the rest come from the file name, in Title Case with their acronyms. A
 * detail page sets $page_title to the record's name after this. Not escaped:
 * the footer writes it through json_encode() and emptyStateHtml() escapes it.
 */

$page_file = basename($_SERVER['PHP_SELF'], '.php');
$page_dir = basename(dirname($_SERVER['PHP_SELF']));

$page_names = [
    'kb' => 'Knowledge Base',
    'software' => 'Licenses',
    'shares' => 'Shared Links',
    'sla' => 'SLAs',
    'tax_rates' => 'Taxes',
    'getting_started' => 'Get Started',
    'global_search' => 'Search',
    'products_reprice' => 'Update Prices',
    'products_count' => 'Count Stock',
    'client_overview' => 'Overview',
    'saved_payment_methods' => 'Saved Cards',
    'user_details' => 'Profile',
    'user_security' => 'Security',
    'user_preferences' => 'Preferences',
    'user_activity' => 'Activity',
    'user_canned_responses' => 'Canned Responses',
];

$page_title = '';
if ($page_dir === 'reports') {
    if ($page_file === 'index') {
        $page_title = 'Reports';
    } else {
        foreach (reportCatalog() as $report_group) {
            if (isset($report_group['reports'][$page_file])) {
                $page_title = $report_group['reports'][$page_file]['title'];
            }
        }
    }
} elseif ($page_dir === 'admin' && strpos($page_file, 'settings_') === 0) {
    foreach (settingsPages() as $settings_page) {
        if (($settings_page['url'] ?? '') === "$page_file.php") {
            $page_title = $settings_page['title'];
        }
    }
}
if ($page_title === '') {
    $page_title = $page_names[$page_file] ?? ucwords(str_replace('_', ' ', $page_file));
    // Acronyms in capitals, and the small words in lower case after the first
    $page_title = preg_replace_callback('/\b(Ai|Api|Rmm|Sla|Sms|Ip|Mfa|Pdf|Csv|Id)\b/', function ($m) { return strtoupper($m[1]); }, $page_title);
    $page_title = preg_replace_callback('/(?<=\s)(By|And|Of|To|For|In|On|Vs)\b(?=\s)/', function ($m) { return strtolower($m[1]); }, $page_title);
}

// Sanitize title for SQL input such as logging
$page_title_sanitized = escapeSql($page_title);

$tab_title = $session_company_name;
