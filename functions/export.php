<?php

/*
 * ITFlow - Export helpers
 *
 * Shared plumbing behind the list page export modals:
 *   - a per-export column registry (label, source field, formatting, default state)
 *   - the column picker that every export modal renders
 *   - the CSV and PDF writers
 *
 * Handlers keep owning their own filter parsing and SQL. They hand rows over one
 * at a time, in the same loop the old fputcsv() call sat in:
 *
 *     $export = beginExport('assets', $_POST['export_assets'], "$file_name_prepend" . 'Assets', 'Assets', $filter_summary);
 *     while ($row = mysqli_fetch_assoc($sql)) {
 *         addExportRow($export, $row);
 *     }
 *     finishExport($export);
 *
 * A column's 'field' is just a key on the row array handed to addExportRow(), so a
 * handler that needs a computed value (a prefixed number, a decrypted secret, a
 * status word) sets that key on $row before the call - see getExportColumns() notes.
 */

// PDF is a read-and-print format, not a bulk transport. Past this many rows TCPDF's
// HTML table builder gets slow enough to walk into max_execution_time, so the
// handlers bounce the request back to CSV instead of timing out mid-download.
DEFINE("EXPORT_PDF_MAX_ROWS", 2000);

/*
 * The column registry - one entry per export, in the order columns should appear.
 *
 *   'label'   Column heading in the CSV / PDF.
 *   'field'   Key on the row array. Defaults to the column key itself.
 *   'format'  '' (raw), 'phone', 'money', 'number'. Only affects presentation.
 *   'default' false to leave the box unticked when the modal opens. Defaults true.
 *   'weight'  Relative PDF column width, default 1. Bump it for long free text.
 *
 * Column keys are what the modal posts back, so they are whitelisted against this
 * registry on the way in - an unknown key is dropped, not queried.
 */
