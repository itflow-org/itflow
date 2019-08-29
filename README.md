## IT Documentation Accounting and Invoicing System for Small Managed IT Companies

### Features
* Manage Clients
  * Contacts
  * Locations
  * Vendors
  * Assets
  * Licenses
  * Logins
  * Domains
  * Apps
  * Networks
  * Files
  * Documentation
* Invoicing
  * Email Past Due Reminders
  * Auto Email Receipts upon payments
  * Recurring Invoices
* Quotes / Estimates
  * Automated customer approval
  * Turn Quotes into invoices with a click
* Vendors
* Accounting
  * Track Business Expenses
  * P&L Reports
  * Income/Expense Summary
  * Mileage Tracking
  * Account Transfers
  * Bank Accounts
* Alerting/Notifications
  * Low Account Balances
  * Domains to expire
  * Password reset reminder for customers
  * Past Due Invoices
  * Software License Expiring
* Calendar Integration
  * Schedule Jobs
  * Overview of Invoices Domains
  * Schedule Events
* Dashboard
  * Gives a clear overview of your business

* API
  * XML Phonebook download for VOIP Phones
  * FreePBX Integrated called ID (When call comes in it queries the Database and displays the company name on your caller ID)
  * Pull Email Lists
  * Check account Balances using FreePBX IVR

### Installation Instructions

* Clone this repo
* Create a Mysql database
* Point your browser to the URL where you downloaded the crm
* Go through the Setup Process
* Login
* Start inputting some data

#### Requirements
* Webserver (Apache, NGINX)
* PHP7+
* Mysql or MariaDB

### Technologies Used
* PHP/MySQL
* SB Admin Bootstrap CSS Framework
* fontawesome
* chart.js
* moments.js
* Jquery
* PHPmailer
* mPDF
* FullCalendar.io
* bootstrap-select
* Date Range Picker
* Bootstrap Typeahead
* EasyMDE forked from SimpleMDE
* parsedown

### Future Todo
* MeshCentral Integation to assign devices and easily access remote desktop within the app
* HestiaCP Integration for intergrating domains and webclients
* CalDAV to integrate with 3rd party calendars
* CardDAV to integrate with 3rd party Address books
* Unifi and UNMS integration
* Stripe Integration for online payments