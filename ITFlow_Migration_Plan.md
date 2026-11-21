# ConnectWise PSA (on-prem) → ITFlow Migration Plan

Prepared for Ameer · Updated 2026-11-19 · Real schema confirmed via direct database discovery, cross-checked against ITFlow's own support team's migration guidance

## 1. Status

This plan now uses your actual ConnectWise on-prem SQL Server table and column names, confirmed through twenty-five rounds of discovery queries run directly against your database (not the REST API guesses the first draft used) — plus a real cross-check against migration guidance sent directly by ITFlow support (Marcus), which caught several things the schema alone didn't make obvious. `etl_skeleton.py` now has real, working code for every core entity: clients, vendors, locations, contacts, assets, asset↔ticket links, contracts, tickets, ticket replies/time, invoices (including both product and time-based line items, with a fully confirmed billing rate), payments, credits, and quotes (hybrid model — see Section 2). Three stubs remain, all lower-priority scope not yet started: `migrate_attachments()` and the two custom-field functions.

**One fix from an earlier round is worth flagging specifically**: `tickets.ticket_created_by` is a required (`NOT NULL`) column with no default in ITFlow's schema, and it was missing from the ticket INSERT entirely — under MySQL/MariaDB's default strict SQL mode, that's not a soft gap, it's a hard error on every single ticket row. That's now fixed (defaults to `0`, "system", confirmed acceptable by ITFlow support).

**A second, unrelated fix is worth flagging just as clearly**: every `migrate_*` function had a crash-safety gap in how it used the local SQLite id-map (`migration_id_map`), the thing every stage relies on to know what's already been migrated and to resolve foreign keys across stages. `record_mapping()` was committing each mapping to SQLite immediately, per row, while the actual ITFlow insert for that same row wasn't committed to MySQL until the *end* of the whole stage. If the script had died, lost a connection, or been killed partway through a stage — after some rows' mappings were already committed to SQLite but before that stage's final ITFlow commit — those rows' mappings would durably say "already migrated" even though ITFlow never actually received them. Re-running the stage would then silently skip them forever (`already_migrated()` finds the mapping, skips the row) with no error raised — permanent, silent data loss for exactly the rows unlucky enough to be mid-flight when something went wrong. Fixed by removing `record_mapping()`'s own commit and committing the id-map right alongside each stage's real `itflow_conn.commit()` instead, so both databases' "this really happened" boundary lines up. See item 24 in Section 6.

A handful of smaller items are still open — called out inline in the relevant section below and tracked in Section 6, not buried in a caveat at the top. Two external factors also shape how this runs: the Proactive board is being deleted from ConnectWise before migration (not just filtered out by the script), and invoice history is split across two migrations — Sage for older invoices (your own script, runs first) and this script for ConnectWise invoices, migrating with their original numbers/prefix unchanged (see Section 6, item 9, for the collision risk that creates). The script now runs in 11 checkable stages rather than as one long migration — see Section 4 for the full breakdown and CLI usage — and reads its DB credentials from environment variables rather than the file itself, since it's now close to running on a real server (Section 7 covers where and how).

## 2. Scope — updated based on real data

