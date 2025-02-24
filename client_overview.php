<?php

require_once "includes/inc_all_client.php";

$expiration_days = [7, 30, 90]; // Array of expiration days

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

$sql_recent_tickets = mysqli_query(
    $mysqli,
    "SELECT * FROM tickets
    WHERE ticket_client_id = $client_id
    ORDER BY ticket_created_at ASC
    LIMIT 5"
);

$sql_recent_logins = mysqli_query(
    $mysqli,
    "SELECT * FROM logins
     WHERE login_client_id = $client_id
     AND login_archived_at IS NULL
     ORDER BY login_updated_at ASC
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
        AND ticket_updated_at < CURRENT_DATE - INTERVAL 3 DAY
        AND ticket_resolved_At IS NULL
        AND ticket_closed_at IS NULL
    ORDER BY ticket_updated_at ASC 
    LIMIT 5"
);

foreach ($expiration_days as $days) {
    // Get Domains Expiring
    $sql_domains_expiring = mysqli_query(
        $mysqli,
        "SELECT * FROM domains
        WHERE domain_client_id = $client_id
            AND domain_expire IS NOT NULL
            AND domain_archived_at IS NULL
            AND domain_expire > CURRENT_DATE
            AND domain_expire < CURRENT_DATE + INTERVAL $days DAY
        ORDER BY domain_expire ASC 
        LIMIT 5"
    );

    // Get Certificates Expiring
    $sql_certificates_expiring = mysqli_query(
        $mysqli,
        "SELECT * FROM certificates
        WHERE certificate_client_id = $client_id
            AND certificate_expire IS NOT NULL
            AND certificate_archived_at IS NULL
            AND certificate_expire > CURRENT_DATE
            AND certificate_expire < CURRENT_DATE + INTERVAL $days DAY
        ORDER BY certificate_expire ASC 
        LIMIT 5"
    );

    // Get Licenses Expiring
    $sql_licenses_expiring = mysqli_query(
        $mysqli,
        "SELECT * FROM software
        WHERE software_client_id = $client_id
            AND software_expire IS NOT NULL
            AND software_archived_at IS NULL
            AND software_expire > CURRENT_DATE
            AND software_expire < CURRENT_DATE + INTERVAL $days DAY
        ORDER BY software_expire ASC
        LIMIT 5"
    );

    // Get Asset Warranties Expiring
    $sql_asset_warranties_expiring = mysqli_query(
        $mysqli,
        "SELECT * FROM assets
        WHERE asset_client_id = $client_id
            AND asset_warranty_expire IS NOT NULL
            AND asset_archived_at IS NULL
            AND asset_warranty_expire > CURRENT_DATE
            AND asset_warranty_expire < CURRENT_DATE + INTERVAL $days DAY
        ORDER BY asset_warranty_expire ASC
        LIMIT 5"
    );

    // Get Assets Retiring 7 Year
    $sql_asset_retire = mysqli_query(
        $mysqli,
        "SELECT * FROM assets
        WHERE asset_client_id = $client_id
            AND asset_install_date IS NOT NULL
            AND asset_archived_at IS NULL
            AND asset_install_date + INTERVAL 7 YEAR > CURRENT_DATE
            AND asset_install_date + INTERVAL 7 YEAR <= CURRENT_DATE + INTERVAL $days DAY
        ORDER BY asset_install_date ASC
        LIMIT 5"
    );
}

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
    ORDER BY domain_expire ASC 
    LIMIT 5"
);

// Get Certificates Expired
$sql_certificates_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM certificates
    WHERE certificate_client_id = $client_id
        AND certificate_expire IS NOT NULL
        AND certificate_archived_at IS NULL
        AND certificate_expire < CURRENT_DATE
    ORDER BY certificate_expire ASC 
    LIMIT 5"
);

// Get Licenses Expired
$sql_licenses_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM software
    WHERE software_client_id = $client_id
        AND software_expire IS NOT NULL
        AND software_archived_at IS NULL
        AND software_expire < CURRENT_DATE
    ORDER BY software_expire ASC
    LIMIT 5"
);

// Get Asset Warranties Expired
$sql_asset_warranties_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $client_id
        AND asset_warranty_expire IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_warranty_expire < CURRENT_DATE
    ORDER BY asset_warranty_expire ASC
    LIMIT 5"
);

