# Changelog

All notable changes to ITFlow will be documented in this file.

## [25.1]

### Added / Changed
- Added ability to save cards in Stripe for automatic invoice payment
- Page titles now reflect the page name, client selection, company name, ticket info, invoice info etc. for easier multi tab navigation.
- Admin pages now once again use the new admin role-check
- Admin roles can now be archived
- Debug now shows the current git branch
- Auto-acknowledgement email for email-parsed tickets now contains a guest link
- Recurring tickets no longer require a contact
- Stripe online payment setup now prompts you to set the income/expense account
- New cron/cli scripts are in the scripts subfolder - please update your cron configurations!
- Moved all modal includes to /modals to tidy root directory
- Moved most include files to /includes to tidy root directory
- Moved guest pages to /guest to tidy directory structure
- Renamed include file pagination.php to filter_footer.php as it is used in conjunction with filter_header.php for page filtering
- Guest ticket feedback now shows friendly ticket prefix & number, rather than just the ID
- Individual POST handler logic pages can no longer be accessed directly
- Added Payment Deltions on Payments and client Payments Page

### Fixed
- Fixed ticket edit modal not showing multi-client/no-client projects
- Fixed asset interface losing DHCP setting
- Fixed creating / editing recurring expenses causing 500 error due to incorrect var name
- Fixed tickets created via portal/email not being marked as billable

### Removed / Deprecated
- Deprecated the current cron scripts in the root directory - change cron to use the ones in the scripts subfolder instead


## [24.12]

### Added / Changed
- First introduced versioned releases!
