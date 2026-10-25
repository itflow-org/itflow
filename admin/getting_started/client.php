<?php

/*
 * Get started - Your first client: a name, a website and who to talk to,
 * made by the same New Client handler as the Clients page (add_client,
 * agent/post/client.php - every field it reads is sent, the ones not asked
 * here empty); or the demo data, to look around first (load_demo_data, as on
 * Maintenance > Starter Content). Both come back here.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

defined('FROM_STARTER_CONTENT') || define('FROM_STARTER_CONTENT', true);
require_once dirname(__DIR__) . '/post/demo_data_model.php';

$gs_demo = demoDataStatus($mysqli);
$gs_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_created_at DESC, client_id DESC LIMIT 3");
$gs_client_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM clients WHERE client_archived_at IS NULL"))[0] ?? 0);
$gs_defaults = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_default_hourly_rate, config_default_net_terms FROM settings WHERE company_id = 1")) ?: [];

?>
<?php if ($gs_client_count) { ?>
    <ul class="list-unstyled itflow-gs-people mb-4" data-gs-clients="<?= $gs_client_count ?>">
        <?php while ($gs_client = mysqli_fetch_assoc($gs_clients)) { ?>
            <li>
                <span class="itflow-gs-person-initials" aria-hidden="true"><?= escapeHtml(personInitials($gs_client['client_name'])) ?></span>
                <a href="/agent/client_overview.php?client_id=<?= intval($gs_client['client_id']) ?>" class="fw-semibold text-truncate min-w-0 flex-grow-1"><?= escapeHtml($gs_client['client_name']) ?></a>
            </li>
        <?php } ?>
        <?php if ($gs_client_count > 3) { ?>
            <li class="small text-body-secondary ps-1"><a href="/agent/clients.php">and <?= $gs_client_count - 3 ?> more</a></li>
        <?php } ?>
    </ul>
    <h2 class="h6 fw-semibold">Add another</h2>
<?php } ?>

<form action="/agent/post.php" method="post" autocomplete="off" data-gs-form data-gs-client-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach (['type', 'address', 'city', 'state', 'zip', 'country', 'location_phone_country_code', 'location_phone', 'location_extension', 'location_fax_country_code', 'location_fax',
        'title', 'contact_phone_country_code', 'contact_phone', 'contact_extension', 'contact_mobile_country_code', 'contact_mobile'] as $gs_field) { ?>
        <input type="hidden" name="<?= $gs_field ?>" value="">
    <?php } ?>
    <input type="hidden" name="rate" value="<?= escapeHtml(number_format(floatval($gs_defaults['config_default_hourly_rate'] ?? 0), 2, '.', '')) ?>">
    <input type="hidden" name="net_terms" value="<?= intval($gs_defaults['config_default_net_terms'] ?? 30) ?>">
    <div class="row g-3 mb-3">
        <div class="col-sm-7">
            <?= settingsInput('name', '', ['id' => 'gsClientName', 'icon' => 'fa-user-friends', 'label' => 'Client', 'required' => true, 'maxlength' => 200, 'placeholder' => 'Name or company']) ?>
        </div>
        <div class="col-sm-5">
            <?= settingsInput('website', '', ['id' => 'gsClientWebsite', 'icon' => 'fa-globe', 'label' => 'Website', 'maxlength' => 200, 'placeholder' => 'example.com']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('contact', '', ['id' => 'gsClientContact', 'icon' => 'fa-user', 'label' => 'Who you talk to', 'maxlength' => 200, 'placeholder' => 'Their name']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('contact_email', '', ['id' => 'gsClientEmail', 'icon' => 'fa-envelope', 'type' => 'email', 'label' => 'Their email', 'maxlength' => 200, 'placeholder' => 'name@example.com']) ?>
        </div>
    </div>
    <p class="small text-body-secondary">The website's domain and certificate are added too, to watch for renewals. The rest - their address, phones, rate and SLAs - is on the client once it's made, and Clients &gt; Import brings in a whole list from a CSV.</p>
    <button type="submit" name="add_client" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-plus me-2"></i>Add the client</button>
</form>

<div class="itflow-gs-or" aria-hidden="true"><span>or</span></div>

<div class="d-flex flex-wrap align-items-center gap-3" data-gs-demo="<?= count($gs_demo['loaded']) ?>">
    <div class="min-w-0 flex-grow-1">
        <div class="fw-semibold">Look around with the demo data first</div>
        <div class="small text-body-secondary">
            <?php if ($gs_demo['loaded']) { ?>
                <?= count($gs_demo['loaded']) ?> of the <?= $gs_demo['total'] ?> demo clients are here. Remove them all in Maintenance &gt; Starter Content once you're done.
            <?php } else { ?>
                <?= $gs_demo['total'] ?> made-up clients, each with contacts, assets, tickets and a year of invoices. They come out again in one go from Maintenance &gt; Starter Content.
            <?php } ?>
        </div>
    </div>
    <?php if (count($gs_demo['loaded']) < $gs_demo['total']) { ?>
        <form action="post.php" method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" name="load_demo_data" value="1" class="btn btn-outline-secondary" data-gs-demo-load><i class="fas fa-flask me-2"></i>Load the demo data</button>
        </form>
    <?php } ?>
</div>
