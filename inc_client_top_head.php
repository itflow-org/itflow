<div class="card d-print-none">
    <div class="card-body py-2">
        <div class="row">
            <div class="col">
                <a href="#" data-toggle="collapse" data-target="#clientHeader"><h4 class="text-secondary"><strong><?php echo $client_name; ?></strong></h4></a>
            </div>
            <div class="col">
                <?php if ($session_user_role == 3) { ?>
                <div class="dropdown dropleft text-center">
                    <button class="btn btn-dark btn-sm float-right" type="button" data-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">
                            <i class="fas fa-fw fa-edit mr-2"></i>Edit Client
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#exportClientPDFModal">
                            <i class="fas fa-fw fa-file-pdf mr-2"></i>Export Data
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_client=<?php echo $client_id; ?>">
                            <i class="fas fa-fw fa-archive mr-2"></i>Archive Client
                        </a>
                        <?php if ($session_user_role == 3) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger text-bold" href="#" data-toggle="modal" data-target="#deleteClientModal<?php echo $client_id; ?>">
                            <i class="fas fa-fw fa-trash mr-2"></i>Delete Client
                        </a>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>

        <div class="collapse show" id="clientHeader">

            <div class="row">

                <div class="col-md border-top">
                    <h5 class="text-secondary mt-1">Primary Location</h5>
                    <?php if (!empty($location_address)) { ?>
                        <div>
                            <a href="//maps.<?php echo $session_map_source; ?>.com/?q=<?php echo "$location_address $location_zip"; ?>" target="_blank">
                                <i class="fa fa-fw fa-map-marker-alt text-secondary ml-1 mr-2"></i><?php echo $location_address; ?>
                                <div class="ml-4"><?php echo "$location_city $location_state $location_zip"; ?></div>
                            </a>
                        </div>
                    <?php }

                    if (!empty($location_phone)) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2"></i><a href="tel:<?php echo $location_phone?>"><?php echo $location_phone; ?></a>
                        </div>
                        <hr class="my-2">
                    <?php }

                    if (!empty($client_website)) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-globe text-secondary ml-1 mr-2"></i><a target="_blank" href="//<?php echo $client_website; ?>"><?php echo $client_website; ?></a>
                        </div>
                    <?php } ?>

                </div>

                <div class="col-md border-left border-top">
                    <h5 class="text-secondary mt-1">Primary Contact</h5>
                    <?php

                    if (!empty($contact_name)) { ?>
                        <div>
                            <i class="fa fa-fw fa-user text-secondary ml-1 mr-2"></i> <?php echo $contact_name; ?>
                        </div>
                    <?php }

                    if (!empty($contact_email)) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-envelope text-secondary ml-1 mr-2"></i>
                            <a href="mailto:<?php echo $contact_email; ?>"> <?php echo $contact_email; ?></a>
                        </div>
                        <?php
                    }

                    if (!empty($contact_phone)) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2"></i>
                            <a href="tel:<?php echo $contact_phone; ?>"><?php echo $contact_phone; ?></a>

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
                            <i class="fa fa-fw fa-mobile-alt text-secondary ml-1 mr-2"></i>
                            <a href="tel:<?php echo $contact_mobile; ?>"><?php echo $contact_mobile; ?></a>
                        </div>
                    <?php } ?>

                </div>

                <?php if ($session_user_role == 1 || $session_user_role == 3 && $config_module_enable_accounting == 1) { ?>
                <div class="col-md border-left border-top">
                    <h5 class="text-secondary mt-1">Billing</h5>
                    <div class="ml-1 text-secondary">Hourly Rate
                        <span class="text-dark float-right"> <?php echo numfmt_format_currency($currency_format, $client_rate, $client_currency_code); ?></span>
                    </div>
                    <div class="ml-1 mt-1 text-secondary">Paid
                        <span class="text-dark float-right"> <?php echo numfmt_format_currency($currency_format, $amount_paid, $client_currency_code); ?></span>
                    </div>
                    <div class="ml-1 mt-1 text-secondary">Balance
                        <span class="<?php if ($balance > 0) { echo "text-danger"; }else{ echo "text-dark"; } ?> float-right"> <?php echo numfmt_format_currency($currency_format, $balance, $client_currency_code); ?></span>
                    </div>
                    <div class="ml-1 mt-1 text-secondary">Monthly Recurring
                        <span class="text-dark float-right"> <?php echo numfmt_format_currency($currency_format, $recurring_monthly, $client_currency_code); ?></span>
                    </div>
                    <div class="ml-1 mt-1 text-secondary">Net Terms
                        <span class="text-dark float-right"><?php echo $client_net_terms; ?><small class="text-secondary ml-1">Days</small></span>
                    </div>
                    <?php if(!empty($client_tax_id_number)) { ?>
                    <div class="ml-1 mt-1 text-secondary">Tax ID
                        <span class="text-dark float-right"><?php echo $client_tax_id_number; ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>


                <div class="col-md border-left border-top">
                    <h5 class="text-secondary mt-1">Support</h5>
                    <div class="ml-1 text-secondary">Open Tickets
                        <span class="text-dark float-right"><?php echo $num_active_tickets; ?></span>
                    </div>
                    <div class="ml-1 text-secondary mt-1">Closed Tickets
                        <span class="text-dark float-right"><?php echo $num_closed_tickets; ?></span>
                    </div>
                    <?php
                    if (!empty($client_tag_name_display_array)) { ?>
                    <hr>
                    <?php echo $client_tags_display; ?>
                    <?php } ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php

require_once "client_edit_modal.php";

require_once "client_delete_modal.php";

require_once "client_download_pdf_modal.php";
