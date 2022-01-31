<div id="top"></div>

<!-- PROJECT SHIELDS -->
[![Contributors][contributors-shield]][contributors-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![Commits][commit-shield]][commit-url]
[![GPL License][license-shield]][license-url]

<!-- PROJECT LOGO -->
<div align="center">
  <!-- <a href="https://github.com/johnnyq/itflow">
    <img src="images/logo.png" alt="Logo" width="80" height="80">
  </a> -->

  <h3 align="center">ITFlow</h3>

  <p align="center">
    IT Documentation, Accounting and Invoicing System for Small MSPs.
    <br />
    <br />
    <a href="https://demo.itflow.org"><strong>View demo</strong></a>
    <br />
    Username: <b>demo@demo</b> | Password: <b>demo</b>
    <br />
    <br />
    <a href="https://github.com/johnnyq/itflow">Docs</a>
    ·
    <a href="https://forum.itflow.org/">Forum</a>
    ·
    <a href="https://github.com/johnnyq/itflow/issues">Report Bug</a>
    ·
    <a href="https://forum.itflow.org/t/features">Request Feature</a>
  </p>
</div>

<!-- ABOUT THE PROJECT -->
## About

[![ITFlow][product-screenshot]]()

<b>ITFlow is a free & open-source solution for IT service management, documentation, and accounting.</b>

### The Problem
- You're a busy MSP with 101 things to do. 
- Information about your clients is unorganised and unstructured: scattered in random tickets or folders - when you do eventually find it, it's out of date. 
- For some tickets, you spend longer looking for the relevant documentation than actually working the ticket. 
- On top of the technical day to day, you also have to take care of the financial side of the business - consistent pricing, invoicing/billing and accounting. 

### The Solution: ITFlow
- ITFlow consolidates common MSP needs (ticketing, wiki/docs, CMDB and accounting) into one system to help you do what you do best - IT.

### In Beta
* This project is still in early beta and is considered a **work in progress**.  Many changes are being performed and may cause breakage upon updates. 
* We strongly recommend against storing confidential information like passwords in ITFlow at this time.
* We are hoping to have a stable 1.0 release by July 2022.

<!-- BUILT WITH -->
### Built With

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

<!-- GETTING STARTED -->
## Getting Started

ITFlow is self-hosted. There is a full installation guide in the [docs](https://itflow.org/docs.php?doc_id=1), but the main steps are:

### Prerequisites

* Git
  ```sh
  sudo apt install git
  ```
* Apache
  ```sh
  sudo apt install apache2
  ```
* PHP
  ```sh
  sudo apt install php libapache2-mod-php
  ```
* MariaDB
  ```sh
  sudo apt install mariadb-server
  ```

### Installation

1. Login to your server, change directory to your web root
2. Clone the repo
   ```sh
   git clone https://github.com/johnnyq/itflow.git .
   ```
3. Create a MariaDB Database
4. Point your browser to your HTTPS web server to begin setup

<!-- FEATURES -->
## Features
* Client Documentation
  * <b>Contacts</b> - Keep track of important individuals
  * <b>Locations</b> (Head Quarters, Satellite locations)
  * <b>Vendors</b> (ISP, WebHost, MailHost, Software Company, VOIP company, etc.)
  * <b>Assets</b> (Laptop, Workstations, Routers, Switches, Access Points, phones, etc.)
  * <b>Password Manager</b> (AES Encrypted in database)
  * <b>Domain Names & SSL Certificates</b>
  * <b>Software</b> (Manage Applications Licenses, Renewals etc.)
  * <b>Networks</b> 
  * <b>Files</b> (PDF Contracts, Manuals, Firewall Backup Configs, etc.)
  * <b>Documents</b> (Tech Docs, How-tos, Processes, Procedures, Notes, etc.)
  * <b>Services</b> (Relate all of the above together, e.g. Active Directory, a web app, etc.)
  * <b>Tickets</b>
  * Single Downloadable IT Documentation for a client
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


## API
* Caller ID lookup (Great for integrating with your phone system like FreePBX, and having your VOIP phone return the client's name thats calling in) - /api.php?api_key=[API_KEY]&cid=[PHONE_NUMBER] - Returns a name
* XML Phonebook Download - /api.php?api_key=[API_KEY]&phonebook 
* Client Email (great for mailing lists) - /api.php?api_key=[API_KEY]&client_emails - Returns Client Name - Email Address
* Account Balance for Client (can be integrated into multiple places for example in FreePBX Press 3 to check account balance, please enter your client ID your balance is) - /api.php?api_key=[API_KEY]&client_id=[CLIENT_ID] - Returns Account Balance
* Add new asset for a client - /api.php?api_key=[API_KEY]&client_id=ClientID&add_asset=Name&type=[Desktop|Laptop|Server]&make=Make&model=Model&serial=Serial&os=OS
    * Required: api_key, client_id, add_asset (name)
* NOTE: [API_KEY] - is auto generated when a company is created and shows up in General Settings, this can also be changed manually.



<!-- ROADMAP -->
## Roadmap / Future to-do

* MeshCentral / TacticalRMM (Export Assets Info to ITFlow, Exports common software applications to Software)
* CalDAV to integrate with 3rd party calendars
* CardDAV to integrate with 3rd party Address books
* Stripe Integration for online payments
* Toast Alerts with recent caller that matches caller ID in database which allows you to click on the toast alerts and bring up the clients account right away.
* FIDO2 WebAuthn Support for passwordless auth (TPM Fingerprint), (USB Hardware keys such as Yubikey)

See the [forum](https://forum.itflow.org) and the [open issues](https://github.com/johnnyq/itflow/issues) for a full list of proposed features & known issues.


<!-- CONTRIBUTING -->
## Support & Contributions

### Forum
For assistance using ITFlow, feature requests, and general ideas/discussions please use the community <a href="https://forum.itflow.org">forum</a>.
For bugs, please raise an [issue](https://github.com/johnnyq/itflow/issues).

### Contributing
If you are able to make a contribution that would make ITFlow better, please fork the repo and create a pull request.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature`)
3. Commit your Changes (`git commit -m 'Add some'`)
4. Push to the Branch (`git push origin feature`)
5. Open a Pull Request

#### Contributors
<a href="https://github.com/johnnyq/itflow/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=johnnyq/itflow" />
</a>

<!-- LICENSE -->
## License

ITFlow is distributed under the GPL License in the hope that it will be useful, but WITHOUT ANY WARRANTY.  See `LICENSE.txt` for details.


<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[contributors-shield]: https://img.shields.io/github/contributors/johnnyq/itflow.svg?style=for-the-badge
[contributors-url]: https://github.com/johnnyq/itflow/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/johnnyq/itflow.svg?style=for-the-badge
[forks-url]: https://github.com/johnnyq/itflow/network/members
[stars-shield]: https://img.shields.io/github/stars/johnnyq/itflow.svg?style=for-the-badge
[stars-url]: https://github.com/johnnyq/itflow/stargazers
[issues-shield]: https://img.shields.io/github/issues/johnnyq/itflow.svg?style=for-the-badge
[issues-url]: https://github.com/johnnyq/itflow/issues
[license-shield]: https://img.shields.io/github/license/johnnyq/itflow.svg?style=for-the-badge
[license-url]: https://github.com/johnnyq/itflow/blob/master/LICENSE.txt
[commit-shield]: https://img.shields.io/github/last-commit/johnnyq/itflow?style=for-the-badge
[commit-url]: https://github.com/johnnyq/itflow/commits/master
[product-screenshot]: .github/dash.png

<!-- https://github.com/othneildrew/Best-README-Template -->