function getExportColumns($export_type) {

    $registry = [

        // Clients - handler joins the primary contact and primary location
        'clients' => [
            'client_name'          => ['label' => 'Client Name', 'weight' => 2],
            'client_type'          => ['label' => 'Industry'],
            'client_referral'      => ['label' => 'Referral'],
            'client_website'       => ['label' => 'Website', 'weight' => 2],
            'location_name'        => ['label' => 'Primary Location Name'],
            'location_phone'       => ['label' => 'Location Phone', 'format' => 'phone'],
            'location_address'     => ['label' => 'Location Address', 'weight' => 2],
            'location_city'        => ['label' => 'City'],
            'location_state'       => ['label' => 'State'],
            'location_zip'         => ['label' => 'Postal Code'],
            'location_country'     => ['label' => 'Country'],
            'contact_name'         => ['label' => 'Primary Contact Name'],
            'contact_title'        => ['label' => 'Title'],
            'contact_phone'        => ['label' => 'Contact Phone', 'format' => 'phone'],
            'contact_extension'    => ['label' => 'Extension'],
            'contact_mobile'       => ['label' => 'Contact Mobile', 'format' => 'phone'],
            'contact_email'        => ['label' => 'Contact Email', 'weight' => 2],
            'client_rate'          => ['label' => 'Hourly Rate', 'format' => 'money'],
            'client_currency_code' => ['label' => 'Currency'],
            'client_net_terms'     => ['label' => 'Payment Terms', 'format' => 'number'],
            'client_tax_id_number' => ['label' => 'Tax ID'],
            'client_abbreviation'  => ['label' => 'Abbreviation'],
        ],

        'contacts' => [
            'contact_name'       => ['label' => 'Name', 'weight' => 2],
            'contact_title'      => ['label' => 'Title'],
            'contact_department' => ['label' => 'Department'],
            'contact_email'      => ['label' => 'Email', 'weight' => 2],
            'contact_phone'      => ['label' => 'Phone', 'format' => 'phone'],
            'contact_extension'  => ['label' => 'Ext'],
            'contact_mobile'     => ['label' => 'Mobile', 'format' => 'phone'],
            'location_name'      => ['label' => 'Location'],
        ],

        'locations' => [
            'location_name'        => ['label' => 'Name', 'weight' => 2],
            'location_description' => ['label' => 'Description', 'weight' => 3],
            'location_address'     => ['label' => 'Address', 'weight' => 2],
            'location_city'        => ['label' => 'City'],
            'location_state'       => ['label' => 'State'],
            'location_zip'         => ['label' => 'Postal Code'],
            'location_phone'       => ['label' => 'Phone', 'format' => 'phone'],
            'location_hours'       => ['label' => 'Hours'],
        ],

        'vendors' => [
            'vendor_name'           => ['label' => 'Name', 'weight' => 2],
            'vendor_description'    => ['label' => 'Description', 'weight' => 3],
            'vendor_contact_name'   => ['label' => 'Contact Name'],
            'vendor_phone'          => ['label' => 'Phone', 'format' => 'phone'],
            'vendor_website'        => ['label' => 'Website', 'weight' => 2],
            'vendor_account_number' => ['label' => 'Account Number'],
            'vendor_notes'          => ['label' => 'Notes', 'weight' => 3],
        ],

        'assets' => [
            'asset_name'              => ['label' => 'Name', 'weight' => 2],
            'asset_description'       => ['label' => 'Description', 'weight' => 3],
            'asset_type'              => ['label' => 'Type'],
            'asset_make'              => ['label' => 'Make'],
            'asset_model'             => ['label' => 'Model'],
            'asset_serial'            => ['label' => 'Serial Number'],
            'asset_os'                => ['label' => 'Operating System', 'weight' => 2],
            'asset_purchase_date'     => ['label' => 'Purchase Date'],
            'asset_warranty_expire'   => ['label' => 'Warranty Expire'],
            'asset_install_date'      => ['label' => 'Install Date'],
            'contact_name'            => ['label' => 'Assigned To'],
            'location_name'           => ['label' => 'Location'],
            'asset_physical_location' => ['label' => 'Physical Location'],
            'asset_notes'             => ['label' => 'Notes', 'weight' => 3],
            // Available from the handler's join but not exported historically
            'client_name'             => ['label' => 'Client', 'default' => false],
            'interface_ip'            => ['label' => 'Primary IP', 'default' => false],
            'interface_mac'           => ['label' => 'Primary MAC', 'default' => false],
        ],

        'asset_interfaces' => [
            'interface_name'        => ['label' => 'Name'],
            'interface_description' => ['label' => 'Description', 'weight' => 3],
            'interface_type'        => ['label' => 'Type'],
            'interface_mac'         => ['label' => 'MAC'],
            'interface_ip'          => ['label' => 'IP'],
            'interface_nat_ip'      => ['label' => 'NAT IP'],
            'interface_ipv6'        => ['label' => 'IPv6', 'weight' => 2],
            'network_name'          => ['label' => 'Network'],
        ],

        'networks' => [
            'network_name'          => ['label' => 'Name', 'weight' => 2],
            'network_description'   => ['label' => 'Description', 'weight' => 3],
            'network_vlan'          => ['label' => 'VLAN'],
            'network'               => ['label' => 'Network (CIDR)'],
            'network_gateway'       => ['label' => 'Gateway'],
            'network_dhcp_range'    => ['label' => 'IP Range'],
            'network_primary_dns'   => ['label' => 'Primary DNS'],
            'network_secondary_dns' => ['label' => 'Secondary DNS'],
        ],

        'certificates' => [
            'certificate_name'        => ['label' => 'Name', 'weight' => 2],
            'certificate_description' => ['label' => 'Description', 'weight' => 3],
            'certificate_domain'      => ['label' => 'Domain', 'weight' => 2],
            'certificate_issued_by'   => ['label' => 'Issuer', 'weight' => 2],
            'certificate_expire'      => ['label' => 'Expiration Date'],
        ],

        'domains' => [
            'domain_name'        => ['label' => 'Domain', 'weight' => 2],
            'domain_description' => ['label' => 'Description', 'weight' => 3],
            // The columns hold vendor IDs; the handler aliases the joined names
            'domain_registrar'   => ['label' => 'Registrar', 'field' => 'domain_registrar_name'],
            'domain_webhost'     => ['label' => 'Web Host', 'field' => 'domain_webhost_name'],
            'domain_expire'      => ['label' => 'Expiration Date'],
        ],

        // Defaults match what this export has always emitted, secrets included.
        // The handler decrypts into credential_username / credential_password.
        'credentials' => [
            'credential_name'        => ['label' => 'Name', 'weight' => 2],
            'credential_description' => ['label' => 'Description', 'weight' => 3],
            'credential_username'    => ['label' => 'Username'],
            'credential_password'    => ['label' => 'Password'],
            'credential_otp_secret'  => ['label' => 'TOTP'],
            'credential_uri'         => ['label' => 'URI', 'weight' => 2],
        ],

        // assigned_to_assets / assigned_to_contacts are built by the handler
        'software' => [
            'software_name'         => ['label' => 'Name', 'weight' => 2],
            'software_version'      => ['label' => 'Version'],
            'software_description'  => ['label' => 'Description', 'weight' => 3],
            'software_type'         => ['label' => 'Type'],
            'software_license_type' => ['label' => 'License Type'],
            'software_seats'        => ['label' => 'Seats', 'format' => 'number'],
            'software_key'          => ['label' => 'Key', 'weight' => 2],
            'assigned_to_assets'    => ['label' => 'Assets', 'weight' => 2],
            'assigned_to_contacts'  => ['label' => 'Contacts', 'weight' => 2],
            'software_purchase'     => ['label' => 'Purchased'],
            'software_expire'       => ['label' => 'Expires'],
            'software_notes'        => ['label' => 'Notes', 'weight' => 3],
        ],

        // ticket_number_display is prefixed by the handler
        'tickets' => [
            'ticket_number_display' => ['label' => 'Ticket Number'],
            'ticket_priority'       => ['label' => 'Priority'],
            'ticket_status_name'    => ['label' => 'Status'],
            'ticket_subject'        => ['label' => 'Subject', 'weight' => 3],
            'ticket_created_at'     => ['label' => 'Date Opened'],
            'ticket_resolved_at'    => ['label' => 'Date Resolved'],
            'ticket_closed_at'      => ['label' => 'Date Closed'],
            'client_name'           => ['label' => 'Client', 'default' => false],
            'contact_name'          => ['label' => 'Contact', 'default' => false],
            'ticket_assigned_to'    => ['label' => 'Assigned To', 'default' => false],
            'ticket_category_name'  => ['label' => 'Category', 'default' => false],
            'ticket_billable'       => ['label' => 'Billable', 'default' => false],
        ],

        // invoice_number_display is prefix . number, built by the handler
        'invoices' => [
            'invoice_number_display' => ['label' => 'Invoice Number'],
            'invoice_scope'          => ['label' => 'Scope', 'weight' => 3],
            'invoice_amount'         => ['label' => 'Amount', 'format' => 'money'],
            'invoice_date'           => ['label' => 'Issued Date'],
            'invoice_due'            => ['label' => 'Due Date'],
            'invoice_status'         => ['label' => 'Status'],
            'client_name'            => ['label' => 'Client', 'weight' => 2],
            'invoice_currency_code'  => ['label' => 'Currency', 'default' => false],
            'amount_paid'            => ['label' => 'Paid', 'format' => 'money', 'default' => false],
            'invoice_balance'        => ['label' => 'Balance', 'format' => 'money', 'default' => false],
        ],

        'quotes' => [
            'quote_number_display' => ['label' => 'Quote Number'],
            'quote_scope'          => ['label' => 'Scope', 'weight' => 3],
            'quote_amount'         => ['label' => 'Amount', 'format' => 'money'],
            'quote_date'           => ['label' => 'Date'],
            'quote_status'         => ['label' => 'Status'],
            'client_name'          => ['label' => 'Client', 'weight' => 2, 'default' => false],
        ],

        'recurring_invoices' => [
            'recurring_invoice_number_display' => ['label' => 'Recurring Number'],
            'recurring_invoice_scope'          => ['label' => 'Scope', 'weight' => 3],
            'recurring_invoice_amount'         => ['label' => 'Amount', 'format' => 'money'],
            'recurring_invoice_frequency_display' => ['label' => 'Frequency'],
            'recurring_invoice_created_at'     => ['label' => 'Date Created'],
            'client_name'                      => ['label' => 'Client', 'weight' => 2, 'default' => false],
        ],

        'products' => [
            'product_name'          => ['label' => 'Product', 'weight' => 2],
            'product_description'   => ['label' => 'Description', 'weight' => 3],
            'product_price'         => ['label' => 'Price', 'format' => 'money'],
            'product_currency_code' => ['label' => 'Currency'],
            'category_name'         => ['label' => 'Category'],
            'tax_name'              => ['label' => 'Tax'],
        ],

        'expenses' => [
            'expense_date'        => ['label' => 'Date'],
            'expense_amount'      => ['label' => 'Amount', 'format' => 'money'],
            'vendor_name'         => ['label' => 'Vendor', 'weight' => 2],
            'expense_description' => ['label' => 'Description', 'weight' => 3],
            'category_name'       => ['label' => 'Category'],
            'account_name'        => ['label' => 'Account'],
            'client_name'         => ['label' => 'Client', 'weight' => 2, 'default' => false],
            'expense_reference'   => ['label' => 'Reference', 'default' => false],
        ],

        'income' => [
            'income_date'          => ['label' => 'Date'],
            'income_type'          => ['label' => 'Type'],
            'income_source'        => ['label' => 'Source', 'weight' => 3],
            'income_category'      => ['label' => 'Category'],
            'income_client'        => ['label' => 'Client', 'weight' => 2],
            'income_amount'        => ['label' => 'Amount', 'format' => 'money'],
            'income_currency_code' => ['label' => 'Currency'],
            'income_method'        => ['label' => 'Payment Method'],
            'income_reference'     => ['label' => 'Reference'],
            'income_account'       => ['label' => 'Account'],
        ],

        'transactions' => [
            'transaction_date'           => ['label' => 'Date'],
            'transaction_type'           => ['label' => 'Type'],
            'transaction_description'    => ['label' => 'Description', 'weight' => 3],
            'transaction_other_account'  => ['label' => 'Transfer Account'],
            'transaction_reference'      => ['label' => 'Reference'],
            'transaction_category'       => ['label' => 'Category'],
            'transaction_payment_method' => ['label' => 'Payment Method'],
            'transaction_amount'         => ['label' => 'Amount', 'format' => 'money'],
            'transaction_balance'        => ['label' => 'Balance', 'format' => 'money'],
        ],

        'trips' => [
            'trip_date'        => ['label' => 'Date'],
            'trip_purpose'     => ['label' => 'Purpose', 'weight' => 3],
            'trip_source'      => ['label' => 'Source', 'weight' => 2],
            'trip_destination' => ['label' => 'Destination', 'weight' => 2],
            'trip_miles'       => ['label' => 'Miles', 'format' => 'number'],
            'client_name'      => ['label' => 'Client', 'weight' => 2, 'default' => false],
        ],

        // user_status_display is the status word, built by the handler
        'users' => [
            'user_name'          => ['label' => 'Name', 'weight' => 2],
            'user_email'         => ['label' => 'Email', 'weight' => 2],
            'role_name'          => ['label' => 'Role'],
            'user_status_display' => ['label' => 'Status'],
            'user_created_at'    => ['label' => 'Creation Date'],
        ],

    ];

    return $registry[$export_type] ?? [];
}

