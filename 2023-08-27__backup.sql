

CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(200) NOT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `account_currency_code` varchar(200) NOT NULL,
  `account_notes` text DEFAULT NULL,
  `account_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `account_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `account_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO accounts VALUES("1","COMPANY ACCOUNT","0.00","ZMW","This is the Company Bank Account","2023-08-26 12:34:15","2023-08-26 16:47:17","");
INSERT INTO accounts VALUES("2","Chanda Chewe","0.00","ZMW","This is the personal Account","2023-08-26 16:47:46","","");



CREATE TABLE `api_keys` (
  `api_key_id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key_name` varchar(255) NOT NULL,
  `api_key_secret` varchar(255) NOT NULL,
  `api_key_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `api_key_expire` date NOT NULL,
  `api_key_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`api_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `asset_custom` (
  `asset_custom_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_custom_field_value` int(11) NOT NULL,
  `asset_custom_field_id` int(11) NOT NULL,
  `asset_custom_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_custom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `asset_documents` (
  `asset_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `asset_files` (
  `asset_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `asset_logins` (
  `asset_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_type` varchar(200) NOT NULL,
  `asset_name` varchar(200) NOT NULL,
  `asset_description` varchar(255) DEFAULT NULL,
  `asset_make` varchar(200) NOT NULL,
  `asset_model` varchar(200) DEFAULT NULL,
  `asset_serial` varchar(200) DEFAULT NULL,
  `asset_os` varchar(200) DEFAULT NULL,
  `asset_ip` varchar(20) DEFAULT NULL,
  `asset_mac` varchar(17) DEFAULT NULL,
  `asset_status` varchar(200) DEFAULT NULL,
  `asset_purchase_date` date DEFAULT NULL,
  `asset_warranty_expire` date DEFAULT NULL,
  `asset_install_date` date DEFAULT NULL,
  `asset_notes` text DEFAULT NULL,
  `asset_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `asset_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `asset_archived_at` datetime DEFAULT NULL,
  `asset_accessed_at` datetime DEFAULT NULL,
  `asset_login_id` int(11) NOT NULL DEFAULT 0,
  `asset_vendor_id` int(11) NOT NULL DEFAULT 0,
  `asset_location_id` int(11) NOT NULL DEFAULT 0,
  `asset_contact_id` int(11) NOT NULL DEFAULT 0,
  `asset_network_id` int(11) NOT NULL DEFAULT 0,
  `asset_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`asset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO assets VALUES("1","Laptop","Laptop Core I5","","","","","","","","","","","","","2023-08-26 15:48:48","","","","0","0","0","0","0","1");



CREATE TABLE `budget` (
  `budget_id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_month` tinyint(4) NOT NULL,
  `budget_year` int(11) NOT NULL,
  `budget_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `budget_description` varchar(255) DEFAULT NULL,
  `budget_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `budget_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `budget_category_id` int(11) NOT NULL,
  PRIMARY KEY (`budget_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO budget VALUES("1","8","2023","1000.00","This Money will be sent on advertising only","2023-08-26 16:49:35","2023-08-26 16:54:58","3");



CREATE TABLE `calendars` (
  `calendar_id` int(11) NOT NULL AUTO_INCREMENT,
  `calendar_name` varchar(200) NOT NULL,
  `calendar_color` varchar(200) NOT NULL,
  `calendar_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `calendar_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `calendar_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`calendar_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO calendars VALUES("1","Default","blue","2023-08-26 12:34:16","","");



CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(200) NOT NULL,
  `category_type` varchar(200) NOT NULL,
  `category_color` varchar(200) DEFAULT NULL,
  `category_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `category_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `category_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO categories VALUES("1","Office Supplies","Expense","blue","2023-08-26 12:34:15","","");
INSERT INTO categories VALUES("2","Travel","Expense","red","2023-08-26 12:34:15","","");
INSERT INTO categories VALUES("3","Advertising","Expense","green","2023-08-26 12:34:15","","");
INSERT INTO categories VALUES("4","Service","Income","blue","2023-08-26 12:34:15","","");
INSERT INTO categories VALUES("5","Friend","Referral","blue","2023-08-26 12:34:15","","");
INSERT INTO categories VALUES("6","Search Engine","Referral","red","2023-08-26 12:34:15","","");
INSERT INTO categories VALUES("7","Cash","Payment Method","blue","2023-08-26 12:34:16","","");
INSERT INTO categories VALUES("8","Check","Payment Method","red","2023-08-26 12:34:16","","");
INSERT INTO categories VALUES("9","BULK SMS","Income","000000","2023-08-26 16:36:32","","");
INSERT INTO categories VALUES("10","Grant","Income","000000","2023-08-26 16:58:12","","");



CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL AUTO_INCREMENT,
  `certificate_name` varchar(200) NOT NULL,
  `certificate_domain` varchar(200) DEFAULT NULL,
  `certificate_issued_by` varchar(200) NOT NULL,
  `certificate_expire` date DEFAULT NULL,
  `certificate_public_key` text DEFAULT NULL,
  `certificate_notes` text DEFAULT NULL,
  `certificate_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `certificate_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `certificate_archived_at` datetime DEFAULT NULL,
  `certificate_accessed_at` datetime DEFAULT NULL,
  `certificate_domain_id` int(11) NOT NULL DEFAULT 0,
  `certificate_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`certificate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `client_tags` (
  `client_tag_client_id` int(11) NOT NULL,
  `client_tag_tag_id` int(11) NOT NULL,
  PRIMARY KEY (`client_tag_client_id`,`client_tag_tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(200) NOT NULL,
  `client_type` varchar(200) DEFAULT NULL,
  `client_website` varchar(200) DEFAULT NULL,
  `client_referral` varchar(200) DEFAULT NULL,
  `client_rate` decimal(15,2) DEFAULT NULL,
  `client_currency_code` varchar(200) NOT NULL,
  `client_net_terms` int(10) NOT NULL,
  `client_tax_id_number` varchar(255) DEFAULT NULL,
  `client_notes` text DEFAULT NULL,
  `client_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `client_archived_at` datetime DEFAULT NULL,
  `client_accessed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO clients VALUES("1","MACROIT","","","","0.00","ZMW","30","","","2023-08-26 13:26:47","2023-08-26 20:55:56","","2023-08-26 20:55:56");



CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(200) NOT NULL,
  `company_address` varchar(200) DEFAULT NULL,
  `company_city` varchar(200) DEFAULT NULL,
  `company_state` varchar(200) DEFAULT NULL,
  `company_zip` varchar(200) DEFAULT NULL,
  `company_country` varchar(200) DEFAULT NULL,
  `company_phone` varchar(200) DEFAULT NULL,
  `company_email` varchar(200) DEFAULT NULL,
  `company_website` varchar(200) DEFAULT NULL,
  `company_logo` varchar(250) DEFAULT NULL,
  `company_locale` varchar(200) DEFAULT NULL,
  `company_currency` varchar(200) NOT NULL,
  `company_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO companies VALUES("1","MACROIT INFORMATION TECHNOLOGY","Northmead, Lusaka, Zambia","Lusaka","Lusaka","10101","Zambia","0973750029","info@macro-it.net","https://macro-it.net","","en_US","ZMW","2023-08-26 12:34:15","");



CREATE TABLE `contact_assets` (
  `contact_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `contact_documents` (
  `contact_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `contact_files` (
  `contact_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `contact_logins` (
  `contact_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `contacts` (
  `contact_id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_name` varchar(200) NOT NULL,
  `contact_title` varchar(200) DEFAULT NULL,
  `contact_email` varchar(200) DEFAULT NULL,
  `contact_phone` varchar(200) DEFAULT NULL,
  `contact_extension` varchar(200) DEFAULT NULL,
  `contact_mobile` varchar(200) DEFAULT NULL,
  `contact_photo` varchar(200) DEFAULT NULL,
  `contact_pin` varchar(255) DEFAULT NULL,
  `contact_notes` text DEFAULT NULL,
  `contact_auth_method` varchar(200) DEFAULT NULL,
  `contact_password_hash` varchar(200) DEFAULT NULL,
  `contact_password_reset_token` varchar(200) DEFAULT NULL,
  `contact_token_expire` datetime DEFAULT NULL,
  `contact_primary` tinyint(1) NOT NULL DEFAULT 0,
  `contact_important` tinyint(1) NOT NULL DEFAULT 0,
  `contact_billing` tinyint(1) DEFAULT 0,
  `contact_technical` tinyint(1) DEFAULT 0,
  `contact_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `contact_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `contact_archived_at` datetime DEFAULT NULL,
  `contact_accessed_at` datetime DEFAULT NULL,
  `contact_location_id` int(11) NOT NULL DEFAULT 0,
  `contact_department` varchar(200) DEFAULT NULL,
  `contact_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`contact_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO contacts VALUES("1","Chanda Chewe","CFO","chewe@gmail.com","0973750029","","0973750029","","test1234","","local","$2y$10$r9ZnwFOHw0/.8dLqx8N63uTb6ImqmrYUvTqzXIx7D1frRy0c0blpS","","","1","1","0","0","2023-08-26 13:26:47","2023-08-26 20:51:41","","","0","Accounts","1");



CREATE TABLE `custom_fields` (
  `custom_field_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_field_table` varchar(255) NOT NULL,
  `custom_field_label` varchar(255) NOT NULL,
  `custom_field_type` varchar(255) NOT NULL DEFAULT 'text',
  `custom_field_location` int(11) NOT NULL DEFAULT 0,
  `custom_field_order` int(11) NOT NULL DEFAULT 999,
  PRIMARY KEY (`custom_field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `custom_values` (
  `custom_value_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_value_value` text NOT NULL,
  `custom_value_field` int(11) NOT NULL,
  PRIMARY KEY (`custom_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_name` varchar(200) NOT NULL,
  `document_content` longtext NOT NULL,
  `document_content_raw` longtext NOT NULL,
  `document_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `document_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `document_archived_at` datetime DEFAULT NULL,
  `document_accessed_at` datetime DEFAULT NULL,
  `document_template` tinyint(1) NOT NULL DEFAULT 0,
  `document_folder_id` int(11) NOT NULL DEFAULT 0,
  `document_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`document_id`),
  FULLTEXT KEY `document_content_raw` (`document_content_raw`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `domains` (
  `domain_id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(200) NOT NULL,
  `domain_expire` date DEFAULT NULL,
  `domain_ip` varchar(255) DEFAULT NULL,
  `domain_name_servers` varchar(255) DEFAULT NULL,
  `domain_mail_servers` varchar(255) DEFAULT NULL,
  `domain_txt` text DEFAULT NULL,
  `domain_raw_whois` text DEFAULT NULL,
  `domain_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `domain_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `domain_archived_at` datetime DEFAULT NULL,
  `domain_accessed_at` datetime DEFAULT NULL,
  `domain_registrar` int(11) NOT NULL DEFAULT 0,
  `domain_webhost` int(11) NOT NULL DEFAULT 0,
  `domain_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `email_queue` (
  `email_id` int(11) NOT NULL AUTO_INCREMENT,
  `email_status` tinyint(1) NOT NULL DEFAULT 0,
  `email_recipient` varchar(255) NOT NULL,
  `email_recipient_name` varchar(255) DEFAULT NULL,
  `email_from` varchar(255) NOT NULL,
  `email_from_name` varchar(255) NOT NULL,
  `email_subject` varchar(255) NOT NULL,
  `email_content` longtext NOT NULL,
  `email_queued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `email_failed_at` datetime DEFAULT NULL,
  `email_attempts` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(200) NOT NULL,
  `event_description` longtext DEFAULT NULL,
  `event_start` datetime NOT NULL,
  `event_end` datetime DEFAULT NULL,
  `event_repeat` varchar(200) DEFAULT NULL,
  `event_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `event_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `event_archived_at` datetime DEFAULT NULL,
  `event_client_id` int(11) NOT NULL DEFAULT 0,
  `event_location_id` int(11) NOT NULL DEFAULT 0,
  `event_calendar_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_description` text DEFAULT NULL,
  `expense_amount` decimal(15,2) NOT NULL,
  `expense_currency_code` varchar(200) NOT NULL,
  `expense_date` date NOT NULL,
  `expense_reference` varchar(200) DEFAULT NULL,
  `expense_payment_method` varchar(200) DEFAULT NULL,
  `expense_receipt` varchar(200) DEFAULT NULL,
  `expense_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expense_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `expense_archived_at` datetime DEFAULT NULL,
  `expense_vendor_id` int(11) NOT NULL DEFAULT 0,
  `expense_client_id` int(11) NOT NULL DEFAULT 0,
  `expense_category_id` int(11) NOT NULL DEFAULT 0,
  `expense_account_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`expense_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO expenses VALUES("1","Payments for rentals","5500.00","ZMW","2023-08-26","003993903","","","2023-08-26 16:44:58","","","2","0","1","1");
INSERT INTO expenses VALUES("2","","5000.00","ZMW","2023-08-26","","","","2023-08-26 16:48:28","","","0","0","0","1");



CREATE TABLE `files` (
  `file_id` int(11) NOT NULL AUTO_INCREMENT,
  `file_reference_name` varchar(200) DEFAULT NULL,
  `file_name` varchar(200) NOT NULL,
  `file_ext` varchar(200) DEFAULT NULL,
  `file_hash` varchar(200) DEFAULT NULL,
  `file_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `file_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `file_archived_at` datetime DEFAULT NULL,
  `file_accessed_at` datetime DEFAULT NULL,
  `file_folder_id` int(11) NOT NULL DEFAULT 0,
  `file_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `folders` (
  `folder_id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_name` varchar(200) NOT NULL,
  `parent_folder` int(11) NOT NULL DEFAULT 0,
  `folder_location` int(11) DEFAULT 0,
  `folder_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `history_status` varchar(200) NOT NULL,
  `history_description` varchar(200) NOT NULL,
  `history_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `history_invoice_id` int(11) NOT NULL DEFAULT 0,
  `history_recurring_id` int(11) NOT NULL DEFAULT 0,
  `history_quote_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO history VALUES("1","Draft","INVOICE added!","2023-08-26 16:33:30","1","0","0");
INSERT INTO history VALUES("2","Active","Recurring Invoice created!","2023-08-26 16:36:50","0","1","0");
INSERT INTO history VALUES("3","Draft","Quote created!","2023-08-26 16:38:42","0","0","1");
INSERT INTO history VALUES("4","Sent","QUOTE marked sent","2023-08-26 16:39:29","0","0","1");
INSERT INTO history VALUES("5","Accepted","Quote accepted!","2023-08-26 16:39:33","0","0","1");
INSERT INTO history VALUES("6","Draft","Quote copied to Invoice!","2023-08-26 16:39:41","2","0","0");
INSERT INTO history VALUES("7","Sent","INVOICE marked sent","2023-08-26 16:39:48","2","0","0");
INSERT INTO history VALUES("8","Paid","Payment added","2023-08-26 16:40:08","2","0","0");
INSERT INTO history VALUES("9","Sent","INVOICE marked sent","2023-08-26 16:40:36","1","0","0");
INSERT INTO history VALUES("10","Paid","Payment added","2023-08-26 16:40:54","1","0","0");
INSERT INTO history VALUES("11","1","Recurring modified","2023-08-26 17:02:39","0","1","0");
INSERT INTO history VALUES("12","1","Recurring modified","2023-08-26 17:03:40","0","1","0");
INSERT INTO history VALUES("13","Sent","Invoice Generated from Recurring!","2023-08-26 17:09:17","3","0","0");
INSERT INTO history VALUES("14","Sent","Invoice Generated from Recurring!","2023-08-26 20:07:02","4","0","0");
INSERT INTO history VALUES("15","0","Recurring modified","2023-08-26 20:11:19","0","1","0");
INSERT INTO history VALUES("16","Paid","Payment added","2023-08-26 20:17:18","4","0","0");



CREATE TABLE `interfaces` (
  `interface_id` int(11) NOT NULL AUTO_INCREMENT,
  `interface_number` int(11) DEFAULT NULL,
  `interface_description` varchar(200) DEFAULT NULL,
  `interface_connected_asset` varchar(200) DEFAULT NULL,
  `interface_ip` varchar(200) DEFAULT NULL,
  `interface_created_at` datetime DEFAULT current_timestamp(),
  `interface_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `interface_archived_at` datetime DEFAULT NULL,
  `interface_connected_asset_id` int(11) NOT NULL DEFAULT 0,
  `interface_network_id` int(11) NOT NULL DEFAULT 0,
  `interface_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`interface_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `invoice_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(200) NOT NULL,
  `item_description` text DEFAULT NULL,
  `item_quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
  `item_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `item_subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `item_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `item_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `item_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `item_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `item_archived_at` datetime DEFAULT NULL,
  `item_tax_id` int(11) NOT NULL DEFAULT 0,
  `item_quote_id` int(11) NOT NULL DEFAULT 0,
  `item_recurring_id` int(11) NOT NULL DEFAULT 0,
  `item_invoice_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO invoice_items VALUES("1","Laptop","Laptop Core I5","1.00","7500.00","7500.00","0.00","7500.00","2023-08-26 16:34:48","","","0","0","0","1");
INSERT INTO invoice_items VALUES("2","BULK SMS","Bulk sms payments","6.00","300.00","1800.00","0.00","1800.00","2023-08-26 16:37:26","","","0","0","1","0");
INSERT INTO invoice_items VALUES("3","Web Hosting","Wen hosting","1.00","2500.00","2500.00","0.00","2500.00","2023-08-26 16:39:13","","","0","1","0","0");
INSERT INTO invoice_items VALUES("4","Web Hosting","Wen hosting","1.00","2500.00","2500.00","0.00","2500.00","2023-08-26 16:39:41","","","0","0","0","2");
INSERT INTO invoice_items VALUES("5","BULK SMS","Bulk sms payments","6.00","300.00","1800.00","0.00","1800.00","2023-08-26 17:09:17","","","0","0","0","3");
INSERT INTO invoice_items VALUES("6","BULK SMS","Bulk sms payments","6.00","300.00","1800.00","0.00","1800.00","2023-08-26 20:07:02","","","0","0","0","4");



CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_prefix` varchar(200) DEFAULT NULL,
  `invoice_number` int(11) NOT NULL,
  `invoice_scope` varchar(255) DEFAULT NULL,
  `invoice_status` varchar(200) NOT NULL,
  `invoice_date` date NOT NULL,
  `invoice_due` date NOT NULL,
  `invoice_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `invoice_currency_code` varchar(200) NOT NULL,
  `invoice_note` text DEFAULT NULL,
  `invoice_url_key` varchar(200) DEFAULT NULL,
  `invoice_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `invoice_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `invoice_archived_at` datetime DEFAULT NULL,
  `invoice_category_id` int(11) NOT NULL,
  `invoice_client_id` int(11) NOT NULL,
  PRIMARY KEY (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO invoices VALUES("1","INV-","1","LAPTOP","Paid","2023-08-26","2023-09-25","7500.00","ZMW","","6I8G0TE4u8iiqsDAOCLOAXxCOtRLHNpH1wj4WvUehCXDG8kRZg7n6aB2IYoiqmLd9aqiyQJ8hcz7eahXrGklxyVWa8SoxZpPnGSzOepQv8a5yQnLU4IuLg8yOixVWlcJKIjPuplLxgrZWeWTu8qbKAUPupcm","2023-08-26 16:33:30","2023-08-26 16:40:54","","4","1");
INSERT INTO invoices VALUES("2","INV-","2","QUOTE ON WEBHOSTING","Paid","2023-08-26","2023-09-25","2500.00","ZMW","","86rgyvBlAmDU0vk69KUCfEKl45dkoqTJNyme4KicuT9HlR6BN4twkWnOREaGPL4LOpX3z86DvSC4mUQUo5xmo6k38AvZAgAXbrq6D5DfpdnDTKt6k2h1TSVO3hPz6ehGmmumVBsyx6tnLh9YJsYAs6Zh1QNY","2023-08-26 16:39:41","2023-08-26 16:40:07","","4","1");
INSERT INTO invoices VALUES("3","INV-","3","BULK SMS","Sent","2023-08-26","2023-09-25","1800.00","ZMW","","OKApWin3ohWPVdGWbkVG8P8pxsk0ZGr0g8IwHcCp534JFZzI2Dr6aF0pwm2C51xD5aiWMPDXFcBOgp0KIGKaNma46fnxP9lsWjmPHf3mhokIz0Y2pGcaMoqNmti0cbnMw0nUaoPekMiZj9KoLBpCqOm8pNQt","2023-08-26 17:09:17","","","9","1");
INSERT INTO invoices VALUES("4","INV-","4","BULK SMS","Paid","2023-08-26","2023-09-25","1800.00","ZMW","","oD9oGFDxqJ8qYjw8PW5pnTtnv28udUvanNmHu4RUJMFusXmt881wpu4Qf1PxdIdaAU1PEvCmxYZZzVdLm1hOH9fKzrBbF4CepeyoWxs52k6jWK4Vy8RIIzcXFG5A0It08jiJm6148RdALcGo7gmWKFMhs0E1","2023-08-26 20:07:02","2023-08-26 20:17:17","","9","1");



CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL AUTO_INCREMENT,
  `location_name` varchar(200) NOT NULL,
  `location_country` varchar(200) DEFAULT NULL,
  `location_address` varchar(200) DEFAULT NULL,
  `location_city` varchar(200) DEFAULT NULL,
  `location_state` varchar(200) DEFAULT NULL,
  `location_zip` varchar(200) DEFAULT NULL,
  `location_phone` varchar(200) DEFAULT NULL,
  `location_hours` varchar(200) DEFAULT NULL,
  `location_photo` varchar(200) DEFAULT NULL,
  `location_primary` tinyint(1) NOT NULL DEFAULT 0,
  `location_notes` text DEFAULT NULL,
  `location_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `location_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `location_archived_at` datetime DEFAULT NULL,
  `location_accessed_at` datetime DEFAULT NULL,
  `location_contact_id` int(11) NOT NULL DEFAULT 0,
  `location_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `logins` (
  `login_id` int(11) NOT NULL AUTO_INCREMENT,
  `login_name` varchar(200) NOT NULL,
  `login_description` varchar(255) DEFAULT NULL,
  `login_category` varchar(200) DEFAULT NULL,
  `login_uri` varchar(200) DEFAULT NULL,
  `login_username` varchar(200) DEFAULT NULL,
  `login_password` varbinary(200) DEFAULT NULL,
  `login_otp_secret` varchar(200) DEFAULT NULL,
  `login_note` text DEFAULT NULL,
  `login_important` tinyint(1) NOT NULL DEFAULT 0,
  `login_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `login_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `login_archived_at` datetime DEFAULT NULL,
  `login_accessed_at` datetime DEFAULT NULL,
  `login_contact_id` int(11) NOT NULL DEFAULT 0,
  `login_vendor_id` int(11) NOT NULL DEFAULT 0,
  `login_asset_id` int(11) NOT NULL DEFAULT 0,
  `login_software_id` int(11) NOT NULL DEFAULT 0,
  `login_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`login_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO logins VALUES("1","Laptop Core I5","","","","otjOO3OrajcAPL4Jc8lYuYDWusWUfmlgKbQMKQ==","QtQOvichPQnCgNVv5XH6Wfl32haU3tIMP5TchA==","","","0","2023-08-26 15:48:48","","","","0","0","1","0","1");



CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `log_type` varchar(200) NOT NULL,
  `log_action` varchar(255) NOT NULL,
  `log_description` varchar(255) NOT NULL,
  `log_ip` varchar(200) DEFAULT NULL,
  `log_user_agent` varchar(250) DEFAULT NULL,
  `log_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `log_client_id` int(11) NOT NULL DEFAULT 0,
  `log_user_id` int(11) NOT NULL DEFAULT 0,
  `log_entity_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO logs VALUES("1","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 12:34:23","0","1","0");
INSERT INTO logs VALUES("2","Client","Create","Chanda Chewe created client MACROIT, primary contact 0973750029 added","::1","Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.91 Mobile Safari/537.36","2023-08-26 13:26:47","1","1","1");
INSERT INTO logs VALUES("3","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:04:36","0","1","0");
INSERT INTO logs VALUES("4","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:04:50","0","1","0");
INSERT INTO logs VALUES("5","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:05:15","0","1","0");
INSERT INTO logs VALUES("6","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:07:26","0","1","0");
INSERT INTO logs VALUES("7","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:08:10","0","1","0");
INSERT INTO logs VALUES("8","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:08:19","0","1","0");
INSERT INTO logs VALUES("9","Login","Failed","Failed login attempt using chewec03@gmail.com","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:08:41","0","0","0");
INSERT INTO logs VALUES("10","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:10:45","0","1","0");
INSERT INTO logs VALUES("11","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:10:58","0","1","0");
INSERT INTO logs VALUES("12","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 14:13:28","0","1","0");
INSERT INTO logs VALUES("13","Login","Create","Chanda Chewe created login credentials for asset Laptop Core I5","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 15:48:48","1","1","1");
INSERT INTO logs VALUES("14","Asset","Create","Chanda Chewe created asset Laptop Core I5","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 15:48:48","1","1","1");
INSERT INTO logs VALUES("15","Scheduled Ticket","Create","Chanda Chewe created scheduled ticket for REPAIR IPHONE 13 PRO MAX - Weekly","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 15:54:35","1","1","1");
INSERT INTO logs VALUES("16","Invoice","Create","INV-1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:33:30","0","1","0");
INSERT INTO logs VALUES("17","Category","Create","BULK SMS","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:36:32","0","1","0");
INSERT INTO logs VALUES("18","Recurring","Create","2023-08-26 - 9","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:36:51","0","1","0");
INSERT INTO logs VALUES("19","Quote","Create","1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:38:43","0","1","0");
INSERT INTO logs VALUES("20","Quote","Update","1 marked sent","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:39:29","0","1","0");
INSERT INTO logs VALUES("21","Quote","Modify","Accepted Quote 1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:39:34","0","1","0");
INSERT INTO logs VALUES("22","Quote","Create","Quote copied to Invoice","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:39:42","0","1","0");
INSERT INTO logs VALUES("23","Invoice","Update","2 marked sent","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:39:49","0","1","0");
INSERT INTO logs VALUES("24","Payment","Create","","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:40:08","1","1","1");
INSERT INTO logs VALUES("25","Invoice","Modify","1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:40:22","0","1","0");
INSERT INTO logs VALUES("26","Invoice","Update","1 marked sent","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:40:36","0","1","0");
INSERT INTO logs VALUES("27","Payment","Create","","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:40:54","1","1","2");
INSERT INTO logs VALUES("28","Revenue","Create","2023-08-26 - 15000","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:41:29","0","1","0");
INSERT INTO logs VALUES("29","Product","Create","Chanda Chewe created product SYSTEM DEVOPS","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:42:05","0","1","0");
INSERT INTO logs VALUES("30","Vendor","Create","Chanda Chewe created vendor Zamtel","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:43:25","0","1","0");
INSERT INTO logs VALUES("31","Vendor","Create","Chanda Chewe created vendor Office Rentals","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:44:07","0","1","0");
INSERT INTO logs VALUES("32","Expense","Create","Payments for rentals","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:44:58","0","1","0");
INSERT INTO logs VALUES("33","Trip","Create","Chanda Chewe logged trip to Living Stone","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:46:33","1","1","0");
INSERT INTO logs VALUES("34","Account","Modify","COMPANY ACCOUNT","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:47:17","0","1","0");
INSERT INTO logs VALUES("35","Account","Create","Chanda Chewe","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:47:46","0","1","0");
INSERT INTO logs VALUES("36","Transfer","Create","2023-08-26 - 5000","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:48:28","0","1","0");
INSERT INTO logs VALUES("37","Budget","Create","This Money will be sent on advertising only","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:49:35","0","1","0");
INSERT INTO logs VALUES("38","Budget","Edit","This Money will be sent on advertising only","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:54:58","0","1","0");
INSERT INTO logs VALUES("39","Category","Create","Grant","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:58:12","0","1","0");
INSERT INTO logs VALUES("40","Revenue","Modify","1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 16:58:20","0","1","0");
INSERT INTO logs VALUES("41","Recurring","Modify","1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 17:02:39","0","1","0");
INSERT INTO logs VALUES("42","Recurring","Modify","1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 17:03:40","0","1","0");
INSERT INTO logs VALUES("43","Mail","Error","Failed to send email to  regarding Invoice INV-3. Mailer Error: Invalid address:  (From): ...","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 17:09:17","0","1","0");
INSERT INTO logs VALUES("44","Invoice","Create","Chanda Chewe forced recurring invoice into an invoice","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 17:09:17","1","1","3");
INSERT INTO logs VALUES("45","Mail","Error","Failed to send email to  regarding Invoice INV-4. Mailer Error: Invalid address:  (From): ...","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:07:03","0","1","0");
INSERT INTO logs VALUES("46","Invoice","Create","Chanda Chewe forced recurring invoice into an invoice","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:07:03","1","1","4");
INSERT INTO logs VALUES("47","Recurring","Modify","1","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:11:20","0","1","0");
INSERT INTO logs VALUES("48","Payment","Create","","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:17:18","1","1","3");
INSERT INTO logs VALUES("49","Client","Modify","Chanda Chewe modified client MACROIT","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:41:43","1","1","1");
INSERT INTO logs VALUES("50","Contact","Modify","Chanda Chewe modified contact 0973750029","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:45:37","1","1","1");
INSERT INTO logs VALUES("51","Logout","Success","Chanda Chewe logged out","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:46:42","0","1","0");
INSERT INTO logs VALUES("52","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:46:46","0","1","0");
INSERT INTO logs VALUES("53","Logout","Success","Chanda Chewe logged out","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:46:50","0","1","0");
INSERT INTO logs VALUES("54","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:47:18","0","1","0");
INSERT INTO logs VALUES("55","Contact","Modify","Chanda Chewe modified contact 0973750029","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:49:10","1","1","1");
INSERT INTO logs VALUES("56","Contact","Modify","Chanda Chewe modified contact Chanda Chewe","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:51:21","1","1","1");
INSERT INTO logs VALUES("57","Contact","Modify","Chanda Chewe modified contact Chanda Chewe","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:51:41","1","1","1");
INSERT INTO logs VALUES("58","Logout","Success","Chanda Chewe logged out","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:51:46","0","1","0");
INSERT INTO logs VALUES("59","Client Login","Success","Client contact chewe@gmail.com successfully logged in locally","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:51:57","1","0","0");
INSERT INTO logs VALUES("60","Ticket","Create","Client contact Chanda Chewe created ticket Not Working","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:52:39","1","0","0");
INSERT INTO logs VALUES("61","Login","Success","Chanda Chewe successfully logged in ","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-26 20:53:10","0","1","0");
INSERT INTO logs VALUES("62","Settings","Modify","Chanda Chewe regenerated cron key","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-27 01:12:50","0","1","0");
INSERT INTO logs VALUES("63","Settings","Modify","Chanda Chewe modified online payment settings","::1","Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36","2023-08-27 01:13:29","0","1","0");



CREATE TABLE `networks` (
  `network_id` int(11) NOT NULL AUTO_INCREMENT,
  `network_name` varchar(200) NOT NULL,
  `network_vlan` int(11) DEFAULT NULL,
  `network` varchar(200) NOT NULL,
  `network_gateway` varchar(200) NOT NULL,
  `network_dhcp_range` varchar(200) DEFAULT NULL,
  `network_notes` text DEFAULT NULL,
  `network_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `network_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `network_archived_at` datetime DEFAULT NULL,
  `network_accessed_at` datetime DEFAULT NULL,
  `network_location_id` int(11) NOT NULL DEFAULT 0,
  `network_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`network_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_type` varchar(200) NOT NULL,
  `notification` varchar(255) NOT NULL,
  `notification_timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `notification_dismissed_at` datetime DEFAULT NULL,
  `notification_dismissed_by` int(11) DEFAULT NULL,
  `notification_client_id` int(11) NOT NULL DEFAULT 0,
  `notification_user_id` int(11) NOT NULL DEFAULT 0,
  `notification_entity_id` int(11) DEFAULT 0,
  PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO notifications VALUES("1","Mail","Failed to send email to ","2023-08-26 17:09:17","","","1","0","0");
INSERT INTO notifications VALUES("2","Mail","Failed to send email to ","2023-08-26 20:07:03","","","1","0","0");



CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `payment_currency_code` varchar(10) NOT NULL,
  `payment_method` varchar(200) DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `payment_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `payment_archived_at` datetime DEFAULT NULL,
  `payment_account_id` int(11) NOT NULL,
  `payment_invoice_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO payments VALUES("1","2023-08-26","2500.00","ZWD","Cash","00399399393","2023-08-26 16:40:07","","","1","2");
INSERT INTO payments VALUES("2","2023-08-26","7500.00","ZWD","Cash","003993993","2023-08-26 16:40:53","","","1","1");
INSERT INTO payments VALUES("3","2023-08-26","1800.00","ZWD","Cash","03993993","2023-08-26 20:17:17","","","1","4");



CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(200) NOT NULL,
  `product_description` text DEFAULT NULL,
  `product_price` decimal(15,2) NOT NULL,
  `product_currency_code` varchar(200) NOT NULL,
  `product_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `product_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `product_archived_at` datetime DEFAULT NULL,
  `product_tax_id` int(11) NOT NULL DEFAULT 0,
  `product_category_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO products VALUES("1","SYSTEM DEVOPS","","14000.00","ZMW","2023-08-26 16:42:05","","","0","4");



CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_template` tinyint(1) NOT NULL DEFAULT 0,
  `project_name` varchar(255) NOT NULL,
  `project_description` text DEFAULT NULL,
  `project_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `project_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `project_archived_at` datetime DEFAULT NULL,
  `project_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `quotes` (
  `quote_id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_prefix` varchar(200) DEFAULT NULL,
  `quote_number` int(11) NOT NULL,
  `quote_scope` varchar(255) DEFAULT NULL,
  `quote_status` varchar(200) NOT NULL,
  `quote_date` date NOT NULL,
  `quote_expire` date DEFAULT NULL,
  `quote_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `quote_currency_code` varchar(200) NOT NULL,
  `quote_note` text DEFAULT NULL,
  `quote_url_key` varchar(200) DEFAULT NULL,
  `quote_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `quote_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `quote_archived_at` datetime DEFAULT NULL,
  `quote_category_id` int(11) NOT NULL,
  `quote_client_id` int(11) NOT NULL,
  PRIMARY KEY (`quote_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO quotes VALUES("1","QUO-","1","QUOTE ON WEBHOSTING","Invoiced","2023-08-26","2023-09-25","2500.00","ZMW","","ubDLcdufAWorb4yzuKw6dhgM387k9ElhwDHDjDxpfRbryAWZY26qjgAMTi0I6obS6wdQedbNELgcySDYiztvSm18yTERrxB6TTYowKVVjM6mtihO0aXUJqk81mTq27j0ylRX4JMcX6vMF9c5FjF1aBkqYq6l","2023-08-26 16:38:42","2023-08-26 16:39:41","","4","1");



CREATE TABLE `records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `record_type` varchar(200) NOT NULL,
  `record` varchar(200) NOT NULL,
  `record_value` varchar(200) NOT NULL,
  `record_priority` int(11) DEFAULT NULL,
  `record_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `record_updated_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
  `record_archived_at` datetime DEFAULT NULL,
  `record_domain_id` int(11) NOT NULL,
  PRIMARY KEY (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `recurring` (
  `recurring_id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_prefix` varchar(200) DEFAULT NULL,
  `recurring_number` int(11) NOT NULL,
  `recurring_scope` varchar(255) DEFAULT NULL,
  `recurring_frequency` varchar(200) NOT NULL,
  `recurring_last_sent` date DEFAULT NULL,
  `recurring_next_date` date NOT NULL,
  `recurring_status` int(1) NOT NULL,
  `recurring_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `recurring_currency_code` varchar(200) NOT NULL,
  `recurring_note` text DEFAULT NULL,
  `recurring_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recurring_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `recurring_archived_at` datetime DEFAULT NULL,
  `recurring_category_id` int(11) NOT NULL,
  `recurring_client_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO recurring VALUES("1","REC-","1","BULK SMS","month","2023-08-26","2023-09-26","0","1800.00","ZMW","","2023-08-26 16:36:50","2023-08-26 20:11:19","","9","1");



CREATE TABLE `recurring_expenses` (
  `recurring_expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_expense_frequency` tinyint(1) NOT NULL,
  `recurring_expense_day` tinyint(4) DEFAULT NULL,
  `recurring_expense_month` tinyint(4) DEFAULT NULL,
  `recurring_expense_last_sent` date DEFAULT NULL,
  `recurring_expense_next_date` date NOT NULL,
  `recurring_expense_status` tinyint(1) NOT NULL DEFAULT 1,
  `recurring_expense_description` text DEFAULT NULL,
  `recurring_expense_amount` decimal(15,2) NOT NULL,
  `recurring_expense_payment_method` varchar(200) DEFAULT NULL,
  `recurring_expense_reference` varchar(255) DEFAULT NULL,
  `recurring_expense_currency_code` varchar(200) NOT NULL,
  `recurring_expense_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recurring_expense_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `recurring_expense_archived_at` datetime DEFAULT NULL,
  `recurring_expense_vendor_id` int(11) NOT NULL,
  `recurring_expense_client_id` int(11) NOT NULL DEFAULT 0,
  `recurring_expense_category_id` int(11) NOT NULL,
  `recurring_expense_account_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_expense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `revenues` (
  `revenue_id` int(11) NOT NULL AUTO_INCREMENT,
  `revenue_date` date NOT NULL,
  `revenue_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `revenue_currency_code` varchar(200) NOT NULL,
  `revenue_payment_method` varchar(200) DEFAULT NULL,
  `revenue_reference` varchar(200) DEFAULT NULL,
  `revenue_description` varchar(200) DEFAULT NULL,
  `revenue_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revenue_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `revenue_archived_at` datetime DEFAULT NULL,
  `revenue_category_id` int(11) NOT NULL DEFAULT 0,
  `revenue_account_id` int(11) NOT NULL,
  `revenue_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`revenue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO revenues VALUES("1","2023-08-26","15000.00","ZMW","Check","0029939003","Grant","2023-08-26 16:41:29","2023-08-26 16:58:20","","10","1","0");
INSERT INTO revenues VALUES("2","2023-08-26","5000.00","ZMW","","","","2023-08-26 16:48:28","","","0","2","0");



CREATE TABLE `scheduled_tickets` (
  `scheduled_ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `scheduled_ticket_category` varchar(200) DEFAULT NULL,
  `scheduled_ticket_subject` varchar(200) NOT NULL,
  `scheduled_ticket_details` longtext NOT NULL,
  `scheduled_ticket_priority` varchar(200) DEFAULT NULL,
  `scheduled_ticket_frequency` varchar(10) NOT NULL,
  `scheduled_ticket_start_date` date NOT NULL,
  `scheduled_ticket_next_run` date NOT NULL,
  `scheduled_ticket_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `scheduled_ticket_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `scheduled_ticket_created_by` int(11) NOT NULL DEFAULT 0,
  `scheduled_ticket_client_id` int(11) NOT NULL DEFAULT 0,
  `scheduled_ticket_contact_id` int(11) NOT NULL DEFAULT 0,
  `scheduled_ticket_asset_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`scheduled_ticket_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO scheduled_tickets VALUES("1","","REPAIR IPHONE 13 PRO MAX","","High","Weekly","2023-08-31","2023-08-31","2023-08-26 15:54:35","","1","1","1","1");



CREATE TABLE `service_assets` (
  `service_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `service_certificates` (
  `service_id` int(11) NOT NULL,
  `certificate_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `service_contacts` (
  `service_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `service_documents` (
  `service_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `service_domains` (
  `service_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `service_logins` (
  `service_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `service_vendors` (
  `service_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `service_description` varchar(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `service_category` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `service_importance` varchar(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `service_backup` varchar(200) DEFAULT NULL,
  `service_notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `service_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `service_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `service_accessed_at` datetime DEFAULT NULL,
  `service_review_due` date DEFAULT NULL,
  `service_client_id` int(11) NOT NULL,
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `settings` (
  `company_id` int(11) NOT NULL,
  `config_current_database_version` varchar(10) NOT NULL,
  `config_start_page` varchar(200) DEFAULT 'clients.php',
  `config_smtp_host` varchar(200) DEFAULT NULL,
  `config_smtp_port` int(5) DEFAULT NULL,
  `config_smtp_encryption` varchar(200) DEFAULT NULL,
  `config_smtp_username` varchar(200) DEFAULT NULL,
  `config_smtp_password` varchar(200) DEFAULT NULL,
  `config_mail_from_email` varchar(200) DEFAULT NULL,
  `config_mail_from_name` varchar(200) DEFAULT NULL,
  `config_imap_host` varchar(200) DEFAULT NULL,
  `config_imap_port` int(5) DEFAULT NULL,
  `config_imap_encryption` varchar(200) DEFAULT NULL,
  `config_imap_username` varchar(200) DEFAULT NULL,
  `config_imap_password` varchar(200) DEFAULT NULL,
  `config_default_transfer_from_account` int(11) DEFAULT NULL,
  `config_default_transfer_to_account` int(11) DEFAULT NULL,
  `config_default_payment_account` int(11) DEFAULT NULL,
  `config_default_expense_account` int(11) DEFAULT NULL,
  `config_default_payment_method` varchar(200) DEFAULT NULL,
  `config_default_expense_payment_method` varchar(200) DEFAULT NULL,
  `config_default_calendar` int(11) DEFAULT NULL,
  `config_default_net_terms` int(11) DEFAULT NULL,
  `config_invoice_prefix` varchar(200) DEFAULT NULL,
  `config_invoice_next_number` int(11) DEFAULT NULL,
  `config_invoice_footer` text DEFAULT NULL,
  `config_invoice_from_name` varchar(200) DEFAULT NULL,
  `config_invoice_from_email` varchar(200) DEFAULT NULL,
  `config_invoice_late_fee_enable` tinyint(1) NOT NULL DEFAULT 0,
  `config_invoice_late_fee_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `config_recurring_prefix` varchar(200) DEFAULT NULL,
  `config_recurring_next_number` int(11) NOT NULL,
  `config_quote_prefix` varchar(200) DEFAULT NULL,
  `config_quote_next_number` int(11) DEFAULT NULL,
  `config_quote_footer` text DEFAULT NULL,
  `config_quote_from_name` varchar(200) DEFAULT NULL,
  `config_quote_from_email` varchar(200) DEFAULT NULL,
  `config_ticket_prefix` varchar(200) DEFAULT NULL,
  `config_ticket_next_number` int(11) DEFAULT NULL,
  `config_ticket_from_name` varchar(200) DEFAULT NULL,
  `config_ticket_from_email` varchar(200) DEFAULT NULL,
  `config_ticket_email_parse` tinyint(1) NOT NULL DEFAULT 0,
  `config_ticket_client_general_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `config_ticket_autoclose` tinyint(1) NOT NULL DEFAULT 0,
  `config_ticket_autoclose_hours` int(5) NOT NULL DEFAULT 72,
  `config_enable_cron` tinyint(1) NOT NULL DEFAULT 0,
  `config_cron_key` varchar(255) DEFAULT NULL,
  `config_recurring_auto_send_invoice` tinyint(1) NOT NULL DEFAULT 1,
  `config_enable_alert_domain_expire` tinyint(1) NOT NULL DEFAULT 1,
  `config_send_invoice_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `config_invoice_overdue_reminders` varchar(200) DEFAULT NULL,
  `config_stripe_enable` tinyint(1) NOT NULL DEFAULT 0,
  `config_stripe_publishable` varchar(255) DEFAULT NULL,
  `config_stripe_secret` varchar(255) DEFAULT NULL,
  `config_stripe_account` tinyint(1) NOT NULL DEFAULT 0,
  `config_azure_client_id` varchar(200) DEFAULT NULL,
  `config_azure_client_secret` varchar(200) DEFAULT NULL,
  `config_module_enable_itdoc` tinyint(1) NOT NULL DEFAULT 1,
  `config_module_enable_accounting` tinyint(1) NOT NULL DEFAULT 1,
  `config_client_portal_enable` tinyint(1) NOT NULL DEFAULT 1,
  `config_login_message` text DEFAULT NULL,
  `config_login_key_required` tinyint(1) NOT NULL DEFAULT 0,
  `config_login_key_secret` varchar(255) DEFAULT NULL,
  `config_module_enable_ticketing` tinyint(1) NOT NULL DEFAULT 1,
  `config_theme` varchar(200) DEFAULT 'blue',
  `config_telemetry` tinyint(1) DEFAULT 0,
  `config_timezone` varchar(200) NOT NULL DEFAULT 'America/New_York',
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO settings VALUES("1","0.7.6","clients.php","","","","","","","","","","","","","","","","","","","","30","INV-","5","","","","0","0.00","REC-","2","QUO-","2","","","","TCK-","2","","","0","1","0","72","0","ERbnM6gZUhZkJUD0w3w43sZLPj01tWJE","1","1","1","1,3,7","1","","","0","","","1","1","1","","0","","1","blue","0","America/New_York");



CREATE TABLE `shared_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_active` int(1) NOT NULL,
  `item_key` varchar(255) NOT NULL,
  `item_type` varchar(255) NOT NULL,
  `item_related_id` int(11) NOT NULL,
  `item_encrypted_username` varchar(255) DEFAULT NULL,
  `item_encrypted_credential` varchar(255) DEFAULT NULL,
  `item_note` varchar(255) DEFAULT NULL,
  `item_views` int(11) NOT NULL,
  `item_view_limit` int(11) DEFAULT NULL,
  `item_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `item_expire_at` datetime DEFAULT NULL,
  `item_client_id` int(11) NOT NULL,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `software` (
  `software_id` int(11) NOT NULL AUTO_INCREMENT,
  `software_name` varchar(200) NOT NULL,
  `software_version` varchar(200) DEFAULT NULL,
  `software_type` varchar(200) NOT NULL,
  `software_license_type` varchar(200) DEFAULT NULL,
  `software_key` varchar(200) DEFAULT NULL,
  `software_seats` int(11) DEFAULT NULL,
  `software_purchase` date DEFAULT NULL,
  `software_expire` date DEFAULT NULL,
  `software_notes` text DEFAULT NULL,
  `software_template` tinyint(1) NOT NULL DEFAULT 0,
  `software_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `software_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `software_archived_at` datetime DEFAULT NULL,
  `software_accessed_at` datetime DEFAULT NULL,
  `software_login_id` int(11) NOT NULL DEFAULT 0,
  `software_client_id` int(11) NOT NULL,
  `software_template_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`software_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `software_assets` (
  `software_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `software_contacts` (
  `software_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `software_documents` (
  `software_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `software_files` (
  `software_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `software_logins` (
  `software_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `tags` (
  `tag_id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(200) NOT NULL,
  `tag_type` int(11) NOT NULL,
  `tag_color` varchar(200) DEFAULT NULL,
  `tag_icon` varchar(200) DEFAULT NULL,
  `tag_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `tag_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `tag_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_template` tinyint(1) NOT NULL DEFAULT 0,
  `task_name` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `task_finish_date` date DEFAULT NULL,
  `task_status` varchar(255) DEFAULT NULL,
  `task_completed_at` datetime DEFAULT NULL,
  `task_completed_by` int(11) DEFAULT NULL,
  `task_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `task_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `task_ticket_id` int(11) DEFAULT NULL,
  `task_project_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `taxes` (
  `tax_id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(200) NOT NULL,
  `tax_percent` float NOT NULL,
  `tax_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `tax_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `tax_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tax_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `ticket_attachments` (
  `ticket_attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_attachment_name` varchar(255) NOT NULL,
  `ticket_attachment_reference_name` varchar(255) NOT NULL,
  `ticket_attachment_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_attachment_ticket_id` int(11) NOT NULL,
  `ticket_attachment_reply_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`ticket_attachment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `ticket_replies` (
  `ticket_reply_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_reply` longtext NOT NULL,
  `ticket_reply_type` varchar(10) NOT NULL,
  `ticket_reply_time_worked` time DEFAULT NULL,
  `ticket_reply_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_reply_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ticket_reply_archived_at` datetime DEFAULT NULL,
  `ticket_reply_by` int(11) NOT NULL,
  `ticket_reply_ticket_id` int(11) NOT NULL,
  PRIMARY KEY (`ticket_reply_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `ticket_views` (
  `view_id` int(11) NOT NULL AUTO_INCREMENT,
  `view_ticket_id` int(11) NOT NULL,
  `view_user_id` int(11) NOT NULL,
  `view_timestamp` datetime NOT NULL,
  PRIMARY KEY (`view_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `ticket_watchers` (
  `watcher_id` int(11) NOT NULL AUTO_INCREMENT,
  `watcher_name` varchar(255) DEFAULT NULL,
  `watcher_email` varchar(255) NOT NULL,
  `watcher_ticket_id` int(11) NOT NULL,
  PRIMARY KEY (`watcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_prefix` varchar(200) DEFAULT NULL,
  `ticket_number` int(11) NOT NULL,
  `ticket_source` varchar(255) DEFAULT NULL,
  `ticket_category` varchar(200) DEFAULT NULL,
  `ticket_subject` varchar(200) NOT NULL,
  `ticket_details` longtext NOT NULL,
  `ticket_priority` varchar(200) DEFAULT NULL,
  `ticket_status` varchar(200) NOT NULL,
  `ticket_vendor_ticket_number` varchar(255) DEFAULT NULL,
  `ticket_feedback` varchar(200) DEFAULT NULL,
  `ticket_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ticket_archived_at` datetime DEFAULT NULL,
  `ticket_closed_at` datetime DEFAULT NULL,
  `ticket_created_by` int(11) NOT NULL,
  `ticket_assigned_to` int(11) NOT NULL DEFAULT 0,
  `ticket_closed_by` int(11) NOT NULL DEFAULT 0,
  `ticket_vendor_id` int(11) NOT NULL DEFAULT 0,
  `ticket_client_id` int(11) NOT NULL DEFAULT 0,
  `ticket_contact_id` int(11) NOT NULL DEFAULT 0,
  `ticket_location_id` int(11) NOT NULL DEFAULT 0,
  `ticket_asset_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ticket_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO tickets VALUES("1","TCK-","1","","","Not Working","Something went wrong","High","Open","","","2023-08-26 20:52:39","","","","0","0","0","0","1","1","0","0");



CREATE TABLE `transfers` (
  `transfer_id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_notes` text DEFAULT NULL,
  `transfer_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `transfer_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `transfer_archived_at` datetime DEFAULT NULL,
  `transfer_expense_id` int(11) NOT NULL,
  `transfer_revenue_id` int(11) NOT NULL,
  PRIMARY KEY (`transfer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO transfers VALUES("1","","2023-08-26 16:48:28","","","2","2");



CREATE TABLE `trips` (
  `trip_id` int(11) NOT NULL AUTO_INCREMENT,
  `trip_date` date NOT NULL,
  `trip_purpose` varchar(200) NOT NULL,
  `trip_source` varchar(200) NOT NULL,
  `trip_destination` varchar(200) NOT NULL,
  `trip_start_odometer` int(11) DEFAULT NULL,
  `trip_end_odmeter` int(11) DEFAULT NULL,
  `trip_miles` float(15,1) NOT NULL,
  `round_trip` int(1) NOT NULL,
  `trip_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `trip_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `trip_archived_at` datetime DEFAULT NULL,
  `trip_user_id` int(11) NOT NULL DEFAULT 0,
  `trip_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`trip_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO trips VALUES("1","2023-08-26","Selling our products","Lusaka","Living Stone","","","120.0","0","2023-08-26 16:46:33","","","1","1");



CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `user_role` int(11) NOT NULL,
  `user_config_records_per_page` int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO user_settings VALUES("1","3","10");



CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(200) NOT NULL,
  `user_email` varchar(200) NOT NULL,
  `user_password` varchar(200) NOT NULL,
  `user_status` tinyint(1) NOT NULL DEFAULT 1,
  `user_token` varchar(200) DEFAULT NULL,
  `user_avatar` varchar(200) DEFAULT NULL,
  `user_specific_encryption_ciphertext` varchar(200) DEFAULT NULL,
  `user_php_session` varchar(255) DEFAULT NULL,
  `user_extension_key` varchar(18) DEFAULT NULL,
  `user_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `user_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO users VALUES("1","Chanda Chewe","chewec03@gmail.com","$2y$10$g6KlKgZ2vjOpkH9sI4HRCu8PzkVWLT7/DxSUmMx4CPtC.TNpe.Mxe","1","","","yYSYv8vPTsfDxUxNpnUh6qJrWnUP9MuTYGHW3YtFarTh/65LKk0yQlmzwk1qBJU2hfOKrLuKGfk=","","","2023-08-26 12:32:16","2023-08-26 20:46:43","");



CREATE TABLE `vendor_documents` (
  `vendor_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `vendor_files` (
  `vendor_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `vendor_logins` (
  `vendor_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;




CREATE TABLE `vendors` (
  `vendor_id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_name` varchar(200) NOT NULL,
  `vendor_description` varchar(200) DEFAULT NULL,
  `vendor_contact_name` varchar(200) DEFAULT NULL,
  `vendor_phone` varchar(200) DEFAULT NULL,
  `vendor_extension` varchar(200) DEFAULT NULL,
  `vendor_email` varchar(200) DEFAULT NULL,
  `vendor_website` varchar(200) DEFAULT NULL,
  `vendor_hours` varchar(200) DEFAULT NULL,
  `vendor_sla` varchar(200) DEFAULT NULL,
  `vendor_code` varchar(200) DEFAULT NULL,
  `vendor_account_number` varchar(200) DEFAULT NULL,
  `vendor_notes` text DEFAULT NULL,
  `vendor_template` tinyint(1) NOT NULL DEFAULT 0,
  `vendor_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `vendor_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `vendor_archived_at` datetime DEFAULT NULL,
  `vendor_accessed_at` datetime DEFAULT NULL,
  `vendor_client_id` int(11) NOT NULL DEFAULT 0,
  `vendor_template_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`vendor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO vendors VALUES("1","Zamtel","Zamtel Mobile Operator","Elvis Mushaukwa","","","","","","","","0104143267300","","0","2023-08-26 16:43:25","","","","0","0");
INSERT INTO vendors VALUES("2","Office Rentals","","","","","","","","","","","","0","2023-08-26 16:44:07","","","","0","0");

