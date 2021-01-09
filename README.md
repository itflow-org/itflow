## IT Documentation Accounting and Invoicing System for Small MSPs.

### Online Demo

* https://demo.itflow.org
* USERNAME: demo@demo
* PASSWORD: demo

### Features
* Client Documentation
  * Contacts
  * Locations (Head Quarters, Satellite locaitons)
  * Vendors (ISP, WebHost, MailHost etc)
  * Assets (Laptop, Workstations, Routers, Switches, Access Points, phones, etc)
  * Password Manager (AES Encrypted in DB)
  * Domain Names  
  * Applications Licenses
  * Networks
  * Files
  * Notes
  * Tickets
  * Single Downloadable PDF of all documentation for a client to give them.
* Client Portal
  * Invoice and Payment information
  * More to come soon...
* Invoicing
  * Automatically Emails Past Due Invoices to clients
  * Auto Email Receipts upon receiving payments
  * Recurring Invoices
* Quotes
  * Automated customer approval process using email
  * Turn Quotes into invoices with a signle click
* Accounting
  * Expense Tracking
  * Profit and Loss Reports
  * Income/Expense Summaries
  * Travel Mileage Tracking
  * Account Transfers / Deposits
  * Accounts
* Alerting/Notifications
  * Low Account Balances
  * Domains to expire
  * Password reset reminder for customers
  * Past Due Invoices
  * Software License Expiring
* Calendar
  * Schedule Jobs
  * Overview of Invoices, Domains that are expiring, etc
  * Schedule Events
  * Automatic Email Reminders of upcoming calendar events to customers
* Dashboard
  * Gives a clear overview of your business financials using graphs

* API
  * XML Phonebook download for VOIP Phones
  * FreePBX Integrated called ID (When call comes in it queries the Database and displays the company name on your caller ID as well as alerts you in the CRM)
  * Pull Emails for Mailing list Integration
  * Check account Balances using FreePBX IVR

* Multi-Tenant - One Instance Multiple Companies and Users
* Audit Logging - Logs actions of users on the system
* 2FA Login Support (TOTP)



### Installation Instructions

* Change directory to your webroot
* git clone https://github.com/johnnyq/itflow.git .
* Create a MySQL database
* Point your browser to your web Server
* Go through the Setup Process
* Login
* Start inputing some data

#### Requirements
* Webserver (Apache, NGINX)
* PHP7+
* MySQL or MariaDB

### Technologies Used
* PHP/MySQL
* AdminLTE3
* fontawesome
* chart.js
* moments.js
* Jquery
* PHPmailer
* mPDF
* FullCalendar.io
* Select2
* Date Range Picker
* Bootstrap Typeahead
* SummerNote

### API Calls
* Caller ID lookup (Great for integrating with your phone system like FreePBX, and having your VOIP phone return the client thats calling) - /api.php?api_key=[API_KEY]&cid=[PHONE_NUMBER] - Returns a name
* XML Phonebook Download (Great for using with VOIP Phones so phpnes have an up to date directory) - /api.php?api_key=[API_KEY]&phonebook 
* Client Email (great for mailing lists) - /api.php?api_key=[API_KEY]&client_emails - Returns Client Name - Email Address
* Account Balance for Client (can be integrated into multiple places for example in FreePBX Press 3 to check account balance, please enter your client ID your blanace is) - /api.php?api_key=[API_KEY]&client_id=[CLIENT_ID] - Returns Account Balance
NOTE: [API_KEY] - is auto generated when a company is created and shows up in General Settings, this can also be changed manually.

### Future Todo
* MeshCentral / TacticalRMM Integation to assign devices to assets and easily access remote desktop within the app, as well as pull vital information such as asset make, model, serial, hostname, Operating System, 
* CalDAV to integrate with 3rd party calendars
* CardDAV to integrate with 3rd party Address books
* Stripe Integration for online payments
* Toast Alerts with recent caller that matches caller ID in database which allows you to click on the toast alerts and bring up the clients account right away.
* Built-in mailing list used for alerts and marketing
* WebAuthn Support for passwordless auth (TPM Fingerprint), (USB Hardware keys such as Yubikey)
