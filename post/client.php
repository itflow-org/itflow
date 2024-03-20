<?php

/*
 * ITFlow - GET/POST request handler for clients/customers (overview)
 */

if (isset($_POST['add_client'])) {

    require_once 'post/client_model.php';


    validateAdminRole();

    $location_phone = preg_replace("/[^0-9]/", '', $_POST['location_phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $country = sanitizeInput($_POST['country']);
    $contact = sanitizeInput($_POST['contact']);
    $title = sanitizeInput($_POST['title']);
    $contact_phone = preg_replace("/[^0-9]/", '', $_POST['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '', $_POST['contact_extension']);
    $contact_mobile = preg_replace("/[^0-9]/", '', $_POST['contact_mobile']);
    $contact_email = sanitizeInput($_POST['contact_email']);

    $extended_log_description = '';

    $return_data = createClient(
        [
            'name' => $name,
            'type' => $type,
            'website' => $website,
            'referral' => $referral,
            'rate' => $rate,
            'currency_code' => $currency_code,
            'net_terms' => $net_terms,
            'tax_id_number' => $tax_id_number,
            'lead' => $lead,
            'notes' => $notes,
            'location_phone' => $location_phone,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'country' => $country,
            'contact' => $contact,
            'title' => $title,
            'contact_phone' => $contact_phone,
            'contact_extension' => $contact_extension,
            'contact_mobile' => $contact_mobile,
            'contact_email' => $contact_email
        ]
    );
    $client_id = $return_data['client_id'];
    referWithAlert(
        'Client <strong>' . $name . '</strong> added', 'success', 'clients.php?client_id=' . $client_id
    );
}

if (isset($_POST['edit_client'])) {

    require_once 'post/client_model.php';

    validateAdminRole();

    $client_id = intval($_POST['client_id']);

    $return_data = updateClient(
            [
                'client_id' => $client_id,
                'name' => $name,
                'type' => $type,
                'website' => $website,
                'referral' => $referral,
                'rate' => $rate,
                'currency_code' => $currency_code,
                'net_terms' => $net_terms,
                'tax_id_number' => $tax_id_number,
                'lead' => $lead,
                'notes' => $notes
            ]
        );
    referWithAlert(
        'Client <strong>' . $name . '</strong> updated', 'success', 'clients.php?client_id=' . $client_id
    );
}

if (isset($_GET['archive_client'])) {

    validateAdminRole();

    $client_id = intval($_GET['archive_client']);

    $return_data = archiveClient([
        'client_id' => $client_id
    ]);
    referWithAlert(
        'Client <strong>' . $return_data['client_name'] . '</strong> archived'
    );
}

if (isset($_GET['undo_archive_client'])) {

    validateAdminRole();

    $client_id = intval($_GET['undo_archive_client']);
    $return_data = unarchiveClient($client_id);
    referWithAlert(
        'Client <strong>' . $return_data['client_name'] . '</strong> unarchived'
    );
}

if (isset($_GET['delete_client'])) {

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $client_id = intval($_GET['delete_client']);



    $return_data = deleteClient(
        [
            'client_id' => $client_id
        ]
    );
    if ($return_data['status'] == 'success') {
        referWithAlert(
            'Client deleted along with all associated data'
        );
    } else {
        referWithAlert(
            'Client <strong>' . $return_data['client_name'] . '</strong> could not be deleted'
        );
    }

}

if (isset($_POST['export_clients_csv'])) {

    //get records from database
    $sql = mysqli_query($mysqli, "SELECT * FROM clients
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        ORDER BY client_name ASC
    ");

    if ($sql->num_rows > 0) {
        $delimiter = ",";
        $filename = $session_company_name . "-Clients-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Client Name', 'Industry', 'Referral', 'Website', 'Primary Address', 'Contact Name', 'Contact Phone', 'Extension', 'Contact Mobile', 'Contact Email', 'Creation Date');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['client_name'], $row['client_type'], $row['client_referral'], $row['client_website'], $row['location_address'] . ' ' . $row['location_city'] . ' ' . $row['location_state'] . ' ' . $row['location_zip'], $row['contact_name'], formatPhoneNumber($row['contact_phone']), $row['contact_extension'], formatPhoneNumber($row['contact_mobile']), $row['contact_email'], $row['client_created_at']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if (isset($_POST['export_client_pdf'])) {

    validateAdminRole();

    $client_id = intval($_POST['client_id']);
    $export_contacts = intval($_POST['export_contacts']);
    $export_locations = intval($_POST['export_locations']);
    $export_assets = intval($_POST['export_assets']);
    $export_software = intval($_POST['export_software']);
    $export_logins = intval($_POST['export_logins']);
    $export_networks = intval($_POST['export_networks']);
    $export_certificates = intval($_POST['export_certificates']);
    $export_domains = intval($_POST['export_domains']);
    $export_tickets = intval($_POST['export_tickets']);
    $export_scheduled_tickets = intval($_POST['export_scheduled_tickets']);
    $export_vendors = intval($_POST['export_vendors']);
    $export_invoices = intval($_POST['export_invoices']);
    $export_recurring = intval($_POST['export_recurring']);
    $export_quotes = intval($_POST['export_quotes']);
    $export_payments = intval($_POST['export_payments']);
    $export_trips = intval($_POST['export_trips']);
    $export_logs = intval($_POST['export_logs']);


    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients 
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE client_id = $client_id
    ");

    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    $location_address = $row['location_address'];
    $location_city = $row['location_city'];
    $location_state = $row['location_state'];
    $location_zip = $row['location_zip'];
    $contact_name = $row['contact_name'];
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_email = $row['contact_email'];
    $client_website = $row['client_website'];

    $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
    $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
    $sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_client_id = $client_id ORDER BY login_name ASC");
    $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets 
        LEFT JOIN contacts ON asset_contact_id = contact_id 
        LEFT JOIN locations ON asset_location_id = location_id
        WHERE asset_client_id = $client_id
        AND asset_archived_at IS NULL
        ORDER BY asset_type ASC"
    );
    $sql_asset_workstations = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id WHERE asset_client_id = $client_id AND (asset_type = 'desktop' OR asset_type = 'laptop') AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $sql_asset_servers = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN locations ON asset_location_id = location_id WHERE asset_client_id = $client_id AND asset_type = 'server' AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $sql_asset_vms = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id AND asset_type = 'virtual machine' AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $sql_asset_network = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN locations ON asset_location_id = location_id WHERE asset_client_id = $client_id AND (asset_type = 'Firewall/Router' OR asset_type = 'Switch' OR asset_type = 'Access Point') AND asset_archived_at IS NULL ORDER BY asset_type ASC");
    $sql_asset_other = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id WHERE asset_client_id = $client_id AND (asset_type NOT LIKE 'laptop' AND asset_type NOT LIKE 'desktop' AND asset_type NOT LIKE 'server' AND asset_type NOT LIKE 'virtual machine' AND asset_type NOT LIKE 'firewall/router' AND asset_type NOT LIKE 'switch' AND asset_type NOT LIKE 'access point') AND asset_archived_at IS NULL ORDER BY asset_type ASC");
    $sql_networks = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL ORDER BY network_name ASC");
    $sql_domains = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL ORDER BY domain_name ASC");
    $sql_certficates = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL ORDER BY certificate_name ASC");
    $sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL ORDER BY software_name ASC");

    ?>

    <script src='plugins/pdfmake/pdfmake.min.js'></script>
    <script src='plugins/pdfmake/vfs_fonts.js'></script>
    <script>

        var docDefinition = {
            info: {
                title: '<?php echo strtoAZaz09($client_name); ?>-IT Documentation',
                author: <?php echo json_encode($session_company_name); ?>
            },

            pageMargins: [ 15, 15, 15, 15 ],

            content: [
                {
                    text: <?php echo json_encode($client_name); ?>,
                    style: 'title'
                },

                {
                    layout: 'lightHorizontalLines',
                    table: {
                        body: [
                            [
                                {
                                    text: 'Address',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($location_address); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'City State Zip',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode("$location_city $location_state $location_zip"); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_phone); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Website',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($client_website); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Contact',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Email',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_email); ?>,
                                    style: 'item'
                                }
                            ]
                        ]
                    }
                },

                //Contacts Start
                <?php if(mysqli_num_rows($sql_contacts) > 0 && $export_contacts == 1){ ?>
                {
                    text: 'Contacts',
                    style: 'title'
                },

                {
                    table: {
                        // headers are automatically repeated if the table spans over multiple pages
                        // you can declare how many rows should be treated as headers
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Title',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Department',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Email',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Mobile',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_contacts)){
                            $contact_name = $row['contact_name'];
                            $contact_title = $row['contact_title'];
                            $contact_phone = formatPhoneNumber($row['contact_phone']);
                            $contact_extension = $row['contact_extension'];
                            if(!empty($contact_extension)){
                                $contact_extension = "x$contact_extension";
                            }
                            $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                            $contact_email = $row['contact_email'];
                            $contact_department = $row['contact_department'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_title); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_department); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_email); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$contact_phone $contact_extension"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_mobile); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Contact END

                //Locations Start
                <?php if(mysqli_num_rows($sql_locations) > 0 && $export_locations == 1){ ?>
                {
                    text: 'Locations',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Address',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_locations)){
                            $location_name = $row['location_name'];
                            $location_address = $row['location_address'];
                            $location_city = $row['location_city'];
                            $location_state = $row['location_state'];
                            $location_zip = $row['location_zip'];
                            $location_phone = formatPhoneNumber($row['location_phone']);
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$location_address $location_city $location_state $location_zip"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_phone); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Locations END

                //Vendors Start
                <?php if(mysqli_num_rows($sql_vendors) > 0 && $export_vendors == 1){ ?>
                {
                    text: 'Vendors',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Description',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Website',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Account Number',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_vendors)){
                            $vendor_name = $row['vendor_name'];
                            $vendor_description = $row['vendor_description'];
                            $vendor_account_number = $row['vendor_account_number'];
                            $vendor_contact_name = $row['vendor_contact_name'];
                            $vendor_phone = formatPhoneNumber($row['vendor_phone']);
                            $vendor_email = $row['vendor_email'];
                            $vendor_website = $row['vendor_website'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($vendor_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_description); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_phone); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_website); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_account_number); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Vendors END

                //Logins Start
                <?php if(mysqli_num_rows($sql_logins) > 0 && $export_logins == 1){ ?>
                {
                    text: 'Logins',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Description',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Username',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Password',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'URI',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_logins)){
                            $login_name = $row['login_name'];
                            $login_description = $row['login_description'];
                            $login_username = decryptLoginEntry($row['login_username']);
                            $login_password = decryptLoginEntry($row['login_password']);
                            $login_uri = $row['login_uri'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($login_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_description); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_username); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_password); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_uri); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php

                }

                ?>
                //Logins END

                //Assets Start
                <?php if(mysqli_num_rows($sql_assets) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Assets',
                    style: 'assetTitle'
                },
                <?php } ?>
                //Assets END

                //Asset Workstations Start
                <?php if(mysqli_num_rows($sql_asset_workstations) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Workstations',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Assigned To',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_workstations)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $contact_name = $row['contact_name'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Workstation END

                //Assets Servers Start
                <?php if(mysqli_num_rows($sql_asset_servers) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Servers',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_servers)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Servers END

                //Asset VMs Start
                <?php if(mysqli_num_rows($sql_asset_vms) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Virtual Machines',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_vms)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset VMs END

                //Assets Network Devices Start
                <?php if(mysqli_num_rows($sql_asset_network) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Network Devices',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_network)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Network Devices END

                //Asset Other Start
                <?php if(mysqli_num_rows($sql_asset_other) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Other Devices',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_other)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }

                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Other END

                //Software Start
                <?php if(mysqli_num_rows($sql_software) > 0 && $export_software == 1){ ?>
                {
                    text: 'Software',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'License',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'License Key',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Notes',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_software)){
                            $software_name = $row['software_name'];
                            $software_type = $row['software_type'];
                            $software_key = $row['software_key'];
                            $software_license_type = $row['software_license_type'];
                            $software_notes = $row['software_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($software_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_license_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_key); ?>,
                                    style: 'item'
                                },

                                {
                                    text: <?php echo json_encode($software_notes); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Software END

                //Networks Start
                <?php if(mysqli_num_rows($sql_networks) > 0 && $export_networks == 1){ ?>
                {
                    text: 'Networks',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'vLAN',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Network Subnet',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Gateway',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'DHCP Range',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_networks)){
                            $network_name = $row['network_name'];
                            $network_vlan = $row['network_vlan'];
                            $network = $row['network'];
                            $network_gateway = $row['network_gateway'];
                            $network_dhcp_range = $row['network_dhcp_range'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($network_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_vlan); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_gateway); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_dhcp_range); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Networks END

                //Domains Start
                <?php if(mysqli_num_rows($sql_domains) > 0 && $export_domains == 1){ ?>
                {
                    text: 'Domains',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Domain Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Expire',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_domains)){
                            $domain_name = $row['domain_name'];
                            $domain_expire = $row['domain_expire'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($domain_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($domain_expire); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Domains END

                //Certificates Start
                <?php if(mysqli_num_rows($sql_certficates) > 0 && $export_certificates == 1){ ?>
                {
                    text: 'Certificates',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Certificate Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Domain Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Issuer',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Expiration Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_certficates)){
                            $certificate_name = $row['certificate_name'];
                            $certificate_domain = $row['certificate_domain'];
                            $certificate_issued_by = $row['certificate_issued_by'];
                            $certificate_expire = $row['certificate_expire'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($certificate_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_domain); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_issued_by); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_expire); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Certificates END



            ], //End Content,
            styles: {
                //Title
                title: {
                    fontSize: 15,
                    margin: [0,20,0,5],
                    bold: true
                },
                assetTitle: {
                    fontSize: 15,
                    margin: [0,20,0,0],
                    bold: true
                },
                //Asset Subtitle
                assetSubTitle: {
                    fontSize: 10,
                    margin: [0,10,0,5],
                    bold: true
                },
                //Item Header
                itemHeader: {
                    fontSize: 9,
                    margin: [0,1,0,1],
                    bold: true
                },
                //item
                item: {
                    fontSize: 9,
                    margin: [0,1,0,1]
                }
            }
        };


        //pdfMake.createPdf(docDefinition).download('<?php echo strtoAZaz09($client_name); ?>-IT_Documentation-<?php echo date('Y-m-d'); ?>');
        pdfMake.createPdf(docDefinition).download('<?php echo strtoAZaz09($client_name); ?>-IT_Documentation-<?php echo date('Y-m-d'); ?>');

    </script>


    <?php

}
