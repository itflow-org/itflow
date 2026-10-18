# Changelog

This file documents all notable changes made to ITFlow.

## [26.11]

### Upgrading to 26.11

Update from Maintenance > Update — Queue Update hands the job to cron, which updates the files and applies this release's database changes (2.7.9 to 3.2.1) in the same pass. Nothing else is required.

This is a big release, with forty-two database steps in one go, so take a backup before you update.

The update also clears out data that deletes in older versions left behind, all of it pointing at something that no longer exists:

- rack items still linked to a deleted asset (the item stays in the rack, the dead link is dropped)
- assets, licenses, tickets, domains, services and certificates still pointing at a deleted vendor or domain, and certificates linked to another client's domain
- the tasks, approvals and history of deleted tickets, the projects of deleted clients, and tickets still pointing at a deleted project
- project template entries whose ticket template is gone, and the same service link saved twice

It also gives every existing network a type (LAN, WAN, Guest and so on) guessed from its name or description, which you can change afterwards, and fills in the issue date, names covered and fingerprint of every certificate already on file. Invoices made from a quote before the update are linked to that quote, so each one shows where it came from. Products that ever had stock added keep tracking their stock, and the rest are set to ordered as needed (see Breaking Changes, Products). Their past stock changes are sorted into received, sold and returned from their notes, and linked to the invoice they name. No product becomes a client asset when it is sold until you give it an asset type (see New Features, Products). Each product's price history starts with the update, so lines on recurring invoices are only flagged as behind once their product's price changes.

Payment reminders keep the schedule they had: 1 and 30 days late, then every 30 days until the invoice is paid. If reminders were on, they stay on, and every client is included. The schedule can now be changed in Admin > Settings > Invoice (see Breaking Changes, Billing emails). Monthly statements start switched off.

Expenses already entered take who entered them from the audit log, where it has a record of it. Expense receipts are no longer opened straight from the uploads folder - see Breaking Changes, Expenses.

Recurring expenses keep their next date and from then on run on that day of the month - monthly ones stay monthly and yearly ones yearly. Look over their next dates once after the update (see Breaking Changes, Expenses). The expenses they recorded before are linked to them, matched on the vendor, category, client and description. No expense or recurring expense becomes billable to a client by itself - they stay your cost until you mark them (see New Features, Expenses).

Trips already logged keep the miles they were entered with (see Breaking Changes, Trips). Each takes who logged it from the audit log, or its driver where the log has no record. A trip for a client whose destination is one of that client's locations, by name or by address, is linked to that location. Trips already logged count as driven in a company vehicle and not billable, so nothing is paid back or billed for them until you say so (see New Features, Trips). No mileage rate or trip charge is set, and mileage goes under an expense category called Mileage, or Travel, when there is one.

Other income already entered (money in that isn't for an invoice, which used to be called revenue) takes who entered it from the audit log, where it has a record of it. Income with no category now counts in the dashboard and reports, so their totals can go up by that much (see Breaking Changes, Income).

Every account is given a kind - checking, savings, credit card, cash or payment processor - guessed from its name: Visa, Amex or card is a credit card, savings or reserve is savings, cash or petty cash is cash, and Stripe, PayPal, Square or merchant is a payment processor. An account given a liability type back when accounts had types (2023) is a credit card, so what is on it reads as owed. Anything else is checking. Change any that came out wrong with Edit on the Accounts page. An account's balance now leaves out archived payments, income and expenses everywhere, so the Accounts page can show less or more than before (see Bug Fixes). No account is reconciled after the update. The first statement you reconcile on each can start from the bank's balance on the day it starts, bringing everything before it forward as reconciled, or from the account's opening balance (see New Features, Accounts and transactions). Transfers already made out of a cash account count as having taken everything in it up to then to the bank, so the first deposit you make after the update only offers the checks and cash taken in since the last one.

Taxes keep their names and rates, now kept to four decimal places. No client has a rate of its own or is tax exempt, and there is no default rate, so new lines start untaxed as before until you pick one in Admin > Taxes. Billed time, trips and expenses stay untaxed until you switch them on there. Discounts now come off before tax on invoices and quotes that are still open, the next time they are edited (see Breaking Changes, Taxes). No expense has tax recorded on it until you add it, so Tax Collected shows no tax paid on purchases for the months before.

Tags keep their names, colors, icons and records. A color saved as a word, such as dark or primary, becomes the color it stands for, a short one such as #abc is written out in full, and a tag with no color, or one that isn't a color, turns grey, so its pills can be read (see Bug Fixes). An icon saved with `fas fa-` in front keeps just its name. A tag archived in an older version now counts as archived (see Breaking Changes, Tags). Licenses, services, networks, documents and tickets can now be tagged too, and start with no tags - the Tags pack in Maintenance > Starter Content adds a few for each.

Categories keep their names, colors, icons, order and records. A color saved as a word, such as lightblue or gray, becomes the same color written as #rrggbb, and a short one such as #abc is written out in full, so nothing looks different, but the ticket lists and the reports, which only read colors written that way, now show it (see Bug Fixes). A color that isn't one is taken off, and a category with no color stays without one. Interface types, asset statuses, software types, rack types and note types keep the order they are offered in.

API keys keep their names, end dates and the agent they act as. Admin > API Keys shows each as never used until its next call. A key acting as an agent who is disabled or archived, or has no role, was already turned away by the API, and is now marked Won't work with the reason (see New Features, API Keys).

The built-in Accountant role gets full access to Financial, so it can delete an expense, other income, a transfer or a payment entered by mistake - but only if it is still as it came: named Accountant, with Clients, Support, Sales and Reports at Read and Financial at Modify. One you have changed keeps what you gave it (see Breaking Changes, Roles).

What the API makes from now on - tickets, replies, clients and the rest - is stamped in ITFlow's timezone, as the pages stamp what they make. What it made before keeps the server's time, which on a server set to UTC is hours off (see Bug Fixes).

Ticket templates keep their subject, details and tasks, and set nothing else on a ticket until you give them a category, priority, agent, due time or tags. Words in square brackets already in a template, like [Employee Name], are asked for as plain, optional text when a ticket is raised from it, until you set them up on the template's page (see New Features, Ticket templates). Tickets raised before the update don't know which template they came from, so a template's use is counted from the update on. The starter ticket templates you added from Maintenance > Starter Content stay as they are; Update on that page brings them up to the new versions, which ask what they need and come with request forms.

If you are coming from 26.08 or earlier, the database update still has to be run from the command line one time, exactly as described under Upgrading to 26.09 below.

### Breaking Changes and Notes

- Client restrictions: if you limit agents to certain clients, that limit is now applied in places it used to slip through. This covers editing, archiving or deleting the client itself, the client list's bulk actions, credits, expenses, trips, recurring expenses, revenues, task approvals, the dashboard lists and the per-client reports. A restricted agent will lose access there. Company-wide report totals are unchanged.
- Dashboard: the Financial and Technical switches at the top of the dashboard are gone. Each part of the new dashboard shows to anyone whose role can open that part of ITFlow. If you used the switches to keep figures off someone's screen, take that module out of their role instead.
- Reports: each report now checks access on the page itself, not only in the menu, and is hidden when its part of ITFlow (accounting, ticketing or IT documentation) is turned off. Every report still needs the Reporting permission, plus:
  - Sales for Receivables Aging and Recurring Revenue, which used to need Financial, as they show invoices. Unbilled Work also needs Sales now, in the menu as well as on the page.
  - Financial for Mileage, which used to open with Reporting alone.
- Reports have new names that say what they show: Outstanding Balances is now Receivables Aging, Recurring Income by Client is Recurring Revenue, Expense by Vendor is Spend by Vendor, Tax Summary is Tax Collected, Unbilled Tickets is Unbilled Work, Time by Technician is Technician Performance, Tickets is Ticket Overview and SLA Summary is SLA Performance. The addresses are the same, so bookmarks still work, though the filters saved in an old bookmark may be ignored and the report opens on its default period.
- Client export: the client PDF no longer prints passwords and 2FA secrets unless you ask for them, and then it needs a password to open. The old PDF always printed them in plain text, archived credentials included.
- Shared links:
  - Links you sent before the update keep working until they expire.
  - New links keep their key after a # at the end of the address, so the whole link has to be copied. A system that cuts off everything after the # breaks the link.
  - Opening a link now takes a click on Reveal. This is so mail filters and chat apps that open every link cannot use up a one-time link.
  - Ended links stay listed for 90 days, with who opened them. They used to be deleted overnight.
  - Only the agent who made a link is told when it is opened. It used to notify every agent.
- Billing:
  - Line amounts are rounded to the cent when a line is saved. Tax used to be stored unrounded, so a total could be a cent off what was printed. Lines already on file keep their amounts until they are edited.
  - Taking a service line off an invoice no longer adds stock to the service. Only products kept in stock move stock, as before when they were added.
  - A quote that has run out can no longer be accepted from its link. Extend its date, or send a new one.
  - Batch Payment only pays invoices that are still owed (sent, viewed or part paid). It used to include non-billable invoices too, and marked them paid.
  - Invoice and quote PDFs have a new layout, and print on Letter paper in the United States and Canada and A4 elsewhere. They were always A4.
  - The recurring invoices list no longer has a date filter. It filtered by the date each one was created.
  - Links to the invoices list with `?status=Draft`, `Unpaid` or `Overdue` still work, and `Paid` and `Cancelled` are new.
- Billing emails:
  - Payment reminders moved from Admin > Settings > Notifications to Admin > Settings > Invoice, under Reminders and Statements. Saving the Notifications page no longer changes them.
  - Reminders and payment receipts go to the client's primary and billing contacts, once per address. They used to go to the primary contact only.
  - An invoice on a recurring invoice with autopay is not reminded before or on its due date, as the card is charged that day. It is reminded once it is late.
  - Invoice, quote, reminder, receipt and statement emails have a new look, with the invoice, quote or statement attached as a PDF. Attaching can be turned off in Invoice Settings, or for one email in its Send window. The subjects changed too, such as "Invoice INV-12 from PittPC", "Overdue: invoice INV-12 is 30 days past due" and "Payment received for invoice INV-12". If you filter mail on the old subjects ("Overdue Invoice INV-12", "Quote [scope]", "Account Statement - Client"), update the filters.
  - The late fee is now a line on the invoice like any other, and the total is worked out from the lines. It still comes every 30 days late, while reminders are on.
- Recurring invoices:
  - The nightly run also bills a run it missed by up to 7 days, for nights the cron didn't run. It used to bill only on the day itself. A recurring invoice further behind than that is still left for you to look at.
  - Invoice Now bills the next run early, and the schedule moves one period on from that run, so the invoice takes its place. It used to restart the schedule from today: pressing it on the 16th for a run due on the 1st moved every later invoice to the 16th.
  - Every invoice made from a recurring invoice takes its taxes at today's rates, and the recurring invoice's lines are updated to match. The nightly run used to copy the amounts saved when each line was added, while Invoice Now used today's rates.
  - A schedule on the 29th, 30th or 31st keeps that day. After a shorter month it goes back to the 31st, where it used to stay on the 28th from then on.
  - The email goes to the client's primary and billing contacts that are not archived, once per address. When outgoing mail is not set up, nothing is queued and the invoice stays a draft. It used to be marked Sent anyway.
- Quotes:
  - A quote's amount is now what it bills up front. Lines billed each month, quarter or year are counted apart, as so much a month, and the quotes list, search and the client portal show that next to it. Quotes from before the update have only one-time lines, so their amounts don't change.
  - The quote's discount comes off its one-time lines. On a quote with only recurring lines it comes off the first recurring part (each month, then each quarter, then each year), and stays on the recurring invoice made from it.
  - Make Invoice only works on an accepted quote, and only once. The quote is then Invoiced. It used to invoice a quote in any status, as many times as it was pressed. When the client says yes by phone or email, mark the quote Accepted first.
  - The quote email says "Cost: $1,540.00 up front and $2,034.76 a month" when the quote has recurring lines. A quote with only one-time lines still says "Total Cost".
  - Quotes Pipeline values a quote at what it bills up front plus a year of its recurring lines, so a quote with monthly lines counts for more than its up-front amount in quoted, won, lost and open. The open quotes table shows that first-year value with the breakdown under it, and its CSV has Up front, Monthly and First year columns.
- Products:
  - Only a product with Track stock on moves stock when it is invoiced, and it still can't be invoiced past what is on the shelf. After the update, a product that ever had stock added keeps tracking it. One that never did is ordered as needed and can now be invoiced - it used to be refused as not enough in stock. Turn on Track stock in Edit for anything else you count.
  - The Products page shows everything, with tabs for Services, Products, Recurring and Stocked. It used to open on Services. Old links to `products.php?type=service` and `?type=product` still open those tabs.
  - Clicking a product opens its new page instead of the Edit window.
  - Import finds columns by their heading, so a file can have any of the fields in any order. The old three column file (Product Name, Description, Price) still works. A row whose code is already in the catalog is skipped unless Update products with the same code is on.
  - Add Stock is replaced by one Stock window with Receive, Count and Write off, on the product's page and in the list's menu. Count records the difference from what you counted, and Write off asks why.
  - Cancelling or deleting an invoice puts its tracked products back on the shelf. Deleting one used to leave the stock gone for good.
  - Copying an invoice, or making an invoice from a quote, is refused when a tracked product on it is short, the same as adding the line by hand. Add the stock first.
  - Invoices made from recurring invoices are now linked to their products, so they count as sold on the product's page and in the reports. They don't move stock.
  - Stock can be counted in fractions (up to two decimal places), such as cable by the metre.
- Expenses:
  - Receipts open through ITFlow, for agents with Financial access to the expense, instead of straight from `uploads/expenses/`. An old link to a receipt file stops working. On Apache the folder is closed by the `.htaccess` that comes with the update. On nginx, deny `/uploads/expenses/` the way you deny `/uploads/tmp/` and `/uploads/backups/`.
  - A new expense takes the currency of the account it is paid from. It used to take the company's currency whatever the account.
  - The description is optional now.
  - Bulk Set Category, Set Account and Set Client are one window each from the same Bulk menu, with Set Payment Method added. Bulk Delete is one button that asks once.
  - Recurring expenses run on the day of the month of their next date, the way recurring invoices do. The nightly run used to move a monthly one a month on from the day it happened to run, and a new one started in the month picked in the form even when it was monthly. Check the next dates on the Recurring Expenses page after the update.
  - A recurring expense the nightly run missed by up to 7 nights is recorded the next night, dated the day it was due. One further behind waits for you to Record Now or Move On. The notification every night for each recurring expense with a date in the past is replaced by one notification, on the day it falls too far behind.
  - Deleting a recurring expense needs full Financial access, the same as deleting an expense. It used to need write access.
  - Unbilled Time also lists expenses to bill for agents with Financial access, and its count in the menu adds them. For those agents it shows in the menu even with ticketing turned off.
  - Transactions has a Reimbursement type for money paid back to an agent. A Transactions export filtered to Expense leaves those out.
- Trips:
  - Every agent can open Trips when accounting is on, and log their own driving. An agent without Financial access sees only the trips they drove or logged, and can change or delete only those. Logging a trip for someone else, or changing theirs, needs write access to Financial, and deleting theirs needs full access. Before, any agent who opened the page saw every trip of their clients and could change any of them.
  - A trip's miles are the whole trip. The form asks for the miles one way and doubles them for a round trip. The old form saved the number as typed, whether Round Trip was ticked or not, so an older round trip entered as the miles one way still counts once. Edit those to correct them.
  - The database column `trips.trip_end_odmeter` is now `trip_end_odometer`. Anything of your own that reads it, such as a custom report, needs the new name.
- Income:
  - Money in that isn't for an invoice is now called other income (it was revenue). Adding and changing it needs write access to Financial, and deleting it full access. It used to need Sales. Payments go by Financial too, with Sales at Read (see Roles).
  - Other income counts whether it has a category or not, on the Income list, the dashboard, the client overview and every report, as No category when it has none. The dashboard and reports used to leave it out until it had a category, so their income can go up after the update by what had none. Money coming in from a transfer between your own accounts is still not income.
  - New other income takes the currency of the account it goes into. It used to take the company's currency whatever the account.
  - The Income list's Bulk menu has Set Category, Set Account, Set Payment Method and the new Set Client, a window each, and Delete. A payment's category is its invoice's, so Set Category on a payment sets its invoice's category, as before. A payment's client is its invoice's, so Set Client leaves payments alone, and payments are deleted from their invoice, not here.
  - The export's Type column says Invoice payment or Other income (it was Payment or Revenue), and Source is now What for, with new Invoice and Paid Online columns.
  - In Transactions, a payment's date opens its quick look instead of the invoice. The quick look has Open Invoice.
- Accounts, transactions and transfers:
  - The Transfers page is now the Transfers pill of Transactions. The Transfers link stays in the menu and opens it for every account. An old link to `transfers.php` lands there with its account and dates. The old page could filter by the account a transfer came from and the one it went to together. Now pick one account, and its Transfers pill shows the transfers into and out of it.
  - The New Transfer window no longer has the list of recent payments to copy into the notes.
  - A transfer takes the currency of the account it leaves. It used to take the company's currency whatever the accounts.
  - An account can only be archived once it holds nothing and no payment provider pays into it, and only an account that was never used can be deleted. The Accounts page used to hide Archive otherwise, but the link itself archived any account, and Delete removed an account with its history still pointing at it. Archive one you no longer use instead.
  - The Transactions export's Type column says Invoice payment, Other income, Transfer in, Expense, Transfer out or Reimbursement (it was Payment, Revenue, Transfer In, Expense, Transfer Out or Reimbursement). Money in and out are now separate In and Out columns, with Account, Who or What and Client added. The signed Amount column is still there, but off unless ticked.
  - Links to `transactions.php?type=Payment` and the other old type names still work.
  - Once a payment, other income, expense, transfer or reimbursement has cleared on a reconciled statement, its amount, date and account stay as they are and it can't be deleted, until that reconciliation is undone. Its category, client, description, method and reference can still change. This also stops:
    - deleting the invoice a cleared payment is on (cancel the invoice instead), or refunding a cleared Stripe payment from ITFlow, which deleted the payment
    - deleting a client with cleared payments or other income, from ITFlow or through the API (keep the client archived instead)
    - Bulk Set Account and Bulk Delete on the Income and Expenses lists, which leave cleared ones as they are and say how many
    - undoing a reimbursement whose money out has cleared
  - An account brought forward as reconciled (see New Features, Accounts and transactions) has everything before the day it was brought forward to locked the same way, until that is undone.
  - An account's opening balance can't be changed by hand once a statement on it is reconciled, as the first statement starts from it. On an account brought forward from the bank's balance, Correct the opening balance can still move it once.
- Taxes:
  - Discounts come off before tax. On an invoice, quote or recurring invoice with a discount, tax is worked out on what the client pays after it, the discount shared across the lines by their amounts. It used to be worked out on the full price. Paid, cancelled and non-billable invoices, and quotes that are answered or invoiced, never change. Others follow the next time a line or the discount is edited, and a recurring invoice when it next bills. To keep taxing the full price, switch off Take discounts off before tax in Admin > Taxes.
  - Changing the percent of a rate that is on an invoice or quote that has gone out now makes a new rate with the same name, and archives the old one. What was billed keeps the old rate, on the page, the PDF and the Tax Collected report, where the two show as separate rows. Products, clients, the default and recurring invoices move to the new rate, and draft invoices and quotes do too unless you untick it. It used to change the rate in place, so an invoice already sent showed the new percent next to tax worked out at the old one. A rate's name, description and registration number still change in place.
  - A rate can only be deleted when nothing has ever used it - archive it otherwise. Archiving asks where to move the products, clients, recurring invoices, drafts and default that use it. Left where they are, recurring invoices and drafts keep charging it, and new lines for its products and clients take the next rate along.
  - Tax Rates in the Admin menu is now Taxes.
  - A line on a rate made of parts is taxed part by part, each to the cent, so its tax can be a cent off the whole rate times the line.
  - Tax Collected now shows tax by part, so a rate made of a state's and a county's part shows two rows, and a part named the same on two rates shows one. Its sales are net of discounts, where they used to be the lines before any discount, and archived payments no longer count. It still counts as payments come in unless you switch it to Invoiced.
- Tags:
  - Archiving a tag now does something: it stays on the records that have it, but isn't offered in record forms, the tag picker or the list and export filters, unless a record or filter already has it. A tag archived in an older version is archived now too - restore it from the Archived tab in Admin > Tags.
  - The + beside the Tags field in record forms is gone. Admins type the new tag's name into the field instead, and it is made when the record is saved. Other agents pick from the tags there are, as before - the + never worked for them.
  - A tag's name is now unique within its kind, in any case: a second client tag called VIP can't be made, though an asset tag can be. Tags already named alike keep their names. Typing a name that exists into a record form picks that tag.
  - A tag that a recurring invoice line counts users or devices by can't be deleted. Archive it, or merge it into another tag, which moves the line along with it.
  - Pills now have dark text on light colors such as yellow and light blue, where every pill had white text.
  - The search box at the top, and the license, service, network, ticket and Files searches, also match tag names, so a search can bring back records whose own details don't have the words in them. In the search box and on its full results page, the tag that matched shows next to each.
- Categories:
  - Renaming a referral, asset status, interface type, software type, rack type, or contact or asset note type now renames it on every client, asset, interface, license, license template, rack or note that has it. Records keep these by name, so renaming one used to leave its records on the old name, which could no longer be picked.
  - A category can only be deleted while nothing uses it - merge it into another, or archive it. Deleting one in use used to leave expenses, invoices and tickets with no category, and clients and assets with a name that could no longer be picked. Delete is now on current categories too, not only archived ones.
  - A category's name is now unique within its kind, in any case: a second expense category called Travel can't be made, though an income category can be. Categories already named alike keep their names.
  - Interface types and rack types are held to 50 characters, which is all an interface or a rack keeps.
  - The + beside a category picker shows for admins only, on every form. It opened Admin's New Category window, which refused anyone else. Agents typing a referral that isn't one yet into a client's form still make it, as before.
- Notifications now follow access too. An agent only sees notifications for clients and areas they can open, so someone without sales access no longer sees invoice and quote notifications.
- Drop-downs: Tom Select is gone, replaced by ITFlow's own picker. It works the way you are used to: type to search, tick several in a multi-select, and type a new value in fields that allow it, such as ticket watchers. See Developer Updates if you have custom pages that used Tom Select.
- Several small windows are gone because the change is now made where the value is shown:
  - ticket assignee, priority and billable
  - Assign Tags on assets, clients, contacts, credentials and locations
  - Link Closed Ticket on projects

  Click the value and pick a new one, or use the + at the end of a record's tags. Each change saves straight away.
- Tickets: a closed ticket can no longer be changed, either from the ticket's windows or through the API. Merging is only offered between tickets of the same client.
- Tickets: a ticket that closes by itself some time after it was resolved now sends the client the closed email, as closing it yourself does, when client notifications are on (Admin > Ticket). It used to close without a word.
- Tickets: an agent is now emailed when a ticket is assigned to them from New Ticket or Edit, as they already were from the assignee pick and Bulk Action. It used to be an app notice only. Assigning a ticket to yourself sends nothing.
- Client portal: the email inviting a contact to the portal no longer has their password in it. Tick Email them an invite to the portal - on New Contact as well as Edit - and they get a link to set their own password, good for 7 days, or with a Microsoft login a link to sign in. It used to email the password the agent typed, in plain text. A password you type is still set, for reading out over the phone; the link lets them choose another.
- Email on the contacts and clients lists comes from one of your mailboxes in Admin > Mail, picked by name, and goes out in the email look. Archived contacts and contacts without an email address are left out, and each address gets it once. With no roles ticked on the clients list it goes to every contact that isn't archived. Nothing is sent while outgoing mail isn't set up.
- Ticket templates: [Client], [Contact], [Asset], [Site], [Agent], [Project], [Ticket], [Date], [Month], [Quarter] and [Year] in a template's subject, details and tasks are filled in when a ticket is raised from it - and in a recurring ticket's each time it raises one, template or not. A recurring ticket with [Month] in its subject used to raise it with [Month] in it. Anything else in square brackets is now asked for when a ticket is raised from the template - optional unless the template says otherwise - and stays as it is when it isn't answered.
- Ticket templates: a project template's tickets are set up as their ticket templates say. They were always Medium, uncategorised and billed as the default.
- New Tickets on the assets list: with [Asset] in the subject, the asset's name isn't put in front of it as well. New Tickets on the assets and clients lists now email the agent they go to, once for all of them, as New Ticket does.
- Emails to agents keep their subjects, so mail rules still match. The ones that changed: a client's reply from the portal is "ITFlow - Ticket updated - [TCK-12] Subject", with the dash the emailed reply always had; one recurring ticket assigned from Bulk Action is "ITFlow - Recurring ticket Subject assigned to you", as from the assignee pick; and a ticket raised on a schedule reaches the new-ticket address as "ITFlow - New Recurring Ticket - Client: Subject".
- Client portal: primary and technical contacts' Tickets page now opens on everyone's tickets at their company, with Mine to see only their own. The separate All tickets page is gone, and links to it land on Tickets. A ticket shows the client where it stands - Received, In progress, Waiting on you, On hold, Resolved or Closed - rather than your ticket statuses.
- Client portal: invoices and quotes now open in the portal, where they used to open their guest link in a new tab. The links in your emails work as before. A quote answered in the portal records the contact's name and any note they left. Pay invoices pays the invoices ticked as one card payment, and the Pay drop-down on each invoice is gone (see Client portal).
- Client portal: only the primary contact can add or change contacts now, and give or turn off their portal access. Technical contacts still see the contacts, but can no longer add or edit them. A primary contact can turn access back on for someone you turned off, and it is logged and notified when they do.
- Client portal: turning off a contact's portal access, in ITFlow or in the portal, now signs them out at once. They used to stay signed in until their session ran out.
- Client portal: Certificates is now a tab of Domains & certificates, and links to it land there. A contact who opens a page they can't use is taken home with a message, where they used to be signed out.
- Client portal: a link to set a password now runs out - an hour for Forgot password, a week for an invite - and works once. Links sent before you update keep working for a day. A link used to work until the nightly run, whenever it was sent.
- Client portal: a contact's password has to be at least 8 characters when they change it, and changing it - or turning two-step sign-in on or off - asks for their current password and emails them.
- Sign in: Remember Me on the code step now says how long the browser is remembered (Admin > Settings > Security).
- Stripe: Refund on a Stripe payment now refunds that payment's amount, where it used to refund the whole card charge. A card payment from the client portal over several invoices is booked as a payment on each, so refund each invoice's part from that invoice. Stripe's fee for such a payment is recorded once, naming every invoice it paid.
- Invoicing a ticket:
  - The invoice line now reads `Ticket TCK-123 - Subject - 10/14/2026`. It used to be a block of text with the agent, dates, contact, asset and time spent. You can still edit it before adding the line.
  - Billing a ticket goes in the ticket's history ("Billed on invoice INV-45"). It used to add an internal note to the conversation.
  - An invoice made from a ticket is due on the client's terms, the same as New Invoice. A client set to On Receipt is due on receipt, where it used to get the default terms.
  - Add Tickets on an invoice now adds a line for each ticket's time. To only put the tickets on the invoice, as before, switch off Bill their time.
  - A ticket's time is counted the same way everywhere: Unbilled Time, the Invoice window, Add Tickets and the Unbilled Work report. A ticket counts once it is resolved or closed, and archived tickets and archived replies are left out.
- Calendars: only admins can add, edit or delete calendars now. Agents can still add and edit events.
- Roles:
  - The money on invoices goes by Financial, with Sales at Read to see the invoices: write access to Financial to take a payment (one, a saved card or a batch) or change one, and full access to delete or refund one. These used to need Sales as well - write access to take a payment, full access to Sales and Financial to change or delete one. Emailing a payment reminder, a statement or a receipt works with write access to Financial and Sales at Read, as well as with write access to Sales, as before. Nothing a role could do before is taken away. Editing, sending, cancelling and deleting an invoice stay with Sales.
  - A client's calendar events follow the role's Clients access: Read to see them, write access to add, change, move, skip or send them, and full access to delete them - or write access, for the agent who added it. Agents still see the events they are on, and an event with no client is anyone's, as before. So an agent whose role has no Clients access no longer sees other agents' client events, on the calendar or through their calendar link, and deleting someone else's client event now takes full access to Clients.
- Licenses: archiving a license keeps its seats (the devices and users on it), so restoring it brings everything back. In 26.10, archiving took the seats off.
- Vendors: deleting a vendor that has expenses, recurring expenses or payment provider fees archives it instead, so your books keep it. Deleting any vendor now also clears it from the assets, licenses, tickets and domains that named it.
- Locations: a client's primary location cannot be archived or deleted. Make another location primary first. Bulk archive skips primary locations.
- Users: passwords must be at least 8 characters, and an email address can only belong to one account, agent or client portal. Existing accounts keep working. The rules apply when an account is added or changed.
- Users: Incident Response now resets agent passwords only, where it used to reset client portal logins too. It also forgets every agent's remembered browsers, so MFA is asked for again. The new passwords are shown once on a plain page, ready to read out or copy.
- Your account: changing your email or password, or turning MFA off, now asks for your current password. Your old address gets a notice when the email changes, and you get an email when your password changes or MFA is turned off.
- Agent user limit, for hosting providers: an optional `$config_agent_user_limit` in config.php caps the number of active agent users. It is not set by default, so nothing changes unless you add it. Disabled and archived agents do not count toward it.
- API:
  - Tickets: a closed ticket cannot be updated. Creating a ticket for a client that does not exist is refused. A contact, asset or vendor belonging to another client is left off rather than saved, and the assignee has to be an active agent.
  - Tickets: the vendor's ticket number is now text, so case numbers with letters keep them. Send it as `ticket_vendor_ticket_number`; the old `ticket_vendor_ticket_id` still works. Resolving a ticket that is already resolved does nothing.
  - Certificates: creating one follows the same rules as the New Certificate window. A name or a host is enough. The domain has to be one of the client's, and the host is checked as it is saved.
  - Domains: an update no longer wipes the expiry date and DNS records when the lookup comes back empty. A domain name the client already has is refused.
  - Deleting an asset, contact, client or location through the API now also cleans up whatever pointed at it, the same as deleting it in ITFlow.
  - New endpoint to read services, under the Support permission. Vendors take the account manager's phone and email.
  - A call the key's role doesn't allow is refused with what it needs and what the key's user has - "This needs Modify on Support - the key's user, Tess Tech (Technician), has Read." - in place of "The user linked to this API key does not have permission for this action." It is still a 403. Anything that matches on the old text needs changing.

### New Features & Updates

- **Everywhere**
  - Search: press Ctrl+K (Cmd+K on a Mac) or click the search box in the top bar. Results show as you type, grouped by type: clients, tickets, contacts, assets, credentials, documents, files, licenses, domains, certificates, networks, IP addresses and more.
    - A ticket, invoice or quote number comes up first, so Enter opens it straight away. Phone numbers, IP addresses and MAC addresses all match.
    - Use the arrow keys and Enter, or Ctrl+Enter to open in a new tab. With nothing typed, it lists the clients you opened last.
    - Search everything opens the full results page, which also searches ticket conversations and the text of documents.
  - Notifications: the bell opens a panel with Unread and All tabs, grouped by day. Clicking one opens what it is about and marks it read. Each one can be marked read or unread, and Mark all as read clears the lot. The count updates every minute. The full Notifications page has search, type and date filters.
  - Quick looks: most records now have a quick look, a window with the essentials that opens from the lists and from anywhere else the record is mentioned, without leaving the page you are on. Each one has a button to open the full page.
  - Record pages: licenses, locations, vendors, racks and services have their own page now. Assets, contacts, documents, projects and networks have rebuilt pages. All of them share one layout:
    - actions along the top
    - the key details in cards
    - related records in tabs with counts, and most pages remember the last tab you used
  - Change it where you see it: ticket status, priority, assignee and billable, a project's manager, a recurring ticket's agent, an asset's status, location and contact, and a contact's location. Click the value, pick a new one, and it saves.
  - Tags look the same on every list and record page. The + at the end adds or removes tags as you tick them, on one record or on everything you have selected. Admins can make a new tag right there.
  - Copy buttons show up when you point at a value, such as an IP, MAC, serial, email, phone, username or password, so lists are less cluttered.
  - Linking: one Link window handles every kind of link: files, documents, assets, contacts, licenses, vendors, credentials, services, domains and certificates. It has a tab for each type, lets you pick several at once, and only offers the client's own records that are not linked yet.
  - Drop-downs show pictures, initials, colors and icons where they help: client, agent and contact avatars, tag and category colors, priority dots and asset types. On a phone the list comes up from the bottom of the screen.
  - Forms have an icon in front of every field, the same across the app. Long forms are split into tabs, and most of them take you to a required field you left empty on another tab when you save.
  - Buttons that only show an icon say what they do when you point at them.
  - Empty lists look the same on every list page. With nothing added yet, a list says so, with a line on what goes in it and its own New buttons - and Import, where the list has it. When a search or filters leave nothing, it says nothing matches, with Clear filters to go back to the whole list, keeping the client and the tab you are on. An empty tab that flags trouble, such as Overdue or Expiring soon, shows a tick, as nothing there is good news. Archived says when nothing is archived.
  - Links that delete or disable something ask first.
  - Leaving a page with a half-written reply or note asks first, including after a window has been opened and closed on that page.
  - The side navigation shows your company logo (Admin > Settings > Company Details) or the company's initials, with the company name wrapping onto two lines.
  - The editor's outline follows your theme color when you click into it, like every other field.
- **Dashboard** (rebuilt)
  - Needs attention along the top:
    - tickets past their SLA, unassigned tickets and overdue projects
    - overdue and draft invoices, resolved tickets not invoiced yet, stock to reorder, lines on recurring invoices, drafts and quotes at an old price, and hardware sold in the last 90 days that isn't on the client's assets yet
    - domains, certificates, licenses and warranties expiring in the next 30 days
    - services needing attention and service reviews due

    Each one opens a list of exactly those.
  - Your day: your open tickets, your schedule for the week, your time this week, open tickets per agent (admins), and tickets opened against resolved.
  - Finance: pick a period (this month, last 30 days, this quarter, year to date, or any year). It shows:
    - income, expenses and profit compared with the period before
    - receivables, and recurring revenue and expenses per month
    - a cash flow chart that looks ahead using unpaid invoices, recurring invoices and recurring expenses
    - receivables aging, top clients, account balances, the latest income and expenses, and trips
- **Reports** (rebuilt)
  - Every report works the same way:
    - pick a period (this or last month, this or last quarter, this or last year, the last 30 days, 90 days or 12 months, a past year, or your own dates), and narrow it to one client or one agent where that makes sense
    - read the headline figures, compared with the period before, then the chart and the tables
    - click a column heading to sort, and click a figure to open the tickets, invoices, income or expenses behind it
    - Export downloads any table as a CSV with every row, and is recorded in the audit log. Print gives a clean page with your company name and the date.
  - The Reports page lists every report you can open, grouped into Finance, Sales & Billing, Service Desk, and Clients & Documentation, with a search box. The menu on the left uses the same groups.
  - New reports:
    - Client Review: one client's quarter on a page, made to print for a business review (QBR). It covers their tickets and how fast they were handled, SLA and ratings, what the tickets were about, who raised them and which devices keep coming back, urgent tickets, projects, hardware to plan for, renewals, services needing a look, documentation gaps and billing. It opens with recommendations to talk through, and is also in the client's menu.
    - Renewals & Expirations: domains, certificates, licenses and warranties due in the next 30 days to 12 months, or lapsed in the last 90 days, by month and by client. Ones that renew themselves show in blue and can be hidden.
    - Asset Lifecycle: how old the hardware is, its warranty, and whether its operating system is still supported (Windows 10, Windows Server 2012 and older, macOS 13 and older and so on are flagged). It shows what reaches replacement age each quarter for the next two years, for budgeting, and lists the replacement candidates.
    - Documentation Coverage: for each client, whether the basics are documented: primary and billing contacts, a primary location, assets with a location and an owner, networks, credentials, documents, domains, services and licenses. Each client gets a score, and what is missing links to the page to add it.
    - Customer Satisfaction: the share of closed tickets rated good, how many get rated, by agent and by client, how ratings follow a late first reply or a slow fix, and every bad rating to follow up.
    - Open Ticket Aging: the open tickets right now by how long they have been open, per agent and per client, with whose move it is, their SLA and when anything last happened.
    - Client Profitability: income, direct costs and time per client, what you earn per hour against your target rate, and recurring revenue.
    - Quotes Pipeline: quotes won, lost and open, the win rate by count and by value, and open quotes about to expire.
    - Ticket Templates: how often each template is used and how - by agents, from the client portal's request forms, by recurring tickets and by project templates - with what is resolved and still open, how long its tickets take and the time spent on them, and the templates not used in the period.
    - Mileage: trips by agent and by client, valued at your rate per mile.
    - Budget vs Actual: set a monthly budget for each expense category right on the report, or copy last year's, and see spending against it by month and category.
    - Inventory: what is on the shelf and what it is worth at cost, what came in, and the cost of what was sold and written off, by month. Every tracked product with its stock, value and reorder level, and every stock move in the period.
  - Reworked reports:
    - Profit & Loss: monthly or quarterly, with last year and the change beside each line, and the margin.
    - Income and Expenses: by category across the year, with last year drawn as a dashed line.
    - Spend by Vendor and Income by Client: the period against the one before, with a switch for those paid 600 or more for 1099s. Spend by Vendor adds each vendor's main category and recurring spend. Income by Client adds recurring revenue and the share of your top five clients.
    - Receivables Aging: what each client owes, split into not due yet, 1 to 30, 31 to 60, 61 to 90 and over 90 days late, and the overdue invoices, longest overdue first.
    - Recurring Revenue: monthly and yearly recurring revenue by client and category, and every recurring invoice with its next date. With Financial access, what is left after recurring expenses.
    - Tax Collected: tax by part of each rate, as invoiced or as paid, with taxable, exempt and non-taxable sales, the tax paid on purchases and what is left, by quarter or by month (see Taxes).
    - Unbilled Work: billable tickets that are done but not invoiced, with the hours to bill (rounded the way Billing Time in Admin > Settings > Invoice says, as Unbilled Time does) and what they are worth at the client's rate.
    - Time Detail by Client: per client, each ticket and the time on every reply, with the hours to bill.
    - Ticket Overview: tickets opened against resolved, the typical first reply and time to resolve, sources, priorities, categories, top clients and contacts, and a weekday by hour chart of when tickets come in.
    - Tickets by Client: volume and its change, urgent tickets, time logged, reply and resolution times, SLA and satisfaction for each client.
    - Technician Performance: hours logged and billable, utilization against the working days in the period, replies, tickets worked and resolved, SLA and satisfaction for each agent.
    - SLA Performance and SLA by Client: targets met over time, by priority, by agent and by client, and the tickets past their SLA right now.
    - Credential Rotation: how old every password is, the clients with the most due, which ones have 2FA, and every password due for a change.
- **Clients**
  - The clients list has compact rows, filter chips you can remove one at a time, open ticket and balance columns, and tags under the industry with a + to add one. Clicking anywhere in the client's cell opens it.
  - The client side menu shows the client's initials and name under an All clients link. Click it to switch to another client and stay on the same page.
  - The header on every client page shows the initials, industry, tags, primary location, open tickets and balance due, with a panel that opens for more.
  - The client overview is redesigned in two parts:
    - Technical: needs attention (such as licenses over seats, passwords due for a change, certificate problems and services needing attention), open tickets, quick notes that save when you click away, key contacts, favorites, the next 14 days, projects, services, renewals, assets, active share links and recent activity.
    - Financial: unpaid invoices, recurring invoices, and invoiced against paid.
  - Export Data, in the client's menu, is rebuilt with two choices:
    - Documentation PDF: a printable runbook to hand over or keep on file. It has a cover, contents with page numbers, and an overview with the key contacts, service levels and how to reach you. Then comes a chapter for each part you pick: contacts, locations, networks with their IP addresses, assets by type, racks, licenses with their keys and who holds them, domains and DNS, certificates, services with what they run on and depend on, vendors, credentials, files, and every document in full with its images.
    - Full export: one zip with everything held for the client, for a handover, an offboarding or your own records. Every record comes as a CSV spreadsheet and as JSON for moving to another system. Documents come as web pages in their folders, files in their folders under their own names, and ticket conversations with their attachments, alongside projects, invoices, quotes and payments, photos and the PDF. A README.html links it all together.
  - Each part shows how many records it holds. Archived records and internal ticket notes go in only if you tick them.
  - Passwords and 2FA secrets go in only if you ask, and the PDF or zip is then locked with a password you set (AES-256). An encrypted zip opens with 7-Zip, WinRAR, Keka or The Unarchiver, but not with the zip tools built into Windows and macOS.
  - Each export is recorded in the client's audit log, including whether passwords went out.
- **Shared links** (rebuilt)
  - One Share window for logins, documents and files, from their lists, quick looks and pages, plus two new kinds of link:
    - Secure note: type a password, a Wi-Fi key or a recovery code and send it as a link. It is not saved anywhere else.
    - Ask for a login: the client gets a form to send you a login safely instead of by email. You get a notification and an email when it arrives. It then waits in Shared Links until you save it to their credentials or discard it.
  - Choose:
    - how long the link works, from 1 hour to 30 days
    - how many times it opens: once, 2, 3, 5 or 10 times, or until it expires
    - what it asks for first: nothing, a passphrase you give them by phone or text (one is made up for you), or a 6-digit code emailed to the recipient, so a forwarded link is no use to anyone else. Five wrong tries lock the link.
  - The link can be emailed to a contact, copied, or shown as a QR code. It is shown once, and nobody can look it up again later.
  - For a login, you can include the 2FA code: they see the current code, never the secret. What they get is fixed when you make the link.
  - The page they open shows your logo and who sent it, then a Reveal button. It has copy buttons, the 2FA code with a countdown, and a button to destroy the link once they have what they need. Documents can be printed, and files download.
  - Shared Links, in each client's menu and under All Client Docs, lists every link:
    - what went out, to whom, who made it, until when, how often it was opened, and where it is now
    - filters for Open, Sent back and Ended
    - turn off one link, the ticked ones, or every open link the client has, when an engineer leaves or a mailbox is compromised
  - Each link's quick look has its history: when it was opened, from which address and browser, wrong passphrases, codes sent, and when it was turned off.
  - You are notified when a link you made is opened (you can switch this off per link), and always when one locks.
  - The key is the part of the link after the #, which browsers never send to the server, so it stays out of web server and proxy logs. Logins, notes and what clients send back are encrypted with that key, which is not stored, so the database alone cannot open them.
  - Admin > Settings > Security sets the longest a link may last, up to 90 days, and whether shared passwords and secure notes must have a passphrase or a code.
- **Billing** (rebuilt)
  - Invoices, quotes and recurring invoices have new lists and pages, and every billing window is redone.
  - Lists:
    - Invoices: what you are owed, what of that is overdue, what is sitting in drafts and what came in over the last 30 days, each opening the invoices behind it. Filter by Draft, Unpaid, Overdue, Paid or Cancelled, by category, client and date. Each row shows what is still owed and how long until it is due, or how many days late it is.
    - Quotes: what is out waiting for an answer, what has been accepted and not invoiced yet, what you won over the last 90 days and your win rate. Filter by Draft, Awaiting answer, Expired, Accepted, Invoiced or Declined. Each row shows how long a quote has left.
    - Recurring invoices: your monthly recurring revenue (quarterly ones count as a third, yearly ones as a twelfth), what bills in the next 30 days, how many are running, and any that need a look. Filter by Active, Next 7 days, Late, Paused or All. Each row shows the amount, what it brings in a month, when it next bills, whether it is on autopay, and whether it bills per user or per device.
  - Pages: the invoice, quote or recurring invoice is shown the way the client sees it, with the next thing to do at the top:
    - Invoice: Send (or Mark Sent) a draft, Add Payment on one that is owed, with Charge Saved Card when the client has a card on file. On the side: what has been paid and what is left with a progress bar, the payments (edit, refund through Stripe or delete them there), the tickets billed on it, and its history as a timeline.
    - Quote: Send, then Accepted or Declined when the client answers by phone or email, then Make Invoice (Start Billing when it has recurring lines). A quote that ran out without an answer says so, with an Extend button. On the side: the files the client sent back, and its history.
    - Recurring invoice: Invoice Now, Pause and Resume. On the side: the schedule with the next few dates, the period the next invoice covers and whether invoices go out by themselves, autopay, the invoices it has made and whether they were paid, and its history. A run missed in the last 7 days is billed by the next nightly run. One further behind says so, and offers Invoice Now for each missed run or moving on to the next date without billing them.
  - Lines are edited on the page:
    - Type a product's name or code to fill in its description, price and tax. What the line comes to shows as you type, and Enter adds it. The page comes back ready for the next line.
    - Drag a line to move it. Edit or remove it from its menu. An invoice's lines are locked once it is paid, cancelled or non-billable, and a quote's once it is answered.
    - Taxes show by name on the totals, such as PA Sales Tax 6%, instead of one Tax line.
  - Windows: New and Edit have the same fields for all three, with the discount on both. A new invoice shows its due date from the client's terms, and a quote's valid-until date has 15, 30, 60 and 90 day shortcuts.
    - Add Payment starts at what is owed, says what is left after the payment as you type, and shows each account's balance. Batch Payment lists the invoices it will pay, oldest first.
    - Send, Copy, Make Recurring, Make Invoice, Mark Sent and Add Tickets (any number at once) say what they will do before you do it.
  - PDFs: one new look for invoices, quotes and packing slips, with your logo, taxes by name and page numbers. An invoice still owed carries a QR code to open it online and pay, and a quote waiting for an answer one to accept it. Paid, cancelled, accepted and declined ones are stamped.
  - The client's view of an invoice: what is owed and by when, with Pay Now, the PDF, the payments made, and everything else they owe with a link to each.
  - The client's view of a quote: Accept and Decline, with how long it is good for. Once accepted, they can send back a signed copy or a purchase order.
  - A new recurring invoice can be set to leave a draft to check, and take a discount, from the start.
  - Per user, per device and per license billing on recurring invoices:
    - A line can take its quantity from the client. That can be their contacts (all, or those with a tag), their devices (the types you tick, or those with a tag) or a license's seats (in use or bought). Lost, stolen and retired devices are left out.
    - The count is taken again each time an invoice is made, so a new hire or a new laptop is on the next invoice without anyone editing it. A line can have a minimum for contracts that set one.
    - On the add-a-line row, Counted switches a line to Per user or Per device. Edit Line has every option, and shows the count as you choose.
    - Each such line is marked on the page. A Seats card shows what each one bills against what the client has now, with Update now when they differ.
  - Quarterly recurring invoices, alongside monthly and yearly. They count as a third in monthly recurring revenue on the dashboard, the client pages, the client portal and the reports.
  - Period words: `{month}`, `{quarter}`, `{year}` and `{period}` in a recurring invoice's scope, notes or lines are filled in with the period each invoice covers, such as "Managed services November 2026", "Q4 2026" or "Nov 1 - Nov 30, 2026".
  - Invoice Now names the run it bills, whether it will be emailed and when the next one is, before you confirm. Pressing it twice, or in two tabs, bills that run only once.
  - **Unbilled Time**, under Billing and in each client's menu, with a count of what is waiting:
    - Every client with billable tickets that are done and not invoiced, with their tickets, the time logged, the hours to bill, their rate, what it comes to and how long the oldest has waited. Tick clients and make all their invoices at once - a new draft each, or onto each one's newest draft.
    - One client's tickets, each with its hours to bill filled in and ready to change before it goes on. Tick the ones to bill and make an invoice or add them to one of the client's drafts, with a line for each ticket or one for all of them. The total follows as you change the hours, rate or tax.
    - Not billable writes off ticked tickets so they leave the list. Each one's history says so.
    - Two agents billing the same tickets at once can't both bill them.
    - Expenses to bill on to clients sit beside the time and go on the same invoice - see Expenses, Billing expenses on.
  - Billing Time in Admin > Settings > Invoice: round a ticket's time to 6, 12, 15 or 30 minutes or the hour, to the nearest step or always up, with a minimum a ticket bills, and the name of the invoice line. It starts as before: the nearest 15 minutes, no minimum.
  - The ticket's Invoice window is redone. It shows the time logged by each agent and the hours that bills, with the client's rate (or the default rate) filled in, and it can add to one of the client's drafts. An open ticket can still be billed from here, with a warning that later time won't be.
  - Add Tickets on an invoice bills their time: tick the tickets, change their hours, and they go on as lines at the rate.
  - The client overview's Not yet invoiced shows what the unbilled time comes to and opens Unbilled Time. The dashboard and the Unbilled Work report link to it too.
  - **Quotes with monthly lines**, so one quote can carry the onboarding and the agreement that follows:
    - Each line is billed one time, or each month, quarter or year. Pick it under Billed on the add-a-line row, or in Edit Line.
    - The quote groups its lines under One-time, Each month, Each quarter and Each year, with a total for each, on the page, the PDF and the client's view. The top shows what it comes to up front and each month, quarter or year. A line drags within its own group.
    - Start Billing on an accepted quote: the one-time lines go on a draft invoice, dated as you choose and due on the client's terms. Each month, quarter or year part goes on a new recurring invoice from the date you pick (the first of next month to start), with its invoices emailed as they are made or left as drafts, or is added to one of the client's recurring invoices billed as often. Switch on Bill the first month on the invoice now to put the first period on the invoice too. A quote with only one-time lines keeps Make Invoice.
    - The quote shows what it was invoiced as, with a link to each invoice and recurring invoice, including any it added lines to. The invoice and the recurring invoices link back to the quote, and each history says what happened.
    - The quotes list adds the monthly part to each row and to its totals. Search, the client portal's quotes, the Send and Copy windows, the quote email and the client's Accept all show the amounts by part. The quotes export has a Monthly column, and the client export a monthly amount on quotes and how each quote line is billed.
  - **Reminders and statements**, in Admin > Settings > Invoice under Reminders and Statements:
    - Set when reminders go: any days before the due date, on the due date, any days late, then every so many days until the invoice is paid. The schedule is shown in words as you type, such as "3 days before it is due, on the due date, 7 and 14 days late, then every 30 days until it is paid".
    - A reminder before the due date is a friendly one. Once the invoice is late it is firmer, and says how many days late it is.
    - A client can be left out of reminders and monthly statements with the switch on the Billing tab of its Edit window.
    - The invoice page's payment card shows how many reminders have gone and when the last one went, and when the next one goes, or why none will. Send Reminder in the More menu sends one now.
    - Monthly statements: switch them on and pick the day of the month (the 1st to the 28th). Each client with a balance gets one, unless left out of reminders.
    - The nightly run sends at most one reminder a day for each invoice and one statement a day for each client, however often it runs. The invoice's history says who each reminder went to and how late the invoice was.
  - **Statements**: Send Statement attaches the statement as a PDF. It shows what is owed by age (current, 1-30, 31-60, 61-90 and over 90 days) and every invoice in the period with what was paid and what is left. Download PDF in the window follows the dates and options you pick, and Statement PDF is in the invoice list's menu when it is showing one client.
  - **Send windows**: the invoice, quote and statement windows take a message of your own, which goes in the email above the document, and a switch to attach the PDF. On an invoice that is still owed, the invoice window can send the invoice or a reminder.
  - **Billing emails** have a new look: your logo, what is owed and by when in a summary, the message if there is one, and a button to view and pay the invoice or accept the quote. This covers invoices, quotes, reminders, payment receipts and statements, and the notices to your team when a quote is accepted or declined or a payment is made online.
    - Receipts show the amount, method, reference and what is still to pay. A batch payment's receipt lists each invoice it was applied to.
    - Each email's entry in the history says who it went to and whether the PDF went with it.
- **Products** (rebuilt)
  - Each product or service records what it costs you, so its margin shows, along with the vendor you buy it from, their part number and notes for agents only.
  - How it is billed by default: one time, monthly, quarterly or yearly, and per user or per device. Picking it on a quote fills in Billed. On a recurring invoice, per user or per device takes the quantity from the client's count.
  - Track stock, for products: a tracked product comes off the shelf as it is invoiced and can't be sold past what is there. The rest are ordered as needed, such as drop-shipped hardware.
  - The list:
    - The top shows what catalog lines bring in each month on recurring invoices, what was sold over the last 90 days with the top seller, and what the stock on the shelf is worth at cost.
    - Tabs for Services, Products, Recurring, Stocked and To reorder, a vendor filter, and search by part number and vendor too.
    - Each row shows how it is billed, the stock and where it is kept, the vendor and part number, and the price with its cost and margin.
  - Each product has a page:
    - Its price and margin, what it sold over 12 months, and what it brings in a month on recurring invoices.
    - Its stock, where it is kept and what that is worth, and a Stock tab with every move and what was on the shelf after it.
    - Where it is used: the recurring invoices it is on, open quotes, the invoices it was sold on, and its history.
    - Notes save as you click away. Copy makes a new one to start from.
  - New and Edit are one window, with Service or Product at the top and the margin worked out as you type the price and cost.
  - Stock, for products that track it:
    - Every change is recorded as Received, Sold, Returned, Counted or Written off, with the unit cost, the invoice or expense behind it, a note and who made it.
    - One Stock window to Receive (at a unit cost, which can become the product's cost, linked to an expense from the last year, the vendor's first), Count (type what is on the shelf and the difference is recorded) or Write off (damaged, lost or stolen, used by us, or sent back to the vendor). It shows what will be on the shelf as you type.
    - Reorder at and Order on each product. Products at or under their level show on the To reorder tab, with a badge on the list, on Products in the menu and in Needs attention on the dashboard. A Low Stock notification goes out when stock drops to the level or runs out, saying how many to order and from whom.
    - Count Stock lists every tracked product, or one location's, with what ITFlow has and a box for what you counted. The difference shows as you type, Enter moves to the next box, and blank ones are left alone. Print it as a count sheet to take to the shelf.
  - Hardware becomes client assets:
    - Give a product the asset it becomes - a laptop, a switch, an access point and so on - with its make, model and warranty in months.
    - An invoice with hardware on it has a Hardware card: each line, how many of it are on the client's assets, and which ones with their serial numbers.
    - Add to Assets has a row for each one still to add, named from the model, for its serial number and who has it. Enter after a serial, or a barcode scan, moves to the next one. The make, model and warranty (counted from the invoice date) come from the product and can be changed, and the status and location are set for all of them. A row can be linked to an asset the client already has instead, or taken out and left for later.
    - Each asset made this way is bought on the invoice date with the invoice number as its order reference, and its page links to the invoice and the product.
    - The product's page shows the asset it becomes and how many are in use, with an Assets tab listing each one with its client, serial number, warranty and invoice.
    - Products has a Hardware tab, and each row says the asset it becomes.
    - The packing slip lists the serial numbers under each line.
    - The starter catalog's hardware comes with its asset type and warranty, and so does the demo data's.
  - Price changes reach recurring invoices:
    - Every change to a product's price or cost is kept, with who made it and whether it was edited, imported or came from received stock, on a Prices tab on its page. Pricing says when its price last changed and from what.
    - Lines on recurring invoices, draft invoices and draft quotes still at one of its old prices are flagged on the product's page, the Products page, each of those documents and Needs attention on the dashboard, with what updating them adds a month.
    - Update Prices lists them by product - all of them from the Products menu, or one product's, one client's or one document's. Lines at an old price are picked, ready for today's price. A price the product never had looks agreed with the client, so it is marked Own price and left alone unless picked, with the last change applied to it as the suggestion. Each new price can be changed, Catalog price or the same percent fills in a product's lines at once, and the footer says what recurring invoices bring in a month after. Invoices already sent, and quotes out with clients, keep their prices.
    - Each line changed goes in its document's history, and the document's tax and total follow.
    - Saving a new price, or importing a price list, says how many lines are at an old price.
  - Import takes every field, including Reorder At, Order, Asset Type, Make, Model and Warranty. Categories, taxes and vendors are matched by name. A new price list can update products by their code, and a file with only Code and Price will do. Download the template for the layout.
  - Export adds cost, margin, how it is billed, vendor, part number, stock and its value, the reorder level, and the asset it becomes with its make, model and warranty. It can export just what is to reorder, or just hardware.
  - Invoices, quotes and recurring invoices show agents their margin on lines from products with a cost, at today's cost. A quote shows it up front and each month.
  - Our vendors' pages have a Products tab listing what we buy from them.
  - Search finds products by part number and opens their page.
  - The starter catalog in Starter Content comes with how each item is billed. The demo data's products have costs, vendors and part numbers.
- **Expenses** (rebuilt)
  - Along the top of the list: what was spent this month against the same days of last month, the year so far with its monthly average, what the running recurring expenses come to a month, and how many expenses in the last 90 days have no receipt. Each one opens the expenses behind it.
  - Pills cut the list to expenses for a client, ones with no receipt, and refunds, each with a count. Filter by vendor, category, client and date, and under More filters by account and how it was paid. The total of everything the filters leave sits under the list.
  - Each row shows the vendor with what it was for and its reference, the category with its colour, the client, the account and how it was paid, and a paperclip that opens the receipt.
  - Clicking an expense opens its quick look:
    - the receipt right in the window when it is a photo or a PDF
    - who it was paid to, the category, account, payment method, reference and client
    - who entered it and when
    - the refunds against it
    - the stock deliveries it paid for
  - With no receipt yet, attach one right there - on a phone, take the picture.
  - New Expense, Edit, Copy and Refund are one form with an icon on every field. Picking a vendor fills in the category, account and payment method of the last expense with them, unless you changed those yourself.
  - The payment method is saved on each expense, starting from the default in Admin > Settings > Defaults.
  - Copy dates the copy today and leaves off the reference and receipt.
  - Refund starts at what hasn't come back yet and is linked to the expense, so each shows the other.
  - A receipt can be replaced or removed in Edit.
  - Bulk actions set the category, account, payment method or client on the ticked expenses, or delete them. Expenses of clients you can't see are left alone.
  - Export takes the same filters as the list, adding client, payment method and the pills, with Paid By, Receipt and Entered By columns.
  - Search finds expenses by what they were for, their reference or their amount, and opens the quick look.
  - The vendor page's Expenses tab, Transactions and a product's stock history open the expense's quick look. Client Profitability links each client's direct costs to their expenses.
  - **Recurring expenses** run on a real schedule:
    - monthly, quarterly or yearly, on the day of the month they started on - the 31st runs on the last day of a shorter month
    - with an optional end date for a lease or financing, after which they stop by themselves
  - The New and Edit windows say the schedule in words as you set it, such as "Every quarter on the 31st, starting Dec 31, 2026, until Dec 31, 2028", and fill in from the vendor's last expense like New Expense does.
  - The list shows what they come to a month (quarterly as a third, yearly as a twelfth), what is recorded over the next 30 days, how many are running, and how many need a look. Pills for Active, Next 7 days, Late, Paused, Ended and All, and filters for vendor, category and client.
  - Each row shows the amount and how often, what it comes to a month, the next date with how long until it or how late it is, when it ends, and when it was last recorded.
  - A quick look shows the schedule with the next few dates, the account and payment method, the client, what has been recorded this year, and the expenses it recorded.
  - Record Now records the next run straight away - dated today when early, or the day it was due when late - and moves the schedule on. Pressing it twice records it once.
  - A recurring expense that fell behind can record each missed run with Record Now, or skip them with Move On. Pause and Resume stop and start it.
  - The expenses it records carry its payment method and reference, and are linked to it. They show a ↻ in the expenses list and their quick look names it. Expenses Recorded lists them, and the export takes that filter too.
  - The nightly run tells the team about each expense it records, opening it.
  - The dashboard's recurring expenses, its cash flow forecast and the Expenses, Recurring Revenue and Spend by Vendor reports count quarterly ones and stop at end dates. The vendor page's Recurring tab opens the quick look.
  - The demo data's standing costs are recurring expenses on their own days, with the insurance quarterly, linked to the history they recorded.
  - **Billing expenses on** to clients - pass-through costs such as hardware, licenses or a courier:
    - Mark an expense for a client billable in New Expense or Edit, with a markup. The window shows what it will bill as you type. The default markup is set in Admin > Settings > Invoice, under Billable Expenses.
    - It waits in **Unbilled Time** beside the time. One client's page lists their expenses to bill with the cost, markup, the price to change and the margin, and Make Invoice puts the ticked tickets and expenses on one new draft, or one of their drafts. Not billable takes the ticked ones off the list. The all-clients page has an Expenses column, includes clients with only expenses waiting, and bills each client's time and expenses together.
    - Each goes on the invoice as a line of its own, named from the first line of its description, with the vendor, date and reference under it. A refund against it comes off the price first.
    - The invoice has an **Expenses** card: each expense's cost against what it billed, the margin, and the totals. Add opens Add Expenses with the client's waiting expenses and their prices. Taking one off removes its line, and so does deleting the line or the invoice - the expense is then to bill again. Once the invoice is paid it stays. A cancelled invoice keeps its line, and the expense can still be taken off to bill on a new one.
    - The expense's quick look says it is to bill, at what price and markup, or which invoice it is billed on with the margin, with Take off Invoice. The list has To bill and Billed pills, a mark on each row linking to Unbilled Time or the invoice, and Bulk Set Billable. A billed expense can't be moved to another client until it is taken off.
    - A client's **recurring expense** can be billed on each time it records one, with a markup, or be **covered by** one of the client's recurring invoices. A covered one isn't billed by itself: the recurring invoice's new **Costs** card lists what it covers, what it bills a month before tax against what they cost a month, and the margin left, with Add to set up another one it covers. The recurring expenses list and quick look show which it is.
    - Expenses to bill show on the dashboard and the client overview's attention list, and the client overview's Not yet invoiced includes them. The export has Billing and Billed On columns.
    - The demo data's latest client expenses are to bill, at 15%.
  - **Import Statement**, from the expenses menu, brings a card or bank statement in as expenses:
    - Upload the CSV your bank or card exports, and pick the account it is for and how it was paid. Lines before the header, semicolons or tabs, and amounts like (12.00), 12.00 CR or 1.234,56 are read as they come.
    - The columns are worked out for you: the date and its format, the description and a memo, the amount (charges positive or negative) or separate money out and money in columns, and a reference such as the check number. You check them once against the statement - the next statement from the same account goes straight to the review.
    - The review lists every line with the vendor, category and client it matched: what you picked for that merchant last time, or one of your vendors whose name is in it (cut short too, such as THREE RIVERS COLO for Three Rivers Colocation), with the vendor's last category. A line with no match offers a new vendor named from its text - "SQ *JOES COFFEE #123 PITTSBURGH PA" is Joes Coffee.
    - Picking a vendor fills in the other lines from the same merchant. Set a category or client for all the ticked lines at once. A line for a client can be billed on to them.
    - Lines brought in before are locked, so importing the same statement twice brings nothing in twice - two coffees on one day are still two. A line with the same amount as an expense entered by hand on that account within 4 days is left out unless ticked, and is linked to that expense so the next statement knows it too.
    - Payments to the card, deposits and refunds are left out unless ticked - then they come in as money back. Bring them in as income takes the same statement on to Import Deposits (see Income).
    - Whether charges are positive or negative is worked out from the words on the lines (DEPOSIT, ACH CREDIT, TRANSFER FROM, INTEREST against PURCHASE, WITHDRAWAL, ACH DEBIT) when they say, so a statement with mostly deposits reads the right way round.
    - Import brings in the ticked lines, making each new vendor once, and opens the expenses list on that account and those dates. The merchants are remembered for next time - see or forget them from the Import Statement page.
  - **Paid personally**: an expense an agent paid for themselves - a hotel, parts from the store - is picked as Paid personally, with who paid it. It counts as spent on its date like any other, but comes out of none of your accounts until they are paid back.
    - The list has a To reimburse pill. On it, each agent shows with what they are owed and a Reimburse button. Rows show who paid and whether they have been paid back.
    - Reimburse pays an agent back for the ticked expenses from one account, on a date, with how and a reference. The account's balance goes down then, and the payment shows in Transactions as a Reimbursement. Spending totals and reports count the expenses once, on their own dates.
    - The expense's quick look says who paid it, To reimburse with a Reimburse button, or when it was paid back. The reimbursement opens with what it paid back, and Undo (full Financial access) takes the payment back off the account.
    - Bulk Reimburse pays back the ticked expenses of one agent. Bulk Set Account puts a paid personally expense on an account instead, unless it was paid back already.
    - Expenses to reimburse show on the dashboard. The export has a Paid Personally column, and the list can be cut to one agent's.
- **Tickets**
  - The ticket list has views for Open, Mine, Unassigned, Needs reply, Waiting, Overdue, Scheduled, Closed and All.
    - Each row shows whose move it is (yours or the client's) along with SLA and due badges, and status, priority and agent change right in the row.
    - The SLA column can be sorted, the filters fold away, and the list has a proper phone layout.
  - The board view is redesigned and works in dark mode. Dragging a ticket to resolved or back follows the same rules as the ticket page.
  - The ticket page has a Details panel where you click a property to change it, with billing moved into the menu. New cards show:
    - Site: the location's address, phone, hours, access notes and directions
    - Vendor: their case number, support line, hours, SLA, account number and PIN, with copy buttons
    - Also open: the client's other open tickets
  - Replying: pick Internal note, Public note or Email reply. Nothing is picked for you, and a line says exactly who will see it. An internal note is for your team only. A public note shows in the portal but sends no email. An email reply goes to the contact and any watchers.
  - New Ticket can start from an asset or contact, and shows when they already have open tickets.
  - Recurring tickets can be paused and resumed or raised now. Their schedules are written out in plain words with a preview of the next run, and bulk actions are available.
  - The ticket lists on asset, contact, location, vendor and project pages look and work like the main list.
  - Client portal: a ticket waiting on the client says Waiting on you (see Client portal).
- **Projects**
  - The projects list has views for Active, Mine, Overdue, Due soon, Stalled, Over budget, Ready to close, Completed, Archived and All.
    - Each row shows ticket and task progress bars, time logged against the budget with unbilled hours, and due badges, and the manager can be changed in place.
    - Bulk actions can set the manager or due date, complete, reopen, archive, restore and delete. Export is to CSV or PDF.
  - The project page shows warnings when something needs a look, and stat cards for tickets, tasks, time and due date. Tickets, Tasks and Activity are on tabs, alongside the scope, the details and the team with their hours.
  - Projects now have a start date, budget hours and a client contact.
    - A template can assign its tickets to the manager. You can also add a template's tickets to an existing project.
    - Their tickets are set up as their ticket templates say, with [Client], [Project], [Month] and the like filled in (see Ticket templates).
  - You can add tickets by picking them or by number, and completed projects can be reopened.
  - Project templates in Admin keep their ticket templates in order, and can be duplicated, archived and restored.
  - Projects show on the ticket page, the client overview, the dashboard, the calendar and in search.
- **Ticket templates** (rebuilt)
  - A template sets up the whole ticket, not just its subject, details and tasks: its category, priority, billing, the agent it goes to, when it is due (so many hours or days after it is raised) and its tags. Each is left alone unless the template sets it.
  - Picking one on New Ticket fills in every field it sets, so you see the ticket before it is made and can change anything. The picker says what each template sets up and how many tasks it has.
  - The New Tickets windows on the assets and clients lists now offer templates too, as recurring tickets do - and the clients list can raise each ticket for the client's primary contact.
  - [Client], [Contact], [Asset], [Site], [Agent], [Project], [Ticket], [Date], [Month], [Quarter] and [Year] are filled in when the ticket is raised - in its subject, details and tasks.
  - Fill-ins: anything else in square brackets, like [Employee Name] or [Start Date], is asked for when a ticket is raised from the template - on New Ticket, in the New Tickets windows (once, for all of them) and on recurring tickets (once, for every ticket they raise). The answer goes in wherever its [Name] is.
  - On the template's page each fill-in can be made a date, a choice from a list or yes or no, made required, given a line of help, renamed (it is renamed in the text and tasks too) and dragged into the order it is asked in. You can add one that isn't in the text, to keep the answer on the ticket.
  - The answers stay on the ticket, in a Fill-ins card. One left blank stays in the text, and can be answered on the ticket later - it then goes in where it is still waiting, and each change goes in the ticket's history. Tickets raised by a project template are answered this way.
  - A template's task can be for one answer only: "Order a laptop" when Needs a laptop is Yes, "Assign Business Premium" when Licence is Business Premium. On New Ticket the tasks for other answers drop out as you answer. A task whose answer isn't given is kept.
  - Request forms in the client portal: a template can be offered there, under a title, a line about it and an icon of its own - "Onboard a new employee" - to everyone with portal access or the primary and technical contacts only, at every client or the ones you pick. The client answers its fill-ins (a fill-in can be kept for agents to answer instead), adds anything else and any files, and the ticket is raised set up as the template says: category, priority, billing, the agent it goes to (who is told), due time, tags and the tasks for those answers. With [Asset] in the template, the form asks which device. The template's page shows how it appears, who and where it is offered and how many requests it has brought in, and the list marks the ones in the portal. A copy of one starts off not offered.
  - Export and import: Admin > Ticket Templates exports every current template, the ones picked, or one from its page, as a file to import into another ITFlow or keep. Each goes with its tasks, fill-ins, request form, category and tags - not the agent it goes to or the clients its request form is for. Importing matches categories and tags by name and makes the ones missing, and one already here by name is left as it is, added as well under a name of its own, or replaced - keeping its tickets, its agent and its request form's switch and clients. A request form comes in turned off.
  - The MSP starter templates in Starter Content are rebuilt on all of this: each asks what it needs (New Hire Onboarding asks the start date, job title, manager, whose access to copy and whether they need a computer or a phone extension), is set up with its category, priority, tags and due time, adds the tasks for the answers given, and the ones clients raise come with a request form - Onboard a new employee, Someone is leaving, Ask for a new computer and Report a suspicious email. Two new ones, Software Request and Access Change Request, have request forms too. An install that added the starter templates before can bring them up to date with Update - their tickets, agent and request form switch stay.
  - A ticket keeps which template it came from, and its history says so. Admin > Ticket Templates shows what each sets up, its tasks and their time, how many tickets it has raised and the recurring tickets and project templates that raise it. A template's page shows the ticket it starts with its placeholders marked, its tasks (added with their time, dragged into order) and its latest tickets, and New ticket from it opens New Ticket with it filled in.
  - Templates can be copied, archived - not offered for new tickets, while the recurring tickets and project templates that use them keep them - restored and deleted.
- **Assets**
  - The asset list has:
    - type icons, colored status pills and OS icons
    - Web, SSH and RDP connect buttons, warranty warnings and rack position
    - type tabs with counts, including a Printers tab, tag search and more sortable columns
  - The asset page has a header with the photo, status, tags and connect buttons, then Network, Hardware, Where & who and Quick notes cards. Related records sit on tabs.
  - Add, Edit and Copy share one form in tabs (Details, Assignment, Network, Purchase, Login or History, Notes), with a DHCP switch and photo removal.
  - Network interfaces have their own form:
    - the IP address is checked against the network, with next free IP and VLANs
    - one port can be connected to another, and any interface can be made primary
    - switches, firewalls and access points get a port map, and a whole run of ports can be added at once
    - interfaces can be imported and exported with their VLANs and connections, and edited in bulk
- **Racks**
  - The rack page shows the front and rear of the rack to scale. Click an empty U to add something there.
  - Items can be:
    - mounted front or rear, at full or half depth
    - non-asset items: patch panel, cable management, shelf, PDU, UPS, KVM or blanking panel
    - edited and moved
  - Space and power used are shown, along with the largest free gap. Racks can have front and rear photos, number U1 from the top or the bottom, and have a print view.
  - A Not racked yet list shows what still needs a place. Racks also appear under All Client Docs, and an asset's page shows where it is racked.
- **Contacts**
  - The contacts list has role tabs with counts, avatars, role pills, and copy on phone, mobile and email. Location changes in place, and there are chips for linked assets, credentials, licenses and tickets, plus a tag filter.
  - The contact page has the same layout as the asset page, with reach details, notes and tabs for everything linked to them.
  - Add and Edit share one form in tabs (Details, Roles, Access, Notes), with role switches, a password generator, a duplicate email check and photo removal.
  - A contact's avatar now shows everywhere they appear, in the same color throughout. Ticket contact pickers show their title and role under the name.
- **Files and documents**
  - Files has:
    - a folder tree with counts, and All, Documents and Uploads tabs
    - color-coded file type tiles, with shared and portal markers
    - search across every folder that shows where each result lives
    - a grid view with real previews (image thumbnails, the first page of a PDF, text excerpts), and a previewer you move through with the arrow keys
  - Drag files onto the list to upload them into that folder. Move, archive, restore and delete files and documents together in bulk.
  - The document page has Edit, Share, PDF, Print, Rename, Move, Link, client portal visibility, Archive and Delete. Its History card lets you view, restore or delete versions, and restoring keeps the current content as a version.
  - Files have a quick look with a preview, the folder, who uploaded it, sharing and links.
  - The upload window has a drop box, a file list and size checks, and it tells you about any file it skipped.
  - Documents and files can now be linked to locations and vendors.
  - Quote and ticket attachments show their file type. The client portal documents list shows the folder, summary and last updated date.
- **Licenses**
  - The license list has Renewing soon, Expired, Over seats and No seats assigned tabs. Each row has a seat bar (red when over) and a color-coded renewal date, and you can Renew, Archive, Restore and Delete in bulk.
  - The license page has the key, order reference, vendor account and support details, all copyable. Seats, Keys, Credentials, Documents, Files and History are on tabs.
  - Individual keys can be pasted one per line and handed out to devices and users. A key goes back to the pool when its device or user comes off the license.
  - The Renew window, for one license or many, moves the renewal date by a term or to a set date, previews each new date, and records the new order reference.
  - Renewal Ticket opens New Ticket already filled in. The New form has the template picker built in.
  - Asset and contact pages list their licenses with the key to install. The client overview flags licenses over seats, and search finds licenses by name, vendor, order reference or key.
- **Locations**
  - The location page has:
    - Directions and Call buttons
    - New ticket, contact, asset, network, rack, document, upload or trip, already set to that location
    - Move here, to move contacts, assets, networks and racks to it in one go
    - Getting there, Ask for and Access notes that save when you click away, and tabs for everything at the location
  - The locations list puts the primary location first, with a photo or map pin, directions, phone and hours, the on-site contact and counts of what is there.
  - The form has tabs, and a client's first location becomes primary by itself.
  - The export adds the primary flag, on-site contact, counts and access notes.
- **Vendors**
  - The vendor page is built around calling them:
    - Call support dials the extension, alongside Email, the support site and a star
    - a New menu for a case with them, a license, an asset, a portal login, a document, an upload, or an expense for your own vendors
    - Support and Account cards, with the PIN hidden until you show or copy it, and the account manager's direct line and email
    - Also used by, listing your other clients with the same vendor
    - tabs for Cases, Licenses, Assets, Domains, Services, Logins, Documents, Files and History
    - spend this year, last year and in total for your own vendors
  - The vendors list puts starred vendors first and shows the support line, hours, SLA, account number and PIN. An Ours / Clients' switch is included, and All Client Docs has a view across every client.
  - Starting a vendor from a template fills in every field. Add to Clients rolls a template out to many clients at once, and Admin > Vendor Templates shows how many clients use each one.
  - Vendors can be tagged.
- **Credentials**
  - The list has:
    - username copy and password show and copy, and a live 2FA code fetched when you ask for it
    - where each one opens and what it is for, plus password age and when it was last used
    - filters for Starred, With 2FA, No 2FA, Not changed in 90 days and Shared
  - The form has a password generator (passphrase or random) and a strength meter. The 2FA secret takes spaces or an otpauth:// link, and shows the current code so you can check it.
  - Change password dates and logs the change. Share is in each row's menu and the quick look (see Shared links), and Ask the client for a login is in the list's top menu.
  - The client overview lists passwords due for a change, and the rotation report lets you pick the time span.
- **Domains**
  - The domain list has:
    - expiry pills (red when expired, amber when close, blue when close but set to auto-renew)
    - the registrar and hosts, and SPF, DMARC and DKIM pills
    - filters for Starred, Expiring in 45 days, Expired, No auto-renew and Email gaps
  - The domain quick look explains the email checks in plain words. It also has A, NS and MX records to copy, the registrar and host logins, and the raw WHOIS.
  - You can add several domains at once from a pasted list. A pasted web address is tidied down to the domain, and the name is checked as you type.
  - Bulk actions can look up now, set the registrar or host, tag and star.
  - The background check now looks at up to 50 domains a run, soonest to expire first. Expiry alerts mention auto-renew.
  - The client portal shows days to expiry and auto-renew.
- **Certificates**
  - Check now connects to the host and records what it serves: the issuer, the dates, the names covered and the fingerprint. The result is OK, name mismatch, untrusted, expired or unreachable.
  - The form has a Check button to see what the host serves before you save. For a host ITFlow cannot reach, paste the certificate or enter the issuer and expiry by hand.
  - The list has All, Starred, Expiring soon, Expired, Problems and Not checked views, and can check in bulk.
  - Certificates valid for 100 days or less, such as Let's Encrypt, renew themselves. They only count as expiring soon inside 20 days, and skip the 45-day alert.
  - Certificates are checked daily, with a notification when a good one goes bad. The client overview has a Certificate problems box.
- **Services**
  - The services list has Needs attention, Review due, High importance, No review set, Starred and Archived views. It shows what each service depends on, and has bulk actions.
  - The service page covers what it is, backup and recovery, who to call, and notes. Tabs list the assets it runs on, logins, domains and certificates, documents, tickets and history.
  - Services can have a review schedule. The Review window has a checklist, and reviews due show on the dashboard and calendar, and in the nightly notifications.
  - A service needs attention when a domain or certificate it uses is expiring, a certificate check fails, a device it runs on is out of warranty or out of action, or something linked to it has been archived.
  - Report a problem opens a ticket linked to the service, and tickets can be linked to services from either side.
  - Services can be archived instead of deleted.
- **Calendar**
  - The calendar only loads the dates on screen. Each calendar can be shown or hidden, along with scheduled tickets, project dates, service reviews, recurring tickets, renewals and invoices due.
  - You can filter to one agent, open a quick look by clicking an item, and drag tickets and events to reschedule them. A To schedule panel lets you drag open tickets onto the calendar.
  - Events can:
    - repeat every few days, weeks, months or years, with an end date and single dates skipped
    - have a client site, a location or a meeting link
    - invite agents, contacts and guest emails, with invitations, updates and cancellations sent as real calendar invites
    - be downloaded as a calendar file, or have the invitation sent again
  - Every agent has a personal subscription link for their phone or mail app, with setup help for Google, Outlook, Apple, Nextcloud and Thunderbird.
    - It can show their own schedule or the whole team's, with a busy-only option.
    - It follows their role and client access, and stops working when the account is disabled.
  - Repeating events keep their time across daylight saving changes in subscriptions and invites.
  - The ticket schedule window shows the assigned agent's day.
- **Networks**
  - The networks list has type tiles and filters, usage bars, and the subnet, gateway, DNS and DHCP at a glance. Search finds the network an IP address belongs to.
  - The network page has an address plan:
    - documented addresses and device interfaces merged into one list
    - free ranges with a Use button, plus the DHCP range, gateway and next free IP
    - flags for conflicts, addresses in the DHCP pool, MAC mismatches, held-back addresses and addresses outside the subnet
    - a notice when the network overlaps another one, and WAN provider details
  - The form guesses the type from the name and shows live subnet details, with gateway and DHCP suggestions and an overlap check.
  - Documented addresses can be fixed, a DHCP reservation with its MAC, held back or floating. Up to 256 can be added as a range, and import and export include the type and MAC.
- **Users and your account**
  - Admin > Users has:
    - Users, Admins, No MFA, Disabled and Archived views, with a role filter
    - status and MFA pills, remembered browsers and a client access summary
    - the last sign-in with its device and IP, and a count of agent seats
  - Bulk actions can change role, require MFA, forget remembered browsers, activate and disable.
  - New and Edit User share one form in tabs: Details, Sign in and Client access. It has generated passwords, a picture preview, and Only or Never rules per client with search and Set all.
  - Archiving a user hands their open tickets to another agent. Restoring asks for a new password. The export has a role filter and MFA, last sign-in and client access columns.
  - Your account is split into Profile, Security, Preferences and Activity.
    - The MFA QR code is drawn on the page itself.
    - You can see your remembered browsers and forget one or all of them.
    - You can set how many rows a list shows per page.
- **Trips** (rebuilt)
  - Along the top of the list: the miles this month against the same days of last month, the year so far, your own this month, and what is owed for driving their own vehicles (with no mileage rate set, how much of the year's driving was for clients).
  - Pills cut the list to your own trips, trips for a client, trips for a ticket, our own errands, to pay back and to bill, each with a count. Filter by driver, client and date. Search finds what it was for, where from and to, the client, site, driver and ticket number. The total of everything the filters leave sits under the list.
  - Each row shows the route with the ticket and what it was for, the client and their site, the driver as an agent pill, and the miles with marks for a round trip, their own vehicle (paid back or not) and billing (billed or not).
  - Clicking a trip opens its quick look:
    - the route, with Directions in Google Maps from where it started, and the miles each way for a round trip
    - the driver, the client, their site and the ticket, each linked
    - the odometer readings, and who logged it and when
    - how many trips have gone to that site, opening them
    - what it pays back or was paid, and what it bills or was billed on, with Pay Mileage and Take off Invoice
  - From the quick look or a row's menu: Edit, Copy (dated today), Return Trip and Delete. Return Trip is the way back, from there to where it started, one way and on the same day.
  - New Trip, Edit, Copy and Return Trip are one form with an icon on every field:
    - Pick the client to choose from their sites and their tickets (open ones, and those done in the last 60 days). A site fills in its address as where to. A ticket fills in what the trip was for, and its site.
    - From starts where your last trip started, and offers the places you start from most.
    - Enter the miles one way and switch Round trip on or off. The form shows what the whole trip comes to.
    - A route you have driven before fills in its miles.
    - Odometer readings are optional. With both, the trip is what the odometer says.
  - Tickets: Log a trip in the ticket's menu starts with its client, site and subject. An onsite ticket, or one with trips, has a Trips card listing them with the miles driven for it.
  - Locations: the location page has a Trips tab with the trips out to it and Log Trip.
  - The Mileage report shows each trip's ticket and site under its purpose, a trip's date opens it, and the CSV has Site and Ticket columns. Its rate starts at the mileage rate below, and it is in miles or kilometers as set.
  - Bulk actions set the driver, the client, the vehicle or billable on the ticked trips, pay one driver's mileage, or delete them. Changing the client takes off the old client's site and ticket.
  - Export takes the same filters as the list, with Driver, Round Trip, One Way, Site, Ticket, the odometer readings, Vehicle, Paid Back and Billing as columns.
  - Trips follow their ticket. Merging a ticket with its replies moves its trips to the ticket it goes into, and moving it to another client takes its trips along. Deleting a ticket or a location keeps the trips, without the link.
  - Admin > Settings > Invoice has Trips and Mileage:
    - miles or kilometers, for every trip, rate and report
    - what an agent is paid back for each mile in their own vehicle, such as the IRS standard rate, and the expense category it goes under
    - what a trip bills a client: a trip charge, a rate a mile, and how far the charge covers before the rate starts
    - the name of the invoice line
  - Paying agents back for their own vehicle:
    - The form has In their own vehicle, starting as the driver's last trip was, and shows what the trip pays back.
    - The list's To pay back pill and card show what is owed (Owed to you, for an agent without Financial access), with each agent and what they are owed above the list.
    - Pay Mileage pays one agent for the trips they tick, at the usual rate or another for trips from before it changed. Each client's trips become a mileage expense the agent paid personally, under the mileage category and to the vendor Mileage (made the first time), so each client's costs include the driving. Pay it back now pays them from an account there and then; otherwise the expenses wait in To reimburse with anything else they paid for.
    - Once paid for, a trip keeps its distance, driver and vehicle, and can't be deleted. Deleting the mileage expense makes its trips to pay back again. The expense's quick look lists the trips it pays for.
  - Billing trips to clients:
    - The form has Bill the client for it, starting as the ticket is billable, and shows what the trip bills.
    - The list's To bill pill shows the trips waiting. Unbilled Time has Travel to bill beside the expenses, each at its price to change, going on the client's invoice with their tickets and expenses, a line each. Billing every client at once takes their trips too, and Not billable takes a trip off the list.
    - The invoice has a Travel card with the trips on it, Add for the client's trips waiting, and Take off Invoice. Deleting the line or the invoice makes a trip to bill again.
    - Once billed, a trip keeps its distance, client and billable, and can't be deleted.
  - The dashboard shows Trips to bill and Mileage to pay back, the client overview Trips to bill, and the Unbilled Time count in the menu counts trips.
  - The demo data logs a trip to the site for each onsite ticket fixed in the last six months. The last six weeks of driving are in the agent's own vehicle, and a billable ticket's trip in the last month is billable when trips have a charge or rate.
- **Income** (rebuilt)
  - Income is counted the same way everywhere - the Income list, the dashboard, the client overview, Income Summary, Profit & Loss, Income by Client and Client Profitability: invoice payments, and other income (money in that isn't for an invoice) whether it has a category or not. Money moved between your own accounts is left out.
  - Along the top of the list: what came in this month against the same days of last month, the year so far with its monthly average, what the running recurring invoices bill a month, and what is still owed on invoices with how much of it is late. Each one opens what is behind it.
  - Pills cut the list to invoice payments, other income, what was paid online and what has no category, each with a count. Filter by client, category and date, and under More filters by the account it went into and how it was paid. Search finds what it was for, an invoice number, a reference such as a check number, an amount, the client, category or account. The total of everything the filters leave sits under the list.
  - Each row shows the invoice and what it was for (or what the other income was), how it was paid and its reference, the category with its colour, the client, the account, and the amount with a mark when it was paid online.
  - Clicking a payment opens its quick look:
    - the invoice it paid, its status and due date, what it came to, what has been paid on it in all and what is still owed
    - the other payments on the same invoice, each opening its own
    - the client, the invoice's category, the account, how it was paid and the reference
    - for a payment made online, the payment provider's fee and what came in after it, when the fee is in the expenses
    - Open Invoice, Email Receipt (to the client's billing contacts again), Edit and Delete
  - Clicking other income opens its quick look: what it was with the rest of the description as notes, the client, category, account, how it was paid, the reference, who entered it and when, and what has come in from that client this year. Edit, Copy and Delete.
  - New Income, Edit and Copy are one form with an icon on every field. Pick the client it came from - a rebate passed on to a client, a deposit held for them - and it counts for them in Income by Client and Client Profitability. The amount shows the account's currency, and can be negative for money that went back out. Every field is checked when it is saved: a real date, an amount, an account and an income category. Copy dates the copy today and leaves off the reference.
  - A client's Income page has the same cards and pills for that client, with New Income starting with them.
  - Bulk actions set the category, account, payment method or client on the ticked rows, or delete the other income among them. Each window says what it will leave alone.
  - Export takes the same filters as the list, including the pills, with Invoice and Paid Online columns.
  - Income Summary and Profit & Loss list No category as a line, opening those on the list, and their Invoice payments and Other income figures open those pills. Accounts link to Transactions and the income into each account. Search finds other income by what it was, its reference or its amount, and payments by their reference, opening the quick look.
  - The demo data adds the money an MSP takes in besides invoices: a distributor's partner rebate each quarter, interest on the savings account and the odd referral fee.
  - **Import Deposits**, from the Income list's menu, brings the money in on a bank statement in as payments and income:
    - Upload the CSV your bank exports, and pick the account the deposits went into and how clients usually pay. It is the same file Import Statement takes for expenses, read the same way, and the columns are kept on the account for both: a statement already brought in for its expenses goes straight to the review, and either side can go on to the other without uploading it again. Only the money in is brought in here.
    - Each deposit is matched to what it is:
      - an invoice number in its text or reference ("MOBILE DEPOSIT INV-1042")
      - what the same payer was last time - a client's payment, other income in its category, or a transfer
      - a client's name in it ("ACH CREDIT RAVENWOOD DENTAL GRP")
      - the only invoice owing exactly that much, the oldest of one client's invoices owing it, or everything one client owes together
      - a payout from the payment provider, which comes in as a transfer from the account its online payments go into, and a transfer from your savings or another of your accounts
      - anything else is other income, for you to give a category and the client it came from
    - A payment pays the client's invoices the oldest due first. One check for several invoices pays them all, and a deposit short of what they owe leaves the last one part paid. Pick the client and the invoices it pays are ticked for you - the one owing the amount, the ones that add up to it, or the oldest first - and each deposit says what it pays off, in full or part paid, or how much more than they owe it is. Two deposits on one invoice don't pay it twice.
    - Deposits brought in before are locked, so importing the same statement again brings nothing in twice. A deposit with the amount of a payment, other income or transfer entered by hand on that account within 4 days - or of a day's payments together, one check entered as a payment on each invoice - is left out unless ticked, and is linked to what was entered so the next statement knows it. A payout into the account the payment provider's payments go into is recorded already, so it is left out.
    - Import brings in the ticked deposits as payments (each invoice paid or part paid, with its history saying so), other income and transfers, and opens the Income list on that account and those dates. Email each client their receipt sends the usual payment receipt for each invoice paid. Every ticked deposit is checked first, so nothing comes in until they are all right.
    - How it was paid is read from the text - ACH, a check, a wire - when one of your payment methods fits, or the one picked on upload.
    - The payers are remembered for next time - see or forget them from the Import Deposits page.
    - A payment or other income brought in this way says so on its quick look.
    - Payments need write access to Sales, as adding one on an invoice does. Without it, deposits can still come in as other income and transfers.
- **Accounts and transactions** (rebuilt)
  - Each account has a kind - checking, savings, credit card, cash, payment processor or other - with its own icon wherever the account is picked. A credit card's balance runs below zero as it is spent on, and shows as what is owed on it ("$1,240.00 owed") on the Accounts page, its ledger, the dashboard and every account picker.
  - Along the top of the Accounts page: what the accounts hold (cards aside), what is owed on cards, and the money in and out this month against the same days of last month. Money moved between your own accounts doesn't count as in or out.
  - Each account shows its description and the defaults it carries (payments go in, expenses paid from, transfers from or to, or a payment provider's payments), its kind and currency, what it holds with its opening balance, what came in and went out this month, and its latest line with how many there are. Its menu has Transactions, Income into it, Expenses from it, Edit, Pay the Card for a card or Transfer from it otherwise, and Archive once it holds nothing. The Archived pill lists archived accounts, each with Restore, and Delete for one never used.
  - New Account and Edit are one form with an icon on every field: the name (unique among open accounts), the kind, the currency, the opening balance, a description and notes. The opening balance can be changed later, and the change is logged. The currency is locked once money is recorded on the account. For a card, an opening balance below zero is what was owed on it.
  - Transactions is each account's ledger. Pick an account at the top of the filters, or Every account:
    - The cards show what it holds now (or owes, for a card), the money in and out over the dates picked, and the difference, with what it held at the end of those dates.
    - Every line has its date (opening its quick look), what it was - the invoice paid, the other income, the vendor, the transfer's other account or the agent paid back - with its kind, how it was paid, the reference and a mark when it came from a bank statement, then the category, the client, In, Out and the balance after it. The balance shows for one account sorted by date. With Every account, an Account column takes its place.
    - Pills for Money in, Money out and Transfers, each with a count. Filter by the date, category and client, and under More filters by the kind of line, how it was paid and an amount range. Search finds what it was, the description, the reference, the category, the client, a transfer's other account, how it was paid, or an amount. The totals in and out sit under the list.
    - Export takes the same filters and pills, for one account or every account.
  - Transfers keep their place in the menu, which opens the Transfers pill for every account. Each transfer shows once there, from the account it left, and the cards show what was moved, what paid off cards and what payment processors paid out.
  - New Transfer, Pay the Card and Edit are one form: where it comes from and where it goes, each with what it holds now, a Swap button to turn it round, the amount in the currency of the account it leaves, the date, how it was moved and notes. Every field is checked when it is saved: two different open accounts, an amount, a real date and one of your payment methods. Editing moves both sides together.
  - A transfer into a credit card is a card payment, and says so on the ledger and in its quick look. Clicking a transfer opens its quick look: what came out of one account and went into the other, with what each holds now and a link to its ledger, how it was moved, who entered it, notes, and Edit and Delete. A transfer's expense opens it too.
  - **Deposits** out of a cash account: a new transfer out of a cash account lists the payments and other income taken into it that haven't gone to the bank yet, oldest first, each with its date, what it is for, the client, how it was paid and the amount. Tick the checks and cash going to the bank, and the amount and notes fill in, until either is typed over. What is ticked goes with the transfer and isn't offered again. A transfer out of it with nothing ticked - every one made before the update, and any made since without ticking - counts as taking everything up to it, so only what came in after it is listed.
    - A deposit's quick look lists what was in it, and says so when they don't add up to the amount. The quick look of a payment or other income in one says which deposit it went in, opening it.
    - Deleting the transfer, or changing it to come out of another account, puts what it carried back to waiting. So does moving one of them to another account, with Edit or Bulk Set Account.
  - The dashboard's accounts open their ledgers, and a card shows what is owed on it.
  - Adding and changing accounts and transfers needs write access to Financial, and deleting full access, as before. Restoring an archived account needs write access.
  - The demo data adds a Business Visa for software, hosting, supplies, meals and the other small costs, paid off from Operating Checking each month.
  - **Reconcile**, from an account's menu on the Accounts page or Transactions, checks the account against its bank or card statement:
    - Enter the statement's ending date and balance (for a card, what the statement says is owed), with notes such as the statement's number. It starts from where the last statement ended.
    - The first statement on an account starts from the bank's balance or the opening balance:
      - From the bank's balance, enter the day the statement starts and the balance it starts from (for a card, what was owed then). Everything on the account before that day is brought forward as reconciled - cleared and locked as if a statement had been reconciled to the day before - so an account with years of history starts matching the bank from that statement on, without ticking its past. This is picked for you when the account has lines from before the statement's month.
      - From the opening balance, every line since the account was opened is listed to tick, as before.
    - Where ITFlow had the account at a different balance from the bank's on the day it was brought forward to, the statement says how far apart they were. Correct the opening balance moves the account's opening balance by that much, once, so ITFlow's balance matches the bank's from then on. Leave it, and the statements still reconcile from the bank's balance.
    - Brought forward shows in the history with everything before the day it covers, and what the opening balance was corrected by or how far ITFlow was off. While it is the latest and nothing is in progress, Undo takes it away, frees its lines and puts the opening balance back.
    - Every line on the account up to the statement's date that hasn't cleared yet is listed, oldest first. Tick each one the statement shows. Lines brought in with Import Statement or Import Deposits start ticked.
    - The cards show where it started, the statement, what is ticked (cleared) and the difference, and the bar at the bottom keeps the difference and Finish in view down a long statement. Finish opens once the difference is nothing.
    - Pills for Money in, Money out, Not ticked and Ticked, a search by who, reference or amount, and Tick all shown and Untick all shown for what they leave.
    - Ticks are saved as they are made, so a statement can be left and finished later. Change the statement's date or balance from its menu, or Discard it.
    - Add what the statement has and ITFlow doesn't - a bank fee, interest - with New Expense, New Income or New Transfer from its menu, starting on the account.
    - With nothing in progress, Reconcile shows where the account is reconciled to, how much hasn't cleared yet, and the statements reconciled before - each with its ending balance, the lines it cleared, what came in and went out, and who reconciled it when. The latest can be undone, which puts it back in progress with what was ticked.
  - On Transactions, a line cleared on a statement has a tick by its date, opening the lines that statement cleared - or saying it was brought forward. The Uncleared pill lists what no statement has cleared yet, and the export has a Cleared column with the statement's date. The Accounts page shows where each account is reconciled to, or that one is being reconciled.
  - A cleared line's quick look says which statement it cleared on, and its Edit window keeps its amount, date and account as they are (see Breaking Changes, Accounts, transactions and transfers).
  - Reconciling needs write access to Financial, and undoing a reconciled statement full access. Agents with read access can look at the statements.
  - The demo data reconciles Operating Checking and the Business Visa to each month's statement through last month.
- **Client portal** (rebuilt: the look, home, tickets, billing, Your IT and accounts)
  - A new look: a light top bar with your logo, or your company's initials on your theme colour, and the sections the contact can open - Home, Tickets, Billing for billing contacts, Your IT for technical contacts, and your custom links - with New ticket and their account menu. On a phone the sections sit in a bar along the bottom and a menu that slides in. Contacts can switch to dark mode from their account menu, and it stays that way. The welcome banner along the top of every page is gone.
  - Home shows what needs the contact first: tickets waiting on their reply or resolved for them to confirm, tasks waiting for their approval and invoices past due. Then their open tickets, what is owed with Pay invoices, the domains, certificates, licenses and warranties renewing in the next 45 days or lapsed in the last 30, the devices they use with any warranty that has run out or is about to, and your phone and email when they are set.
  - Tickets has tabs for Open, Waiting on you, Resolved, Closed and All, each with its count, a search by subject or number, and 20 to a page. Primary and technical contacts see their whole company's, with who raised each, and Mine for their own.
  - A ticket shows how far it has got - received, in progress, resolved, closed - and the conversation oldest first, with the contact's own messages on the right and the files that came with each. Tasks waiting for their approval have Approve. Alongside are the priority, category, who raised it, who is working on it, the device and how many tasks are done.
  - Replies take files dropped onto the form or chosen, listed before they are sent. It's fixed marks a ticket resolved once its tasks are done, a resolved ticket asks them to close it or reopen it, and a closed one asks how you did.
  - With request forms offered to the contact (see Ticket templates), New ticket starts with a choice: Report a problem, or one of the forms. Home lists them under Ask for something, and a ticket raised from one shows its answers under Request details.
  - New Ticket asks how much it is getting in the way, what it is about and which device - their own, or any of their company's for primary and technical contacts - and takes screenshots and files. Alongside it is what happens next, and your phone number for anything urgent when it is set.
  - Invoices open in the portal, with tabs for To pay, Paid and All, each with its count, a search by number or scope, and what is owed - and how much of it is late - under the title. An invoice shows as it prints, with the amount due and when, Pay, Download PDF and Print, the payments made with a receipt for each, and the recurring invoice it came from. When autopay will pay it, it says which card and when. Opening a sent invoice marks it viewed, with the contact's name in its history.
  - Pay invoices: the contact ticks the invoices to pay - all of them, or just the one when they come from its Pay button - and pays them together as one card payment, with a saved card there and then or a new card on a checkout page. The total and the Pay button follow the ticks, and your Stripe threshold applies to the total. Each invoice gets a payment for its part with the same reference, the client gets one receipt listing them all, and your paid notification address one email.
  - Quotes has tabs for Waiting for you, Accepted and All. A quote shows as it prints, with what it comes to up front and each month, quarter or year. Accept and Decline open a window for the contact's name and a note for you - a PO number, when to start, what would change their mind - and both go in the quote's history, the notification and the email to your quote notification address. Accepting asks them to confirm they can accept it for their company. An accepted quote takes a signed copy or purchase order, and one that has run out offers to ask for an updated quote.
  - Payments lists every payment on the account, with one card payment over several invoices as one line. Its receipt shows what was paid, when and how, and each invoice it paid with what is still owed, ready to print.
  - Statement shows what is owed by how late it is - current, then 1-30, 31-60, 61-90 and over 90 days - with the open invoices, or everything from the last 12 months with the paid ones. Download PDF is the same statement you email.
  - A recurring invoice shows what it bills line by line, when the next invoice comes, its autopay card and the invoices it has made.
  - Cards & autopay: the saved cards, with expired ones marked and the recurring invoices each one pays, adding a card in Stripe's form straight after agreeing to it, removing one, and autopay for each recurring invoice, all on one page.
  - Home lists quotes waiting for an answer, and a past-due invoice opens that invoice. Ask us on an invoice, quote or recurring invoice starts a ticket with the subject filled in.
  - Pay invoices and Cards & autopay only show when Stripe is set up. Without it, an invoice says to use the payment details on it.
  - Devices has tabs by kind - Computers, Servers, Network, Phones & tablets and Other - each with its count, Everyone's or Mine, and a search by name, make, model, serial or who uses it. Each shows who uses it and where it is, with its warranty when it has run out or is about to.
  - A device shows its make and model, serial, OS, who uses it, where it is, when it was bought and how old it is, its warranty, and its open tickets and licenses. Report a problem starts a ticket with the device picked, and Open goes to its link when it has one. Any contact can open the devices they use from Home.
  - Licenses has tabs for All, Renewing soon (the next 30 days) and Expired, with how many seats are in use and when each renews. License keys are not shown.
  - Domains & certificates: the domains with their registrar, DNS, web and mail hosts, when each expires and whether it renews by itself, and on its own tab the certificates with their domain, issuer and expiry.
  - Documents lists the documents you share with the client, in tabs by folder, with a search that looks inside them. A document shows as written, with its tables and colours, and the files that came with it - a PDF or picture shown on the page, any file to open or download - ready to print, and Ask us about it starts a ticket. Primary and technical contacts can write a new document in the editor or upload a file (PDF, Word, OpenDocument, RTF or text), and you are notified of each.
  - Contacts lists everyone at the company, the primary contact first, with their roles, and a search by name, email, title, department or phone.
  - The primary contact manages their company's contacts: Add a person with their details, phone numbers and roles, and give them portal access with an emailed invite to set a password - or to sign in with Microsoft 365 when that is set up. A person's page shows whether they can sign in and when they last did, with Email the invite again, Turn off access and Left the company?, which turns off their access and starts an offboarding ticket. Each change is in the audit log, and you are notified when someone is added, given access, turned off or has left.
  - Home: the renewals open the domain, certificate, license or device they are about, and each device opens its page.
  - Your account: who the contact is to you, with Ask us to change them; their phone numbers; the emails they get; signing in - changing their password, two-step sign-in and the browsers it remembers; their phone PIN; and their recent sign-ins with the device and address. Activity has tabs for signing in and changes, 25 to a page.
  - Two-step sign-in: a contact who signs in with a password can turn on a code from an authenticator app - Microsoft or Google Authenticator, 1Password, Authy, Bitwarden - by scanning a QR code. Signing in then asks for the code, and can remember the browser for as long as your Remember Me setting. Five wrong codes in 10 minutes stop that account for a while. Microsoft 365 sign-ins are left to Microsoft.
  - Admin > Client Portal can require two-step sign-in: a contact signing in with a password who hasn't set it up is taken to set it up before anything else. On the agent's contact page, Turn off two-step sign-in is there for a lost phone.
  - Emails from us: a contact can have every ticket email, or replies and fixes only - not the emails saying a ticket was raised or closed. Primary and technical contacts can also hear about their company's other tickets: new ones (a short email with who raised it and a link), or new ones and every update, as a watcher would. Raised in the portal, by email or by an agent, while client notifications are on (Admin > Ticket).
  - Forgot password and the invite page are in the new look. A new password is typed twice, and the contact gets an email when it is set. Forgot password sends one email per account every 5 minutes, and answers the same whether or not the address has an account.
  - Announcements: Admin > Client Portal posts a notice at the top of the portal's home page - to every client or one - as a Notice, Heads up or Urgent, from when to when you choose. Web addresses in it become links.
  - View as contact: on a contact's page, View portal as them opens the portal in a new tab as they see it, read only - nothing can be sent, paid or changed, opening an invoice doesn't mark it viewed, and a strip along the top says whose view it is, with Exit. It needs read access to clients and support and to the client; billing shows only when your role reads sales and financial too. Each one is in the audit log.
  - The agent's contact page shows whether a contact signs in with a code too, when they last signed in, and the ticket emails they chose.
- **Taxes** (rebuilt)
  - Admin > Taxes lists each rate with what it comes to, its parts, its registration number and what uses it: the invoices, quotes and recurring invoices with a line at it, and the products and clients that pick it. Current and Archived tabs, with what replaced a rate and when, Restore, Make default, and Delete for a rate nothing has used.
  - A rate can be made of parts, such as a state's 6% and a county's 1%, or GST and QST. A part can be charged on top of the parts above it. The whole rate shows as you type. Invoices, quotes, their PDFs and the client's views show each part on its own line in the totals, such as Pennsylvania 6% and Allegheny County 1%.
  - Rates go to four decimal places, such as Quebec's 9.975%, and lines and the Tax Collected report show the exact rate.
  - Each rate takes a description and a registration number, and each part can have its own number. The numbers a document charges under print with your company's details on the invoice or quote and its PDF, such as GST No. 123456789 RT0001.
  - Edit on a rate that has gone out on invoices or quotes says how many, and that a new rate or parts makes a new rate from now on, with a switch to move the draft invoices and quotes too.
  - How lines are taxed, on the same page: the default rate for new lines, whether billed time, trips and expenses billed to clients are taxed, and whether discounts come off before tax.
  - Each client can have its own rate, on the Billing tab of its Edit window, for where it is. New lines start on the client's rate, then the product's, then the default, whether typed in, picked from the catalog, or made from tickets, Unbilled Time, trips and expenses. A product set to no tax stays untaxed. Unbilled Time bills each line at its client's usual rate unless you pick one, and the ticket's Invoice and Quote windows, Add Tickets, Add Expenses and Add Trips start on it.
  - Tax exempt clients, with the certificate number and when it expires, on the Billing tab. Their new lines start untaxed, and their pages say Tax exempt, or that the certificate has run out. An open invoice, quote or recurring invoice that still has taxed lines for them says so, with Take the tax off. An invoice or quote with no tax on it says Tax exempt with the certificate number under the client's name.
  - The client overview's Terms shows the client's rate, or that it is tax exempt.
  - Tax Collected (Reports) is rebuilt for filing:
    - Invoiced counts each invoice once it is sent, in the month of its date. Paid counts each payment with its share of its invoice's tax and sales, so a part-paid invoice counts only the part paid.
    - Tax shows by part of each rate, with its registration number, so a state's and a county's tax show apart. Name a part the same on two rates and they add up together.
    - Sales, before tax and net of discounts, split into taxable, exempt (no tax, for a client marked tax exempt) and non-taxable (no tax, for anyone else).
    - The tax paid on purchases, by part, and the net tax - what was collected less what was paid - for GST, HST and VAT returns.
    - Exempt sales by client, with each certificate and when it expires, expired ones flagged.
    - By quarter or month, with a chart of each month's tax. Export the table, or the exempt sales by client, as CSV.
  - Expenses record the tax included in them: pick the rate and the tax is worked out from the amount as you type, or type over it with what the receipt says. A tax with no rate of yours can be typed in on its own. Money back gives its tax back. The list and the quick look show the tax, and picking a vendor fills in the tax of the last expense with them along with the rest.
  - Recurring expenses take a tax too, and put it on each expense they record.
  - Exports: invoices can add Subtotal, Discount, Tax and Tax by Part; quotes and recurring invoices Tax; expenses Before Tax, Tax and Tax Rate; and clients Tax Rate, Tax Exempt, Exemption Certificate and Exemption Expires. They are off until ticked.
  - API: clients take `client_default_tax_id`, `client_tax_exempt`, `client_tax_exempt_number` and `client_tax_exempt_expires`, and a new invoice line takes `tax_id` `default` for the client's usual rate. A new endpoint reads the taxes with their parts. Invoices and quotes add their subtotal, tax, tax by part and registration numbers, invoice lines name their rate, and expenses include their tax.
  - Demo data: both taxes have a registration number, Allegheny County Sales Tax is made of the state's 6% - named PA Sales Tax, so Tax Collected adds the two up together - and the county's 1%, each client has its rate, the church and the charter school are tax exempt, one with a certificate that has run out, and what the company buys for its own use pays sales tax.
- **Tags** (rebuilt)
  - Admin > Tags lists each kind's tags - clients, locations, contacts, credentials, assets, vendors, domains, licenses, services, networks, documents and tickets, down the side with how many each has. Each tag shows as it looks on a record, with its description, how many records have it (and how many of those are archived), opening that list filtered by it, and the recurring invoice lines that count users or devices by it.
  - Search by name or description, sort by name or by most used, and Current and Archived tabs.
  - Each tag has Edit, Merge into..., a link to its records, Archive or Restore, and Delete, which asks first and says how many records lose the tag. Tick several for Merge into one, Archive or Restore, and Delete.
  - The tag window shows the tag as it will look while you change it:
    - its name, and what it is for, which can be changed while nothing has the tag yet
    - a description, shown when you point at the tag and in the tag picker, which searches it too
    - a color from the swatches, or any other from the color picker or typed as a hex value - the text on the pill is dark or white, whichever reads
    - an icon from the grid, which you can search, or any Font Awesome icon by its name
  - Merge moves every record with the merged tags onto the one you keep, once each, and the recurring invoice lines that count by them count by it. The others are deleted.
  - Record forms: an admin types a new tag's name into the Tags field and picks Add or presses Enter, and the tag is made, with the next color along, when the record is saved - nothing reloads and nothing typed is lost. Typing the name of an archived tag brings it back.
  - A recurring invoice line counting by a tag keeps it in its window once the tag is archived, or when the client no longer has anything with it, so saving the line doesn't drop it.
  - Licenses, services, networks, documents and tickets take tags, like the other seven kinds:
    - their lists show each one's tags with the + to change them (tickets show the + once a ticket has a tag), filter by tag and find tags by name in the search, and Bulk Action has Tags
    - their pages and quick looks show the tags, with the + to change them, and their New and Edit windows have a Tags field
    - their exports filter by tag and can add a Tags column
  - Files: documents take tags. Filtering by a tag shows the documents with it - from every folder at the top level, like a search - and no uploads. Bulk Action > Tags tags the documents ticked and leaves uploads alone.
  - Tickets: Tags is in the More filters, shown as a chip like the others, and the board's cards show their tags. Changing a ticket's tags is written in its history. Merging a ticket into another moves its tags over too, apart from archived ones.
  - Search finds records by their tags: searching VIP finds the clients, assets or tickets tagged VIP, with the tag next to each. A Tags group lists the tags that match, each in its color, opening its list filtered by it - or for a document tag, the search for it, as documents are listed one client at a time.
  - API: read the tags, one record's tags or the records with a tag, set a record's tags or add and take some off, and make a tag (admin keys only). Every record read now includes its tags.
  - Starter Content: the Tags pack has tags for licenses, services, networks, documents and tickets - We Resell, Managed, PCI, Runbook, Escalated and so on. Demo data tags its licenses, services, documents and tickets with them.
- **Categories** (rebuilt)
  - Admin > Categories lists each kind - expense, income, referral, ticket, network interface, asset status, software type, rack type, and contact and asset note types - down the side with how many each has, and says under the heading what the kind is for.
  - Each category shows as it looks on records - its icon, or a dot, in its color, and an asset status as its badge - with its description and what uses it, such as "42 expenses · 3 recurring expenses · 24 budget months" (and how many of those are archived). Each opens its list filtered by the category where there is one: expenses, recurring expenses, invoices, other income, products, tickets, recurring tickets, and clients by referral.
  - Search by name or description, sort by name or by most used, and Current and Archived tabs. The kinds offered in an order of their own - interface types, asset statuses, software types, rack types and note types - list in that order first, and are dragged into it, or moved with Move up and Move down.
  - Each category has Edit, Merge into..., Archive or Restore, and Delete while nothing uses it - otherwise Delete is greyed out and says what uses it. Tick several for Merge into one, Archive or Restore, and Delete, which keeps the ones in use and says which.
  - The category window shows it as it will look while you change it:
    - its name and a description, shown when you point at it
    - a color from the swatches, any other from the color picker or typed as a hex value, or none - an asset status with none keeps its built-in color
    - an icon from the grid, which you can search, any Font Awesome icon by its name, or none
    - what uses it, and for a kind records keep by name, that renaming it renames it on them too
  - Merge moves everything with the merged categories onto the one you keep, and the others are deleted: expenses, recurring expenses, budget months (a month budgeted under both becomes one line of their total), import rules, a payment provider's fees and the mileage setting, invoices, quotes, recurring invoices, other income, products, tickets, recurring tickets and canned responses - and for the kinds kept by name, the clients, assets, interfaces, licenses, license templates, racks and notes that have them.
  - Names that records have but aren't categories - notes typed General, referrals entered before there was a list - are listed under the kind's categories with how many have each, linked to them where there is a list, and Add makes one a category. To fold one into another, add it, then merge it.
  - Renaming or merging doesn't mark the records it changes as edited.
  - Every form that picks a category picks it the same way, from the categories in the order they are offered in, each with its color, its icon and its description underneath. A record keeps a category archived since it was picked, marked (archived), and a status, type or referral it has that isn't a category, marked (not set up), so saving it changes neither.
  - The + beside a category picker opens the category window over the form. What you make there is picked in the picker and the window closes, leaving the form as it was - nothing reloads and nothing typed is lost. A name that is taken is said in the window. It is on every form that picks one: expenses, recurring expenses, other income, invoices, quotes and recurring invoices (and a ticket's quote and invoice, and Unbilled Time), products, tickets and recurring tickets, a client's referral, an asset's status, an interface's type, a license's and a license template's type, a rack's type, a contact or asset note's type, payment providers, canned responses and the mileage setting - and their Bulk Action windows.
  - A referral typed into a client's form that matches one in another case takes its spelling.
  - A window opened over another shades the one underneath, rather than drawing its shade behind it.
  - Lists show a category the way the category window does - its icon in its color, or a dot of its color: expenses, recurring expenses, other income, transactions, tickets, recurring tickets, a ticket and its quick look, and the expense, recurring expense, payment and other income quick looks.
  - A list's category filter offers the categories its records have, rather than every one, in the kind's order with their colors and icons. One its records have that has since been archived is still there, marked (archived), and a referral clients have that isn't one, marked (not set up). This is on expenses, recurring expenses, income, invoices, products, tickets, recurring tickets, clients by referral, and their export windows.
  - New filters, each in the export window too: quotes and recurring invoices by category, assets by status, licenses by software type and racks by type. The assets tabs count within the status picked, and the licenses tabs within the software and license type.
  - The invoice, quote and recurring invoice exports have a Category column, and the asset export a Status column, to tick.
  - Admin > Categories links what uses a category to these lists filtered by it too: quotes, recurring invoices, assets, licenses and racks.
  - API: read the categories - every kind the key can see, one kind, one by id or by name, archived ones too - with how many records have each.
- **Roles** (rebuilt)
  - Admin > Roles shows what each role opens at a glance - its level of each part of ITFlow in that level's color, or Admin - everything - and its agents, opening Users filtered to them. Search, and Current and Archived tabs.
  - New, Edit and Copy are one window. Admin is a switch; otherwise each part of ITFlow - Clients, Support, Credentials, Sales, Financial and Reports - gets None, Read, Modify or Full, with a line for each level saying what it lets them do, ticked as you pick. Reports only has None and Read, all it uses. A change reaches the role's agents from the next page they open.
  - Copy starts a new role from another's levels.
  - Archive a role nobody has to stop it being given out, and Restore it. Delete one no agent has, archived ones included. Archive and Delete are greyed out with the reason when they can't be used.
  - The Administrator role can now be opened and renamed. No admin role can lose admin while it is your own, so nobody can lock themselves out of Admin.
  - Role names are unique, in any case, and the description is optional.
  - The audit log says what changed on a role: "Sales Read to Modify, Reports None to Read".
  - A page reads the agent's role once, where it used to ask the database again at every check - dozens of times on a busy page.
  - When a role doesn't allow something, ITFlow says so plainly - "Your role, Technician, can only view Support, not change it" - with how to get it changed: on a page, in a card where the page would be, with Back; in a window, in the window; after a button, back on its page. It used to show a bare line of text on a blank page, and a window it refused only said it couldn't load.
  - Agents aren't offered what their role can't do: the Clients and Assets bulk menus at Read, Delete among archived assets below Full, editing an asset's recurring tickets at Read, and links into a report for an agent who can't open it.
  - The role window warns when a role is given Support, Credentials, Sales or Financial with Clients at None, as each client's pages need Clients.
  - A user's quick look shows what they can open, from their role, and the user form shows what the role picked opens.
  - API: read the roles - an admin key every role, with its level of each part of ITFlow and how many agents have it, archived ones too; any other key only its own user's role. Read the agents, with their role, under the Support permission.
  - API: a call the key's role doesn't allow says what it needs and what the key's user has, with how to get it changed (see Breaking Changes, API).
  - Accounting is the Accountant's job: with Financial and Sales at Read, a role can take payments - one, a saved card or a batch - change them and email receipts, reminders and statements, and with full access to Financial delete and refund them (see Breaking Changes, Roles). The invoice, the invoice list, Income and the payment quick looks offer what the role can do, and the reminder window offers only the reminder to a role that can't send the invoice itself. The built-in Accountant role now has full access to Financial.
  - Calendar events follow the role, through the client they are for (see Breaking Changes, Roles). A role that can't add to a client's calendar isn't offered New event there, or a client in the event window, which says why, and its events can't be dragged.
- **API Keys** (rebuilt)
  - Admin > API Keys shows, for each key, the agent it acts as and their role, what it can open and for which clients, whether it works and when it was last used. Whether it works reads Works until a day, Expires in so many days in its last two weeks, Expired, or Won't work with why: its agent is disabled, archived or has no role, or it acts as nobody. Search finds keys by their name or their agent's.
  - The New API Key and Edit windows show what the picked agent can open as you pick them, and offer only active agents. A new key lasts a year unless you change it, and its last day has to be after today. An expired key can be renamed and stays expired; a later day brings it back.
  - Create on the Details tab before ticking that the key and password were copied opens the Key tab, rather than doing nothing.
  - Revoke ends a key today. Delete is for expired keys, as before, one at a time or several from the bulk menu.
  - The audit log says who a new key acts as and when it ends, and what an edit changed: "renamed from RMM, expiring 2027-06-01 (was 2026-12-01), acting as Tess Tech (was Johnny Test)".
- **Emails** (rebuilt)
  - Ticket emails to clients now look like the billing emails: your logo or name, a coloured heading saying what it is, the ticket's number, subject, status and priority in a box, the reply or the details quoted as they were written with who wrote them, a button to open the ticket, and your details at the foot. This covers a ticket opened by an agent, on a schedule or from an email, a reply, resolved, closed, being made a watcher, replying to a closed ticket, a reply through the API and a task needing their approval.
  - Each says under it why they get it - the contact on the ticket, watching it, or asking for every update on their company's tickets - in place of the YOU ARE A COLLABORATOR block.
  - A new ticket's email says when you will respond, from its SLA, and an Urgent or High one asks them to phone if it is holding them up.
  - Resolved says when it closes by itself, with a button to view or reopen it. Closed asks how you did.
  - The subjects are the same and the reply line stays at the top, so replies still go onto their ticket and are cut off where they were.
  - Images in a reply point at your ITFlow, so they show, and anything in a reply that could run in the email is taken out.
  - Scheduled work: the contact, the ticket's watchers and the assigned agent get the date, time and whether it is on site, with the calendar file as before. Cancelling it tells them the same way, and the calendar file takes it out of their calendar.
  - Emails to your team are in the same look, each with an Open in ITFlow button:
    - Assigned to you: who assigned it, with the details and the client. Several from Bulk Action come as one email listing them, with a button to the agent's tickets. Recurring tickets the same way.
    - New ticket, to the new-ticket address (Admin > Ticket): who raised it and how - in the portal, by email or on a schedule - with what they wrote. A ticket raised on a schedule now reaches it too.
    - Client replied, to the agent the ticket is assigned to: who replied, in the portal or by email, with what they wrote. A portal reply used to say only to check ITFlow.
    - SLA at risk and missed, to the SLA alert address (Admin > SLAs) and the assigned agent: which target, when it is or was due and who has it, in amber for at risk and red once missed. An agent who is also the alert address gets one.
    - Shared links: a login sent back through a link you made, with a button to review it.
  - Account emails: a new agent's sign in details with a Sign in button (and that the email has their password in it), a change to their account, a sign in from a new device and a wrong MFA code. The sign in alerts say when, the browser and system in words, and the IP address, with a button to their sign in security.
  - The test email in Admin > Mail comes in the new look, saying which mailbox it was sent from.
  - Client portal: the email confirming a saved card is in the billing emails' look, from the invoice mailbox, naming the card and when it expires.
  - Client portal emails - an invite, from you or from a primary contact, Forgot password, a changed password, and two-step sign-in turned on or off - all come from the system mailbox in the same look.
  - Shared links: the email with a link and the one with a code to open it are in the same look, with your note quoted and the whole link printed under the button, as a link only works when it is copied whole. Subjects are the same.
  - Calendar invitations, updates and cancellations say when, how often it repeats, where, the description and a Join button for a meeting link, with the calendar file as before.
  - Email on the contacts and clients lists: what you write goes out with your logo above it, your details below and a line saying why they got it. Pick the mailbox it comes from and the name it shows, start it "Hello" and their name if you like, and send it now or later. What you write is made safe and its images point at your ITFlow, as in ticket emails.
- **Admin > Mail > Previews**: every email ITFlow sends, 52 of them, as it goes out - your logo, your details and the address it comes from. Each is made when you open it, by the same code that sends it, on sample records (a client, its contacts and an agent) that are thrown away again, so looking sends and saves nothing. It says who gets it and what sends it, shows it at computer or phone width, and Send me a copy emails it to you, marked [Preview].

### Bug Fixes

- Client Portal: setting up automatic payment on a recurring invoice now only works on that client's own invoice and saved card, and needs a contact with billing access. On the agent side, the saved card has to belong to the invoice's client.
- Search: the full results page no longer puts passwords into the page. Showing or copying a password from there is logged, the same as everywhere else.
- Credentials: saving a credential after your vault session had timed out wiped its password. That no longer happens.
- Credentials: saved 2FA secrets with spaces or lowercase letters produced no code. Imports carried values from one row into the next and accepted any file type. Share links stayed open after their credential was deleted.
- Credentials: a credential entered on the asset form with only a username was not saved.
- Shared links: a mail filter or chat preview opening a one-time link used it up before the person it was for could open it.
- Shared links: anyone holding a login link could keep getting its 2FA codes until the link expired, even after its one view was used. Codes now go only to the browser that opened it, for 30 minutes.
- Shared links: the link's key, and for a login the key that decrypts it, were in the address, so they ended up in web server logs.
- Billing: editing an invoice or a quote set its discount to zero, and a discount given on a new quote was ignored.
- Billing: changing the quantity of a stocked product on an invoice did not move its stock.
- Billing: editing a payment's amount did not update the invoice, so it could stay Partial when paid in full or Paid when not.
- Billing: deleting a recurring invoice left its autopay behind.
- Billing: an invoice made from a quote was due counting from today instead of the invoice date, and with no terms when the client had none set.
- Billing: the client's view of a quote offered Accept on a viewed quote that had run out, and the invoice and quote views put the browser's HTML into the history.
- Billing: copying an invoice logged its old number with the prefix twice.
- Billing: the nightly late fee line was saved without its subtotal, and the invoice total was raised by hand instead of from its lines.
- Billing: the email telling your team a quote was accepted or declined linked to a page that doesn't exist. It now opens the quote.
- Billing: a batch payment's receipt went to the primary contact only, and sending it wrote a PHP warning.
- Expenses: receipts were served straight from `uploads/expenses/`, so anyone with a receipt's address could open it without signing in.
- Expenses: Bulk Delete's Yes, Delete! button did nothing. Bulk Set Client could change expenses of clients the agent is not allowed to see.
- Expenses: the New Expense window opened for agents who could not save one. Deleting an expense with no receipt, or replacing a receipt when there was none, wrote a PHP warning.
- Expenses: there was no way to set how an expense was paid, so the default payment method in Admin > Settings > Defaults went unused. A photo receipt downloaded with .pdf on its name.
- Recurring expenses: editing one, even just its amount, moved its next date to the day and month in the form. The expenses they made left out the payment method, and a recurring expense could not be paused.
- Recurring expenses: the list's date filter went by when each was set up, not when it runs.
- Products: Restore in the archived list's bulk actions did nothing. Deleting a product left its stock records behind.
- Products: Add Stock could record zero units, or link an expense that no longer existed.
- Products: deleting an invoice did not put its stock back. Copying an invoice or making one from a quote lost the link to the product, so the copy took no stock and the product did not count it as sold.
- Products: a stock change for a fraction of a unit, such as 1.5, was rounded to a whole number.
- Billing: Make Invoice on a quote could make a second invoice when pressed twice or from two tabs, and could invoice a quote that was never accepted or had been declined. Its audit log entry named the quote with the current quote prefix instead of the quote's own.
- Recurring invoices: if cron missed a night, that recurring invoice stopped billing until someone moved its date. The missed run is now billed the next night, up to 7 days late.
- Invoicing a ticket: on an invoice that already had lines, its line went in near the top instead of at the end. A ticket already on an invoice could be billed again from a second window, and a ticket with 100 hours or more logged had no hours filled in.
- Invoicing a ticket for a client with no hourly rate filled in nothing. It now takes the default hourly rate, as the Unbilled Work report already did.
- Recurring invoices: the email went to contacts that had been archived, and could reach the same address twice.
- Client Portal: the recurring card on the home page didn't show for a client with only yearly recurring invoices, and counted archived ones.
- Copy buttons in many places, such as pages that already had a window open, showed Copied! but copied nothing. Copy guest URL on invoices and quotes jumped the page to the top, and copying an empty field wiped your clipboard.
- Tickets: an agent without sales access who edited a ticket turned billable off. Email reply was offered when no mail server was set up. The timer threw an error on resolved tickets, and the due date was not shown.
- Assets: editing some asset types wiped the OS, firmware, purchase and warranty fields. The add form broke when there were no OS values, and bulk assigning a contact or location could change assets of another client.
- Assets: the quick look's notes could save over the wrong asset's notes, and its Credentials tab showed without permission.
- Racks: every rack save wiped the model. Non-admins could not archive a rack, and positions outside the rack were accepted. Deleting or transferring an asset, or deleting a client, left rack items and racks behind.
- Contacts: saving a contact with the client portal turned off cleared their portal sign-in method. Replacing a photo tried to delete a folder. The contacts list scrolled sideways on phones, and archived assets and credentials showed on the contact page.
- Files and documents: deleting a folder orphaned its files, documents and subfolders. They now move up a level. The upload size check was skipped, and skipped uploads were not reported.
- Files and documents: searching with quotes did not match. PDF export needed edit access, and deleting a file or document left its links behind.
- Licenses: editing a Subscription or Open Source license cleared its type. Adding a device or user twice caused an error, and license deletes ignored the destructive deletes setting.
- Vendors: deleting a vendor from a template unlinked every other vendor from that template. Vendor websites without http:// did not open. New vendor templates lost the phone country code, and pushing a template update with nothing ticked caused an error.
- Domains: lookups, edits, the background check and API updates wiped the expiry date and records when a lookup came back empty. Edits also dropped a past expiry date, and every edit waited on slow lookups.
- Domains: expiry alerts were sent for archived domains, and bulk archive needed delete rights. A new client's website was not added as a domain when its expiry could not be found, and the WHOIS summary was cut short.
- Certificates: deleting one logged a blank name, and certificates without an issuer organization did not read correctly.
- Projects and services: deleting a project now keeps its tickets, and deleting a client deletes its projects. Archived services are left out of counts and lookups.
- Records of one client can no longer be linked to another client's records: locations, contacts, vendors, assets, networks, domains, certificates, credentials, documents, files and projects.
- Users: Your account > Activity showed other agents' sign-ins. A password with a space at the start or end was saved inconsistently, so the agent either could not sign in or could sign in but not open credentials. Passwords are now saved exactly as typed.
- Users: disabling a user left a blank name in the audit log.
- Income on the dashboard and in the reports counted money moved between your own accounts as income when the transfer had been given a category.
- Income: the Invoice payments and Other revenue figures on the Income Summary report opened the whole Income list, as the list didn't understand their links. Sorting the Income list by a column it doesn't have gave a database error.
- Income: the revenue form saved whatever was typed, such as no account, no category or an amount of nothing. A revenue's client couldn't be set, so it never counted for the client.
- Accounts: the Accounts page, the dashboard and the account pickers counted archived payments, income and expenses in a balance, while Transactions left them out, so the same account could show two balances.
- Accounts: New Account saved whatever was typed, such as no name, the name of another account or a balance of words. Edit could only change the name and notes.
- Transfers: a transfer could be saved from an account to itself, for nothing, or with no date. Editing one took which expense and income to change from the form, so a changed form could rewrite any expense or income. Neither side recorded who entered it.
- Transactions: opening it for an account that no longer exists wrote PHP warnings. It now says so and shows every account's.
- Client Portal: a file attached to an internal note could be downloaded from the client portal by a contact who could see the ticket, by its number. Only the ticket's own files and those on replies the client can read download now.
- Client Portal: files on a closed ticket could not be downloaded from the portal.
- Client Portal: a new ticket could be linked to another client's device, or to a category that isn't a ticket category, by changing the form. Those are ignored now, and a plain contact can only pick a device they use.
- Client Portal: a reply of nothing but empty lines was accepted, and a new ticket could be raised with no details.
- Client Portal: Pay invoices and Recurring invoices wrote PHP warnings onto the page when no payment provider was set up.
- Client Portal: on most pages the browser blocked the arrows on drop-downs, the close crosses and the editor setup, as the portal's security policy didn't allow them.
- Client Portal: paying an invoice with a saved card didn't check the contact had billing access, and couldn't pay a partly paid invoice. A double click could charge the card twice.
- Client Portal: the saved cards page asked Stripe about every card each time it opened, and showed an error instead of the cards when Stripe couldn't be reached. It started a Stripe checkout on every visit too, where it now starts one when Add a card is clicked.
- Client Portal: a technical contact could change the primary contact's details, including the email they sign in with, by changing the edit form. Contacts can only be changed by the primary contact now, never their own record or another primary contact's, and only their own company's.
- Client Portal: a contact whose portal access was turned off stayed signed in until their session ran out.
- Client Portal: documents added in the portal recorded the contact as their author in place of their portal user, so ITFlow showed the wrong person or no one. An uploaded file's name went into its document unescaped.
- Client Portal: a page turning a contact away after it had started could leave only the top bar on screen, as the redirect it fell back on was blocked by the portal's security policy.
- Client Portal: Forgot password accepted a link with no token, so anyone could have the portal email its "password was reset" message to any address. Nothing changed, but the email went out.
- Client Portal: password links were kept in the database as they were sent, so a copy of the database held working links. Only a fingerprint of each is kept now.
- Client Portal: changing a password in the portal didn't check the 8 character minimum.
- Client Portal: a ticket rating could be saved as any value. Only Good and Bad, the two buttons the portal shows, are accepted now. The demo data rated tickets Great and Ok, which the portal never offers, and now uses Good and Bad.
- Notifications that linked to a page did not open correctly from Admin pages.
- Messages showed a backslash before apostrophes and quotes in names, or showed `&amp;` instead of `&`.
- The empty password strength meter showed on the credential form, and page numbers ran off the screen on phones.
- Guest invoice payment pages broke instead of showing their error message.
- Recurring tickets: if cron missed a night, that recurring ticket stopped raising tickets until someone fixed its next run date. The missed ticket is now raised on the next run and the schedule carries on.
- Networks: Export from All Clients with no client picked did not work.
- The add category button on the products filter did nothing.
- Tags: saving a client, contact or asset with the same tag picked twice stopped with an error, and those forms took any tag sent with them, another kind's included. Every record form now keeps only its own kind's tags, each once.
- Tags: the + beside the Tags field in record forms opened Admin's New Tag window, which reloaded the page and lost what had been typed into the form, and showed an error to anyone who wasn't an admin.
- Tags: a tag saved with no color, or a color word such as dark, showed white text on no color at all.
- Tags: the Archived button on Admin > Tags showed the same tags as without it, as nothing could be archived.
- Categories: editing a category whose color was saved as a word, such as lightblue, turned it black, as the color picker can't show words. The ticket list, a ticket's quick look and the recurring tickets list left those colors out, and the reports drew them in a color of their own.
- Categories: the icons of contact and asset note types didn't show in the type pickers, and making a category from the + beside a category picker left out its description.
- Categories: the + beside a category picker reloaded the page once the category was made, losing the form that was being filled in. On the quote made from a ticket it made an expense category, for a picker of income categories.
- Clients: saving a client with no referral made a referral with no name, and typing one that matched an archived referral made a second of it.
- Licenses: the tab counts ignored the license type filter, so a tab could say more than it showed.
- Racks: the Archived button broke onto two lines on a laptop-sized screen.
- Roles: a role's name went into the "created" and "edited" messages as it was typed, so markup in it ran on the page.
- Roles: an admin role could have admin taken off while its agents were the admins using it, your own included, shutting them out of Admin. Archived roles couldn't be seen or brought back.
- Roles: being turned away left "You are not permitted to do that!" waiting on the next page opened.
- API keys: revoking, deleting and bulk deleting keys showed and logged no name ("API Key revoked"), and the bulk message counted the keys ticked rather than those deleted. A name with an apostrophe showed with a backslash in the created and updated messages.
- API keys: a new key could be given today as its last day, so it never worked. An expired key couldn't be renamed without giving it a new day, as Edit wouldn't save the one it had.
- API: reading an agent's time (`technicians/time.php`) needed Modify on Support, though it only reads.
- API: the API ran on the server's clock, not ITFlow's timezone, so the tickets, replies and other records it made were stamped in the server's time - hours off on a server set to UTC - and an API ticket's SLA times were worked out from that. A key also stopped working at midnight by the server's clock.
- Calendar: any agent could change, move or delete any client's event, whatever their role, and one whose role has no Clients access saw every client's events.
- Roles: the built-in Accountant role couldn't take a payment, email a reminder or statement, or delete an expense or other income entered by mistake.
- Tickets: a ticket raised from a recurring ticket never emailed its contact or the new-ticket address, as the settings it read were never loaded.
- Tickets: the email telling someone their email had become a ticket showed an apostrophe in its subject, or in their name, with a backslash in front of it.
- Tickets: SLA alert emails were queued when outgoing mail was not set up. For a ticket raised or answered by email, the new ticket and client replied emails showed an apostrophe in the subject, the client's name or the reply with a backslash in front of it.
- Admin > Mail: the test email was queued when outgoing mail was not set up, or when what was typed was not an email address, and greeted everyone as "Chap".
- Ticket templates: New Tickets on the clients list gave only the first client's ticket the template's tasks, dropped their times, and couldn't be given a template at all from the window. It took any priority, category or agent posted to it, and its "primary contact" never did anything.
- Ticket templates: changing one of a template's tasks moved it to the top of the list.
- New Ticket and recurring tickets: once the window had been opened more than once, Add Task added a task row for every time it had been opened.
- Forms with tabs, like a ticket template's: with a required field left empty on a tab that wasn't showing, Save did nothing and said nothing. That tab now opens, on the field.
- Email on the contacts list queued an email to a contact with no email address, which then failed in the mail queue. Email on the clients list included archived contacts. Both took any From address posted to them.
- Emails: a saved card's confirmation, a new agent's sign in details and the email about a change to an agent's account were only sent when an SMTP host was filled in, so they were skipped when mail goes through Microsoft 365 or Google with no host. The saved card's email began "Were writing".
- Contacts: an agent without Support saw a contact's Assets, Documents, Files and Services tabs, which led to pages they were refused. A document's or file's linked assets, licenses, services, domains and certificates were listed to them too.
- All Client Docs listed Credentials to agents who can't open it.
- Notifications: shared-link notifications reached agents without Support, with a link they couldn't open.
- License templates: the type picker showed a stray "<" at the top.
- Admin > Settings > Defaults: Payment Method and Expense Payment Method listed the payment method categories that moved to Admin > Payment Methods in 2.3.1, so a new install had nothing to pick. They list Admin > Payment Methods now.
- Messages, hints and window titles were reworded where they were unclear, and bulk windows say "1 asset" or "3 assets" correctly.

### Developer Updates

- Database 2.8.0 to 3.0.8, one file per step under `admin/database_updates/`, with `db.sql` matching 3.0.8:
  - 2.8.0: `racks.rack_units_descending`, `rack_power_capacity` and `rack_photo_rear`. On `rack_units`: `unit_face`, `unit_full_depth`, `unit_type`, `unit_power_watts`, `unit_notes` and a `unit_asset_id` key. Unlinked items move from asset id 0 to NULL.
  - 2.8.1: `location_documents` and `location_files`.
  - 2.8.2: `vendor_tags` (tag type 6), and `vendor_contact_phone_country_code`, `vendor_contact_phone` and `vendor_contact_email`.
  - 2.8.3: `domain_tags` (tag type 7), and `domain_auto_renew`, `domain_dmarc`, `domain_dkim` and `domain_refreshed_at`.
  - 2.8.4: `certificate_issued_at`, `certificate_names`, `certificate_fingerprint`, `certificate_check_status` and `certificate_checked_at`, backfilled from the stored PEM.
  - 2.8.5: `recurring_ticket_paused_at`, and orphaned ticket task, approval, history and SLA rows removed.
  - 2.8.6: `project_start`, `project_budget_hours`, `project_contact_id` and a `ticket_project_id` index.
  - 2.8.7:
    - `service_archived_at`, `service_reviewed_at` and `service_review_interval`, plus a `service_review_due` index
    - the category and backup columns widened
    - the service link tables deduplicated and keyed on the pair, `service_domains` foreign keys, and a new `service_tickets`
  - 2.8.8:
    - on events: `event_repeat_interval`, `event_repeat_until`, `event_repeat_skip`, `event_sequence` and `event_created_by`
    - on attendees: `attendee_user_id` and `attendee_invited_at`
    - `user_calendar_feeds`, and `email_queue.email_cal_str` changed to `text`
  - 2.8.9:
    - `network_type` and `network_vendor_id`, with types backfilled from the name and the CIDR prefix moved into the address
    - `ip_type` and `ip_mac`, and `interface_vlans`
    - `interface_network_id` and `network_location_id` indexes
  - 2.9.0:
    - on `shared_items`: `item_version`, `item_name`, `item_payload`, `item_vault_key`, `item_passphrase`, `item_verify_email`, `item_code`, `item_code_sent_at`, `item_failed_attempts`, `item_notify`, `item_last_viewed_at`, `item_created_by`, `item_received_at`, `item_ended_at`, `item_ended_by` and `item_end_reason`, with keys on the client, the type and record, and the expiry
    - `item_note` widened to 1000, `item_view_limit` made `NOT NULL DEFAULT 0` (NULL was unlimited), existing `item_key` values replaced by their SHA-256, and `item_name` filled from the shared record
    - `settings.config_share_max_hours` (720) and `config_share_require_protection` (0)
  - 2.9.1: `recurring_invoice_items.item_seat_source`, `item_seat_filter` (JSON) and `item_seat_minimum`, and `recurring_invoices.recurring_invoice_anchor_day`, backfilled from the day of each next date.
  - 2.9.2: `settings.config_time_increment` (15), `config_time_rounding` ('nearest'), `config_time_minimum` (0) and `config_time_item_name` ('Support [Hourly]').
  - 2.9.3: `quote_items.item_frequency` (NULL for one time, or `month`, `quarter` or `year`), `quotes.quote_monthly_amount`, `invoices.invoice_quote_id` and `recurring_invoices.recurring_invoice_quote_id`. Invoices are linked to their quote from the "Invoice created from quote QUO-n" history the old Make Invoice wrote, matched on the client too.
  - 2.9.4: `products.product_cost`, `product_vendor_id`, `product_vendor_part`, `product_notes`, `product_frequency` (NULL once, or `month`, `quarter`, `year`), `product_seat_source` (NULL, `contacts` or `assets`) and `product_track_stock`, set for products with any `product_stock` rows. Keys on `item_product_id` in `invoice_items`, `quote_items` and `recurring_invoice_items`, and on `product_stock.stock_product_id`.
  - 2.9.5: `product_stock.stock_qty` becomes `decimal(15,2)`, with `stock_type` (`received`, `sold`, `returned`, `adjusted` or `written_off`, default `adjusted`), `stock_unit_cost`, `stock_invoice_id` (keyed) and `stock_user_id`. Existing rows take their invoice from a note ending "Invoice n" and their type from the sign and the note. `products.product_reorder_level` and `product_reorder_qty`.
  - 2.9.6: `products.product_asset_type` (one of the asset types, NULL for none), `product_make`, `product_model` and `product_warranty_months`. `assets.asset_product_id`, `asset_invoice_id` and `asset_invoice_item_id`, keyed on the product and the invoice.
  - 2.9.7: `product_prices` - a product's price and cost history: `price_old`, `price_new`, `price_cost_old`, `price_cost_new`, `price_source` (`edit`, `import` or `receive`), `price_user_id` and `price_product_id`, keyed.
  - 2.9.8: `settings.config_invoice_reminder_days` ('1,30' - days from the due date, negative before it), `config_invoice_reminder_repeat` (30), `config_statement_monthly` (0), `config_statement_day` (1) and `config_billing_email_attach_pdf` (1), and `clients.client_reminders` (1).
  - 2.9.9: `expenses.expense_created_by` (0 for cron and payment providers), backfilled from the audit log's Expense Create entries, and `expense_refund_of_id`.
  - 3.0.0 (after 2.9.9, the way 2.0.0 followed 1.9.9): `recurring_expenses.recurring_expense_frequency` becomes `varchar(10)` (`month`, `quarter` or `year`, from 1 and 2), with `recurring_expense_anchor_day` (backfilled from the next date) and `recurring_expense_end_date`. `recurring_expense_day` and `recurring_expense_month` are dropped. `expenses.expense_recurring_expense_id`, backfilled by vendor, category, client and description from the day each recurring expense was set up.
  - 3.0.1: `expenses.expense_billable` (0), `expense_markup` (`decimal(7,2)`, 0.00), `expense_invoice_id` (keyed) and `expense_invoice_item_id`. `recurring_expenses.recurring_expense_billable` (0), `recurring_expense_markup` and `recurring_expense_recurring_invoice_id`. `settings.config_expense_markup` (0.00).
  - 3.0.2: `expenses.expense_import_key` (keyed), `expense_paid_by` (0) and `expense_reimbursement_id` (keyed). `expense_import_rules` (`import_rule_match` unique, `import_rule_vendor_id`, `import_rule_category_id`, `import_rule_client_id`, `import_rule_billable`, `import_rule_used_count`). `reimbursements` (`reimbursement_user_id`, `reimbursement_date`, `reimbursement_amount`, `reimbursement_currency_code`, `reimbursement_account_id`, `reimbursement_payment_method`, `reimbursement_reference`, `reimbursement_expense_id`, `reimbursement_created_by`). `accounts.account_import_map`.
  - 3.0.3: `trips.trip_end_odmeter` renamed `trip_end_odometer`, and `trip_miles` from `float(15,1)` to `decimal(10,1)` (0.0). New `trip_location_id`, `trip_ticket_id` and `trip_created_by` (0), keyed with `trip_user_id` (`trip_user_id`, `trip_date`). `trip_created_by` is backfilled from the audit log's Trip Create entries, else the driver, and `trip_location_id` where the destination matches one of the client's locations by name or by `formatAddress()` of its address, case and spacing aside.
  - 3.0.4: `trips.trip_own_vehicle` (0), `trip_expense_id` (0, keyed), `trip_billable` (0), `trip_invoice_id` (0, keyed) and `trip_invoice_item_id` (0). `settings.config_mileage_unit` ('mi' or 'km'), `config_mileage_rate` (`decimal(10,4)`, 0), `config_mileage_category_id` (set to an Expense category named Mileage, else Travel), `config_mileage_vendor_id`, `config_trip_charge` (`decimal(15,2)`), `config_trip_bill_rate` (`decimal(10,4)`), `config_trip_included` (`decimal(10,1)`) and `config_trip_item_name` ('Travel').
  - 3.0.5: `revenues.revenue_description` becomes `text`, with `revenue_created_by` (0, backfilled from the audit log's Revenue Create entries) and a `revenue_date` key. `payments` gets a `payment_date` key.
  - 3.0.6: `payments.payment_import_key` and `revenues.revenue_import_key` (keyed), and `income_import_rules` (`import_rule_match` unique, `import_rule_action` - payment, income or transfer - `import_rule_client_id`, `import_rule_category_id`, `import_rule_account_id`, `import_rule_used_count`).
  - 3.0.7: no schema change. `accounts.account_type` is set from the name for every account without a kind from 1 to 6 (1 checking, 2 savings, 3 credit card, 4 cash, 5 payment processor, 6 other), the same way as `accountTypeGuess()`, with the 2023 liability types (20 to 29) as 3. Setup and `scripts/setup_cli.php` create the Cash account as type 4.
  - 3.0.8: `account_reconciliations` (`reconciliation_account_id` and `reconciliation_statement_date` keyed together, `reconciliation_starting_balance`, `reconciliation_ending_balance`, `reconciliation_notes`, `reconciliation_created_by`, `reconciliation_created_at`, `reconciliation_finished_by`, `reconciliation_finished_at` - NULL while in progress), and `payments.payment_reconciliation_id`, `revenues.revenue_reconciliation_id` and `expenses.expense_reconciliation_id` (keyed).
  - 3.0.9: `account_reconciliations.reconciliation_opening` (0, 1 for an account brought forward as reconciled) and `reconciliation_adjustment` (`decimal(15,2)`, 0.00 - what its opening balance was corrected by), and `payments.payment_deposit_transfer_id` and `revenues.revenue_deposit_transfer_id` (NULL, keyed - the transfer out of a cash account they were deposited with).
- New shared function files, loaded by `functions.php`: `functions/asset.php`, `asset_interface.php`, `certificate.php`, `client_overview.php`, `contact.php`, `credential.php`, `dashboard.php`, `links.php`, `location.php`, `notifications.php`, `project.php`, `rack.php`, `search.php`, `service.php`, `software.php`, `tags.php`, `ticket.php`, `ticket_fields.php`, `user.php` and `vendor.php`. They hold the queries and rules, so the pages, the API and cron share one definition.
- Removed post models: `agent/post/{asset_interface,certificate,credential,domain,event,location,network_ip,network,recurring_ticket,vendor}_model.php`, `admin/post/user_model.php` and `api/v1/certificates/certificate_model.php`. Their handlers call the shared functions above. Interface handlers moved to `agent/post/asset_interface.php`.
- New scripts: `js/itflow_select.js`, `quick_pick.js`, `tag_picker.js`, `global_search.js`, `notifications.js`, `dashboard.js`, `client_overview.js`, `client_switcher.js`, `credentials.js` and `autosubmit.js`, and `agent/js/calendar.js`, `calendar_event_modal.js`, `files.js`, `interface_form.js`, `network_form.js`, `project_modal.js` and `ticket_schedule_modal.js`.
- New pages: `agent/license.php`, `location.php`, `rack.php`, `service.php` and `vendor.php`. The credentials, domains, certificates and tickets lists accept `?open=ID` to open a record's quick look on load.
- Select picker: `js/itflow_select.js` is behind every `<select class="select2">`, and is loaded first in `includes/footer.php`.
  - The native `<select>` is the only state. Setting `.value`, `option.selected`, replacing `innerHTML` or calling `form.reset()` redraws the picker by itself, and `change` fires as usual.
  - Options take:
    - `data-image`, `data-initials`, `data-tone`, `data-shape`, `data-soft`, `data-color`, `data-icon`, `data-description` and `data-keywords`
    - written by `selectOptionClient()`, `selectOptionUser()`, `selectOptionContact()`, `selectOptionColor()`, `selectOptionPriority()` and `selectOptionAsset()` in `functions/format.php`
  - The `<select>` itself takes `data-placeholder`, `data-tags="true"`, `data-search` and `data-avatar="initials"`. From script, use `select.itflowSelect.open()`, `.close()`, `.clear()` and `.setValue(v)`, and `itflowSelectAll(root)`.
  - `js/tom_select.js` and `libs/tom-select/` are removed. Custom code that called `new TomSelect` or `.tomselect` needs updating.
- Tags: `tagPills()` in `functions/tags.php` renders the pills and the `[data-tag-picker]` button, and `js/tag_picker.js` saves through `agent/ajax.php`. The `*_bulk_assign_tags.php` modals are removed.
- Quick pickers: `quickPick()` in `functions/ticket_fields.php` and `js/quick_pick.js`. Changes go through `agent/ajax.php` using the same field functions the modals used, so history, audit, SLA and notifications are unchanged. `ticket_assign.php`, `ticket_billable.php` and `ticket_priority.php` modals are removed.
- Links: `functions/links.php` is a relation registry, with:
  - `linkAdd`, `linkRemove`, `linkRemoveAll`, `linkedRecords`, `linkCandidates` and `linkGroupsHtml`
  - `agent/post/link.php` with `link_items` and `unlink_item`, plus the legacy link and unlink names
  - one `modals/link/link.php`, with same-client checks on every relation
- Search: `functions/search.php` drives both the palette (`agent/ajax.php?global_search`, `js/global_search.js`) and `agent/global_search.php`, gated by permission and `clientScopeSql()`.
- Notifications: `functions/notifications.php` with `notificationTypeModules()` maps notification types to modules. `js/notifications.js` drives the panel, and `modals/notifications.php` is removed.
- Client scope: `enforceClientAccess()` now runs for every selected client in the client bulk handlers and on the existing record's client in the expense, recurring expense, revenue, trip, task approval and credit handlers. `clientScopeSql()` is applied in the dashboard and per-client reports.
- Confirmations: links with `confirm-link` ask through `js/confirm_modal.js`, which treats `btn-danger` and `btn-outline-danger` as destructive. One `beforeunload` guard covers every TinyMCE editor on the page, replacing the per-editor `window.onbeforeunload`.
- `js/autosubmit.js` (`data-autosubmit`) replaces inline `onchange` submits in the client portal, which let the Content-Security-Policy go back on for `client/recurring_invoices.php`.
- Copy: `js/app.js` handles `.clipboardjs` itself on click, using `navigator.clipboard` with a textarea fallback inside the trigger's modal. It still reads `data-clipboard-text`, and an empty value is refused. ClipboardJS is no longer loaded on agent pages and is now only used by `agent/user/mfa_enforcement.php`. `.itflow-copy` buttons show on hover, and `.itflow-copy-always` keeps them visible.
- Calendar:
  - Ticket scheduling moved into `functions/ticket.php` and is shared with the calendar.
  - Feeds and invites write TZID and VTIMEZONE, with INTERVAL, UNTIL and EXDATE.
  - The mail queue sends invitations and cancellations as a `text/calendar` part, and other calendar files as `invite.ics`.
  - Ticket visit files are built without zapcal, so `libs/zapcal` is no longer used by anything.
- Certificates: `certificateCheckAndSave()` runs the host check for the form, the API and the refresher. The refresher runs daily in a time-boxed batch, writes history and notifies when an OK certificate turns bad. Domains: `domainLookup()` and `domainApplyLookup()`, and the refresher takes 50 a run.
- Recurring tickets: cron skips paused ones and raises a missed run once.
- Agent user limit: `getAgentUserLimit()`, `getActiveAgentUserCount()`, `agentUserLimitReached()` and `agentUserLimitMessage()` in `functions/app.php`. These are checked when adding, activating or restoring an agent.
- Users:
  - `userSetPassword()` writes the hash and the vault key from the same untrimmed password.
  - `userCheckIdentity()` validates the email format and checks it is unique across agent and client portal accounts.
  - Signatures go through `documentHtmlPurify()`.
  - `disable_mfa` is now a POST (`disable_your_mfa`) that needs the current password.
  - `admin/modals/user/user_invite.php` is removed.
  - Saving preferences no longer touches `users.user_extension_key`. The form had no extension field, so every save cleared it, and nothing else reads the key.
- Dashboard: `user_settings.user_config_dashboard_financial_enable` and `user_config_dashboard_technical_enable` are no longer read. The columns are left in place.
- Reports:
  - `functions/reports.php` (loaded by `functions.php`) holds everything the report pages share:
    - the catalogue, `reportCatalog()`, which drives the side menu and the Reports page, and access through `reportEnforce()`, `reportCan()` and `reportCatalogForUser()`
    - periods (`reportPeriod()`, `reportRangeSql()`, `reportBuckets()`, `reportPeriodQuery()`) and request filters (`reportClientId()`, `reportAgentId()`, `reportClientSql()`)
    - the page parts: `reportHeader()`, `reportStats()`, `reportChart()`, `reportTable()` and `reportSectionOpen()`
    - CSV export with `reportExporting()` and `reportCsv()`, which guards against formulas and writes an audit log entry
    - shared queries: `reportRenewals()`, `reportLifecycleAssets()`, `reportOsEndOfLife()` (the `REPORT_OS_END_OF_LIFE` list), `reportCoverage()` and `reportSlaCompliance()`
  - A report page starts with `agent/reports/includes/inc_report.php` and `reportEnforce('key')`, works out its figures, sends a CSV if asked (`if (reportExporting()) reportCsv(...)`), and only then includes `inc_report_layout.php` to draw the page. To add a report, add it to `reportCatalog()` under the file name, and it shows in the menu and on the Reports page for whoever may open it.
  - `reportTable()` columns take `label`, `type` (money, count, hours, duration, decimal, percent, date), `html`, `csv`, `csv_label`, `screen => false` (export only), `sort` and `title`. A row of `['_heading' => ...]` draws a heading row. Times export as decimal hours and the heading says so.
  - Charts are drawn by `js/reports.js` from the JSON in `canvas[data-report-chart]`, with Chart.js. It also sorts `table[data-report-sort]`, submits `[data-report-autosubmit]` filters and runs the budget editor.
  - `agent/reports/includes/inc_all_reports.php` is kept for custom report pages and now loads the two new includes.
  - Tax Collected is worked out by `taxReportSales()` and `taxReportPurchases()` in `functions/tax.php` (see Taxes, database 3.1.2).
  - Budgets: `agent/post/budget.php` handles `save_budget` and `copy_budget`, needing Financial at level 2 and a CSRF token. A blank or zero amount removes that month's budget rather than storing 0.
  - `dashboardRevenueSql()` in `functions/dashboard.php` is the one rule for which revenues count as income, used by the dashboard and the reports. It leaves out the incoming side of transfers. It now returns `incomeRevenueSql('revenues.revenue_id')` (see Income below), so uncategorised revenues count.
  - CSS: the `.itflow-report-*` classes in `css/itflow_custom.css`, with print rules that keep side-by-side cards together.
  - Client portal: `add_ticket_feedback` stores only `Good` or `Bad`. Demo data rates some closed tickets and leaves a few older Windows machines on Windows 10, so the satisfaction and lifecycle reports have something to show.
  - The client actions menu links to the Client Review for that client, and the credentials list's rotation report link keeps the client.
- Client export:
  - `functions/client_export.php` (loaded by `functions.php`) holds both outputs. `clientExportSections()` lists the sections with the module and the part of ITFlow each needs, and `clientExportOptions()` keeps only what the agent may read, whatever is posted.
  - `clientExportDatasets()` and `clientExportRows()` define every table once, and the PDF and the zip both read from them. The CSV headings and JSON keys come from the same column list.
  - `clientExportPdf()` builds the PDF with TCPDF through the `ClientExportPdf` class in `functions/client_export_pdf.php`, which adds the header, the footer with "Page x of y", and an `Error()` that throws, so a document TCPDF cannot draw falls back to plain text instead of ending the PDF. Document images are inlined from `uploads/documents/<id>/` only, and other image URLs are never fetched.
  - `clientExportArchive()` builds the zip in `uploads/tmp`, stores already-compressed files without recompressing them, encrypts every entry with `ZipArchive::EM_AES_256` when passwords are included, and removes the file when the request ends, however it ends. Files and attachments are only read from the client's own upload folders.
  - The window is `agent/modals/client/client_export_data.php` with `agent/js/client_export.js`, and the handler takes `export_client_pdf` or `export_client_archive` in `agent/post/client.php`. The `itflow_download` cookie tells the window the download has started.
  - Removed: `agent/modals/client/client_download_pdf.php`, `getClientPackSections()`, `getClientPackSectionAccess()`, `resolveClientPackSections()` and `renderClientPackSections()`.
- Shared links:
  - `functions/share.php` (loaded by `functions.php`). A link's secret is 32 random bytes, base64url, after the # (`shareUrl()`). `item_key` is its SHA-256 (`shareKeyHash()`), and the content key is an HMAC-SHA256 of it (`shareContentKey()`), never stored.
  - Logins, secure notes and request answers are sealed with AES-256-GCM (`shareSeal()` / `shareUnseal()`) in `item_payload`. A request also keeps its content key under the vault (`item_vault_key`, `encryptCredentialEntry()`), so only an agent whose vault is open can read the answer.
  - `shareCreate()` makes a link, `shareState()` says where it is (active, waiting, received, used, expired, revoked, locked, destroyed, saved or discarded), `shareClaim()` takes a view through `claimSharedItemView()`, and `shareEnd()` ends it and wipes its secrets.
  - `shareSweep()` wipes secrets once a link ends or expires, and `SHARE_GRACE_MINUTES` (30) after its last view. It runs on every guest request and on the list. `sharePrune()` runs nightly and forgets links `SHARE_KEEP_DAYS` (90) after they end. Unsaved answers go after `SHARE_RECEIVED_KEEP_DAYS` (30).
  - Passphrases and codes are kept with `password_hash()`. `shareTakeAttempt()` takes each try with a conditional UPDATE, so a burst of guesses cannot pass `SHARE_ATTEMPTS` (5).
  - Permissions: `shareCanSee()` needs Support, plus Credential for login links and requests. `shareCanMake()` needs Support at level 2, plus Credential to share a login and Credential at level 2 to ask for one.
  - Guest side:
    - `guest/share.php` with `js/guest_share.js`, and `guest/share_ajax.php`, which takes JSON POSTs: `open`, `passphrase`, `send_code`, `code`, `reveal`, `totp`, `destroy` and `submit`.
    - What a browser has passed and opened is kept in its session for the grace time.
    - `guest/guest_download_file.php` takes a JSON POST and serves only from the client's upload folder.
    - `guest/guest_view_item.php` serves links made before 2.9.0 (`item_version` 1, `key` and `ek` in the query string, the login under aes-128-cbc).
    - The page sends `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex`, `frame-ancestors 'none'` and `no-store`.
  - Agent side:
    - `agent/modals/share/share_add.php` with `agent/js/share_add.js`, posting `share_create` to `agent/ajax.php`.
    - `agent/shares.php`, for one client or All Client Docs. `?share=ID` opens a link's quick look, `agent/modals/share/share.php`, whose history comes from the audit log (`log_type` Share, `log_entity_id` the link).
    - `agent/post/share.php` handles `share_revoke`, `share_revoke_all`, `share_bulk_revoke`, `share_request_save` (`credentialFormSet()`) and `share_request_discard`.
  - Notifications go to `item_created_by` only. Links with no owner fall back to `appNotify()`.
  - Removed: `agent/modals/share_modal.php`, `agent/js/share_modal.js`, `agent/modals/credential/credential_share.php`, `share_generate_link` in `agent/ajax.php`, `get_share_totp_token` in `guest/guest_ajax.php`, and `deactivate_shared_item` in `post/misc.php`.
- Billing:
  - `functions/billing.php` (loaded by `functions.php`) serves invoices, quotes and recurring invoices through one map, `billingTypes()`: each kind's table, column prefix, items table and key, history column, page, list and URL parameter. `billingRow()` reads one with its client and category (scoped to the agent's clients unless told otherwise), and `billingItems()`, `billingTotals()`, `billingNumber()`, `billingMoney()` and `billingHistory()` work on any of the three.
  - Lines: `billingItemSave()` adds or edits a line (amounts rounded by `billingLineAmounts()`, stock moved by the difference for invoice lines of `product_type` product), `billingItemDelete()` removes one, `billingItemsOrder()` reorders, and `billingRecalc()` sets the total from the lines less the discount and, for an invoice already paid against, runs `updateInvoiceStatusFromPayments()`. `billingCanEditItems()` is the lock. The edit handlers for all three kinds now use `billingRecalc()`.
  - State and pills: `invoiceState()` (open, overdue, draft, paid, cancelled or non-billable, with balance and days), `invoiceDuePill()`, `invoiceStatusBadge()`, `invoiceCanPay()`, `quoteExpired()`, `quoteStatusBadge()`, `quoteExpiryPill()`, `getQuoteBadgeColor()`, `recurringInvoiceNextPill()`, `recurringInvoiceStep()` (a period on, keeping the anchor day and falling back to a shorter month's last day), `recurringInvoiceUpcoming()` and `recurringInvoiceCatchUp()`. `paymentAccounts()` returns every account with its balance in one query.
  - `includes/billing_paper.php` draws the document for the agent pages and the guest pages from a `$paper` array, with the line controls when `edit` is set. The add-a-line inputs post through `form="billingAddLine"`.
  - `agent/post/billing.php` handles `billing_item_save` (POST `type`, `doc_id`, optional `item_id`, `name`, `description`, `qty`, `price`, `tax_id`, `product_id`), `billing_item_delete` (GET), `recurring_invoice_set_status` and `recurring_invoice_catch_up`. `billing_items_order` in `agent/ajax.php` takes `type`, `doc_id` and `items` (ids, comma separated) and checks client access.
  - `js/billing_items.js`: `itflowBillingItems()` on the pages and `itflowBillingLineForm()` for any line form (the Edit Line window).
  - Windows: `agent/modals/billing/billing_item.php` (Edit Line), `agent/modals/billing/billing_form.php` (the fields shared by New and Edit for all three kinds, posting the names the existing handlers read) and `agent/modals/payment/payment_form.php` (shared by Add Payment, Batch Payment and Edit Payment). `agent/modals/invoice/invoice_add_ticket.php` is now an ajax window, and `add_ticket_to_invoice` takes `ticket_ids[]`.
  - PDFs: `billingPdf($type, $row, $variant)` returns the file name and data, and `billingPdfSend()` sends it. The drawing is in `functions/billing_pdf.php` (the `BillingPdf` TCPDF class and `billingPdfRender()`), loaded only when a PDF is made. `export_invoice_pdf`, `export_invoice_packing_slip`, `export_quote_pdf` and the guest downloads use it. The guest downloads compare the URL key with `hash_equals()` and return 404 when it is wrong.
  - Guest pages: `billingGuestViewed()` marks a sent document viewed, logs the view in plain text and notifies. `billingClientOpenInvoices()` is the client's statement.
  - CSS: the `.itflow-paper-*` and `.itflow-billing-*` classes in `css/itflow_custom.css`, with print rules that print only the document.
  - Making invoices from recurring: `recurringInvoiceGenerate($id, $by, $early, $run)` is the one path, used by `cron/nightly_tasks.php` and `force_recurring`. It refuses when `$run` (the run date the caller means) is no longer the next date, or, without `$early`, when that date is still to come. It claims the run with a conditional `UPDATE` on the next date, then refreshes the lines, applies the period words, copies the lines (with `item_product_id`, but no stock moves), logs history on both documents and emails when set to. It returns the new invoice id, or 0.
  - Cron bills active, unarchived recurring invoices due from `RECURRING_INVOICE_GRACE_DAYS` (7) ago up to today, passing each one's next date as `$run`. `force_recurring` takes `run` from the page's link, and a link without it bills the current next date.
  - Schedules: `recurringInvoiceFrequencies()` (month, quarter, year), `recurringInvoiceMonths()`, `recurringInvoicePeriodWord()`, `recurringInvoiceMonthly()` and `recurringInvoiceMonthlySql()` (use it in SQL sums), `recurringInvoiceAfter()`, `recurringInvoicePeriod()` and `recurringInvoiceTokens()`. `validateRecurringFrequency()` accepts `quarter`. The edit handler keeps `recurring_invoice_anchor_day` unless the next date changes, and the add and edit handlers run their dates through `validateDate()`.
  - Seats: `recurringSeatSources()`, `recurringSeatFilter()`, `recurringSeatCount()` (null when a license is gone or isn't the client's), `recurringSeatDescribe()`, `recurringSeatOptions()`, `recurringSeatLines()` and `recurringInvoiceRefresh()`, which syncs the seat quantities and takes each line's tax at its current rate. `billing_item_save` takes `seat_source` (empty for typed), `seat_tag`, `seat_types[]`, `seat_software_id`, `seat_count` (`used` or `total`) and `seat_minimum` on recurring lines. A form without `seat_source` leaves the seat settings alone.
  - New handlers: `recurring_invoice_sync` (GET, `agent/post/billing.php`) and `recurring_seat_count` in `agent/ajax.php`, which returns `{count, quantity, label}` or `{error}` and needs Sales and access to the client. `itflowBillingSeatForm()` in `js/billing_items.js` drives the Edit Line seat fields.
  - Billing time (`functions/billing.php`): `billingTimeRules()` reads the settings once a request (falling back to the old rules before 2.9.2 is applied, so they are not in `load_global_settings.php`), `billingTimeIncrements()`, `billingTimeRulesText()`, `billingTimeHours()`, `billingTimeRate()`, `billingTimeDescription()`. `billingUnbilledSql()` is the one definition of unbilled (billable, status Resolved or Closed, not archived, `ticket_invoice_id = 0`); `billingUnbilledTickets()`, `billingUnbilledByClient()` and `billingUnbilledCount()` read it, scoped to the agent's clients. The dashboard, client overview, sidebars and the Unbilled Work report use them.
  - `billingTimeInvoice($client_id, $hours, $opts, &$error)` is the one path that bills time - Unbilled Time, `add_invoice_from_ticket`, `add_ticket_to_invoice`. It validates first, makes the draft with `billingInvoiceCreate()` when needed, claims the tickets with a conditional `UPDATE` (deleting a new draft that ends up with none), adds the lines through `billingItemSave()`, and logs ticket history, invoice history and the audit log. `billingTimeWriteOff()` sets `ticket_billable = 0`. `invoiceUnbilledTickets()` is replaced by `billingUnbilledTickets()`, and `roundToNearest15Min()` is no longer used.
  - New page `agent/unbilled_time.php` with `js/unbilled_time.js`, and handlers `unbilled_invoice`, `unbilled_invoice_clients` and `unbilled_write_off` in `agent/post/billing.php`. `add_ticket_to_invoice` takes `ticket_ids[]`, `hours[id]`, `bill_time`, `item_name`, `price`, `tax_id` and `lines` (`each` or `one`). `add_invoice_from_ticket` keeps its field names. `remove_ticket_from_invoice` writes to the ticket's history.
  - Quotes (`functions/billing.php`): `quoteFrequencies()`, `quoteFrequency()` (anything else is one time), `quoteLineGroups($items, $discount)` (lines grouped by frequency with subtotal, tax, taxes by name, discount and total each; the discount sits on the one-time group, or the first recurring one when there is none), `quoteGroups($quote)`, `quoteHasRecurring()`, `quoteMonthly()` and `quoteAmountText()`. `quote_amount` is the one-time group's total and `quote_monthly_amount` the recurring ones as a month, both set by `billingRecalc()`. `billingTotals()` for a quote adds `groups`, `recurring` and `monthly`. `billing_item_save` takes `frequency` on quote lines, and a form without it leaves the line's frequency alone.
  - `quoteConvert($quote_id, $opts, &$error)` is the one path that invoices a quote, used by `add_quote_to_invoice`. `$opts`: `date`, `first_period`, `recurring[frequency]` with `to` (a recurring invoice id, or 0 for a new one), `start` and `auto_send`, and `by`. It checks every target first, claims the quote with a conditional `UPDATE` from Accepted to Invoiced, and makes everything in one transaction, rolled back and logged with `logApp()` on failure. Lines are copied with `billingCopyLines()`. It returns `invoice_id`, `recurring` (id => number) and `numbers`, or null with `$error`. `quoteMadeFrom()` lists what a quote became. A recurring invoice that only got lines is found through its history entry, which carries `history_quote_id` as well as `history_recurring_invoice_id`, so `billingHistory()` leaves such rows off the quote's history. `delete_quote` keeps those rows and clears `invoice_quote_id` and `recurring_invoice_quote_id`.
  - `agent/modals/quote/quote_to_invoice.php` posts those fields. Quote lines carry `data-group`, with `tr.itflow-paper-group` headings in `includes/billing_paper.php`, and the Sortable in `js/billing_items.js` drags only `tr[data-item-id]`, within its group when the table is grouped.
  - `reportCsv()` writes a totals row as given, without the column's `csv` function, which read fields a totals row doesn't have. The quotes export takes `quote_monthly_amount`.
  - Removed: `add_invoice_item`, `edit_invoice_item`, `delete_invoice_item`, `add_quote_item`, `edit_quote_item`, `delete_quote_item`, `add_recurring_invoice_item`, `edit_recurring_invoice_item`, `delete_recurring_invoice_item` and `link_invoice_to_ticket` (it wrote a column that does not exist), `update_invoice_items_order`, `update_quote_items_order` and `update_recurring_invoice_items_order` in `agent/ajax.php`, `agent/modals/invoice/invoice_item_edit.php`, `agent/modals/quote/quote_item_edit.php`, `agent/modals/recurring_invoice/recurring_invoice_item_edit.php`, `guest/guest_quote_upload_file_modal.php` and `js/product_autocomplete.js`.
- Billing emails, in `functions/billing_email.php` (loaded by `functions.php`):
  - Schedule: `billingReminderRules($fresh)` reads the 2.9.8 settings once a request, falling back to 1 and 30 days late before 2.9.8 is applied, so they are not in `load_global_settings.php`. Also `billingReminderDaysParse()`, `billingReminderOnDay($days, $rules)` (days late, negative before the due date: a listed day, or every `repeat` days after the last listed day), `billingReminderScheduleText()` (the settings page's script mirrors it), `billingReminderNext($invoice, $rules, $late_only)`, `billingReminderAutopay()` and `billingReminderWhen()`.
  - Layout: `billingEmailHtml($email)` draws every billing email from one array (`kind`, `tone`, `title`, `greeting`, `intro`, `note`, `summary`, `lines`, `button`, `after`, `footnote`) as inline-styled tables, for mail clients. The templates return `[$subject, $email]`: `billingInvoiceEmail($invoice, $opts)` (`kind` invoice or reminder, and `note`; a paid invoice gets the receipt), `billingQuoteEmail()`, `billingPaymentEmail($applied, $payment)`, `billingStatementEmail()` and `billingNotifyEmail()`.
  - Sending: `billingEmailRecipients($client_id, $contact_ids, $type)` returns the client's own contacts with an address, once each, or the defaults from `documentDefaultContactFilterSql()` when `$contact_ids` is null. `billingEmailQueue($recipients, $subject, $email, $opts)` (`from` and `attach`) queues one row each and returns "Name (email)" labels for the history. Also `billingEmailFrom()` and `billingEmailReady()`.
  - Attachments: `billingEmailPdf($pdf)` writes the PDF under `uploads/tmp/billing_mail/<random>/` for the queue's attachment manifest, and `billingEmailPdfCleanup($days)` removes folders older than that. The nightly run calls it with 7.
  - Receipts and notices: `billingPaymentSend($invoice_id, $amount, $payment, $notify)` is used by `add_payment`, saved-card and guest Stripe payments and cron autopay. With `$notify` it also emails `config_invoice_paid_notification_email` through `billingPaymentNotify()`. `guest/guest_post.php` calls `billingQuoteAnsweredNotify($quote_id, $accepted)`.
  - Statements: `billingStatement($client_id, $from, $to, $include_paid)` returns the invoices with paid, balance and days late, the total, what is overdue and the `aging` buckets. Also `billingStatementPeriod()`, `billingStatementPdf()` and `billingStatementSend($statement, $contact_ids, $opts)`. The drawing is `billingStatementPdfRender()` in `functions/billing_pdf.php`, where `billingPdfConfig()` now loads the footer settings when the globals are not set, as in cron.
  - Handlers: `email_invoice` takes `kind` (`invoice` or `reminder`), `note` (up to 2000 characters) and `attach_pdf`, and `email_quote` and `send_statement` take `note` and `attach_pdf`. New GET `export_client_statement_pdf=client_id`, with `dtf`, `dtt` and `include_paid`. `edit_invoice_settings` takes `config_send_invoice_reminders`, `reminder_before`, `reminder_on_due`, `reminder_after`, `reminder_repeat`, `config_statement_monthly`, `config_statement_day` and `config_billing_email_attach_pdf`. `edit_notification_settings` no longer writes `config_send_invoice_reminders`. Client add and edit take `reminders` with a hidden `reminders_field`, and a form without the field leaves `client_reminders` alone.
  - `agent/modals/invoice/invoice_email.php?kind=reminder` opens on the reminder. Recurring auto-send, the batch payment receipt and the nightly reminders use the same functions. The nightly late fee goes through `billingItemSave()`. Reminders write "Reminder emailed to ..." history, and today's "Cron Emailed Overdue Invoice" entries from before the update still count as sent. Monthly statements are logged with `logAudit("Client", "Statement", ...)`, which is also what stops a second one the same day.
- Products:
  - `functions/products.php` (loaded by `functions.php`): `productTypes()`, `productSeatSources()`, `productBilledText()`, `productMargin()` (percent of the price, null without a cost), `productMarginPill()`, `productStockSql()`, `productTile()`, `productRow()` (with category, tax, vendor and stock), `productVendors()`, `productCopy()`, `productDelete()` (with its stock), `productUsage()` (recurring, open quotes, invoice lines, sold over 12 months and a month's recurring, scoped with `clientScopeSql()`), `productHistory()` and `productStockMoves()`.
  - `productSave($in, $product_id, &$error)` is the one path that writes a product - New, Edit, Copy, Import and notes. On an existing product only the keys given change. It validates the type, numbers, an Income category, the tax and one of our vendors, clears Counted when billed once and Track stock for a service.
  - Stock moves only when `product_track_stock = 1`, in `billingItemSave()`, `billingItemDelete()` and `api/v1/invoice_items/create.php`, where it used to follow `product_type = 'product'`.
  - `getProductsForAutocomplete()` adds `frequency`, `seat_source` and `track_stock`, and `js/billing_items.js` uses `itflowBillingBilledText()` and `itflowBillingPick()` to fill in Billed and Counted on the add-a-line row.
  - `billingMargin($type, $items)` and `billingMarginHtml()` work out the margin at today's product cost, by frequency for a quote, for the invoice, quote and recurring invoice pages.
  - New page `agent/product.php?product_id=`. `agent/modals/product/product_form.php` holds the fields of `product_add.php` and `product_edit.php`. Handlers: `copy_product` (GET), and `product_set_notes` in `agent/ajax.php` for the page's notes. `import_products_csv` maps columns by heading and takes `update_existing`. `agent/post/product_model.php` is removed. The list's bulk Restore posts `bulk_restore_products`.
  - Export columns for products: type, code, cost, margin, billed, vendor, part number, stock, location and notes. The products export takes `vendor` and the list's `type` tabs.
  - Seeds: `demoEnsureProducts($mysqli, $currency, $vendors)` with `demoProductExtras()`, and a seventh column on `starterContentProducts()` for how each is billed (`month`, `month:contacts`).
  - Stock ledger, in `functions/products.php`: `productStockMove($product_id, $qty, $type, $opts)` is the one path that writes `product_stock` (`$opts`: `note`, `unit_cost`, `invoice_id`, `item_id`, `expense_id`). The unit cost defaults to the product's cost for every type but `received`, and it records `stock_user_id` and calls `productReorderCheck()`, which raises a `Low Stock` notification (a `module_sales` type) when a move takes stock down to the reorder level or out. Also `productStockTypes()`, `productWriteOffReasons()`, `productOnHand()`, `productReorderSql()` (the one definition of to reorder), `productReorderCount()`, `productStockQty()`, `productStockShortfall($items)` (a message for the first short product, or '') and `productStockReturnInvoice($invoice_id, $why)`, which returns what an invoice still has out from the net of its moves, so it can't return twice. `productStockMoves($id, $limit)` adds the running balance, the invoice, expense and agent.
  - Callers: `billingItemSave()` and `billingItemDelete()` record `sold` and `returned` with the invoice and line. `billingCopyLines()` carries `item_product_id` (dropped when the product is gone) and records `sold` when copying onto an invoice. `quoteConvert()` and `add_invoice_copy` check `productStockShortfall()` before creating anything. `cancel_invoice` and `delete_invoice` call `productStockReturnInvoice()`, and `api/v1/invoice_items/create.php` links its move to the invoice and line.
  - Handlers: `product_stock_save` (POST `product_id`, `mode` and `note`, with `qty`, `unit_cost`, `update_cost` and `expense` to receive, `counted` to count, or `qty` and `reason` to write off) from `agent/modals/product/product_stock.php?id=&mode=`, and `product_stock_count` (POST `counted[product_id]` and `note`) from the new page `agent/products_count.php`. Removed: `agent/modals/product/product_stock_add.php` and `add_product_stock`.
  - `productSave()` and `productCopy()` take `reorder_level` and `reorder_qty`, with import aliases and export columns for them and the stock value. The products list and export take `type=reorder`.
  - `$num_products_reorder` in `agent/includes/get_side_nav_counts.php`, `Stock to reorder` in `dashboardAttention()`, and `inventory` in `reportCatalog()` (Sales & Billing, `module_sales`, accounting) for `agent/reports/inventory.php`.
  - Sold hardware, in `functions/products.php`: `productAssetTypes()` (the `$asset_types_array` list), `productAssetType()`, `productAssetIcon()`, `productHardwareSql()`, `productHardwareUnits()` (a line's quantity rounded up, none for a credit), `productWarrantyUntil()`, `productHardwareInvoiceSql()` (not drafts or cancelled, not made by a recurring invoice), `productHardwareShortSql()`, `productHardwarePending($days)`, `productInvoiceHardware($invoice_id)`, `productAssets($product_id)` and `productAssetCandidates()`. `productSaleAssets($invoice_id, $lines, $units, $shared, &$error)` checks every row first, then adds or links them in one transaction, each new one with its primary interface, and each with a history line and an audit log entry.
  - Handler `invoice_assets_save` (POST `invoice_id`, `status`, `location`, `lines[item_id][make|model|warranty]` and `units[item_id][n][name|serial|contact|existing]`; needs Sales, and Support at edit) from `agent/modals/invoice/invoice_assets.php?invoice_id=`. `billingItemDelete()` clears `asset_invoice_item_id` on the line's assets and `delete_invoice` clears both invoice links - the assets stay.
  - `productSave()` and `productCopy()` take `asset_type`, `make`, `model` and `warranty_months`, with import aliases and export columns for them. The products list and export take `type=hardware`, and `invoices.php` takes `hardware=1`. `dashboardAttention()` adds Hardware not on assets for agents with Support access, `billingPdfRender()` reads the serials for the packing slip, and the asset page joins the invoice and the product. Seeds: columns 12 to 15 on `demoDataProducts()` and an eighth and ninth on `starterContentProducts()`. CSS: `.itflow-hw-*`.
  - Price history: `productSave($in, $product_id, &$error, $source)` records a change to an existing product's price or cost through `productPriceRecord()` - `edit_product` passes `edit`, `import_products_csv` passes `import` and `product_stock_save` passes `receive` when a delivery sets the cost. Also `productPriceSources()`, `productPrices()` and `productPriceLastChange()`. `productDelete()` and the demo data removal delete the history too.
  - Repricing: `productRepriceKinds()`, `productRepriceUnionSql()` (every line from a product on recurring invoices not archived, draft invoices and draft quotes, as one union), `productRepriceOldSql()` (at a price in the product's history), `productRepriceFilterSql()`, `productRepriceCount($filters)` (lines, by kind, products, and the change a month on recurring invoices) and `productRepriceLines($filters)` (by product, with `old` and `suggested`). `productRepriceApply($changes, &$error)` checks every line and price, then changes them in one transaction through `billingItemSetPrice($type, $doc_id, $item_id, $price)` (new in `functions/billing.php` - the line's amounts at its tax rate, then `billingRecalc()`), with a history line on each document.
  - New page `agent/products_reprice.php` (`?product_id=`, `client_id`, `kind` and `doc_id`) posting `products_reprice_save` (`pick[]` of `kind:item_id`, and `price[kind:item_id]`). `dashboardAttention()` adds Lines at an old price, and the invoice, quote and recurring invoice pages link their own. CSS: `.itflow-reprice-*`.
- Expenses, in `functions/expenses.php` (loaded by `functions.php`):
  - `expenseRow($id, $scoped = true)` reads an expense with its vendor, category, account, client and creator, `transfer_id` when it is the out side of a transfer, the expense it refunds and `refunded_amount`. It is scoped to the agent's clients unless told otherwise.
  - `expenseSave($in, $id, &$error)` is the one path that writes an expense from a form - New, Edit, Copy and Refund. It checks the date, a non-zero amount, one of our vendors, an Expense category, an account, a client in scope and a listed payment method. An archived vendor, category or account is kept only when the expense already has it. `refund_of` makes the amount negative and sets `expense_refund_of_id`. A new expense takes the account's currency and records `expense_created_by`.
  - `expenseDelete($id, &$error)` refuses a transfer's expense. Otherwise it removes the receipt, clears `product_stock.stock_expense_id` and refunds' `expense_refund_of_id`, then deletes.
  - Receipts: `expenseReceiptTypes()`, `expenseReceiptPath()` (realpath-checked under `uploads/expenses/`), `expenseReceiptKind()`, `expenseReceiptName()`, `expenseReceiptStore($id, $file, &$error)` (replaces and deletes the old file) and `expenseReceiptRemove()`.
  - The list and export share `expenseFiltersSql($src, &$summary)` (q, vendor, category, account, client, method, show, dtf, dtt, with the client scope and the vendor-only rule), `expenseJoinsSql()` and `expenseShows()`, the pills.
  - Also `expenseStats()`, `expenseRecurringMonthlySql()`, `expenseVendorDefaults()` (the last expense's category, account and method by vendor), `expenseVendors()`, `expenseCategories()`, `expenseCategoryDot()` and `expenseLabel()`.
  - New `agent/expense_receipt.php?expense_id=` serves the receipt after the Financial and client checks: inline with `&action=view` for photos and PDFs, otherwise as a download. `uploads/expenses/.htaccess` denies the folder, and `.gitignore` keeps it.
  - New windows: the quick look `agent/modals/expense/expense.php?id=`, `expense_form.php` (the fields of `expense_add.php`, `expense_edit.php`, `expense_copy.php` and `expense_refund.php`) and `expense_bulk_edit.php?field=` (category, account, client or method).
  - Handlers: `add_expense` (with `refund_of`) and `edit_expense` (with `receipt_remove`) post `vendor`, `amount`, `date`, `category`, `account`, `payment_method`, `client_id`, `reference`, `description` and `file`. New are `expense_receipt_save` (POST `expense_id`, `file`) and `bulk_edit_expenses` (POST `field`, `value`, `expense_ids[]`). `export_expenses` takes `client`, `method` and `show`, and the export adds `expense_payment_method`, `expense_receipt_display` and `user_name` columns.
  - `agent/expenses.php` takes `?client=`, `?method=`, `?show=` and `?open=` (opens a quick look, as search does). `globalSearchGroups()` has an `expenses` group under `module_financial`. CSS: `.itflow-category-dot` and `.itflow-expense-receipt-empty`.
  - Removed: `agent/post/expense_model.php`, `agent/modals/expense/expense_bulk_edit_category.php`, `expense_bulk_edit_account.php` and `expense_bulk_edit_client.php`, and the `bulk_edit_expense_category`, `bulk_edit_expense_account` and `bulk_edit_expense_client` handlers.
  - Recurring expenses, in the same file:
    - They use the recurring invoice schedule helpers: `recurringInvoiceStep()`, `recurringInvoiceFrequencies()`, `recurringInvoiceMonths()` and `recurringInvoiceMonthlySql()`. `expenseRecurringMonthlySql()` now wraps the last.
    - `RECURRING_EXPENSE_GRACE_DAYS` (7), `recurringExpenseRunningSql()` (status 1, not archived, with a vendor) and `recurringExpenseMonthlyTotal()`. The dashboard and the Expenses, Recurring Revenue and Spend by Vendor reports use them.
    - `recurringExpenseRow($id, $scoped)` adds `made_count` and `made_year`. Also `recurringExpenseState()` (active, paused or ended - ended is status 0 with the next date past the end date), `recurringExpenseEnded()`, `recurringExpenseAfter()`, `recurringExpenseUpcoming()`, `recurringExpenseRunsUntil()`, `recurringExpenseCatchUp()`, `recurringExpenseScheduleText()`, `recurringExpenseOrdinal()`, `recurringExpenseNextPill()`, `recurringExpenseShows()`, `recurringExpenseFiltersSql()`, `recurringExpenseMade()` and `recurringExpenseLabel()`.
    - `recurringExpenseSave($in, $id, &$error)` is the one path that writes one. The anchor day follows the next date when that changes, and an end date before the next date ends it.
    - `recurringExpenseRecord($id, $by_user_id, $run, $by)` is the one path that records a run, used by cron and Record Now. It claims the run with a conditional `UPDATE` on the next date and refuses when `$run` is no longer the next date. It dates the expense `min(run, today)`, carries the method and reference, sets `expense_recurring_expense_id` and `expense_created_by`, and sets status 0 after the last run before the end date.
    - `recurringExpenseDelete()` unlinks the expenses it recorded.
    - Handlers: `create_recurring_expense` and `edit_recurring_expense` post `vendor`, `amount`, `frequency`, `next_date`, `end_date`, `category`, `account`, `payment_method`, `client_id`, `reference` and `description`. GET handlers: `recurring_expense_set_status` (with `status`), `recurring_expense_record` (with `run`), `recurring_expense_catch_up` and `delete_recurring_expense`, which now needs Financial at 3.
    - New windows: the quick look `agent/modals/recurring_expense/recurring_expense.php?id=` and `recurring_expense_form.php`, the fields of `recurring_expense_add.php` and `recurring_expense_edit.php`. `agent/recurring_expenses.php` takes `?status=` (active, soon, late, paused, ended, all), `vendor`, `category`, `client` and `?open=`. `agent/expenses.php` and its export take `?recurring_expense_id=`.
    - Cron: the recurring expense block in `cron/nightly_tasks.php` records active ones due from `RECURRING_EXPENSE_GRACE_DAYS` ago up to today, passing each next date as `$run`. It sends an `Expense Created` notification per expense, linking `expenses.php?open=`, and an `Expense` notification the day one falls one day past the grace. The nightly notification for every past next date is gone.
    - `dashboardExpectedExpenses()` walks each one's runs with `recurringExpenseRunsUntil()`.
    - Seeds: `demoBuildCompanyFinancials()` gives its recurring expenses `recurring_expense_anchor_day` and a next date one period after the last of their history, and links that history.
  - Billing expenses on, in the same file:
    - `expenseUnbilledSql()` is the one definition of an expense to bill: billable, on no invoice, for a client, with a vendor and a positive amount, not archived. `expenseUnbilled($client_id, $ids)` reads them in the agent's client scope with `cost` (less refunds - fully refunded ones are left out), `price` (`expenseBillPrice($cost, $markup)`) and `waiting`. Also `expenseUnbilledByClient()`, `expenseUnbilledCount($client_id)` (count and value, in SQL, for the menus, dashboard and client overview), `expenseMarkupDefault()` and `expenseMarginText()`.
    - `expenseBillInvoice($client_id, $prices, $opts, &$error)` is the one path that bills expenses. It validates the prices and tax, uses one of the client's invoices whose lines can change or makes a draft with `billingInvoiceCreate()`, claims the expenses with a conditional `UPDATE`, adds a line each through `billingItemSave()` (`expenseBillLine()` names it), sets `expense_invoice_item_id`, and writes invoice history and the audit log. `expenseBillWithTime($client_id, $hours, $prices, $opts, &$error)` bills tickets with `billingTimeInvoice()` then expenses on the same invoice, for Unbilled Time.
    - `expenseUnbill($id, &$error)` takes one off - its line through `billingItemDelete()`, or only the link on a cancelled invoice - refusing on a paid or non-billable one (`expenseCanUnbill($status)`). `billingItemDelete()` frees the expense billed on a line it deletes, and `delete_invoice` frees the invoice's expenses. `expenseWriteOff()` sets `expense_billable = 0`. `expenseOnInvoice($invoice_id)` lists an invoice's with `cost` and `billed`. `expenseUnbilledMerge($clients, $client_id)` adds expenses to `billingUnbilledByClient()`'s clients.
    - `expenseSave()` takes `billable` and `markup` (0 to 1000, the default when blank) and refuses a new client while billed. `expenseRow()` adds the invoice's number and status and `billed_subtotal`. `expenseShows()` adds `to_bill` and `billed`.
    - Recurring: `recurringExpenseSave()` takes `billing` (`bill`, `covered` or empty, with a client), `markup` and `recurring_invoice_id`, which must be one of the client's from `recurringExpenseCoverOptions()`. `recurringExpenseRecord()` makes the expense billable with the markup when the recurring one is billed on. `recurringExpenseRow()` adds the covering recurring invoice. `recurringExpensesCovering($recurring_invoice_id)` and `recurringInvoiceCosts($recurring_invoice_id)` (revenue a month before tax, the running costs, margin and percent). `delete_recurring_invoice` clears `recurring_expense_recurring_invoice_id`.
    - Handlers: GET `expense_unbill` (Financial and Sales at 2) and POST `invoice_add_expenses` (`invoice_id`, `expense_ids[]`, `expense_price[id]`, `tax_id`). `unbilled_invoice` and `unbilled_write_off` take `expense_ids[]` and `expense_price[id]` too (Financial at 2 when they are posted), and `unbilled_invoice_clients` bills each client's expenses for agents with Financial at 2. `bulk_edit_expenses` takes `field=billable` (`value` yes with `markup`, or no). `edit_invoice_settings` saves `config_expense_markup`. The export adds `expense_billing_display` and `expense_billed_on`.
    - New window `agent/modals/invoice/invoice_add_expenses.php?invoice_id=`. `recurring_expense_add.php` takes `?recurring_invoice_id=`. `agent/invoice.php` has the Expenses card and `agent/recurring_invoice.php` the Costs card for agents with Financial access. `js/unbilled_time.js` counts `[data-unbilled-expense-row]` rows with their `[data-unbilled-expense-pick]` and `[data-unbilled-expense-price]`. CSS: `.itflow-unbilled-price`.
    - `dashboardAttention()` and `clientOverviewAttention()` add Expenses to bill, and `clientOverviewMoney()` adds `unbilled_expenses` and `unbilled_expense_value`. `get_side_nav_counts.php` and `inc_all_client.php` set `$num_unbilled_expenses`.
  - Statement import, in the new `functions/expense_import.php` (loaded by `functions.php`):
    - `expenseImportRead($path, &$error)` reads a CSV up to `EXPENSE_IMPORT_MAX_BYTES` (2 MB) and `EXPENSE_IMPORT_MAX_LINES` (2000): byte order mark, Windows-1252, the delimiter the first lines agree on, and the header as the line before the first with a date. `expenseImportDateFormats()`, `expenseImportDate($value, $format)`, `expenseImportLooksLikeDate()` and `expenseImportAmount()`.
    - `expenseImportGuess($header, $rows)` maps date, description, memo, amount or debit and credit, and reference by header name then by content, with `date_format` and `sign`. `expenseImportMapClean()` checks a posted or saved one. `expenseImportSavedMap()` and `expenseImportSaveMap()` keep it in `accounts.account_import_map`, keyed on the column names (`expenseImportColumnsKey()`).
    - `expenseImportLines($rows, $map)` gives each line `date`, `description` (with the memo), `match_text`, `amount` (charges positive), `reference` and `problem`. `expenseImportReview($import)` runs `expenseImportMatch()` (rules from `expenseImportRules()`, then vendor names through `expenseImportWords()`), `expenseImportKeys()` (SHA-256 of account, date, amount, text and repeat, as `key_hash`) and `expenseImportDuplicates()` (`imported`, and `entered` within `EXPENSE_IMPORT_DUPLICATE_DAYS`). `expenseImportMatchKey()` and `expenseImportVendorName()`.
    - `expenseImportSave($import, $picks, &$error)` checks every ticked line first, makes new vendors once (`vendorFormSet()`), inserts the expenses with `expense_import_key`, upserts `expense_import_rules` on `import_rule_match`, and stamps the key on hand-entered expenses a left-out line matched.
    - New page `agent/expense_import.php` (`?step=columns` and `?step=review`, the statement in `$_SESSION['expense_import']`) and `js/expense_import.js` (`itflowExpenseImport()`). Handlers in the new `agent/post/expense_import.php`: `expense_import_upload` (POST `account`, `payment_method`, `file`), `expense_import_columns`, `expense_import_save` (POST `picks`, one JSON list of `n`, `take`, `vendor` or `new` with `new_vendor`, `category`, `client`, `billable` - under `max_input_vars` for long statements), GET `expense_import_discard` and `expense_import_rule_delete`. New window `agent/modals/expense/expense_import_rules.php`. CSS: `.itflow-import-*`.
  - Paid personally, in `functions/expenses.php`:
    - `expenseSave()` takes `account=personal` with `paid_by` (one of `expensePayers()`), saving `expense_account_id = 0` and `expense_paid_by`, in the company currency. Money back can't be paid personally, and a paid back expense keeps who paid it. `expenseSave()` and `expenseDelete()` refuse the payment row of a reimbursement, as they do a transfer's.
    - `expenseToReimburseSql()`, `expenseToReimburse($user_id, $ids)` (by agent, scoped), `expenseToReimburseCount()` and `expenseReimburse($user_id, $ids, $opts, &$error)`, which claims the expenses with a conditional `UPDATE`, then inserts the payment as an expense with no vendor and category on the account and the `reimbursements` row pointing at it. `reimbursementRow()`, `reimbursementExpenses()` and `reimbursementDelete()`.
    - `expenseRow()` adds `paid_by_name`, `reimbursement_date`, `reimbursed_account_name`, `reimbursement_out_id` and `reimbursed_to_name` (its joins on `accounts` and `users` are aliased now). `expenseShows()` adds `to_reimburse`, and `expenseFiltersSql()` takes `paid_by`.
    - Handlers: POST `expense_reimburse` (`user_id`, `expense_ids[]`, `account`, `date`, `payment_method`, `reference`) and GET `expense_reimbursement_delete` (Financial at 3). `bulk_edit_expenses` with `field=account` clears `expense_paid_by` and skips paid back ones. The export adds `expense_paid_by_display`.
    - New windows `agent/modals/expense/expense_reimburse.php` (`?user_id=` or `?expense_ids[]=`) and `expense_reimbursement.php?id=`. `agent/transactions.php`, its export and `agent/post/transaction.php` give the payment the type Reimbursement, with the agent as the other side. `dashboardAttention()` adds Expenses to reimburse.
- Trips, in the new `functions/trips.php` (loaded by `functions.php`):
  - Access: `tripCanSeeAll()` (Financial at 1), `tripCanManage()` (Financial at 2), `tripIsMine($row)` (drove or logged it), `tripCanEdit($row)`, `tripCanDelete($row)` (theirs, or Financial at 3) and `tripScopeSql()` - `clientScopeSql('trips.trip_client_id')`, plus their own trips only without Financial access. Every read below goes through it.
  - `tripRow($id, $scoped = true)` reads a trip with its client, driver (`driver_name`, `driver_avatar`), `created_by_name`, location and ticket, through `tripJoinsSql()` (`users` aliased `driver`). `tripsFor($column, $id, $limit)` and `tripTotalsFor($column, $id)` read the trips of a ticket, location or client, and `tripTableHtml($rows)` draws them for a location's Trips tab.
  - `tripSave($in, $id, &$error)` is the one path that writes a trip from a form - New, Edit, Copy and Return. It takes `date`, `driver`, `client_id`, `ticket_id`, `location_id`, `source`, `destination`, `miles` (one way), `round_trip`, `odometer_start`, `odometer_end` and `purpose`. `trip_miles` is the whole trip: both odometer readings make it, otherwise the miles one way, doubled for a round trip. A ticket brings its client (and needs Support access), a location and ticket must be the client's, and without `tripCanManage()` the driver is the agent. It sets `trip_created_by` on insert. `tripDelete($id, &$error)` checks `tripCanDelete()`.
  - The list and export share `tripFiltersSql($src, &$summary)` (q, driver, client, location_id, ticket_id, show, dtf and dtt) and `tripShows()`, the pills (`mine`, `client`, `ticket` and `own`). Also `tripStats()`, `tripDrivers()`, `tripClientSites()`, `tripClientTickets()`, `tripKnownRoutes()` (one way miles by lowercased `from|to`, both directions, the latest first), `tripStartPlaces()`, `tripLastStart()`, `tripOfficeAddress()`, `tripLocationAddress()`, `tripOneWay()`, `tripLabel()`, `tripMapUrl()`, `tripUnit()` and `tripMilesText()`.
  - New windows: the quick look `agent/modals/trip/trip.php?id=`, `trip_form.php` (the fields of `trip_add.php`, `trip_edit.php` and `trip_copy.php`), `trip_bulk_edit.php?field=` (driver or client) and `trip_bulk_delete.php`, with the menu in `agent/includes/trip_bulk_menu.php`. `trip_add.php` takes `?client_id=`, `?location_id=` and `?ticket_id=`, and `trip_copy.php` takes `?return=1`.
  - Handlers in `agent/post/trip.php`: `add_trip` and `edit_trip` (with `trip_id`) post the fields above through `tripSave()`. GET `delete_trip`. New are `bulk_edit_trips` (`field`, `value`, `trip_ids[]`; Financial at 2) and `bulk_delete_trips` (Financial at 3). `export_trips` takes the list's filters and adds the `user_name`, `round_trip_display`, `trip_one_way`, `location_name`, `ticket_display`, `trip_start_odometer` and `trip_end_odometer` columns. The old fields `roundtrip` and `user` are gone.
  - `agent/trips.php` takes `?driver=`, `?client=`, `?location_id=`, `?ticket_id=`, `?show=` and `?open=`, and is open to every agent. `side_nav.php` lists Trips under Finance for everyone, `globalSearchPages()` offers it with accounting on, and `inc_all_client.php` counts `$num_trips` through `tripTotalsFor()`.
  - `agent/ticket.php` has the Trips card and Log a trip. `agent/location.php` has the Trips tab. `agent/reports/mileage.php` joins the location and ticket and links each date to `trips.php?open=`.
  - Kept in step: `ticketDeleteForGood()` and `locationClearReferences()` clear `trip_ticket_id` and `trip_location_id`. `ticketMergeInto()` with `$move_replies` moves the trips. `change_client_ticket` moves the ticket's trips to the new client, without a location.
  - Seeds: `demoBuildClient()` gives the quarterly visits the primary location's address and id, and adds a trip for each onsite ticket resolved in the last six months. Every seeded trip has `trip_created_by`.
  - CSS: `.itflow-trip-route`.
  - Removed: `agent/post/trip_model.php`.
  - Mileage money, in the same file:
    - `tripRules()` reads the settings (`unit`, `rate`, `category_id`, `vendor_id`, `charge`, `bill_rate`, `included`, `item_name`). `tripUnit()` and `tripUnitOne()` follow the unit, and `tripMilesText()` with them. Also `tripPayBackOn()`, `tripBillingOn()`, `tripRateInput()` (up to four decimals), `tripRateMoney()`, `tripRateText()`, `tripDistanceText()`, `tripBillPrice($row)` (the charge plus the rate on the distance beyond `included`) and `tripBillRuleText()`.
    - Paying back: `tripClaimSql()`, `tripsToClaim($user_id, $ids)` (by driver, scoped), `tripToClaimCount()` and `tripClaim($user_id, $trip_ids, $opts, &$error)`. It makes one expense per client with `expense_paid_by` the driver, `expense_account_id` 0, the vendor from `tripMileageVendor()` and the category from `tripMileageCategory()` (both made and stored in the settings when missing; `tripMileageCategoryShown()` reads without making), and claims the trips into `trip_expense_id` with a conditional `UPDATE` before the amount is set. `expenseDelete()` clears `trip_expense_id`.
    - Billing: `tripUnbilledSql()`, `tripUnbilled($client_id, $ids)`, `tripUnbilledCount()`, `tripUnbilledMerge($clients, $client_id)` (after `expenseUnbilledMerge()`, adding `trips`, `trip_value` and `trip_miles`), `tripBillLine()`, `tripBillInvoice($client_id, $prices, $opts, &$error)` (as `expenseBillInvoice()`), `tripCanUnbill()`, `tripUnbill()`, `tripsOnInvoice()` and `tripWriteOff()`. `expenseBillWithTime()` takes `$trip_prices` as a sixth argument and returns `trips`. `billingItemDelete()` and `delete_invoice` clear `trip_invoice_id` and `trip_invoice_item_id`.
    - `tripSave()` takes `own_vehicle` and `billable`, keeping the distance, driver and vehicle of a trip on a mileage expense and the distance, client and billable of a billed one. `tripDelete()` refuses either. `tripRow()` adds `claim_expense_id`, `claim_date`, `claim_amount`, `reimbursement_date`, `invoice_prefix`, `invoice_number`, `invoice_status`, `billed_subtotal` and `ticket_billable`. `tripClientTickets()` adds each ticket's billable, and `tripDriverVehicles()` gives each driver's vehicle on their latest trip.
    - `tripShows()` adds `to_pay` and `to_bill` when paying back or billing is on, `tripFiltersSql()` takes `expense_id` and `invoice_id`, and `tripsFor()` and `tripTotalsFor()` take `trip_expense_id` and `trip_invoice_id`.
    - Handlers in `agent/post/trip.php`: `trip_claim` (POST `user_id`, `trip_ids[]`, `rate`, `date`, and `pay_now` with `account`, `payment_method` and `reference`; Financial at 2), GET `trip_unbill` (Financial and Sales at 2) and POST `invoice_add_trips` (`invoice_id`, `trip_ids[]`, `trip_price[id]`, `tax_id`). `bulk_edit_trips` takes `field=vehicle` and `field=billable`, and leaves trips paid for or billed alone. `unbilled_invoice`, `unbilled_invoice_clients` and `unbilled_write_off` in `agent/post/billing.php` take `trip_ids[]` and `trip_price[id]` (`unbilledPostTripPrices()`, Financial at 2). The export adds `vehicle_display`, `paid_back_display` and `billing_display`, and its distance column is labelled in the unit.
    - New windows `agent/modals/trip/trip_claim.php` (`?user_id=` or `?trip_ids[]=`, one driver's) and `agent/modals/invoice/invoice_add_trips.php?invoice_id=`. `agent/unbilled_time.php` has the Travel to bill card, with `data-unbilled-trip-row`, `-pick`, `-price` and `-all` in `js/unbilled_time.js`. `agent/invoice.php` has the Travel card.
    - Settings: the Trips and Mileage section of `admin/settings_invoice.php` (`#mileage`), saved by `edit_invoice_settings`.
    - `get_side_nav_counts.php` and `inc_all_client.php` set `$num_unbilled_trips` for the Unbilled Time badge. `dashboardAttention()` adds Trips to bill and Mileage to pay back, and `clientOverviewAttention()` Trips to bill. `dashboardTrips()` returns `unit` for the chart, and the Mileage report's `rate` defaults to the mileage rate.
    - Seeds: `demoBuildClient()` and `demoBuildCompanyFinancials()` set `trip_own_vehicle` for the last 45 days, and `trip_billable` for a billable ticket's trip in the last 30 when `tripBillingOn()`.
- Income, in the new `functions/income.php` (loaded by `functions.php` after `trips.php`):
  - `incomeRevenueSql($column)` is the one rule for which revenues are income - those that aren't the in side of a transfer (`NOT EXISTS` on `transfers.transfer_revenue_id`), categorised or not. `dashboardRevenueSql()` returns it, and `clientOverviewMoney()`, `clientOverviewMonthly()` and `dashboardTopClients()` use that in place of `revenue_category_id > 0`.
  - Access: `incomeCanEdit()` (Financial at 2), `incomeCanDelete()` (Financial at 3) and `incomeCanEditPayments()` (Sales and Financial at 3, as `edit_payment` and `delete_payment`).
  - `incomeUnionSql()` is every payment and non-transfer revenue as one row set, in the agent's client scope (`clientScopeSql('COALESCE(invoice_client_id, 0)')` for payments), with `income_type` (`payment` or `revenue`), `income_id`, `income_date`, `income_created_at`, `income_invoice_id`, `invoice_prefix`, `invoice_number`, `invoice_status`, `income_description` (the invoice's scope for a payment), `income_category_id`, `category_name`, `category_color`, `income_client_id`, `client_name`, `income_amount`, `income_currency_code`, `income_method`, `income_reference`, `income_account_id`, `account_name`, `account_archived_at` and `income_online` (`incomeOnlineSql()`: a reference of `Stripe - pi_...`). Select from it as a derived table `AS income`.
  - The list and export share `incomeFiltersSql($src, &$summary, $with_show = true)` (q, client, category, account, method, show or type, dtf and dtt; q also matches an amount) and `incomeShows()`, the pills (`payments`, `other`, `online`, `uncategorised`). `incomeShowFrom($src)` maps the older `?type=payment` and `revenue`, either case. Also `incomeTotal($from, $to, $client_id)`, `incomeStats($client_id)` (`month`, `last_to_date`, `change`, `year`, `year_average`, `recurring`, `recurring_count`, `owed`, `owed_count`, `overdue`, `overdue_count`) and `incomeCategories($keep_id)`, which leaves out Account Transfer.
  - Other income: `incomeRevenueRow($id, $scoped = true)` (with `client_name`, `category_name`, `account_name`, `account_currency_code` and `created_by_name`; null for a transfer's leg), `incomeRevenueLabel($row)`, `incomeRevenueSave($in, $id, &$error)` - the one path that writes it, taking `date`, `amount`, `account`, `category`, `client_id`, `payment_method`, `reference` and `description`, checking each, taking the account's currency and setting `revenue_created_by` on insert - and `incomeRevenueDelete($id, &$error)`.
  - Payments: `incomePaymentRow($id)` (the invoice's number, status, amount, dates, scope, client and category, `invoice_paid`, `others` - the other payments on the invoice - and `fee`, an expense whose reference starts with an online payment's reference) and `incomePaymentLabel($row)`.
  - `incomeBulkIds($refs)` turns the list's `payment-12` and `revenue-7` (either case, so the old `Payment-12` too) into `['payment' => ids, 'revenue' => ids]`.
  - `agent/income.php` is rebuilt on these, taking `?client=` (or the client pages' `?client_id=`), `?category=`, `?account=`, `?method=`, `?show=`, `?type=`, the date filter, `?open=payment-12` or `revenue-7`, and sorting on a whitelist only.
  - Handlers: `agent/post/revenue.php` - `add_revenue` (New and Copy) and `edit_revenue` (with `revenue_id`) through `incomeRevenueSave()` at Financial 2, GET `delete_revenue` through `incomeRevenueDelete()` at Financial 3, logged with the client. `agent/post/income.php` - `bulk_edit_income` (`field` = category, account, method or client, `value`, `income_ids[]`; Financial at 2, payments only with `incomeCanEditPayments()`), `bulk_delete_income` (Financial at 3, other income only) and `export_income` through `incomeFiltersSql()`, with `income_invoice` and `income_online` added to the `income` export columns. `agent/post/payment.php` adds GET `payment_send_receipt` (Sales at 2), resending the receipt through `billingPaymentSend()`.
  - New windows: `agent/modals/payment/payment.php?id=` and `agent/modals/revenue/revenue.php?id=` (quick looks), `revenue_form.php` (the fields of `revenue_add.php`, which takes `?client_id=`, `revenue_edit.php` and the new `revenue_copy.php`), `agent/modals/income/income_bulk_edit.php?field=` and `income_bulk_delete.php`, with the menu in `agent/includes/income_bulk_menu.php`. `income_export.php` posts `show`, `client`, `dtf` and `dtt` in place of `type`, `date_from` and `date_to`.
  - `globalSearchGroups()` has an Income group over `incomeUnionSql()`. `reportCategoryYearTable()` takes a sixth argument, the query that opens the No category row (`show=uncategorised` on the Income Summary). `agent/transactions.php` opens `payment.php` and `revenue.php`, and `agent/accounts.php` links each account's Transactions and Income.
  - Seeds: `demoBuildClient()` sets `revenue_created_by`, and `demoBuildCompanyFinancials()` adds the company's rebates, interest and referral fees (references `RBT-`, `INT-` and `REF-`, removed with the demo data).
  - CSS: `.itflow-income-from`.
  - Removed: `agent/post/income_model.php`, and `agent/modals/income/income_bulk_edit_account.php`, `income_bulk_edit_category.php` and `income_bulk_edit_method.php` (`bulk_edit_income_account`, `bulk_edit_income_category` and `bulk_edit_income_method` are replaced by `bulk_edit_income`).
- Import Deposits, in the new `functions/income_import.php` (loaded after `income.php`), on top of `functions/expense_import.php`'s reader:
  - `incomeImportLines($rows, $map, &$charges)` keeps the money in from `expenseImportLines()`, made positive, counting the charges. `incomeImportOpenInvoices()` is every invoice still owed in the agent's client scope, oldest due first, with its balance. `incomeImportRules()`, `incomeImportMethod($text, $default)`, `incomeImportInvoiceNumbers($text, $prefixes)` (after an invoice prefix in use or the word invoice - never a bare number), `incomeImportPickInvoices($invoices, $amount)` (`exact`, `sum` - the fewest adding up to it, of up to 14 - `oldest` or `over`), `incomeImportNameWords()` and `incomeImportNameMatch()` (whole, or cut short), `incomeImportProviderAccounts()` and `incomeImportKind($text)` (`payout` or `transfer`).
  - `incomeImportMatch($lines, $account_id, $method)` sets each deposit's `action` (payment, income or transfer), `client_id`, `invoice_ids`, `how`, `category_id`, `from_account_id`, `method`, `matched` (number, rule, name, amount, payout or transfer) and `match_note`. `expenseImportKeys()` keys them. `incomeImportDuplicates($account_id, $lines)` sets `imported` (from `payment_import_key` or `revenue_import_key`) or `entered_type`, `entered_ids` and `entered_label` (a payment, revenue or transfer leg on the account within `INCOME_IMPORT_DUPLICATE_DAYS`, or one day's payments summing to it). `incomeImportReview($import, &$charges)` runs them all and sets `state` (new, imported, entered, recorded or problem).
  - `incomeImportSave($import, $picks, $opts, &$error)` checks every ticked line - `incomeImportCanPay()` (Sales and Financial at 2), the client in scope, its open invoices with running balances across the statement, an income category, another account - before inserting the payments (`updateInvoiceStatusFromPayments()`, history, `triggerCustomAction('invoice_pay')`, `billingPaymentSend()` with `$opts['receipts']`), revenues and transfers (`incomeImportTransfer()`), upserting `income_import_rules` and stamping the keys of entered lines left out.
  - New page `agent/income_import.php` (`?step=columns` and `?step=review`, the statement in `$_SESSION['income_import']`) and `js/income_import.js` (`itflowIncomeImport()`, with each client's open invoices). Handlers in the new `agent/post/income_import.php`: `income_import_upload`, `income_import_columns`, `income_import_save` (POST `picks`, one JSON list of `n`, `take`, `action`, `client`, `invoices`, `category`, `account`, and `receipts`), GET `income_import_discard`, `income_import_rule_delete`, and `income_import_from_statement` and `expense_import_from_statement`, which copy the statement in the session from one side to the other. New window `agent/modals/income/income_import_rules.php`.
  - The columns step is now `agent/includes/statement_columns.php` (`$columns_form_id`, `$columns_submit`, `$columns_side`), shared by `agent/expense_import.php` and `agent/income_import.php`. `expenseImportGuess()` takes the sign from the words on the lines when they are clear.
  - CSS: `.itflow-import-details` and `.itflow-import-invoices`.
- Accounts, in the new `functions/accounts.php` (loaded after `income_import.php`):
  - `accountTypes()` (id => `label` and `icon`), `accountTypeGuess($name)` and `accountType($row)`.
  - `accountBalanceSql($column, $to_date = null)` is the one balance rule: the opening balance plus payments and revenues, less expenses, leaving out archived rows, up to a date when given. `accountBalance($id, $to_date)`, `accountsList($archived = false, $keep_id = 0)` (false open, true archived, null both; each row with `type`, `balance`, `month_in`, `month_out`, `last_date`, `line_count` and `defaults`), `accountRow($id)`, `accountDefaults()` (the settings defaults and active payment providers' accounts), `accountBalanceText($account)` and `accountsStats($accounts)`. `paymentAccounts()` returns `accountsList(false, $keep_id)`, and `dashboardAccountBalances()` uses it too.
  - `accountInUse($id)` (payments, revenues, expenses, recurring expenses, payment providers and reimbursements), `accountSave($in, $id, &$error)` (`name`, `type`, `currency_code`, `opening_balance`, `description`, `notes`), `accountArchive()`, `accountRestore()` and `accountDelete()`, which clears the settings defaults that pointed at it.
  - `accountLedgerSql($account_id = 0)` is every line as one row set, `txn_kind` one of `accountLedgerKinds()` (`payment`, `revenue`, `transfer_in`, `expense`, `transfer_out`, `reimbursement`), with `txn_id`, `txn_transfer_id`, `txn_account_id`, `txn_date`, `txn_created_at`, `txn_amount` (signed), `txn_what`, `txn_description`, `txn_category_id`, `txn_client_id`, `txn_other_account_id`, `txn_other_name`, `txn_method`, `txn_reference`, `txn_invoice_id`, `txn_currency`, `txn_imported`, the account's and category's names, `client_name`, `other_account_name`, `other_account_type` (a transfer's other account's kind) and `txn_balance`, the running balance per account. Select from it `AS ledger`. The page and export share `accountLedgerFiltersSql($src, &$summary, $with_show, $all_accounts)` (q, kind or the old type names through `accountLedgerTypeNames()`, category, client, method or payment_method, amount_min, amount_max, show, dtf, dtt) and `accountLedgerShows()`. With every account, the `transfers` pill is the out side only. `accountLedgerModal($row)` is the quick look of a line.
  - Transfers: `transferRow($id)`, `transferLabel($row)`, `transferWhat($row)` ('Card payment' into a type 3 account), `transferSave($in, $id, &$error)` (`date`, `amount`, `account_from`, `account_to`, `transfer_method`, `notes`; updates both legs on edit, sets `expense_created_by` and `revenue_created_by` on insert) and `transferDelete($id, &$error)`.
  - `agent/transactions.php` is rebuilt on these, taking `?account=`, `?show=` (`in`, `out`, `transfers`), `?kind=` or `?type=`, `?category=`, `?client=`, `?method=` or `?payment_method=`, `?amount_min=`, `?amount_max=`, `?q=` and the date filter, and sorting on a whitelist only. `agent/transfers.php` redirects to `transactions.php?show=transfers`, mapping `account_from` or `account_to` to `account`. `agent/accounts.php` takes `?show=archived` and `?q=`.
  - Handlers: `agent/post/account.php` - `add_account`, `edit_account`, GET `archive_account` and `restore_account` (Financial at 2) and GET `delete_account` (at 3). `agent/post/transfer.php` - `add_transfer` and `edit_transfer` (with `transfer_id`; the form's `expense_id` and `revenue_id` are no longer read) at 2, GET `delete_transfer` at 3. `export_transactions` in `agent/post/transaction.php` reads the ledger with the page's filters; the `transactions` export columns add `transaction_account`, `transaction_what`, `transaction_client`, `transaction_in` and `transaction_out`, with `transaction_amount` off by default.
  - New windows: `agent/modals/account/account_form.php` (shared by `account_add.php`, which takes `?type=`, and `account_edit.php`), `agent/modals/transfer/transfer.php?id=` (quick look) and `transfer_form.php` (shared by `transfer_add.php`, which takes `?from=` and `?to=`, and `transfer_edit.php`). `transaction_export.php` takes the ledger's filters.
  - Seeds: `demoDataAccounts()` adds Business Visa, and accounts take `accountTypeGuess()`. `demoBuildCompanyFinancials()` puts the card's categories on it and pays it off from Operating Checking each month with `demoTransfer()`. Company costs paid from the bank are ACH.
  - CSS: `.itflow-account-name` and `.itflow-ledger-what`.
  - Removed: `agent/post/transfer_model.php`.
- Reconcile, in the new `functions/reconcile.php` (loaded after `accounts.php`):
  - `reconcileKinds()` (the payments, revenues and expenses columns), `reconcileLineRef('payment-12')`, `reconcileRow($id)` (with `account`, `finished` and `card`), `reconcileOpen($account_id)`, `reconcileLast($account_id)`, `reconcileStartingBalance($account_id)`, `reconcileTotals($row)` (`starting`, `ending`, `in`, `in_count`, `out`, `out_count`, `count`, `cleared`, `difference`), `reconcileLines($row, &$later)` and `reconcileHistory($account_id)`.
  - `reconcileStart($account_id, $in, &$error)` (`statement_date`, `ending_balance` - owed, for a card - and `notes`; after the last finished one, not after today; pre-ticks lines with an import key), `reconcileUpdate()`, `reconcileTick($id, $keys, $tick, &$error)`, `reconcileFinish()` (only at a difference of 0.00, storing the starting balance), `reconcileDiscard()` and `reconcileUndo()` (the latest finished one on its account, with none in progress). Ticking sets `*_updated_at` to itself, so it doesn't count as a change to the line.
  - Locks: `reconcileLock($kind, $id)` is the finished reconciliation a line cleared on. `reconcileCheckChange($kind, $id, $date, $amount, $account_id, &$error, $release = true)` refuses a change to a cleared line's date, amount or account, and takes a line ticked on one in progress off it when it moves account. `reconcileCheckDelete()`, `reconcileInvoiceLock($invoice_id)`, `reconcileClientLock($client_id)` and `reconcileAccountReconciled($account_id)`. They are called from `expenseSave()`, `expenseDelete()`, `incomeRevenueSave()`, `incomeRevenueDelete()`, `transferSave()` (both sides checked before either changes), `transferDelete()`, `reimbursementDelete($id, &$error)` (which now gives the error), `accountSave()` (the opening balance), and the `edit_payment`, `delete_payment`, `refund_payment_stripe`, `delete_invoice`, `delete_client`, `bulk_edit_income`, `bulk_delete_income`, `bulk_edit_expenses` and `bulk_delete_expenses` handlers and `api/v1/clients/delete.php`. `reconcileGlanceHtml()`, `reconcileFormNoteHtml()` and `reconcileLockedButtonHtml()` show it in the quick looks and Edit windows.
  - `accountLedgerSql()` adds `txn_line` (payment-12, revenue-5, expense-9), `txn_reconciliation_id`, `txn_cleared_id` and `txn_cleared_date`. `accountLedgerShows()` adds `uncleared`, and `accountLedgerFiltersSql()` takes `reconciliation` (one statement's lines). `accountLedgerWhat($row)` is a line's who or what, shared by Transactions and Reconcile. `accountsList()` rows add `reconciled_to` and `reconciling_id`, and `accountInUse()` counts reconciliations.
  - New page `agent/reconcile.php?account=` and `js/reconcile.js` (`itflowReconcile()`). Handlers in the new `agent/post/reconcile.php`: `reconcile_start` (`account_id`), `reconcile_update` and `reconcile_finish` (`reconciliation_id`) at Financial 2, GET `reconcile_discard` at 2 and `reconcile_undo` at 3. Ticking is POST `reconcile_tick` to `agent/ajax.php` (`reconciliation_id`, `lines[]`, `tick`), answering with the totals.
  - `agent/transactions.php` takes `?reconciliation=` and `?show=uncleared`, and the `transactions` export adds `transaction_cleared` (off by default). `agent/modals/expense/expense_add.php` and `agent/modals/revenue/revenue_add.php` take `?account_id=`.
  - Seeds: `demoReconcileAccounts()` reconciles Operating Checking and the Business Visa monthly, and `demoDataRemove()` removes the reconciliations on the demo's accounts.
  - CSS: `.itflow-reconcile-table`, `.itflow-reconcile-ticked`, `.itflow-reconcile-balanced` and `.itflow-reconcile-bar`, which sticks to the bottom of the window - `.app-main` is `overflow: visible` on that page so it can.
  - Brought forward: on an account never reconciled, `reconcileStart()` takes `start_from` ('statement'; anything else starts from the opening balance), `start_date` (on or before `statement_date`) and `start_balance` (owed, for a card), and first calls `reconcileBringForward($account, $to, $balance)` - a finished reconciliation with `reconciliation_opening` 1, starting at the opening balance and ending at the bank's, clearing every line up to the day before `start_date`. `reconcileOpening($account_id)` is that row, `reconcileOpeningGap($opening)` the bank's balance less ITFlow's there, and `reconcileCorrectOpening($id, &$error)` adds the gap to `accounts.opening_balance` and the row's starting balance once, keeping it in `reconciliation_adjustment`. `reconcileUndo()` deletes a brought forward row, freeing its lines and taking the adjustment back off the opening balance. `reconcileRow()` adds `opening`, `reconcileLock()` returns `reconciliation_opening` (worded by `reconcileLockText()` and `reconcileGlanceHtml()`), and the statement after it pre-ticks only the imported lines after the day brought forward to. `accountLedgerSql()` adds `txn_cleared_opening`.
  - GET `reconcile_correct_opening` (the brought forward reconciliation's id) in `agent/post/reconcile.php`, at Financial 2, logged as an Account Edit. `reconcilePostInput()` adds `start_from`, `start_date` and `start_balance`.
- Deposits, in `functions/accounts.php`: `transferDepositCandidates($account_id, $limit = 200)` (payments and non-transfer revenues on the account, not in a deposit or archived, after the latest transfer out of it with nothing linked, by date then `*_created_at` - each with `kind`, `id`, `key`, `date`, `amount`, `what`, `client_name`, `method` and `reference`), `transferDepositLink($transfer_id, $from_account_id, $keys)`, `transferDepositItems($transfer_id)`, `transferDepositedIn($kind, $id)`, `transferDepositedGlanceHtml($deposit)` and `transferDepositRelease($kind, $id, $account_id)`.
  - `transferSave()` takes `deposit` (`payment-12`, `revenue-5`; only on a new transfer, only lines on the account it leaves) and clears the links when an edit moves the account it leaves. `transferDelete()` clears them. `transferPostInput()` adds `deposit[]`. `edit_payment`, `bulk_edit_income` (Set Account) and `incomeRevenueSave()` call `transferDepositRelease()` before a line moves account. Linking and clearing leave `*_updated_at` as it was.
  - `agent/modals/transfer/transfer_form.php` puts each cash account's waiting lines (account type 4) in `[data-transfer-deposit]`'s `data-deposits` JSON for a new transfer, and renders them as `deposit[]` checkboxes. `transfer.php` lists the deposit, and `payment.php` and `revenue.php` show the deposit it went in.
  - CSS: `.itflow-deposit-list`.
- The billing helpers (`billingTimeInvoice()`, `quoteConvert()`, `recurringInvoiceGenerate()` and the rest) take the agent's name as plain text. Their handlers now pass `stripslashes($session_name)`, so a name with an apostrophe no longer shows a backslash in history and the audit log.
- Sidebar brand: `agent/css/sidebar_brand_fix.css` and `agent/js/sidebar_brand_fix.js` from 26.10 are replaced by CSS in `css/itflow_custom.css`. `includes/load_company_settings.php` now sets `$session_company_logo`.
- `alertMessageHtml()` no longer double-encodes, and save messages built from `escapeSql()` values use `stripslashes()` at the flash.
- CSS: `[hidden] { display: none !important; }` at the top of `css/itflow_custom.css`, so the `hidden` attribute wins over `d-flex` and the other display utilities. `.pagination` wraps.
- Client portal shell: `client/includes/header.php` and `footer.php` are rewritten - the top bar, the phone tab bar and the `#portalMenu` offcanvas all draw from `portalNav()`. A page that sets the Content-Security-Policy keeps it, and the header widens it to `default-src 'self'; img-src 'self' data:`. Dark mode is the `itflow_portal_theme` cookie (`dark` or `light`), read by the header for the first paint.
  - The new `js/portal.js`, loaded on every portal page: the dark mode switch (`[data-portal-theme-toggle]`), TinyMCE on `textarea.tinymce` (moved out of the inline script in the footer that the policy blocked; `data-required` checks the editor on submit), attachment drop zones (`[data-portal-drop]` with a file input and `[data-portal-files]`), `form[data-portal-once]`, and `.table-responsive` round any table a page left bare.
  - `client/functions.php` adds `portalNav()`, `portalAvatarHtml($name, $photo_url, $size)`, `portalSeesAllTickets()`, `portalTicketScopeSql($mine_only)`, `portalTicketState($row)` and `portalTicketStateHtml($row)`, `portalTicketTabs()`, `portalTicketColumnsSql()`, `portalTicketEventText($html)` and `portalHtmlIsEmpty($html)`. `portalEmptyState()` draws `emptyStateHtml()`, and `verifyContactTicketAccess()` takes `Any`.
  - `client/tickets.php` takes `tab` (`open`, `you`, `resolved`, `closed`, `all`), `scope` (`mine`), `q` and `page`. `client/ticket_view_all.php` redirects there. `add_ticket` takes `file[]` and checks the subject, details, category and asset. `client/ticket_attachment.php` refuses a file on an internal or archived reply.
  - Removed: `ticketPortalStatusHtml()` from `functions/ticket.php`, replaced by `portalTicketStateHtml()`.
  - CSS: the `.itflow-portal-*` classes, from `-header`, `-nav`, `-tabbar` and `-menu` to `-panel`, `-state-*`, `-tabs`, `-ticket`, `-steps`, `-msg`, `-event`, `-drop`, `-file-chip`, `-facts`, `-stat` and `-todo`. The black table heads on the pages not rebuilt yet are softened to sit in the light shell.
- Client portal billing:
  - New pages: `client/invoice.php`, `quote.php` and `recurring_invoice.php` (`?id=`, the session's client's only, never a draft or a paused recurring invoice), `payments.php` (`page`), `receipt.php` (`?id=` any payment of the receipt), `pay.php` (the checkout) and `pdf.php` (`?invoice=` or `?quote=`). `invoices.php` takes `tab` (`open`, `paid`, `all`), `q` and `page`; `quotes.php` takes `tab` (`waiting`, `accepted`, `all`), `q` and `page`; `statement.php` takes `view=year`; `unpaid_invoices.php` takes `select=ID,ID`; `saved_payment_methods.php` takes `add=1` to open Stripe's form; `ticket_add.php` takes `subject`.
  - `client/post.php`: `portal_quote_answer` (`quote_id`, `answer` accept or decline, `name`, `note`, `authorised`), `portal_quote_file` (`quote_id`, `file[]`), `portal_pay` (`invoice_ids[]`, `method` - a saved card's id or `new` - and `pay_token`, which is the Stripe idempotency key) and `portal_pay_return` (Stripe's return from the checkout). `add_payment_by_provider` is removed. `export_statement_pdf` draws `billingStatementPdf()` and takes `paid=1`. `create_stripe_customer` goes on to `saved_payment_methods.php?add=1`.
  - The new `functions/billing_pay.php`: `billingStripeProvider()`, `billingStripeClient()`, `billingStripeCustomer($client_id)`, `billingOnlinePayAllowed($amount)`, `billingCardParts($description)`, `billingSavedCards($client_id)`, `billingPaySelection($client_id, $invoice_ids, &$error)`, `billingPaySplit()` and `billingPaySplitParse()`, `billingStripeIntentParams()`, `billingStripeChargeSaved()`, `billingStripeCheckoutIntent()`, `billingStripeBook($pi, $client_id, $by)`, `billingClientPayments()`, `billingPaymentReceipt($payment_id, $client_id)` and `billingPaymentMethodText()`. A portal PaymentIntent carries `itflow_invoices` (`invoice_id:cents,...`) and `itflow_client_id` in its metadata, plus `itflow_invoice_id` when it pays one invoice. `billingStripeBook()` takes a MySQL `GET_LOCK` on the PaymentIntent and books it once, a payment per invoice referenced `Stripe - pi_...`.
  - `functions/billing.php`: `billingQuoteAnswer($quote_id, $accept, $by)`, `billingQuoteAddFiles($quote_id, $files, $by)` and `billingQuoteFiles($quote_id)`. `guest/guest_post.php` answers quotes and takes their uploads through them, with the same messages. `billingGuestViewed()` takes `$who` for a contact in the portal.
  - `functions/billing_email.php`: `billingPaymentReceiptSend($applied, $payment, $notify)` sends one receipt for a payment over one invoice or several, and `billingPaymentSend()` calls it. `billingPaymentNotifyMany()` is the paid notification for several invoices. `billingQuoteAnsweredNotify()` takes who answered and their note.
  - `client/functions.php`: `portalBillingGate()` (billing access and the accounting module), `portalStateBadge()`, `portalInvoiceState($row, $paid)`, `portalQuoteState($row)`, `portalQuoteWaitingSql()`, `portalBillingViewed($type, $row)` (once a sign-in) and `portalAskUrl($subject)`. `portalNav()` adds Payments, and only lists Pay invoices and Cards & autopay when Stripe is set up.
  - A page that sets `$portal_stripe` before `includes/inc_all.php` gets the portal's Content-Security-Policy with Stripe's script, frames and API added (Stripe.js, Checkout and Link, as Stripe lists them), and Stripe.js with the new `js/portal_stripe.js` - the Payment Element on `pay.php` and the embedded Checkout for Add a card. `js/portal.js` adds `[data-portal-print]` and the Pay invoices form (`form[data-portal-pay]`, `[data-pay-cents]`, `[data-pay-all]`, `[data-pay-label]`).
  - `includes/billing_paper.php` takes `client` to leave out the agent-only note on a seat line.
  - `cron/nightly_tasks.php` reconciles Stripe fees by payment reference, so a card payment over several invoices gets one fee expense. `refund_payment_stripe` refunds the payment's amount.
  - CSS: `.itflow-portal-amount`, `-big-amount`, `-state-late`, `-outcome`, `-note`, `-payrow`, `-paybox`, `-methods`, `-method`, `-paybar`, `-stripe-element`, `-card-brand`, `-aging`, `-table` and `-receipt`, and print rules that leave only the document.
- Client portal Your IT:
  - New pages: `client/asset.php` (`?id=`, within `portalAssetScopeSql()`, so a plain contact reaches the devices they use), `contact.php` (`?id=`, or `?new=1` for the primary contact) with its form in `client/includes/contact_form.php`, `document_add.php` (the editor, or `?upload=1` for a file) and `licenses.php` (`tab` `all`, `soon` or `expired`, and `q`). `assets.php` takes `tab` (`all`, `computers`, `servers`, `network`, `phones`, `other`), `scope=mine`, `q` and `page`; `domains.php` takes `tab=certificates`; `documents.php` takes `folder` (a folder's id, or 0 for those in none), `q` and `page`; `contacts.php` takes `q`. `certificates.php` redirects to `domains.php?tab=certificates`, and `contact_add.php` and `contact_edit.php` to `contact.php`. `ticket_add.php` takes `asset` to pick the device.
  - `client/post.php`: `add_contact` and `edit_contact` are replaced by `portal_contact_save` (`contact_id`, 0 for new; the contact fields; `invite` and `method` for a new one), `portal_contact_access` (`contact_id`, `invite` or `off`, and `method`) and `portal_contact_left` (`contact_id`), all needing `contactCan('contacts_manage')` and refusing a primary contact or the contact's own record. A form that fails is kept in `$_SESSION['portal_contact_form']` for the page to fill back in. `client_add_document` and `client_upload_document` check `portalItGate()`, refuse a blank name or empty document, set `document_created_by` and `file_created_by` to the portal user and notify.
  - `client/functions.php`: `contactCan('contacts_manage')` (the primary contact only), `portalItGate()` (primary or technical, and the module), `portalAssetScopeSql()`, `portalAssetGroups()`, `portalTile($icon, $size)`, `portalRenewalState($renewal, $active_label)`, `portalContactRoles($row)`, `portalContactAccess($row)` (`none`, `off`, `local` or `azure`, with a label, icon and class), `portalContactLastSignIn($user_id)`, `portalSignInMethods()`, `portalContactInvite($contact_id, $method, $by)` and `portalContactAccessOff($contact_id)`. The invite makes or turns back on the contact's portal user and, when email is set up, sends `login_reset.php`'s own set-password link, or the sign-in page for Microsoft 365. `enforceContactCan()` sends the contact home with a message rather than to logout.
  - `client/includes/check_login.php` ends the session when the user has no sign-in method (`user_auth_method` not `local` or `azure`).
  - `client/includes/header.php` starts output buffering, so a gate after `includes/inc_all.php` sends a real redirect. A page that sets `$portal_csp_inline_styles` before `includes/inc_all.php` gets `style-src 'self' 'unsafe-inline'` added to the portal's policy, for HTML from `documentHtmlPurify()`; `document.php` sets it.
  - CSS: `.itflow-portal-tile` with `-sm` and `-lg` (the grey icon tiles), `.itflow-portal-roles`, `.itflow-portal-pdf` and `.itflow-portal-document`. The tiles in a list row are left off on phones.
- Client portal accounts (database 3.1.0):
  - New table `portal_announcements` (title, body, tone `info`/`warning`/`danger`, `announcement_client_id` 0 for every client, starts and ends, created by, archived). New columns `contacts.contact_ticket_emails` (`all` or `replies`) and `contacts.contact_company_ticket_emails` (`none`, `new` or `all`), and `settings.config_client_portal_require_mfa`.
  - Password links: `user_password_reset_token` holds the SHA-256 of the token and `user_password_reset_token_expire` is used at last. The migration hashes any link still out and gives it a day. `cron/nightly_tasks.php` forgets only the links that ran out.
  - The new `functions/portal.php`: `PORTAL_RESET_LINK_MINUTES`, `PORTAL_INVITE_LINK_DAYS`, `PORTAL_RESET_REQUEST_GAP_MINUTES`, `PORTAL_TICKET_EMAILS`, `PORTAL_COMPANY_TICKET_EMAILS` and `PORTAL_ANNOUNCEMENT_TONES`; `portalPasswordLink($user_id, $minutes)`, `portalPasswordLinkUser($email, $token, $client_id)` and `portalPasswordLinkClear($user_id)`; `portalContactLastSignIn()` (moved from `client/functions.php`); `portalRememberedBrowsers()`, `portalMfaReset()` and `portalMfaQrSvg()`; `portalAccountEmail()`; `portalAnnouncementsFor($client_id)`, `portalAnnouncementBodyHtml()` and `portalAnnouncementState()`; and `portalViewAsAccess($agent_user_id, $contact_id)`.
  - `login.php`: a portal user's secret is `users.user_token` and a remembered browser a `remember_tokens` row, as for agents. `pending_mfa_login` carries `type` (`client` or `agent`) and `client_user_id`, and the code step only matches the account whose password was checked. A client's row is matched on `contact_user_id`, as `users.user_id` comes back NULL from the `user_settings` join. Wrong codes are logged as Client Login / MFA Failed and counted per account. `$_SESSION['login_message_type']` = `success` shows the message in green.
  - `client/login_reset.php` is rewritten: it logs Client Login `Reset Requested`, `Reset Sent` and `Password Reset`, and limits asks to 10 per address in 10 minutes. `portalContactInvite()` makes its link with `portalPasswordLink()`.
  - `client/includes/check_login.php` sets `$session_auth_method`, `$session_mfa_on` and `$portal_mfa_setup_needed` (only `profile.php` and `post.php` open until set up), and `$portal_view_as` for View as contact - from `$_SESSION['portal_view_as']` in an agent's session, checked with `portalViewAsAccess()` on every page.
  - `client/post.php`: `edit_profile` is replaced by `portal_password_change` (`current_password`, `new_password`, `confirm_password`), with `portal_mfa_enable` (`verify_code`, against `$_SESSION['portal_mfa_secret']`), `portal_mfa_disable`, `portal_forget_browsers` and `portal_email_prefs` (`ticket_emails`, `company_ticket_emails`). While viewing as a contact, everything but `exit_view_as`, `logout` (which only leaves the view) and `export_statement_pdf` is turned away. `client/pay.php` refuses, and invoice and quote views, file and attachment downloads write nothing.
  - `client/functions.php`: `portalViewingAs()`; `contactCan('accounting')` is false while viewing as a contact without billing; `portalReauthenticate()` reads `$session_auth_method`.
  - `client/profile.php` is rebuilt (`?mfa=setup` opens setting up two-step sign-in) and `client/activity.php` takes `tab` (`signins`, `changes`) and `page`, without DataTables.
  - `functions/ticket.php`: `ticketMailContactAndWatchers()` takes `$kind` (`created`, `closed`, or `update`, the default) and sends `update` and `closed` to the colleagues who chose every update; `ticketContactWantsEmail($preference, $kind)`, `ticketColleagueRecipients($ticket_id, $kind)` and `ticketMailColleaguesNew($ticket_id)` - called from the agent's and the portal's `add_ticket` and from the email parser. The parser's and recurring tickets' created emails follow the contact's choice.
  - `agent/post/contact.php`: `portal_view_as` and `reset_contact_portal_mfa` (GET, with the CSRF token).
  - Admin: `admin/settings_client_portal.php` with `admin/post/settings_client_portal.php` (`edit_client_portal_settings`) and `admin/post/portal_announcement.php` (`add_portal_announcement`, `edit_portal_announcement`, `archive_`/`restore_`/`delete_portal_announcement`), and the windows in `admin/modals/portal_announcement/`.
  - CSS: `.itflow-portal-announcement` with `-icon`, `-title`, `-body` and `is-warning`/`is-danger`, `.itflow-portal-viewas` and `.itflow-announcement-dot`.
- Taxes (database 3.1.1):
  - `taxes.tax_percent` is `decimal(9,4)` (it was a float), with new `tax_description`, `tax_registration_number` and `tax_replaced_by` (0, the rate a billed rate was changed to). New table `tax_components` (`component_tax_id` keyed, `component_name`, `component_percent` `decimal(9,4)`, `component_compound`, `component_registration_number`, `component_order`) - a rate without rows is its own single part, and `tax_percent` is the whole rate its parts come to. New `clients.client_default_tax_id` (0, the default), `client_tax_exempt`, `client_tax_exempt_number` and `client_tax_exempt_expires`, and `settings.config_tax_default_id`, `config_tax_time`, `config_tax_trips`, `config_tax_expenses` (all 0) and `config_tax_discount_before` (1).
  - The new `functions/tax.php` (loaded after `billing_pay.php`): `taxSettings()`, `taxRow($tax_id)` (cached, with `components` - name, percent, compound, registration - and `grouped`) and `taxForget()`, `taxPercentText()`, `taxLabel()`, `taxEffectivePercent($components)`, `taxAmounts($base, $tax)` (`tax` and `parts` - label, name, percent, amount, registration, registration_for - each part to the cent), `taxCurrent($tax_id)` (follows `tax_replaced_by` to a current rate, else 0), `taxClient($client_id)`, `taxClientSetSql($in, $current)`, `taxExemptText($client, $with_expiry)`, `taxDocWarning($type, $doc_id, $client_id, $items, $open, $can_edit)`, `taxDefaultFor($client_id, $kind, $product_tax_id)` (`goods`, `time`, `trip`, `expense`, `product`), `TAX_USUAL` (-1) and `taxChoice($input, $client_id, $kind)`, `taxUsage()`, `taxUsageAll()`, `taxBilled($tax_id)` (on an invoice or quote past draft), `taxFromInput($in)`, `taxSameRate()`, `taxCreate()`, `taxUpdate($tax_id, $fields, $move_drafts)`, `taxChangeRate()`, `taxRepoint($from, $to, $move_drafts)`, `taxDocIds($type, $tax_id)`, `taxRecalcOpen()`, `taxArchive($tax_id, $move_to)`, `taxRestore()`, `taxUnused()`, `taxDelete()`, `taxSetDefault()`, `taxMovedText()`, `taxOptionsHtml($taxes, $selected, $usual_label, $usual_percent)` and `taxSelectOptions()`.
  - `functions/billing.php`: `billingLineTax($quantity, $price, $tax_id, $base)`, `billingLinesOpen($type, $status)`, `billingDiscountShares($items, $discount)` (by subtotal, to the cent, largest remainder), `billingRecalcTaxes($type, $doc_id, $discount)`, `billingTaxParts($items)`, `billingRemoveTax($type, $doc_id)` and `billingTaxRegistrationLines($totals)`. `billingRecalc()` works each line's tax out again while its lines can change - on a quote, the discount comes off the one-time group, else the first recurring one. `billingTotals()` `taxes` are by part, with `tax_registrations` added, and `quoteLineGroups()` the same per group. `billingBillTo()` adds `tax_exempt_text`. `recurringInvoiceRefresh()` updates quantities and subtotals and leaves the tax to `billingRecalc()`. `billingItemSave()` checks the rate exists. `billingTimeInvoice()`, `expenseBillInvoice()` and `tripBillInvoice()` take `TAX_USUAL`.
  - `getProductsForAutocomplete($mysqli, $client_id)` gives each product's `tax` and `tax_percent` for that client. The line windows, the add-a-line row, Unbilled Time and the ticket Invoice and Quote windows build their tax select with `taxOptionsHtml()`.
  - Admin: `admin/tax_rates.php` is rebuilt, with `admin/modals/tax/tax_form.php` shared by `tax_add.php` and `tax_edit.php`, the new `tax_archive.php`, and `js/tax_form.js`. `admin/post/tax.php`: `add_tax` (`name`, `description`, `registration`, `percent`, or `part_name[key]`, `part_percent[key]`, `part_compound[key]` and `part_registration[key]`; `make_default`), `edit_tax` (`tax_id`, `drafts_asked`, `move_drafts`), `archive_tax` (now POST, `tax_id`, `move_to`), GET `restore_tax`, `default_tax` and `delete_tax`, and `edit_tax_settings` (`default_tax_id`, `tax_time`, `tax_trips`, `tax_expenses`, `tax_discount_before`).
  - Clients: `agent/modals/client/client_tax_fields.php` on the Billing tab of `client_add.php` and `client_edit.php` posts `tax_fields`, `default_tax_id`, `tax_exempt`, `tax_exempt_number` and `tax_exempt_expires`, saved by `agent/post/client.php` through `taxClientSetSql()`. `api/v1/clients/client_model.php` does the same from the API fields. GET `billing_remove_tax` (the document's id, `type`) in `agent/post/billing.php`, at Sales 2.
  - `api/v1/invoice_items/create.php` takes `tax_id` `default` (a product's line by `taxDefaultFor()` with `product`) and works the invoice out again with `billingRecalc()`.
  - Seeds: `demoDataTaxes()` rows add a description, registration number and parts, demo clients get `client_default_tax_id`, and a profile's `tax_exempt` (number, expiry) makes it exempt. `demoDataRemove()` counts clients using a rate and removes its parts.
  - CSS: `.itflow-paper-tax-label`, so a line's rate wraps under its tax.
- Tax reporting and tax on expenses (database 3.1.2):
  - New `expenses.expense_tax_id` and `expense_tax_amount` (`decimal(15,2)`, part of `expense_amount`, negative on money back), and `recurring_expenses.recurring_expense_tax_id` and `recurring_expense_tax_amount`, copied to each expense by `recurringExpenseRecord()`.
  - `functions/tax.php`: `taxIncluded($gross, $tax)`, `taxSplit($amount, $row, $base, $no_rate_label)` (a tax amount shared over a rate's parts - `parts`, `registrations` and `numbers`, the registration number by part label), `TAX_REPORT_BASES`, `taxReportMonths()`, `taxReportInvoice($items, $discount, $exempt)`, `taxReportSales($from, $to, $basis)` (`invoiced` or `paid`; `parts`, `numbers`, `sales`, `taxable`, `nontaxable`, `exempt`, `exempt_clients`, `invoices`) and `taxReportPurchases($from, $to)` (`parts`, `purchases`, `expenses`). `billingTaxParts()` uses `taxSplit()`.
  - `functions/expenses.php`: `expenseTaxFromInput($in, $amount, $keep, &$error)` reads `tax_id` and `tax_amount` for `expenseSave()` and `recurringExpenseSave()`; a refund starts from the rate of what it refunds. `expenseRow()` and `recurringExpenseRow()` add `tax_name` and `tax_percent`, and `expenseVendorDefaults()` adds `tax`. The fields are `agent/modals/expense/expense_tax_fields.php`, on `expense_form.php` and `recurring_expense_form.php`.
  - `agent/reports/tax_summary.php` takes `basis` (`invoiced`, `paid`), `view` and `year`, and `export=csv` with `table` `tax` (Section, Figure, Registration, the periods and the year) or `exempt`.
  - Exports: `invoice_subtotal`, `invoice_discount_amount`, `invoice_tax` and `invoice_tax_parts`, `quote_tax` (up front, from `quoteLineGroups()`), `recurring_invoice_tax`, `expense_before_tax`, `expense_tax_amount` and `expense_tax_rate`, and `client_tax_rate`, `client_tax_exempt_display`, `client_tax_exempt_number` and `client_tax_exempt_expires`, all `default => false`. The quote and recurring invoice exports select their ids for it.
  - API: `api/v1/taxes/read.php` (`tax_id`, `archived`; each rate with `tax_components` and `tax_default`), mapped to `module_sales`. `billingApiTaxFields($type, $row)` adds `invoice_subtotal`, `invoice_tax`, `invoice_taxes` (`name`, `amount`) and `invoice_tax_registrations` to `api/v1/invoices/read.php`, and the `quote_` ones to `api/v1/quotes/read.php`. `api/v1/invoice_items/read.php` adds `tax_name` and `tax_percent`.
  - Seeds: `demoDataTaxes()` names Allegheny County's state part PA Sales Tax, and the company's own-use purchases (office supplies, tools, equipment, the van) carry PA Sales Tax.
- Tags (database 3.1.3):
  - New `tags.tag_description` (`varchar(255)`, NULL). The migration writes every `tag_color` as `#rrggbb` (short hex grows, an eight-digit one loses its alpha, Bootstrap's color words become their hex, anything else `#6c757d`) and every `tag_icon` as the bare Font Awesome name (`fas fa-` and anything but `a-z0-9-` taken off, NULL when empty).
  - `functions/tags.php`: `tagEntity()` adds `title`, `plural` and `icon`, and `tagsForRecords()` adds `tag_description` and `tag_archived_at`. New: `TAG_COLORS` and `TAG_ICONS` (what the tag window offers), `tagColor()` (`#rrggbb`, grey otherwise), `tagTextColor()` (`#212529` or `#fff` by luminance), `tagIcon()` (the bare name, `tag` when none), `tagPill($tag)`, `tagKinds()` (type => entity), `tagKindPhrase($type)`, `tagEntityByType($type)` (adds `entity`), `tagRow()`, `tagUsageCounts()`, `tagUsageBreakdown()` (`all`, `archived`), `tagUsage()`, `tagSeatLineItems($tag_ids)`, `tagSeatLineCounts()`, `tagSeatLines()`, `tagFromInput($in, $existing)` (returns `[$fields, $error]`: `name`, `description`, `type`, `color`, `icon`), `tagCreate()`, `tagUpdate()`, `tagArchive()`, `tagRestore()`, `tagDelete($tag_id, &$error)` (refused while a seat line counts by it), `tagMerge($from_ids, $into_id)` (same kind only; records moved, seat filters repointed, returns how many records), `tagOptionsFor($entity, $keep_ids)`, `tagOptionsHtml($entity, $selected)` and `tagSetForRecord($entity, $record_id, $values)`.
  - `tagSetForRecord()` is the one save for a record form's `tags[]`: numeric values are tag ids, anything else (from an admin) a typed name, matched case-insensitively or made with `tagNextColor()`. Only that kind's tags are kept, each once, and an archived tag only on a record that already had it. `agent/post/client.php`, `contact.php` and `asset.php` call it, and `locationSetTags()`, `credentialSetTags()`, `vendorSetTags()` and `domainSetTags()` now wrap it.
  - The record forms' Tags fields are `<?= tagOptionsHtml($entity, $selected) ?>` with `data-tags="true"` for admins, and their links to `admin/modals/tag/tag_add.php` are gone. The list pages' tag filters and the seven export windows leave out archived tags that aren't chosen.
  - `tagPills()` and `tagPill()` set `--itflow-tag-text` beside `--itflow-tag-color`, and a pill's title leads with the description. `agent/ajax.php`: `tag_picker` returns each tag's `description` (with "Archived"), `tag_picker_set` refuses putting an archived tag on (409), and `tag_picker_create` matches case-insensitively and restores an archived match. `js/tag_picker.js` shows and searches the description.
  - Admin: `admin/tags.php` is rebuilt (`type`, `q`, `sort=use`, `archived`), with `admin/modals/tag/tag_form.php` shared by `tag_add.php` (`type`) and `tag_edit.php` (`id`), the new `tag_merge.php` (`tag_ids[]`), and `js/tag_form.js`. `admin/post/tag.php`: `add_tag` and `edit_tag` (`tag_id`, `name`, `type`, `description`, `color`, `icon`), GET `archive_tag`, `restore_tag` and `delete_tag`, POST `merge_tags` (`tag_ids[]`, `into_tag_id`), and `bulk_archive_tags`, `bulk_restore_tags` and `bulk_delete_tags` (`tag_ids[]`). Audit entries are Tag Create, Edit, Archive, Restore, Delete and Merge, naming the kind.
  - Removed: `agent/post/tag.php` and `agent/post/tag_model.php` (an older copy of `add_tag` for the window opened from agent pages), and `admin/post/tag_model.php`.
  - `agent/modals/billing/billing_item.php` adds a seat line's own tag to its choices when `recurringSeatOptions()` leaves it out.
  - CSS: `.itflow-tag` takes its text color from `--itflow-tag-text`; new `.itflow-tag-swatch`, `.itflow-tag-icons`, `.itflow-tag-icon` and `.itflow-tag-icon-custom`.
- Tags on more records (database 3.1.4):
  - New link tables `software_tags`, `service_tags`, `network_tags`, `document_tags` and `ticket_tags` - the record's id and `tag_id`, keyed together and on `tag_id`, gone with the record or the tag. Tag types 8 (license - the `software` table), 9 (service), 10 (network), 11 (document) and 12 (ticket) in `tagKinds()` and `tagEntity()`; a document's `client_list` says its list (`files.php`) is one client's.
  - `functions/tags.php`: `tagListUrl($entity, $tag)` (the list filtered by it, or `global_search.php?query=` for documents), `tagFilterIds()` (`?tags[]=`), `tagFilterSql($entity, $ids, $column)`, `tagSearchSql($entity, $like, $column)`, `tagFilterOptionsHtml($entity, $selected, $records_where)` (the tags on records the list can show, its client joined, plus those chosen), `tagOptionHtml($row, $selected)`, `tagFormFieldHtml($entity, $selected, $placeholder)` (with the `tags_form` marker), `tagIdsForRecord()`, `tagChangeText($before, $after)`, `tagExportFieldHtml()`, `tagNamesSql()` and `tagExportFilterSql($entity, $values, &$summary, $column)`, `tagMatchSql()` and `tagSearchSpec()` for search, and `tagApiRow()` and `tagApiAttach($rows, $entity)` for the API. `tagsForRecords()` adds `tag_type`.
  - Licenses, services, networks, documents and tickets: the lists read `tags[]`; the forms post `tags[]` and `tags_form`; `add_software`, `add_service`, `add_network`, `add_document` and `add_ticket` save them with `tagSetForRecord()`, and the edits do when `tags_form` is posted. `edit_ticket` writes the change to the ticket's history, as does the picker (`agent/ajax.php` `tag_picker_set`) for a ticket. `ticketMergeInto()` copies the merged ticket's tags that aren't archived.
  - Exports: `tags[]` filters, and `software_tags`, `service_tags`, `network_tags` and `ticket_tags` columns, off until ticked.
  - `js/tag_picker.js`: `data-tag-bulk="document_ids[]"` takes only the ticked boxes of that name.
  - Global search: a group's `tags` (`[entity, id column]`) also matches its records' tag names and hands back each item's matching `tags` (`name`, `color`, `text`, `icon`); the new `tags` group (`module` null - it checks each kind's module in its own query) gives each item its `tag`. CSS: `.itflow-search-tag-icon`, `.itflow-search-item-text` and `.itflow-search-item-tags`.
  - API: `api/v1/tags/read.php` (`kind` - a kind's name, `software` or its type - `record_id`, `tag_id` with `tag_record_ids`, `tag_name`, `archived`, `limit`, `offset`), `update.php` (`kind`, `record_id`, `tags` as an array or comma-separated ids or names, `mode` `set`, `add` or `remove`, and `client_id` for a key limited to certain clients) and `create.php` (admin keys; `kind`, `name`, `description`, `color`, `icon`). `enforce_api_rbac.php` maps `tags` to `''`, so `tagApiKind()` checks the module of the kind asked for. `read_output.php` adds `tags` to each row when the endpoint sets `$api_tag_entity`, as the twelve kinds' reads now do; `credentials/read.php` adds them itself.
  - Seeds: `starterContentTags()` has types 8 to 12, and demo data tags its documents, services, licenses and tickets (`demoTicketTags()`).
- Categories (database 3.1.5):
  - The migration writes every `category_color` as `#rrggbb` - short hex grows, an eight-digit one loses its alpha, the 148 CSS color words become their own hex, anything else NULL - and every `category_icon` as `fa-` and the name (`fas fa-`, markup and anything but `a-z0-9-` taken off, NULL when empty), leaving `category_updated_at` alone. No column changes; `setup/seed_data.php` writes hex colors.
  - New `functions/categories.php`, loaded by `functions.php`: `categoryKinds()` - each `category_type` with `label`, `one`, `many`, `icon`, `about`, `by` (`id` or `name`), `ordered`, `max` (name length), and `uses` (`table`, `column`, `one`, `many`, `url` with `{id}` or `{name}`, `archived` and `updated` columns, and `text` for an id kept as text, `tickets.ticket_category` and `recurring_tickets.recurring_ticket_category`). Also `categoryKind()` (adds `kind`), `categoryKindPhrase()`, `categoryUseText()`, `CATEGORY_COLORS`, `CATEGORY_ICONS`, `categoryColor()` (`#rrggbb` or `''`), `categoryIcon()` (the bare name or `''`), `categoryNextColor()`, `categoryChip($row)`, `categoryRow()`, `categoryRows($kind)` (in picker order), `categoryNameKey()`, `categoryUsage($kind)` (id => `all`, `archived`, `uses`), `categoryLooseNames($kind)`, `categoryUsageFor()`, `categoryUsageHtml()`, `categoryUsageText()`, `categoryFromInput($in, $existing)` (`[$fields, $error]`: `type`, `name`, `description`, `color`, `icon`), `categorySetSql()`, `categoryCreate()` (last in the order), `categoryUpdate($row, $fields)` (renames a by-name kind on its records; returns how many), `categoryMoveRecords($config, $where_sql, $to_sql)` (keeps each record's `*_updated_at`), `categoryArchive()`, `categoryRestore()`, `categoryDeleteBlocker($row, $used)`, `categoryDelete($id, &$error)`, `categoryMerge($from_ids, $into_id)` (same kind; returns records moved), `categoryFoldBudget()` and `categoryReorder($kind, $ids)`.
  - Admin: `admin/categories.php` is rebuilt (`category`, `q`, `sort` - `order`, `name` or `use` - and `archived`; no paging), with `admin/modals/category/category_form.php` shared by `category_add.php` (`category`, or none to pick the kind) and `category_edit.php` (`id`), the new `category_merge.php` (`category_ids[]`), and `js/category_form.js`. `admin/post/category.php`: `add_category` and `edit_category` (`category_id`, `type`, `name`, `description`, `color`, `icon`), GET `archive_category`, `restore_category`, `delete_category` and `move_category` (`direction` `up` or `down`), POST `merge_categories` (`category_ids[]`, `into_category_id`), `reorder_categories` (`category`, `category_ids[]`; answers JSON), and `bulk_archive_categories`, `bulk_restore_categories` and `bulk_delete_categories` (`category_ids[]`). Audit entries are Category Create, Edit, Archive, Restore, Delete and Merge, naming the kind.
  - Removed: `agent/post/category.php` (the + window posts to `admin/post.php` from any page), and `admin/post/category_model.php` and `agent/post/category_model.php`.
  - Pickers: `categoryPicker($kind, $keep, $opts)` - the current ones in the kind's order plus `$keep` (ids, or names for a kind held by name) even when archived, a kept name that is no category as a row with `loose` and no id, keyed by the value posted; `$opts` `since` (also those archived after then) and `exclude` (names). `categoryOptionHtml($row, $selected)` (`data-color` - an asset status's badge color, `data-icon` - a software type's icon from its name when it has none, `data-description`, and "(archived)" or "(not set up)"), `categoryOptionsHtml($kind, $selected, $opts)`. `expenseCategories()`, `incomeCategories()` and `billingCategories($since, $keep_id)` return `categoryPicker()`. Every record form and Bulk Action window that picks a category writes its options with `categoryOptionsHtml()`.
  - The +: `categoryAddButtonHtml($kind, $class)` - admins only, opening `admin/modals/category/category_add.php?category=&inline=1`, marked `data-category-add`. `js/app.js` notes the picker beside it in `window.itflowCategoryTarget`; `js/category_form.js` posts the inline window with fetch, and `add_category` with `inline` answers JSON - `ok`, `value`, `option` (the picker's `<option>`, selected) and `message`, or `ok` false and `error`. Without `inline` it goes back to the page it came from, or to the kind in Admin > Categories. The category windows post to `/admin/post.php`.
  - List filters: `categoryFilterOptionsHtml($kind, $selected, $used_sql, $opts)` - the categories the list's records have (`$used_sql` selects their ids, or names for a kind held by name), archived ones and loose names included, plus `$selected`; without `$used_sql`, the current ones. The list writes its own "any" option. The category filters of expenses, recurring expenses, income (other income's categories and those of the invoices paid), invoices, products, tickets, recurring tickets and clients' referrals, and the expense, income, invoice, product and ticket export windows, use it.
  - New list parameters: `agent/quotes.php?category=`, `agent/recurring_invoices.php?category=`, `agent/assets.php?status=` (a name; the `type` tabs and `$tab_counts` take it in), `agent/software.php?software_type=` (`$tab_counts` take it and `license_type` in) and `agent/racks.php?type=` (a name). `buildExportModalUrl()` hands each to its export window, and `export_quotes` and `export_recurring_invoices` take `category`, `export_assets` `status` and `export_software` `software_type`, each in the filter summary. Export columns: `category_name` for `invoices`, `quotes` and `recurring_invoices`, and `asset_status` for `assets`, off until ticked; the quote and recurring invoice handlers select `category_name`.
  - `categoryKinds()`: `modules` - the modules whose records hold a kind, any one of which reads it (`Income` is `module_sales` or `module_financial`) - and `uses` URLs for quotes, recurring invoices, assets, licenses and racks.
  - `categoryMarkHtml($color, $icon, $dot)` - the icon in the color (`.itflow-category-icon`), else `$dot` (`sprintf` with a style attribute or `''`) or `.itflow-category-dot`. `expenseCategoryDot($color, $icon)` returns it, and the ticket lists, a ticket and its quick look pass their `.itflow-ticket-dot`. `category_icon` is now selected by `incomeUnionSql()` (both halves), `incomeRevenueRow()`, `incomePaymentRow()`, the account ledger in `functions/accounts.php`, and the expense, recurring expense, ticket, recurring ticket and ticket quick look queries.
  - API: `api/v1/categories/read.php` (GET; `kind` - a `category_type` or its label, any case - `category_id` (archived or not), `category_name` (any case), `archived`, `limit`, `offset`) answers `category_id`, `category_name`, `category_description`, `category_type`, `category_color` and `category_icon` (or null), `category_order`, `category_created_at`, `category_updated_at`, `category_archived_at`, `category_used` and `category_used_by` (by table; both null for a user limited to certain clients, as they count every client's records). `api/v1/categories/categories_model.php`: `categoryApiCanRead()`, `categoryApiReadableKinds()`, `categoryApiKind()` (refuses an unknown kind or one the user can't read) and `categoryApiRow()`. `enforce_api_rbac.php` maps `categories` to `''`.
  - `categoryEnsure($kind, $name)` - a name typed for a kind held by name as its category's own spelling, matched in any case, archived or not, or made (with the next color, logged); `''` for none. `add_client`, `edit_client` and the client import use it for the referral, in place of their own insert.
  - `js/app.js`: a modal shown over another (`show.bs.modal`) takes a z-index 20 above it, its backdrop just under it, and `modal-open` goes back on the body when the top one closes.
  - `selectOptionColor()` turns an icon kept as `fa-name` into `fas fa-name`.
  - `admin/settings_default.php` reads `paymentMethods()`, keeping a saved value that is no longer one.
  - CSS: `.itflow-category`, `-mark`, `-name` and `-badge` (`categoryChip()`), `.itflow-category-swatch-none`, `.itflow-category-kinds` and `.itflow-category-grip`.
- Roles (database 3.1.6):
  - The migration deletes `user_role_permissions` rows of a role or module that is gone or of level 0 or less, keeps the highest level (held to 3) where a role has a module twice, and adds `PRIMARY KEY (user_role_id, module_id)`.
  - `lookupUserPermission()` reads the session role's levels in one query and keeps them for the request (a `static`, keyed by role). It still returns `3` for an admin and `false` for none.
  - New `functions/roles.php`, loaded by `functions.php`: `ROLE_LEVELS`, `ROLE_LEVEL_TONES`, `roleModuleInfo()` (by `module_name`: `label`, `icon`, `about`, `levels` - what each adds - and `max`), `roleModules()` (`module_id` => that, from the `modules` table, a module it doesn't know named from its row), `roleLevels($role_ids)`, `roleRows($archived)` (with `levels`, `agents` and `agents_archived`), `roleRow()`, `roleAgents($role_ids, $limit)`, `roleAccessHtml($row)`, `roleFromInput($in, $role_id)` (`[$fields, $error]`: `name`, `description`, `admin`, `levels`), `roleAdminBlocker()`, `roleArchiveBlocker()`, `roleDeleteBlocker()`, `roleChangeText($before, $fields)`, `roleSave($fields, $role_id)` (an admin role keeps its levels), `roleSetArchived()` and `roleDelete()`.
  - Admin: `admin/roles.php` is rebuilt (`q`, `archived`; no paging or sorting). `admin/modals/role/role_form.php` is shared by `role_add.php` (`copy` - a role to start from) and `role_edit.php` (`id`), with `js/role_form.js`. `admin/post/roles.php`: `add_role` and `edit_role` (`role_id`, `name`, `description`, `admin`, `levels[module_id]` 0 to 3 - the old `role_name`, `role_description`, `role_is_admin` and `module_id##module_name` fields are gone), and GET `archive_role`, `restore_role` and `delete_role`. Audit entries are User Role Create, Edit (with what changed), Archive, Restore and Delete.
  - The hard-coded `role_id !== 3` checks on the roles page are gone; the guards are `roleAdminBlocker()` and the archive and delete blockers.
  - CSS: `.itflow-role-agents` and `.itflow-role-level-list`.
  - Refusals: `enforceUserPermission()` and `enforceAdminPermission()` go through `permissionRefuse($message)` in `functions/auth.php`. It answers a window (`ITFLOW_MODAL`, now defined by `includes/modal_header.php`) with its own JSON `content`, `ajax.php` with a 403 and `{ok: false, error}`, a handler or a page that hasn't drawn anything yet with `flashAlert()` and `redirect(permissionBackUrl())`, and a page already drawing with a `.itflow-refused` card and the footer. Also `permissionRefusedText($module, $level, $has)`, `permissionRoleName()` and `permissionBackUrl()` (the referrer on this host unless it is this page, else the dashboard). The session alert is no longer set before the old `exit`. `WORDING_ROLECHECK_FAILED` is kept but no longer used.
  - `reportCanOpen($key)` in `functions/reports.php`, for links into a report. The credentials, unbilled time, expenses, recurring expenses, income and trips pages use it, and the dashboard's Profit card opens Transactions for the period without it.
  - `LINK_ENTITIES` has a `view` module for asset, software, service, domain and certificate (Support) and contact and vendor (Clients), so `linkTypesFor()` leaves them out for an agent without it. `notificationTypeModules()` files `Shared link opened`, `Shared link locked`, `Login sent back` and `Share Viewed` under Support.
  - `agent/includes/inc_client_top_head.php` includes `modals/client/client_delete.php` only where Delete Client is in the menu. `agent/includes/client_overview_side_nav.php` checks Credentials.
  - The role window marks each module `data-role-module="module_name"` and has `data-role-needs-clients`; the user form has a `data-user-role-access` block per role.
- API Keys (database 3.1.7):
  - The migration adds `api_keys.api_key_last_used_at` (DATETIME, null). `api/v1/validate_api_key.php` sets it on a call that gets past the key check, at most once a minute, in `config_timezone`, as the API doesn't set PHP's or MySQL's clock to it.
  - New `functions/api_keys.php`, loaded by `functions.php`: `apiKeyRows($search)` and `apiKeyRow($id)` (the key with its agent's `user_name`, `user_avatar`, `user_type`, `user_status`, `user_archived_at` and `user_role_id`), `apiKeyExpired($row)`, `apiKeyAgentProblem($row, $role)`, `apiKeyStatePill($row, $role)`, `apiKeyAgents()` (active agents), `apiKeyFromInput($in, $current_expire)` (`[$fields, $error]`: `name`, `expire`, `user_id`) and `apiKeyAgentFieldHtml($selected)` (the `run_as_user` picker, with a `data-api-key-access` block per agent).
  - Admin: `admin/api_keys.php` is rebuilt (`q`; no paging or sorting). `admin/post/api_keys.php`: `add_api_key` (`name`, `expire`, `run_as_user`, `key` - 32 of `A-Za-z0-9_-` - and `password`), `edit_api_key` (`api_key_id`, `name`, `expire`, `run_as_user`), GET `revoke_api_key` (`api_key_expire` to today) and `delete_api_key`, and `bulk_delete_api_keys` (`api_key_ids[]`). Each checks the key exists, and add and edit take an active agent only. Audit entries are API Key Create, Edit (with what changed, and only when something did), Revoke and Delete, each linked to its key. The Bulk Delete summary entry is gone, as each key logs its own Delete.
  - API: `api/v1/roles/read.php` (GET; `role_id`, `archived`, `limit`, `offset`) answers `role_id`, `role_name`, `role_description`, `role_is_admin`, `role_levels` (`module_name` => 0 to 3), `role_agents`, `role_created_at`, `role_updated_at` and `role_archived_at`. A key whose user isn't an admin gets only `$session_user_role`. `enforce_api_rbac.php` maps `roles` to `''`. `api/v1/technicians/read.php` (GET, `module_support`; `technician_id`, `archived`, `limit`, `offset`) answers `technician_id`, `technician_name`, `technician_email`, `technician_status`, `role_id`, `role_name`, `role_is_admin`, `technician_created_at` and `technician_archived_at`.
  - `enforce_api_rbac.php`: `apiPermissionText($module, $level)` words the module refusal, and `$operation_level` maps `time.php` to 1.
- Payments, calendar events and the API's clock (database 3.1.8):
  - The migration gives the built-in Accountant (role 1) level 3 on `module_financial` when it still has its shipped levels (name, not admin, Clients, Support, Sales and Reports 1, Financial 2, no Credentials). `setup/seed_data.php` seeds it at 3.
  - `functions/billing.php`: `billingMoneyRules()` (`take`, `edit`, `delete`, `receipt`, `remind`, `statement` - a Financial level with Sales at 1, or a Sales and Financial pair as before), `billingMoneyCan($action)` and `enforceBillingMoney($action)`. Used by `add_payment`, `add_payment_stripe`, `add_bulk_payment`, `edit_payment`, `delete_payment`, `refund_payment_stripe` and `payment_send_receipt` in `agent/post/payment.php`, `email_invoice` with `kind=reminder` (Sales 2 for anything else, a draft, or when nothing is owed) and `send_statement` in `agent/post/invoice.php`, and the payment, batch payment, statement and invoice email windows. `invoice_email.php` posts a hidden `kind=reminder` for a role without Sales 2. `apply_credit` and `add_credit` are unchanged, as credits aren't offered yet.
  - `incomeCanEditPayments()` is `billingMoneyCan('edit')`, and new `incomeCanDeletePayments()` is `billingMoneyCan('delete')`. `bulk_edit_income` Set Category on payments also needs Sales 2, as it sets their invoices'.
  - Calendar: `calendarEventCan($event, $action)` (`view`, `edit`, `delete`), `enforceCalendarEvent()`, `calendarIsOn($event_id)` and `calendarEventScopeSql()`, which `calendarEventItems()`, `calendarUserFeedItems()` and the dashboard's events add. `calendarEventForRequest()` and `calendarEventForModal()` take the action. `calendar_move` in `ajax.php` checks `edit`, and a calendar item's `editable` follows it. `agent/calendar.php` sets `data-can-add`, which `agent/js/calendar.js` reads to turn off picking dates.
  - `includes/inc_set_timezone.php` sets `$session_timezone` and `$session_utc_offset`, puts them in `$_SESSION` only when a session is open, and no longer leaves `$result` and `$row` behind. `api/v1/validate_api_key.php` requires it, and sets `api_key_last_used_at` with `NOW()`.
- Emails:
  - New `functions/email.php`, loaded by `functions.php`: `emailHtml($email)` draws every email - `billingEmailHtml()` is now a wrapper for it that greets nobody without a name - with new fields `marker` (the reply line, first), `preview` (inbox preview text), `message` (`html`, `by`, `when`, `label` - HTML someone wrote, through `emailMessageHtml()`), `callout`, `contact` (the foot's contact line) and the `secondary` tone, and `name` '' for "Hello,". `EMAIL_TONES` and `EMAIL_REPLY_MARKER`. `emailMessageHtml($html)` purifies it (`documentHtmlPurify()`), drops the reply line, makes `/` links and images absolute, and caps image widths. `emailFrom($which)` (`system` or `ticket`, read from the settings as saved). `emailQueue($recipients, $subject, $email, $opts)` - recipients with `email`, `name`, `greet` and `footnote`, raw values, escaped for `addToMailQueue()`, each address once; `from` and `attachments` in `$opts`.
  - `functions/ticket.php`: `ticketEmailContent($row, $kind, $opts)` (`created` with `how` agent, email or scheduled, `reply`, `resolved`, `closed`, `watcher`) and `ticketMailSend($ticket_id, $subject, $email, $opts)` (`attachments`, `to_contact`, `to_watchers`, `to`, `kind`) replace `ticketMailContactAndWatchers()` and `ticketMailCompany()`. Also `ticketEmailFrom()`, `ticketEmailUrl()`, `ticketEmailContactLine()`, `ticketEmailSla()` (in place of `getTicketSlaEmailNotice()`, removed) and `ticketMailClosed()`. `ticketMailResolved($ticket_id, $note)` takes the note as raw HTML. The reply handlers read the reply back from `ticket_replies` for the email, so it carries the signature as saved.
  - `recurringTicketRaise()` declares `$config_ticket_client_general_notifications` as a global, and tells the new-ticket address through `ticketMailNewTicketAddress()`. `cron/nightly_tasks.php` calls `ticketMailClosed()` for each ticket it closes. `cron/ticket_email_parser.php`'s new-ticket reply and closed-ticket bounce, `api/v1/ticket_replies/create.php`, `add_ticket_watcher`, `bulk_ticket_reply`, `bulk_resolve_tickets` and `add_ticket_task_approver` go through them too.
  - `functions/email.php`: `emailSettings()` reads the mail settings from the database once per request, and `emailReady()` says whether outgoing mail is set up - so cron scripts, which keep escaped copies of the settings or none, can use them. `emailFrom()` reads through it. `emailQueue()` takes `cal_str` in `$opts`.
  - `functions/ticket.php`, emails to agents: `ticketAgentEmailContent($row, $kind, $opts)` (`new` with `how` portal, email or scheduled; `replied` with `how`, `message` and `by`; `assigned` with `by`; `scheduled` with `when`, `where` and `cancelled`; `sla` with `track`, `breached` and `due`), `ticketAgentUrl($row)`, `ticketMailAgent($user_id)` (an active agent's email and name, or null), `ticketMailAgents($recipients, $subject, $email)` (from the ticket mailbox), `ticketMailNewTicketAddress($ticket_id, $how)`, `ticketMailAssigneeReplied($ticket_id, $how, $reply_html, $by)`, `ticketMailAssigned($user_id, $ticket_ids, $by)` and `recurringTicketMailAssigned($user_id, $subjects, $by)` - not when an agent assigns themselves. `ticketEmailContent()` has a `scheduled` kind, and `ticketMailSend()` passes `cal_str`. `ticketMailSchedule()` sends the agent's email through `ticketAgentEmailContent()` and the client's through `ticketMailSend()`, both with the calendar file.
  - Callers: `ticketChangeAssignee()`, `recurringTicketChangeAssignee()`, `bulk_assign_ticket`, `add_ticket` and `edit_ticket` (`agent/post/ticket.php`), `recurringTicketNotifyAgent()` (`agent/post/recurring_ticket.php`), the portal's `add_ticket`, `add_ticket_comment` and `stripe_save_card` (`client/post.php`, the last through `billingEmailQueue()`), the parser's `addTicket()` and `addReply()`, `login.php`'s sign in alerts, `add_user`, `shareNotifyCreator()` and `test_email_smtp`.
  - `cron/ticket_sla.php`: `sendSlaAlert($ticket, $track, $breached, $due)` - `$track` is `response` or `resolution` - in place of `sendSlaAlert($ticket, $subject_line, $body_line)`. `$from_email` and `$from_name` are gone.
  - `functions/user.php`: `userSignInUrl()` (with the login key when one is required), `userMailAppName()`, `userMailNewAccount($name, $email, $password, $force_mfa)` (true when queued) and `userMailSignInAlert($name, $email, $kind, $ip, $user_agent)` (`new` or `mfa`). `userNotifyChange()` sends through `emailQueue()`. `userQueueMail()` is removed.
  - Every email now goes through `emailQueue()` - `addToMailQueue()` is called only from there and from a preview's Send me a copy. `emailHtml()` takes `content` (HTML that is the email itself, made safe by `emailMessageHtml()` and shown as it is) and `button.show_url` (the address printed under the button), and leaves out the small heading when `kind` is empty. `emailQueue()` takes `queued_at` and `always` (queue without mail set up - `billingEmailQueue()` passes it, as billing's Send windows always queued). New `emailMailboxes()` (the From addresses as `system`, `ticket`, `invoice` and `quote`), `emailTestSend($to, $mailbox)`, `emailBulkSend($recipients, $mailbox, $from_name, $subject, $body, $greet, $queued_at)` and `emailBulkPost($recipients, $picked)`, which the contacts and clients handlers call. Their windows share `agent/includes/bulk_email_fields.php` and post `mailbox` and `greet` in place of `mail_from`.
  - Capturing: between `emailCaptureStart()` and `emailCaptureStop()`, `emailQueue()` keeps each email (`to`, `from`, `subject`, `html`, `attachments`, `cal_str`) instead of queuing it, `emailReady()` is true, and `triggerCustomAction()` does nothing (`emailCapturing()`).
  - New `functions/email_preview.php`: `emailPreviewCatalog()` (each email's group, label, who gets it, what sends it, and the call that sends it), `emailPreviewSample()` (the sample records), `emailPreview($key)` (in a transaction that is rolled back, logging an app error if anything ended it early) and `emailPreviewSendCopy($key, $to, $name)`. `admin/mail_preview.php` returns one as JSON for the Previews tab, and `send_email_preview` in `admin/post/settings_mail.php` sends a copy.
  - Pulled out of the handlers so the previews can call them: `ticketMailCreated()` (from `agent/post/ticket.php`, now with `$how` and `$to`, which the email parser uses), `ticketMailReply($ticket_id, $reply_html, $by, $resolved, $attachments)` (the reply handler, bulk reply and the API), `ticketMailWatchersAdded()`, `ticketMailTaskApproval($approval_id, $by)`, `ticketMailClosedReply($ticket_id, $from_email)` and `ticketSlaAlert($ticket_id, $track, $breached, $due, $address)` in `functions/ticket.php` - `sendSlaAlert()` in `cron/ticket_sla.php` is gone - and `billingMailCardSaved()` in `functions/billing_email.php`.
  - `functions/portal.php`: `portalMailReady()`, `portalMailInvite($user_id, $by)` (used by `add_contact`, `edit_contact` - which take `send_email` - and the portal's `portalContactInvite()`), `portalMailPasswordReset($user_id)` and `portalMailAccountChange($email, $name, $change)` (`password`, `password_link`, `mfa_on`, `mfa_off`, `mfa_off_by_us`). `portalAccountEmail()` sends through `emailQueue()`.
  - `functions/share.php`: `shareMailHtml()` and `shareQueueMail()` are removed. `shareEmailLink()` returns a bool, and new `shareEmailCode($row, $code)` and `shareMailFrom()`. `shareMailReady()` goes by `emailReady()`. `calendarSendInvites()` sends through `emailQueue()`, and `calendarCanEmail()` is `emailReady()`.
- Ticket templates:
  - Database 3.1.9: `ticket_templates` gains `ticket_template_category_id`, `ticket_template_priority` (NULL leaves it), `ticket_template_billable` (NULL leaves it), `ticket_template_assigned_to` and `ticket_template_due_hours`; new `ticket_template_tags` (ticket tags, cascading like `ticket_tags`); `tickets.ticket_template_id` with a key.
  - New `functions/ticket_template.php`: `ticketTemplateRow()`, `ticketTemplateTasks()`, `ticketTemplateTagIds()`, `ticketTemplateFields($post, &$error)`, `ticketTemplateSave($id, $fields)`, `ticketTemplateSetTags()`, `ticketTemplateCopy()`, `ticketTemplateUsage($ids)`, `ticketTemplateDefaultsHtml()`, `ticketTemplatePickerData()`, `ticketTemplateDueText()`, `ticketTemplateDueAt()`, `ticketTemplateMinutesText()` and `ticketTemplatePlaceholdersIn()`; placeholders in `TICKET_PLACEHOLDERS`, `ticketPlaceholderValues($ticket_id)`, `ticketPlaceholderFill($text, $values, $html)` and `ticketPlaceholdersFill($ticket_id)`; and `ticketTemplateFinish($ticket_id, $template_id, $defaults, $tasks)`, which every path that raises from a template calls - `add_ticket`, `bulk_add_asset_ticket`, `bulk_add_client_ticket` (rewritten, with `use_primary_contact` now a switch in its window) and `projectApplyTemplate()`, which now takes each ticket template's settings. `recurringTicketRaise()` records the recurring ticket's template and calls `ticketPlaceholdersFill()`. `projectTemplateSubject()` is gone.
  - `agent/includes/inc_ticket_template_select.php` writes each template's settings as `data-template-*` on its option and takes `$ticket_template_field`, `$ticket_template_apply_on_load` and `$ticket_template_hint`; `agent/js/ticket_tasks_modal.js` writes them into the form's fields marked `data-template-field` (`subject`, `details`, `priority`, `category`, `assigned`, `due`, `tags`, `billable`) and the task rows. `ticket_add.php` takes `ticket_template_id`, and `agent/tickets.php?new_from_template=` opens it.
  - Admin: `admin/modals/ticket_template/ticket_template_form.php` is shared by the add and edit windows, which are ajax windows now; `copy_ticket_template`, `archive_ticket_template` and `restore_ticket_template` are new. `admin/post/ticket_template.php` no longer requires `agent/post/ticket.php` and `agent/post/task.php`, and `edit_ticket_template_task` moved there from `agent/post/task.php`.
  - `functions/tags.php`: `tagIdsFromValues($entity, $values)` and `tagKeepIds($entity, $ids, $had)` are split out of `tagSetForRecord()`, and `tagFormFieldHtml()` takes the select's extra attributes.
  - Database 3.2.0: fill-ins. `ticket_template_fill_ins` (`fill_in_name`, `fill_in_type` - text, date, list or yesno - `fill_in_options` one per line, `fill_in_required`, `fill_in_help`, `fill_in_order`; unique per template and name, cascading with the template) holds only the ones set up or dragged into order - the rest of a template's bracketed words are asked as optional text, so templates made elsewhere (starter content, demo data) need nothing. `ticket_fill_ins` and `recurring_ticket_fill_ins` keep the answers with their question as it was (name, type, options, required, value, order), cascading with the ticket or recurring ticket. `task_templates` gains `task_template_when_fill_in` and `task_template_when_value` (NULL: always).
  - `functions/ticket_template.php`: `TICKET_PLACEHOLDER_NAME` (what may go in brackets), `TICKET_FILL_IN_TYPES`, `ticketFillInName()`, `ticketFillInOptions()`, `ticketTemplateFillInRowsFor($ids)`, `ticketTemplateFillIns($template, $tasks, $rows)` (the rows, then the text's other bracketed words, each with `in_text`) and `ticketTemplateFillInsFor($id)`, `ticketFillInAnswers($fill_ins, $posted, &$error, $required)` (checks `fill_ins[Name]`), `ticketFillInText()`, `ticketFillInsStored($kind, $id)` / `ticketFillInsStore($kind, $id, $answers)` for `ticket` and `recurring_ticket`, `ticketFillInFieldHtml()`, `ticketTemplateTasksFor($ids)` (tasks carry `when`), `ticketTemplateTaskApplies()`, `ticketTemplateAddTasks($ticket_id, $template_id, $answers)` (replaces `addTasksFromTicketTemplate()`, removed from `functions/app.php`), `ticketTemplateWhenChoices()`, `ticketTemplateWhenFromPost()`, `ticketTemplateTaskWhenOffered()`, `ticketTemplateWhenSelectHtml()`, `ticketTemplateTaskWhenText()` and `ticketTemplateRenameFillIn()`. `ticketPlaceholderFill()` fills any names it is given, matching the editor's `&nbsp;` between words; `ticketPlaceholdersFill()` adds the ticket's answers; `ticketTemplatePlaceholdersIn()` reads HTML as text. `ticketTemplateFinish()` takes `$answers` (null: kept unanswered) and `ticketTemplatePickerData()` the fill-ins.
  - Handlers: `add_ticket`, `bulk_add_asset_ticket`, `bulk_add_client_ticket`, `add_recurring_ticket` and `edit_recurring_ticket` check `fill_ins[]` before saving anything; `recurringTicketRaise()` copies a recurring ticket's answers onto each ticket. New `edit_ticket_fill_ins` and `agent/modals/ticket/ticket_fill_ins.php`. Admin: `save_ticket_template_fill_in`, `delete_ticket_template_fill_in`, `reorder_ticket_template_fill_ins` (JSON) and `admin/modals/ticket_template/ticket_template_fill_in.php`; `add_ticket_template_task` and `edit_ticket_template_task` take `when` (`Name|Choice`).
  - Export and import: new `functions/ticket_template_transfer.php` - `ticketTemplatesExport($ids, $archived)` (the file's contents: `format` itflow-ticket-templates, `version` 1, `templates`), `ticketTemplatesExportFileName()`, `ticketTemplatesImportRead($contents, &$error)` (checks and tidies a file - details through `documentHtmlPurify()` - and takes the starter pack's array the same way), `ticketTemplatesImport($templates, $existing, $only_existing)` (`skip`, `copy` or `replace`; categories through `categoryFromInput()`/`categoryCreate()`, tags through `tagCreate()`) and `ticketTemplatesImportSummary()`. Handlers `export_ticket_templates` (POST, `ticket_template_ids[]` and `archived`), `export_ticket_template` (GET, one) and `import_ticket_templates`, with `ticket_template_export.php` and `ticket_template_import.php` windows.
  - Starter Content: `starterContentTicketTemplates()` is written in the file format and `starterLoadTicketTemplates()` loads it through `ticketTemplatesImport()`; new `starterUpdateTicketTemplates()` and the `update_starter_ticket_templates` handler replace the ones already here.
  - Report: `agent/reports/ticket_templates.php`, `ticket_templates` in `reportCatalog()` under Service Desk.
  - Database 3.2.1: request forms. `ticket_templates` gains `ticket_template_portal`, `ticket_template_portal_title`, `ticket_template_portal_description`, `ticket_template_portal_icon` (one of `TICKET_TEMPLATE_PORTAL_ICONS`), `ticket_template_portal_who` (`everyone` or `managers`) and `ticket_template_portal_all_clients`; new `ticket_template_portal_clients` (cascading with the template and the client). `fill_in_client`, `ticket_fill_in_client` and `recurring_ticket_fill_in_client` say whether the client is asked a fill-in and sees its answer (default yes).
  - Request forms: `ticketTemplatePortalForms($client_id, $manager)` and `ticketTemplatePortalForm()` (what a contact may use - `$manager` is `portalSeesAllTickets()`), `ticketTemplatePortalTitle()`, `ticketTemplatePortalIcon()`, `ticketTemplateMentionsAsset()`, `ticketTemplatePortalAnswers()` (the client's answers checked, the agents' left blank), `ticketTemplatePortalRaise()` and `ticketTemplatePortalRequests($ids)`; `ticketTemplatePortalClientIds()` and `ticketTemplateSetPortalClients()`. `ticketTemplateFields()` and `ticketTemplateSave()` take the portal fields, and `ticketTemplateFinish()` takes the history line. `client/ticket_add.php` takes `?form=` (`problem` for the usual form) and posts `add_ticket_from_form` to `client/post.php`; its What happens next is `client/includes/ticket_next_steps.php`. `[Agent]` is no longer filled in with a client's name for a ticket raised in the portal with no agent - only an agent who raised it counts.
  - `inc_ticket_template_select.php` draws a `[data-fill-ins-box]` under the picker (`$ticket_template_fill_in_values` starts it with answers) and the options carry `data-template-fill-ins`; `ticket_tasks_modal.js` draws the fields, hides and disables the task rows for other answers, and binds its document listeners once (a window flag). `js/app.js` opens the tab of a required field left empty on a hidden tab (a capturing `invalid` listener).
- Empty states: every list page sets `$empty_state` before including `includes/filter_footer.php`, and `includes/inc_empty_state.php` draws it with `emptyStateHtml()` (`functions/format.php`) when the list has nothing - `thing`, `icon`, `title`, `text`, `actions` (`label`, `modal` or `href`, `size`, `icon`; the first is the main one), `archived`, `view`, `filtered`, `views`, `match_title`, `match_text` and `clear_url`. `emptyStateFiltered($views)` reads the GET string for a search or filter, leaving out paging, sorting, `client_id`, `archived` and the page's views, and `emptyStateClearUrl($views)` is the page without them. `emptyStatePill($pills, $key, $default, $good)` builds a pill's own state from the page's pill array (`label`, `icon`, `title`, `alarm`), with a tick for `alarm` pills and the keys in `$good`. Files, notifications and racks call `emptyStateHtml()` in their own layout; files and notifications set `$empty_state_skip`. A page that sets nothing still gets one, worded from `$page_title`. The pages' own empty blocks (`.itflow-asset-empty` under a list) are gone. CSS: `.itflow-empty-state` with `-icon` (a grey round tile), `-title`, `-text` and `-actions`.
- Removed unused helpers and files:
  - `credentialLink()`, `projectLink()`, `linkCounts()`, `assetContactAvatar()`, `clientOverviewPersonInitials()`, and `getMonthlyTax()` and `getQuarterlyTax()`, which looked a tax up by name
  - `agent/modals/asset/asset_documents.php`, `document_bulk_move.php`, `document_add_file_relation.php` and `project_link_closed_ticket.php`
  - `agent/js/file_delete_modal.js`, `certificate_fetch_ssl.js`, `credential_show_password_via_id.js` and `credential_show_otp_via_id.js`
- `title` attributes were added to about 300 icon-only buttons, and `aria-label` to the show-password buttons and the portal's mobile menu button.
- API: `api/v1/services/read.php` is new, with `services` mapped to `module_support` in `enforce_api_rbac.php`. Ticket create and update use `ticketClientOwnedId()` and `ticketAgentId()`, and certificate create uses `certificateFormSet()`.

### Library Updates

- Removed Tom Select, replaced by ITFlow's own `js/itflow_select.js`.
- ClipboardJS is no longer loaded on agent pages (still shipped for the MFA enforcement page).

## [26.10]

### Upgrading to 26.10

Update from Maintenance > Update — Queue Update hands the job to cron, which updates the files and applies this release's one database change (2.7.8 to 2.7.9) in the same pass. Nothing else is required.

If you are coming from 26.08 or earlier, the database update still has to be run from the command line one time, exactly as described under Upgrading to 26.09 below.

### Breaking Changes and Notes

- API: reading locations, documents, vendors, domains, networks and software no longer returns archived records, including when you ask for one by its ID. There is no way to list archived records through the API yet, so keep the ID of anything you archive if you may want to unarchive it later. Thanks to @Wrongecho.
- API: a location must be archived before the delete endpoint will remove it, the same rule contacts have followed since 26.09.1.
- Recurring Invoices: every existing recurring invoice is set to Send Automatically after the update, so nothing changes until you switch one to the new draft option below. A draft is not emailed, does not appear in the client portal, and is not charged automatically until you send it. Automatic payments only run on an invoice's due date, so if a recurring invoice also takes payment automatically, send its draft before the due date or it will not be charged.
- Notifications: each night ITFlow posts a notification when draft invoices are waiting for review. It counts every draft invoice, including ones created by hand, so if you keep drafts around you will see it daily.
- Stripe: payments affected by the one-cent problem under Bug Fixes are not repaired by the update. A guest invoice payment that went through in Stripe but never appeared in ITFlow still has to be added by hand with Add Payment, putting the pi_ reference from Stripe in the reference field.
- The two shipped files in `agent/custom/includes/` (`custom_side_nav.php` and `inc_all_custom.php`) are now marked do not modify, because updates replace them. If you have edited either one, keep a copy of your version under a different name before you update.

### New Features & Updates

- Recurring Invoices: each recurring invoice now has an Action setting — Send Automatically, as before, or Generate Draft for Review. A draft is created on schedule as usual but waits for you to check it, change it if needed, and send it yourself. The Email Notify toggle is hidden while a recurring invoice is set to draft. Thanks to @Wrongecho.
- Invoices: tickets can be taken back off an invoice. Each linked ticket has a remove button that unlinks it and returns it to unbilled, and the Tickets card shows each ticket's number as a link to the ticket. Billable tickets that are Resolved can now be added, not just Closed ones. Thanks to @Wrongecho.
- Tickets: the assignee shows as a small pill with the agent's photo, or their initials if they have not uploaded one. This applies on the ticket list, the board view, the ticket itself, recurring tickets, and the ticket lists on assets, contacts and projects. An open unassigned ticket gets a red Unassigned pill that turns grey once the ticket is resolved or closed. The Mine and Unassigned buttons on the tickets page have new icons to match.
- Networks: an archived network now shows (Archived) next to its name at the top of its page.
- Navigation: your company name at the top of the side navigation shrinks to fit instead of overflowing when it is long. Thanks to @BoredManCodes.
- API: new endpoints to update, archive, unarchive and delete locations; archive and unarchive documents and software; and create, update, archive and unarchive vendors, domains and networks. For domains, the DNS and WHOIS details are looked up from the domain itself rather than taken from the request, and so is the expiry date when you do not send one. Thanks to @Wrongecho.

### Bug Fixes

- Stripe: about one card payment in fifteen was charged a cent less than the invoice balance. When paid from a guest invoice link, the payment then failed to record in ITFlow even though Stripe had taken the money. When paid automatically, from the client portal or by an agent, it was recorded a cent short and the invoice was marked Paid. Charges now match the balance exactly. See the note above about payments already affected.
- Invoices: adding an unbilled ticket from the invoice page did not work. The Tickets card that holds the add button also only appeared once a ticket was already linked, so the first ticket could never be added there. Both are fixed. Thanks to @Wrongecho.
- Licenses: archived licenses could not be restored, because the menu on an archived license still offered Edit and Archive. It now offers Restore. Restoring does not bring back the asset and contact assignments that archiving removed.
- Assets: a login entered while creating an asset was only saved if it had a username, so a password on its own was lost. A username or a password on its own is now enough.
- Copy buttons inside pop-up windows, such as the ones for credentials and a new API key, did nothing. They copy again.
- Editor: inside pop-up windows, the editor's "..." button for more tools closed as soon as it opened, and the link dialog would not take typing, so links could not be added or edited. The editor's other drop-down menus and dialogs were affected the same way. All of them work again.
- Editor: closing a pop-up that contains an editor and opening it again, New Ticket for example, brought the editor back as a plain text box until the page was refreshed. It now loads every time.
- Documents: images in documents, document versions and queued emails opened in a pop-up spilled past the edge of the window, and tables showed without their styling. The same happened in document previews on the Files page. Both now display properly.
- Tickets: in Change Client, picking the new client did not load that client's contacts, so no contact could be chosen.
- Client Portal: the header with your photo, the welcome line and the company logo no longer breaks onto separate rows on smaller screens, and the menu button on phones shows a proper menu icon. Thanks to @BoredManCodes.
- Restore the ability to type in a manual entry in select boxes with data-tags=true inclusing adding custom ticket watchers.

### Developer Updates

- Database 2.7.9 adds `recurring_invoices.recurring_invoice_auto_send` (`tinyint(1) NOT NULL DEFAULT 1`). It is read by the nightly run in `cron/nightly_tasks.php` and by `force_recurring` in `agent/post/recurring_invoice.php`. Invoices generated in draft mode are inserted as Draft and everything else as Sent, as before. The cron email still also requires `config_recurring_auto_send_invoice` and the recurring invoice's email notify to be on.
- The nightly draft notice is a single `appNotify('Draft Invoices', ...)` linking to `invoices.php?status=Draft`. It is raised whenever any Draft invoice exists.
- Stripe: all four PaymentIntent sites (`agent/post/payment.php`, `client/post.php`, `cron/nightly_tasks.php`, `guest/guest_ajax.php`) now send `intval(round($balance_to_pay * 100))`.
  - `intval()` alone truncates, and about 6.6% of two-decimal amounts land just under the whole cent in floating point (2.01 × 100 = 200.999…).
  - The guest return path compares whole cents with `round()` on both sides, so the short charge failed that check and nothing was booked.
  - The other three paths compare whole dollars, so the short charge passed and they booked the short amount. Those dollar-level checks are unchanged.
- `formatAssignee($user_id, $user_name, $user_avatar, $highlight_unassigned = true)` in `functions/format.php` replaces the assignee rendering at nine sites. Callers need `user_avatar` in their query. It was added to the related-ticket queries on contacts and projects and to `$ticket_select_columns` in `agent/tickets.php`.
- TinyMCE in modals: Bootstrap 5's focus trap pulled focus back out of TinyMCE's popups, which render in `.tox-tinymce-aux` on `<body>`.
  - `js/app.js` binds a `focusin` listener on `.tox-tinymce-aux` once at page load, and it calls `stopImmediatePropagation()`. Bootstrap adds its own listener each time a modal opens, so this one always runs first.
  - `js/ajax_modal.js` now removes a modal's TinyMCE editors on `hidden.bs.modal`. TinyMCE kept the dead editor in its registry, and `tinymce.init` skipped the next textarea with the same id.
- Scripts loaded inside ajax modals cannot rely on `DOMContentLoaded`, which has already fired by then.
  - `js/pretty_content.js` now exposes `prettyContent()` and runs it immediately when the document is already parsed. `agent/files.php` calls it again after loading a preview.
  - `agent/js/ticket_change_client.js` moved to `itflowReady()`.
- Clipboard: the ClipboardJS container is now chosen per click — the trigger's own modal, otherwise `body` — so Bootstrap's focus trap no longer swallows the copy.
- Sidebar brand: `agent/css/sidebar_brand_fix.css` and `agent/js/sidebar_brand_fix.js` are loaded from `agent/includes/side_nav.php`. They scale `#sidebar-brand-text` with a CSS transform, driven by a `ResizeObserver` on the brand link. The custom side nav does not load them.
- Invoices: new `remove_ticket_from_invoice` GET handler in `agent/post/invoice.php` (CSRF token in the URL, `module_sales` 2, `enforceClientAccess`). `add_ticket_to_invoice` now redirects to the invoice instead of `post.php`, and the billable list is `ticket_status IN (4, 5)`.
- Software: new `restore_software` handler in `agent/post/software.php` (`module_support` 2).
- API: new endpoint files under `api/v1/`:
  - `locations/{update,archive,unarchive,delete}`, `documents/{archive,unarchive}`, `vendors/{create,update,archive,unarchive}`, `domains/{create,update,archive,unarchive}`, `networks/{create,update,archive,unarchive}` and `software/{archive,unarchive}`.
  - New `vendor_model.php`, `domain_model.php` and `network_model.php`.
  - Writes are POST and scoped by the posted `client_id`. Every `read.php` for those modules now filters on `*_archived_at IS NULL`.
  - Domain create and update call `getDnsRecords()` and `getDomainExpirationDate()`, so each request does live DNS and WHOIS lookups. Update writes `domain_history` rows the way the web form does.
  - Archiving software through the API only sets `software_archived_at` and leaves `software_assets` and `software_contacts` alone. The web archive deletes them.
- `add_asset` in `agent/post/asset.php` creates the credential when either a username or a password is posted. It previously required a username.

### Library Updates

- Bump TinyMCE from 8.8.2 to 8.9.2.
- Bump stripe-php from 21.0.0 to 21.3.2. The pinned API version moves from 2026-06-24.dahlia to 2026-08-26.dahlia. `TelemetryId` is unchanged, so `includes/stripe_init.php` is still needed.
- Bump FullCalendar from 7.0.2 to 7.1.0.
- Bump DataTables from 3.0.1 to 3.1.2.

## [26.09.3] Maint Release

### Upgrading to 26.09.3

Update from Maintenance > Update — Queue Update hands the job to cron and it applies on its own. There is no database change in this release, so nothing else is required.

### Breaking Changes and Notes

- New tickets are now assigned to whoever is creating them. The assignee on the ticket form and on the client bulk-add form starts on your own name instead of Unassigned, and you can still pick anyone else or Unassigned before saving.
- API: a ticket created with an assignee now comes in as Open rather than New, matching what happens when an agent assigns a ticket by hand. Anything of yours that watches for New tickets to pick up work will no longer see assigned ones.
- Tax Summary and the dashboard Income by Category chart will show different numbers than they did before. Both were wrong wherever an invoice had been partly paid, and both usually read high. The corrected figures are described under Bug Fixes.

### New Features & Updates

- Invoices: a partly paid invoice shows what is still owed in red underneath the invoice total in the list, so you can see the outstanding balance without opening the invoice.

### Bug Fixes

- Invoices: the Unpaid figure at the top of the invoice list counted the whole of every partly paid invoice rather than what was left on it, and counted that invoice again for each payment against it. An invoice for $1,000 with three payments of $100 added $3,000 to Unpaid; it now adds the $700 that is actually outstanding.
- Reports: Tax Summary counted an invoice's full tax once per payment recorded against it, so a partly paid invoice with three payments reported three times its tax. Tax is now booked to the month the money came in, in proportion to how much of the invoice that payment covered, and each payment is counted once. Yearly totals also no longer disagree with the months they are made of.
- Dashboard: the Income by Category chart only counted invoices marked Paid, so partly paid invoices contributed nothing and revenues entered outside an invoice never appeared at all. It now counts payments and revenues as they land, matching the Cash Flow chart above it and the Income Summary report.
- Tickets: ticket-created emails told the client the status was Open no matter what the ticket was actually set to. They now carry the real status, on tickets an agent creates and on scheduled tickets from recurring tickets.
- Tickets: the assignee list on the client bulk-add form left out agents on the Accountant role and did not match the list on the normal ticket form. Both lists are now the same.
- Quotes and Recurring Invoices: picking a product from the item autocomplete put the word "undefined" in the item name, while the description and price filled in correctly. Invoices were not affected. The product list behind the box was also missing information on those two pages, so every entry read "No tax", services showed a stock badge, and searching by product code did not match. Reported by @cthompson.

### Developer Updates

- `getMonthlyTax()` and `getQuarterlyTax()` in `functions/app.php` are rewritten. They previously joined `invoice_items` to `invoices` to `payments`, which multiplied the line-item rows by the payment rows — the double counting was row multiplication, not a rounding problem. Both now drive off `payments`, join a pre-aggregated per-invoice tax subquery, and scale by `payment_amount / invoice_amount`, with `invoice_amount > 0` guarding the division. `agent/reports/tax_summary.php` also dropped a second loop that recalculated each row total by calling `getMonthlyTax()` another twelve times; the total accumulates in the first loop instead, cutting the queries behind the monthly view in half.
- `agent/invoices.php`: the Partial total no longer selects `SUM(invoice_amount)` across a `payments` join, and payments against partial invoices are subtracted from the unpaid figure. The list query gained a derived `LEFT JOIN (SELECT payment_invoice_id, SUM(payment_amount) ... GROUP BY payment_invoice_id)` for the per-row balance, which keeps it to one query rather than one per row.
- `agent/dashboard.php`: the `TopCategories` temporary table is now built from a `UNION ALL` of payments (carrying their invoice's category) and revenues, keyed on payment and revenue dates rather than `invoice_status = 'Paid'` and `invoice_date`. The Other bucket is built from the same union.
- `api/v1/tickets/create.php` sets `ticket_status = 2` when `assigned_to > 0`, rather than always inserting status 1.
- `agent/modals/client/client_bulk_add_ticket.php` filtered the assignee list on `user_role_id > 1` where every other assignee list uses `user_type = 1`. Role 1 is the built-in Accountant role, so accountant-role agents were missing from it.
- Product autocomplete is consolidated. The three pages each carried their own product `SELECT` and their own copy of the autocomplete JavaScript; the queries drifted, and quote and recurring invoice were still on a four-column version that had no `product_name` or `prod_id`, so the shared `onSelect` wrote `undefined` into `#name`. Its last line also assigned to `#product_id`, which only the invoice form has, so `onSelect` threw a `TypeError` on those two pages and the `input` handler under it threw on every keystroke. The query now lives in `getProductsForAutocomplete($mysqli)` in `functions/app.php` and the JavaScript in `js/product_autocomplete.js`, with the hidden `#product_id` added to the quote and recurring item forms and treated as optional in the JavaScript. Net 218 lines removed for 134 added. Note that `item_product_id` is still only written by `add_invoice_item` and the API, so the hidden field on those two forms is inert until the handlers are wired up.

## [26.09.2] Maint Release

- Updates the App Version to a proper version number.


## [26.09.1] Maint Release

### Upgrading to 26.09.1

Update from Maintenance > Update — Queue Update hands the job to cron and it applies on its own. There is no database change in this release, so nothing else is required.

### Breaking Changes and Notes

- API: a contact must be archived before the delete endpoint will remove it. Deleting an active contact is refused and reports nothing deleted, so archive it first and then delete. Thanks to @Wrongecho.

### Bug Fixes

- Assets: IP address fields demanded all three digits of every octet, so `10.0.0.1` had to be entered as `010.000.000.001`. They take natural input again, and a field holding DHCP is now left alone rather than being emptied the moment the modal opens.
- Networks: in the IP list, an empty hostname or description shows a dash rather than a blank cell, the column headings match the rest of the app, and the table is tighter so more addresses fit on screen.

### Developer Updates

- The IPv4 mask in `js/app.js` is a regex mask rather than four `IMask.MaskedRange` blocks. A pattern mask will not advance past a separator until the current block reaches its `maxLength`, which is what forced the three-digit octets. A regex mask has no per-block completeness rule and tests the whole value on each keystroke, so a partial `10.0.` is valid on its own. Octets are still bounded to 0-255 and leading zeros are still accepted, matching what the old jquery.inputmask `ip` alias allowed. Any value not made purely of digits and dots is skipped, because `interface_ip` and `asset_ip` are `varchar(200)` and also carry the literal `DHCP` written by the checkbox on those same modals.
- Dead display variables removed from the asset, expense and product listings. `$asset_description_display`, `$client_name_display` and `$product_description_display` are folded into `?: '-'` at the point of assignment.

## [26.09]

### Upgrading to 26.09

> **The database update has to be run from the command line one more time.** The web interface no longer applies database updates, and the new queued-update path needs a schema change that this release itself adds — so on this one upgrade there is nothing in the browser that can finish the job. After this, it goes back to being a single button.

1. **Back everything up.** Maintenance > Backup, or a full VM snapshot.
2. **Update the files from Maintenance > Update as normal.** The old page pulls the new files and reports success, then drops you into 26.09 running against the 26.08 database. Errors at that point are expected and stop as soon as step 3 completes.
3. **Run the database update from the command line.** Run it as the user that owns the ITFlow files — the script tells you which user if you get it wrong:
```bash
sudo -u www-data php /var/www/itflow.example.com/scripts/update_cli.php
```
It applies every pending version from 2.6.8 to 2.7.8 in order and reports each one as it goes. If a step fails it stops there without advancing the recorded version, so you can fix the cause and run it again.
4. **Check it took.** Maintenance > Update should show the database up to date and the Queue Update button available.

From here on the command line is optional. Maintenance > Update hands the work to cron, which updates the files and the database in one pass.

### Breaking Changes and Notes

- Updates are no longer applied by your browser. Maintenance > Update either hands the job to cron, which runs it in the background, or you run it from the shell. As a side effect ITFlow now updates cleanly on hardened hosts that disable PHP's shell functions, which it could not do before.
- Database updates have been removed from the web interface entirely. Cron applies them as part of a queued update, or you run `php scripts/update_cli.php` yourself.
- Running `php scripts/update_cli.php` with no arguments updates the files and then the database in one go, and the file update is forced — any local edits you have made to shipped files are discarded. Use `--update_db` if you only want the database half; it never touches your files.
- If you have added your own CSS or JavaScript under `agent/custom/`, expect it to need updating. The interface has moved to a new major version of the framework it is built on and most class names have changed. Details are in Developer Updates below.
- Deleting a payment now requires Full access to both Sales and Financial, up from Modify. Refunding requires the same. Deleting a payment removes money from the books, so it is treated as a delete rather than an edit — agents who could remove payments before may no longer be able to.
- The five built-in ticket statuses now have fixed SLA clock behaviour and can no longer be configured. New and Open always run; On Hold, Resolved and Closed always pause the resolution clock. Only custom statuses keep the SLA Clock dropdown, and the built-ins are marked "(fixed)" in the list.
- On Hold pauses the resolution clock, not the response clock, and only once the ticket has had its first reply. A ticket parked on hold before anyone replies still breaches its response target — reply first, then hold.
- Breaches already recorded against on-hold tickets under the old behaviour are left as they are. Change a ticket's priority or SLA to re-stamp it if you want it recalculated.
- Holidays and closure days are new and start empty. Until you add them, SLA clocks keep running through your closures exactly as they did before.
- Tickets default to Medium priority when none is given, and ticket replies default to Public.
- Closed tickets can no longer be deleted. A closed ticket is treated as a permanent record.
- Send Email on invoices and quotes now opens a contact picker rather than sending straight away. Quick Send, described below, keeps the old one-click behaviour, but any bookmarked direct links to the send action no longer work.

### New Features & Updates

- The interface has had a full visual overhaul, with dark mode carried properly through the calendar, the editor, confirmation dialogs, tables and form controls.
- Your theme colour and any custom CSS now apply everywhere, including the client portal, the guest pages, login and setup. Those pages previously ignored both and rendered in the default styling.
- Ticket: canned responses. Add them under Admin > Templates > Canned Responses, scoped to a ticket category or to all categories, and pick one from the reply form. It inserts at the cursor, so picking one into a half-written reply adds to it rather than replacing it.
- Ticket: SLA response targets now appear on ticket-created emails, rolled up to business days, with a note to call in on High and Urgent.
- Ticket: the reply card stays out of the way until you click Reply.
- SLA: holidays and closure days. Define the days you are closed and the SLA clock pauses through them, with a one-click importer for US federal holidays.
- Networks: a full IP address section under each subnet — address, hostname and description, with search, sort, bulk delete, CSV import and export. Addresses are checked against the subnet they are being added to and duplicates within a subnet are refused, so the same guard applies whether you type one in or import a thousand. Both IPv4 and IPv6, sorted numerically so .9 comes before .10.
- Designate one client record as your own organization, under Company Details. Clicking your company name at the top of the side navigation now takes you straight to it.
- Invoices and Quotes: Quick Send is a one-click send to the default contacts, the way Send Email used to work — primary and billing for invoices, primary for quotes. It sits at the top of the actions menu with a lightning bolt and asks for confirmation, and never opens the picker.
- Invoices and Quotes: Send Email, below it, opens a modal listing every contact on the client with their email addresses and the same defaults pre-checked, for when the invoice needs to go somewhere other than the usual place. Who it went to is recorded in the document history either way.
- Invoices and Quotes: Mark Sent asks how it was sent — snail mail, an email client, in person and so on — and records the reason in the history.
- Stripe: refund a card payment from the invoice itself. The refund goes through Stripe, is written to the invoice history, and the payment is removed, replacing the old routine of deleting the payment in ITFlow and refunding it by hand in the Stripe dashboard.
- Stripe: adding or editing the Stripe provider now checks the secret key against Stripe and tells you if it is wrong, instead of the key looking fine until the first client tries to pay.
- Stripe: a client paying from a guest invoice link no longer needs a Stripe customer record to exist first — Pay Now sets one up as part of the payment.
- Client account statements. Send one from the invoice list with a date range and an option to leave out paid invoices, view it on the guest invoice page, and download it as a PDF from the client portal.
- Files: documents and files are now told apart, with a filter for all documents or just files. Thumbnail view shows every file, previews work for documents, PDFs and text, and your choice of list or thumbnail view survives navigating between folders.
- Client Portal: the profile page has been rebuilt — department, location, title and phone with inline editing, PIN changes, recent sign-ins and recent activity, with a separate full activity page.
- Client Portal: empty tables now say what is missing instead of showing a bare header row, and the saved payment method wording is clearer about what saving a card actually does.
- Guest: the ticket task approval page has been rebuilt. The request being approved leads the page, the ticket body sits underneath as context, the approve action is a proper button rather than a link in a paragraph, and approving or declining lands on a real confirmation screen. Internal routing jargon that meant nothing to a client has been dropped.
- Guest and client portal pages now have proper footers, and the agent footer stays at the bottom of short pages instead of floating mid-screen.
- Phone numbers show their country code, and the country is kept when you edit the number. The international phone input is now used on every phone field.
- Maintenance > Update: Queue Update hands the update to cron, which runs it in its own process and updates the files and the database in one pass. Check now looks for new commits without needing shell access, listing the pending commits with their dates and descriptions.
- Mail Parser: you are now notified when the parser skips an autogenerated email rather than it disappearing silently.
- Demo data. Twenty fictional clients with two years of history — a mix of managed and break-fix, tickets, invoices, expenses, assets and contracts, including FOSS products — loaded from Maintenance > Starter Content and tagged so it can be cleared out again.
- API: reads accept an optional `client_id` filter on top of the key's own client scoping, so a key that can see everything can ask for one client's records. It can only narrow — a key with no access to that client still gets nothing.
- API: new endpoints to close a ticket and to delete a client. Closing resolves the ticket first if it has not been resolved already, so the SLA figures come out right. Thanks to @BoredManCodes for the client delete endpoint.
- Client pages load noticeably faster, running roughly a third fewer queries than before, and several pages that were slow on large installs have been sped up.
- Lists now say whether nothing matched your filters or there are no records at all, instead of showing an empty table either way.
- Assets and Contacts: an "Add primary" link where a client has no primary location or contact set.
- The client header at the top of client pages collapses, and stays collapsed as you move between pages.
- Page changes fade in rather than flashing, the calendar reserves its height so the page no longer jumps as it loads, and table listings line up consistently across the app.

### Security

- A restricted agent could open an asset, contact or location belonging to a client they have no access to by entering its id in the address bar. Those pages are now gated on the module permission that owns them and checked against the agent's client access rules, like every other record page.
- Client Portal: changing a password or a PIN now requires the current password. Contacts signing in through SSO are exempt — there is no local password to check and the identity provider has already done it.
- A user name containing HTML could inject markup into the page through the ticket task approver list. It no longer can.
- Deleting and refunding payments now require Full access to both Sales and Financial, as described under Breaking Changes.

### Bug Fixes

- Tickets could not be opened for clients marked as a lead.
- Client Portal: raising a ticket sent no new-ticket notification — the notification errored out instead.
- The readable password generator called a function that no longer existed, so generating one did nothing.
- Client pages flashed blank on every load.
- Invoice emails went to archived contacts, and every copy carried the primary contact's name in the greeting rather than the name of the contact receiving it.
- Guest: the confirmation dialog on ticket task approvals and on quote accept and decline rendered as plain text at the foot of the page instead of as a dialog, because the guest pages never loaded its stylesheet.
- Filtering the audit log by date was slow on large installs.
- API: deleting an asset reported how many interfaces it had removed rather than confirming the asset itself was deleted.
- API: updating a client failed outright if the request did not include the lead field.
- API: a request that authenticated correctly but then failed on the query wrote nothing to the app log, leaving nothing to debug from.

### Developer Updates

Front-end framework migration:
- AdminLTE 3.2.0 to 4.9.1 and Bootstrap 4.6.2 to 5.3.8. The layout skeleton is renamed throughout — `content-wrapper`, `main-sidebar` and `main-header` become `app-main`, `app-content`, `app-sidebar` and `app-header`.
- AdminLTE 4 dropped a number of v3 classes ITFlow relies on. `text-bold`, `text-sm`, `btn-default`, `img-circle`, the `.alert .icon` pairing, the sidebar badge positioning, the `small-box` watermark icon and all sixteen theme colours are reproduced in `css/itflow_custom.css` at v3's computed values, driven by a single `--itflow-accent` variable per theme.
- Bootstrap 5 split `.bg-*` from `.text-bg-*`, so every `bg-dark` card and modal header needed its text colour restored explicitly.
- `input-group-append` and `input-group-prepend` wrappers are deleted rather than renamed, selects moved from `form-control` to `form-select`, `data-toggle="buttons"` groups became `.btn-check`, and `custom-control`, `custom-select` and `custom-file` are gone.
- `.input-group > .form-control` at specificity (0,2,0) outranks `.form-control-color`'s `width:3rem` at (0,1,0), which collapsed every colour swatch. Fixed with a matching-specificity `.input-group > .form-control-color` rule rather than a `w-auto` override, which only hands the width to the UA default.
- Stylesheets and scripts were repeatedly found loaded by `includes/header.php` only. `css/itflow_custom.css` and `libs/sweetalert2/css/sweetalert2.min.css` are now loaded by the client portal, guest, login, setup and MFA headers as well — the SweetAlert2 gap is why guest dialogs rendered in normal flow, since all of its positioning lives in the stylesheet while `includes/footer.php` was loading the JS fine.

jQuery removal:
- jQuery, jQuery UI, select2, Inputmask, daterangepicker, Moment, Tempus Dominus, toastr, pdfmake, Dropzone and Popper are all gone. Replacements are Tom Select, Flatpickr, IMask, SweetAlert2 and Bootstrap's own toasts, plus `js/autocomplete.js` for the product and OS autocompletes.
- New helpers in `js/app.js`: `itflowPostForm()` reproduces jQuery's bracketed array encoding that `ajax.php` parses, `itflowBindOnce()` replaces the namespaced `.off().on()` pattern, and `initTomSelect` / `refreshTomSelect` / `clearTomSelect` / `setTomSelectValue` wrap Tom Select.
- `includes/modal_footer.php` re-executes `js/app.js` on every ajax modal open, so every initialiser needs a re-entry guard or it double-initialises.
- New `itflowReady()` restores jQuery's `.ready()` semantics. Scripts injected into an ajax modal run after `DOMContentLoaded` has already fired, so a bare listener never runs — this is what broke notification pagination, asset OS autocomplete, the contact authentication toggle, AI ticket summaries and AI document template generation mid-cycle.
- `js/ajax_modal.js` re-injects `<script>` tags explicitly, because `innerHTML` does not execute them and `.append()` did.

Schema and queries:
- `history_description` widens from `varchar(200)` to `text` (2.7.7). A send to several recipients overflows 200 characters and strict mode errors rather than truncating.
- Indexes added on the client-scoped columns (2.7.5), the per-parent child fetches and the mail queue loop (2.7.6), and `logs(log_user_id, log_client_id)` for the portal profile and activity pages (2.7.8). No new migrations after 2.7.8 — the Stripe work reuses `client_payment_provider`.
- `agent/includes/inc_all_client.php` rewritten so the sidebar badge counts are one query per table rather than one per number: 43 queries to 27 per client page load, with invoices scanned once instead of eight times.
- `admin/audit_logs.php`'s date filter was `DATE(log_created_at) BETWEEN`, which is not sargable and made `KEY log_created_at` unusable. It is now a half-open range.

API:
- `apiClientScopeSql()` now takes an optional caller-supplied `client_id` and appends it after the scope fragment, so the filter can only narrow what the key is already allowed to see. Reads only — writes take `client_id` as the target they act on and validate it separately.
- `api/v1/tickets/close.php` sets status 4 with `ticket_resolved_at` first where the ticket has not been resolved, calls `syncTicketSlaClock()` and `setTicketResolutionSlaMet()`, then sets status 5 and stamps `ticket_closed_by`. Every query is scoped by `ticket_client_id` and `ticket_closed_at IS NULL`, so a second call is a no-op rather than a re-close.
- The four `*_output.php` helpers now `logApp()` on query failure, which previously returned an error to the caller and left no trace server-side.

Other:
- The client portal PIN handler checked length before `escapeSql()`, whose `strip_tags()` then emptied the value, and stored the blank while flashing success. Length is now checked after sanitising.
- The client delete modal's script was blocking the parser mid-body, which is why every client page flashed blank; it is deferred now.
- The gating fix under Security covers `agent/asset.php`, `agent/contact.php`, `agent/contacts.php` and `agent/locations.php`.
- The ticket task approver picker built `<option>` markup by template literal into `innerHTML`; it uses `new Option()` now, which assigns text.
- Stripe refunds live in `agent/post/payment.php` behind `refund_payment_stripe`, pulling the payment intent out of the stored reference and calling `refunds->create()` for the full amount. Partial refunds are not supported.
- Payment provider post variables moved into a model to stop the add and edit paths drifting apart.
- New `functions/network.php` (subnet containment and IP normalisation, v4 and v6) and `functions/files.php`; `functions/sla.php` extended for holidays and closure days.
- Phone input handling moved out of `js/app.js` into `js/phone_inputs.js` so the client portal can load it without pulling in everything else.
- Update path: `admin/post/update.php` no longer shells out at all. `cron/update_check.php` does the fetch and stores the result, `cron/app_update.php` runs `scripts/update_cli.php` as a child process, and the database phase re-execs against the newly updated code so a migration calling a brand new helper does not hit an undefined function.
- Removed 29 dead or duplicate files, including 348KB of unused FullCalendar themes, and fixed two broken script paths.
- The debug page now recommends 512M for PHP's memory limit.
- README, CONTRIBUTING and SECURITY.md updated.

### Library Updates

- Bump AdminLTE from 3.2.0 to 4.9.1.
- Bump Bootstrap from 4.6.2 to 5.3.8.
- Bump intl-tel-input from 25.3.0 to 29.2.3.
- Bump ImapEngine from 1.25.4 to 1.25.6, along with its dependencies.
- Added Tom Select, Flatpickr, IMask and SweetAlert2, none of which need jQuery.
- DataTables now uses its Bootstrap 5 styling build.
- Removed jQuery, jQuery UI, select2, select2-bootstrap4-theme, Inputmask, daterangepicker, Moment, Tempus Dominus, toastr, pdfmake, Dropzone, Popper and Show-Hide-Passwords-Bootstrap-4.

## [26.08.2] Maint Release

### Upgrading to 26.08.2

Update the files from Settings > Update as normal. There is no database change in this release, so nothing else is required.

### Bug Fixes
- Calendar: fixed the agent calendar showing no events.
- Calendar: shared calendar feeds set to publish busy blocks only were publishing full event titles, locations and descriptions to anyone holding the subscription link.
- Cron: fixed Maintenance > Cron failing to load. Scheduled jobs themselves were unaffected and kept running.
- Exports: restored the missing columns on the ticket, quote, recurring invoice, software and user exports.
- API: restored the full record on the credentials list endpoint.
- Mail: switching an existing install from Standard SMTP/IMAP to Microsoft 365 or Google OAuth no longer leaves the old mail server behind, which stopped sending and ticket email fetching from working. The connection settings for OAuth providers are now fixed by the provider and cleared on save.

## [26.08.1] Maint Release

### Upgrading to 26.08.1

Update the files from Settings > Update as normal. This release moves the database to 2.6.7 and the web updater completes it for you — the command line step that 26.08 required is not needed again.

### Breaking Changes and Notes

- Client access: agents with restricted client access now see records that have no client assigned. Previously this varied by page — unassigned tickets and projects were visible, unassigned expenses and credentials were not. It is now consistent everywhere.

### Bug Fixes
- Setup: fixed the wizard closing itself after the first user, which left new 26.08 installs stuck in a redirect loop between `/setup` and `/login.php`.
- API: tightened client scoping on the expense read and record update endpoints.
- Income: revenue rows now respect restricted client access.
- Client PDF Export: fixed the export producing a CSV file, and each section is now gated on the module that owns it.
- AI: fixed model creation, per-use-case model selection, configurable temperature, and error reporting.
- Ticket: system-generated replies no longer record time worked that was never worked.
- Ticket: fixed an error when scheduling a ticket.
- Ticket: cancelling a schedule now cancels the calendar event on the recipient's calendar.
- Ticket: history no longer records a status change when the status did not change.
- Recurring Ticket: bulk priority changes no longer deny access to agents who are not administrators.
- Contact: deleting a contact now removes the linked portal user, and anonymizing now redacts the phone number.
- Calendar: fixed event deletion.

### New Features & Updates
- Performance: queries now select only the columns they use instead of `SELECT *`, cutting memory use and query time across the app and especially in the crons.
- Performance: removed client joins that were only there for scoping — side nav badge counts are significantly faster.
- Client scoping: added a `clientScopeSql()` helper so list queries scope on the owning column instead of a joined `clients.client_id`.
- Contributing: documented the column-selection and client-scoping conventions.


## [26.08]
 
### Upgrading to 26.08
 
> **Read this before you start.** Done out of order this update will break your instance. The database structure changes, every API key is deleted, and the whole cron setup is replaced.
 
1. **Back everything up.** Take a full VM backup or snapshot before you start.
2. **Remove every ITFlow line from your crontab** (or delete `/etc/cron.d/itflow`). The old per-minute jobs must not keep firing against a half-updated install. You put the new one in at step 5.
3. **Update the files with the normal web updater**, from Settings > Update. It will pull the new files, report the update as successful, and then error out as it drops you back into the app. That is normal — the new code is now running against the old database.
4. **Run the database update from the command line.** Just this once it cannot be done from the web interface. Run it as the user that owns the ITFlow files — the script tells you which user if you get it wrong:
```bash
sudo -u www-data php /path/to/itflow/scripts/update_cli.php --update_db
```
It applies every pending version in order and reports each one as it goes. On an install with a lot of ticket history it can take a minute or more, so let it finish. If a step fails it stops there without advancing the recorded version, so you can fix the problem and run it again. The 500s stop as soon as it completes.
 
5. **Add the new cron entry.** One line runs everything now, and the schedules are managed in ITFlow under Settings > Cron:
```
* * * * * www-data php /path/to/itflow/cron/cron.php >/dev/null
```
Drop the `www-data` column if this goes in a user crontab rather than `/etc/cron.d`.
 
6. **Recreate your API keys.** Every existing key is deleted by this update. Issue new ones and update anything that talks to the ITFlow API.
7. **Check it took.** Open Settings > Cron — the green "Cron last checked in" banner should appear within a couple of minutes and every job should pick up a schedule.
Only this release needs the command line for the database update. Normal updates go back to running from Settings > Update as usual.
 
### Breaking Changes and Notes
 
- Cron: the crontab collapses to a single entry. `cron/cron.php` is now a dispatcher that runs every minute and works out which jobs in `cron/` are due, and the old nightly work has moved to `cron/nightly_tasks.php` which it runs at 03:00.
- Cron: an existing crontab keeps working — the per-minute scripts still run and still lock correctly, and a single daily `cron.php` entry still runs the daily jobs — but any job added in this or a future release only runs once the dispatcher is scheduled.
- Cron: ticket SLAs need no entry of their own. `cron/ticket_sla.php` is in the dispatcher's job list and runs every minute once the new entry is in place. Without it SLA targets are still worked out and displayed, but warnings and breaches never fire.
- Backups: write down your backup encryption key. It is generated on first use and stored in `config.php`, never in the database, and without it a backup cannot be restored.
- API: every existing key is deleted by this update and must be recreated. Keys are now owned by a user and inherit that user's role, module and client permissions instead of carrying their own client scope, and existing keys cannot be safely mapped to a user.
- API: credential decrypt passwords are now read from the request body instead of the query string. Any caller passing that value in the URL needs updating.
- Client access permissions now support deny rules as well as allow, and the permissions UI will not load until the database update has run.
- Business hours are new and default to Monday to Friday, 09:00 to 17:00 in your configured timezone. SLA targets are measured against them, so set them before assigning SLAs.
- The `plugins` directory is now `libs`. Anything pointing at `plugins/` directly — custom scripts, reverse proxy rules, web server config — needs updating.
- Several pages dropped the `_details` suffix and moved to consistent singular and plural filenames, so old bookmarks and external links will 404.
- Credential passwords moved from `varbinary` to `varchar(500)` and now have a length guard. Existing credentials are migrated by the database update.
### New Features & Updates
 
- Backups are now encrypted, catalogued, schedulable and restorable from the command line. Three types — Full (database and uploads), Database Only, and Master Key — and every archive is an AES-256 encrypted zip. Open them with 7-Zip, WinZip, PeaZip or Keka; `unzip`, Windows Explorer and the macOS Archive Utility do not support AES.
- Backups: one encryption key per install, generated on first use and stored in `config.php`, never in the database and never in the file name. It is shown in Maintenance > Backup.
- Backups are built by cron rather than by your browser. The button queues the work and the dispatcher picks it up within the minute, then notifies you — a dump of a real install takes longer than a web request is allowed to live, which is why the old Download Backup button timed out on large instances.
- Backups: scheduled backups are a new `backup` cron job, off by default, turned on in Maintenance > Cron. Retention by age and by count runs in the nightly job and never deletes the newest backup.
- Backups are stored outside the web-served path under `uploads/backups/` with a deny-all rule and downloaded through an admin-only handler. Set `$config_backup_path` in `config.php` to keep them off the web root entirely.
- Backups: restore from the command line with `php scripts/restore_cli.php --file=/path/to/backup.zip`. This is the only restore path with no size limit — the setup wizard's restore is capped by PHP's upload limits and a full backup is usually larger. `--inspect` checks an archive without changing anything.
- Backups: restores validate before they destroy. The key is checked and the archive unpacked before any table is dropped, and the current database is dumped first and put back automatically if the import fails.
- Cron: one entry instead of five, and a page to manage it. Jobs are tracked in a new `cron_jobs` table, so a job whose slot was missed runs at the next opportunity rather than waiting a day, and each job locks for its own run so a slow mailbox or a long nightly pass no longer holds anything else up.
- Cron: new Settings > Cron page listing every job with its schedule, last run, duration, outcome and next due time. Jobs can be disabled, rescheduled, or run on demand — Run Now hands the job to the next dispatch so it starts within a minute and still runs on the command line. The last error is kept until dismissed rather than vanishing behind the next success, and the page says plainly when the crontab entry itself is missing.
- Cron: the nightly run is safe to repeat. Late fees, overdue invoice reminders and autopay retries now apply at most once per invoice per day, so a Run Now after the scheduled pass no longer stacks fees or re-emails clients. Nightly Tasks only accepts the daily schedule.
- Cron: the master enable switch moved out of Notifications and into the Cron settings where it belongs, and the unused overdue invoice setting has been removed.
- Ticket: major UI overhaul of the ticket list, the kanban board and the ticket detail page.
- Ticket: redesigned the task bar on ticket details and removed the redundant task count.
- Ticket SLAs, optional throughout. An SLA sets a response target and an optional resolution target, assigned per client and priority with a global default and an explicit "no SLA" override. Targets are measured against your business hours. Tickets show time remaining and turn yellow at a configurable warning threshold and red on breach, on both the ticket list and the kanban board, and can be filtered by SLA state. Nominated statuses pause the resolution clock for "waiting on customer", preserving the remaining budget. Two new reports, SLA Summary and SLA by Client. With no assignments defined nothing behaves any differently.
- Ticket: added an Urgent priority.
- Ticket: agents can attach files to tickets from inside the app, both when raising a ticket and on a reply, and attachments are emailed to the contact through the mail queue. A 10 MB ceiling applies per message; anything that does not fit stays on the ticket to download.
- Ticket: tasks can be added and edited inline in the add ticket and add recurring ticket modals.
- Ticket: the older add ticket modal has been retired, there is one add ticket modal now.
- Ticket: watchers and attachments have moved into the assignment section of the add ticket modal.
- Ticket: recurring tickets can be assigned a ticket template. Picking one fills in the subject and details and stamps the template's task list onto every ticket the schedule raises, from the nightly run and a forced run alike. The recurring ticket list shows which schedules carry a template and how many tasks it adds.
- Ticket: recurring tickets now own their task list. The template fills it in when picked but it can then be edited per schedule, and it is those edits the run reads. Existing schedules are backfilled from their template by the database update.
- API: added ticket reply endpoints for creating and reading replies.
- API: added an invoice_items endpoint for adding line items to an invoice.
- Calendar: calendars can be published as a read-only iCalendar (ICS) subscription feed and read by Google Calendar, Nextcloud, Apple Calendar, Thunderbird or anything else that takes a feed URL. The link carries a secret key and needs no login, can be regenerated or revoked at any time, and a busy only option publishes time blocks without titles, descriptions or locations. Refresh timing belongs to the subscribing client — Google refreshes on its own schedule and cannot be forced, and Nextcloud defaults to weekly and refuses feed URLs resolving to private IPs.
- Calendar: events can be marked all day, and the date and time are now separate fields. Previously all day was inferred from a midnight start, which made a genuine midnight appointment indistinguishable from an all-day event. Existing events are backfilled by the database update using the old rule, so nothing changes appearance.
- Calendar: repeating events now work. The Repeat field was present but disabled and the stored value was never drawn. It is now selectable daily, weekly, monthly or yearly, and monthly and yearly series skip dates that do not exist in a period rather than sliding into the next month. Recurrence is series-wide — editing any occurrence edits the whole series, and individual occurrences cannot yet be moved or cancelled. Repeating events are marked with an icon and a hover note, and the delete action reads Delete series and asks for confirmation.
- Calendar: clicking empty space creates an event there. Clicking a day or a time slot, or dragging across several, opens the New Event modal with the start and end already filled in and the All day switch set to match. A range dragged out or lengthened by hand is no longer overwritten by the end-time-follows-start behaviour.
- Exports: every export modal now has a Filter tab and a Selectable Columns tab with sensible defaults, and can export to PDF as well as CSV.
- Combined Payments and Revenues into a single Income page with CSV export. Revenue not tied to an invoice is still added there and payments are still added from invoices. The standalone Payments and Revenues pages are gone.
- Income: added a Category column and filter, carried through to the exports.
- Income: added bulk actions for account, payment method and category.
- New Transactions page — a per-account ledger of transfers, revenues, payments and expenses with filtering by type, category, client, payment method, amount range and date, a running balance, summary cards, account balances in the account picker, and CSV export.
- Products: added a basic product import via CSV.
- Added user based RBAC for API keys, so a key runs as a user and inherits that user's permissions.
- Added deny rules to client access permissions, so access can be granted broadly and revoked for specific clients.
- New secure file download handler for files and ticket attachments, with client and contact permission isolation on the client portal.
- Invoices: clicking the Paid or Partial status badge opens a read-only breakdown of the payments recorded against that invoice.
- Assets: multiple notes per asset, same as contact notes, with categorized note types (Maintenance, Repair, Configuration, Upgrade, Inspection, Note).
- Reworked the Maintenance > Update page, and fixed the branch handling on it.
- Categories and tag types moved from a top button nav to a left side nav.
- Dashboard: added expiring asset warranties and licenses, along with an "Expiring in" filter for assets, licenses, domains and certificates.
- Added bulk and single refresh actions for domains and certificates.
- Stripe gateway fees now come from the actual Stripe balance transaction rather than a static percentage and flat fee configured in ITFlow, with a nightly pass to backfill fees that were not available at payment time. The static fee fields are gone from payment provider settings.
- Database updates are now split into per-version files under `admin/database_updates/`, the latest version is derived from the directory listing, and one run applies everything pending. Migration history before 2.0.0 has been pruned.
- Reorganized the main, client, admin and reports side navigation menus.
- Mail settings tabs are now URL addressable and stay on the active tab after saving.
- Removed the legacy vendor contacts feature.
- `dig` and `whois` are no longer required, domain lookups use native DNS and RDAP.
- Tightened the `.htaccess` rules, and added one for `uploads/tmp`.
- Bumped the minimum supported PHP version.
### Security
 
- Rate limited 2FA code attempts and narrowed the TOTP acceptance window.
- Rotate the session ID on login to prevent session fixation.
- Stopped parallel login attempts from bypassing the login rate limits.
- Tightened validation on the recurring invoice frequency used by the billing run.
- Admin UI modals are now gated to admins. Previously any logged-in user could open them directly and read stored payment provider and AI provider API keys — rotate those keys when you update, as there is no record of who may have viewed them.
- Global search returned credentials to users without credential module access. It is now gated like every other credential surface.
- Credential password reveals are now written to the audit log, on both the reveal endpoint and the TOTP code.
- Swept module and client permission enforcement across modals and ajax endpoints to match the post handlers, closing a number of cases where a user restricted to certain clients could read another client's records by ID.
- Products: the CSV export now requires sales module read access.
- Neutralized CSV formula injection in generated exports.
- Fixed weak random number generation in TOTP secret generation.
- Shared item views are now claimed atomically so the view limit cannot be exceeded by simultaneous requests, and guest audit IPs are logged.
- Hardened CSRF handling and session cookies, and set `SameSite=Lax` on the session cookie.
- Hardened file upload handling to use random storage names.
- Client Portal: contacts can no longer edit their own contact record.
- The setup wizard's restore step is now closed on any install that has users, whatever `config.php` says. Previously, if `$config_enable_setup` was missing from `config.php` — a state an install can be left in when setup does not reach its final step — the restore step stayed reachable. Restoring over a live install is now done from the command line.
- A restore no longer takes ITFlow's `uploads/.htaccess` from the archive. The guards are rewritten afterwards regardless of what the backup contained, so restoring a backup taken before those guards existed no longer removes them.
- Tightened the directory guards under `uploads`.
### Bug Fixes
 
- Deleting a payment now correctly recalculates and sets the invoice status.
- Fixed contact notes, and several broken modal links in contacts, assets and file linking.
- Client Portal: fixed adding saved payment methods and cards following a Stripe API change.
- Fixed sending invoices and quotes over OAUTH2, which was reading the SMTP host instead of the SMTP provider — the host is not filled in when OAUTH2 is selected.
- Mail Parser: correctly work out whether the ITFlow folder belongs under the `INBOX` namespace or the root directory, fixing folder creation on cPanel Dovecot Maildir++ setups.
- Fixed possible duplicate emails caused by a race condition in the mail queue.
- Added a shared lock guard across every cron entry point, scoped per script and per install, replacing the mail queue's non-atomic lock file. Rows left in a sending state by a run that died are now recovered.
- Prevented duplicate Stripe payment bookings and overlapping cron runs.
- Mail bodies are cleared after successful delivery.
- Reworked `getFieldById` to stop escaping its return value, and reworked every caller to escape at the point of use — it was causing double escaping in a lot of places.
- Added missing `maxlength` attributes to forms backed by length-limited columns, so an overlong value no longer throws a 500.
- Fixed undefined variables in the audit log and flash messages for expenses, assets, contacts and several other handlers, which were logging blanks in place of the record name.
- Fixed the spelling of the expense description in audit logging.
- Fixed autofill on invoices, quotes and recurring invoices, where the tax field was not updating and a dash was being placed in front of the product.
- Fixed gaps in ticket history.
- Recurring Expense: fixed editing not keeping the client.
- Fixed client name truncation in the side navigation being applied after escaping.
- Side navigation counts are only shown to users with permission to see them.
- Invoice statistics now only reflect clients the user has permission to see.
- The agent category handler no longer drops the category description.
- Expenses: allowed negative amounts, and the current date is now prefilled.
- Certificates can now be searched by description.
- Fixed cents calculation rounding.
- Fixed guest view credential TOTP display, and removed the legacy OTP code path.
- Gated the SLA option in ticket details, which was gated everywhere else.
- Deleting a ticket template task or a payment provider recorded the wrong name in the audit log and the confirmation message, reading an unrelated record's id in place of the name.
- Bulk-creating tickets from a template against multiple assets only added the template's tasks to the first ticket, and dropped each task's completion estimate.
- Deleting a ticket template now unlinks it from any recurring ticket that referenced it, instead of leaving the schedule pointing at a template that no longer exists.
- Fixed the asset section in the recurring ticket modal when opened outside a client, and project selection when raising a ticket.
- Tickets raised by the nightly recurring schedule were created without a guest URL key, so the "View ticket" link in reply and task approval emails could not be opened. Cron now generates a key like every other path that raises a ticket, and existing tickets missing one are backfilled by the database update.
- Calendar: fixed the last day of a multi-day all-day event not being drawn or published to subscribed feeds. `event_end` holds the last day the event covers, which is what the event modal asks for, but FullCalendar and iCalendar both treat an all-day end as exclusive.
- Fixed `confirm-link` doing nothing inside an ajax modal, where the handler was only bound to links present at page load.
### Developer Updates
 
- Line endings normalized to LF across the codebase, with `.gitattributes` and `.editorconfig` added. Vendored code under `libs/` is marked so it stays byte identical to upstream.
- Converted to the short echo tag `<?=` throughout.
- `functions.php` is now a loader, with helpers split into topical files under `functions/`. Unused legacy functions removed, including an unused database wrapper layer.
- PHP functions renamed to camelCase throughout, including `nullable_htmlentities` to `escapeHtml`, `sanitizeInput` to `escapeSql`, `logAction` to `logAudit`, and `key32gen` to `generateTotpSecret`.
- Seed data is now shared between the setup wizard and `setup_cli.php` from one file, so a headless install gets the same starter content as a browser install.
- `CONTRIBUTING.md` added and expanded, covering the security rules, style conventions, database column prefix convention and migration pairing.
### Library Updates
 
- Bump TinyMCE from 8.6.0 to 8.8.2.
- Bump DataTables from 2.3.7 to 3.0.1.
- Bump FullCalendar from 7.0.0 to 7.0.2.
- Bump ImapEngine from 1.25.0 to 1.25.4, along with its dependencies — notably zbateson/mail-mime-parser 3.0.6 to 4.0.3 and guzzlehttp/psr7 2.12.3 to 3.0.0.


## [26.07.1]

### Bug fixes
- Fixed broken M365 and Google Workspaces OAUTH2 in Mail Settings.
- Security Fix.


## [26.07]

### Major Changes
- Migrated from Webklex php Library to IMAPEngine.
- Major Rewrite of the mail settings page to better support Microsoft 365 and Google OAUTH2.

### Bug fixes
- Many Security Fixes.
- Microsoft 365 and Google can now specify Licensed User.
- Clients - Only show 3 Tags per line instead of streching all the way across.
- Login: Make Email field email type instead of text.
- Fixed Invoice Late Overdue notices now shows correct balance when late fees are attached and if partial invoice was paid.
- User Preferences Avatar: Fix creating user upload directory if doesn't exist, and remove Avatar now properly deletes the old avatar image.
- Do not send an in-app alert on successful cron execution, keep it in logging only.
- Fix Issues with Clients Signing in via Entra in the client Portal, items were broken because CSRF token was not being generated.

### New Features & Updates
- Clients: Removed Entity Stat Counter was slow and unused.
- Added Monospace text in areas where it deserves it like, IPs, Amounts Costs in Tabular data forms etc.
- Cicking into the client section no longer turns the main nav and text gray, it keeps the configured theme across the app. It was implmented long ago to differentiate between the client section and the main section of ITFLow but didn't work very well. 
- Bump Sortablejs from 1.15.6 to 1.15.7.
- Bump TinyMCE from 8.5.0 to 8.6.0.
- Bump Fullcalendar from 6.1.20 to 7.0.0 amd convert existing code to comply with 7.0, also make calendar more printable.
- Bump PHPMailer from 7.0.2 to 7.1.1.

## [26.05.1] Stable Release
- Security Fixes.

## [26.05] Stable Release
### Bug Fixes
- Stripe Payment: Fix adding saved cards on client portal.
- Various client and module enforments fixes. 
- Projects: Fix slow load by using an optimized query to count tickets and tasks.
- Show correct currency for the account balance when adding payment to invoice.
- Expire all Password reset tokens nightly with cron.
- Shared Items via secure link: Do not delete shared items that have not been viewed before cron runs.
- Client: Fix Client Abbreviation being converted to an int on edit.

### New Features & Updates 
- Bump TinyMCE from 8.4.0 to 8.5.0.
- Bump TCPDF from 6.11.2 to 6.11.3.
- DeBump stripe-php from 20.0.0 to 19.4.1.

## [26.04] Stable Release
### Bug Fixes
- Racks: Fix Device Removal.
- Table Lists: replace class table-responsive-sm with just table-reponsive was causing ui issues with certain screen sizes.
- Client: Fix Edit erroring on certain characters.
- Category: Fix Add/Edit due to missing CSRF fields.
- Category: Fix Restore function and Icon and text color.
- Invoice: Do not apply late fee on first overdue reminder (1 day).
- Ticket: Fix issue with contact not being added with Add contact modal v1.
- Quote: Fix Copy was missing client.
- API: Don't set client ID from POST - this is properly done via require_post_method instead only if it's an all-clients key.
- API: Prevent error 500s when existing data can't be cleanly re-inserted to database.
- API: Add more helpful errors.
- API: Fix asset read uri_2 field.
- API: Various other field fixes.

### New Features & Updates 
- Categories: Add Description Field.
- Categories: Add DB Field for order.
- Categories: Move Asset Status and Network Interface Type to categories so custom ones can be created and edited.
- Categories: Moved note type, software type, rack type to be creatable/editable Categories with common defaults and descriptions
- Files: Allow .swb file for MikroTik Backup Files.
- Software: Added additonal License Types including Perpetual, Site, etc.
- API: Invoice Items: Add read endpoint.
- Networks: Added Import.
- Bump TinyMCE from 8.3.2 to 8.4.0.
- Bump stripe-php from 19.4.1 to 20.0.0.

## [26.03] Stable Release
### Bug Fixes
- Ticket Templates: Fix Task Sortinhahahg.
- Ticket: Lower autoclose setting minimum value from 48 to 24 Hours.
- Ticket: Fix Task Approval.
- Recurring Ticket: add empty value placeholder for Ticket Frequency.
- Documents/Files: Fix redirect after File Upload to redirect to files instead of the non existent documents.
- Setup: Fix base url tacking on /setup when not installing via script.

### New Features & Updates 
- Clients: Net Terms: Added common 45 and 15 Days, removed 14 Days not as common.
- Clients: Bulk Action Set Net Terms Added.
- Clients: Swapped location and contact column, add PopOver with Details such as created, abbreviation, DB ID instead of taking up space underneath client, rounded tag pills and increased padding, removed info badges and added one info badge that displays a popover with details.
- Clients: Added New Ticket to Client Top Header Menu.
- Clients: Client Overview: UI Sprucing.
- Invoice: Send reminder 1 day after due date.
- Invoices/Quotes/Recurring Invoices: Split Items tables into their own POST logic and Modal UIs and tables (quote_items, invoice_items, recurring_items).
- Tickets: New Ticket Parsing - Anyone CC'ed onto the original email that created the ticket is added as a ticket watcher.
- Ticket/Quotes: Quotes can now be associated with a ticket.
- Networks: Removed Subnet Mask Field, Use CIDR instead.
- Networks: Rearranged fields, Updated placeholders, Add/Edit/list for better flow.
- Networks: Renamed DHCP to IP Range to allow for you use of both DHCP and or Usable IPs.
- Assets: Rearranged fields, Updated placeholders, Add/Edit/list for better flow.
- Assets: Added IPv6 if available under IP, Make and Model are now one line with Serial Underneath. Added OS under Type. use pill for status.
- Calendar: Event thats are cut off can now be viewed as a tooltip on hover.
- Calendar: Renamed System Calendars to built-in calendars and added the names and color dot for reference.
- Calendar: You can now delete a custom calendar.
- Report: Client Ticket Time Detail Audit: Selectable Billing Time Increment, will later be avauilable globally.
- Roles/Permissions: Now complete and is out of beta all permission roles are strictly enforced, except for in Trips and Calendar, new enforce modules will be added for these at a later date.
- Project Templates: Ticket Template order can now be dragged and dropped.
- Global: Introduced new checkbox class to all Checkbox select columns to keep consistency and reduce space and enhance ui.
- Global: CSRF Checks everywhere instead of just deletion calls.
- Global: Renamed the rest of the unarchive post and label calls to restore.
- Files: Allow upload of .unifi extension.
- Bump Libraries:
  - stripe-php from 19.0.0 to 19.4.1.
  - fullcalendar from 6.1.19 to 6.1.20
  - TCPDF from 6.10.1 to 6.11.2

## [26.02.1] Maint Release
### Bug Fixes
- Credentials: Fix Password Generator.
- Calendar: Restrict Events for client restricted agents. 
- Ticket Merge: Fix.
- Asset Transfer: Fix.
- Ticket Listing: Restrict Tickets presented in ticket list view from client restricted agents.
- Ticket Details: Deny access to client restricted agents to view tickets without client_id in uri.
- Tickets: Allow agents with restricted client access to view and edit tickets without a client.
- Ticket Change client: Limit selection for agents with restricted client access.
- Ticket Details: Don't display updated at when null.

### New Features & Updates 
- Report: Added Client Detail Auditing.
- API: Added Endpoint to retrieve time worked by agent.
- ajax-modal: Revert to previous JS implementation before 26.02 release.
- Ticket: Move Subject from Ticket main ticket header to ticket details card header.

## [26.02] Stable Release
### Bug Fixes
- Mail Parser - Do not automatically send new ticket notifications to noreply/donotreply addresses.
- Ticket: removed newline \n on Parsed emails.
- Show Trips for everyone if accounting module is enabled.
- Fix Invoice Exporting.
- Fix Billable Column not sorting correctly in tickets.
- Fix Login flow where user agent and client user exists and agent has MFA but will not let them continue.
- Fix passing missing user_id var in client portal.
- Fix Ticket Templates not auto filling when selected.
- Fix Invoices not being sent to all billings contacts when manaully sent.
- Fix Documents and Files not able to be bulk deleted.
- Fix Role Archiving, can be archived as long as no users are assigned to the role.
- Fix showing Powered By ITFlow visibility on the login screen when Whitelabel is enabled.
- Missing username in audit log on successful login due to missing passed user_id to logging.
- API: Fix updating all documents instead of the intended document.
- Documents: Fix Document created at not showing the correct creation date of the master document.
- Ticket: Fixed Using edit ticket modal agent was not able to be set.
- Always check if a user is archived and or disabled instead of just during login.
- Report: Fix Collected tax report not totalling all tax categories.

### New Features & Updates 
- Task Approval System for ticket tasks: Once an approval is requested, the task cannot be marked as complete until approved. Internal Approvals Any other technician, or Specific technician, Client Approvals Anyone (usually the requestor) Tech contacts Billing contacts.
- Printable Invoice Packing Slips now available.
- Drastic Performance Bump: Up to 50% faster queries accross the board and reduced server memory usage by 40% by switching Database Query method from mysqli_fetch_array to mysqli_fetch_assoc.
- Added Connect to Microsoft 365 Button to mail settings.
- OAUTH2 support for Microsoft 365 and Google Workspaces is now considered stable and working.
- Favorites: Assets and Credentials now can be favorited singly or by Bulk action. Favorited items appear in the client overview now.
- Files/Documents: Collapsable folders feature, collapsed by default with a button to expand all.
- URL Keys and such are now set to a more manageable 32 Characters by default.
- Various UI/UX Updates throughout the app, with focus oin ticket details, contact details modal etc.
- Added Show Archived files and documents to the files section.
- Added Bulk Archive and restore options to files and documents.
- Rewrite of the Kanban Ticket view to match our procedural style of coding.
- All options are available in TinyMCE now in Mobile mode.
- Agent names appear now in Invoice History section.
- Mail Parser: Support flowed text.
- Assets: Keep Purchase reference when copying.
- Assets: Add basic tracking history: Archiving, restoring, name changes, transferimg to new clients.
- Mail Parser: NDR Parsing.
- Allow SVG files in mail attachments.
- Tickets: Use a more friendly time worked instead of 02:41:00 translates to 2h 41m.
- Update wording on ticket to invoice item details.
- Merge Tickets: Now wth a ticket merge dropdown list of tickets instead of a text field.
- Role Permissions can now be set during role creation, update Permission UI to use radio buttons instead of select boxes.
- Bump TinyMCE 8.2.2 to 8.3.2.
- Bump PHPMailer from 7.0.1 to 7.0.2.
- Bump Datatables from 2.3.4 to 2.3.7.

## [25.12.1] Maint Release

### Major Changes
- Unified the Client/Agent Login and process (Note only Client Users can Reset passwords from the login page, does not apply to agent users).

### Bug Fixes
- Fix Payment Provider not adding an account.
- Fix New ticket button in contact details in the related tickets section.

### New Features & Updates
- You can now Set Payment Provider income/expense account, expense vendor and expense category upond creation or editing.
- Moved Saved Payment Provider Methods away from admin side nav to the count link within Payment Providers page.
- Moved AI Models from the admin side nav to the model count link within AI Providers.
- Add Favicon Reset.

## [25.12] Stable Release

### Breaking Changes ###
- For Existing installs: **php-xml** extension needs to be installed for document creation and editing, new install script does this for you as of Dec 6th 2025. To install php-xml: `sudo apt install php-xml`

### Major Changes
- Consolidated "Files" and "Documents" into a single section called **Files**.

### Bug Fixes
- Resolved issue with updating asset notes in asset details.
- Fixed problem with bulk ticket merging.
- Corrected issue where decimal inputs (e.g., price, cost) weren’t displaying on iPhones in certain forms.
- Added CSV escaping to the sample export data in areas where a sample CSV template is provided.
- Fix a race condition where dupe tickets, invoices, recurring invoices, recurring tickets, quotes will be created using the same number if created in parallel espcecially when using the API.

### New Features & Updates
- Introduced automatic subject-based ticket merging/reply detection. Now, if an email comes from a known contact or domain and the subject matches 95% of a ticket opened in the last 7 days, it will be merged automatically.
- Added `cleanInput` function to sanitize data before inserting it into the database when using MySQLi prepared statements.
- Migrated client post functionality to use MySQLi prepared statements.
- Updated payment method post functionality to use MySQLi prepared statements.
- Implemented `saveBase64Images()` to convert base64-encoded `<img>` tags into actual image files stored under `/uploads/<module>/<id>/` with secure filenames. Added wrapper functions, and updated document creation to use processed image paths.
- For new documents and document templates, images are now stored in `/uploads/documents/$document_id` instead of being stored as base64 in the database, using the `saveBase64Images()` function.
- UI/UX improvements made to the document details page.
- Removed sidebar quick-add options.
- Created new folders in the uploads directory: `documents`, `document_templates`, and `recurring_tickets`.
- Reworked the bulk action function to pass the name arrays, instead of a generic `selected_ids` array. This allows multiple bulk name arrays to be passed at once, currently used for the new file-document merge.
- Big task: Converted the remaining modals to use the new `ajax-modal` system, enabling more flexible flow expansion going forward.
- Mail queue: Added a `--no-mx-validation` flag to bypass recipient domain MX validation.
- Bump PHPMailer from 7.0.0 to 7.0.1.
- Bump stripe-php from 18.1.0 to 19.0.0.
- Bump TCPDF from 6.10.0 to 6.10.1.
- Bump TinyMCE from 8.2.0 to 8.2.2.

## [25.11.1] Maint Release

### Fixes
- Fix broken edit Payment Method.
- Fix unable to delete Vendor Template.
- Fix Mail Queue link in flash alert for testing email and sending a quote.
- Add Show Category Type select if not defined.
- Add Show Product Type select if not defined.
- Fix add ticket watcher.
- Fix if Client isn't assigned to a ticket dont show client view.
- Fix missing session client id check when paying an invoice from client portal.
- Update Composer Webklex-IMAP library dependency symfony/http-foundation from 7.3.3 to 7.3.7 to fix security related issues.
- Add back delete Payment provider the database will handle cascade deletes to saved cards, recurring payments and client payment provider reference.
- Don't show Client Tickets Breadcrumb if no client is assigned to a ticket.
- Don't Show Contact or Assignment Tab in edit ticket if no Client is Assigned.
- Don't Show add contact, asset, vendor, watcher if not client is assigned to a ticket.
- Don't Show Public Comment & Email if contact email doesn't exist.
- Fixed IMAP Test whicn now uses RAW TCP Connection instead of the depracated php-imap extension.
- Fix Broken Link in Ticket Updates via Client Portal to agent.

### Added / Changed
- [Feature] Added Asset Tags.
- [Feature] Added Quick Add Links to most side bar navs example quickly add a client from sidebar.
- Migrate ticket template add to ajax modal.
- Add TOTP secret to Client Export PDF in Credential section.
- Add UserID on hover in users listing.
- Merge ticket now redirects to the new ticket details page.
- [Feature] Add Pay via saved card under invoice Listings.
- Ticket Related Side Items UI Cleanup to use btn-tool class. 

## [25.11] Stable

### Deprecation Notice:
- **Outdated CRON Scripts**: The following scripts are removed.
  - `/scripts/cron_mail_queue.php`
  - `/scripts/cron_ticket_email_parser.php`
  - `/scripts/cron.php`
  - `/scripts/cron_domain_refresher.php`
  - `/scripts/cron_certificate_refresher.php`
  
  **Action Required**: Transition to the new versions:
  - `/cron/mail_queue.php`
  - `/cron/ticket_email_parser.php`
  - `/cron/cron.php`
  - `/cron/domain_refresher.php`
  - `/cron/certificate_refresher.php`

- PHP Extensions php-imap and php-mime-mail-parser are no longer required.
---

### Fixes
- **Ticket Listing**: Resolved issue where the “Check All” checkbox was visible even when ticket status wasn’t set. Now hidden for closed tickets only.
- **Timer Auto-Start**: Show H/M/S placeholders when timer auto-start is disabled.
- **Ticket Guest URL**: Fixed email not including the ticket guest URL key.
- **EML Generation**: Resolved issue with EML not being generated in the new ticket parser.
- **New Ticket Mail Notification**: Included message when notifying the tech of a reply in the new ticket mail parser.
- **Advanced Filter Collapse**: Added clause to prevent collapse of advanced filters when the “from” date is set to the default (1970-01-01).
- **Recurring Invoice**: Fixed issue where email was marked as sent but not actually sent when forcing a recurring invoice to an invoice.
- **CSRF Token**: Fixed issue with deleting recurring ticket from asset details page due to missing CSRF check token.
- **Vendor Website Link**: Fixed missing `https://` prefix in the vendor website link on the vendor details modal.
- **Agent Select Box**: Resolved issue where agents sometimes didn’t appear in the agent select boxes.
- **TinyMCE**: Fixed TinyMCE editor issue on Bulk Create Ticket in Assets.
- **Ticket Timer**: Fixed ticket timer initialization after reload and when the tab is put to sleep (background tab).
- **Client Deletion**: Fixed issue with client deletion.
- **Domain Records**: Added flag for missing SOA record when adding a domain (prevents subdomain creation).
- **Domain Fetching**: Quits domain record fetching if no SOA record exists (prevents subdomains).
- **Domain Expiry**: Only show time to expiry when there’s an expiry date set; otherwise, display a dash.
- **Certificates**: Improved handling of empty date in the agent UI.
- **Certificates API**: Fixed bug with missing JS to fetch certificate details.
- **API Updates**:
  - Clients API: Added support for archiving/un-archiving clients, updating client data, and abbreviation support.
  - Contacts API: Added archiving/un-archiving and restriction to only allow one primary contact per client.
  - Mail Queue: Added recipient domain MX validation before sending emails.

---

### Added / Changed
- **Backup / Restore**: Improved backup and restore by streaming data to disk (to prevent memory issues), setting unlimited timeouts, checking for bad backup contents, and using PHP for DB import instead of shell exec. Added `.htaccess` to prevent PHP execution in `/uploads/` directory.
- **Ajax Modals**: Migrated all Add and Bulk modals to the new Ajax Modal for improved performance.
- **Recurring Ticket Sorting**: Default sorting of recurring tickets by `RunDate` instead of subject.
- **Recurring Ticket Enhancements**:
  - Added Billable column.
  - Added bulk actions for setting priority, agent, billable status, and next run dates.
  - Added filters for category, assigned agent, and billable status.
  - Added new frequency options: 3-day and biweekly.
- **Asset Select**: Updated asset select dropdown to separate asset types using opt groups (planned for wider use).
- **Expiring Domains & Certificates**: Added "30 Day" warning for expiring domains and certificates in the dashboard.
- **Ticket Search**: Allowed search using both ticket prefix and number.
- **Recurring Invoice**: Cancel recurring invoices when the associated client is archived.
- **Credentials Import/Export**: Now includes TOTP secrets when importing/exporting credentials.
- **Asset Notes Import**: Allowed importing of asset notes.
- **Ticket View**: Added a "View HTML Code" button in all ticket views for TinyMCE.
- **Date Range Picker**: Updated all date filters to use the improved DateRangePicker JS.
- **Bulk Ticket Creation**: Added bulk ticket creation for clients.
- **Sidebar Updates**: Updated all sidebars to use absolute paths for easier integration with custom code.
- **Document Actions**: Added Archive and Delete buttons to the Document Details view with improved redirect behavior.
- **Ticket Template Sorting**: Allowed sorting by task count in ticket templates.
- **Contact Modal UI**: Updated contact details modal to display contact information at the top.
- **API & Code Updates**: 
  - Separated out post files for recurring tickets, invoices, expenses, and payments.
  - Removed unused budget code.
- **Invoice Product Autocomplete**: Now allows searching for product codes as well as names.
- **Client Duplicate Check**: Flags duplicate clients or leads when using the client add modal.
- **Recurring Invoice Reference**: Added a column to invoices indicating if they were created from a recurring invoice.
- **Global Search Enhancements**: 
  - Allowed ticket details to be searchable in global search.
  - Allowed searching for quotes in global search.
- **UI/UX Improvements**:
  - Spruced up the ticket details page UI.
  - Added contact email validation to flag duplicates or invalid addresses.
- **API Debugging**: Log API endpoint/URL path for authentication failures to aid in debugging.
- **Image Upload Optimization**: Removed image optimization from uploads (this will be handled by a cron job in the future).
- **View Behavior Change**: Updated ticket/invoice/quote views to always be in the Client section, showing client-side navigation and top info bar.

---

### Library Updates:
- **DataTable**: Bumped from 2.3.3 to 2.3.4.
- **TinyMCE**: Bumped from 8.0.2 to 8.2.0.
- **Stripe-PHP**: Bumped from 17.6.0 to 18.1.0.
- **PHPMailer**: Bumped from 6.10.0 to 7.0.0.
- **Chart.js**: Bumped from 4.5.0 to 4.5.1.




## [25.10.1]
- Deprecation Notice: `/scripts/cron_mail_queue.php` , `/scripts/cron_ticket_email_parser.php` , `/scripts/cron.php` `/scripts/cron_domain_refresher.php`, `/scripts/cron_certificate_refresher.php` are being phased out. Please transition to `/cron/mail_queue.php` , `/cron/ticket_email_parser.php`, `/cron/cron.php`, `/cron/domain_refresher.php`, `/cron/certificate_refresher.php` These older scripts will be removed in the November 25.11 release—update accordingly. 25.10.1 installs have the script already configured.

### Fixes
- Fix regression missing custom Favicon.
- Update SMTP and IMAP provider to allow for empty strings, empty means disabled.
- Fix Client portal Microsoft SSO Logins.
- Fix regression in Vendor Templates.
- Fix refression in some broken links from user to agent.
- Fix Project edit.
- Prevent open redirects upon agent login.
- Fix regression on switching to Webklex IMAP to allow for no SSL/TLS in IMAP.
- Fix Setup Redirect not behaving properly when setup hasnt been performed.
- Added Server Document Root Var to several includes, headers, footers files to allow includes from deeper directory strutures such as the new custom directories.
- Fix edit contact in contact details.
- Add .htaccess to /cron/.

### Added / Changed
- Support for HTML Signatures.
- Add Edit Project Functionality in a ticket.
- Added more custom locations: /cron/custom/, /scripts/custom/, /api/v1/custom/, /setup/custom/.
- Copied `/scripts/cron.php` `/scripts/cron_domain_refresher.php`, `/scripts/cron_certificate_refresher.php` to `/cron/cron.php`, `/cron/domain_refresher.php`, `/cron/certificate_refresher.php`. See Above!
- Signatures is now handled in post ticket reply on Public Comments only.

## [25.10]

### Breaking Changes
- Renamed `/user/` directory to `/agent/`.
- Deprecation Notice: `/scripts/cron_mail_queue.php` and `/scripts/cron_ticket_email_parser.php` are being phased out. Please transition to `/cron/mail_queue.php` and `/cron/ticket_email_parser.php`. These older scripts will be removed in the November release—update accordingly. New Installs via the script will have this already configured.
- Custom is working now. Custom code should be placed in /admin/custom/ , /agent/custom/ , /client/custom/ /guest/custom/
We will provide example code with directory structure for each custom directory a week after this release.

### Fixes
- Resolved issue with "Restore from Setup" not functioning correctly.
- Corrected asset name display in logs and flash messages when editing an asset in a ticket.
- Fixed Payment Provider Threshold not being applied.
- Fixed issue where Threshold setting was not saving properly.
- Various minor fixes for Payment Provider issues.
- Removed leads from the client selection list in the "New Ticket" modal.
- Fixed issues with the MFA modal.
- Resolved MFA enforcement bugs.
- Fixed KeepAlive functionality to maintain user sessions longer.
- Fixed multiple broken links caused by the `/user/` to `/agent/` path migration.
- Fixed Custom code directories.

### Added / Changed
- Removed "ACH" as a payment method; added "Bank Transfer" instead.
- Replaced relative paths with absolute paths for web assets.
- Tickets can now be resolved via the API.
- Added a filter for Archived Users and an option to restore them.
- Introduced a modal when archiving users, allowing reassignment of open and recurring tickets to another agent.
- Improved logic for determining the index/root page.
- Added "Assigned Agent" column for recurring tickets.
- Introduced "Additional Assets" option when editing assets in tickets; modal now uses the updated AJAX method.
- Added Gibraltar to the list of supported countries.
- Added Custom Link Option for the Admin Nav.
- Added Custom Link Option for the Reports Nav.

### Other notes
- Major releases will happen on the first week of every Month.


## [25.09.2]

### Fixes
- Fix Payment Method Select box in Revenue.
- Remove Extra Feeback Wording When Invoice Sends.
- Updated all CSV exports to use escape parameters.
- Fix Missing First row on Asset interface export.
- Fix Edit User not working due to incorrect modal footer path.
- Fix Add Certificate breaking due spelling on function.
- Update all CSV Exports to include company name or client name depending on when its being exported from.
- Introduced new function sanitize_filename and implmented it in all exports.
- Spruced up UI/UX Saved Paymented section in Client Portal.
- Fix add Payment Link in client portal recurring invoice section.
- Better Logic handling for default page redirect.

### Features
- Introduced new Beta mail parser cron using webklex imap library instead of php-imap as this is deprecated --Not Enabled on existing installs, only new installs.
- Introduced Beta support for OAUTH2 Authentication for Microsoft 365 and Google Workspaces for both incoming ticket parsing and outgoing email but must use new mail parser and mail queue for this to work, and requires changing the cron jobs: scripts/cron_mail_queue.php to cron/mail_queue.php and scripts/cron_ticket_email_parser.php to cron/ticket_email_parser.php.

---

## [25.09.1]

### Fixes
- **Web Installer**: Resolved issue with broken installer caused by incorrect database schema file name.
- Hide the "Add Credit" button as the feature is not fully implemented yet.
- Corrected long invoice/quote notes that were overlapping with the footer in PDF exports.
- Fixed AI settings not appearing in the Admin Menu when the Billing module was disabled.
- Enabled wrapping of client tags when they are too long.
- Fixed an issue where AI was not functioning correctly.
- Removed extra spacing between the contact name and icon in the Ticket Details contact card.

### Features
- Redesigned **AI Ticket Summary**, now divided into 3 sections: Main Issue, Actions Taken, and Resolution/Next Steps.
- Updated the **AI Ticket Summary** prompt to include ticket status, reply author, source, category, and priority.

---

## [25.09]

***BACK UP*** before updating.

---

### Breaking Changes and Notes
- We strongly recommend updating from the command line, however if performed via the webui and after performed it will return a 404. thats normal as the directory structure has changed, just close your browser then log back in then go back to update to perform the many database updates. 
- This is a major release with significant changes. While the community has done a great job identifying bugs, some may still remain — continued testing is encouraged.
- All AI settings will be **reset** and must be reconfigured using the new AI provider backend.
- The `xcustom` directory has been renamed to `custom`. All custom libraries and post-processing scripts should now be placed here.

---

### Added / Changed
- Numerous UI improvements and refinements across the application.
- Enhanced visual clarity by thickening the left border on ticket comments to help identify comment types.
- Ticket details UI redesigned to use less space at the top of the screen.
- Introduced tracking for the **first response date/time** on tickets.
- New reporting feature: **Average time to first response** on tickets.
- Stripe integration rebuilt using the new **payment provider backend**.
- Clients can now save and manage **multiple payment methods**.
- Support for selecting saved cards for **recurring invoices** in both the client and agent portals.
- Initial database structure and logic added for **credit management** (feature not yet enabled).
- Major **backend directory restructuring**.
- Introduced **stock/inventory management**, including a stock ledger backend.
- Stock quantities now update automatically when invoice items are added or removed.
- Invoice autocomplete now includes: **name, description, price, tax, stock levels**, and links `product_id` to `item_id`.
- Added a **category filter** to invoices.
- Linked stock to related expenses.
- New product fields: **location, code, and type**.
- Products now separated into two types: **Service** and **Product**.
- **Dark mode** introduced.
- Projects: Now support linking **closed tickets**.
- Clients: Added bulk actions for tags, referral source, industry, hourly rate, email, archive, and restore.
- Invoices: Bulk action added to **assign categories**.
- Assets: New `client_uri` field, visible in both the agent and client portals.
- Client Portal: Clients can now **select an asset** during ticket creation.
- Client Portal: Company logo now **displays in the header**.
- Client Portal: Dashboard cards are now **clickable** for more detail.
- Assets: Option added to include **MAC Address** in additional columns.
- Asset Interface: Bulk actions added — set DHCP, network type, and delete.
- API:
  - Added `/location` endpoint.
  - Ticket content now supports **HTML formatting**.
- New option to filter and display **500 records per page** in the footer.
- Payment methods are now treated as a **separate entity** instead of being grouped under categories.
- Updated libraries:
  - **TinyMCE**
  - **Chart.js** (major upgrade)
  - **DataTables**
  - **Bootstrap**
  - **FullCalendar**
  - **php-stripe**

---

### Fixed
- Several security vulnerabilities patched (with thanks to www.helx.io).
- Ticket status is no longer updated when scheduling.
- Client Portal: Tech contacts can no longer edit their own details.
- Fixed overlapping logo issue in Invoice/Quote PDF exports.
- Refactored `check_login.php` into multiple files for modular login functionality.
- Removed redundant logging comments for redirects.
- Renamed `get_settings.php` to `load_global_settings.php`.
- Simplified syntax for `ajax-modal` and updated usage throughout the app.
- Fixed issue where primary contact text wasn’t displaying.
- Corrected client **Net Terms** display.
- Fixed logic for recurring expense **next run date**.
- Resolved broken **IMAP test button**.
- Archived clients can no longer log into the portal.
- Searching closed tickets no longer reverts to open tickets.
- Fixed project search filter not showing completed projects.
- Fixed issue where company logo was not being removed correctly.
- Resolved API bugs:
  - Default rate and net terms.
  - Contact location.
  - Document endpoint.

---

### Developer Updates
- Replaced legacy code with newer functions like `redirect()`, `getFieldById()`, and `flash_alert()`.
- Significantly improved performance of queries used for filter selection boxes.


## [25.06.1]

### Fixed
- Fixed a regression in setup causing it to crash and never complete, due to missing default for currency.

## [25.06]

### Breaking CHANGES
- Old Document Verions will be deleted due to the major backend rewrite how document versions work.

### Added / Changed
- Improved function for retrieving remote IP address for logging purposes.
- Ticket categories are now sorted alphabetically.
- Visiting a deleted invoice or recurring invoice now redirects to the listing page; delete option added to invoice details page.
- Added "Mark as Sent" and "Make Payment" actions directly on the invoice listing page.
- Introduced Ticket Category UI for recurring tickets.
- In Project Details, bulk actions and sorting are now available for tickets.
- Updated ticket details UI to use full card stacks with edit icons for stackable items (e.g., asset, watchers, contact).
- Added a new setting to toggle AutoStart Timer in ticket details (disabled by default).
- Applied gray accent theme in the client section to visually distinguish from the global view.
- Introduced Ticket Due Date functionality (currently supports add/edit only; more updates coming next release).
- Added settings option to display Company Tax ID on invoices.
- Client overview now displays badge counts for all entities.
- Overhauled UI for Invoice, Quote, and Recurring Invoice details; switched PDF generation to TCPDF PHP from PDFMake JS.
- Document versioning has been moved to a separate backend table to resolve permanent link issues -- SEE Breaking CHANGES.
- Migrated Document Templates, Vendor Templates, and Software/License Templates to dedicated tables.
- Added functionality to mark all tasks in a ticket as complete or incomplete.
- Asset CSV import now supports a purchase date field.
- Recurring Payments have been restructured to auto-charge on the invoice due date instead of at generation time.
- Added "Base Template" label for vendor templates when available.
- Backup and restore processes now use a temporary directory; files are cleaned up automatically if operations fail.
- Added confirmation prompt when accepting or declining a quote.
- Other minor code UI/UX cleanups and refactoring throughout the app.

### Fixed
- Resolved issue with enabling MFA.
- Fixed UI regression where ticket listing columns would misalign.
- Non-billable invoices are no longer included in calculations.
- Addressed multiple minor reported security vulnerabilities.
- Tickets with open tasks are no longer resolved in bulk; a warning is shown along with a count of affected tickets.


## [25.05.1]

### Added / Changed
- Added Domain Expiring Card to Client Portal Dashboard for Primary and Technical Users.
- Added Balance and Monthly Recurring Amount to Client Portal Dahboard for Primary and Technical Users.
- Added Archive Searching to network and certificates also added unarchive capabilities to them as well.

### Fixed
- Add Payment not showing in Invoice.
- Updated Client Overview Entities to not show archived client's Entities even though the entity may not be archived.


## [25.05]

### Added / Changed
- Expanded file upload allow-list to include .bat and .stk file types.
- Added full backup/restore functionality. Backup downloads a zip that includes the SQL dump and uploads folder, setup now has option to restore from zip backup.
- Migrated Asset and Contact Links to modals to resolve variable overlap issue.
- Added Pagination to Notification Modal.
- Removed 500 Records Per Page option.
- Removed unused old DB checks in the top nav.
- Clients can now use the portal to setup Stripe automatic payments themselves for recurring invoices
- Automatic payments are now disabled for all recurring invoices if the saved payment method is removed
- Added Card Details and Payment added to Client Stripe.
- UI / UX updates to guest pay Make use of cards.
- Don't show Checkbox columns when ticket is closed, compact ticket list now matches round pills for status and priority.
- Ticket UI/UX update allow the ticket toolbar to be a little more mobile-friendly
- UI / UX Updates to Expenses - Combine Category and Description into 1 column.
- Country information is now displayed in Invoices, Quotes, Recurring Invoices, Clients, Locations, and the client top header.
- Added country-based search filters in Locations and Clients sections.
- Changed the settings name from Integrations to Identity Providers to make room for future iDPs (e.g. Google).
- Bump FullCalendar from 6.1.15 to 6.1.17.
- Bump DataTables from 2.2.2 to 2.3.1.
- Bump TCPDF from 6.8.2 to 6.9.4.
- Bump tinyMCE from 7.7.1 to 7.9.0.
- Bump phpMailer from 6.9.2 to 6.10.0.
- Bump stripe-php from 16.4.0 to 17.2.1.


### Fixed
- "None" option for SMTP encryption now functions correctly.
- Debug table row counts now reflect actual counts instead of relying on SHOW TABLE STATUS.
- Archived Categories now display properly.
- Stripe saved payment methods are now limited to credit/debit cards only.

## [25.03.6]

### Fixed
- Set default to date to 2035-12-31 as 9999-12-31 and 2999-12-31 broke certain browsers.
- Update Client PDF Export, add header added company logo.
- Present Larger clearer Warning about updates on update page.
- Allow to search by project reference.

## [25.03.5]

### Fixed
- Fixed the user listing issue when copying a trip.
- Corrected the display of recurring invoice amounts on the dashboard.
- Fixed the linking of entities with assets and contacts.
- Resolved the issue with displaying the correct mobile country code in the contact listing.
- Set the default date to `9999-12-31` to ensure future items (like invoices) are displayed by default.
- Fixed the display issue where file folders were not showing properly during document creation.
- Migrated from Dragula to SortableJS for a more modern, mobile-friendly solution.
- Added Handlebars icons for drag-and-drop items.
- Changed behavior to open Contact and Asset Details pages directly instead of using a modal.

## [25.03.4]

### Fixed
- Ability to remove additional assets from the ticket details screen.
- Fix the ability to remove assets from edit ticket not working when only 1 asset exists.
- Fix Database Backup corruption.
- Client Portal - show ticket number instead of ticket id in ticket listing.
- Add Purchase Reference to copy asset.
- Add Link to asset details from the global search.
- Fix Bulk assign ticket only showing contacts instead of ITFlow users.


## [25.03.3]

### Fixed
- Fix adding ITFlow user.
- Do not alert on inactive recurring invoices.
- Fix ticket user assignment including bulk assignment.
- Fix adding a location phone extension.
- Do not default to +1 Country code, instead default to null.
- Do not format numbers unless a country code is entered.
- Fix editing network location.
- Fix ticket redaction on client replies.
- Remove more from user activity as it requires admin privledges.
- Fix MFA Enforcement page.

## [25.03.2]

### Fixed
- Revert DB.sql change

## [25.03.1]

### Fixed
- Phone number missing in various sections.
- Match Database.
- Client Export Only display licenses users and assets from the selected client only.

## [25.03]

### Fixed
- Resolved missing attachments in ticket replies processed via the email parser.
- Fixed issue where the top half of portrait image uploads appeared cut off at the bottom.
- Ensured all tables and fields use `CHARACTER SET utf8mb4` and `COLLATE utf8mb4_general_ci` for updates and new installations.
- Converted `service_domains` table to use InnoDB instead of MyISAM.
- Fixed the initials function to properly handle UTF-8 characters, preventing contact-related issues.
- Interfaces can now start with `0`.
- Adjusted AI prompt handling to focus solely on content, avoiding unnecessary additions.

### Added / Changed
- Introduced bulk delete functionality for assets.
- Added the ability to redact ticket replies after a ticket is closed.
- Added support for redacting specific text while a ticket is open.
- Switched file upload hashing from SHA256 to MD5 to significantly improve performance.
- Enabled assigning multiple assets to a single ticket.
- Updated all many-to-many tables to support cascading deletes using foreign key associations, improving efficiency, performance, and data integrity.
- Enabled caching for AJAX modals to reduce repeated reloads and enhance browser performance.
- Upgraded DataTables from 2.2.1 to 2.2.2.
- Upgraded TinyMCE from 7.6.1 to 7.7.1, providing a significant performance boost.
- Added “Copy Credentials to Clipboard” button in AJAX asset and contact views.
- Renamed and reorganized several tables.
- Improved theme color organization by grouping primary colors and their related shades.
- Displayed a user icon next to contacts who have user accounts.
- New image uploads are now converted to optimized `.webp` format by default; original files are no longer saved. Existing images remain unchanged.
- Added international phone number support throughout the system.
- Introduced user signatures in preferences, which are now appended to all ticket replies.
- Optimized search filters to only display defined tags.
- Added “Projects” to the client-side navigation.
- Enabled “Create New Ticket” from within project details.
- Reintroduced batch payment functionality in client invoices.
- Included client abbreviations in both client and global search options.
- Added assigned software license details (User/Asset) to the client PDF export.
- Replaced client-side `pdfMake` with the PHP-based `TCPDF` library for generating client export runbooks.
- Introduced the ability to download documents as PDFs.
- Added a “Reference” field to tickets and invoices generated from recurring templates (not yet in active use).

### Breaking Changes
> **Important:** To update to this version, you **must** run the following commands from the command line from the scripts directory:
>
> ```bash
> php update_cli.php
> php update_cli.php --db_update
> ```
>
> Repeat `--db_update` until no further updates are found.
>
> **Back up your system before upgrading.**  
> This version includes numerous backend changes critical for future development.

## [25.02.4]

### Fixed
- Resolved issue preventing the addition or editing of licenses when no vendor was selected.
- Fixed several undeclared variables in AJAX contact details.
- Corrected the contact ticket count display.
- Addressed an issue where clicking "More Details" in AJAX contact/asset details failed to include the `client_id` in the URL.
- Fixed an issue with recurring invoices in the client URL: clicking "Inactive" or "Active" would unexpectedly navigate away from the client section.
- Added new php function getFieldById() to return a record using just an id and sanitized as well.

## [25.02.3]

### Fixed
- Fixed notifications being reversed as dismissed notifications.

## [25.02.2]

### Fixed
- Corrected some edit modals not showing notes correctly.
- Bugfix: When exporting to CSV, the first asset wasn't being shown.
- Fix broken create / edit credentials.
- Fixed missing Notificatons link.
- Fixed a few dead links.
- Fixed Overdue count also counting Non-Billable Invoices.
- Fix Edit Client Notes.

### Added / Changed
- Implemented SSL certificate history tracking.
- Added Inactive / Active Filter to Recurring Invoices.
- Merged Dismissed notifications and notification in one.
- Added Link Button to addd / edit Document WYSIWYG.
- Added Physical location to the asset export / import.

## [25.02.1]
### Fixed
- Resolved broken links in the client overview, project and client listings, and rack details.
- Corrected asset transfer functionality to clients.
- Fixed the ticket scheduling redirect.
- Corrected the ticket link in the Scheduled Ticket Agent Notification email.
- Addressed issues with credentials and ticket actions in the Contact Detail Modal.
- Fixed text wrapping in notifications.
- Adjusted notifications so that they are sorted with the newest first.
- Fixed drag-and-drop functionality for tickets in the Kanban view on mobile devices.
- Resolved a weird issue with TinyMCE that prevented using links referencing your ITFlow instance url.
- Corrected image orientation issues during upload and the preview optimization process.

### Added / Changed
- Introduced entity link indicator icons and counts in the contacts and credentials section.
- Implemented a fade animation for the new AJAX modal.
- Removed the Client Overview Expire Day Select and replaced it with simplified 1, 7, or 45-day options.
- Added the ability to link and unlink entities within asset details.
- Introduced quick tag/category creation across the app.
- Added a Vendor Quick Details Modal.
- Enabled vendor linking and added a License Purchase Reference in the Software Licenses section.
- Added download original, optimized and thumbnail option for images.
- Added Paid status to the top corner of Invoice PDFs.

## [25.02]
### Fixed
- Migrated several reports to the new permissions/roles system.
- Resolved issue with empty task box showing for closed/resolved tickets.
- Corrected ticket priority sorting.
- Cloned asset interfaces when transferring assets between clients.

### Added / Changed
- Restored max number of records per page option back to 500 since we dont have repeating modals.
- Bulk Categorize Tickets feature.
- Renamed "Interface port" to "Interface Description." "Interface Name" should now refer to port name and/or number.
- Changed "Transfer Asset to Client" from a single action to a bulk action.
- Updated Filter Footer UI to show "Showing x to x of x records" instead of just the total records.
- Added Client Overview section to view client assets, contacts, licenses, credentials, etc.
- Introduced Quick Peek for asset details, contact information, and document viewing throughout the ITFlow App, all made possible by AJAX.
- Enabled Simple Drag-and-Drop Ordering for Invoices, Recurring Invoices, Quotes, Ticket Tasks, and Ticket Template Tasks.
- Added new Ticket View options: Kanban and Simple View.
- Migrated all repeating modals to the new AJAX modal function for faster loading times and quicker development.
- Allowed clients to upload PDF documents to accepted quotes.
- Client Portal now shows ticket category.
- Custom links can now be added to the Client Portal navbar.
- Lots of little tweaks to UI, performance, bugs, etc.

### Breaking Changes
- Cron scripts have officially been moved to the /scripts folder and are no longer in the root directory; they must be updated to function properly.

## [25.01.3]
### Fixed
- Fixed ticket assignment modal showing client contacts.

## [25.01.2]
### Fixed
- Fixed app version.

## [25.01.1]

### Added / Changed
- Redesigned the Multi-Factor Authentication (MFA) Setup and Enforcement Flow UI/UX for a more intuitive user experience.
- Added a "Member" column in the user roles listing for improved visibility.
- General UI/UX improvements, along with minor performance optimizations and cleanups.

### Fixed
- Fixed an issue where Stripe was not appearing as a recurring payment option.
- Corrected inaccurate Quarter 2 Expense results in the Profit & Loss Report.
- Resolved TOTP code not displaying correctly on hover in the Contact or Asset Details sections.
- Archived contacts no longer appear in the Bulk Mail section.
- Fixed an issue where the Ticket Assign Modal was showing both ITFlow and client users.
- Fixed issue with login key redirecting to legacy client portal page.

## [25.01]

### Added / Changed
- Added support for saving cards in Stripe for automatic invoice payments.
- Page titles now display detailed information (e.g., page name, client selection, company name, ticket and invoice info) for easier multi-tab navigation.
- Reintroduced the new admin role-check for admin pages.
- Admin roles can now be archived.
- Debug mode now shows the current Git branch.
- The auto-acknowledgment email for email-parsed tickets now includes a guest link.
- Recurring tickets no longer require a contact.
- Stripe online payment setup now prompts you to set the income/expense account.
- New cron/CLI scripts have been moved to the `/scripts` subfolder — remember to update your cron configurations!
- Moved modal includes to `/modals` to tidy up the root directory.
- Moved most include files to `/includes` to improve directory structure.
- Moved guest pages to `/guest` for better organization.
- Renamed the include file `pagination.php` to `filter_footer.php`, as it is used in conjunction with `filter_header.php` for page filtering.
- Guest ticket feedback now shows the ticket prefix and number, not just the ID.
- Individual POST handler logic pages are no longer directly accessible.
- Added the ability to delete payments on the Payments and Client Payments pages.
- Implemented domain history tracking.
- Added Asset Interface Linking/Connections to show what interface is connected to which interface port of another asset.
- Added Force Recurring Ticket option in more locations, not just for recurring tickets.
- Implemented row spanning and centered devices that occupy multiple units in a rack.
- Added tooltips to main navigation badge counts to clarify what is being counted.
- Reduced max records per page from 500 to 100 to prevent performance issues.
- Updated several plugins:
  - `stripe-php` from 10.5.0 to 16.4.0
  - `Inputmask` from 5.0.8 to 5.0.9
  - `DataTables` from 2.1.8 to 2.2.1
  - `pdfmake` from 0.2.8 to 0.2.18
  - `php-mime-mail-parser` to 9.0.1
  - `TinyMCE` from 7.5.1 to 7.6.1
- Removed unused libraries from the vendor folder and moved Stripe to the plugins folder, eliminating the vendor folder.
- Merged the MFA TOTP functionality files `base32static.php` and `rfc6238.php` into a single file (`totp`) and moved it to the plugins folder.
- No longer need to pass the DB connection (`$mysqli`) to the `addToMailQueue` function.
- Disabled HTML Purifier caching.
- Replaced the `nullable_htmlentities` function with `htmlspecialchars`.
- Updated filter variable naming.
- Implemented other minor UI updates, performance optimizations, and directory cleanups.

### Fixed
- Fixed an issue where the ticket edit modal didn't show multi-client or no-client projects.
- Fixed asset interface losing DHCP settings.
- Fixed a 500 error when creating or editing recurring expenses due to an incorrect variable name.
- Fixed tickets created via the portal/email not being marked as billable.
- Fixed issues with editing recurring expenses.
- Resolved a regression where the TinyMCE editor didn’t display when adding or editing ticket templates.
- Fixed a TinyMCE license issue.

### Removed / Deprecated
- Deprecated the cron scripts in the root directory. Cron jobs should now use the ones in the `/scripts` subfolder, which no longer require a cron key and must be run via CLI.

### BREAKING CHANGES
- The client portal has been moved from `/portal` to `/client`:
  - Links in previous emails will be broken.
  - The Azure Entra ID SSO Redirect URI needs to be updated to `/client`.
  - You may need to update other links (e.g., website, support page).
- Guest links have been moved from `/` to `/guest`. Previous links will be broken.

## [24.12]

### Added / Changed
- Introduced versioned releases for the first time!
