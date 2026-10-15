<?php
/*
 * The primary contact, primary location and details columns under the
 * client's name. Shared by the client overview's header and the client header
 * on every other client page (inc_client_top_head.php), so both show the same.
 * Expects the client variables from inc_all_client.php; the caller supplies
 * the surrounding .row.
 */
$client_details_edit = lookupUserPermission('module_client') >= 2;
$client_since = $client_created_at ? date('M Y', strtotime($client_created_at)) : '';
$client_website_url = $client_website ? (preg_match('#^https?://#i', $client_website) ? $client_website : "https://$client_website") : '';
$client_website_label = preg_replace('#^https?://#i', '', rtrim($client_website, '/'));
$location_map_query = rawurlencode(html_entity_decode(trim("$location_address $location_city $location_state $location_zip"), ENT_QUOTES));
?>
<div class="col-md-4">
    <div class="itflow-ov-label">Primary contact</div>
    <?php if ($contact_id) { ?>
        <?= contactLink($client_id, $contact_id, $contact_name, $contact_photo, ['class' => 'fw-semibold']) ?>
        <?php if ($contact_title) { ?><span class="text-body-secondary small ms-1"><?= $contact_title ?></span><?php } ?>
        <div class="small mt-1 d-flex flex-column gap-1">
            <?php if ($contact_phone) { ?>
                <a class="text-reset text-decoration-none" href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', html_entity_decode($contact_phone))) ?>"><i class="fas fa-fw fa-phone text-body-secondary me-2" aria-hidden="true"></i><?= $contact_phone ?><?= $contact_extension ? " x$contact_extension" : '' ?></a>
            <?php } ?>
            <?php if ($contact_mobile) { ?>
                <a class="text-reset text-decoration-none" href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', html_entity_decode($contact_mobile))) ?>"><i class="fas fa-fw fa-mobile-alt text-body-secondary me-2" aria-hidden="true"></i><?= $contact_mobile ?></a>
            <?php } ?>
            <?php if ($contact_email) { ?>
                <span class="d-flex align-items-center">
                    <a class="text-reset text-decoration-none text-truncate" href="mailto:<?= $contact_email ?>"><i class="far fa-fw fa-envelope text-body-secondary me-2" aria-hidden="true"></i><?= $contact_email ?></a>
                    <button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $contact_email ?>"><i class="far fa-copy"></i></button>
                </span>
            <?php } ?>
        </div>
    <?php } elseif ($client_details_edit) { ?>
        <a class="ajax-modal small" href="#" data-modal-size="lg" data-modal-url="modals/contact/contact_add.php?client_id=<?= $client_id ?>&primary=1"><i class="fas fa-fw fa-plus me-1"></i>Add primary contact</a>
    <?php } else { ?>
        <span class="text-body-tertiary">None</span>
    <?php } ?>
</div>

<div class="col-md-4">
    <div class="itflow-ov-label">Primary location<?php if ($location_id) { ?> &middot; <a class="text-reset" href="location.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>" title="Open the location"><?= $location_name ?></a><?php } ?></div>
    <?php if ($location_id) { ?>
        <?php if ($location_address || $location_city) { ?>
            <a class="text-reset text-decoration-none" href="//maps.<?= $session_map_source ?>.com/?q=<?= $location_map_query ?>" target="_blank" rel="noopener" title="Open in maps">
                <?php if ($location_address) { ?><div><?= $location_address ?></div><?php } ?>
                <div class="<?= $location_address ? 'small text-body-secondary' : '' ?>"><?= formatAddress('', $location_city, $location_state, $location_zip, $location_country, ', ') ?></div>
            </a>
        <?php } else { ?>
            <div><?= $location_name ?></div>
        <?php } ?>
        <?php if ($location_phone) { ?>
            <a class="small text-reset text-decoration-none d-block mt-1" href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', html_entity_decode($location_phone))) ?>"><i class="fas fa-fw fa-phone text-body-secondary me-2" aria-hidden="true"></i><?= $location_phone ?></a>
        <?php } ?>
    <?php } elseif ($client_details_edit) { ?>
        <a class="ajax-modal small" href="#" data-modal-url="modals/location/location_add.php?client_id=<?= $client_id ?>&primary=1"><i class="fas fa-fw fa-plus me-1"></i>Add primary location</a>
    <?php } else { ?>
        <span class="text-body-tertiary">None</span>
    <?php } ?>
</div>

<div class="col-md-4">
    <div class="itflow-ov-label">Details</div>
    <div class="small d-flex flex-column gap-1">
        <?php if ($client_website) { ?>
            <a class="text-truncate" href="<?= $client_website_url ?>" target="_blank" rel="noopener"><i class="fas fa-fw fa-globe text-body-secondary me-2" aria-hidden="true"></i><?= $client_website_label ?></a>
        <?php } ?>
        <?php if ($client_since) { ?>
            <span><i class="far fa-fw fa-calendar text-body-secondary me-2" aria-hidden="true"></i><?= $client_is_lead ? 'Lead' : 'Client' ?> since <?= $client_since ?></span>
        <?php } ?>
        <?php if ($client_referral) { ?>
            <span><i class="fas fa-fw fa-handshake text-body-secondary me-2" aria-hidden="true"></i>Referred by <?= $client_referral ?></span>
        <?php } ?>
        <span class="text-body-secondary"><i class="fas fa-fw fa-hashtag me-2" aria-hidden="true"></i>Client ID <?= $client_id ?></span>
    </div>
</div>