- Clients, sites, contacts
- Assets (ConnectWise "Configurations")
- **Service tickets — Reactive board only.** Your ConnectWise instance has 248,829 total ticket-like records, but 240,354 of those (96.6%) are on the "Managed Service - Proactive Alerts" board — automated noise, per your call to exclude it. The "Managed Service - Reactive" board has 8,475 tickets, which is what actually migrates. A third board, "Projects," has zero tickets and can be ignored.
- Ticket notes/discussion and time entries, scoped to the same Reactive-board ticket set
- Agreements/contracts (103 of them)
- Invoices. Originally scoped at 1,695 total; round 25 (Nov 2026) found 1,742 real (non-credit-memo) `Billing_Log` rows (1,301 Agreement-type + 441 Standard-type) — the difference is just new invoices created in ConnectWise in the months since the original count, not a discrepancy to chase.
- **Payments — implemented, sourced from `Billing_Application` (real per-invoice payment applications, covering both full and partial payments — see Section 5.9a).** ITFlow support's own migration guidance was explicit that a "Paid" invoice status should be backed by a real `payments` row for accounting to reconcile, not just a status label.
- **Credits — implemented and confirmed with real numbers (round 25).** ConnectWise credit memos (`Billing_Type_ID='C'`) are not real invoices (your call) — they migrate into ITFlow's dedicated `credits` table instead, linked to the invoice(s) they were applied against. See Section 5.9b.
- ~~Knowledge base articles~~ — **dropped from scope.** The `KB_Resolution` table has zero rows; there's no knowledge base content in this instance.
- Vendors
- Attachments/documents
- Custom fields
- **Quotes — confirmed and implemented.** ITFlow has `quotes`/`quote_items`, structured almost identically to invoices. Round 15 came back with an entire module (`SO_` = **Sales Opportunity**, not "Sales Order" as first guessed) — ConnectWise's full sales/CRM/pipeline system: `SO_Opportunity`, `SO_Pipeline`, `SO_Status`, `SO_Type`, `SO_Probability`, sales team/activity tracking, and reporting views. **Business question resolved: you confirmed the Opportunity record itself is what you call "the quote"** — no separate object gets created once a deal is won.

  Round 16 turned up two more findings. First, a dead end reported honestly: `v_api_collection_system_quote_link` / `v_ML_QuoteLinks` are not quote records at all — just admin config for an external quote-tool integration URL (`System_Screens` where `screen_id = 'quote_integration'`), a naming coincidence like `CS_Survey` before it. Second, a real find: `v_api_collection_sales_order`'s definition reveals **`Order_Header`**, a table round 15's search completely missed (its name doesn't contain "sales," "opportunity," "quote," or "SO_"). This is ConnectWise's actual formal "Sales Order" object — its own status, totals, tax, ship-to/bill-to addresses, and its own line items via `IV_Product` (same pattern as invoice line items) — with only an *optional* link to `SO_Opportunity`.

  Round 17 confirmed the real usage split: 334 `Order_Header` rows total, 230 of them linked to an opportunity (out of 384 opportunities) — so roughly 60% of quotes have progressed to formal pricing, 40% haven't. That's not a data gap; you confirmed it directly by checking a real example ("Truline Bitwarden") in the ConnectWise UI — it's simply an open deal awaiting a decision, not priced yet. **Design confirmed: the hybrid model.** Every `SO_Opportunity` migrates as a quote (full coverage, ~384 rows); the ~230 with a linked `Order_Header` get a real `quote_amount` (from `Order_Header.Total`) and real `quote_items` (from `IV_Product`, filtered by `Order_Header_RecID` — the exact same pattern already used for invoice product line items); the rest migrate as unpriced quote headers (`quote_amount = 0`, no line items), reflecting genuine business reality. Round 18 confirmed the status lookup table is `SO_Opp_Status` (not `SO_Status`, which was queried by mistake first) — `SO_Opp_Status_RecID` is `SO_Opportunity`'s real status FK. See Section 5.9c for the full confirmed mapping. **Implemented and working** in `etl_skeleton.py`'s `migrate_quotes()`.
- ~~ConnectWise Projects module~~ — **confirmed out of scope.** Not used day-to-day; the script never touches `PM_Project_RecID` or project-specific tables.

## 3. Real table map

| Business entity | Real ConnectWise table(s) | ITFlow table(s) |
|---|---|---|
| Clients | `Company` (excluding vendor-flagged) | `clients` |
| Sites/locations | `Company_Address` | `locations` |
| Vendors | `Company` (where vendor-flagged via `Company_Company_Type` → `Company_Type.Vendor_Flag`) | `vendors` |
| Contacts | `Contact` | `contacts` |
| Contact phone/email | `Contact_Communication` (+ `Communication_Type` lookup) | `contacts` (email/phone/mobile columns) |
| Assets | `Config` | `assets` |
| Contracts | `AGR_Header` (+ `AGR_Detail` for line items, `AGR_Type` for type) | `contracts` |
| Tickets | `SR_Service` (filtered to `SR_Board_RecID` = Reactive board) | `tickets` |
| Ticket notes | `SR_Detail` | `ticket_replies` |
| Time entries | `Time_Entry` | `ticket_replies` (merged with notes — see 5.7) |
| Invoices | `Billing_Log` (+ `Billing_Status`, `Billing_Type`, `Billing_Terms`, `Currency`), excluding credit memos (`Billing_Type_ID='C'`) | `invoices` |
| Invoice line items | `Time_Entry` rows where `Billing_Log_RecID` is set (time-based lines); products/expenses have their own equivalent tables, not yet confirmed | `invoice_items` |
| Payments | `Billing_Application` (`Payment_Type='P'`) | `payments`, posted to a single dedicated `accounts` row |
| Credits | `Billing_Log` credit memos (`Billing_Type_ID='C'`), linked via `Billing_Application.Credit_Memo_RecID` | `credits` |
| Quotes | `SO_Opportunity` (+ `SO_Opp_Status`, `Currency`), with pricing from `Order_Header` where linked | `quotes` |
| Quote line items | `IV_Product` rows where `Order_Header_RecID` is set (only present for the ~60% of opportunities with a linked, priced order) | `quote_items` |
| Attachments | `DM_Document` + `DM_Relationship` (polymorphic link table) | `files` / `documents` (see 5.10) |
| Technicians/users | `Member` | ITFlow users (needs your own mapping list) |
| Custom fields | Various `*_User_Defined_Field_Value` tables per entity | `custom_fields` / `custom_values` |

This replaces the REST-API-name guesses from the first draft. A few important structural surprises worth knowing before writing the ETL script:

- **Invoices aren't called "Invoice" anywhere** — they live in `Billing_Log`, ConnectWise's internal billing/GL module table.
- **Ticket notes and time-entry notes are two different physical tables that ConnectWise's own UI merges for display.** `SR_Detail` holds pure text notes; `Time_Entry` holds notes attached to logged time (`Notes`, `TE_Problem_Flag`, `TE_Resolution_Flag`, `TE_InternalAnalysis_Flag`). The `v_rpt_ServiceNote` view is literally a `UNION ALL` of both — confirms the plan's original instinct to merge them into one `ticket_replies` stream per ticket was right, just from two source tables instead of one.
- **A ticket's opening description isn't a column on the ticket.** `SR_Service` has no "initial description" field — it's the earliest `SR_Detail` row for that ticket (flagged `Problem_Flag`/`Issue_Flag`). Building ITFlow's `ticket_details` needs a join, not a column copy.
- **Company (client) records have no email or phone-on-the-client field at all** in either ConnectWise or ITFlow — phone lives on `Company_Address`/`locations`, email only exists at the contact level. Don't expect a `client_email` anywhere.

## 4. Migration order, dependencies, and staged execution

Unchanged in principle from the first draft, now with real tables:

1. **Clients** ← `Company` (non-vendor)
2. **Locations** ← `Company_Address`
3. **Vendors** ← `Company` (vendor-flagged)
4. **Contacts** ← `Contact` + `Contact_Communication`
5. **Custom field definitions** (one-time setup per entity)
6. **Assets** ← `Config`
7. **Contracts** ← `AGR_Header`
8. **Tickets** ← `SR_Service` (Reactive board only)
9. **Ticket replies** ← `SR_Detail` + `Time_Entry` (merged, ticket-scoped)
10. **Invoices** ← `Billing_Log` (excluding credit memos — see #11a)
11. **Payments** ← `Billing_Application` (needs invoices' ID map)
11a. **Credits** ← `Billing_Log` credit memos (`Billing_Type_ID='C'`) (needs invoices' ID map)
12. **Quotes** ← `SO_Opportunity` (+ `Order_Header` where linked)
13. **Attachments** ← `DM_Document` + `DM_Relationship`
14. **Custom field values** (last — needs every other entity's ID map populated)

**Your call: this runs in checkable stages, not all at once.** `etl_skeleton.py` now has a `--stage N` CLI flag — run one stage, go check the result directly in ITFlow, then run the next whenever you're satisfied, rather than one long migration you can only inspect at the very end. The same dependency order above is preserved, just grouped into 11 stages:

| Stage | Contents | What to check in ITFlow before moving on |
|---|---|---|
| 1 | Members, clients, vendors, locations, contacts | Clients/vendors/contacts/locations look right; both technicians' tickets will attribute correctly |
| 2 | Custom field definitions | Not yet implemented — safe no-op |
| 3 | Assets | Assets attached to the right client/location/contact; `asset_type` blank as expected |
| 4 | Contracts | SLA response/resolution numbers look right after the 5-tier → 3-tier collapse |
| 5 | Attachments | Not yet implemented — safe no-op |
| 6 | Tickets, ticket replies/time, asset↔ticket links | ~8,475 Reactive-board tickets present; `ticket_priority` shows Low/Medium/High (ITFlow's confirmed vocabulary); replies show Internal/Public correctly. Needs stages 1 and 3 done first |
| 7 | Invoices | Totals match ConnectWise; line items correct; `invoice_status` shows Draft/Sent/Paid (ITFlow's confirmed vocabulary); no number collision with the Sage-migrated invoices; credit memos no longer appear here at all (see Stage 9) |
| 8 | Payments | A payment row exists for every invoice with real cash applied — full or partial — sourced from `Billing_Application`, posted to your one real `PAYMENT_ACCOUNT_NAME` account. Needs Stage 7 done first |
| 9 | Credits | One `credits` row per ConnectWise credit memo (11 total, confirmed round 25), linked to the invoice it was applied against where there's exactly one — 2 of the 11 were genuinely applied across 3 invoices each and correctly land unlinked with full detail in `credit_note` instead. Needs Stage 7 done first |
| 10 | Quotes | All ~384 opportunities show up as quotes; the ~230 with a linked Order_Header have real amounts/line items, the rest show 0.00 with none — expected, not a bug |
| 11 | Custom field values | Not yet implemented — safe no-op. Must run last regardless |

Because every entity function already checks the `migration_id_map` SQLite database before inserting anything, stages are safe to run in completely separate sittings — nothing gets duplicated if you stop after Stage 1 today and come back to Stage 3 next week. Usage:

```
python etl_skeleton.py --list-stages       # see every stage before running anything
python etl_skeleton.py --stage 1           # run just stage 1, then go check ITFlow
python etl_skeleton.py --stage 3 --stage 4 # run several stages in one invocation
python etl_skeleton.py --all               # run every stage back to back, once you're confident
```

## 5. Entity-by-entity mapping (confirmed columns)

### 5.1 Clients ← `Company`

Excludes companies where `Company_Company_Type` → `Company_Type.Vendor_Flag = 1` (those go to 5.3 instead).

| Real CW column | ITFlow column (`clients`) | Notes |
|---|---|---|
| `Company_Name` | `client_name` | |
| `Company_ID` | `client_abbreviation` | CW allows up to 50 chars, ITFlow's field is 10 — truncate |
| `Lead_Flag` | `client_lead` | Direct boolean |
| `Tax_ID` | `client_tax_id_number` | |
| `Website_URL` | `client_website` | |
| `Currency_RecID` → `Currency.Iso_Code` | `client_currency_code` | Join required |
| `Billing_Terms_RecID` → `Billing_Terms.Due_Days` | `client_net_terms` | Join required |
| `Date_Entered` | `client_created_at` | |
| `Delete_Flag` / `Date_Deleted` | `client_archived_at` | Set timestamp if deleted |
| — | `client_favorite` | No CW equivalent, default 0 |

`Company.PhoneNbr` and `Company.PhoneNbr_Fax` exist but have no ITFlow home on `clients` (ITFlow keeps phone on `locations` only) — carry them onto the client's default `Company_Address` record instead of dropping them.

### 5.2 Locations ← `Company_Address`

| Real CW column | ITFlow column (`locations`) | Notes |
|---|---|---|
| `Description` | `location_name` | |
| `Address_Line1` + `Address_Line2` | `location_address` | Concatenate |
| `City` | `location_city` | |
| `State_ID` | `location_state` | |
| `Zip` | `location_zip` | |
| `PhoneNbr` + `PhoneNbrExt` | `location_phone` + `location_phone_extension` | |
| `PhoneNbr_Fax` | `location_fax` | |
| `Default_Flag` | `location_primary` | |
| `Inactive_Flag` | `location_archived_at` | Set timestamp if true |
| `Company_RecID` | `location_client_id` | Via ID map |
| `Country_RecID` | `location_country` | Needs a `Country` lookup join (not yet pulled — small table, low priority) |

### 5.3 Vendors ← `Company` (vendor-flagged)

Same source table as clients, filtered the other direction: `INNER JOIN Company_Company_Type ... INNER JOIN Company_Type ON Vendor_Flag = 1`.

| Real CW column | ITFlow column (`vendors`) |
|---|---|
| `Company_Name` | `vendor_name` |
| `PhoneNbr` | `vendor_phone` |
| `Website_URL` | `vendor_website` |
| `Account_Nbr` | `vendor_account_number` |

No company-level email exists on the CW side (see the structural note in Section 3) — `vendor_email` will need to come from that vendor's default contact's `Contact_Communication` row, if one exists, or stay blank.

### 5.4 Contacts ← `Contact` + `Contact_Communication`

| Real CW column | ITFlow column (`contacts`) | Notes |
|---|---|---|
| `First_Name` + `Last_Name` | `contact_name` | Concatenate |
| `Title` | `contact_title` | |
| `Default_Billing_Flag` | `contact_billing` | |
| `Default_Flag` | `contact_primary` | |
| `Company_RecID` | `contact_client_id` | Via ID map |
| `Company_Address_RecID` | `contact_location_id` | Via ID map |
| `Inactive_Flag` | `contact_archived_at` | Set timestamp if true |
| `Date_Entered` | `contact_created_at` | |

Email/phone/mobile come from `Contact_Communication` joined to `Communication_Type` — real columns confirmed: `Communication_Type_RecID`, `Communication_Name`, `Phone_Flag`, `Fax_Flag`, `Email_Flag`, `Disabled`. Your org has 28 custom communication types (Email, Direct, Mobile, Fax, plus custom ones like "Customer Support Phone," "Personal Cellphone," etc. added in 2023), but they all carry the same consistent three-way flag pattern that buckets into phone-like / fax-like / email-like. Telling "mobile" apart from "regular phone" within the phone-flagged types isn't a separate flag — it's a naming convention on `Communication_Name` (`Mobile`, `Personal Cellphone`, `Work Cellphone`, `Shared Work Cellphone` match; everything else phone-flagged defaults to the regular phone field). Fax-flagged types have no ITFlow contact field to land in. **Implemented and working** in `etl_skeleton.py`'s `migrate_contacts()`.

`contact_technical` (ITFlow's technical-contact flag) has no direct CW equivalent — closest is checking `Contact_Contact_Type` → `Contact_Type.Description` for a type name containing "technical," which is a text-matching heuristic, not a clean join.

### 5.5 Assets ← `Config`

| Real CW column | ITFlow column (`assets`) | Notes |
|---|---|---|
| `Config_Name` | `asset_name` | |
| `Model_Number` | `asset_model` | |
| `Serial_Number` | `asset_serial` | |
| `Os_Type` / `Os_Info` | `asset_os` | Combine |
| `Config_Status_RecID` → `Config_Status.Description` | `asset_status` | Join required |
| `Date_Purchased` | `asset_purchase_date` | |
| `Date_Installed` | `asset_install_date` | |
| `Date_Expiration` | `asset_warranty_expire` | Direct match — better than the first draft assumed, CW does track this natively |
| `Notes` | `asset_notes` | Append `IP_Address`/`MAC_Address` here too, since ITFlow has no dedicated columns for either |
| `Company_RecID` | `asset_client_id` | Via ID map |
| `Company_Address_RecID` | `asset_location_id` | Via ID map |
| `Contact_RecID` | `asset_contact_id` | Via ID map |
| `Manufacturer_RecID` | `asset_make` | Needs its lookup table confirmed (`Config` also has a separate `Mfg_Company_RecID` column — the two may be redundant/legacy, worth spot-checking a few real rows before picking one) |

**Open item:** `Config` has no `Config_Type_RecID` column, and the obvious-looking `v_api_collection_company_configuration_type` view turned out to be a red herring — it's actually built from `dbo.CS_Survey` (a customer survey config, unrelated to asset device types), a naming coincidence. You've confirmed your team does categorize asset types in the ConnectWise UI, so it's tracked somewhere — most likely a custom field (`Config_User_Defined_Field_Value`) rather than a standard column. A follow-up discovery round is checking that table plus `IV_Product` (which `Config.IV_Product_RecID` links to, in case type rides along with the linked inventory product's category).

### 5.6 Contracts ← `AGR_Header`

| Real CW column | ITFlow column (`contracts`) | Notes |
|---|---|---|
| `AGR_Name` | `contract_name` | |
| `AGR_Cancel_Flag` | `contract_status` | |
| `AGR_Type_RecID` → `AGR_Type.AGR_Type_Desc` | `contract_type` | Join required |
| `AGR_Date_Start` | `contract_start_date` | |
| `AGR_Date_End` / `AGR_NoEnd_Flag` | `contract_end_date` | |
| `Company_RecID` | `contract_client_id` | Via ID map — no site-level scoping needed, `AGR_Site` has zero rows in your instance |
| `AGR_InvDesc` / `AGR_Notes` | `contract_details` | Combine |
| `Billing_Terms_RecID` | `contract_net_terms` | |
| `SR_SLA_RecID` → `SR_SLAPriority` (per urgency tier) | `contract_sla_*_response_time` / `contract_sla_*_resolution_time` | CW's SLA targets are keyed per-urgency (5 tiers: Critical/High/Medium/Low/Do Not Respond) but ITFlow only has low/medium/high — needs an explicit tier-collapsing rule, not a clean 1:1 join |

Agreement line items (`AGR_Detail`, 175 rows) don't map to a flat ITFlow contract field — as noted in the first draft, recurring billing structure would need to be rebuilt in ITFlow's `recurring_invoices`/`recurring_invoice_items` separately if it needs to keep working post-migration.

### 5.7 Tickets ← `SR_Service` (Reactive board only)

Filter: `WHERE SR_Board_RecID = <Reactive board's RecID>` (confirm exact value from your board query results — two boards exist, Reactive and Proactive Alerts; Reactive is the one to keep).

| Real CW column | ITFlow column (`tickets`) | Notes |
|---|---|---|
| `Summary` | `ticket_subject` | |
| (earliest `SR_Detail` row, `Problem_Flag`/`Issue_Flag`) | `ticket_details` | Not a column — see structural note in Section 3 |
| `SR_Status_RecID` → `SR_Status.Description` | `ticket_status` | Join, then match by name to pre-created ITFlow `ticket_statuses` rows |
| `SR_Urgency_RecID` → `URGENCY_TO_TICKET_PRIORITY` | `ticket_priority` | **Fixed**: previously copied CW's raw urgency text ("Priority 1 - Critical" etc.) straight through. ITFlow support confirmed the app only expects exactly "Low"/"Medium"/"High" (Title Case) — now collapsed via the same RecID mapping already used for the SLA tier logic. "Do Not Respond" folds to Low (judgment call, same open item as before). |
| `SR_Type_RecID` / `SR_SubType_RecID` → their `Description` columns | `ticket_category` | Concatenate |
| `SR_Source_RecID` → `SR_Source.Description` | `ticket_source` | Join required. Real values never sampled — ITFlow's docs suggest Email/Client Portal/In-App/Project Template, but ITFlow support confirmed a custom migration value is fine, so this is cosmetic, not urgent |
| `Company_RecID` | `ticket_client_id` | Via ID map |
| `Contact_RecID` | `ticket_contact_id` | Via ID map |
| `Company_Address_RecID` | `ticket_location_id` | Via ID map |
| `Date_Entered` | `ticket_created_at` | |
| `Date_Closed` | `ticket_closed_at` | |
| `Date_Closed` | `ticket_resolved_at` | **Resolved (round 20):** `SR_Service`'s full column list is now confirmed, and CW simply has no separate resolved-vs-closed concept (`Date_Closed`/`Date_Closed_UTC`/`IsClosed_Flag` exist; nothing resolution-specific does). Set equal to `Date_Closed` — a judgment call (not every closed ticket was necessarily "resolved" in ITFlow's stricter sense), but avoids every migrated ticket looking permanently unresolved for ITFlow's resolution-SLA reporting |
| `Ticket_Owner_RecID` → `Member` | `ticket_assigned_to` | Via technician ID map — this, not `Assigned_By_RecID` or `Entered_By`, is the real "assigned tech" field |
| `Closed_By` → `Member.Member_ID` | `ticket_closed_by` | Note: `Closed_By` stores the Member_ID *code* (text), not the numeric RecID — join on the code, not an integer FK |
| `TimeBillable_Flag` / `BillSR_Flag` | `ticket_billable` | |
| — | `ticket_created_by` | **Critical fix**: `NOT NULL` in ITFlow's schema with no default, and previously missing from the INSERT entirely — under strict SQL mode this errors on every ticket. Now explicitly set to `0` ("system"), confirmed acceptable by ITFlow support |
| — | `ticket_url_key` | **Fixed**: nullable so it never errored, but ITFlow support confirmed it needs a random 200-character string for portal/share links. Now generated per ticket |
| CW's own `SR_Service_RecID` | `ticket_vendor_ticket_number` | A dedicated field ITFlow's guidance calls out for referencing a migrated system's original ticket number, alongside (not replacing) reusing that RecID as `ticket_number` itself |

Asset linkage: `SR_Config` **confirmed** as the ticket↔configuration join table — its only content columns are `Config_RecID` and `SR_Service_RecID`, a clean many-to-many link. **Implemented and working** in `etl_skeleton.py`'s `migrate_tickets()` (ticket header) and `migrate_asset_ticket_links()` (the `SR_Config` join, run after both assets and tickets exist). `ticket_status` is resolved via a get-or-create helper that matches ITFlow's `ticket_statuses` by name and creates any that don't already exist, so this doesn't need a pre-seeded status list to run. The "Do Not Respond" priority decision (item 2 below) is still open — those tickets migrate like any other for now, priority folded to Low.

### 5.8 Ticket replies ← `SR_Detail` + `Time_Entry` (merged)

Two source tables feed one ITFlow `ticket_replies` stream per ticket, matching how ConnectWise's own `v_rpt_ServiceNote` view already unions them:

| Source | Real CW column | ITFlow column (`ticket_replies`) |
|---|---|---|
| `SR_Detail` | `SR_Detail_Notes` / `SR_Detail_Notes_Markdown` | `ticket_reply` (prefer markdown version when present) |
| `SR_Detail` | `InternalAnalysis_Flag` | `ticket_reply_type` — **confirmed**: `1` = hidden from client (internal), everything else = public. Matches ConnectWise's standard client-portal behavior and your own description of how the team uses it. Written as exactly `"Internal"` / `"Public"` (Title Case) — an earlier version wrote lowercase `"internal"`/`"public"`, which ITFlow support's own example (`'Internal'`, `'Public'`, `'Client'`, quoted literally) implies wouldn't match |
| `SR_Detail` | `Member_RecID` | `ticket_reply_by` (via technician map) |
| `SR_Detail` | `Date_Created_UTC` | `ticket_reply_created_at` |
| `SR_Detail` | `SR_Service_RecID` | `ticket_reply_ticket_id` (via ID map) |
| — | `ticket_reply_time_worked` | Set to `'00:00:00'` for `SR_Detail`-sourced replies (no logged time). Nullable in the schema, but ITFlow support's guidance says this shouldn't be left null |
| `Time_Entry` | `Notes` | `ticket_reply` |
| `Time_Entry` | `TE_InternalAnalysis_Flag` | `ticket_reply_type` (same rule and same Title-Case fix as `SR_Detail`) |
| `Time_Entry` | `Hours_Actual` | `ticket_reply_time_worked` (convert decimal hours → MySQL `TIME`) |
| `Time_Entry` | `Member_RecID` | `ticket_reply_by` |
| `Time_Entry` | `Date_Entered_UTC` | `ticket_reply_created_at` |
| `Time_Entry` | `SR_Service_RecID` | `ticket_reply_ticket_id` |

`Internal_Member_Flag` / `External_Source_Flag` turned out **not** to be the visibility mechanism — real sample data showed both flags set simultaneously on both technician notes and customer-submitted messages, which reads more like "who authored this / where did it originate" than a visibility toggle. `InternalAnalysis_Flag` is the confirmed answer for *visibility*.

**Open item, from ITFlow support's guidance**: ITFlow actually supports a *third* `ticket_reply_type` value, `'Client'`, for replies the client themselves submitted — distinct from `'Public'` (an agent's client-facing reply). This migration currently only does the 2-way Internal/Public split; every non-internal reply lands as `'Public'`, whether it was written by a technician or by the client. `Internal_Member_Flag`/`External_Source_Flag` were ruled out as the *visibility* signal above, but never fully ruled out as an *authorship* signal — that's a different question, and worth a follow-up discovery round correlating those flags against replies known to have come from the client portal before attempting a 3-way split. Not attempted yet since it needs real data to confirm rather than guess.

Both sources filter to the same Reactive-board ticket set via `SR_Service_RecID` — this is what shrinks `SR_Detail`'s raw 787,436 rows and `Time_Entry`'s 4,235 rows down to whatever's actually attached to the 8,475 Reactive tickets. **Implemented and working** in `etl_skeleton.py`'s `migrate_ticket_replies_and_time()`.

**Note style: verbatim, by your choice.** A large share of `Time_Entry.Notes`/`Notes_Markdown` follow a rigid ConnectWise fill-in-the-blank template: `Time on Site: HH:MM - HH:MM`, `Total non-billable time: ...`, `Total billable time: ...`, the actual work narrative, then `Additional Notes:` / `Items still outstanding:` — almost always left as an empty placeholder (`N/a`, a typo'd `N\a`, `None`, or blank). Three options were laid out side by side against real pasted samples — verbatim (migrate the template exactly as ConnectWise stores it), light cleanup (drop the redundant time restatement and empty placeholder fields, keep everything else), and full restructure (strip all labels, reformat into bullets) — and **you preferred ConnectWise's own note style as-is**. `migrate_ticket_replies_and_time()` migrates both `SR_Detail` and `Time_Entry` notes verbatim, unmodified. A working, tested `clean_onsite_note_template()` function still exists in `etl_skeleton.py` (just not called) in case this preference changes later — it's documented inline as available but unused.

Also fixed while evaluating this (kept regardless of the style decision, since it's a bug fix not a style choice): `Time_Entry`'s query now prefers `Notes_Markdown` over plain `Notes` (via `COALESCE`), matching what `SR_Detail` already did — this was an oversight in the original implementation.

### 5.9 Invoices ← `Billing_Log`

| Real CW column | ITFlow column (`invoices`) | Notes |
|---|---|---|
| `Invoice_Number` | `invoice_number` | |
| `Reference` (fallback: synthesized "ConnectWise Invoice #n") | `invoice_scope` | A real `invoices` column that had been completely unmapped; ITFlow support's guidance calls it "the invoice description" |
| `Billing_Status_RecID` → `BILLING_STATUS_TO_INVOICE_STATUS`, overridden to `Paid` if `Date_Paid` is set | `invoice_status` | See the callout below for why this changed |
| `Date_Invoice` | `invoice_date` | |
| `Date_Invoice` + `Billing_Terms.Due_Days` | `invoice_due` | CW computes due date rather than storing it — replicate the same `DATEADD` logic |
| `Invoice_Amount` | `invoice_amount` | |
| `Currency_RecID` → `Currency.Iso_Code` | `invoice_currency_code` | Join required |
| `Internal_Note` / `Top_Comment` / `Bottom_Comment` | `invoice_note` | Combine |
| — | `invoice_url_key` | Random 200-character string, per ITFlow support's guidance (portal/share links need it) |
| `Company_RecID` | `invoice_client_id` | Via ID map |

1,742 real (non-credit-memo) invoices confirmed as of round 25 (Nov 2026) — full history, no cutoff needed.

Line items: `Time_Entry` rows carry their own `Billing_Log_RecID` once invoiced — a billed time entry *is* a line item, not a separate row in a flat items table. **Products follow the identical pattern, confirmed**: `IV_Product` (despite the name, this is a transactional product/parts line-item table, not a catalog) has its own `Billing_Log_RecID` and `SR_Service_RecID` columns, plus `Quantity`, `Unit_Price`, `Unit_Cost`, `Description` — everything needed for `invoice_items`. Expenses almost certainly follow the same pattern in their own table (not yet confirmed, lower priority).

**`invoice_status`.** An email from ITFlow support (Marcus) confirmed `invoice_status` only has four valid values: Draft, Sent, Paid, Cancelled — the code was previously copying `Billing_Status.Description` straight through, unvalidated against that vocabulary. Discovery round 19 then pulled `Billing_Status`'s real values for the first time: New, Approved, Review, Sent, Closed, Closed - Emailed, Closed - Mailed — a genuine invoice *workflow* state, not a payment state; none of them mean "Paid." `BILLING_STATUS_TO_INVOICE_STATUS` now maps "New"/"Review" → Draft and everything else → Sent (a judgment call — these are historical, already-issued invoices, so defaulting to Sent is safer than Draft for the ambiguous middle statuses like "Approved"). Separately, and regardless of that mapping, `invoice_status` is forced to `"Paid"` whenever `Billing_Log.Date_Paid` is set — see Section 5.9a for why that's the real payment signal, and where the backing `payments` row comes from.

**Implemented and working** in `etl_skeleton.py`'s `migrate_invoices()` — now excludes credit memos (`Billing_Type_ID='C'`, confirmed not real invoices, with real numbers confirmed round 25 — see Section 5.9b), including the `invoice_due` date-math replication (`Date_Invoice` + the client's `Billing_Terms.Due_Days`, joined via `Company` since `Billing_Log` doesn't carry its own terms), a get-or-create helper for `invoice_category_id` (ITFlow requires a non-null category with no default — a single "Migrated from ConnectWise" category is created and reused), and both product line items (from `IV_Product`) and time line items (from `Time_Entry`) with real quantity/price. Time_Entry's billing rate is now confirmed: `Hourly_Rate` (per-hour rate) × `Hours_Bill` (billed quantity — not always identical to `Hours_Actual`, CW rounds/adjusts some rows) is the line total, and `Extended_Invoice_Amount` is CW's own precomputed version of that same number — spot-checked against real rows and it matches exactly every time, so the script uses `Extended_Invoice_Amount` directly rather than recomputing, keeping migrated totals identical to what CW actually billed.

### 5.9a Payments ← `Billing_Application` (Payment_Type='P')

This whole entity exists because of a direct question you asked ("how are we going to mark invoices as paid") plus ITFlow support's own migration guidance, which was explicit: *"Invoices can be marked as paid directly, but the system expects a payment to be associated with the invoice for accounting to reconcile."* A status label alone isn't really what "Paid" is supposed to mean in ITFlow.

**How we got to the real answer (rounds 19–24):** round 19 confirmed `Billing_Status` is a workflow state, not a payment state, and ruled out four other candidate tables (`EX_Payment`, `Payment_Sources`, `Portal_Payment_Processor*`, `WisePay_Payments` — lookup tables, gateway config, or genuinely 0 rows). That left `Billing_Log.Date_Paid` looking like the real signal — until round 21 checked for a partial-payment column on `Billing_Log` and found nothing, which you then directly contradicted ("There are some invoices with partial payments... visible in ConnectWise itself"). Round 22 ruled out "multiple `Billing_Log` rows per invoice." **Round 23 found the real mechanism, read straight out of ConnectWise's own view SQL** (`OBJECT_DEFINITION` on `v_rpt_AccountsReceivableAging` and `v_InvoiceSearch`), not inferred: both compute `Paid_Amount` from **`dbo.Billing_Application`**, a table no keyword search caught because it's named "Billing_Application," not "Payment." That round also proved `Billing_Log.Date_Paid` is only ever set once an invoice reaches a *zero* balance — every confirmed partial invoice found (e.g. Billing_Log_RecID 1754, Invoice 2783, paid 15.60 of 60.00) has `Date_Paid = NULL`. **Round 24 confirmed `Billing_Application`'s real shape** and closed out the design: `Billing_Log_RecID`, `Applied_Amount`, `Payment_Type` (`'P'` = 1,485 rows, `'C'` = 15 rows), `Date_Applied` (a real per-payment date), `Payment_Source_RecID` (present but every sampled row was `0` — not yet a usable payment-method signal). Critically, zero invoices have more than one `'P'` application row in this data, so one ITFlow `payments` row per invoice is correct.

| Real CW column | ITFlow column (`payments`) | Notes |
|---|---|---|
| `SUM(Billing_Application.Applied_Amount) WHERE Payment_Type='P', GROUP BY Billing_Log_RecID` | `payment_amount` | The real cash amount actually applied — not `Invoice_Amount`. This is what fixes both the missing-partial-payments bug and the wrong-full-amount bug in one change |
| `MAX(Billing_Application.Date_Applied)` (same grouping), falling back to `Billing_Log.Date_Paid` | `payment_date` | Real per-payment date, not the invoice's own paid-in-full date |
| `Currency_RecID` → `Currency.Iso_Code` | `payment_currency_code` | Join required; falls back to `'GBP'` |
| — | `payment_method` | Still a placeholder — defaults to `'Bank Transfer'`. `Payment_Source_RecID` exists on `Billing_Application` but every sampled row was `0`; not a usable signal yet unless you actually track payment source day-to-day in ConnectWise |
| CW's own `Billing_Log_RecID` | `payment_reference` | For traceability |
| — | `payment_account_id` | **Your explicit call, overriding ITFlow support's suggested default:** posts to a single real `accounts` row — the one inbound bank account you actually reconcile both current and migrated invoices against. Set via `PAYMENT_ACCOUNT_NAME`, matched by exact name via `get_payment_account_id()`, which does **not** create the account if it's missing. **Action needed before Stage 8**: create/confirm that account exists in ITFlow (Finance > Accounts) |
| `Billing_Log_RecID` (via the invoice's own ID map) | `payment_invoice_id` | Requires Stage 7 (Invoices) to have already run |

**Implemented and working** in `etl_skeleton.py`'s `migrate_payments()`, run as Stage 8, sourced directly from `Billing_Application` rather than gated on `Billing_Log.Date_Paid`. Two things worth knowing, both judgment calls rather than confirmed with you directly — flag if either should work differently:

- **Credit-memo applications (`Payment_Type='C'`, 15 rows total) are deliberately NOT migrated as ITFlow `payments`.** A credit isn't cash that moved through `PAYMENT_ACCOUNT_NAME`'s real bank account, so including it would misstate that account's actual activity. Real example: Billing_Log_RecID 778 was paid in full through 485.20 cash + 110.00 credit — it migrates one `payments` row for 485.20 (the real cash), while `invoice_status` still correctly shows "Paid" (via `Date_Paid`, which does account for credits).
- **Credit-memo *invoices* themselves — `Billing_Log` rows with `Billing_Type_ID='C'` and a negative `Invoice_Amount`** — you confirmed directly these aren't real invoices. See Section 5.9b for the full resolution.

### 5.9b Credits ← `Billing_Log` credit memos (`Billing_Type_ID='C'`)

Credit memos (`Billing_Log` rows with `Billing_Type_ID='C'`, negative `Invoice_Amount`) are **not real invoices** — you confirmed this directly ("that is not an actual invoice") after an earlier version migrated them as regular negative-amount ITFlow invoices. Real example: Billing_Log_RecID 1149, "Parkway Derby LTD", `Invoice_Amount` −33,529.20, `Reference` "PWD001-2023-02CR". `migrate_invoices()` excludes them entirely (`WHERE Billing_Type_ID <> 'C'`).

You also told us exactly where they should go instead: *"Credits follow the invoicing numbering system and need a row and as a credit and then get applied against an invoice."* ITFlow has a real, purpose-built table for this — confirmed by reading `itflow_schema_reference.sql` directly: `credits` (`credit_amount`, `credit_type` enum, `credit_note`, `credit_client_id`, `credit_invoice_id` — a single nullable FK to one invoice, no FK constraint actually enforced in ITFlow's schema but the column itself is genuinely nullable, `credit_created_at`, `credit_created_by`).

**Round 25 confirmed every real number this design needed, and the implementation checks out against all of them:**

- **11 credit memos total**, confirmed via a full `Billing_Type_ID` breakdown of all of `Billing_Log` (1,301 Agreement-type, 441 Standard-type, 11 Credit-Memo-type) — not just the handful seen incidentally in earlier rounds, which turned out to already be the complete set.
- **Every one of the 11 is fully applied** — for each, the sum of its `Billing_Application` applications (`Payment_Type='C'`, grouped by `Credit_Memo_RecID`) matches its own `Invoice_Amount` magnitude exactly (e.g. Credit_Memo_RecID 1149: one application of 33,529.20 against invoice 1124, matching −33,529.20 exactly). No unapplied credits exist in the current data, though the code still correctly supports that state if one shows up later.
- **2 of the 11 really were split across more than one invoice** — exactly the edge case the code was built to handle defensively, now confirmed real rather than hypothetical: Credit_Memo_RecID 655 (−270.00) was applied as 90.00 each against three separate invoices (651, 673, 693), and Credit_Memo_RecID 741 (−270.00) the same way across invoices 707, 765, 793 — both read like a recurring monthly credit for the same client (reference prefix "HBL001") applied one month at a time rather than all at once. For both, `migrate_credits()` correctly leaves `credit_invoice_id` NULL and records all three applied-to invoices/amounts in `credit_note` instead, with a warning logged — verified against real data, not just reasoned through.

| Real CW column | ITFlow column (`credits`) | Notes |
|---|---|---|
| `ABS(Invoice_Amount)` | `credit_amount` | `Billing_Log` stores the amount negative; ITFlow's column is a plain magnitude |
| — | `credit_type` | Placeholder — defaults to `'manual'` (one of ITFlow's five enum values: prepaid/manual/refund/promotion/usage). `'refund'` may be more accurate for how these are actually used; not confirmed |
| `Invoice_Number` + `Reference` + `Internal_Note` + `Billing_Log_RecID` | `credit_note` | Free text — carries the ConnectWise credit memo's own invoice number (drawn from the same sequence as real invoices, per your note) so it stays traceable even though it never becomes an ITFlow invoice |
| `Date_Invoice` | `credit_created_at` | |
| — | `credit_created_by` | Defaults to `0` ("system"), same convention as `ticket_created_by` |
| `Billing_Application.Credit_Memo_RecID` → `Billing_Log_RecID` it was applied to | `credit_invoice_id` | **The real linkage, confirmed round 25.** Zero applications → `NULL` (doesn't occur in current data, but handled); exactly one invoice (9 of 11 credit memos) → linked via the invoice id map; more than one distinct invoice (2 of 11, confirmed real) → `NULL` with full detail in `credit_note`, logged loudly |
| `Company_RecID` | `credit_client_id` | Standard client id-map lookup |

**Implemented and confirmed** in `etl_skeleton.py`'s `migrate_credits()`, run as Stage 9 (after Stage 7, Invoices). Only remaining open point: `credit_type` is still a placeholder (`'manual'`) — worth changing to `'refund'` if that better matches how you think about these.

### 5.9c Quotes ← `SO_Opportunity` (+ `Order_Header` where linked)

The Opportunity record itself is the quote, the whole way through its life — confirmed by walking through a live example (Opportunity 368 / Order_Header 335, "Truline Bitwarden") in the ConnectWise UI together. **Hybrid model**: every `SO_Opportunity` row migrates as a quote; the subset with a linked `Order_Header` also gets real pricing and line items.

| Real CW column | ITFlow column (`quotes`) | Notes |
|---|---|---|
| `Opportunity_RecID` | `quote_number` | Reused as-is for traceability — same no-synthetic-prefix approach as invoices, see item 9 below |
| `Opportunity_Name` (fallback: synthesized "ConnectWise Opportunity #n") | `quote_scope` | A real `quotes` column that was already being fetched from CW (`Opportunity_Name`) but never actually used anywhere |
| `SO_Opp_Status_RecID` → `SO_Opp_Status.Description` | `quote_status` | Join required. Column name and full value set (Open, Won, Lost, No Decision, Recommendation, Order Approved, Lost - Price, Lost - Time) confirmed directly by round 18's `COLUMN_NAME` query |
| `Date_Became_Lead` | `quote_date` | |
| `Date_Close_Expected` | `quote_expire` | |
| `Notes` | `quote_note` | |
| `Currency_RecID` → `Currency.Iso_Code` | `quote_currency_code` | Join required; falls back to `'GBP'` when missing |
| `Company_RecID` | `quote_client_id` | Via ID map (client, falling back to vendor) |
| — | `quote_category_id` | No CW equivalent — same get-or-create pattern as `invoice_category_id`, a single "Migrated from ConnectWise" `Quote`-type category, since ITFlow requires this non-null with no default |
| — | `quote_url_key` | Random 200-character string, same reasoning as `invoice_url_key`/`ticket_url_key` |
| `Order_Header.Total` (via linked `Order_Header`, if any) | `quote_amount` | `0` when no linked order exists (unpriced quote) |

Line items (`quote_items`): sourced from `IV_Product` filtered by `Order_Header_RecID`, the exact same pattern already confirmed for invoice product line items (`Description`/`Quantity`/`Unit_Price`) — only present for quotes with a linked order.

`SO_Opportunity.Estimated_Total` and `old_Actual_Total` are **not** used — real sample data showed `Estimated_Total = 0.00` on a genuinely active, real-value quote (the "Truline Bitwarden" example), and the `old_` prefix on several related columns (`old_Close_Flag`, `old_Won_Flag`, `old_Competitor`, `old_Close_Notes`) signals they're legacy, superseded by `Order_Header`'s totals for anything that's actually been priced.

`SO_Opp_Status.Description` as the `quote_status` source is now **directly confirmed** (round 18's `COLUMN_NAME` query), not just the strong inference it was before — the confirmed value set is Open, Won, Lost, No Decision, Recommendation, Order Approved, Lost - Price, Lost - Time, matching what the earlier data-only sample implied exactly.

**Implemented and working** in `etl_skeleton.py`'s `migrate_quotes()`.

### 5.10 Attachments ← `DM_Document` + `DM_Relationship`

`DM_Relationship` is a polymorphic link: one row per attachment-to-parent link, with an `Attached_Record_Type` label (`"Company"`, `"Service"`, etc.) indicating which of its many nullable `*_RecID` columns is actually populated for that row (`Company_RecID`, `Config_RecID`, `SR_Service_RecID`, `Contact_RecID`, `Billing_Log_RecID`, and more).

| Real CW column | Notes |
|---|---|
| `DM_Document.Title` | File display name |
| `DM_Document.Filename` / `ServerFilename` / `Path` | Needed to locate and download the actual binary — this is a file-transfer step, not just a database copy |
| `DM_Relationship.Attached_Record_Type` | Tells you which join table to populate in ITFlow |

ITFlow's file-join tables cover contacts, vendors, software, and tickets, but **not clients directly** — a `DM_Relationship` row with `Attached_Record_Type = 'Company'` has no matching ITFlow join table. Those will need to land as ITFlow `documents` (which do belong directly to a `client_id`) rather than as raw `files`.

### 5.11 Custom fields

Not yet started — lower priority than the core entities above. Each entity's custom fields live in a `*_User_Defined_Field_Value` table (e.g. `Company_User_Defined_Field_Value`, `Contact_User_Defined_Field_Value`, `Config_User_Defined_Field_Value`, `Time_Entry_User_Defined_Field_Value`), which is a reasonably standard ConnectWise pattern — should be straightforward to confirm once the core entities are working.

## 6. Still-open items

Resolved: `Communication_Type`'s columns, `SR_Config` as the ticket↔asset link, the `SR_Detail`/`Time_Entry` visibility rule (`InternalAnalysis_Flag`), asset type (resolved as a decision, not a schema finding), the SLA tier-collapsing rule, the `Time_Entry` billing rate, technician mapping, the quotes design/source (`SO_Opportunity` + `Order_Header` hybrid), `SO_Opp_Status.Description` as the quote-status column, how invoices get marked Paid, a critical `ticket_created_by` bug, `ticket_priority` vocabulary, `ticket_reply_type`/`invoice_status` casing and vocabulary, missing `url_key` fields, `ticket_resolved_at`, `EX_Payment`/`WisePay_Payments`/`GL_Entry`/`GL_EntryDtl` all ruled out as customer-payment sources, which ITFlow account migrated payments post against, full-vs-partial payment (item 22), credit-memo invoices (item 23, now fully confirmed with real numbers — round 25), and the id-map crash-safety gap (item 24). What's left:

1. ~~Where asset *type* is actually tracked~~ — **resolved by your call, not by finding the column**: it's likely a `Config` custom field via `User_Defined_Field`/`Config_User_Defined_Field_Value` (or lives in ConnectWise Automate, which syncs devices into Manage and owns this classification), but rather than migrate a guess, `asset_type` is left unpopulated in `migrate_assets()` — every ITFlow asset lands with no type set, to be categorized manually after migration. Automate DB access is potentially available for a future pass if you want to revisit this.
2. **"Do Not Respond" priority tickets** — business decision on how these should land in ITFlow (active, closed, or excluded). Not blocking: `migrate_tickets()` currently migrates them like any other ticket.
3. ~~Product/expense invoice line items~~ — **resolved for products**: `IV_Product` follows the identical `Billing_Log_RecID`/`SR_Service_RecID` pattern as `Time_Entry`, and is fully implemented. Expenses not yet confirmed, still low priority.
4. ~~Technician (`Member`) → ITFlow user mapping~~ — **resolved by your update: only 2 technicians exist in this ConnectWise instance.** `migrate_members()` now matches `Member.Email_Address` to an existing ITFlow `users.user_email` (case-insensitive) and records the id mapping — it deliberately does **not** create new ITFlow user accounts, since `users.user_password` is a required hashed-password column and generating a throwaway login from a migration script is the wrong way to hand out PSA access. **Action needed before running:** both technicians need a real ITFlow login already created (the normal way, through the app) with an email address matching their ConnectWise `Email_Address`, or the mapping will log a warning and skip them (everything downstream still degrades to "unassigned" rather than erroring, so this doesn't block a run either way).
5. **`Manufacturer_RecID` vs. `Mfg_Company_RecID`** on `Config` — likely redundant, worth a quick spot-check.
6. **Country lookup** for `locations.location_country` — small table, not yet pulled.
7. ~~The SLA 5-tier → 3-tier collapsing rule for contracts~~ — **resolved**: implemented in `migrate_contracts()` via `URGENCY_TO_SLA_TIER`, Critical and High both collapse to "high," Critical wins on tie.
8. ~~`Time_Entry`'s billing rate/amount column~~ — **resolved**: `Hourly_Rate` × `Hours_Bill` = `Extended_Invoice_Amount`, confirmed against real invoiced rows. `migrate_invoices()` now prices time-based line items using the real numbers.
9. **Sage invoice migration coexistence** — you're migrating older historical invoices from Sage into ITFlow separately, via your own script, before this one runs. `invoice_number`/`invoice_prefix` now migrate exactly as ConnectWise has them — **by your explicit instruction, no synthetic prefix is added**, even though an earlier version of this script did add `'CW-'` specifically to avoid ambiguity with Sage's invoice numbers. Since that's been removed, if a Sage-migrated invoice and a ConnectWise-migrated invoice ever land on the same `invoice_number`, ITFlow has nothing distinguishing them apart from client/date/whichever migration ran first — worth a quick check that your Sage and ConnectWise invoice number ranges genuinely don't overlap before running this for real. Also still worth confirming that script's migration order relative to this one — specifically, that it either reuses this script's client/location id mapping (via the same `migration_id_map` SQLite database) or matches clients by name/some other reliable key, so Sage invoices don't end up attached to duplicate client records.
10. **Proactive board deletion** — you're deleting the "Managed Service - Proactive Alerts" board and its tickets from ConnectWise entirely before running this migration. The script's `WHERE SR_Board_RecID = REACTIVE_BOARD_RECID` filter stays in place regardless, as a harmless safety net (see `etl_skeleton.py` comment above `REACTIVE_BOARD_RECID`) — it costs nothing and protects against a partial cleanup or running against a pre-cleanup copy of the database by mistake.
11. ~~Currency~~ — **resolved**: confirmed **GBP**, not USD. `migrate_clients()`, `migrate_invoices()`, and `migrate_quotes()` all default to `'GBP'` when `Currency_RecID` doesn't resolve to a code (real currency data is always used when present — this only affects rows where it's genuinely missing).
12. ~~`SO_Opp_Status.Description` column name~~ — **resolved**: directly confirmed via `COLUMN_NAME` query. `Description` is real, and the full status value set is Open, Won, Lost, No Decision, Recommendation, Order Approved, Lost - Price, Lost - Time — matching the earlier inference exactly.
13. ~~How invoices get marked Paid~~ — **resolved**: round 19 confirmed `Billing_Status` is a workflow state, not a payment state, and found `Billing_Log.Date_Paid` as the real payment signal, with no separate AR/cash-receipts table in this instance. ITFlow support's own migration guidance confirmed the exact `invoice_status` vocabulary (Draft/Sent/Paid/Cancelled) and that a real `payments` row should back "Paid." Both `migrate_invoices()`'s status logic and `migrate_payments()` (Stage 8) are implemented — see Section 5.9/5.9a.
14. **`ticket_created_by` was silently missing from every ticket insert — fixed, but worth flagging why it mattered.** ITFlow's `tickets.ticket_created_by` column is `NOT NULL` with no `DEFAULT`. The original `migrate_tickets()` never set it, which is fine under lenient SQL modes but is a hard "doesn't have a default value" error under MySQL/MariaDB's default *strict* mode — almost certainly what a fresh ITFlow install runs. This would have meant Stage 6 failing outright on the very first ticket, not a partial/degraded migration. Caught by cross-checking the schema against ITFlow support's own example INSERT statement, not by running against a real database. Now set to `0` ("system"), which ITFlow support confirmed is an acceptable value for this field.
15. ~~`ticket_priority` vocabulary~~ — **resolved**: was passing CW's raw 5-tier text through unchanged; ITFlow support confirmed the app only expects "Low"/"Medium"/"High". Fixed via `URGENCY_TO_TICKET_PRIORITY`, same collapse pattern as the SLA tier logic, tied to the same open "Do Not Respond" judgment call as item 2.
16. ~~`ticket_reply_type` / `invoice_status` casing and vocabulary~~ — **resolved**: ticket replies now write exactly `"Internal"`/`"Public"` (was lowercase); invoice status now constrained to ITFlow's real 4-value set instead of raw ConnectWise text.
17. **`ticket_reply_type`'s third value, `'Client'` — not yet implemented.** ITFlow support's guidance revealed replies can be `Internal`, `Public`, or `Client` (the client's own submission) — this migration only does the 2-way Internal/Public split, so client-submitted replies currently look identical to agent public replies. `Internal_Member_Flag`/`External_Source_Flag` (ruled out earlier as the *visibility* signal) haven't been ruled out as the *authorship* signal for this — worth a real-data discovery round before attempting it, not a guess.
18. ~~`ticket_resolved_at`~~ — **resolved (round 20)**: `SR_Service`'s full column list confirms CW has no separate resolved-vs-closed concept in this instance. `migrate_tickets()` now sets it equal to `Date_Closed` (judgment call, documented in the function's docstring).
19. **`url_key` fields (`ticket_url_key`, `invoice_url_key`, `quote_url_key`) were never populated** — nullable, so nothing errored, but ITFlow support confirmed these need a random 200-character string for portal/share links to work. All three now generate one per row via `generate_url_key()`.
20. ~~`EX_Payment`~~ — **resolved, ruled out**: round 20 confirmed both its real columns and row count. Its columns (`Description`, `Default_Flag`, plus standard audit columns) match the exact shape of every other CW lookup table in this project (`SO_Opp_Status`, `Billing_Status`, etc.) rather than a transaction table — and it has **0 rows** regardless, so it's irrelevant either way. `EX_Payment` is not a source of any payment data.
21. ~~`WisePay_Payments`~~ — **resolved, ruled out**: its columns confirm this is genuinely CW's real online-payment transaction table (`Payment_Date_UTC`, `Batch_Payment_Amount`, `WisePay_Reference`), but it has **0 rows** — this MSP has never taken a payment through it. With every candidate from round 19's search now ruled out, `Billing_Log.Date_Paid` isn't just the best payment signal found in this ConnectWise instance, it's confirmed to be the only one that exists.
22. ~~Full vs. partial payment~~ — **resolved and implemented.** Round 21 (checking `Billing_Log`'s own columns) found nothing; you confirmed partial payments are real anyway. Rounds 22–24 traced it to the real mechanism: `dbo.Billing_Application` (`Billing_Log_RecID`, `Applied_Amount`, `Payment_Type` `'P'`/`'C'`, `Date_Applied`) is what every ConnectWise paid-amount figure is actually computed from, confirmed via ConnectWise's own view SQL and a real example (Invoice 2783, paid 15.60 of 60.00, `Date_Paid` NULL — proving `Date_Paid` is only ever set on full payoff, so the old `Date_Paid`-gated query was silently skipping every partially-paid invoice, not just mis-amounting them). `migrate_payments()` now queries `Billing_Application` directly (`Payment_Type='P'`) for both `payment_amount` and `payment_date`, covering full and partial payments correctly. `GL_Entry`/`GL_EntryDtl` were ruled out along the way (general-ledger/costing records, not a payment ledger). Two judgment calls made, not separately confirmed with you: `Payment_Type='C'` credit-memo applications are excluded from `payments` (not real cash through the bank account); `Payment_Source_RecID` (always `0` in the sample checked) isn't yet used for `payment_method`. See Section 5.9a.
23. ~~Credit-memo invoices (`Billing_Type_ID='C'`, negative `Invoice_Amount`)~~ — **fully resolved and confirmed with real numbers (round 25).** Found while designing item 22's fix; you confirmed directly these are not real invoices (real example: Billing_Log_RecID 1149, "Parkway Derby LTD", −33,529.20, reference "PWD001-2023-02CR"), then told us exactly where they should go instead: *"Credits follow the invoicing numbering system and need a row and as a credit and then get applied against an invoice."* `migrate_invoices()` excludes them entirely; `migrate_credits()` (Stage 9) migrates them into ITFlow's dedicated `credits` table instead. Round 25 confirmed all 11 real credit memos exist, all 11 are fully applied, and 2 of the 11 (Credit_Memo_RecID 655 and 741, both split across exactly 3 invoices each) genuinely exercise the multi-invoice edge case the code was built to handle — verified against real data, not just reasoned through. See Section 5.9b. Only remaining point: `credit_type` is a placeholder (`'manual'`), not confirmed against how you actually categorize these.
24. ~~Id-map/ITFlow commit ordering — crash-safety gap~~ — **resolved.** Every `migrate_*` function called `record_mapping()` per row (which committed the SQLite id-map immediately) but only committed the actual ITFlow insert once, at the end of the whole stage. A crash between those two points would leave the id-map durably believing rows were migrated that ITFlow never received — and `already_migrated()` would then skip re-migrating them on every future run, forever, with no error. Not tied to any specific entity — it applied identically to all stages that call `record_mapping()`. Fixed by removing the commit from `record_mapping()` itself and committing the id-map immediately after each stage's real `itflow_conn.commit()`, so a crash between the two now only risks the same small window every dual-database write has, not a per-row window repeated across an entire stage. `migrate_members()` (which never writes to ITFlow, only maps existing users) gets its own explicit final commit for the same reason. Worth being aware of even after the fix: if a stage does crash mid-run, some ITFlow rows from that stage may exist without a matching id-map entry (since ITFlow's commit — and now the id-map's — only happens at the very end) — re-running the stage will re-attempt those rows. That's a duplicate-row risk on retry after a crash, not silent loss, and most inserts either have natural uniqueness (invoice/quote/ticket numbers) or `INSERT IGNORE` (asset↔ticket links) — worth a manual check of row counts against `stats.extracted` after any run that didn't finish cleanly, before re-running the same stage.
25. ~~Which ITFlow account migrated payments post against~~ — **resolved by your explicit call, overriding the earlier default.** ITFlow support's guidance suggested a separate dedicated "migration" account; you want the opposite — every payment, migrated and current/future alike, posted against the ONE real inbound bank account you actually use, matching your real process. `get_or_create_account()` (which would auto-create a account by name) has been replaced with `get_payment_account_id()`, a strict lookup that does NOT create anything — it requires that account to already exist in ITFlow. **Action needed before Stage 8**: create/confirm that account in ITFlow (Finance > Accounts) and set `PAYMENT_ACCOUNT_NAME` in `etl_skeleton.py` (or the `ITFLOW_PAYMENT_ACCOUNT_NAME` env var) to its exact name — the script now fails fast with a clear error if the name doesn't match, rather than risk creating or posting against the wrong account.

## 7. Where to run the script

**Confirmed: ITFlow is self-hosted on the same local network as ConnectWise's on-prem SQL Server.** That's the simplest case — no VPN or firewall changes should be needed between the two.

The script needs live, simultaneous network access to both databases from whichever single machine runs it: for every batch of rows it reads from ConnectWise and writes to ITFlow within the same function call, so it can't be split across two machines without a rewrite. Concretely: outbound TCP access to ConnectWise's SQL Server (default port 1433) and access to ITFlow's MySQL (default port 3306) at the same time.

**Recommended: run it directly on the ITFlow server itself**, if it's a machine you can get a shell on (ITFlow is a standard LAMP-style PHP/MySQL app, so this is usually straightforward). That gets MySQL access for free over `localhost` — no firewall rule or MySQL user grant needed for that side — and only needs one thing confirmed: that the ITFlow server can reach the ConnectWise SQL Server's IP and port 1433 across your LAN. If there's any VLAN segmentation between the two, even calling it "the same network," that's worth a quick connectivity test first (e.g. `Test-NetConnection <cw-server-ip> -Port 1433` from the ITFlow box, or `telnet <cw-server-ip> 1433`) before running Stage 1 for real.

If running directly on the ITFlow server isn't practical, the alternative is any other machine on the same LAN — a workstation, a small VM — as long as ITFlow's MySQL is configured to accept remote connections from that machine specifically (check the MySQL user's host grant, e.g. `'migration_user'@'192.168.1.50'` rather than `'migration_user'@'localhost'`, and that `bind-address` in MySQL's config isn't restricted to `127.0.0.1` only).

Either way, before running anything for real:

1. Install the Python dependencies on that machine: `pip install pyodbc mysql-connector-python --break-system-packages` (or `pymssql` instead of `pyodbc`, matching whichever SQL Server driver is actually installed there), plus the ODBC Driver 18 for SQL Server if using `pyodbc`.
2. Set the 8 required environment variables (`CW_DB_SERVER`, `CW_DB_NAME`, `CW_DB_USER`, `CW_DB_PASSWORD`, `ITFLOW_DB_HOST`, `ITFLOW_DB_NAME`, `ITFLOW_DB_USER`, `ITFLOW_DB_PASSWORD`) — see the example block at the top of `etl_skeleton.py`. Credentials are read from the environment now, not typed into the file, since this script may end up living on a real server. The script fails fast with a clear error listing exactly which ones are missing if you forget any. A 9th, `ITFLOW_PAYMENT_ACCOUNT_NAME`, is only required for Stage 8 (Payments) — see step 5 below.
3. For the ConnectWise side, a read-only SQL login is worth creating specifically for this script rather than reusing an admin account, since it only ever runs `SELECT` queries against ConnectWise.
4. Run `python etl_skeleton.py --list-stages` first — it only prints the stage list and touches nothing, a safe way to confirm the script itself is wired up correctly before touching real data.
5. **Before running Stage 8 (Payments)**: create or confirm the real ITFlow account that both migrated and current/future invoice payments should post against (Finance > Accounts) — the one inbound bank account you actually reconcile against, per your call. Set `ITFLOW_PAYMENT_ACCOUNT_NAME` to that account's exact name. `migrate_payments()` looks it up and stops with a clear error rather than guessing or creating a new account if the name doesn't match.
6. **Take a fresh, verified ITFlow database backup immediately before the first real `--all` or per-stage run — separate from the daily 02:00 systemd backup.** The backup strategy decided alongside this plan (see the `itflow-backup-strategy` project doc) gives ITFlow a real transactionally-consistent `mysqldump`, but its own open items note the restore path hasn't actually been tested yet. Don't let this migration be the first time that restore process gets exercised for real — run one manual backup right before Stage 1, and ideally do a throwaway restore-to-scratch-database test at least once beforehand so you know the restore steps work, not just that the dump completes. This matters most for Stages 7/8/9 (Invoices, Payments, Credits), which write real financial data and, for Stage 8, post against a real bank account.

## 8. Validation checklist (unchanged from first draft, still applies)

- Row counts per entity roughly match expectations (105 clients, ~1,600 contacts, 541 assets, 8,475 Reactive tickets, 103 contracts, ~1,742 invoices, 11 credits).
- Foreign key integrity — no orphaned `tickets.ticket_client_id`, `contacts.contact_client_id`, etc.
- Every CW ticket status and priority value has a corresponding ITFlow row.
- Client portal users can log in and see only their own data.
- Time totals per client roughly match CW's billed/actual hours for a sample period.
- Attachments open correctly, not just that a `files`/`documents` row exists.
- A final client-by-client walkthrough before decommissioning ConnectWise access.
