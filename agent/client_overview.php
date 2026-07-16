<?php

require_once "includes/inc_all_client.php";

$sql_recent_activities = mysqli_query(
    $mysqli,
    "SELECT * FROM logs
    WHERE log_client_id = $client_id
    ORDER BY log_created_at DESC
    LIMIT 5"
);

$sql_important_contacts = mysqli_query(
    $mysqli,
    "SELECT * FROM contacts
    WHERE contact_client_id = $client_id
        AND (contact_important = 1
            OR contact_billing = 1
            OR contact_technical = 1
            OR contact_primary = 1
        )
        AND contact_archived_at IS NULL
    ORDER BY contact_primary DESC, contact_name DESC LIMIT 5"
);

$sql_favorite_assets = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $client_id
        AND asset_favorite = 1
        AND asset_archived_at IS NULL
    ORDER BY asset_type ASC, asset_name ASC"
);

$sql_favorite_credentials = mysqli_query(
    $mysqli,
    "SELECT * FROM credentials
    WHERE credential_client_id = $client_id
        AND credential_favorite = 1
        AND credential_archived_at IS NULL
        ORDER BY credential_name ASC"
);

$sql_recent_tickets = mysqli_query(
    $mysqli,
    "SELECT * FROM tickets
    WHERE ticket_client_id = $client_id
    ORDER BY ticket_created_at ASC
    LIMIT 5"
);

$sql_recent_credentials = mysqli_query(
    $mysqli,
    "SELECT * FROM credentials
     WHERE credential_client_id = $client_id
     AND credential_archived_at IS NULL
     ORDER BY credential_updated_at ASC
     LIMIT 5"
);

$sql_shared_items = mysqli_query(
    $mysqli,
    "SELECT * FROM shared_items
    WHERE item_client_id = $client_id
        AND item_active = 1
    ORDER BY item_created_at ASC
    LIMIT 5"
);

/*
 * EXPIRING/ACTION ITEMS
 */

// Stale Tickets
$sql_stale_tickets = mysqli_query(
    $mysqli,
    "SELECT * FROM tickets
    WHERE ticket_client_id = $client_id
        AND ticket_updated_at < CURRENT_DATE - INTERVAL 7 DAY
        AND ticket_resolved_At IS NULL
        AND ticket_closed_at IS NULL
    ORDER BY ticket_updated_at ASC"
);

// 8 - 45 Day Warning

// Get Domains Expiring
$sql_domains_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM domains
    WHERE domain_client_id = $client_id
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
    WHERE certificate_client_id = $client_id
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
    WHERE software_client_id = $client_id
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
    WHERE asset_client_id = $client_id
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
    WHERE asset_client_id = $client_id
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
    WHERE domain_client_id = $client_id
        AND domain_expire IS NOT NULL
        AND domain_archived_at IS NULL
        AND domain_expire < CURRENT_DATE
    ORDER BY domain_expire ASC"
);

// Get Certificates Expired
$sql_certificates_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM certificates
    WHERE certificate_client_id = $client_id
        AND certificate_expire IS NOT NULL
        AND certificate_archived_at IS NULL
        AND certificate_expire < CURRENT_DATE
    ORDER BY certificate_expire ASC"
);

// Get Licenses Expired
$sql_licenses_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM software
    WHERE software_client_id = $client_id
        AND software_expire IS NOT NULL
        AND software_archived_at IS NULL
        AND software_expire < CURRENT_DATE
    ORDER BY software_expire ASC"
);

// Get Asset Warranties Expired
$sql_asset_warranties_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $client_id
        AND asset_warranty_expire IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_warranty_expire < CURRENT_DATE
    ORDER BY asset_warranty_expire ASC"
);

// Get Retired Assets
$sql_asset_retired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $client_id
        AND asset_install_date IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_install_date + INTERVAL 7 YEAR < CURRENT_DATE  -- Assets retired (installed more than 7 years ago)
    ORDER BY asset_install_date ASC"
);


?>