/*
 * Which columns the modal ticked, whitelisted against the registry.
 * Registry order always wins, so the file layout is stable no matter what order
 * the checkboxes came back in. Nothing ticked (or no picker on the form) falls
 * back to the export's defaults.
 */
function resolveExportColumns($export_type) {

    $available = getExportColumns($export_type);
    if (empty($available)) {
        return [];
    }

    $requested = $_POST['columns'] ?? [];
    if (!is_array($requested)) {
        $requested = [];
    }

    $selected = [];
    foreach ($available as $key => $column) {
        if (in_array($key, $requested, true)) {
            $selected[$key] = $column;
        }
    }

    if (empty($selected)) {
        foreach ($available as $key => $column) {
            if ($column['default'] ?? true) {
                $selected[$key] = $column;
            }
        }
    }

    return $selected;
}

/*
 * The export buttons post their format as the value of the trigger, so
 * $_POST['export_assets'] is the string 'csv' or 'pdf'. Anything else is CSV.
 */
function resolveExportFormat($format) {
    return ($format === 'pdf') ? 'pdf' : 'csv';
}

/*
 * The gate every export handler opens on. Keying on isset() alone means any other
 * field that happens to share the trigger's name fires the export - the client PDF
 * pack's section checkboxes (export_assets=1, export_contacts=1, ...) did exactly
 * that, and since post.php loads every handler, the first match won and streamed a
 * CSV instead. Only 'csv' or 'pdf' - what renderExportButtons() posts - counts.
 */
