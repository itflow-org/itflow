-- MariaDB dump 10.19  Distrib 10.5.21-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: itflow_dev
-- ------------------------------------------------------
-- Server version	10.5.21-MariaDB-1:10.5.21+maria~ubu2004

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_keys` (
  `api_key_id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key_name` varchar(255) NOT NULL,
  `api_key_secret` varchar(255) NOT NULL,
  `api_key_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `api_key_expire` date NOT NULL,
  `api_key_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`api_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_custom`
--

DROP TABLE IF EXISTS `asset_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_custom` (
  `asset_custom_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_custom_field_value` int(11) NOT NULL,
  `asset_custom_field_id` int(11) NOT NULL,
  `asset_custom_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_custom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_documents`
--

DROP TABLE IF EXISTS `asset_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_documents` (
  `asset_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_files`
--

DROP TABLE IF EXISTS `asset_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_files` (
  `asset_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_logins`
--

DROP TABLE IF EXISTS `asset_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_logins` (
  `asset_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendars`
--

DROP TABLE IF EXISTS `calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendars` (
  `calendar_id` int(11) NOT NULL AUTO_INCREMENT,
  `calendar_name` varchar(200) NOT NULL,
  `calendar_color` varchar(200) NOT NULL,
  `calendar_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `calendar_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `calendar_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`calendar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(200) NOT NULL,
  `category_type` varchar(200) NOT NULL,
  `category_color` varchar(200) DEFAULT NULL,
  `category_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `category_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `category_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_tags`
--

DROP TABLE IF EXISTS `client_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_tags` (
  `client_tag_client_id` int(11) NOT NULL,
  `client_tag_tag_id` int(11) NOT NULL,
  PRIMARY KEY (`client_tag_client_id`,`client_tag_tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_assets`
--

DROP TABLE IF EXISTS `contact_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_assets` (
  `contact_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_documents`
--

DROP TABLE IF EXISTS `contact_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_documents` (
  `contact_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_files`
--

DROP TABLE IF EXISTS `contact_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_files` (
  `contact_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_logins`
--

DROP TABLE IF EXISTS `contact_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_logins` (
  `contact_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `custom_fields`
--

