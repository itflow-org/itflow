<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

// Billing Card Queries
 //Add up all the payments for the invoice and get the total amount paid to the invoice
$sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $session_client_id AND invoice_status != 'Draft' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable'");
$row = mysqli_fetch_array($sql_invoice_amounts);

$invoice_amounts = floatval($row['invoice_amounts']);

$sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $session_client_id");
$row = mysqli_fetch_array($sql_amount_paid);

$amount_paid = floatval($row['amount_paid']);

$balance = $invoice_amounts - $amount_paid;

//Get Monthly Recurring Total
$sql_recurring_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_monthly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'month' AND recurring_invoice_client_id = $session_client_id");
$row = mysqli_fetch_array($sql_recurring_monthly_total);

$recurring_monthly_total = floatval($row['recurring_monthly_total']);

//Get Yearly Recurring Total
$sql_recurring_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_yearly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'year' AND recurring_invoice_client_id = $session_client_id");
$row = mysqli_fetch_array($sql_recurring_yearly_total);

$recurring_yearly_total = floatval($row['recurring_yearly_total']) / 12;

$recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;

// Technical Card Queries
// 8 - 45 Day Warning

// Get Domains Expiring
$sql_domains_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM domains
    WHERE domain_client_id = $session_client_id
        AND domain_expire IS NOT NULL
        AND domain_archived_at IS NULL
        AND domain_expire > CURRENT_DATE
        AND domain_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY domain_expire ASC"
);

// Get Certificates Expiring
$sql_certificates_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM certificates
    WHERE certificate_client_id = $session_client_id
        AND certificate_expire IS NOT NULL
        AND certificate_archived_at IS NULL
        AND certificate_expire > CURRENT_DATE
        AND certificate_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY certificate_expire ASC"
);

// Get Licenses Expiring
$sql_licenses_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM software
    WHERE software_client_id = $session_client_id
        AND software_expire IS NOT NULL
        AND software_archived_at IS NULL
        AND software_expire > CURRENT_DATE
        AND software_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY software_expire ASC"
);

// Get Asset Warranties Expiring
$sql_asset_warranties_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_warranty_expire IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_warranty_expire > CURRENT_DATE
        AND asset_warranty_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY asset_warranty_expire ASC"
);

// Get Assets Retiring 7 Year
$sql_asset_retire = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_install_date IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_install_date + INTERVAL 7 YEAR > CURRENT_DATE
        AND asset_install_date + INTERVAL 7 YEAR <= CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY asset_install_date ASC"
);

/*
 * EXPIRED ITEMS
 */

// Get Domains Expired
$sql_domains_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM domains
    WHERE domain_client_id = $session_client_id
        AND domain_expire IS NOT NULL
        AND domain_archived_at IS NULL
        AND domain_expire < CURRENT_DATE
    ORDER BY domain_expire ASC"
);

// Get Certificates Expired
$sql_certificates_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM certificates
    WHERE certificate_client_id = $session_client_id
        AND certificate_expire IS NOT NULL
        AND certificate_archived_at IS NULL
        AND certificate_expire < CURRENT_DATE
    ORDER BY certificate_expire ASC"
);

// Get Licenses Expired
$sql_licenses_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM software
    WHERE software_client_id = $session_client_id
        AND software_expire IS NOT NULL
        AND software_archived_at IS NULL
        AND software_expire < CURRENT_DATE
    ORDER BY software_expire ASC"
);

// Get Asset Warranties Expired
$sql_asset_warranties_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_warranty_expire IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_warranty_expire < CURRENT_DATE
    ORDER BY asset_warranty_expire ASC"
);

// Get Retired Assets
$sql_asset_retired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_install_date IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_install_date + INTERVAL 7 YEAR < CURRENT_DATE  -- Assets retired (installed more than 7 years ago)
    ORDER BY asset_install_date ASC"
);

// Assigned Assets
$sql_assigned_assets = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_contact_id = $session_contact_id
        AND asset_archived_at IS NULL
    ORDER BY asset_name ASC"
);

?>
<div class="row">
    <div class="col-md-2">
        <a href="ticket_add.php" class="btn btn-primary btn-block mb-3">New ticket</a>
    </div>
</div>
<?php
// Billing Cards
if ($session_contact_primary == 1 || $session_contact_is_billing_contact) { ?>

<div class="row">

    <?php if ($balance > 0) { ?>
    <div class="col-sm-3 offset-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title text-bold">Account Balance</h3>
            </div>
            <div class="card-body">
                <div class="h4 text-danger"><b><?php echo numfmt_format_currency($currency_format, $balance, $session_company_currency); ?></b></div>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($recurring_monthly_total > 0) { ?>
    <div class="col-sm-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recurring Monthly</h3>
            </div>
            <div class="card-body">
                <div class="h4"><b><?php echo numfmt_format_currency($currency_format, $recurring_monthly_total, $session_company_currency); ?></b></div>
            </div>
        </div>
    </div>
    <?php } ?>

</div>

<?php } //End Billing Cards ?>

<?php
// Technical Cards
if ($session_contact_primary == 1 || $session_contact_is_technical_contact) {
?>

<div class="row">

    <?php if (mysqli_num_rows($sql_domains_expiring) > 0) { ?>
    <div class="col-sm-3 offset-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title text-bold">Domains Expiring</h3>
            </div>
            <div class="card-body">
                <?php

                while ($row = mysqli_fetch_array($sql_domains_expiring)) {
                    $domain_id = intval($row['domain_id']);
                    $domain_name = nullable_htmlentities($row['domain_name']);
                    $domain_expire = nullable_htmlentities($row['domain_expire']);
                    $domain_expire_human = timeAgo($row['domain_expire']);

                    ?>
                    <p class="mb-1">
                        <i class="fa fa-fw fa-globe text-secondary mr-1"></i>
                        Domain: <?php echo $domain_name; ?>
                        <span>-- <?php echo $domain_expire; ?> (<?php echo $domain_expire_human; ?>)</span>
                    </p>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <?php } ?>

</div>

<?php } ?>

<?php
// Everone Cards
?>
<div class="row">
    <?php if (mysqli_num_rows($sql_assigned_assets) > 0) { ?>
    <div class="col-sm-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Your Assigned Assets</h3>
            </div>
            <div class="card-body">
                <table>
                <?php

                while ($row = mysqli_fetch_array($sql_assigned_assets)) {
                    $asset_name = nullable_htmlentities($row['asset_name']);
                    $asset_type = nullable_htmlentities($row['asset_type']);

                    ?>
                    <tr>
                        <td><i class="fa fa-fw fa-desktop text-secondary mr-2"></i><?php echo $asset_name; ?></td>
                        <td class="text-secondary">(<?php echo $asset_type; ?>)</td>
                    </tr>
                    <?php
                }
                ?>
                </table>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<?php require_once "includes/footer.php"; ?>