function isExportRequest($trigger) {
    return isset($_POST[$trigger]) && in_array($_POST[$trigger], ['csv', 'pdf'], true);
}

/*
 * PDF is capped - see EXPORT_PDF_MAX_ROWS. Call this after the row count is known
 * and before beginExport(); it redirects rather than returning on refusal.
 */
function guardExportPdfRowCount($format, $num_rows) {
    if (resolveExportFormat($format) === 'pdf' && $num_rows > EXPORT_PDF_MAX_ROWS) {
        flashAlert("That's " . number_format($num_rows) . " rows - too many for a PDF. Narrow the filters or export to CSV instead.", 'error');
        redirect();
    }
}

/*
 * Presentation only. CSV keeps numbers raw so spreadsheets and importers still
 * see a number; the PDF is for reading, so it gets thousands separators.
 */
function formatExportValue($value, $format, $output) {

    // An empty field is empty, whatever its format - a blank amount is not 0.00
    if ($value === null || $value === '') {
        return '';
    }

    if ($format === 'phone') {
        return formatPhoneNumber($value);
    }

    if ($output === 'pdf' && $format === 'money') {
        return number_format(floatval($value), 2);
    }

    if ($output === 'pdf' && $format === 'number') {
        return rtrim(rtrim(number_format(floatval($value), 2), '0'), '.');
    }

    return $value;
}

