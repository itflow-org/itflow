## IT Documentation Accounting and Invoicing System for Small Managed IT Companies

### Features
* Manage Clients
  * Contacts
  * Locations
  * Vendors
  * Assets
  * Password Manager
  * Domain Names 
  * Applications
  * Networks
  * Files
  * Documentation
  * Tickets
* Invoicing
  * Automatically Emails Past Due Invoices to clients
  * Auto Email Receipts upon receiving payments
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
  * Overview of Invoices Domains that are expiring
  * Schedule Events
  * Automatic Email Reminders of upcomming calendar events to customers
* Dashboard
  * Gives a clear overview of your business financials using graphs and such

* API
  * XML Phonebook download for VOIP Phones
  * FreePBX Integrated called ID (When call comes in it queries the Database and displays the company name on your caller ID as well as alerts you in the CRM)
  * Pull Email Lists
  * Check account Balances using FreePBX IVR

* Multi-Tenant - One Instance Multiple Companies and Users
* Audit Logging - Logs actions of users on the system
* 2 Factor Authentication (TOTP)



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

### API Calls
* Caller ID lookup (Returns a Name) - /api.php?api_key=[API_KEY]&cid=[PHONE_NUMBER]
* XML Phonebook Download - /api.php?api_key=[API_KEY]&phonebook
* Client Email (Returns Client Name - Email Address) - /api.php?api_key=[API_KEY]&client_emails
* Account Balance for Client (Returns Account Balance) - /api.php?api_key=[API_KEY]&client_id=[CLIENT_ID]
NOTE: [API_KEY] - is auto generated when a company is created and shows up in General Settings, this can also be changed manually.

### Future Todo
* MeshCentral Integation to assign devices to assets and easily access remote desktop within the app, as well as pull vital information such as Operating System, 
* HestiaCP Integration for intergrating domains and webclients
* CalDAV to integrate with 3rd party calendars
* CardDAV to integrate with 3rd party Address books
* Unifi and UNMS integration
* Stripe Integration for online payments
* Client Portal
* Toast Alerts with recent caller that matches caller ID in database which allows you to click on the toast alerts and bring up the clients account right away.
* Built-in mailing list used for alerts and marketing