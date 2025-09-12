/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: itflow_dev
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0+deb12u2

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
/*!40101 SET character_set_client = utf8mb4 */;
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
-- Table structure for table `ai_models`
--

DROP TABLE IF EXISTS `ai_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_models` (
  `ai_model_id` int(11) NOT NULL AUTO_INCREMENT,
  `ai_model_name` varchar(200) NOT NULL,
  `ai_model_prompt` text DEFAULT NULL,
  `ai_model_use_case` varchar(200) DEFAULT NULL,
  `ai_model_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ai_model_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ai_model_ai_provider_id` int(11) NOT NULL,
  PRIMARY KEY (`ai_model_id`),
  KEY `ai_model_ai_provider_id` (`ai_model_ai_provider_id`),
  CONSTRAINT `ai_models_ibfk_1` FOREIGN KEY (`ai_model_ai_provider_id`) REFERENCES `ai_providers` (`ai_provider_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_providers`
--

DROP TABLE IF EXISTS `ai_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_providers` (
  `ai_provider_id` int(11) NOT NULL AUTO_INCREMENT,
  `ai_provider_name` varchar(200) NOT NULL,
  `ai_provider_api_url` varchar(200) NOT NULL,
  `ai_provider_api_key` varchar(200) DEFAULT NULL,
  `ai_provider_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ai_provider_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`ai_provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_keys` (
  `api_key_id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key_name` varchar(255) NOT NULL,
  `api_key_secret` varchar(255) NOT NULL,
  `api_key_decrypt_hash` varchar(200) NOT NULL,
  `api_key_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `api_key_expire` date NOT NULL,
  `api_key_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`api_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `app_logs`
--

DROP TABLE IF EXISTS `app_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
-- Table structure for table `asset_credentials`
--

DROP TABLE IF EXISTS `asset_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_credentials` (
  `credential_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`credential_id`,`asset_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `asset_credentials_ibfk_1` FOREIGN KEY (`credential_id`) REFERENCES `credentials` (`credential_id`) ON DELETE CASCADE,
  CONSTRAINT `asset_credentials_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_custom`
--

DROP TABLE IF EXISTS `asset_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_custom` (
  `asset_custom_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_custom_field_value` int(11) NOT NULL,
  `asset_custom_field_id` int(11) NOT NULL,
  `asset_custom_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_custom_id`),
  KEY `asset_custom_asset_id` (`asset_custom_asset_id`),
  CONSTRAINT `asset_custom_ibfk_1` FOREIGN KEY (`asset_custom_asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_documents`
--

DROP TABLE IF EXISTS `asset_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_documents` (
  `asset_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`document_id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `asset_documents_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  CONSTRAINT `asset_documents_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_files`
--

DROP TABLE IF EXISTS `asset_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_files` (
  `asset_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `asset_files_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  CONSTRAINT `asset_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_history`
--

DROP TABLE IF EXISTS `asset_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_history` (
  `asset_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_history_status` varchar(200) NOT NULL,
  `asset_history_description` varchar(255) NOT NULL,
  `asset_history_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `asset_history_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_history_id`),
  KEY `asset_history_asset_id` (`asset_history_asset_id`),
  CONSTRAINT `asset_history_ibfk_1` FOREIGN KEY (`asset_history_asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_interface_links`
--

DROP TABLE IF EXISTS `asset_interface_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_interface_links` (
  `interface_link_id` int(11) NOT NULL AUTO_INCREMENT,
  `interface_a_id` int(11) NOT NULL,
  `interface_b_id` int(11) NOT NULL,
  `interface_link_type` varchar(100) DEFAULT NULL,
  `interface_link_status` varchar(50) DEFAULT NULL,
  `interface_link_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `interface_link_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`interface_link_id`),
  KEY `fk_interface_a` (`interface_a_id`),
  KEY `fk_interface_b` (`interface_b_id`),
  CONSTRAINT `fk_interface_a` FOREIGN KEY (`interface_a_id`) REFERENCES `asset_interfaces` (`interface_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interface_b` FOREIGN KEY (`interface_b_id`) REFERENCES `asset_interfaces` (`interface_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_interfaces`
--

DROP TABLE IF EXISTS `asset_interfaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_interfaces` (
  `interface_id` int(11) NOT NULL AUTO_INCREMENT,
  `interface_name` varchar(200) NOT NULL,
  `interface_description` varchar(200) DEFAULT NULL,
  `interface_type` varchar(50) DEFAULT NULL,
  `interface_mac` varchar(200) DEFAULT NULL,
  `interface_ip` varchar(200) DEFAULT NULL,
  `interface_nat_ip` varchar(200) DEFAULT NULL,
  `interface_ipv6` varchar(200) DEFAULT NULL,
  `interface_notes` text DEFAULT NULL,
  `interface_primary` tinyint(1) DEFAULT 0,
  `interface_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `interface_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `interface_archived_at` datetime DEFAULT NULL,
  `interface_network_id` int(11) DEFAULT NULL,
  `interface_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`interface_id`),
  KEY `interface_asset_id` (`interface_asset_id`),
  CONSTRAINT `asset_interfaces_ibfk_1` FOREIGN KEY (`interface_asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_notes`
--

DROP TABLE IF EXISTS `asset_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_notes` (
  `asset_note_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_note_type` varchar(200) NOT NULL,
  `asset_note` text DEFAULT NULL,
  `asset_note_created_by` int(11) NOT NULL,
  `asset_note_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `asset_note_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `asset_note_archived_at` datetime DEFAULT NULL,
  `asset_note_asset_id` int(11) NOT NULL,
  PRIMARY KEY (`asset_note_id`),
  KEY `asset_note_asset_id` (`asset_note_asset_id`),
  CONSTRAINT `asset_notes_ibfk_1` FOREIGN KEY (`asset_note_asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `asset_uri_client` varchar(500) DEFAULT NULL,
  `asset_status` varchar(200) DEFAULT NULL,
  `asset_purchase_reference` varchar(200) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar_event_attendees`
--

DROP TABLE IF EXISTS `calendar_event_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_event_attendees` (
  `attendee_id` int(11) NOT NULL AUTO_INCREMENT,
  `attendee_name` varchar(200) DEFAULT NULL,
  `attendee_email` varchar(200) DEFAULT NULL,
  `attendee_invitation_status` tinyint(1) NOT NULL DEFAULT 0,
  `attendee_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attendee_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `attendee_archived_at` datetime DEFAULT NULL,
  `attendee_contact_id` int(11) NOT NULL DEFAULT 0,
  `attendee_event_id` int(11) NOT NULL,
  PRIMARY KEY (`attendee_id`),
  KEY `attendee_event_id` (`attendee_event_id`),
  CONSTRAINT `calendar_event_attendees_ibfk_1` FOREIGN KEY (`attendee_event_id`) REFERENCES `calendar_events` (`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar_events`
--

DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_events` (
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
  PRIMARY KEY (`event_id`),
  KEY `event_calendar_id` (`event_calendar_id`),
  CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`event_calendar_id`) REFERENCES `calendars` (`calendar_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendars`
--

DROP TABLE IF EXISTS `calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
-- Table structure for table `certificate_history`
--

DROP TABLE IF EXISTS `certificate_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_history` (
  `certificate_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `certificate_history_column` varchar(200) NOT NULL,
  `certificate_history_old_value` text NOT NULL,
  `certificate_history_new_value` text NOT NULL,
  `certificate_history_certificate_id` int(11) NOT NULL,
  `certificate_history_modified_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`certificate_history_id`),
  KEY `certificate_history_certificate_id` (`certificate_history_certificate_id`),
  CONSTRAINT `certificate_history_ibfk_1` FOREIGN KEY (`certificate_history_certificate_id`) REFERENCES `certificates` (`certificate_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL AUTO_INCREMENT,
  `certificate_name` varchar(200) NOT NULL,
  `certificate_description` mediumtext DEFAULT NULL,
  `certificate_domain` varchar(200) DEFAULT NULL,
  `certificate_issued_by` varchar(200) NOT NULL,
  `certificate_expire` date DEFAULT NULL,
  `certificate_public_key` mediumtext DEFAULT NULL,
  `certificate_notes` mediumtext DEFAULT NULL,
  `certificate_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `certificate_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `certificate_archived_at` datetime DEFAULT NULL,
  `certificate_accessed_at` datetime DEFAULT NULL,
  `certificate_domain_id` int(11) NOT NULL DEFAULT 0,
  `certificate_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`certificate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_notes`
--

DROP TABLE IF EXISTS `client_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_notes` (
  `client_note_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_note_type` varchar(200) NOT NULL,
  `client_note` text DEFAULT NULL,
  `client_note_created_by` int(11) NOT NULL,
  `client_note_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_note_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `client_note_archived_at` datetime DEFAULT NULL,
  `client_note_client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_note_id`),
  KEY `client_note_client_id` (`client_note_client_id`),
  CONSTRAINT `client_notes_ibfk_1` FOREIGN KEY (`client_note_client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_payment_provider`
--

DROP TABLE IF EXISTS `client_payment_provider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_payment_provider` (
  `client_id` int(11) NOT NULL,
  `payment_provider_id` int(11) NOT NULL,
  `payment_provider_client` varchar(200) NOT NULL,
  `client_payment_provider_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`client_id`,`payment_provider_id`),
  KEY `payment_provider_id` (`payment_provider_id`),
  CONSTRAINT `client_payment_provider_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `client_payment_provider_ibfk_2` FOREIGN KEY (`payment_provider_id`) REFERENCES `payment_providers` (`payment_provider_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_saved_payment_methods`
--

DROP TABLE IF EXISTS `client_saved_payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_saved_payment_methods` (
  `saved_payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `saved_payment_provider_method` varchar(200) NOT NULL,
  `saved_payment_description` varchar(200) DEFAULT NULL,
  `saved_payment_client_id` int(11) NOT NULL,
  `saved_payment_provider_id` int(11) NOT NULL,
  `saved_payment_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `saved_payment_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`saved_payment_id`),
  KEY `saved_payment_client_id` (`saved_payment_client_id`),
  KEY `saved_payment_provider_id` (`saved_payment_provider_id`),
  CONSTRAINT `client_saved_payment_methods_ibfk_1` FOREIGN KEY (`saved_payment_client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `client_saved_payment_methods_ibfk_2` FOREIGN KEY (`saved_payment_provider_id`) REFERENCES `payment_providers` (`payment_provider_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_tags`
--

DROP TABLE IF EXISTS `client_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_tags` (
  `client_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`client_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `client_tags_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `client_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(200) NOT NULL,
  `company_address` varchar(200) DEFAULT NULL,
  `company_city` varchar(200) DEFAULT NULL,
  `company_state` varchar(200) DEFAULT NULL,
  `company_zip` varchar(200) DEFAULT NULL,
  `company_country` varchar(200) DEFAULT NULL,
  `company_phone_country_code` varchar(10) DEFAULT NULL,
  `company_phone` varchar(200) DEFAULT NULL,
  `company_email` varchar(200) DEFAULT NULL,
  `company_website` varchar(200) DEFAULT NULL,
  `company_logo` varchar(250) DEFAULT NULL,
  `company_locale` varchar(200) DEFAULT NULL,
  `company_currency` varchar(200) DEFAULT 'USD',
  `company_tax_id` varchar(200) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_assets` (
  `contact_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`asset_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `contact_assets_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE,
  CONSTRAINT `contact_assets_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  CONSTRAINT `contact_assets_ibfk_3` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE,
  CONSTRAINT `contact_assets_ibfk_4` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_credentials`
--

DROP TABLE IF EXISTS `contact_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_credentials` (
  `contact_id` int(11) NOT NULL,
  `credential_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`credential_id`),
  KEY `credential_id` (`credential_id`),
  CONSTRAINT `contact_credentials_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE,
  CONSTRAINT `contact_credentials_ibfk_2` FOREIGN KEY (`credential_id`) REFERENCES `credentials` (`credential_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_documents`
--

DROP TABLE IF EXISTS `contact_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_documents` (
  `contact_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`document_id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `contact_documents_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE,
  CONSTRAINT `contact_documents_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_files`
--

DROP TABLE IF EXISTS `contact_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_files` (
  `contact_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `contact_files_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE,
  CONSTRAINT `contact_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_notes`
--

DROP TABLE IF EXISTS `contact_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_notes` (
  `contact_note_id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_note_type` varchar(200) NOT NULL,
  `contact_note` text DEFAULT NULL,
  `contact_note_created_by` int(11) NOT NULL,
  `contact_note_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `contact_note_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `contact_note_archived_at` datetime DEFAULT NULL,
  `contact_note_contact_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_note_id`),
  KEY `contact_note_contact_id` (`contact_note_contact_id`),
  CONSTRAINT `contact_notes_ibfk_1` FOREIGN KEY (`contact_note_contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_tags`
--

DROP TABLE IF EXISTS `contact_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_tags` (
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `contact_tags_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE,
  CONSTRAINT `contact_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `contact_id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_name` varchar(200) NOT NULL,
  `contact_title` varchar(200) DEFAULT NULL,
  `contact_email` varchar(200) DEFAULT NULL,
  `contact_phone_country_code` varchar(10) DEFAULT NULL,
  `contact_phone` varchar(200) DEFAULT NULL,
  `contact_extension` varchar(200) DEFAULT NULL,
  `contact_mobile_country_code` varchar(10) DEFAULT NULL,
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
-- Table structure for table `credential_tags`
--

DROP TABLE IF EXISTS `credential_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `credential_tags` (
  `credential_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`credential_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `credential_tags_ibfk_1` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE,
  CONSTRAINT `credential_tags_ibfk_2` FOREIGN KEY (`credential_id`) REFERENCES `credentials` (`credential_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credentials`
--

DROP TABLE IF EXISTS `credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `credentials` (
  `credential_id` int(11) NOT NULL AUTO_INCREMENT,
  `credential_name` varchar(200) NOT NULL,
  `credential_description` varchar(500) DEFAULT NULL,
  `credential_category` varchar(200) DEFAULT NULL,
  `credential_uri` varchar(500) DEFAULT NULL,
  `credential_uri_2` varchar(500) DEFAULT NULL,
  `credential_username` varchar(500) DEFAULT NULL,
  `credential_password` varbinary(200) DEFAULT NULL,
  `credential_otp_secret` varchar(200) DEFAULT NULL,
  `credential_note` text DEFAULT NULL,
  `credential_important` tinyint(1) NOT NULL DEFAULT 0,
  `credential_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `credential_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `credential_archived_at` datetime DEFAULT NULL,
  `credential_accessed_at` datetime DEFAULT NULL,
  `credential_password_changed_at` datetime DEFAULT current_timestamp(),
  `credential_folder_id` int(11) NOT NULL DEFAULT 0,
  `credential_contact_id` int(11) NOT NULL DEFAULT 0,
  `credential_asset_id` int(11) NOT NULL DEFAULT 0,
  `credential_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`credential_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credits`
--

DROP TABLE IF EXISTS `credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `credits` (
  `credit_id` int(11) NOT NULL AUTO_INCREMENT,
  `credit_amount` decimal(15,2) NOT NULL,
  `credit_type` enum('prepaid','manual','refund','promotion','usage') NOT NULL DEFAULT 'manual',
  `credit_note` text DEFAULT NULL,
  `credit_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `credit_created_by` int(11) NOT NULL,
  `credit_expire_at` date DEFAULT NULL,
  `credit_invoice_id` int(11) DEFAULT NULL,
  `credit_client_id` int(11) NOT NULL,
  PRIMARY KEY (`credit_id`),
  KEY `credit_client_id` (`credit_client_id`),
  KEY `credit_invoice_id` (`credit_invoice_id`),
  KEY `credit_created_at` (`credit_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `custom_fields`
--

DROP TABLE IF EXISTS `custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_fields` (
  `custom_field_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_field_table` varchar(255) NOT NULL,
  `custom_field_label` varchar(255) NOT NULL,
  `custom_field_type` varchar(255) NOT NULL DEFAULT 'text',
  `custom_field_location` int(11) NOT NULL DEFAULT 0,
  `custom_field_order` int(11) NOT NULL DEFAULT 999,
  PRIMARY KEY (`custom_field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `custom_links`
--

DROP TABLE IF EXISTS `custom_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_values` (
  `custom_value_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_value_value` mediumtext NOT NULL,
  `custom_value_field` int(11) NOT NULL,
  PRIMARY KEY (`custom_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `discount_codes`
--

DROP TABLE IF EXISTS `discount_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_codes` (
  `discount_code_id` int(11) NOT NULL AUTO_INCREMENT,
  `discount_code_description` varchar(250) DEFAULT NULL,
  `discount_code_amount` decimal(15,2) NOT NULL,
  `discount_code` varchar(200) NOT NULL,
  `discount_code_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `discount_code_created_by` int(11) NOT NULL,
  `discount_code_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `discount_code_archived_at` datetime DEFAULT NULL,
  `discount_code_expire_at` date DEFAULT NULL,
  PRIMARY KEY (`discount_code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_files`
--

DROP TABLE IF EXISTS `document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_files` (
  `document_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`document_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `document_files_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `document_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_templates`
--

DROP TABLE IF EXISTS `document_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_templates` (
  `document_template_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_template_name` varchar(200) NOT NULL,
  `document_template_description` text DEFAULT NULL,
  `document_template_content` longtext NOT NULL,
  `document_template_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `document_template_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `document_template_archived_at` datetime DEFAULT NULL,
  `document_template_created_by` int(11) NOT NULL DEFAULT 0,
  `document_template_updated_by` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`document_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_versions`
--

DROP TABLE IF EXISTS `document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_versions` (
  `document_version_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_version_name` varchar(200) NOT NULL,
  `document_version_description` text DEFAULT NULL,
  `document_version_content` longtext NOT NULL,
  `document_version_created_by` int(11) DEFAULT 0,
  `document_version_created_at` datetime NOT NULL,
  `document_version_document_id` int(11) NOT NULL,
  PRIMARY KEY (`document_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_name` varchar(200) NOT NULL,
  `document_description` text DEFAULT NULL,
  `document_content` longtext NOT NULL,
  `document_content_raw` longtext NOT NULL,
  `document_important` tinyint(1) NOT NULL DEFAULT 0,
  `document_client_visible` int(11) NOT NULL DEFAULT 1,
  `document_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `document_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `document_archived_at` datetime DEFAULT NULL,
  `document_accessed_at` datetime DEFAULT NULL,
  `document_folder_id` int(11) NOT NULL DEFAULT 0,
  `document_created_by` int(11) NOT NULL DEFAULT 0,
  `document_updated_by` int(11) NOT NULL DEFAULT 0,
  `document_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`document_id`),
  FULLTEXT KEY `document_content_raw` (`document_content_raw`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `domain_history`
--

DROP TABLE IF EXISTS `domain_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_history` (
  `domain_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_history_column` varchar(200) NOT NULL,
  `domain_history_old_value` text NOT NULL,
  `domain_history_new_value` text NOT NULL,
  `domain_history_domain_id` int(11) NOT NULL,
  `domain_history_modified_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`domain_history_id`),
  KEY `domain_history_domain_id` (`domain_history_domain_id`),
  CONSTRAINT `domain_history_ibfk_1` FOREIGN KEY (`domain_history_domain_id`) REFERENCES `domains` (`domain_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `domains`
--

DROP TABLE IF EXISTS `domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `file_id` int(11) NOT NULL AUTO_INCREMENT,
  `file_reference_name` varchar(200) DEFAULT NULL,
  `file_name` varchar(200) NOT NULL,
  `file_description` varchar(250) DEFAULT NULL,
  `file_ext` varchar(10) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `file_mime_type` varchar(100) DEFAULT NULL,
  `file_important` tinyint(1) NOT NULL DEFAULT 0,
  `file_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `file_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `file_archived_at` datetime DEFAULT NULL,
  `file_accessed_at` datetime DEFAULT NULL,
  `file_created_by` int(11) NOT NULL DEFAULT 0,
  `file_folder_id` int(11) NOT NULL DEFAULT 0,
  `file_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `folders`
--

DROP TABLE IF EXISTS `folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `folders` (
  `folder_id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_name` varchar(200) NOT NULL,
  `parent_folder` int(11) NOT NULL DEFAULT 0,
  `folder_location` int(11) DEFAULT 0,
  `folder_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `history`
--

DROP TABLE IF EXISTS `history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `history_status` varchar(200) NOT NULL,
  `history_description` varchar(200) NOT NULL,
  `history_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `history_invoice_id` int(11) NOT NULL DEFAULT 0,
  `history_recurring_invoice_id` int(11) NOT NULL DEFAULT 0,
  `history_quote_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `item_product_id` int(11) NOT NULL DEFAULT 0,
  `item_quote_id` int(11) NOT NULL DEFAULT 0,
  `item_recurring_invoice_id` int(11) NOT NULL DEFAULT 0,
  `item_invoice_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_prefix` varchar(200) DEFAULT NULL,
  `invoice_number` int(11) NOT NULL,
  `invoice_scope` varchar(255) DEFAULT NULL,
  `invoice_status` varchar(200) NOT NULL,
  `invoice_date` date NOT NULL,
  `invoice_due` date NOT NULL,
  `invoice_discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `invoice_credit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `invoice_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `invoice_currency_code` varchar(200) NOT NULL,
  `invoice_note` text DEFAULT NULL,
  `invoice_url_key` varchar(200) DEFAULT NULL,
  `invoice_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `invoice_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `invoice_archived_at` datetime DEFAULT NULL,
  `invoice_category_id` int(11) NOT NULL,
  `invoice_recurring_invoice_id` int(11) NOT NULL DEFAULT 0,
  `invoice_client_id` int(11) NOT NULL,
  PRIMARY KEY (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `location_tags`
--

DROP TABLE IF EXISTS `location_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `location_tags` (
  `location_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`location_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `location_tags_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE,
  CONSTRAINT `location_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL AUTO_INCREMENT,
  `location_name` varchar(200) NOT NULL,
  `location_description` text DEFAULT NULL,
  `location_country` varchar(200) DEFAULT NULL,
  `location_address` varchar(200) DEFAULT NULL,
  `location_city` varchar(200) DEFAULT NULL,
  `location_state` varchar(200) DEFAULT NULL,
  `location_zip` varchar(200) DEFAULT NULL,
  `location_phone_country_code` varchar(10) DEFAULT NULL,
  `location_phone` varchar(200) DEFAULT NULL,
  `location_phone_extension` varchar(10) DEFAULT NULL,
  `location_fax_country_code` varchar(10) DEFAULT NULL,
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
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `payment_method_id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_method_name` varchar(200) NOT NULL,
  `payment_method_description` varchar(250) DEFAULT NULL,
  `payment_method_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_method_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`payment_method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_providers`
--

DROP TABLE IF EXISTS `payment_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_providers` (
  `payment_provider_id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_provider_name` varchar(200) NOT NULL,
  `payment_provider_description` varchar(250) DEFAULT NULL,
  `payment_provider_public_key` varchar(250) DEFAULT NULL,
  `payment_provider_private_key` varchar(250) DEFAULT NULL,
  `payment_provider_threshold` decimal(15,2) DEFAULT NULL,
  `payment_provider_active` tinyint(1) NOT NULL DEFAULT 1,
  `payment_provider_account` int(11) NOT NULL,
  `payment_provider_expense_vendor` int(11) NOT NULL DEFAULT 0,
  `payment_provider_expense_category` int(11) NOT NULL DEFAULT 0,
  `payment_provider_expense_percentage_fee` decimal(4,4) DEFAULT NULL,
  `payment_provider_expense_flat_fee` decimal(15,2) DEFAULT NULL,
  `payment_provider_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_provider_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`payment_provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
-- Table structure for table `product_stock`
--

DROP TABLE IF EXISTS `product_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_stock` (
  `stock_id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_qty` int(11) NOT NULL,
  `stock_note` text DEFAULT NULL,
  `stock_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `stock_expense_id` int(11) DEFAULT NULL,
  `stock_item_id` int(11) DEFAULT NULL,
  `stock_product_id` int(11) NOT NULL,
  PRIMARY KEY (`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(200) NOT NULL,
  `product_type` enum('service','product') NOT NULL DEFAULT 'service',
  `product_description` text DEFAULT NULL,
  `product_code` varchar(200) DEFAULT NULL,
  `product_location` varchar(250) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_prefix` varchar(200) DEFAULT NULL,
  `project_number` int(11) NOT NULL DEFAULT 1,
  `project_name` varchar(255) NOT NULL,
  `project_description` mediumtext DEFAULT NULL,
  `project_due` date DEFAULT NULL,
  `project_manager` int(11) NOT NULL DEFAULT 0,
  `project_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `project_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `project_completed_at` datetime DEFAULT NULL,
  `project_archived_at` datetime DEFAULT NULL,
  `project_client_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quote_files`
--

DROP TABLE IF EXISTS `quote_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `quote_files` (
  `quote_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`quote_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `quote_files_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`quote_id`) ON DELETE CASCADE,
  CONSTRAINT `quote_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quotes`
--

DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurring_expenses`
--

DROP TABLE IF EXISTS `recurring_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_expenses` (
  `recurring_expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_expense_frequency` tinyint(1) NOT NULL,
  `recurring_expense_day` tinyint(4) DEFAULT NULL,
  `recurring_expense_month` tinyint(4) DEFAULT NULL,
  `recurring_expense_last_sent` date DEFAULT NULL,
  `recurring_expense_next_date` date NOT NULL,
  `recurring_expense_status` tinyint(1) NOT NULL DEFAULT 1,
  `recurring_expense_description` mediumtext DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurring_invoices`
--

DROP TABLE IF EXISTS `recurring_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_invoices` (
  `recurring_invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_invoice_prefix` varchar(200) DEFAULT NULL,
  `recurring_invoice_number` int(11) NOT NULL,
  `recurring_invoice_scope` varchar(255) DEFAULT NULL,
  `recurring_invoice_frequency` varchar(200) NOT NULL,
  `recurring_invoice_last_sent` date DEFAULT NULL,
  `recurring_invoice_next_date` date NOT NULL,
  `recurring_invoice_status` int(1) NOT NULL,
  `recurring_invoice_discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `recurring_invoice_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `recurring_invoice_currency_code` varchar(200) NOT NULL,
  `recurring_invoice_note` text DEFAULT NULL,
  `recurring_invoice_email_notify` tinyint(1) NOT NULL DEFAULT 1,
  `recurring_invoice_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recurring_invoice_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `recurring_invoice_archived_at` datetime DEFAULT NULL,
  `recurring_invoice_category_id` int(11) NOT NULL,
  `recurring_invoice_client_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurring_payments`
--

DROP TABLE IF EXISTS `recurring_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_payments` (
  `recurring_payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_payment_currency_code` varchar(10) NOT NULL,
  `recurring_payment_method` varchar(200) NOT NULL,
  `recurring_payment_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recurring_payment_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `recurring_payment_archived_at` datetime DEFAULT NULL,
  `recurring_payment_account_id` int(11) NOT NULL,
  `recurring_payment_recurring_expense_id` int(11) NOT NULL DEFAULT 0,
  `recurring_payment_recurring_invoice_id` int(11) NOT NULL,
  `recurring_payment_saved_payment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`recurring_payment_id`),
  KEY `fk_recurring_saved_payment` (`recurring_payment_saved_payment_id`),
  CONSTRAINT `fk_recurring_saved_payment` FOREIGN KEY (`recurring_payment_saved_payment_id`) REFERENCES `client_saved_payment_methods` (`saved_payment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurring_ticket_assets`
--

DROP TABLE IF EXISTS `recurring_ticket_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_ticket_assets` (
  `recurring_ticket_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_ticket_id`,`asset_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `recurring_ticket_assets_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  CONSTRAINT `recurring_ticket_assets_ibfk_2` FOREIGN KEY (`recurring_ticket_id`) REFERENCES `recurring_tickets` (`recurring_ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurring_tickets`
--

DROP TABLE IF EXISTS `recurring_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_tickets` (
  `recurring_ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_ticket_category` varchar(200) DEFAULT NULL,
  `recurring_ticket_subject` varchar(500) NOT NULL,
  `recurring_ticket_details` longtext NOT NULL,
  `recurring_ticket_priority` varchar(200) DEFAULT NULL,
  `recurring_ticket_frequency` varchar(10) NOT NULL,
  `recurring_ticket_billable` tinyint(1) NOT NULL DEFAULT 0,
  `recurring_ticket_start_date` date NOT NULL,
  `recurring_ticket_next_run` date NOT NULL,
  `recurring_ticket_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recurring_ticket_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `recurring_ticket_created_by` int(11) NOT NULL DEFAULT 0,
  `recurring_ticket_assigned_to` int(11) NOT NULL DEFAULT 0,
  `recurring_ticket_client_id` int(11) NOT NULL DEFAULT 0,
  `recurring_ticket_contact_id` int(11) NOT NULL DEFAULT 0,
  `recurring_ticket_asset_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`recurring_ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_assets`
--

DROP TABLE IF EXISTS `service_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_assets` (
  `service_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  KEY `service_id` (`service_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `service_assets_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `service_assets_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_certificates`
--

DROP TABLE IF EXISTS `service_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_certificates` (
  `service_id` int(11) NOT NULL,
  `certificate_id` int(11) NOT NULL,
  KEY `service_id` (`service_id`),
  KEY `certificate_id` (`certificate_id`),
  CONSTRAINT `service_certificates_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `service_certificates_ibfk_2` FOREIGN KEY (`certificate_id`) REFERENCES `certificates` (`certificate_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_contacts`
--

DROP TABLE IF EXISTS `service_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_contacts` (
  `service_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  KEY `service_id` (`service_id`),
  KEY `contact_id` (`contact_id`),
  CONSTRAINT `service_contacts_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `service_contacts_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_credentials`
--

DROP TABLE IF EXISTS `service_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_credentials` (
  `service_id` int(11) NOT NULL,
  `credential_id` int(11) NOT NULL,
  KEY `service_id` (`service_id`),
  KEY `credential_id` (`credential_id`),
  CONSTRAINT `service_credentials_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `service_credentials_ibfk_2` FOREIGN KEY (`credential_id`) REFERENCES `credentials` (`credential_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_documents`
--

DROP TABLE IF EXISTS `service_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_documents` (
  `service_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  KEY `service_id` (`service_id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `service_documents_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `service_documents_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_domains`
--

DROP TABLE IF EXISTS `service_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_domains` (
  `service_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  KEY `service_id` (`service_id`),
  KEY `domain_id` (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_vendors`
--

DROP TABLE IF EXISTS `service_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_vendors` (
  `service_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  KEY `service_id` (`service_id`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `service_vendors_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `service_vendors_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(200) NOT NULL,
  `service_description` varchar(200) NOT NULL,
  `service_category` varchar(20) NOT NULL,
  `service_importance` varchar(10) NOT NULL,
  `service_backup` varchar(200) DEFAULT NULL,
  `service_notes` mediumtext NOT NULL,
  `service_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `service_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `service_accessed_at` datetime DEFAULT NULL,
  `service_review_due` date DEFAULT NULL,
  `service_client_id` int(11) NOT NULL,
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `config_imap_provider` enum('standard_imap','google_oauth','microsoft_oauth') DEFAULT NULL,
  `config_mail_oauth_client_id` varchar(255) DEFAULT NULL,
  `config_mail_oauth_client_secret` varchar(255) DEFAULT NULL,
  `config_mail_oauth_tenant_id` varchar(255) DEFAULT NULL,
  `config_mail_oauth_refresh_token` text DEFAULT NULL,
  `config_mail_oauth_access_token` text DEFAULT NULL,
  `config_mail_oauth_access_token_expires_at` datetime DEFAULT NULL,
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
  `config_invoice_show_tax_id` tinyint(1) NOT NULL DEFAULT 0,
  `config_recurring_invoice_prefix` varchar(200) DEFAULT NULL,
  `config_recurring_invoice_next_number` int(11) NOT NULL DEFAULT 1,
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
  `config_ticket_timer_autostart` tinyint(1) NOT NULL DEFAULT 0,
  `config_enable_cron` tinyint(1) NOT NULL DEFAULT 0,
  `config_recurring_auto_send_invoice` tinyint(1) NOT NULL DEFAULT 1,
  `config_enable_alert_domain_expire` tinyint(1) NOT NULL DEFAULT 1,
  `config_send_invoice_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `config_invoice_overdue_reminders` varchar(200) DEFAULT NULL,
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
  `config_whitelabel_enabled` int(11) NOT NULL DEFAULT 0,
  `config_whitelabel_key` text DEFAULT NULL,
  `config_ticket_default_view` tinyint(1) NOT NULL DEFAULT 0,
  `config_ticket_ordering` tinyint(1) NOT NULL DEFAULT 0,
  `config_ticket_moving_columns` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shared_items`
--

DROP TABLE IF EXISTS `shared_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `software` (
  `software_id` int(11) NOT NULL AUTO_INCREMENT,
  `software_name` varchar(200) NOT NULL,
  `software_description` text DEFAULT NULL,
  `software_version` varchar(200) DEFAULT NULL,
  `software_type` varchar(200) NOT NULL,
  `software_license_type` varchar(200) DEFAULT NULL,
  `software_key` varchar(200) DEFAULT NULL,
  `software_seats` int(11) DEFAULT NULL,
  `software_purchase_reference` varchar(200) DEFAULT NULL,
  `software_purchase` date DEFAULT NULL,
  `software_expire` date DEFAULT NULL,
  `software_notes` text DEFAULT NULL,
  `software_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `software_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `software_archived_at` datetime DEFAULT NULL,
  `software_accessed_at` datetime DEFAULT NULL,
  `software_vendor_id` int(11) DEFAULT 0,
  `software_client_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_assets`
--

DROP TABLE IF EXISTS `software_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `software_assets` (
  `software_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`asset_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `software_assets_ibfk_1` FOREIGN KEY (`software_id`) REFERENCES `software` (`software_id`) ON DELETE CASCADE,
  CONSTRAINT `software_assets_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_contacts`
--

DROP TABLE IF EXISTS `software_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `software_contacts` (
  `software_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`contact_id`),
  KEY `contact_id` (`contact_id`),
  CONSTRAINT `software_contacts_ibfk_1` FOREIGN KEY (`software_id`) REFERENCES `software` (`software_id`) ON DELETE CASCADE,
  CONSTRAINT `software_contacts_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_credentials`
--

DROP TABLE IF EXISTS `software_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `software_credentials` (
  `software_id` int(11) NOT NULL,
  `credential_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`credential_id`),
  KEY `credential_id` (`credential_id`),
  CONSTRAINT `software_credentials_ibfk_1` FOREIGN KEY (`software_id`) REFERENCES `software` (`software_id`) ON DELETE CASCADE,
  CONSTRAINT `software_credentials_ibfk_2` FOREIGN KEY (`credential_id`) REFERENCES `credentials` (`credential_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_documents`
--

DROP TABLE IF EXISTS `software_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `software_documents` (
  `software_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`document_id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `software_documents_ibfk_1` FOREIGN KEY (`software_id`) REFERENCES `software` (`software_id`) ON DELETE CASCADE,
  CONSTRAINT `software_documents_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_files`
--

DROP TABLE IF EXISTS `software_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `software_files` (
  `software_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`software_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `software_files_ibfk_1` FOREIGN KEY (`software_id`) REFERENCES `software` (`software_id`) ON DELETE CASCADE,
  CONSTRAINT `software_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `software_templates`
--

DROP TABLE IF EXISTS `software_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `software_templates` (
  `software_template_id` int(11) NOT NULL AUTO_INCREMENT,
  `software_template_name` varchar(200) NOT NULL,
  `software_template_description` text DEFAULT NULL,
  `software_template_version` varchar(200) DEFAULT NULL,
  `software_template_type` varchar(200) NOT NULL,
  `software_template_license_type` varchar(200) DEFAULT NULL,
  `software_template_notes` text DEFAULT NULL,
  `software_template_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `software_template_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `software_template_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`software_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_templates`
--

DROP TABLE IF EXISTS `task_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `taxes`
--

DROP TABLE IF EXISTS `taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `taxes` (
  `tax_id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(200) NOT NULL,
  `tax_percent` float NOT NULL,
  `tax_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `tax_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `tax_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tax_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_assets`
--

DROP TABLE IF EXISTS `ticket_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_assets` (
  `ticket_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  PRIMARY KEY (`ticket_id`,`asset_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `ticket_assets_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_assets_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_attachments`
--

DROP TABLE IF EXISTS `ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_attachments` (
  `ticket_attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_attachment_name` varchar(255) NOT NULL,
  `ticket_attachment_reference_name` varchar(255) NOT NULL,
  `ticket_attachment_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_attachment_ticket_id` int(11) NOT NULL,
  `ticket_attachment_reply_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`ticket_attachment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_history`
--

DROP TABLE IF EXISTS `ticket_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_statuses` (
  `ticket_status_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_status_name` varchar(200) NOT NULL,
  `ticket_status_color` varchar(200) NOT NULL,
  `ticket_status_active` tinyint(1) NOT NULL DEFAULT 1,
  `ticket_status_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ticket_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_templates`
--

DROP TABLE IF EXISTS `ticket_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_watchers` (
  `watcher_id` int(11) NOT NULL AUTO_INCREMENT,
  `watcher_name` varchar(255) DEFAULT NULL,
  `watcher_email` varchar(255) NOT NULL,
  `watcher_ticket_id` int(11) NOT NULL,
  PRIMARY KEY (`watcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `ticket_due_at` datetime DEFAULT NULL,
  `ticket_resolved_at` datetime DEFAULT NULL,
  `ticket_archived_at` datetime DEFAULT NULL,
  `ticket_first_response_at` datetime DEFAULT NULL,
  `ticket_closed_at` datetime DEFAULT NULL,
  `ticket_created_by` int(11) NOT NULL,
  `ticket_assigned_to` int(11) NOT NULL DEFAULT 0,
  `ticket_closed_by` int(11) NOT NULL DEFAULT 0,
  `ticket_vendor_id` int(11) NOT NULL DEFAULT 0,
  `ticket_client_id` int(11) NOT NULL DEFAULT 0,
  `ticket_contact_id` int(11) NOT NULL DEFAULT 0,
  `ticket_location_id` int(11) NOT NULL DEFAULT 0,
  `ticket_asset_id` int(11) NOT NULL DEFAULT 0,
  `ticket_quote_id` int(11) NOT NULL DEFAULT 0,
  `ticket_invoice_id` int(11) NOT NULL DEFAULT 0,
  `ticket_project_id` int(11) NOT NULL DEFAULT 0,
  `ticket_recurring_ticket_id` int(11) DEFAULT 0,
  `ticket_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
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
-- Table structure for table `user_client_permissions`
--

DROP TABLE IF EXISTS `user_client_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_client_permissions` (
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
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(200) NOT NULL,
  `role_description` varchar(200) DEFAULT NULL,
  `role_type` tinyint(1) NOT NULL DEFAULT 1,
  `role_is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `role_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `role_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `role_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `user_config_force_mfa` tinyint(1) NOT NULL DEFAULT 0,
  `user_config_records_per_page` int(11) NOT NULL DEFAULT 10,
  `user_config_dashboard_financial_enable` tinyint(1) NOT NULL DEFAULT 0,
  `user_config_dashboard_technical_enable` tinyint(1) NOT NULL DEFAULT 0,
  `user_config_calendar_first_day` tinyint(1) NOT NULL DEFAULT 0,
  `user_config_signature` text DEFAULT NULL,
  `user_config_theme_dark` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `user_role_id` int(11) DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_credentials`
--

DROP TABLE IF EXISTS `vendor_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_credentials` (
  `vendor_id` int(11) NOT NULL,
  `credential_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`credential_id`),
  KEY `credential_id` (`credential_id`),
  CONSTRAINT `vendor_credentials_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_credentials_ibfk_2` FOREIGN KEY (`credential_id`) REFERENCES `credentials` (`credential_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_documents`
--

DROP TABLE IF EXISTS `vendor_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_documents` (
  `vendor_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`document_id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `vendor_documents_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_documents_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_files`
--

DROP TABLE IF EXISTS `vendor_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_files` (
  `vendor_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `vendor_files_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_templates`
--

DROP TABLE IF EXISTS `vendor_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_templates` (
  `vendor_template_id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_template_name` varchar(200) NOT NULL,
  `vendor_template_description` varchar(200) DEFAULT NULL,
  `vendor_template_contact_name` varchar(200) DEFAULT NULL,
  `vendor_template_phone_country_code` varchar(10) DEFAULT NULL,
  `vendor_template_phone` varchar(200) DEFAULT NULL,
  `vendor_template_extension` varchar(200) DEFAULT NULL,
  `vendor_template_email` varchar(200) DEFAULT NULL,
  `vendor_template_website` varchar(200) DEFAULT NULL,
  `vendor_template_hours` varchar(200) DEFAULT NULL,
  `vendor_template_sla` varchar(200) DEFAULT NULL,
  `vendor_template_code` varchar(200) DEFAULT NULL,
  `vendor_template_account_number` varchar(200) DEFAULT NULL,
  `vendor_template_notes` text DEFAULT NULL,
  `vendor_template_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `vendor_template_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `vendor_template_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`vendor_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendors` (
  `vendor_id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_name` varchar(200) NOT NULL,
  `vendor_description` varchar(200) DEFAULT NULL,
  `vendor_contact_name` varchar(200) DEFAULT NULL,
  `vendor_phone_country_code` varchar(10) DEFAULT NULL,
  `vendor_phone` varchar(200) DEFAULT NULL,
  `vendor_extension` varchar(200) DEFAULT NULL,
  `vendor_email` varchar(200) DEFAULT NULL,
  `vendor_website` varchar(200) DEFAULT NULL,
  `vendor_hours` varchar(200) DEFAULT NULL,
  `vendor_sla` varchar(200) DEFAULT NULL,
  `vendor_code` varchar(200) DEFAULT NULL,
  `vendor_account_number` varchar(200) DEFAULT NULL,
  `vendor_notes` text DEFAULT NULL,
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

-- Dump completed on 2025-09-12 15:55:31
