-- MariaDB dump 10.19  Distrib 10.11.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: itflow_dev
-- ------------------------------------------------------
-- Server version	10.11.6-MariaDB-0+deb12u1

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
  `account_description` varchar(250) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `account_currency_code` varchar(200) NOT NULL,
  `account_notes` text DEFAULT NULL,
  `account_type` int(6) DEFAULT NULL,
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
  `api_key_decrypt_hash` varchar(200) NOT NULL,
  `api_key_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `api_key_expire` date NOT NULL,
  `api_key_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`api_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `app_logs`
--

DROP TABLE IF EXISTS `app_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_logs` (
  `app_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `app_log_category` varchar(200) DEFAULT NULL,
  `app_log_type` enum('info','warning','error','debug') NOT NULL DEFAULT 'info',
  `app_log_details` varchar(1000) DEFAULT NULL,
  `app_log_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`app_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_history`
--

DROP TABLE IF EXISTS `asset_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_history` (
  `asset_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_history_status` varchar(200) NOT NULL,
  `asset_history_description` varchar(255) NOT NULL,
  `asset_history_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `asset_history_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_interfaces`
--

DROP TABLE IF EXISTS `asset_interfaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_interfaces` (
  `interface_id` int(11) NOT NULL AUTO_INCREMENT,
  `interface_name` varchar(200) NOT NULL,
  `interface_mac` varchar(200) DEFAULT NULL,
  `interface_ip` varchar(200) DEFAULT NULL,
  `interface_nat_ip` varchar(200) DEFAULT NULL,
  `interface_ipv6` varchar(200) DEFAULT NULL,
  `interface_port` varchar(200) DEFAULT NULL,
  `interface_notes` text DEFAULT NULL,
  `interface_primary` tinyint(1) DEFAULT 0,
  `interface_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `interface_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `interface_archived_at` datetime DEFAULT NULL,
  `interface_network_id` int(11) DEFAULT NULL,
  `interface_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`interface_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_notes`
--

DROP TABLE IF EXISTS `asset_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_notes` (
  `asset_note_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_note_type` varchar(200) NOT NULL,
  `asset_note` text DEFAULT NULL,
  `asset_note_created_by` int(11) NOT NULL,
  `asset_note_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `asset_note_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `asset_note_archived_at` datetime DEFAULT NULL,
  `asset_note_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `asset_uri` varchar(500) DEFAULT NULL,
  `asset_uri_2` varchar(500) DEFAULT NULL,
  `asset_status` varchar(200) DEFAULT NULL,
  `asset_purchase_date` date DEFAULT NULL,
  `asset_warranty_expire` date DEFAULT NULL,
  `asset_install_date` date DEFAULT NULL,
  `asset_photo` varchar(200) DEFAULT NULL,
  `asset_physical_location` varchar(200) DEFAULT NULL,
  `asset_notes` text DEFAULT NULL,
  `asset_important` tinyint(1) NOT NULL DEFAULT 0,
  `asset_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `asset_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `asset_archived_at` datetime DEFAULT NULL,
  `asset_accessed_at` datetime DEFAULT NULL,
  `asset_vendor_id` int(11) NOT NULL DEFAULT 0,
  `asset_location_id` int(11) NOT NULL DEFAULT 0,
  `asset_contact_id` int(11) NOT NULL DEFAULT 0,
  `asset_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_logs`
--

DROP TABLE IF EXISTS `auth_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_logs` (
  `auth_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `auth_log_status` tinyint(1) NOT NULL,
  `auth_log_details` varchar(200) DEFAULT NULL,
  `auth_log_ip` varchar(200) DEFAULT NULL,
  `auth_log_user_agent` varchar(250) DEFAULT NULL,
  `auth_log_user_id` int(11) NOT NULL DEFAULT 0,
  `auth_log_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`auth_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `budget`
--

DROP TABLE IF EXISTS `budget`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `category_icon` varchar(200) DEFAULT NULL,
  `category_parent` int(11) DEFAULT 0,
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
  `certificate_description` text DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_notes`
--

DROP TABLE IF EXISTS `client_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_notes` (
  `client_note_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_note_type` varchar(200) NOT NULL,
  `client_note` text DEFAULT NULL,
  `client_note_created_by` int(11) NOT NULL,
  `client_note_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_note_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `client_note_archived_at` datetime DEFAULT NULL,
  `client_note_client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_lead` tinyint(1) NOT NULL DEFAULT 0,
  `client_name` varchar(200) NOT NULL,
  `client_type` varchar(200) DEFAULT NULL,
  `client_website` varchar(200) DEFAULT NULL,
  `client_referral` varchar(200) DEFAULT NULL,
  `client_rate` decimal(15,2) DEFAULT NULL,
  `client_currency_code` varchar(200) NOT NULL,
  `client_net_terms` int(10) NOT NULL,
  `client_tax_id_number` varchar(255) DEFAULT NULL,
  `client_abbreviation` varchar(10) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_notes`
--

DROP TABLE IF EXISTS `contact_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_notes` (
  `contact_note_id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_note_type` varchar(200) NOT NULL,
  `contact_note` text DEFAULT NULL,
  `contact_note_created_by` int(11) NOT NULL,
  `contact_note_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `contact_note_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `contact_note_archived_at` datetime DEFAULT NULL,
  `contact_note_contact_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_tags`
--

DROP TABLE IF EXISTS `contact_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_tags` (
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `contact_primary` tinyint(1) NOT NULL DEFAULT 0,
  `contact_important` tinyint(1) NOT NULL DEFAULT 0,
  `contact_billing` tinyint(1) DEFAULT 0,
  `contact_technical` tinyint(1) DEFAULT 0,
  `contact_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `contact_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `contact_archived_at` datetime DEFAULT NULL,
  `contact_accessed_at` datetime DEFAULT NULL,
  `contact_location_id` int(11) NOT NULL DEFAULT 0,
  `contact_vendor_id` int(11) NOT NULL DEFAULT 0,
  `contact_user_id` int(11) NOT NULL DEFAULT 0,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `custom_links`
--

DROP TABLE IF EXISTS `custom_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_links` (
  `custom_link_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_link_name` varchar(200) NOT NULL,
  `custom_link_description` text DEFAULT NULL,
  `custom_link_uri` varchar(500) NOT NULL,
  `custom_link_new_tab` tinyint(1) NOT NULL DEFAULT 0,
  `custom_link_icon` varchar(200) DEFAULT NULL,
  `custom_link_location` int(11) NOT NULL DEFAULT 1,
  `custom_link_order` int(11) NOT NULL DEFAULT 0,
  `custom_link_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `custom_link_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `custom_link_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`custom_link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_files`
--

DROP TABLE IF EXISTS `document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_files` (
  `document_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`document_id`,`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `document_description` text DEFAULT NULL,
  `document_content` longtext NOT NULL,
  `document_content_raw` longtext NOT NULL,
  `document_important` tinyint(1) NOT NULL DEFAULT 0,
  `document_parent` int(11) NOT NULL DEFAULT 0,
  `document_client_visible` int(11) NOT NULL DEFAULT 1,
  `document_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `document_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `document_archived_at` datetime DEFAULT NULL,
  `document_accessed_at` datetime DEFAULT NULL,
  `document_template` tinyint(1) NOT NULL DEFAULT 0,
  `document_folder_id` int(11) NOT NULL DEFAULT 0,
  `document_created_by` int(11) NOT NULL DEFAULT 0,
  `document_updated_by` int(11) NOT NULL DEFAULT 0,
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
  `domain_description` text DEFAULT NULL,
  `domain_expire` date DEFAULT NULL,
  `domain_ip` varchar(255) DEFAULT NULL,
  `domain_name_servers` varchar(255) DEFAULT NULL,
  `domain_mail_servers` varchar(255) DEFAULT NULL,
  `domain_txt` text DEFAULT NULL,
  `domain_raw_whois` text DEFAULT NULL,
  `domain_notes` text DEFAULT NULL,
  `domain_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `domain_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `domain_archived_at` datetime DEFAULT NULL,
  `domain_accessed_at` datetime DEFAULT NULL,
  `domain_registrar` int(11) NOT NULL DEFAULT 0,
  `domain_webhost` int(11) NOT NULL DEFAULT 0,
  `domain_dnshost` int(11) NOT NULL DEFAULT 0,
  `domain_mailhost` int(11) NOT NULL DEFAULT 0,
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
  `email_cal_str` varchar(1024) DEFAULT NULL,
  `email_queued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `email_failed_at` datetime DEFAULT NULL,
  `email_attempts` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_attendees`
--

DROP TABLE IF EXISTS `event_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_attendees` (
  `attendee_id` int(11) NOT NULL AUTO_INCREMENT,
  `attendee_name` varchar(200) DEFAULT NULL,
  `attendee_email` varchar(200) DEFAULT NULL,
  `attendee_invitation_status` tinyint(1) NOT NULL DEFAULT 0,
  `attendee_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attendee_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `attendee_archived_at` datetime DEFAULT NULL,
  `attendee_contact_id` int(11) NOT NULL DEFAULT 0,
  `attendee_event_id` int(11) NOT NULL,
  PRIMARY KEY (`attendee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `event_location` text DEFAULT NULL,
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
  `file_description` varchar(250) DEFAULT NULL,
  `file_ext` varchar(10) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `file_hash` varchar(200) DEFAULT NULL,
  `file_mime_type` varchar(100) DEFAULT NULL,
  `file_has_thumbnail` tinyint(1) NOT NULL DEFAULT 0,
  `file_has_preview` tinyint(1) NOT NULL DEFAULT 0,
  `file_important` tinyint(1) NOT NULL DEFAULT 0,
  `file_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `file_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `file_archived_at` datetime DEFAULT NULL,
  `file_accessed_at` datetime DEFAULT NULL,
  `file_created_by` int(11) NOT NULL DEFAULT 0,
  `file_folder_id` int(11) NOT NULL DEFAULT 0,
  `file_asset_id` int(11) NOT NULL DEFAULT 0,
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
  `folder_location` int(11) DEFAULT 0,
  `folder_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `item_order` int(11) NOT NULL DEFAULT 0,
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
  `invoice_discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
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
-- Table structure for table `location_tags`
--

DROP TABLE IF EXISTS `location_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `location_tags` (
  `location_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`location_id`,`tag_id`)
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
  `location_description` text DEFAULT NULL,
  `location_country` varchar(200) DEFAULT NULL,
  `location_address` varchar(200) DEFAULT NULL,
  `location_city` varchar(200) DEFAULT NULL,
  `location_state` varchar(200) DEFAULT NULL,
  `location_zip` varchar(200) DEFAULT NULL,
  `location_phone` varchar(200) DEFAULT NULL,
  `location_fax` varchar(200) DEFAULT NULL,
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
-- Table structure for table `login_tags`
--

DROP TABLE IF EXISTS `login_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_tags` (
  `login_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`login_id`,`tag_id`)
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
  `login_description` varchar(500) DEFAULT NULL,
  `login_category` varchar(200) DEFAULT NULL,
  `login_uri` varchar(500) DEFAULT NULL,
  `login_uri_2` varchar(500) DEFAULT NULL,
  `login_username` varchar(500) DEFAULT NULL,
  `login_password` varbinary(200) DEFAULT NULL,
  `login_otp_secret` varchar(200) DEFAULT NULL,
  `login_note` text DEFAULT NULL,
  `login_important` tinyint(1) NOT NULL DEFAULT 0,
  `login_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `login_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `login_archived_at` datetime DEFAULT NULL,
  `login_accessed_at` datetime DEFAULT NULL,
  `login_password_changed_at` datetime DEFAULT current_timestamp(),
  `login_folder_id` int(11) NOT NULL DEFAULT 0,
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
  `log_description` varchar(1000) NOT NULL,
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
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `module_id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(200) NOT NULL,
  `module_description` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`module_id`)
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
  `network_description` text DEFAULT NULL,
  `network_vlan` int(11) DEFAULT NULL,
  `network` varchar(200) NOT NULL,
  `network_subnet` varchar(200) DEFAULT NULL,
  `network_gateway` varchar(200) NOT NULL,
  `network_primary_dns` varchar(200) DEFAULT NULL,
  `network_secondary_dns` varchar(200) DEFAULT NULL,
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
  `notification` varchar(1000) NOT NULL,
  `notification_action` varchar(250) DEFAULT NULL,
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
-- Table structure for table `patch_panel_ports`
--

DROP TABLE IF EXISTS `patch_panel_ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patch_panel_ports` (
  `port_id` int(11) NOT NULL AUTO_INCREMENT,
  `port_number` int(11) NOT NULL,
  `port_name` varchar(200) DEFAULT NULL,
  `port_description` text DEFAULT NULL,
  `port_type` varchar(200) DEFAULT NULL,
  `port_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `port_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `port_archived_at` datetime DEFAULT NULL,
  `port_asset_id` int(11) DEFAULT NULL,
  `port_patch_panel_id` int(11) NOT NULL,
  PRIMARY KEY (`port_id`),
  KEY `port_patch_panel_id` (`port_patch_panel_id`),
  CONSTRAINT `patch_panel_ports_ibfk_1` FOREIGN KEY (`port_patch_panel_id`) REFERENCES `patch_panels` (`patch_panel_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `patch_panels`
--

DROP TABLE IF EXISTS `patch_panels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patch_panels` (
  `patch_panel_id` int(11) NOT NULL AUTO_INCREMENT,
  `patch_panel_name` varchar(200) NOT NULL,
  `patch_panel_description` text DEFAULT NULL,
  `patch_panel_type` varchar(200) DEFAULT NULL,
  `patch_panel_ports` int(11) NOT NULL,
  `patch_panel_physical_location` varchar(200) DEFAULT NULL,
  `patch_panel_notes` text DEFAULT NULL,
  `patch_panel_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `patch_panel_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `patch_panel_archived_at` datetime DEFAULT NULL,
  `patch_panel_location_id` int(11) DEFAULT NULL,
  `patch_panel_rack_id` int(11) DEFAULT NULL,
  `patch_panel_client_id` int(11) NOT NULL,
  PRIMARY KEY (`patch_panel_id`)
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
-- Table structure for table `project_template_ticket_templates`
--

DROP TABLE IF EXISTS `project_template_ticket_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_template_ticket_templates` (
  `ticket_template_id` int(11) NOT NULL,
  `project_template_id` int(11) NOT NULL,
  `ticket_template_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ticket_template_id`,`project_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_templates`
--

DROP TABLE IF EXISTS `project_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_templates` (
  `project_template_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_template_name` varchar(200) NOT NULL,
  `project_template_description` text DEFAULT NULL,
  `project_template_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `project_template_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `project_template_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`project_template_id`)
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
  `project_prefix` varchar(200) DEFAULT NULL,
  `project_number` int(11) NOT NULL DEFAULT 1,
  `project_name` varchar(255) NOT NULL,
  `project_description` text DEFAULT NULL,
  `project_due` date DEFAULT NULL,
  `project_manager` int(11) NOT NULL DEFAULT 0,
  `project_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `project_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `project_completed_at` datetime DEFAULT NULL,
  `project_archived_at` datetime DEFAULT NULL,
  `project_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `quote_discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rack_units`
--

DROP TABLE IF EXISTS `rack_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rack_units` (
  `unit_id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_start_number` int(11) NOT NULL,
  `unit_end_number` int(11) NOT NULL,
  `unit_device` varchar(200) DEFAULT NULL,
  `unit_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `unit_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `unit_archived_at` datetime DEFAULT NULL,
  `unit_asset_id` int(11) DEFAULT NULL,
  `unit_rack_id` int(11) NOT NULL,
  PRIMARY KEY (`unit_id`),
  KEY `unit_rack_id` (`unit_rack_id`),
  CONSTRAINT `rack_units_ibfk_1` FOREIGN KEY (`unit_rack_id`) REFERENCES `racks` (`rack_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `racks`
--

DROP TABLE IF EXISTS `racks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `racks` (
  `rack_id` int(11) NOT NULL AUTO_INCREMENT,
  `rack_name` varchar(200) NOT NULL,
  `rack_description` text DEFAULT NULL,
  `rack_model` varchar(200) DEFAULT NULL,
  `rack_depth` varchar(50) DEFAULT NULL,
  `rack_type` varchar(50) DEFAULT NULL,
  `rack_units` int(11) NOT NULL,
  `rack_photo` varchar(200) DEFAULT NULL,
  `rack_physical_location` varchar(200) DEFAULT NULL,
  `rack_notes` text DEFAULT NULL,
  `rack_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rack_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `rack_archived_at` datetime DEFAULT NULL,
  `rack_location_id` int(11) DEFAULT NULL,
  `rack_client_id` int(11) NOT NULL,
  PRIMARY KEY (`rack_id`)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `recurring_discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `recurring_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `recurring_currency_code` varchar(200) NOT NULL,
  `recurring_note` text DEFAULT NULL,
  `recurring_invoice_email_notify` tinyint(1) NOT NULL DEFAULT 1,
  `recurring_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recurring_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `recurring_archived_at` datetime DEFAULT NULL,
  `recurring_category_id` int(11) NOT NULL,
  `recurring_client_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurring_expenses`
--

DROP TABLE IF EXISTS `recurring_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remember_tokens` (
  `remember_token_id` int(11) NOT NULL AUTO_INCREMENT,
  `remember_token_token` varchar(255) NOT NULL,
  `remember_token_user_id` int(11) NOT NULL,
  `remember_token_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`remember_token_id`)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `scheduled_ticket_subject` varchar(500) NOT NULL,
  `scheduled_ticket_details` longtext NOT NULL,
  `scheduled_ticket_priority` varchar(200) DEFAULT NULL,
  `scheduled_ticket_frequency` varchar(10) NOT NULL,
  `scheduled_ticket_billable` tinyint(1) NOT NULL DEFAULT 0,
  `scheduled_ticket_start_date` date NOT NULL,
  `scheduled_ticket_next_run` date NOT NULL,
  `scheduled_ticket_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `scheduled_ticket_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `scheduled_ticket_created_by` int(11) NOT NULL DEFAULT 0,
  `scheduled_ticket_assigned_to` int(11) NOT NULL DEFAULT 0,
  `scheduled_ticket_client_id` int(11) NOT NULL DEFAULT 0,
  `scheduled_ticket_contact_id` int(11) NOT NULL DEFAULT 0,
  `scheduled_ticket_asset_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`scheduled_ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `config_default_hourly_rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  `config_project_prefix` varchar(200) NOT NULL DEFAULT 'PRJ-',
  `config_project_next_number` int(11) NOT NULL DEFAULT 1,
  `config_invoice_prefix` varchar(200) DEFAULT NULL,
  `config_invoice_next_number` int(11) DEFAULT NULL,
  `config_invoice_footer` text DEFAULT NULL,
  `config_invoice_from_name` varchar(200) DEFAULT NULL,
  `config_invoice_from_email` varchar(200) DEFAULT NULL,
  `config_invoice_late_fee_enable` tinyint(1) NOT NULL DEFAULT 0,
  `config_invoice_late_fee_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `config_invoice_paid_notification_email` varchar(200) DEFAULT NULL,
  `config_recurring_prefix` varchar(200) DEFAULT NULL,
  `config_recurring_next_number` int(11) NOT NULL,
  `config_quote_prefix` varchar(200) DEFAULT NULL,
  `config_quote_next_number` int(11) DEFAULT NULL,
  `config_quote_footer` text DEFAULT NULL,
  `config_quote_from_name` varchar(200) DEFAULT NULL,
  `config_quote_from_email` varchar(200) DEFAULT NULL,
  `config_quote_notification_email` varchar(200) DEFAULT NULL,
  `config_ticket_prefix` varchar(200) DEFAULT NULL,
  `config_ticket_next_number` int(11) DEFAULT NULL,
  `config_ticket_from_name` varchar(200) DEFAULT NULL,
  `config_ticket_from_email` varchar(200) DEFAULT NULL,
  `config_ticket_email_parse` tinyint(1) NOT NULL DEFAULT 0,
  `config_ticket_email_parse_unknown_senders` int(1) NOT NULL DEFAULT 0,
  `config_ticket_client_general_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `config_ticket_autoclose_hours` int(5) NOT NULL DEFAULT 72,
  `config_ticket_new_ticket_notification_email` varchar(200) DEFAULT NULL,
  `config_ticket_default_billable` tinyint(1) NOT NULL DEFAULT 0,
  `config_enable_cron` tinyint(1) NOT NULL DEFAULT 0,
  `config_cron_key` varchar(255) DEFAULT NULL,
  `config_recurring_auto_send_invoice` tinyint(1) NOT NULL DEFAULT 1,
  `config_enable_alert_domain_expire` tinyint(1) NOT NULL DEFAULT 1,
  `config_send_invoice_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `config_invoice_overdue_reminders` varchar(200) DEFAULT NULL,
  `config_stripe_enable` tinyint(1) NOT NULL DEFAULT 0,
  `config_stripe_publishable` varchar(255) DEFAULT NULL,
  `config_stripe_secret` varchar(255) DEFAULT NULL,
  `config_stripe_account` int(11) NOT NULL DEFAULT 0,
  `config_stripe_expense_vendor` int(11) NOT NULL DEFAULT 0,
  `config_stripe_expense_category` int(11) NOT NULL DEFAULT 0,
  `config_stripe_percentage_fee` decimal(4,4) NOT NULL DEFAULT 0.0290,
  `config_ai_enable` tinyint(1) DEFAULT 0,
  `config_ai_provider` varchar(250) DEFAULT NULL,
  `config_ai_model` varchar(250) DEFAULT NULL,
  `config_ai_url` varchar(250) DEFAULT NULL,
  `config_ai_api_key` varchar(250) DEFAULT NULL,
  `config_stripe_flat_fee` decimal(15,2) NOT NULL DEFAULT 0.30,
  `config_azure_client_id` varchar(200) DEFAULT NULL,
  `config_azure_client_secret` varchar(200) DEFAULT NULL,
  `config_module_enable_itdoc` tinyint(1) NOT NULL DEFAULT 1,
  `config_module_enable_accounting` tinyint(1) NOT NULL DEFAULT 1,
  `config_client_portal_enable` tinyint(1) NOT NULL DEFAULT 1,
  `config_login_message` text DEFAULT NULL,
  `config_login_key_required` tinyint(1) NOT NULL DEFAULT 0,
  `config_login_key_secret` varchar(255) DEFAULT NULL,
  `config_login_remember_me_expire` int(11) NOT NULL DEFAULT 3,
  `config_log_retention` int(11) NOT NULL DEFAULT 90,
  `config_module_enable_ticketing` tinyint(1) NOT NULL DEFAULT 1,
  `config_theme` varchar(200) DEFAULT 'blue',
  `config_telemetry` tinyint(1) DEFAULT 0,
  `config_timezone` varchar(200) NOT NULL DEFAULT 'America/New_York',
  `config_destructive_deletes_enable` tinyint(1) NOT NULL DEFAULT 0,
  `config_phone_mask` tinyint(1) NOT NULL DEFAULT 1,
  `config_whitelabel_enabled` int(11) NOT NULL DEFAULT 0,
  `config_whitelabel_key` text DEFAULT NULL,
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
  `item_recipient` varchar(250) DEFAULT NULL,
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
  `software_description` text DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_templates`
--

DROP TABLE IF EXISTS `task_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_templates` (
  `task_template_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_template_name` varchar(200) NOT NULL,
  `task_template_order` int(11) NOT NULL DEFAULT 0,
  `task_template_completion_estimate` int(11) NOT NULL DEFAULT 0,
  `task_template_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `task_template_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `task_template_archived_at` datetime DEFAULT NULL,
  `task_template_ticket_template_id` int(11) NOT NULL,
  PRIMARY KEY (`task_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_name` varchar(255) NOT NULL,
  `task_status` varchar(255) DEFAULT NULL,
  `task_order` int(11) NOT NULL DEFAULT 0,
  `task_completion_estimate` int(11) NOT NULL DEFAULT 0,
  `task_completed_at` datetime DEFAULT NULL,
  `task_completed_by` int(11) DEFAULT NULL,
  `task_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `task_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `task_ticket_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_history`
--

DROP TABLE IF EXISTS `ticket_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_history` (
  `ticket_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_history_status` varchar(200) NOT NULL,
  `ticket_history_description` varchar(255) NOT NULL,
  `ticket_history_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_history_ticket_id` int(11) NOT NULL,
  PRIMARY KEY (`ticket_history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
-- Table structure for table `ticket_statuses`
--

DROP TABLE IF EXISTS `ticket_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_statuses` (
  `ticket_status_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_status_name` varchar(200) NOT NULL,
  `ticket_status_color` varchar(200) NOT NULL,
  `ticket_status_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ticket_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_templates`
--

DROP TABLE IF EXISTS `ticket_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_templates` (
  `ticket_template_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_template_name` varchar(200) NOT NULL,
  `ticket_template_description` text DEFAULT NULL,
  `ticket_template_subject` varchar(500) DEFAULT NULL,
  `ticket_template_details` longtext DEFAULT NULL,
  `ticket_template_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_template_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ticket_template_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`ticket_template_id`)
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
-- Table structure for table `ticket_watchers`
--

DROP TABLE IF EXISTS `ticket_watchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_watchers` (
  `watcher_id` int(11) NOT NULL AUTO_INCREMENT,
  `watcher_name` varchar(255) DEFAULT NULL,
  `watcher_email` varchar(255) NOT NULL,
  `watcher_ticket_id` int(11) NOT NULL,
  PRIMARY KEY (`watcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `ticket_source` varchar(255) DEFAULT NULL COMMENT 'Where the Ticket Came from\r\nEmail, Client Portal, In-App, Project Template',
  `ticket_category` varchar(200) DEFAULT NULL,
  `ticket_subject` varchar(500) NOT NULL,
  `ticket_details` longtext NOT NULL,
  `ticket_priority` varchar(200) DEFAULT NULL,
  `ticket_status` int(11) NOT NULL,
  `ticket_billable` tinyint(1) NOT NULL DEFAULT 0,
  `ticket_schedule` datetime DEFAULT NULL,
  `ticket_onsite` tinyint(1) NOT NULL DEFAULT 0,
  `ticket_vendor_ticket_number` varchar(255) DEFAULT NULL,
  `ticket_feedback` varchar(200) DEFAULT NULL,
  `ticket_url_key` varchar(200) DEFAULT NULL,
  `ticket_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ticket_resolved_at` datetime DEFAULT NULL,
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
  `ticket_invoice_id` int(11) NOT NULL DEFAULT 0,
  `ticket_project_id` int(11) NOT NULL DEFAULT 0,
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
  `transfer_method` varchar(200) DEFAULT NULL,
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
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_role_permissions`
--

DROP TABLE IF EXISTS `user_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_role_permissions` (
  `user_role_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `user_role_permission_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_role_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_role_name` varchar(200) NOT NULL,
  `user_role_description` varchar(200) DEFAULT NULL,
  `user_role_type` tinyint(1) NOT NULL DEFAULT 1,
  `user_role_is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `user_role_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_role_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `user_role_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_role_id`)
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
  `user_config_force_mfa` tinyint(1) NOT NULL DEFAULT 0,
  `user_config_records_per_page` int(11) NOT NULL DEFAULT 10,
  `user_config_dashboard_financial_enable` tinyint(1) NOT NULL DEFAULT 0,
  `user_config_dashboard_technical_enable` tinyint(1) NOT NULL DEFAULT 0,
  `user_config_calendar_first_day` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `user_auth_method` varchar(200) NOT NULL DEFAULT 'local',
  `user_type` tinyint(1) NOT NULL DEFAULT 1,
  `user_status` tinyint(1) NOT NULL DEFAULT 1,
  `user_token` varchar(200) DEFAULT NULL,
  `user_password_reset_token` varchar(200) DEFAULT NULL,
  `user_password_reset_token_expire` datetime DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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

-- Dump completed on 2024-12-13 15:11:31
