<?php
/**
 * ITFlow install-time seed data.
 *
 * Shared by the web installer (setup/index.php) and the headless installer
 * (scripts/setup_cli.php). Both used to carry their own copy of this block and
 * the two drifted - the CLI path was missing most of the expense, income and
 * referral categories and disagreed on several names and colours. Keep new
 * default rows in here so a headless install and a browser install produce the
 * same database.
 *
 * Per-install values stay in the installers: the first user, the company row,
 * and the default Cash account (which needs the chosen currency).
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

/**
 * Insert every row that is identical for every new install.
 *
 * Runs once, against an empty schema, before any user-entered data exists.
 */
function seedDefaultData($mysqli)
{
    $latest_database_version = LATEST_DATABASE_VERSION;
    mysqli_query($mysqli,"INSERT INTO settings SET company_id = 1, config_current_database_version = '$latest_database_version', config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_invoice_prefix = 'REC-', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_default_net_terms = 30, config_ticket_next_number = 1, config_ticket_prefix = 'TCK-'");

    // Create Categories
    // Expense Categories Examples
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Office Supplies', category_type = 'Expense', category_color = 'blue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Travel', category_type = 'Expense', category_color = 'purple'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Advertising', category_type = 'Expense', category_color = 'orange'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Processing Fee', category_type = 'Expense', category_color = 'gray'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Shipping and Postage', category_type = 'Expense', category_color = 'teal'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Software', category_type = 'Expense', category_color = 'lightblue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Bank Fees', category_type = 'Expense', category_color = 'yellow'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Payroll', category_type = 'Expense', category_color = 'green'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Professional Services', category_type = 'Expense', category_color = 'darkblue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Contractor', category_type = 'Expense', category_color = 'brown'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Insurance', category_type = 'Expense', category_color = 'red'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Infrastructure', category_type = 'Expense', category_color = 'darkgreen'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Equipment', category_type = 'Expense', category_color = 'gray'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Education', category_type = 'Expense', category_color = 'lightyellow'");

    // Income Categories Examples
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Managed Services', category_type = 'Income', category_color = 'green'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Consulting', category_type = 'Income', category_color = 'blue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Projects', category_type = 'Income', category_color = 'purple'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Hardware Sales', category_type = 'Income', category_color = 'silver'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Software Sales', category_type = 'Income', category_color = 'lightblue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Cloud Services', category_type = 'Income', category_color = 'skyblue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Support', category_type = 'Income', category_color = 'yellow'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Training', category_type = 'Income', category_color = 'lightyellow'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Telecom Services', category_type = 'Income', category_color = 'orange'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Backup', category_type = 'Income', category_color = 'darkblue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Security', category_type = 'Income', category_color = 'red'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Licensing', category_type = 'Income', category_color = 'green'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Monitoring', category_type = 'Income', category_color = 'teal'");

    // Referral Examples
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Friend', category_type = 'Referral', category_color = 'blue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Search', category_type = 'Referral', category_color = 'orange'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Social Media', category_type = 'Referral', category_color = 'green'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Email', category_type = 'Referral', category_color = 'yellow'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Partner', category_type = 'Referral', category_color = 'purple'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Event', category_type = 'Referral', category_color = 'red'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Affiliate', category_type = 'Referral', category_color = 'pink'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Client', category_type = 'Referral', category_color = 'lightblue'");

    // Payment Methods
    mysqli_query($mysqli,"INSERT INTO payment_methods SET payment_method_name = 'Cash'");
    mysqli_query($mysqli,"INSERT INTO payment_methods SET payment_method_name = 'Check'");
    mysqli_query($mysqli,"INSERT INTO payment_methods SET payment_method_name = 'Bank Transfer'");
    mysqli_query($mysqli,"INSERT INTO payment_methods SET payment_method_name = 'Credit Card'");

    // Default Calendar
    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = 'Default', calendar_color = 'blue'");

    // Add default ticket statuses
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'New', ticket_status_color = '#dc3545'"); // Default ID for new tickets is 1
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Open', ticket_status_color = '#007bff'"); // 2
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'On Hold', ticket_status_color = '#28a745'"); // 3
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Resolved', ticket_status_color = '#343a40'"); // 4 (was auto-close)
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Closed', ticket_status_color = '#343a40'"); // 5

    // Add default modules
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_client', module_description = 'General client & contact management'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_support', module_description = 'Access to ticketing, assets and documentation'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_credential', module_description = 'Access to client credentials - usernames, passwords and 2FA codes'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_sales', module_description = 'Access to quotes, invoices and products'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_financial', module_description = 'Access to payments, accounts, expenses and budgets'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_reporting', module_description = 'Access to all reports'");

    // Add default roles
    mysqli_query($mysqli, "INSERT INTO user_roles SET role_id = 1, role_name = 'Accountant', role_description = 'Built-in - Limited access to financial-focused modules'");
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 1, user_role_permission_level = 1"); // Read clients
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 2, user_role_permission_level = 1"); // Read support
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 4, user_role_permission_level = 1"); // Read sales
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 5, user_role_permission_level = 2"); // Modify financial
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 6, user_role_permission_level = 1"); // Read reports

    mysqli_query($mysqli, "INSERT INTO user_roles SET role_id = 2, role_name = 'Technician', role_description = 'Built-in - Limited access to technical-focused modules'");
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 1, user_role_permission_level = 2"); // Modify clients
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 2, user_role_permission_level = 2"); // Modify support
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 3, user_role_permission_level = 2"); // Modify credentials
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 4, user_role_permission_level = 2"); // Modify sales

    mysqli_query($mysqli, "INSERT INTO user_roles SET role_id = 3, role_name = 'Administrator', role_description = 'Built-in - Full administrative access to all modules (including user management)', role_is_admin = 1");

    // Custom Links
    mysqli_query($mysqli,"INSERT INTO custom_links SET custom_link_name = 'Docs', custom_link_uri = 'https://docs.itflow.org', custom_link_new_tab = 1, custom_link_icon = 'question-circle'");

    // network_interfaces
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Ethernet', category_type = 'network_interface', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'SFP', category_type = 'network_interface', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'SFP+', category_type = 'network_interface', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'QSFP28', category_type = 'network_interface', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'QSFP-DD', category_type = 'network_interface', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Coaxial', category_type = 'network_interface', category_order = 6"); // 6
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Fiber', category_type = 'network_interface', category_order = 7"); // 7
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'WiFi', category_type = 'network_interface', category_order = 8"); // 8

    // Asset statuses
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Ready to Deploy', category_description = 'Asset is configured and ready to be assigned', category_type = 'asset_status', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Deployed', category_description = 'Asset is actively in use and assigned to a client or location', category_type = 'asset_status', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Out for Repair', category_description = 'Asset has been sent out for servicing or repair', category_type = 'asset_status', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Lost', category_description = 'Asset location is unknown and cannot be accounted for', category_type = 'asset_status', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Stolen', category_description = 'Asset has been reported stolen', category_type = 'asset_status', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Retired', category_description = 'Asset has been decommissioned and is no longer in service', category_type = 'asset_status', category_order = 6"); // 6

    // Contact note types
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Call', category_description = 'Phone call with a client or contact', category_icon = 'fa-phone-alt', category_type = 'contact_note_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Email', category_description = 'Email correspondence with a client or contact', category_icon = 'fa-envelope', category_type = 'contact_note_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Meeting', category_description = 'Scheduled meeting with a client or contact', category_icon = 'fa-handshake', category_type = 'contact_note_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'In Person', category_description = 'In person visit or on-site interaction', category_icon = 'fa-people-arrows', category_type = 'contact_note_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Note', category_description = 'General note or internal comment', category_icon = 'fa-sticky-note', category_type = 'contact_note_type', category_order = 5"); // 5

    // Asset note types
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Maintenance', category_description = 'Routine or scheduled maintenance performed on the asset', category_icon = 'fa-tools', category_type = 'asset_note_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Repair', category_description = 'Repair work or hardware replacement', category_icon = 'fa-wrench', category_type = 'asset_note_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Configuration', category_description = 'Configuration or settings change made to the asset', category_icon = 'fa-sliders-h', category_type = 'asset_note_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Upgrade', category_description = 'Hardware or software upgrade', category_icon = 'fa-arrow-circle-up', category_type = 'asset_note_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Inspection', category_description = 'Physical inspection or audit of the asset', category_icon = 'fa-clipboard-check', category_type = 'asset_note_type', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Note', category_description = 'General note or internal comment', category_icon = 'fa-sticky-note', category_type = 'asset_note_type', category_order = 6"); // 6

    // Rack Types
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '2-Post Open Frame', category_description = 'Two-post open frame rack for patch panels and lightweight equipment', category_type = 'rack_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '4-Post Open Frame', category_description = 'Four-post open frame rack for servers and heavier equipment', category_type = 'rack_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '4-Post Enclosed Cabinet', category_description = 'Four-post enclosed cabinet with doors and sides for secure equipment housing', category_type = 'rack_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Wall-Mount Open', category_description = 'Open frame rack mounted directly to a wall for small deployments', category_type = 'rack_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Wall-Mount Enclosed', category_description = 'Enclosed cabinet rack mounted to a wall with a locking door', category_type = 'rack_type', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Other', category_description = 'Rack type does not fit any standard category', category_type = 'rack_type', category_order = 6"); // 6

    // Software Types
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Software as a Service (SaaS)', category_description = 'Cloud-hosted software accessed via a web browser or API', category_type = 'software_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Productivity Suite', category_description = 'Bundled office and collaboration tools such as Microsoft 365 or Google Workspace', category_type = 'software_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Web Application', category_description = 'Application hosted on a web server and accessed through a browser', category_type = 'software_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Desktop Application', category_description = 'Application installed and run locally on a workstation or laptop', category_type = 'software_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Mobile Application', category_description = 'Application installed and run on a mobile device or tablet', category_type = 'software_type', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Security Software', category_description = 'Software providing antivirus, endpoint protection, or security monitoring', category_type = 'software_type', category_order = 6"); // 6
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'System Software', category_description = 'Low-level software managing hardware resources and system operations', category_type = 'software_type', category_order = 7"); // 7
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Operating System', category_description = 'Core software managing hardware and providing a platform for applications', category_type = 'software_type', category_order = 8"); // 8
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Other', category_description = 'Software type does not fit any standard category', category_type = 'software_type', category_order = 9"); // 9
}
