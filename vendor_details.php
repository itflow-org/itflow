<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "inc_all_client.php";
} else {
    require_once "inc_all.php";
}


if (isset($_GET['vendor_id'])) {
    $vendor_id = intval($_GET['vendor_id']);

    $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_id = $vendor_id");

    $row = mysqli_fetch_array($sql);
    $vendor_id = intval($row['vendor_id']);
    $vendor_name = nullable_htmlentities($row['vendor_name']);
    $vendor_description = nullable_htmlentities($row['vendor_description']);
    if (empty($vendor_description)) {
        $vendor_description_display = "-";
    } else {
        $vendor_description_display = $vendor_description;
    }
    $vendor_account_number = nullable_htmlentities($row['vendor_account_number']);
    $vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
    if (empty($vendor_contact_name)) {
        $vendor_contact_name_display = "-";
    } else {
        $vendor_contact_name_display = $vendor_contact_name;
    }
    $vendor_phone = formatPhoneNumber($row['vendor_phone']);
    $vendor_extension = nullable_htmlentities($row['vendor_extension']);
    $vendor_email = nullable_htmlentities($row['vendor_email']);
    $vendor_website = nullable_htmlentities($row['vendor_website']);
    $vendor_hours = nullable_htmlentities($row['vendor_hours']);
    $vendor_sla = nullable_htmlentities($row['vendor_sla']);
    $vendor_code = nullable_htmlentities($row['vendor_code']);
    $vendor_notes = nullable_htmlentities($row['vendor_notes']);
    $vendor_client_id = intval($row['vendor_client_id']);
    $vendor_created_at = nullable_htmlentities($row['vendor_created_at']);

    // Check to see if Vendor belongs to client
    //if($vendor_client_id !== $client_id) {
    //    exit();
    //}

    // Vendor Contacts
    $sql_vendor_contacts = mysqli_query($mysqli, "SELECT * FROM vendor_contacts WHERE vendor_contact_vendor_id = $vendor_id AND vendor_contact_archived_at IS NULL ORDER BY vendor_contact_name DESC");
    $vendor_contact_count = mysqli_num_rows($sql_vendor_contacts);

    ?>

    <div class="row">

        <div class="col-md-3">

            <div class="card card-dark">
                <div class="card-body">
                    <button type="button" class="btn btn-default float-right" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>">
                        <i class="fas fa-fw fa-edit"></i>
                    </button>
                    <h3 class="text-bold"><?php echo $vendor_name; ?></h3>
                    <?php if ($contact_title) { ?>
                        <div class="text-secondary"><?php echo $vendor_description; ?></div>
                    <?php } ?>
                    <hr>
                    <?php
                    if ($contact_email) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href='mailto:<?php echo $contact_email; ?>'><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button></div>
                    <?php }
                    if ($contact_phone) { ?>
                        <div class="mt-2"><i class="fa fa-fw fa-phone text-secondary mr-2"></i><a href="tel:<?php echo "$contact_phone"?>"><?php echo $contact_phone; ?></a></div>
                    <?php }
                    if ($contact_extension) { ?>
                        <div class="ml-4">x<?php echo $contact_extension; ?></div>
                    <?php }
                    if ($contact_mobile) { ?>
                        <div class="mt-l"><i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><a href="tel:<?php echo $contact_mobile; ?>"><?php echo $contact_mobile; ?></a></div>
                    <?php } ?>
                    <div class="mt-2"><i class="fa fa-fw fa-clock text-secondary mr-2"></i><?php echo date('Y-m-d', strtotime($vendor_created_at)); ?></div>

                    <?php require_once "vendor_edit_modal.php";
 ?>

                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title">Notes</h5>
                </div>
                <textarea class="form-control" rows=6 id="vendorNotes" placeholder="Notes" onblur="updateVendorNotes(<?php echo $vendor_id ?>)"><?php echo $vendor_notes ?></textarea>
            </div>

        </div>

        <div class="col-md-9">

            <!-- Breadcrumbs-->
            <ol class="breadcrumb d-print-none">
                <?php if (isset($_GET['client_id'])) { ?>
                <li class="breadcrumb-item">
                    <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="client_vendors.php?client_id=<?php echo $client_id; ?>">Vendors</a>
                </li>
                <?php } else { ?>
                <li class="breadcrumb-item">
                    <a href="vendors.php">Vendors</a>
                </li>
                <?php } ?>
                <li class="breadcrumb-item active"><i class="fas fa-building mr-1"></i><?php echo "$vendor_name";?></li>
            </ol>

            <div class="btn-group mb-3">
                <div class="dropdown dropleft mr-2">
                    <button type="button" class="btn btn-primary" data-toggle="dropdown"><i class="fas fa-plus mr-2"></i>New</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addVendorContactModal">
                            <i class="fa fa-fw fa-user mr-2"></i>New Vendor Contact
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#createVendorNoteModal<?php echo $vendor_id; ?>">
                            <i class="fa fa-fw fa-sticky-note mr-2"></i>New Note
                        </a>
                    </div>
                </div>

                <div class="dropdown dropleft">
                    <button type="button" class="btn btn-outline-primary" data-toggle="dropdown"><i class="fas fa-link mr-2"></i>Link</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkAssetModal">
                            <i class="fa fa-fw fa-desktop mr-2"></i>Asset (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkSoftwareModal">
                            <i class="fa fa-fw fa-cube mr-2"></i>License (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkCredentialModal">
                            <i class="fa fa-fw fa-key mr-2"></i>Credential (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkServiceModal">
                            <i class="fa fa-fw fa-stream mr-2"></i>Service (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkDocumentModal">
                            <i class="fa fa-fw fa-folder mr-2"></i>Document (WIP)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#linkFileModal">
                            <i class="fa fa-fw fa-paperclip mr-2"></i>File (WIP)
                        </a>
                        
                        
                    </div>
                </div>
            </div>

            <div class="card card-dark <?php if ($vendor_contact_count == 0) { echo "d-none"; } ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-fw fa-users mr-2"></i>Contacts</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Title</th>
                                <th>Department</th>
                                <th>Phone</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_vendor_contacts)) {
                                $vendor_contact_id = intval($row['vendor_contact_id']);
                                $vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
                                $vendor_contact_title = nullable_htmlentities($row['vendor_contact_title']);
                                if (empty($vendor_contact_title)) {
                                    $vendor_contact_title_display = "";
                                } else {
                                    $vendor_contact_title_display = "<small class='text-secondary'>$vendor_contact_title</small>";
                                }
                                $vendor_contact_department = nullable_htmlentities($row['vendor_contact_department']);
                                if (empty($vendor_contact_department)) {
                                    $vendor_contact_department_display = "-";
                                } else {
                                    $vendor_contact_department_display = $vendor_contact_department;
                                }
                                $vendor_contact_extension = nullable_htmlentities($row['vendor_contact_extension']);
                                if (empty($vendor_contact_extension)) {
                                    $vendor_contact_extension_display = "";
                                } else {
                                    $vendor_contact_extension_display = "<small class='text-secondary ml-1'>x$vendor_contact_extension</small>";
                                }
                                $vendor_contact_phone = formatPhoneNumber($row['vendor_contact_phone']);
                                if (empty($vendor_contact_phone)) {
                                    $vendor_contact_phone_display = "";
                                } else {
                                    $vendor_contact_phone_display = "<div><i class='fas fa-fw fa-phone mr-2'></i><a href='tel:$vendor_contact_phone'>$vendor_contact_phone$vendor_contact_extension_display</a></div>";
                                }

                                $vendor_contact_mobile = formatPhoneNumber($row['vendor_contact_mobile']);
                                if (empty($vendor_contact_mobile)) {
                                    $vendor_contact_mobile_display = "";
                                } else {
                                    $vendor_contact_mobile_display = "<div class='mt-2'><i class='fas fa-fw fa-mobile-alt mr-2'></i><a href='tel:$vendor_contact_mobile'>$vendor_contact_mobile</a></div>";
                                }
                                $vendor_contact_email = nullable_htmlentities($row['vendor_contact_email']);
                                if (empty($vendor_contact_email)) {
                                    $vendor_contact_email_display = "";
                                } else {
                                    $vendor_contact_email_display = "<div class='mt-1'><i class='fas fa-fw fa-envelope mr-2'></i><a href='mailto:$vendor_contact_email'>$vendor_contact_email</a><button class='btn btn-sm clipboardjs' type='button' data-clipboard-text='$vendor_contact_email'><i class='far fa-copy text-secondary'></i></button></div>";
                                }
                                $vendor_contact_info_display = "$vendor_contact_phone_display $vendor_contact_mobile_display $vendor_contact_email_display";
                                if (empty($vendor_contact_info_display)) {
                                    $vendor_contact_info_display = "-";
                                }
                                $vendor_contact_notes = nullable_htmlentities($row['vendor_contact_notes']);
                                $vendor_contact_created_at = nullable_htmlentities($row['vendor_contact_created_at']);
                                $vendor_contact_archived_at = nullable_htmlentities($row['vendor_contact_archived_at']);

                                ?>
                                <tr>
                                    <th><?php echo $vendor_contact_name; ?></th>
                                    <td><?php echo $vendor_contact_title_display; ?></td>
                                    <td><?php echo $vendor_contact_department_display; ?></td>
                                    <td><?php echo "$vendor_contact_phone_display $vendor_contact_extension_display"; ?></td>
                                    <td><?php echo $vendor_contact_mobile_display; ?></td>
                                    <td><?php echo $vendor_contact_email_display; ?></td>
                                </tr>

                                <?php

                                require "vendor_contact_edit_modal.php";


                            }

                            ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

<?php } ?>

<?php

require_once "footer.php";