DROP TABLE IF EXISTS `custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_fields` (
  `custom_field_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_field_table` varchar(255) NOT NULL,
  `custom_field_label` varchar(255) NOT NULL,
  `custom_field_type` varchar(255) NOT NULL DEFAULT 'text',
  `custom_field_location` int(11) NOT NULL DEFAULT 0,
  `custom_field_order` int(11) NOT NULL DEFAULT 999,
  PRIMARY KEY (`custom_field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `custom_values`
--

DROP TABLE IF EXISTS `custom_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_values` (
  `custom_value_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_value_value` text NOT NULL,
  `custom_value_field` int(11) NOT NULL,
  PRIMARY KEY (`custom_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `domains`
--

DROP TABLE IF EXISTS `domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_queue`
--

DROP TABLE IF EXISTS `email_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `file_id` int(11) NOT NULL AUTO_INCREMENT,
  `file_reference_name` varchar(200) DEFAULT NULL,
  `file_name` varchar(200) NOT NULL,
  `file_ext` varchar(200) DEFAULT NULL,
  `file_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `file_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `file_archived_at` datetime DEFAULT NULL,
  `file_accessed_at` datetime DEFAULT NULL,
  `file_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `folders`
--

DROP TABLE IF EXISTS `folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `folders` (
  `folder_id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_name` varchar(200) NOT NULL,
  `parent_folder` int(11) NOT NULL DEFAULT 0,
  `folder_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `history`
--

DROP TABLE IF EXISTS `history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `history_status` varchar(200) NOT NULL,
  `history_description` varchar(200) NOT NULL,
  `history_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `history_invoice_id` int(11) NOT NULL DEFAULT 0,
  `history_recurring_id` int(11) NOT NULL DEFAULT 0,
  `history_quote_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interfaces`
--

DROP TABLE IF EXISTS `interfaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logins`
--

DROP TABLE IF EXISTS `logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `networks`
--

DROP TABLE IF EXISTS `networks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quotes`
--

DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotes` (
  `quote_id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_prefix` varchar(200) DEFAULT NULL,
  `quote_number` int(11) NOT NULL,
  `quote_scope` varchar(255) DEFAULT NULL,
  `quote_status` varchar(200) NOT NULL,
  `quote_date` date NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `records`
--

DROP TABLE IF EXISTS `records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurring`
--

DROP TABLE IF EXISTS `recurring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `revenues`
--

DROP TABLE IF EXISTS `revenues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scheduled_tickets`
--

DROP TABLE IF EXISTS `scheduled_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_assets`
--

DROP TABLE IF EXISTS `service_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_assets` (
  `service_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_certificates`
--

DROP TABLE IF EXISTS `service_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_certificates` (
  `service_id` int(11) NOT NULL,
  `certificate_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_contacts`
--

DROP TABLE IF EXISTS `service_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_contacts` (
  `service_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_documents`
--

DROP TABLE IF EXISTS `service_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_documents` (
  `service_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_domains`
--

DROP TABLE IF EXISTS `service_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_domains` (
  `service_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_logins`
--

DROP TABLE IF EXISTS `service_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_logins` (
  `service_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_vendors`
--

DROP TABLE IF EXISTS `service_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_vendors` (
  `service_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `company_id` int(11) NOT NULL,
  `config_current_database_version` varchar(10) NOT NULL,
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
  `config_login_key_required` tinyint(1) NOT NULL DEFAULT 0,
  `config_login_key_secret` varchar(255) DEFAULT NULL,
  `config_module_enable_ticketing` tinyint(1) NOT NULL DEFAULT 1,
  `config_theme` varchar(200) DEFAULT 'blue',
  `config_telemetry` tinyint(1) DEFAULT 0,
  `config_timezone` varchar(200) NOT NULL DEFAULT 'America/New_York',
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shared_items`
--

DROP TABLE IF EXISTS `shared_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software`
--

DROP TABLE IF EXISTS `software`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_assets`
--

DROP TABLE IF EXISTS `software_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `software_assets` (
  `software_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_contacts`
--

DROP TABLE IF EXISTS `software_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `software_contacts` (
  `software_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_documents`
--

DROP TABLE IF EXISTS `software_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `software_documents` (
  `software_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_files`
--

DROP TABLE IF EXISTS `software_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `software_files` (
  `software_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_logins`
--

DROP TABLE IF EXISTS `software_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `software_logins` (
  `software_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `taxes`
--

DROP TABLE IF EXISTS `taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taxes` (
  `tax_id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(200) NOT NULL,
  `tax_percent` float NOT NULL,
  `tax_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `tax_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `tax_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tax_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_attachments`
--

DROP TABLE IF EXISTS `ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_attachments` (
  `ticket_attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_attachment_name` varchar(255) NOT NULL,
  `ticket_attachment_reference_name` varchar(255) NOT NULL,
  `ticket_attachment_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_attachment_ticket_id` int(11) NOT NULL,
  `ticket_attachment_reply_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`ticket_attachment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_replies`
--

DROP TABLE IF EXISTS `ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_views`
--

DROP TABLE IF EXISTS `ticket_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_views` (
  `view_id` int(11) NOT NULL AUTO_INCREMENT,
  `view_ticket_id` int(11) NOT NULL,
  `view_user_id` int(11) NOT NULL,
  `view_timestamp` datetime NOT NULL,
  PRIMARY KEY (`view_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transfers` (
  `transfer_id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_notes` text DEFAULT NULL,
  `transfer_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `transfer_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `transfer_archived_at` datetime DEFAULT NULL,
  `transfer_expense_id` int(11) NOT NULL,
  `transfer_revenue_id` int(11) NOT NULL,
  PRIMARY KEY (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trips`
--

DROP TABLE IF EXISTS `trips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `user_role` int(11) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_documents`
--

DROP TABLE IF EXISTS `vendor_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_documents` (
  `vendor_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_files`
--

DROP TABLE IF EXISTS `vendor_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_files` (
  `vendor_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_logins`
--

DROP TABLE IF EXISTS `vendor_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_logins` (
  `vendor_id` int(11) NOT NULL,
  `login_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2023-07-11 11:40:09