/*
 * Renders the column picker into an export modal. Drop it in the modal body:
 *
 *     <?php renderExportColumnPicker('assets'); ?>
 *
 * Posts back as columns[]. Silently renders nothing for an export type that has
 * no registry entry yet, so a half-converted modal still works.
 */
function renderExportColumnPicker($export_type) {

    $available = getExportColumns($export_type);
    if (empty($available)) {
        return;
    }

    // Modals get appended to the DOM, so the container id has to be unique per instance
    static $instance = 0;
    $instance++;
    $picker_id = 'exportColumns' . $instance;

    ?>

    <div class="mb-3">
        <label class="d-flex justify-content-between align-items-center">
            <span>Columns</span>
            <span>
                <button type="button" class="btn btn-link btn-sm p-0 me-2 export-columns-all">Select all</button>
                <button type="button" class="btn btn-link btn-sm p-0 export-columns-none">None</button>
            </span>
        </label>
        <div id="<?= $picker_id ?>" class="export-column-picker border rounded p-2" style="max-height: 220px; overflow-y: auto;">
            <div class="row">
                <?php foreach ($available as $column_key => $column) { ?>
                    <div class="col-md-6">
                        <label class="d-block mb-1 fw-normal text-truncate" title="<?= escapeHtml($column['label']) ?>">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="<?= $column_key ?>" <?php if ($column['default'] ?? true) { echo 'checked'; } ?>>
                            <?= escapeHtml($column['label']) ?>
                        </label>
                    </div>
                <?php } ?>
            </div>
        </div>
        <small class="form-text text-muted export-columns-count"></small>
    </div>

    <script>
    (function () {
        var picker = document.getElementById('<?= $picker_id ?>');
        if (!picker) {
            return;
        }

        // Everything is looked up relative to the picker's own form group, so several
        // pickers on one page - or several modals in a session - never cross wires
        var group = picker.parentNode;
        var boxes = picker.querySelectorAll('input[name="columns[]"]');
        var counter = group.querySelector('.export-columns-count');
        var form = picker.closest('form');

        function setAll(state) {
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].checked = state;
            }
            update();
        }

        function update() {
            var checked = picker.querySelectorAll('input[name="columns[]"]:checked').length;
            counter.classList.remove('text-danger');
            counter.textContent = checked + ' of ' + boxes.length + ' columns selected'
                + (checked > 10 ? ' - a lot for a PDF, CSV will read better' : '');
        }

        picker.addEventListener('change', update);
        group.querySelector('.export-columns-all').addEventListener('click', function () { setAll(true); });
        group.querySelector('.export-columns-none').addEventListener('click', function () { setAll(false); });

        // An empty selection would silently fall back to the defaults server side -
        // stop it here so the choice stays the user's
        if (form) {
            form.addEventListener('submit', function (e) {
                if (picker.querySelectorAll('input[name="columns[]"]:checked').length === 0) {
                    e.preventDefault();
                    counter.textContent = 'Select at least one column to export';
                    counter.classList.add('text-danger');
                }
            });
        }

        update();
    })();
    </script>

    <?php
}

/*
 * Builds the export modal's data-modal-url with the page's current filters attached,
 * so the list page doesn't need a hand-rolled query string per export button:
 *
 *     data-modal-url="<?= buildExportModalUrl('modals/asset/asset_export.php', ['client_id', 'type', 'q']) ?>"
 *
 * Empty params are dropped and array params (tags[], status[]) survive. The return
 * value is escaped for use in an HTML attribute.
 */
function buildExportModalUrl($modal_path, $names, $extra = []) {

    $params = [];
    foreach ($names as $name) {
        if (!isset($_GET[$name]) || $_GET[$name] === '' || $_GET[$name] === []) {
            continue;
        }
        $params[$name] = $_GET[$name];
    }

    // Filters the page derived rather than read straight off the query string -
    // filter_header.php's canned date ranges being the main one
    foreach ($extra as $name => $value) {
        if ($value !== '' && $value !== null && $value !== []) {
            $params[$name] = $value;
        }
    }

    if (empty($params)) {
        return escapeHtml($modal_path);
    }

    return escapeHtml($modal_path . '?' . http_build_query($params));
}