// Get Retired Assets
$sql_asset_retired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $client_id
        AND asset_install_date IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_install_date + INTERVAL 7 YEAR < CURRENT_DATE  -- Assets retired (installed more than 7 years ago)
    ORDER BY asset_install_date ASC
    LIMIT 5"
);


?>

    <div class="row">

        <!-- Notes -->

        <div class="col-md-4">

            <div class="card card-dark mb-3">
                <div class="card-header">
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
                <div class="card-header">
                    <h5 class="card-title"><i class="fa fa-fw fa-users mr-2"></i>Important Contacts</h5>
                </div>
                <div class="card-body p-2">
                    <table class="table table-borderless table-sm">
                        <?php

                        while ($row = mysqli_fetch_array($sql_important_contacts)) {
                            $contact_id = intval($row['contact_id']);
                            $contact_name = nullable_htmlentities($row['contact_name']);
                            $contact_title = nullable_htmlentities($row['contact_title']);
                            $contact_email = nullable_htmlentities($row['contact_email']);
                            $contact_phone = formatPhoneNumber($row['contact_phone']);
                            $contact_extension = nullable_htmlentities($row['contact_extension']);
                            $contact_mobile = formatPhoneNumber($row['contact_mobile']);

                            ?>
                            <tr>
                                <td>
                                    <a href="#" class="text-bold"
                                        data-toggle="ajax-modal"
                                        data-modal-size="lg"
                                        data-ajax-url="ajax/ajax_contact_details.php"
                                        data-ajax-id="<?php echo $contact_id; ?>">
                                        <?php echo $contact_name; ?>
                                    </a>
                                    <br>
                                    <small class="text-secondary"><?php echo $contact_title; ?></small>
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

        <?php if (mysqli_num_rows($sql_shared_items) > 0) { ?>

            <div class="col-md-4">

                <div class="card card-dark mb-3">
                    <div class="card-header">
                        <h5 class="card-title"><i class="fa fa-fw fa-share-square mr-2"></i>Shared Items</h5>
                    </div>
                    <div class="card-body p-2">
                        <table class="table table-borderless table-sm">
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_shared_items)) {
                                $item_id = intval($row['item_id']);
                                $item_active = nullable_htmlentities($row['item_active']);
                                $item_key = nullable_htmlentities($row['item_key']);
                                $item_type = nullable_htmlentities($row['item_type']);
                                $item_related_id = intval($row['item_related_id']);
                                $item_note = nullable_htmlentities($row['item_note']);
                                $item_recipient = nullable_htmlentities($row['item_recipient']); 
                                $item_views = nullable_htmlentities($row['item_views']);
                                $item_view_limit = nullable_htmlentities($row['item_view_limit']);
                                $item_created_at = nullable_htmlentities($row['item_created_at']);
                                $item_expire_at = nullable_htmlentities($row['item_expire_at']);
                                $item_expire_at_human = timeAgo($row['item_expire_at']);

                                if ($item_type == 'Login') {
                                    $share_item_sql = mysqli_query($mysqli, "SELECT login_name FROM logins WHERE login_id = $item_related_id AND login_client_id = $client_id");
                                    $share_item = mysqli_fetch_array($share_item_sql);
                                    $item_name = nullable_htmlentities($share_item['login_name']);
                                    $item_icon = "fas fa-key";
                                } elseif ($item_type == 'Document') {
                                    $share_item_sql = mysqli_query($mysqli, "SELECT document_name FROM documents WHERE document_id = $item_related_id AND document_client_id = $client_id");
                                    $share_item = mysqli_fetch_array($share_item_sql);
                                    $item_name = nullable_htmlentities($share_item['document_name']);
                                    $item_icon = "fas fa-folder";
                                } elseif ($item_type == 'File') {
                                    $share_item_sql = mysqli_query($mysqli, "SELECT file_name FROM files WHERE file_id = $item_related_id AND file_client_id = $client_id");
                                    $share_item = mysqli_fetch_array($share_item_sql);
                                    $item_name = nullable_htmlentities($share_item['file_name']);
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
                                        <a class="text-danger confirm-link" href="post.php?deactivate_shared_item=<?php echo $item_id; ?>">
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
                    <div class="card-header">
                        <h5 class="card-title"><i class="fa fa-fw fa-exclamation-triangle text-warning mr-2"></i>Upcoming Expirations</h5>
                    </div>
                    <div class="card-body p-2">

                        <?php

                        while ($row = mysqli_fetch_array($sql_domains_expiring)) {
                            $domain_id = intval($row['domain_id']);
                            $domain_name = nullable_htmlentities($row['domain_name']);
                            $domain_expire = nullable_htmlentities($row['domain_expire']);
                            $domain_expire_human = timeAgo($row['domain_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-globe text-secondary mr-1"></i>
                                <a href="domains.php?client_id=<?php echo $client_id; ?>&q=<?php echo $domain_name; ?>"><?php echo $domain_name; ?></a>
                                <span>-- <?php echo $domain_expire_human; ?> <small class="text-muted"><?php echo $domain_expire; ?></small></span>
                            </p>
                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_certificates_expiring)) {
                            $certificate_id = intval($row['certificate_id']);
                            $certificate_name = nullable_htmlentities($row['certificate_name']);
                            $certificate_expire = nullable_htmlentities($row['certificate_expire']);
                            $certificate_expire_human = timeAgo($row['certificate_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-lock text-secondary mr-1"></i>
                                <a href="certificates.php?client_id=<?php echo $client_id; ?>&q=<?php echo $certificate_name; ?>"><?php echo $certificate_name; ?></a>
                                <span>-- <?php echo $certificate_expire_human; ?> <small class="text-muted"><?php echo $certificate_expire; ?></small></span>
                            </p>
                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_asset_warranties_expiring)) {
                            $asset_id = intval($row['asset_id']);
                            $asset_name = nullable_htmlentities($row['asset_name']);
                            $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
                            $asset_warranty_expire_human = timeAgo($row['asset_warranty_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
                                <a href="asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                                <span>-- <?php echo $asset_warranty_expire_human; ?> <small class="text-muted"><?php echo $asset_warranty_expire; ?></small></span>
                            </p>


                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_asset_retire)) {
                            $asset_id = intval($row['asset_id']);
                            $asset_name = nullable_htmlentities($row['asset_name']);
                            $asset_install_date = nullable_htmlentities($row['asset_install_date']);
                            $asset_install_date_human = timeAgo($row['asset_install_date']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
                                <a href="asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                                <span>-- <?php echo $asset_install_date_human; ?> <small class="text-muted"><?php echo $asset_install_date; ?></small></span>
                            </p>

                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_licenses_expiring)) {
                            $software_id = intval($row['software_id']);
                            $software_name = nullable_htmlentities($row['software_name']);
                            $software_expire = nullable_htmlentities($row['software_expire']);
                            $software_expire_human = timeAgo($row['software_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-cube text-secondary mr-1"></i>
                                <a href="software.php?client_id=<?php echo $client_id; ?>&q=<?php echo $software_name; ?>"><?php echo $software_name; ?></a>
                                <span>-- <?php echo $software_expire_human; ?> <small class="text-muted"><?php echo $software_expire; ?></small></span>
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
                    <div class="card-header">
                        <h5 class="card-title"><i class="fa fa-fw fa-exclamation-triangle text-danger mr-2"></i>Expired</h5></h5>
                    </div>
                    <div class="card-body p-2">

                        <?php

                        while ($row = mysqli_fetch_array($sql_domains_expired)) {
                            $domain_id = intval($row['domain_id']);
                            $domain_name = nullable_htmlentities($row['domain_name']);
                            $domain_expire = nullable_htmlentities($row['domain_expire']);
                            $domain_expire_human = timeAgo($row['domain_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-globe text-secondary mr-1"></i>
                                <a href="domains.php?client_id=<?php echo $client_id; ?>&q=<?php echo $domain_name; ?>"><?php echo $domain_name; ?></a>
                                <span>-- <?php echo $domain_expire_human; ?> <small class="text-muted"><?php echo $domain_expire; ?></small></span>
                            </p>
                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_certificates_expired)) {
                            $certificate_id = intval($row['certificate_id']);
                            $certificate_name = nullable_htmlentities($row['certificate_name']);
                            $certificate_expire = nullable_htmlentities($row['certificate_expire']);
                            $certificate_expire_human = timeAgo($row['certificate_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-lock text-secondary mr-1"></i>
                                <a href="certificates.php?client_id=<?php echo $client_id; ?>&q=<?php echo $certificate_name; ?>"><?php echo $certificate_name; ?></a>
                                <span>-- <?php echo $certificate_expire_human; ?> <small class="text-muted"><?php echo $certificate_expire; ?></small></span>
                            </p>
                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_asset_warranties_expired)) {
                            $asset_id = intval($row['asset_id']);
                            $asset_name = nullable_htmlentities($row['asset_name']);
                            $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
                            $asset_warranty_expire_human = timeAgo($row['asset_warranty_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
                                <a href="asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                                <span>-- <?php echo $asset_warranty_expire_human; ?> <small class="text-muted"><?php echo $asset_warranty_expire; ?></small></span>
                            </p>


                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_asset_retired)) {
                            $asset_id = intval($row['asset_id']);
                            $asset_name = nullable_htmlentities($row['asset_name']);
                            $asset_install_date = nullable_htmlentities($row['asset_install_date']);
                            $asset_install_date_human = timeAgo($row['asset_install_date']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
                                <a href="asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                                <span>-- <?php echo $asset_install_date_human; ?> <small class="text-muted"><?php echo $asset_install_date; ?></small></span>
                            </p>

                            <?php
                        }
                        ?>

                        <?php

                        while ($row = mysqli_fetch_array($sql_licenses_expired)) {
                            $software_id = intval($row['software_id']);
                            $software_name = nullable_htmlentities($row['software_name']);
                            $software_expire = nullable_htmlentities($row['software_expire']);
                            $software_expire_human = timeAgo($row['software_expire']);

                            ?>
                            <p class="mb-1">
                                <i class="fa fa-fw fa-cube text-secondary mr-1"></i>
                                <a href="software.php?client_id=<?php echo $client_id; ?>&q=<?php echo $software_name; ?>"><?php echo $software_name; ?></a>
                                <span>-- <?php echo $software_expire_human; ?> <small class="text-muted"><?php echo $software_expire; ?></small></span>
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
                    <div class="card-header">
                        <h5 class="card-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Stale Tickets <small>(Not updated within 3 days)</small></h5>
                    </div>
                    <div class="card-body p-2">

                        <table class="table table-borderless table-sm">
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_stale_tickets)) {
                                $ticket_id = intval($row['ticket_id']);
                                $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                                $ticket_number = intval($row['ticket_number']);
                                $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                                $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
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
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_recent_activities) > 0) { ?>

            <!-- Recent Activities -->

            <div class="col-md-4">

                <div class="card card-dark mb-3">
                    <div class="card-header">
                        <h5 class="card-title"><i class="fa fa-fw fa-history mr-2"></i>Recent Activities <small>(Last 10 tasks)</small></h5>
                    </div>
                    <div class="card-body p-2">

                        <table class="table table-borderless table-sm">
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_recent_activities)) {
                                $log_created_at_time_ago = timeAgo($row['log_created_at']);
                                $log_description = nullable_htmlentities($row['log_description']);

                                ?>
                                <tr>
                                    <td><?php echo $log_created_at_time_ago; ?></td>
                                    <td><?php echo $log_description; ?></td>
                                </tr>

                                <?php
                            }
                            ?>

                            </tbody>
                        </table>
                    </div>
                    <?php if ($session_user_role == 3) { ?>
                    <div class="card-footer">
                        <a href="admin_audit_log.php?client=<?php echo $client_id; ?>">See More...</a>
                    </div>
                    <?php } ?>
                </div>
            </div>

        <?php } ?>

    </div>

</div>

    <script>
        function updateClientNotes(client_id) {
            var notes = document.getElementById("clientNotes").value;

            // Send a POST request to ajax.php as ajax.php with data client_set_notes=true, client_id=NUM, notes=NOTES
            jQuery.post(
                "ajax.php",
                {
                    client_set_notes: 'TRUE',
                    client_id: client_id,
                    notes: notes
                }
            )


        }
    </script>

<?php

require_once "includes/footer.php";

