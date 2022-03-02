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

[![ITFlow][product-screenshot]](https://itflow.org)

<b>ITFlow is a free & open-source solution for IT service management, documentation, and accounting.</b>

### The Problem
- You're a busy MSP with 101 things to do. 
- Information about your clients is unorganised and unstructured: scattered in random tickets or folders - when you do eventually find it, it's out of date. 
- For some tickets, you spend longer looking for the relevant documentation than actually working the ticket. 
- On top of the technical day to day, you also have to take care of the financial side of the business - consistent pricing, quotes/invoicing, and accounting. 

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
## Getting Started / Installation

ITFlow is self-hosted. There is a full installation guide in the [docs](https://itflow.org/docs.php?doc_id=1), but the main steps are:

1. Install a a LAMP stack (Linux, Apache, MariaDB, PHP)
   ```sh
   sudo apt install git apache2 php libapache2-mod-php php-intl php-mysqli mariadb-server
   ```  
2. Clone the repo
   ```sh
   git clone https://github.com/johnnyq/itflow.git /var/www/html
   ```
3. Create a MariaDB Database
4. Point your browser to your HTTPS web server to begin setup

<!-- FEATURES -->
## Key Features
* Client documentation - assets, contacts, domains, files, passwords, and more 
* Accounting/Billing - finance dashboard, quotes, invoices, accounting, expenses, etc
* Client Portal - self service quotes/invoices management for clients (more features to come soon!)
* Alerting - account balance, invoices, domain/SSL renewals
  
<!-- ROADMAP -->
## Roadmap / Future to-do

* MeshCentral / TacticalRMM (Export Assets Info to ITFlow, Exports common software applications to Software)
* CalDAV to integrate with 3rd party calendars
* CardDAV to integrate with 3rd party Address books
* Stripe Integration for online payments
* Toast Alerts with recent caller that matches caller ID in database which allows you to click on the toast alerts and bring up the clients account right away.
* FIDO2 WebAuthn Support for passwordless auth (TPM Fingerprint), (USB Hardware keys such as Yubikey)

See the [forum](https://forum.itflow.org/d/11-road-map) and the [open issues](https://github.com/johnnyq/itflow/issues) for a full list of proposed features & known issues.


<!-- CONTRIBUTING -->
## Support & Contributions

### Forum
For assistance using ITFlow, feature requests, and general ideas/discussions please use the community [forum](https://forum.itflow.org).
For bugs, please raise an [issue](https://github.com/johnnyq/itflow/issues).

### Contributing
If you are able to make a contribution that would make ITFlow better, please fork the repo and create a pull request. Please make sure you're following our [code standards](https://itflow.org/docs.php?doc=coding-standards). 
For large changes/new features, please discuss the issue with other contributors first.

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