<div class="row">

    <!-- Notes -->

    <div class="col-md-8">

        <div class="card card-dark mb-3">
            <div class="card-header p-2">
                <h5 class="card-title"><i class="fa fa-fw fa-edit mr-2"></i>Quick Notes</h5>
            </div>
            <div class="card-body p-1">
                <textarea class="form-control" rows=8 id="clientNotes" placeholder="Enter quick notes here" onblur="updateClientNotes(<?php echo $client_id ?>)"><?php echo $client_notes ?></textarea>
            </div>
        </div>
    </div>

    <div class="col-md-4">

        <?php if (mysqli_num_rows($sql_important_contacts) > 0) { ?>
        <div class="card card-dark mb-3">
            <div class="card-header p-2">
                <h5 class="card-title"><i class="fa fa-fw fa-users mr-2"></i>Important Contacts</h5>
            </div>
            <div class="card-body p-1">
                <table class="table table-borderless table-sm">
                    <?php

                    while ($row = mysqli_fetch_assoc($sql_important_contacts)) {
                        $contact_id = intval($row['contact_id']);
                        $contact_name = escapeHtml($row['contact_name']);
                        $contact_title = escapeHtml($row['contact_title']);
                        $contact_email = escapeHtml($row['contact_email']);
                        $contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
                        $contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
                        $contact_extension = escapeHtml($row['contact_extension']);
                        $contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
                        $contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
                        $contact_photo = escapeHtml($row['contact_photo']);
                        $contact_primary = intval($row['contact_primary']);
                        $contact_initials = initials($contact_name);
                        if ($contact_primary == 1) {
                            $contact_primary_display = "<small class='text-success'>Primary Contact</small>";
                        } else {
                            $contact_primary_display = false;
                        }

                        ?>
                        <tr>
                            <td>
                                <a href="#" class="ajax-modal"
                                    data-modal-size="xl"
                                    data-modal-url="modals/contact/contact.php?id=<?= $contact_id ?>">
                                    <div class="media">
                                        <?php if ($contact_photo) { ?>
                                            <span class="fa-stack fa-2x mr-2 text-center">
                                                <img class="img-size-50 img-circle" src="<?php echo "../uploads/clients/$client_id/$contact_photo"; ?>">
                                            </span>
                                        <?php } else { ?>
                                            <span class="fa-stack fa-2x mr-2">
                                                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                                <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                                            </span>
                                        <?php } ?>

                                        <div class="media-body">
                                            <div class="text-bold"><?php echo $contact_name; ?></div>
                                            <small class="text-secondary"><?php echo $contact_title; ?></small>
                                            <div><?php echo $contact_primary_display; ?></div>
                                            <?php
                                            if (!empty($contact_tags_display)) { ?>
                                                <div class="mt-1">
                                                    <?php echo $contact_tags_display; ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </a>

                            </td>
                            <td>
                                <?php if (!empty($contact_phone)) { ?>
                                    <?php echo "<i class='fa fa-fw fa-phone text-secondary'></i> $contact_phone $contact_extension"; ?>
                                <?php } ?>
                                <?php if (!empty($contact_mobile)) { ?>
                                    <br>
                                    <div class="text-secondary"><i class='fa fa-fw fa-mobile-alt text-secondary'></i> <?php echo "$contact_mobile"; ?></div>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>

                </table>
            </div>
        </div>
        <?php } ?>

    </div>

    <?php if (mysqli_num_rows($sql_favorite_assets) > 0) { ?>

    <div class="col-md-4">

        <div class="card card-dark mb-3">
            <div class="card-header p-2">
                <h5 class="card-title"><i class="fas fa-fw fa-star mr-2"></i>Favorite Assets</h5>
            </div>
            <table class="table table-sm table-hover mb-0">
                <?php

                while ($row = mysqli_fetch_assoc($sql_favorite_assets)) {
                    $asset_id = intval($row['asset_id']);
                    $asset_name = escapeHtml($row['asset_name']);
                    $asset_type = escapeHtml($row['asset_type']);
                    $asset_icon = getAssetIcon($asset_type);
                    $asset_make = escapeHtml($row['asset_make']);
                    $asset_model = escapeHtml($row['asset_model']);

                    ?>
                    <tr>
                        <td>
                            <a href="#" class="ajax-modal"
                                data-modal-size="lg"
                                data-modal-url="modals/asset/asset.php?id=<?= $asset_id ?>">
                                    <i class="fas fa-fw fa-<?= $asset_icon ?> text-dark mr-1"></i><?= $asset_name ?>
                            </a>
                        </td>
                        <td>
                            <div><?= "$asset_make $asset_model"; ?></div>
                        </td>
                    </tr>
                    <?php
                }
                ?>

            </table>
        </div>

    </div>

    <?php } ?>

    <?php if ((mysqli_num_rows($sql_favorite_credentials) > 0) && (lookupUserPermission('module_credential'))) { ?>

    <div class="col-md-4">

        <div class="card card-dark mb-3">
            <div class="card-header p-2">
                <h5 class="card-title"><i class="fas fa-fw fa-star mr-2"></i>Favorite Credentials</h5>
            </div>

            <table class="table table-sm table-hover mb-0">
                <?php

                while ($row = mysqli_fetch_assoc($sql_favorite_credentials)) {
                    $credential_id = intval($row['credential_id']);
                    $credential_name = escapeHtml($row['credential_name']);
                    $credential_description = escapeHtml($row['credential_description']);
                    $credential_uri = sanitize_url($row['credential_uri']);
                    if (empty($credential_uri)) {
                        $credential_uri_display = "-";
                    } else {
                        $credential_uri_display = "<a href='$credential_uri'>" . truncate($credential_uri,40) . "</a><button class='btn btn-sm clipboardjs' type='button' title='$credential_uri' data-clipboard-text='$credential_uri'><i class='far fa-copy text-secondary'></i></button>";
                    }
                    $credential_uri_2 = sanitize_url($row['credential_uri_2']);
                    $credential_username = escapeHtml(decryptCredentialEntry($row['credential_username']));
                    if (empty($credential_username)) {
                        $credential_username_display = "-";
                    } else {
                        $credential_username_display = "$credential_username<button class='btn btn-sm clipboardjs' type='button' data-clipboard-text='$credential_username'><i class='far fa-copy text-secondary'></i></button>";
                    }
                    $credential_otp_secret = escapeHtml($row['credential_otp_secret']);
                    if (empty($credential_otp_secret)) {
                        $otp_display = "";
                    } else {
                        $otp_display = "<small class='text-secondary'><span onmouseenter='showOTPViaCredentialID($credential_id)'><i class='far fa-clock text-dark'></i> <span id='otp_$credential_id'><i>Hover..</i></span></span><small>";
                    }

                    ?>
                    <tr>
                        <td>
                            <a href="#" class="ajax-modal"
                                data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>">
                                    <i class="fas fa-fw fa-key text-dark mr-1"></i><?= $credential_name ?>
                            </a>
                        </td>
                        <td><?= $credential_username_display ?></td>
                        <td class="text-nowrap">
                            <button class="btn p-0" type="button" onclick="showPasswordViaCredentialID(this, <?php echo $credential_id; ?>)"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button><button class="btn btn-sm" type="button" onclick="copyPasswordViaCredentialID(this, <?php echo $credential_id; ?>)"><i class="far fa-copy text-secondary"></i></button>
                            <div><?= $otp_display ?></div>
                        </td>

                    </tr>
                    <?php
                }
                ?>

            </table>
        </div>

    </div>

    <?php } ?>

    <?php if (mysqli_num_rows($sql_shared_items) > 0) { ?>

        <div class="col-md-4">

            <div class="card card-dark mb-3">
                <div class="card-header p-2">
                    <h5 class="card-title"><i class="fa fa-fw fa-share-square mr-2"></i>Shared Items</h5>
                </div>
                <div class="card-body p-2">
                    <table class="table table-borderless table-sm">
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_assoc($sql_shared_items)) {
                            $item_id = intval($row['item_id']);
                            $item_active = escapeHtml($row['item_active']);
                            $item_key = escapeHtml($row['item_key']);
                            $item_type = escapeHtml($row['item_type']);
                            $item_related_id = intval($row['item_related_id']);
                            $item_note = escapeHtml($row['item_note']);
                            $item_recipient = escapeHtml($row['item_recipient']);
                            $item_views = escapeHtml($row['item_views']);
                            $item_view_limit = escapeHtml($row['item_view_limit']);
                            $item_created_at = escapeHtml($row['item_created_at']);
                            $item_expire_at = escapeHtml($row['item_expire_at']);
                            $item_expire_at_human = timeAgo($row['item_expire_at']);

                            if ($item_type == 'Credential') {
                                $share_item_sql = mysqli_query($mysqli, "SELECT credential_name FROM credentials WHERE credential_id = $item_related_id AND credential_client_id = $client_id");
                                $share_item = mysqli_fetch_assoc($share_item_sql);
                                $item_name = escapeHtml($share_item['credential_name']);
                                $item_icon = "fas fa-key";
                            } elseif ($item_type == 'Document') {
                                $share_item_sql = mysqli_query($mysqli, "SELECT document_name FROM documents WHERE document_id = $item_related_id AND document_client_id = $client_id");
                                $share_item = mysqli_fetch_assoc($share_item_sql);
                                $item_name = escapeHtml($share_item['document_name']);
                                $item_icon = "fas fa-folder";
                            } elseif ($item_type == 'File') {
                                $share_item_sql = mysqli_query($mysqli, "SELECT file_name FROM files WHERE file_id = $item_related_id AND file_client_id = $client_id");
                                $share_item = mysqli_fetch_assoc($share_item_sql);
                                $item_name = escapeHtml($share_item['file_name']);
                                $item_icon = "fas fa-paperclip";
                            }
                            ?>
                            <tr>
                                <td title="<?php echo $item_type; ?>">
                                    <i class="<?php echo $item_icon; ?> mr-2 text-secondary"></i><?php echo $item_name; ?>
                                </td>
                                <td>
                                    <div>Views: <?php echo $item_views ?></div>
                                    <div class="text-secondary"><?php echo $item_recipient; ?></div>
                                </td>
                                <td title="Expires at <?php echo $item_expire_at; ?>">Expires <?php echo $item_expire_at_human ?></td>
                                <td title="Deactivate Link">
                                    <a class="text-danger confirm-link" href="post.php?deactivate_shared_item=<?php echo $item_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-calendar-times mr-2"></i>
                                    </a>
                                </td>
                            </tr>

                            <?php } ?>

                        </tbody>

                    </table>
                </div>
            </div>
        </div>

    <?php } ?>

    <?php
    if (mysqli_num_rows($sql_domains_expiring) > 0
        || mysqli_num_rows($sql_certificates_expiring) > 0
        || mysqli_num_rows($sql_asset_warranties_expiring) > 0
        || mysqli_num_rows($sql_asset_retire) > 0
        || mysqli_num_rows($sql_licenses_expiring) > 0
    ) { ?>

        <div class="col-md-4">

            <div class="card card-dark mb-3">
                <div class="card-header p-2">
                    <h5 class="card-title"><i class="fa fa-fw fa-exclamation-triangle text-warning mr-2"></i>Expiring in the Next 45 Days</h5>
                </div>
                <div class="card-body p-2">

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_domains_expiring)) {
                        $domain_id = intval($row['domain_id']);
                        $domain_name = escapeHtml($row['domain_name']);
                        $domain_expire = escapeHtml($row['domain_expire']);
                        $domain_expire_human = timeAgo($row['domain_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-globe text-secondary mr-1"></i>
                            <a href="domains.php?client_id=<?php echo $client_id; ?>&q=<?php echo $domain_name; ?>">Domain: <?php echo $domain_name; ?></a>
                            <span>-- <?php echo $domain_expire; ?> (<?php echo $domain_expire_human; ?>)</span>
                        </p>
                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_certificates_expiring)) {
                        $certificate_id = intval($row['certificate_id']);
                        $certificate_name = escapeHtml($row['certificate_name']);
                        $certificate_expire = escapeHtml($row['certificate_expire']);
                        $certificate_expire_human = timeAgo($row['certificate_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-lock text-secondary mr-1"></i>
                            <a href="certificates.php?client_id=<?php echo $client_id; ?>&q=<?php echo $certificate_name; ?>">Certificate: <?php echo $certificate_name; ?></a>
                            <span>-- <?php echo $certificate_expire; ?> (<?php echo $certificate_expire_human; ?>)</span>
                        </p>
                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_asset_warranties_expiring)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = escapeHtml($row['asset_name']);
                        $asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
                        $asset_warranty_expire_human = timeAgo($row['asset_warranty_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
                            <a href="asset.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>">Asset Warranty: <?php echo $asset_name; ?></a>
                            <span>-- <?php echo $asset_warranty_expire; ?> (<?php echo $asset_warranty_expire_human; ?>)</span>
                        </p>


                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_asset_retire)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = escapeHtml($row['asset_name']);
                        $asset_install_date = escapeHtml($row['asset_install_date']);
                        $asset_install_date_human = timeAgo($row['asset_install_date']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
                            <a href="asset.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>">Asset Retire: <?php echo $asset_name; ?></a>
                            <span>-- <?php echo $asset_install_date; ?> (<?php echo $asset_install_date_human; ?>)</span>
                        </p>

                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_licenses_expiring)) {
                        $software_id = intval($row['software_id']);
                        $software_name = escapeHtml($row['software_name']);
                        $software_expire = escapeHtml($row['software_expire']);
                        $software_expire_human = timeAgo($row['software_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-cube text-secondary mr-1"></i>
                            <a href="software.php?client_id=<?php echo $client_id; ?>&q=<?php echo $software_name; ?>">License: <?php echo $software_name; ?></a>
                            <span>-- <?php echo $software_expire; ?> (<?php echo $software_expire_human; ?>)</span>
                        </p>

                        <?php
                    }
                    ?>

                </div>
            </div>
        </div>

    <?php } ?>


    <?php
    if (mysqli_num_rows($sql_domains_expired) > 0
        || mysqli_num_rows($sql_certificates_expired) > 0
        || mysqli_num_rows($sql_asset_warranties_expired) > 0
        || mysqli_num_rows($sql_asset_retired) > 0
        || mysqli_num_rows($sql_licenses_expired) > 0
    )
    { ?>

        <div class="col-md-4">

            <div class="card card-dark mb-3">
                <div class="card-header p-2">
                    <h5 class="card-title"><i class="fa fa-fw fa-exclamation-triangle text-danger mr-2"></i>Expired</h5>
                </div>
                <div class="card-body p-2">

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_domains_expired)) {
                        $domain_id = intval($row['domain_id']);
                        $domain_name = escapeHtml($row['domain_name']);
                        $domain_expire = escapeHtml($row['domain_expire']);
                        $domain_expire_human = timeAgo($row['domain_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-globe text-secondary mr-1"></i>
                            <a href="domains.php?client_id=<?php echo $client_id; ?>&q=<?php echo $domain_name; ?>">Domain: <?php echo $domain_name; ?></a>
                            <span>-- <?php echo $domain_expire; ?> (<?php echo $domain_expire_human; ?>)</span>
                        </p>
                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_certificates_expired)) {
                        $certificate_id = intval($row['certificate_id']);
                        $certificate_name = escapeHtml($row['certificate_name']);
                        $certificate_expire = escapeHtml($row['certificate_expire']);
                        $certificate_expire_human = timeAgo($row['certificate_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-lock text-secondary mr-1"></i>
                            <a href="certificates.php?client_id=<?php echo $client_id; ?>&q=<?php echo $certificate_name; ?>">Certificate: <?php echo $certificate_name; ?></a>
                            <span>-- <?php echo $certificate_expire; ?> (<?php echo $certificate_expire_human; ?>)</span>
                        </p>
                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_asset_warranties_expired)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = escapeHtml($row['asset_name']);
                        $asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
                        $asset_warranty_expire_human = timeAgo($row['asset_warranty_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>Asset Warranty:
                            <a href="asset.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                            <span>-- <?php echo $asset_warranty_expire; ?> (<?php echo $asset_warranty_expire_human; ?>)</span>
                        </p>


                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_asset_retired)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = escapeHtml($row['asset_name']);
                        $asset_install_date = escapeHtml($row['asset_install_date']);
                        $asset_install_date_human = timeAgo($row['asset_install_date']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
                            <a href="asset.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>">Asset Retire: <?php echo $asset_name; ?></a>
                            <span>-- <?php echo $asset_install_date; ?> (<?php echo $asset_install_date_human; ?>)</span>
                        </p>

                        <?php
                    }
                    ?>

                    <?php

                    while ($row = mysqli_fetch_assoc($sql_licenses_expired)) {
                        $software_id = intval($row['software_id']);
                        $software_name = escapeHtml($row['software_name']);
                        $software_expire = escapeHtml($row['software_expire']);
                        $software_expire_human = timeAgo($row['software_expire']);

                        ?>
                        <p class="mb-1">
                            <i class="fa fa-fw fa-cube text-secondary mr-1"></i>
                            <a href="software.php?client_id=<?php echo $client_id; ?>&q=<?php echo $software_name; ?>">Software: <?php echo $software_name; ?></a>
                            <span>-- <?php echo $software_expire; ?> (<?php echo $software_expire_human; ?>)</span>
                        </p>

                        <?php
                    }
                    ?>

                </div>
            </div>
        </div>

    <?php } ?>

    <?php if (mysqli_num_rows($sql_stale_tickets) > 0) { ?>

        <!-- Stale Tickets -->

        <div class="col-md-4">

            <div class="card card-dark mb-3">
                <div class="card-header p-2">
                    <h5 class="card-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Stale Tickets <small>(Not updated within 3 days)</small></h5>
                </div>
                <table class="table table table-sm table-hover mb-0">
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql_stale_tickets)) {
                        $ticket_id = intval($row['ticket_id']);
                        $ticket_prefix = escapeHtml($row['ticket_prefix']);
                        $ticket_number = intval($row['ticket_number']);
                        $ticket_subject = escapeHtml($row['ticket_subject']);
                        $ticket_created_at = escapeHtml($row['ticket_created_at']);
                        $ticket_created_at_human = timeAgo($row['ticket_created_at']);

                        ?>
                        <tr>
                            <td><a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id?>"><?php echo "$ticket_prefix$ticket_number"; ?></a></td>
                            <td><?php echo $ticket_subject; ?></td>
                            <td><?php echo $ticket_created_at_human; ?> <small class="text-muted"><?php echo $ticket_created_at; ?></small></td>
                        </tr>

                        <?php
                    }
                    ?>

                    </tbody>
                </table>
            </div>
        </div>

    <?php } ?>

    <?php if (mysqli_num_rows($sql_recent_activities) > 0) { ?>

        <!-- Recent Activities -->

        <div class="col-md-6">

            <div class="card card-dark mb-3">
                <div class="card-header p-2">
                    <h5 class="card-title"><i class="fa fa-fw fa-history mr-2"></i>Recent Activities <small>(Last 10 tasks)</small></h5>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql_recent_activities)) {
                        $log_created_at_time_ago = timeAgo($row['log_created_at']);
                        $log_description = escapeHtml($row['log_description']);

                        ?>
                        <tr>
                            <td class="text-nowrap text-secondary"><?php echo $log_created_at_time_ago; ?></td>
                            <td><?php echo $log_description; ?></td>
                        </tr>

                        <?php
                    }
                    ?>

                    </tbody>
                </table>
                <?php if ($session_user_role == 3) { ?>
                <div class="card-footer p-2">
                    <a href="../admin/audit_logs.php?client=<?php echo $client_id; ?>">See More...</a>
                </div>
                <?php } ?>
            </div>
        </div>

    <?php } ?>

</div>

<!-- Include scripts to fetch TOTP codes and passwords via the credential ID -->
<script src="js/credential_show_otp_via_id.js"></script>
<script src="js/credential_show_password_via_id.js"></script>

<script>
    function updateClientNotes(client_id) {
        var notes = document.getElementById("clientNotes").value;

        // Send a POST request to ajax.php as ajax.php with data client_set_notes=true, client_id=NUM, notes=NOTES
        jQuery.post(
            "ajax.php",
            {
                client_set_notes: 'TRUE',
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                client_id: client_id,
                notes: notes
            }
        )


    }
</script>

<?php

require_once "../includes/footer.php";
