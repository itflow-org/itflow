-- MySQL dump 10.16  Distrib 10.1.38-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: pittpc
-- ------------------------------------------------------
-- Server version	10.1.38-MariaDB-0ubuntu0.18.04.1

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
  `opening_balance` decimal(15,2) NOT NULL,
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
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
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_applications`
--

DROP TABLE IF EXISTS `client_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_applications` (
  `client_application_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_application_name` varchar(200) NOT NULL,
  `client_application_type` varchar(200) NOT NULL,
  `client_application_license` varchar(200) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_assets`
--

DROP TABLE IF EXISTS `client_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_assets` (
  `client_asset_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_asset_type` varchar(200) NOT NULL,
  `client_asset_name` varchar(200) NOT NULL,
  `client_asset_make` varchar(200) NOT NULL,
  `client_asset_model` varchar(200) NOT NULL,
  `client_asset_serial` varchar(200) NOT NULL,
  `client_asset_note` varchar(200) NOT NULL,
  `client_password_id` int(11) NOT NULL,
  `client_location_id` int(11) NOT NULL,
  `client_contact_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_asset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_contacts`
--

DROP TABLE IF EXISTS `client_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_contacts` (
  `client_contact_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_contact_name` varchar(200) NOT NULL,
  `client_contact_title` varchar(200) NOT NULL,
  `client_contact_email` varchar(200) NOT NULL,
  `client_contact_phone` varchar(200) NOT NULL,
  `client_contact_primary` tinyint(1) NOT NULL,
  `client_contact_recieve_invoices` tinyint(1) NOT NULL,
  `location_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_contact_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_domains`
--

DROP TABLE IF EXISTS `client_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_domains` (
  `client_domain_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_domain_name` varchar(200) NOT NULL,
  `client_domain_registrar` varchar(200) NOT NULL,
  `client_domain_expire` date NOT NULL,
  `client_domain_server` varchar(200) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_domain_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_locations`
--

DROP TABLE IF EXISTS `client_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_locations` (
  `client_location_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_location_name` varchar(200) NOT NULL,
  `client_location_address` varchar(200) NOT NULL,
  `client_location_city` varchar(200) NOT NULL,
  `client_location_state` varchar(200) NOT NULL,
  `client_location_zip` varchar(200) NOT NULL,
  `client_location_phone` varchar(200) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_logins`
--

DROP TABLE IF EXISTS `client_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_logins` (
  `client_login_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_login_description` varchar(200) NOT NULL,
  `client_login_username` varchar(200) NOT NULL,
  `client_login_password` varchar(200) NOT NULL,
  `client_login_note` text NOT NULL,
  `client_vendor_id` int(11) NOT NULL,
  `client_asset_id` int(11) NOT NULL,
  `client_application_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_login_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_networks`
--

DROP TABLE IF EXISTS `client_networks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_networks` (
  `client_network_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_network_name` varchar(200) NOT NULL,
  `client_network` varchar(200) NOT NULL,
  `client_network_gateway` varchar(200) NOT NULL,
  `client_network_dhcp_range` varchar(200) NOT NULL,
  `client_network_notes` text NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_network_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_notes`
--

DROP TABLE IF EXISTS `client_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_notes` (
  `client_note_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_note_subject` varchar(200) NOT NULL,
  `client_note_body` text NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_vendors`
--

DROP TABLE IF EXISTS `client_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_vendors` (
  `client_vendor_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_vendor_name` varchar(200) NOT NULL,
  `client_vendor_description` varchar(200) NOT NULL,
  `client_vendor_account_number` varchar(200) NOT NULL,
  `client_vendor_note` text NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`client_vendor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;
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
  `client_address` varchar(200) NOT NULL,
  `client_city` varchar(200) NOT NULL,
  `client_state` varchar(200) NOT NULL,
  `client_zip` varchar(200) NOT NULL,
  `client_phone` varchar(200) NOT NULL,
  `client_email` varchar(200) NOT NULL,
  `client_website` varchar(200) NOT NULL,
  `client_net_terms` int(10) NOT NULL,
  `client_created_at` int(11) NOT NULL,
  `client_updated_at` int(11) NOT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_description` text NOT NULL,
  `expense_amount` decimal(15,2) NOT NULL,
  `expense_date` date NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  PRIMARY KEY (`expense_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_history`
--

DROP TABLE IF EXISTS `invoice_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_history` (
  `invoice_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_history_date` date NOT NULL,
  `invoice_history_status` varchar(200) NOT NULL,
  `invoice_history_description` varchar(200) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  PRIMARY KEY (`invoice_history_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `invoice_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_item_name` varchar(200) NOT NULL,
  `invoice_item_description` text NOT NULL,
  `invoice_item_quantity` decimal(15,2) NOT NULL,
  `invoice_item_price` decimal(15,2) NOT NULL,
  `invoice_item_subtotal` decimal(15,2) NOT NULL,
  `invoice_item_tax` decimal(15,2) NOT NULL,
  `invoice_item_total` decimal(15,2) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  PRIMARY KEY (`invoice_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` int(11) NOT NULL,
  `invoice_status` varchar(200) NOT NULL,
  `invoice_date` date NOT NULL,
  `invoice_due` date NOT NULL,
  `invoice_amount` decimal(15,2) NOT NULL,
  `invoice_note` text NOT NULL,
  `category_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  PRIMARY KEY (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mileage`
--

DROP TABLE IF EXISTS `mileage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mileage` (
  `mileage_id` int(11) NOT NULL AUTO_INCREMENT,
  `mileage_date` date NOT NULL,
  `mileage_purpose` varchar(200) NOT NULL,
  `mileage_starting_location` varchar(200) NOT NULL,
  `mileage_destination` varchar(200) NOT NULL,
  `mileage_start_odometer` int(11) DEFAULT NULL,
  `mileage_end_odmeter` int(11) DEFAULT NULL,
  `mileage_miles` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`mileage_id`)
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
  `payment_method` varchar(200) NOT NULL,
  `payment_reference` varchar(200) NOT NULL,
  `account_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transfers` (
  `transfer_id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_amount` decimal(15,2) NOT NULL,
  `transfer_date` date NOT NULL,
  `transfer_account_from` int(11) NOT NULL,
  `transfer_account_to` int(11) NOT NULL,
  PRIMARY KEY (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(200) NOT NULL,
  `avatar` varchar(200) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
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
  `vendor_description` varchar(200) NOT NULL,
  `vendor_account_number` varchar(200) NOT NULL,
  `vendor_created_at` int(11) NOT NULL,
  `vendor_updated_at` int(11) NOT NULL,
  PRIMARY KEY (`vendor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-04-07 12:36:00
