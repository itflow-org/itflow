## IT Documentation, Accounting and Invoicing System for Small MSPs.

### Online Demo

* https://demo.itflow.org
* USERNAME: demo@demo
* PASSWORD: demo

### Notice
* This project is still in early Beta stages and is considered a **work in progress**.  Many changes are being performed and may cause breakage upon updates. 
* We strongly recommend against storing confidential information like passwords in ITFlow at this time.
* We are hoping to have a stable 1.0 release by July 2022.

### Features
* Client Documentation
  * Contacts - Keep track of important individuals
  * Locations (Head Quarters, Satellite locations)
  * Vendors (ISP, WebHost, MailHost, Software Company, VOIP company, etc.)
  * Assets (Laptop, Workstations, Routers, Switches, Access Points, phones, etc.)
  * Password Manager (AES Encrypted in database)
  * Domain Names & SSL Certificates
  * Software (Manage Applications Licenses, Renewals etc.)
  * Networks
  * Files (PDF Contracts, Manuals, Firewall Backup Configs, etc.)
  * Documents (Tech Docs, How-tos, Processes, Procedures, Notes, etc.)
  * Tickets
  * Client Documentation (Single Downloadable IT Documentation for a client)
* Client Portal
  * Invoice, Quotes and Payment information
  * More to come soon...
* Invoicing
  * Automatically Emails Past Due Invoices to clients
  * Automatically Email Receipts upon marking invoices paid
  * Automatic Recurring Invoices
* Quotes
  * Automated customer approval process using a link that is sent via email to the primary contact
  * One Click turn Quotes into Invoices
* Accounting
  * Expense Tracking (Track internal business expenses such as Office Supplies, Professional Services, Equipment, etc.)
  * Profit and Loss Reports
  * Income/Expense Summaries
  * Travel Mileage Tracking
  * Accounts (Manage several accounts including cash on hand, bank accounts, etc.)
  * Account Transfers (Keep track of money transfers from account to account including deposits)
* Alerting/Notifications
  * Low Account Balances
  * Domains to expire
  * Password reset reminder for customers
  * Past Due Invoices
  * Software License Expiration
* Calendar
  * Schedule Jobs
  * Overview of Invoices, Domains, Asset Warranty Expiry, etc.
  * Schedule Events
  * Automatic Email Reminders of upcoming calendar events to customers
* Dashboard
  * Overview of business financials
* Mailing List - Notify users of upcoming change controls, marketing, etc.

* API
  * XML Phonebook download for VOIP Phones
  * FreePBX Integrated called ID (When call comes in it queries the Database and displays the company name on your caller ID as well as alerts you in the CRM)
  * Pull Emails for Mailing list Integration
  * Check account Balances using FreePBX IVR

* Multi-Tenant - One Instance Multiple Companies and Users
* Audit Logging - Logs detailed actions of users and events
* Permissions / Roles
* 2FA Login Support (TOTP)


### Installation Instructions

* Change directory to your webroot
* git clone https://github.com/johnnyq/itflow.git .
* Create a MariaDB database (Note MySQL is broken)
* Point your browser to your Web Server
* Go through the Setup Process
* Login
* Start inputting some data

#### Requirements
* Webserver (Apache, NGINX)
* PHP7+
* MariaDB (MySQL is broken)

### Technologies Used
* Backend / PHP libs
  * PHP
  * MariaDB
  * PHPMailer

* CSS
  * Bootstrap
  * AdminLTE
  * fontawesome

* JS Libraries
  * chart.js
  * moments.js
  * jQuery
  * pdfmake
  * Select2
  * SummerNote
  * FullCalendar.io

### API Calls
* Caller ID lookup (Great for integrating with your phone system like FreePBX, and having your VOIP phone return the client's name thats calling in) - /api.php?api_key=[API_KEY]&cid=[PHONE_NUMBER] - Returns a name
* XML Phonebook Download - /api.php?api_key=[API_KEY]&phonebook 
* Client Email (great for mailing lists) - /api.php?api_key=[API_KEY]&client_emails - Returns Client Name - Email Address
* Account Balance for Client (can be integrated into multiple places for example in FreePBX Press 3 to check account balance, please enter your client ID your balance is) - /api.php?api_key=[API_KEY]&client_id=[CLIENT_ID] - Returns Account Balance
NOTE: [API_KEY] - is auto generated when a company is created and shows up in General Settings, this can also be changed manually.

### Future Todo
* MeshCentral / TacticalRMM (Export Assets Info to ITFlow, Exports common software applications to Software)
* CalDAV to integrate with 3rd party calendars
* CardDAV to integrate with 3rd party Address books
* Stripe Integration for online payments
* Toast Alerts with recent caller that matches caller ID in database which allows you to click on the toast alerts and bring up the clients account right away.
* FIDO2 WebAuthn Support for passwordless auth (TPM Fingerprint), (USB Hardware keys such as Yubikey)