/*
 * filter_header.php falls back to 1970-01-01 / 2099-12-31 when no date range is
 * chosen, so a naive summary would claim a filter that isn't really there.
 * Returns '' for the all-time case, otherwise a readable range.
 */
function formatExportDateRange($date_from, $date_to) {

    $all_time_from = ($date_from === '' || $date_from === null || $date_from === '1970-01-01' || $date_from === '0000-00-00');
    $all_time_to = ($date_to === '' || $date_to === null || $date_to === '2099-12-31' || $date_to === '9999-00-00');

    if ($all_time_from && $all_time_to) {
        return '';
    }

    return $date_from . ' to ' . $date_to;
}

/*
 * Human-readable one-liner of the same thing, for the PDF subtitle.
 */
function summarizeExportFilters($filters) {

    $parts = [];
    foreach ($filters as $label => $value) {
        if ($value !== '' && $value !== null) {
            $parts[] = $label . ': ' . $value;
        }
    }

    return empty($parts) ? '' : 'Filters - ' . implode(' | ', $parts);
}

/*
 * The tabbed shell every export modal uses - Filters on the first pane, Columns on the
 * second, same pill nav as the add-client modal. Ids are per-instance because modals are
 * appended to the DOM rather than living in the page.
 *
 * Sits between the modal header and the form:
 *
 *     <?php exportTabsNav(); ?>
 *     <form ...>
 *         <div class="modal-body">
 *             <?php exportTabsFiltersOpen(); ?>
 *                 ...filter controls...
 *             <?php exportTabsColumns('assets'); ?>
 *         </div>
 *
 * exportTabsColumns() closes the filters pane, renders the picker in the second pane and
 * closes the tab content, so the modal body needs nothing else.
 */
function exportTabsId() {
    static $instance = 0;
    static $current = '';
    if (func_num_args() > 0 && func_get_arg(0) === 'next') {
        $instance++;
        $current = 'exportTab' . $instance;
    }
    return $current;
}

function exportTabsNav($filters_label = 'Filters') {
    $id = exportTabsId('next');
    ?>
    <ul class="modal-header nav nav-pills nav-justified">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="pill" href="#<?= $id ?>-filters"><?= escapeHtml($filters_label) ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#<?= $id ?>-columns">Columns</a>
        </li>
    </ul>
    <?php
}

function exportTabsFiltersOpen() {
    $id = exportTabsId();
    ?>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="<?= $id ?>-filters">
    <?php
}

function exportTabsColumns($export_type) {
    $id = exportTabsId();
    ?>
        </div>
        <div class="tab-pane fade" id="<?= $id ?>-columns">
            <?php renderExportColumnPicker($export_type); ?>
        </div>
    </div>
    <?php
}

/*
 * Renders the CSV / PDF submit pair for an export modal footer.
 * $trigger is the POST key the handler keys on, e.g. 'export_assets'.
 */
function renderExportButtons($trigger) {
    ?>
    <button type="submit" name="<?= $trigger ?>" value="csv" class="btn btn-primary text-bold"><i class="fas fa-fw fa-file-csv me-2"></i>Download CSV</button>
    <button type="submit" name="<?= $trigger ?>" value="pdf" class="btn btn-secondary text-bold"><i class="fas fa-fw fa-file-pdf me-2"></i>Download PDF</button>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    <?php
}

/*
 * Opens an export. $filename_base is everything before the timestamp, e.g.
 * "Acme Inc-Assets"; the date and extension are appended here.
 * $subtitle is the human summary of the filters in play - it prints under the
 * title in the PDF and is ignored for CSV.
 */
function beginExport($export_type, $format, $filename_base, $title = '', $subtitle = '') {

    $format = resolveExportFormat($format);
    $columns = resolveExportColumns($export_type);

    $export = [
        'format'   => $format,
        'columns'  => $columns,
        'title'    => $title,
        'subtitle' => $subtitle,
        'filename' => sanitizeFilename($filename_base . '-' . date('Y-m-d_H-i-s') . '.' . $format),
        'rows'     => 0,
        'fp'       => null,
        'body'     => '',
        'widths'   => [],
        'missing'  => [],
    ];

    if ($format === 'csv') {
        $export['fp'] = fopen('php://memory', 'w');
        fputcsv($export['fp'], array_column($columns, 'label'), ',', '"', '\\');
    } else {
        // TCPDF sizes a table from the cells it sees, so every row needs the widths -
        // putting them on the header alone leaves the body offset from its headings
        $total_weight = 0;
        foreach ($columns as $column) {
            $total_weight += $column['weight'] ?? 1;
        }
        foreach ($columns as $column_key => $column) {
            $export['widths'][$column_key] = round((($column['weight'] ?? 1) / $total_weight) * 100, 2);
        }
    }

    return $export;
}

