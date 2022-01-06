-- MariaDB dump 10.19  Distrib 10.5.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: pittpc_crm
-- ------------------------------------------------------
-- Server version	10.5.13-MariaDB-1:10.5.13+maria~focal

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
  `account_created_at` datetime NOT NULL,
  `account_updated_at` datetime DEFAULT NULL,
  `account_archived_at` datetime DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts` (
  `alert_id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_type` varchar(200) NOT NULL,
  `alert_message` varchar(255) NOT NULL,
  `alert_date` datetime NOT NULL,
  `alert_ack_date` datetime DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`alert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_keys` (
  `api_key_id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key_secret` varchar(255) NOT NULL,
  `api_key_description` varchar(255) DEFAULT NULL,
  `api_key_created_at` datetime NOT NULL,
  `api_key_expire` datetime NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`api_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `asset_make` varchar(200) NOT NULL,
  `asset_model` varchar(200) DEFAULT NULL,
  `asset_serial` varchar(200) DEFAULT NULL,
  `asset_os` varchar(200) DEFAULT NULL,
  `asset_ip` varchar(20) DEFAULT NULL,
  `asset_mac` varchar(17) DEFAULT NULL,
  `asset_purchase_date` date DEFAULT NULL,
  `asset_warranty_expire` date DEFAULT NULL,
  `asset_install_date` date DEFAULT NULL,
  `asset_reciept` varchar(200) DEFAULT NULL,
  `asset_notes` text DEFAULT NULL,
  `asset_created_at` datetime NOT NULL,
  `asset_updated_at` datetime DEFAULT NULL,
  `asset_archived_at` datetime DEFAULT NULL,
  `asset_login_id` int(11) DEFAULT NULL,
  `asset_vendor_id` int(11) DEFAULT NULL,
  `asset_location_id` int(11) DEFAULT NULL,
  `asset_contact_id` int(11) DEFAULT NULL,
  `asset_network_id` int(11) DEFAULT NULL,
  `asset_client_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `calendar_created_at` datetime NOT NULL,
  `calendar_updated_at` datetime DEFAULT NULL,
  `calendar_archived_at` datetime DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`calendar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `campaign_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_name` varchar(255) NOT NULL,
  `campaign_subject` varchar(255) NOT NULL,
  `campaign_from_name` varchar(200) NOT NULL,
  `campaign_from_email` varchar(200) NOT NULL,
  `campaign_content` longtext NOT NULL,
  `campaign_status` varchar(20) NOT NULL,
  `campaign_sent_count` int(11) DEFAULT NULL,
  `campaign_open_count` int(11) DEFAULT NULL,
  `campaign_scheduled_at` datetime DEFAULT NULL,
  `campaign_created_at` datetime NOT NULL,
  `campaign_updated_at` datetime DEFAULT NULL,
  `campaign_archived_at` datetime DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `category_created_at` datetime NOT NULL,
  `category_updated_at` datetime DEFAULT NULL,
  `category_archived_at` datetime DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `certificate_public_key` varchar(1500) DEFAULT NULL,
  `certificate_notes` text DEFAULT NULL,
  `certificate_created_at` datetime NOT NULL,
  `certificate_updated_at` datetime DEFAULT NULL,
  `certificate_archived_at` datetime DEFAULT NULL,
  `certificate_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`certificate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_tags`
--

DROP TABLE IF EXISTS `client_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_tags` (
  `client_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`client_id`,`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `client_currency_code` varchar(200) NOT NULL,
  `client_net_terms` int(10) NOT NULL,
  `client_notes` text DEFAULT NULL,
  `client_created_at` datetime NOT NULL,
  `client_updated_at` datetime DEFAULT NULL,
  `client_archived_at` datetime DEFAULT NULL,
  `client_accessed_at` datetime DEFAULT NULL,
  `primary_location` int(11) DEFAULT NULL,
  `primary_contact` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `company_currency` varchar(200) NOT NULL,
  `company_created_at` datetime NOT NULL,
  `company_updated_at` datetime DEFAULT NULL,
  `company_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `contact_mobile` varchar(200) DEFAULT 'NULL',
  `contact_photo` varchar(200) DEFAULT NULL,
  `contact_notes` text DEFAULT NULL,
  `contact_created_at` datetime NOT NULL,
  `contact_updated_at` datetime DEFAULT NULL,
  `contact_archived_at` datetime DEFAULT NULL,
  `contact_location_id` int(11) DEFAULT NULL,
  `contact_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `contract_id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_title` varchar(200) NOT NULL,
  `contract_description` longtext DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date NOT NULL,
  `contract_created_at` datetime NOT NULL,
  `contract_updated_at` datetime DEFAULT NULL,
  `contract_archived_at` datetime DEFAULT NULL,
  `contract_vendor_id` int(11) NOT NULL,
  `contract_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `custom_links`
