<?php $show_add_credit = 0; // Remove once credits is added hides the button ?>
<?php
    /*
     * #clientHeader starts open on the client overview and closed everywhere else.
     * The flag is worked out once because the disclosure chevron needs it too:
     * Bootstrap only writes aria-expanded onto a data-api trigger after the first
     * click, so without seeding it here the chevron points the wrong way until
     * something is clicked.
     */
    $client_header_open = basename($_SERVER["PHP_SELF"]) == "client_overview.php";
?>

<div class="card mb-3 d-print-none">
    <div class="card-header pb-1 pt-2 px-3">
        <div class="card-title">
            <a href="#" class="client-header-toggle<?= $client_header_open ? '' : ' collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#clientHeader" aria-controls="clientHeader" aria-expanded="<?= $client_header_open ? 'true' : 'false' ?>"><h4 class="text-dark" data-bs-toggle="tooltip" data-bs-placement="right" title="Client ID: <?= $client_id ?>"><i class="fas fa-fw fa-chevron-right client-header-chevron" aria-hidden="true"></i><strong><?= $client_name ?></strong> <?php if ($client_archived_at) { echo "(archived)"; } ?></h4></a>
        </div>
        <?php if (!empty($client_tag_name_display_array)) { ?><div class="card-title ms-2"><?= $client_tags_display ?></div> <?php } ?>
        <?php if (lookupUserPermission("module_client") >= 2) { ?>
        <div class="card-tools">
            <div class="dropdown dropstart text-center">
                <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                    <?php if (lookupUserPermission("module_support") >= 2) { ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?client_id=<?= $client_id ?>" data-modal-size="lg">
                            <i class="fas fa-fw fa-life-ring me-2"></i>New Ticket
                        </a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item ajax-modal" href="#"
                        data-modal-url="modals/client/client_edit.php?id=<?= $client_id ?>">
                        <i class="fas fa-fw fa-edit me-2"></i>Edit Client
                    </a>
                    <?php if (lookupUserPermission("module_billing") >= 2) { ?>
                        <?php if ($show_add_credit) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addCreditModal">
                            <i class="fas fa-fw fa-wallet me-2"></i>Add Credit
                        </a>
                        <?php } ?>
                    <?php } ?>
                    <?php if (lookupUserPermission("module_client") >= 1) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportClientPDFModal">
                            <i class="fas fa-fw fa-file-pdf me-2"></i>Export Data
                        </a>
                    <?php } ?>

                    <?php if (empty($client_archived_at)) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                            <i class="fas fa-fw fa-archive me-2"></i>Archive Client
                        </a>
                    <?php } else { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-primary confirm-link" href="post.php?restore_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                            <i class="fas fa-fw fa-archive me-2"></i>Restore Client
                        </a>
                    <?php } ?>

                    <?php if (lookupUserPermission("module_client") >= 3 && $client_archived_at) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger text-bold" href="#" data-bs-toggle="modal" data-bs-target="#deleteClientModal<?= $client_id ?>">
                        <i class="fas fa-fw fa-trash me-2"></i>Delete Client
                    </a>
                    <?php } ?>

                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<div class="collapse<?= $client_header_open ? ' show' : '' ?>" id="clientHeader">

    <div class="card-group mb-3">
        <div class="card card-body px-3 py-2">
            <h5>Primary Location</h5>
            <?php /* location_id comes from inc_all_client.php's LEFT JOIN on
                     location_primary = 1, so empty means there is genuinely no
                     primary location - not merely a primary location with no
                     street address filled in. Gated at module_client >= 2 to
                     match enforceUserPermission() in agent/post/location.php;
                     without that a read-only user would get a link that 403s. */ ?>
            <?php if (empty($location_id) && lookupUserPermission("module_client") >= 2) { ?>
                <div class="mb-2">
                    <a class="ajax-modal small" href="#" data-modal-url="modals/location/location_add.php?client_id=<?= $client_id ?>&primary=1">
                        Add Primary Location
                    </a>
                </div>
            <?php }

            if (!empty($location_address)) { ?>
                <div>
                    <a href="//maps.<?= $session_map_source ?>.com/?q=<?= "$location_address $location_zip" ?>" target="_blank">
                        <i class="fa fa-fw fa-map-marker-alt text-secondary ms-1 me-2"></i><?= $location_address ?>
                        <div>
                            <i class="fa fa-fw ms-1 me-2"></i><?= formatAddress('', $location_city, $location_state, $location_zip, '', ' ') ?>
                        </div>
                        <div>
                            <i class="fa fa-fw ms-1 me-2"></i><small><?= $location_country ?></small>
                        </div>
                    </a>
                </div>
            <?php }

            if (!empty($location_phone)) { ?>
                <div>
                    <i class="fa fa-fw fa-phone text-secondary ms-1 me-2"></i><a href="tel:<?= $location_phone ?>"><?= $location_phone ?></a>
                </div>
                <hr class="my-2">
            <?php }

            if (!empty($client_website)) { ?>
                <div class="mt-1">
                    <i class="fa fa-fw fa-globe text-secondary ms-1 me-2"></i><a target="_blank" href="//<?= $client_website ?>"><?= $client_website ?></a>
                </div>
            <?php } ?>
        </div>

        <div class="card card-body px-3 py-2">
            <h5>Primary Contact</h5>
            <?php

            if (empty($contact_id) && lookupUserPermission("module_client") >= 2) { ?>
                <div>
                    <a class="ajax-modal small" href="#" data-modal-url="modals/contact/contact_add.php?client_id=<?= $client_id ?>&primary=1">
                        Add Primary Contact
                    </a>
                </div>
            <?php }

            if (!empty($contact_name)) { ?>
                <div>
                    <i class="fa fa-fw fa-user text-secondary ms-1 me-2"></i><?= $contact_name ?>
                </div>
            <?php }

            if (!empty($contact_email)) { ?>
                <div class="mt-1">
                    <i class="fa fa-fw fa-envelope text-secondary ms-1 me-2"></i><a href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a>
                </div>
                <?php
            }

            if (!empty($contact_phone)) { ?>
                <div class="mt-1">
                    <i class="fa fa-fw fa-phone text-secondary ms-1 me-2"></i><a href="tel:<?= $contact_phone ?>"><?= $contact_phone ?></a>

                    <?php
                    if (!empty($contact_extension)) {
                        echo "<small>x$contact_extension</small>";
                    }
                    ?>
                </div>
                <?php
            }

            if (!empty($contact_mobile)) { ?>
                <div class="mt-1">
                    <i class="fa fa-fw fa-mobile-alt text-secondary ms-1 me-2"></i><a href="tel:<?= $contact_mobile ?>"><?= $contact_mobile ?></a>
                </div>
            <?php } ?>
        </div>

        <?php if (lookupUserPermission("module_financial") >= 1 && $config_module_enable_accounting == 1) { ?>

        <div class="card card-body px-3 py-2">
            <h5>Billing</h5>
            <div class="ms-1 text-secondary">Hourly Rate
                <span class="text-dark float-end"> <?= numfmt_format_currency($currency_format, $client_rate, $client_currency_code) ?></span>
            </div>
            <div class="ms-1 mt-1 text-secondary">Paid
                <span class="text-dark float-end"> <?= numfmt_format_currency($currency_format, $amount_paid, $client_currency_code) ?></span>
            </div>
            <div class="ms-1 mt-1 text-secondary">Balance
                <span class="<?php if ($balance > 0) { echo "text-danger"; }else{ echo "text-dark"; } ?> float-end"> <?= numfmt_format_currency($currency_format, $balance, $client_currency_code) ?></span>
            </div>
            <?php /* Credit Not Ready 2025-08-27 JQ
            if ($credit_balance) { ?>
            <div class="ms-1 mt-1 text-secondary">Credit
                <span class="text-success float-end"><?php echo numfmt_format_currency($currency_format, $credit_balance, $client_currency_code); ?></span>
            </div>
            <?php } */?>
            <div class="ms-1 mt-1 text-secondary">Monthly Recurring
                <span class="text-dark float-end"> <?= numfmt_format_currency($currency_format, $recurring_monthly, $client_currency_code) ?></span>
            </div>
            <div class="ms-1 mt-1 text-secondary">Net Terms
                <span class="text-dark float-end">
                    <?php if ($client_net_terms) { ?>
                    <?= $client_net_terms; ?><small class="text-secondary ms-1">Days</small>
                    <?php } else { ?>
                        On Receipt
                    <?php } ?>
                </span>
            </div>
            <?php if(!empty($client_tax_id_number)) { ?>
            <div class="ms-1 mt-1 text-secondary">Tax ID
                <span class="text-dark float-end font-monospace"><?= $client_tax_id_number ?></span>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <?php if (lookupUserPermission("module_support") >= 1 && $config_module_enable_ticketing == 1) { ?>
        <div class="card card-body px-3 py-2">
            <h5>Support</h5>
            <div class="ms-1 text-secondary">Open Tickets
                <span class="text-dark float-end"><?= $num_active_tickets ?></span>
            </div>
            <div class="ms-1 text-secondary mt-1">Closed Tickets
                <span class="text-dark float-end"><?= $num_closed_tickets ?></span>
            </div>
        </div>
        <?php } ?>

    </div>
</div>

<?php
// require_once "modals/client/client_credit_add.php"; --Credit Not Ready 2025-08-27
require_once "modals/client/client_delete.php";
require_once "modals/client/client_download_pdf.php";