/*
 * One row. $row is a plain associative array - normally straight out of
 * mysqli_fetch_assoc(), with any handler-computed keys already set on it.
 */
function addExportRow(&$export, $row) {

    $export['rows']++;

    $values = [];
    foreach ($export['columns'] as $column_key => $column) {
        $field = $column['field'] ?? $column_key;

        // A selected column whose field the query never returned would otherwise be a
        // silently blank column all the way down - note it so finishExport can shout
        if (!array_key_exists($field, $row)) {
            $export['missing'][$field] = true;
        }

        $values[$column_key] = formatExportValue($row[$field] ?? '', $column['format'] ?? '', $export['format']);
    }

    if ($export['format'] === 'csv') {
        fputcsv($export['fp'], array_map('escapeCsvFormula', $values), ',', '"', '\\');
        return;
    }

    // PDF - buffer the row, the document is assembled in finishExport()
    $stripe = ($export['rows'] % 2 === 0) ? ' bgcolor="#f4f4f4"' : '';
    $cells = '';
    foreach ($export['columns'] as $column_key => $column) {
        $align = in_array($column['format'] ?? '', ['money', 'number'], true) ? 'right' : 'left';
        $width = $export['widths'][$column_key] ?? 0;

        // Blank cells read as a mistake in a printed table - an explicit dash doesn't
        $cell = ($values[$column_key] === '') ? '-' : escapeHtml($values[$column_key]);

        $cells .= '<td width="' . $width . '%" align="' . $align . '">' . $cell . '</td>';
    }
    $export['body'] .= '<tr' . $stripe . '>' . $cells . '</tr>';
}

/*
 * A column whose field the query never selected produces a blank column rather than an
 * error, which is exactly the kind of thing that ships unnoticed. Nothing is shown to
 * the user - the file is still valid - but it lands in the PHP error log where a
 * conversion mistake will be spotted.
 */
function reportMissingExportFields(&$export) {
    if (!empty($export['missing'])) {
        error_log('ITFlow export: no such field in result set for column(s): ' . implode(', ', array_keys($export['missing'])));
    }
}

/*
 * Streams the finished file. Sends its own headers, so nothing may be echoed
 * before it; the caller still owns the logAudit() call and the exit.
 */