--

DROP TABLE IF EXISTS `custom_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_links` (
  `custom_link_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_link_name` varchar(250) NOT NULL,
  `custom_link_icon` varchar(100) DEFAULT NULL,
  `custom_link_url` varchar(250) NOT NULL,
  `custom_link_order` int(11) DEFAULT NULL,
  `custom_link_created_at` datetime NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`custom_link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_tags`
--

DROP TABLE IF EXISTS `document_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_tags` (
  `tag_id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(30) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `document_created_at` datetime NOT NULL,
  `document_updated_at` datetime DEFAULT NULL,
  `document_archived_at` datetime DEFAULT NULL,
  `document_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents_tagged`
--

DROP TABLE IF EXISTS `documents_tagged`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents_tagged` (
  `document_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`document_id`,`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `domain_created_at` datetime NOT NULL,
  `domain_updated_at` datetime DEFAULT NULL,
  `domain_archived_at` datetime DEFAULT NULL,
  `domain_registrar` int(11) DEFAULT NULL,
  `domain_webhost` int(11) DEFAULT NULL,
  `domain_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `event_start` datetime NOT NULL,
  `event_end` datetime DEFAULT NULL,
  `event_repeat` varchar(200) DEFAULT NULL,
  `event_created_at` datetime NOT NULL,
  `event_updated_at` datetime DEFAULT NULL,
  `event_archived_at` datetime DEFAULT NULL,
  `event_client_id` int(11) DEFAULT NULL,
  `event_location_id` int(11) DEFAULT NULL,
  `event_calendar_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `expense_created_at` datetime NOT NULL,
  `expense_updated_at` datetime DEFAULT NULL,
  `expense_archived_at` datetime DEFAULT NULL,
  `expense_vendor_id` int(11) DEFAULT NULL,
  `expense_client_id` int(11) DEFAULT NULL,
  `expense_asset_id` int(11) DEFAULT NULL,
  `expense_category_id` int(11) DEFAULT NULL,
  `expense_account_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`expense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `file_created_at` datetime NOT NULL,
  `file_updated_at` datetime DEFAULT NULL,
  `file_archived_at` datetime DEFAULT NULL,
  `file_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `history`
--

DROP TABLE IF EXISTS `history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `history_date` date NOT NULL,
  `history_status` varchar(200) NOT NULL,
  `history_description` varchar(200) NOT NULL,
  `history_created_at` datetime NOT NULL,
  `history_archived_at` datetime DEFAULT NULL,
  `history_invoice_id` int(11) DEFAULT NULL,
  `history_recurring_id` int(11) DEFAULT NULL,
  `history_quote_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `item_quantity` decimal(15,2) NOT NULL,
  `item_price` decimal(15,2) NOT NULL,
  `item_subtotal` decimal(15,2) NOT NULL,
  `item_tax` decimal(15,2) DEFAULT NULL,
  `item_total` decimal(15,2) NOT NULL,
  `item_created_at` datetime NOT NULL,
  `item_updated_at` datetime DEFAULT NULL,
  `item_archived_at` datetime DEFAULT NULL,
  `item_tax_id` int(11) DEFAULT NULL,
  `item_quote_id` int(11) DEFAULT NULL,
  `item_recurring_id` int(11) DEFAULT NULL,
  `item_invoice_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `invoice_amount` decimal(15,2) DEFAULT NULL,
  `invoice_currency_code` varchar(200) NOT NULL,
  `invoice_note` text DEFAULT NULL,
  `invoice_url_key` varchar(200) DEFAULT NULL,
  `invoice_created_at` datetime NOT NULL,
  `invoice_updated_at` datetime DEFAULT NULL,
  `invoice_archived_at` datetime DEFAULT NULL,
  `invoice_category_id` int(11) NOT NULL,
  `invoice_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `location_notes` text DEFAULT NULL,
  `location_created_at` datetime NOT NULL,
  `location_updated_at` datetime DEFAULT NULL,
  `location_archived_at` datetime DEFAULT NULL,
  `location_contact_id` int(11) DEFAULT NULL,
  `location_client_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `login_category` varchar(200) DEFAULT NULL,
  `login_uri` varchar(200) DEFAULT NULL,
  `login_username` varchar(200) DEFAULT NULL,
  `login_password` varbinary(200) DEFAULT NULL,
  `login_otp_secret` varchar(200) DEFAULT NULL,
  `login_note` text DEFAULT NULL,
  `login_created_at` datetime NOT NULL,
  `login_updated_at` datetime DEFAULT NULL,
  `login_archived_at` datetime DEFAULT NULL,
  `login_vendor_id` int(11) DEFAULT NULL,
  `login_asset_id` int(11) DEFAULT NULL,
  `login_software_id` int(11) DEFAULT NULL,
  `login_client_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `log_created_at` datetime NOT NULL,
  `log_archived_at` datetime DEFAULT NULL,
  `log_client_id` int(11) DEFAULT NULL,
  `log_invoice_id` int(11) DEFAULT NULL,
  `log_quote_id` int(11) DEFAULT NULL,
  `log_recurring_id` int(11) DEFAULT NULL,
  `log_user_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_email` varchar(250) NOT NULL,
  `message_ip` varchar(200) DEFAULT NULL,
  `message_sent_at` datetime DEFAULT NULL,
  `message_opened_at` datetime DEFAULT NULL,
  `message_campaign_id` int(11) NOT NULL,
  `message_client_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `network_created_at` datetime NOT NULL,
  `network_updated_at` datetime DEFAULT NULL,
  `network_archived_at` datetime DEFAULT NULL,
  `network_location_id` int(11) DEFAULT NULL,
  `network_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`network_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `payment_created_at` datetime NOT NULL,
  `payment_updated_at` datetime DEFAULT NULL,
  `payment_archived_at` datetime DEFAULT NULL,
  `payment_account_id` int(11) NOT NULL,
  `payment_invoice_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `product_cost` decimal(15,2) NOT NULL,
  `product_currency_code` varchar(200) NOT NULL,
  `product_created_at` datetime NOT NULL,
  `product_updated_at` datetime DEFAULT NULL,
  `product_archived_at` datetime DEFAULT NULL,
  `product_tax_id` int(11) DEFAULT NULL,
  `product_category_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `quote_amount` decimal(15,2) DEFAULT NULL,
  `quote_currency_code` varchar(200) NOT NULL,
  `quote_note` text DEFAULT NULL,
  `quote_url_key` varchar(200) DEFAULT NULL,
  `quote_created_at` datetime NOT NULL,
  `quote_updated_at` datetime DEFAULT NULL,
  `quote_archived_at` datetime DEFAULT NULL,
  `quote_category_id` int(11) NOT NULL,
  `quote_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `record_created_at` datetime NOT NULL,
  `record_updated_at` datetime NOT NULL,
  `record_archived_at` datetime DEFAULT NULL,
  `record_domain_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `recurring_amount` decimal(15,2) DEFAULT NULL,
  `recurring_currency_code` varchar(200) NOT NULL,
  `recurring_note` text DEFAULT NULL,
  `recurring_created_at` datetime NOT NULL,
  `recurring_updated_at` datetime DEFAULT NULL,
  `recurring_archived_at` datetime DEFAULT NULL,
  `recurring_category_id` int(11) NOT NULL,
  `recurring_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `revenue_amount` decimal(15,2) NOT NULL,
  `revenue_currency_code` varchar(200) NOT NULL,
  `revenue_payment_method` varchar(200) DEFAULT NULL,
  `revenue_reference` varchar(200) DEFAULT NULL,
  `revenue_description` varchar(200) DEFAULT NULL,
  `revenue_created_at` datetime NOT NULL,
  `revenue_updated_at` datetime DEFAULT NULL,
  `revenue_archived_at` datetime DEFAULT NULL,
  `revenue_category_id` int(11) NOT NULL,
  `revenue_account_id` int(11) NOT NULL,
  `revenue_client_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`revenue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(200) NOT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `company_id` int(11) NOT NULL,
  `config_api_key` varchar(200) DEFAULT NULL,
  `config_aes_key` varchar(250) DEFAULT NULL,
  `config_base_url` varchar(200) DEFAULT NULL,
  `config_smtp_host` varchar(200) DEFAULT NULL,
  `config_smtp_port` int(5) DEFAULT NULL,
  `config_smtp_username` varchar(200) DEFAULT NULL,
  `config_smtp_password` varchar(200) DEFAULT NULL,
  `config_mail_from_email` varchar(200) DEFAULT NULL,
  `config_mail_from_name` varchar(200) DEFAULT NULL,
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
  `config_recurring_prefix` varchar(200) DEFAULT NULL,
  `config_recurring_next_number` int(11) NOT NULL,
  `config_quote_prefix` varchar(200) DEFAULT NULL,
  `config_quote_next_number` int(11) DEFAULT NULL,
  `config_quote_footer` text DEFAULT NULL,
  `config_ticket_prefix` varchar(200) DEFAULT NULL,
  `config_ticket_next_number` int(11) DEFAULT NULL,
  `config_enable_cron` tinyint(1) DEFAULT NULL,
  `config_recurring_auto_send_invoice` tinyint(1) DEFAULT NULL,
  `config_enable_alert_domain_expire` tinyint(1) DEFAULT NULL,
  `config_send_invoice_reminders` tinyint(1) DEFAULT NULL,
  `config_invoice_overdue_reminders` varchar(200) DEFAULT NULL,
  `config_stripe_enable` tinyint(1) DEFAULT NULL,
  `config_stripe_publishable` varchar(255) DEFAULT NULL,
  `config_stripe_secret` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `software_type` varchar(200) NOT NULL,
  `software_license` varchar(200) DEFAULT NULL,
  `software_notes` text DEFAULT NULL,
  `software_created_at` datetime NOT NULL,
  `software_updated_at` datetime DEFAULT NULL,
  `software_archived_at` datetime DEFAULT NULL,
  `software_login_id` int(11) DEFAULT NULL,
  `software_client_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `tag_created_at` datetime NOT NULL,
  `tag_updated_at` datetime DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `tax_created_at` datetime NOT NULL,
  `tax_updated_at` datetime DEFAULT NULL,
  `tax_archived_at` datetime DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`tax_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `ticket_reply_created_at` datetime NOT NULL,
  `ticket_reply_updated_at` datetime DEFAULT NULL,
  `ticket_reply_archived_at` datetime DEFAULT NULL,
  `ticket_reply_by` int(11) NOT NULL,
  `ticket_reply_ticket_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`ticket_reply_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `ticket_category` varchar(200) DEFAULT NULL,
  `ticket_subject` varchar(200) NOT NULL,
  `ticket_details` longtext NOT NULL,
  `ticket_priority` varchar(200) DEFAULT NULL,
  `ticket_status` varchar(200) NOT NULL,
  `ticket_created_at` datetime NOT NULL,
  `ticket_updated_at` datetime DEFAULT NULL,
  `ticket_archived_at` datetime DEFAULT NULL,
  `ticket_closed_at` datetime DEFAULT NULL,
  `ticket_created_by` int(11) NOT NULL,
  `ticket_assigned_to` int(11) DEFAULT NULL,
  `ticket_closed_by` int(11) DEFAULT NULL,
  `ticket_vendor_id` int(11) DEFAULT NULL,
  `ticket_client_id` int(11) DEFAULT NULL,
  `ticket_contact_id` int(11) DEFAULT NULL,
  `ticket_location_id` int(11) DEFAULT NULL,
  `ticket_asset_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `transfer_created_at` datetime NOT NULL,
  `transfer_updated_at` datetime DEFAULT NULL,
  `transfer_archived_at` datetime DEFAULT NULL,
  `transfer_expense_id` int(11) NOT NULL,
  `transfer_revenue_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `trip_created_at` datetime NOT NULL,
  `trip_updated_at` datetime DEFAULT NULL,
  `trip_archived_at` datetime DEFAULT NULL,
  `trip_client_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`trip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_clients`
--

DROP TABLE IF EXISTS `user_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_clients` (
  `user_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_companies`
--

DROP TABLE IF EXISTS `user_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_companies` (
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_keys`
--

DROP TABLE IF EXISTS `user_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_keys` (
  `user_key_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_key_name` varchar(200) DEFAULT NULL,
  `user_public_key` varchar(250) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`user_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `user_default_company` int(11) NOT NULL,
  `user_role` int(11) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `user_token` varchar(200) DEFAULT NULL,
  `user_avatar` varchar(200) DEFAULT NULL,
  `user_created_at` datetime NOT NULL,
  `user_updated_at` datetime DEFAULT NULL,
  `user_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `vendor_country` varchar(200) DEFAULT NULL,
  `vendor_address` varchar(200) DEFAULT NULL,
  `vendor_city` varchar(200) DEFAULT NULL,
  `vendor_state` varchar(200) DEFAULT NULL,
  `vendor_zip` varchar(200) DEFAULT NULL,
  `vendor_contact_name` varchar(200) DEFAULT NULL,
  `vendor_phone` varchar(200) DEFAULT NULL,
  `vendor_extension` varchar(200) DEFAULT NULL,
  `vendor_email` varchar(200) DEFAULT NULL,
  `vendor_website` varchar(200) DEFAULT NULL,
  `vendor_account_number` varchar(200) DEFAULT NULL,
  `vendor_notes` text DEFAULT NULL,
  `vendor_global` tinyint(1) DEFAULT NULL,
  `vendor_created_at` datetime NOT NULL,
  `vendor_updated_at` datetime DEFAULT NULL,
  `vendor_archived_at` datetime DEFAULT NULL,
  `vendor_client_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2022-01-05 21:36:40