function finishExport(&$export) {

    global $session_company_name;

    reportMissingExportFields($export);

    if ($export['format'] === 'csv') {
        fseek($export['fp'], 0);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $export['filename'] . '";');
        fpassthru($export['fp']);
        fclose($export['fp']);
        return $export['rows'];
    }

    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';

    $column_count = count($export['columns']);

    // Anything past a handful of columns needs the long edge
    $orientation = $column_count > 5 ? 'L' : 'P';

    // Shrink type as columns pile up rather than letting TCPDF wrap every cell
    if ($column_count <= 6) {
        $font_size = 8;
    } elseif ($column_count <= 9) {
        $font_size = 7;
    } elseif ($column_count <= 12) {
        $font_size = 6;
    } else {
        $font_size = 5;
    }

    $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($session_company_name);
    $pdf->SetTitle($export['title']);
    $pdf->SetPrintHeader(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();
    $pdf->SetFont('freeserif', '', $font_size);

    // Same widths the body rows used - see beginExport()
    $head = '';
    foreach ($export['columns'] as $column_key => $column) {
        $width = $export['widths'][$column_key] ?? 0;
        $head .= '<th width="' . $width . '%">' . escapeHtml($column['label']) . '</th>';
    }

    $html = '
    <style>
        h1 { font-size: ' . ($font_size + 6) . 'pt; margin: 0; }
        p.meta { font-size: ' . $font_size . 'pt; color: #555555; margin: 2px 0 0 0; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 0.5px solid #999999; }
        th { background-color: #343a40; color: #ffffff; text-align: left; font-weight: bold; padding: 3px; }
        td { padding: 3px; }
        thead { display: table-header-group; }
    </style>
    <h1>' . escapeHtml($export['title']) . '</h1>
    <p class="meta">' . escapeHtml($session_company_name) . '</p>';

    if (!empty($export['subtitle'])) {
        $html .= '<p class="meta">' . escapeHtml($export['subtitle']) . '</p>';
    }

    $html .= '<p class="meta">' . $export['rows'] . ' record(s) - generated ' . date('Y-m-d H:i') . '</p><br>
    <table cellspacing="0" cellpadding="2">
        <thead><tr>' . $head . '</tr></thead>
        <tbody>' . $export['body'] . '</tbody>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($export['filename'], 'D');

    return $export['rows'];
}

/*
 * ---------------------------------------------------------------------------
 * Client PDF pack
 * ---------------------------------------------------------------------------
 *
 * The sections the client "Export Data" PDF can contain, in document order.
 *
 *   'label'   Checkbox label in the export modal.
 *   'icon'    Font Awesome class shown beside it.
 *   'module'  Permission module that owns the section - same mapping the list
 *             exports use. A role without read access to it never sees the
 *             checkbox and never gets the section, so one missing module drops a
 *             section rather than refusing the whole export.
 *   'default' Whether the box starts ticked.
 *
 * The modal renders from this and the handler resolves from it, so a section
 * can't be offered by one and ignored by the other.
 */
function getClientPackSections() {
    return [
        'contacts'     => ['label' => 'Contacts',            'icon' => 'fa-users',          'module' => 'module_client',     'default' => true],
        'locations'    => ['label' => 'Locations',           'icon' => 'fa-map-marker-alt', 'module' => 'module_client',     'default' => true],
        'vendors'      => ['label' => 'Vendors',             'icon' => 'fa-building',       'module' => 'module_client',     'default' => true],
        'credentials'  => ['label' => 'Credentials',         'icon' => 'fa-key',            'module' => 'module_credential', 'default' => false],
        'assets'       => ['label' => 'Assets',              'icon' => 'fa-desktop',        'module' => 'module_support',    'default' => true],
        'software'     => ['label' => 'Software / Licenses', 'icon' => 'fa-cube',           'module' => 'module_support',    'default' => true],
        'networks'     => ['label' => 'Networks',            'icon' => 'fa-network-wired',  'module' => 'module_support',    'default' => true],
        'domains'      => ['label' => 'Domains',             'icon' => 'fa-globe',          'module' => 'module_support',    'default' => true],
        'certificates' => ['label' => 'Certificates',        'icon' => 'fa-lock',           'module' => 'module_support',    'default' => true],
    ];
}

/*
 * Read access per module, resolved once rather than once per section.
 */
function getClientPackSectionAccess() {
    $access = [];
    foreach (getClientPackSections() as $section) {
        if (!isset($access[$section['module']])) {
            $access[$section['module']] = lookupUserPermission($section['module']) >= 1;
        }
    }
    return $access;
}

/*
 * Resolves the posted checkboxes against the signed-in role. Returns
 * ['contacts' => 1|0, ...]. A section the role can't read is 0 whatever was
 * posted, so a hand-rolled POST can't pull in a section the modal never offered.
 */
function resolveClientPackSections() {

    $access = getClientPackSectionAccess();

    $selected = [];
    foreach (getClientPackSections() as $key => $section) {
        $selected[$key] = ($access[$section['module']] && !empty($_POST["include_$key"])) ? 1 : 0;
    }

    return $selected;
}

/*
 * The section checkboxes for the client PDF pack modal, split across two columns.
 * Sections the role can't read are left out entirely rather than shown and then
 * silently dropped server side.
 */
function renderClientPackSections() {

    $access = getClientPackSectionAccess();

    $visible = [];
    foreach (getClientPackSections() as $key => $section) {
        if ($access[$section['module']]) {
            $visible[$key] = $section;
        }
    }

    $split = (int) ceil(count($visible) / 2);
    $index = 0;

    ?>
    <div class="row">
        <div class="col-sm-6">
        <?php foreach ($visible as $key => $section) { ?>
            <?php if ($index === $split) { ?>
        </div>
        <div class="col-sm-6">
            <?php } ?>
            <li class="list-group-item">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="include_<?= $key ?>" name="include_<?= $key ?>" value="1" <?php if ($section['default']) { echo 'checked'; } ?>>
                    <label for="include_<?= $key ?>" class="form-check-label">
                        <i class="fas fa-fw <?= $section['icon'] ?> me-2"></i><?= escapeHtml($section['label']) ?>
                    </label>
                </div>
            </li>
            <?php $index++; ?>
        <?php } ?>
        </div>
    </div>
    <?php
}
