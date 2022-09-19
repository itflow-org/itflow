<div class="modal" id="viewServiceModal<?php echo $service_id ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream mr-2"></i><?php echo $service_name; ?> </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">
                <div class="row">

                    <!-- Main/Left side -->
                    <div class="col-8 border-right">
                        <div class="col-12">
                            <h4>Service Overview: <?php echo "$service_name $service_importance_display"; ?></h4>
                            <b>Description:</b> <?php echo $service_description; ?> <br>
                            <b>Backup Info:</b> <?php echo $service_backup; ?> <br><br>

                            <h5><i class="nav-icon fas fa-sticky-note"></i> Notes</h5>
                            <div style="white-space: pre-line"><?php echo $service_notes; ?></div>
                            <hr>

                            <!-- Assets -->
                            <?php
                            if(mysqli_num_rows($sql_assets) > 0){ ?>
                                <h5><i class="nav-icon fas fa-desktop"></i> Assets</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_assets pointer to the start - as we've already cycled through once
                                    mysqli_data_seek($sql_assets, 0);

                                    while($row = mysqli_fetch_array($sql_assets)){
                                        if(!empty($row['asset_ip'])){
                                            $ip = '('.$row["asset_ip"].')';
                                        }
                                        else{
                                            $ip = '';
                                        }
                                        echo "<li><a href=\"client_assets.php?client_id=$client_id&q=$row[asset_name]\">$row[asset_name] </a>$ip</li>";
                                    }
                                    ?>
                                </ul>
                            <?php
                            }
                            ?>

                            <!-- Networks -->
                            <?php
                            if($sql_assets){

                                $networks = [];

                                // Reset the $sql_assets pointer to the start
                                mysqli_data_seek($sql_assets, 0);

                                // Get networks linked to assets - push name to array
                                while($row = mysqli_fetch_array($sql_assets)){
                                    if(!empty($row['network_name'])){
                                        $network_data = "$row[network_name]:$row[network_vlan]";
                                        array_push($networks, $network_data);
                                    }
                                }

                                // Remove duplicates
                                $networks = array_unique($networks);

                                // Display
                                if(!empty($networks)){ ?>
                                    <h5><i class="nav-icon fas fa-network-wired"></i> Networks</h5>
                                    <ul>
                                <?php
                                }
                                foreach($networks as $network){
                                        $network = explode(":", $network);
                                        echo "<li><a href=\"client_networks.php?client_id=$client_id&q=$network[0]\">$network[0] </a>(VLAN $network[1])</li>";
                                }

                                // Not showing/haven't added explicitly linked networks - can't see a need for a network that doesn't have an asset on it?
                                // Can add at a later date if there is a use case for this
                                ?>
                                </ul>
                            <?php
                            }
                            ?>

                            <!-- Locations -->
                            <?php
                            if($sql_assets){

                                $location_names = [];

                                // Reset the $sql_assets pointer to the start - as we've already cycled through once
                                mysqli_data_seek($sql_assets, 0);

                                // Get locations linked to assets - push their name and vlan to arrays
                                while($row = mysqli_fetch_array($sql_assets)){
                                    if(!empty($row['location_name'])){
                                        array_push($location_names, $row['location_name']);
                                    }
                                }

                                // Remove duplicates
                                $location_names = array_unique($location_names);

                                // Display
                                if(!empty($location_names)){ ?>
                                    <h5><i class="nav-icon fas fa-map-marker-alt"></i> Locations</h5>
                                    <ul>
                                    <?php
                                }
                                foreach($location_names as $location){
                                        echo "<li><a href=\"client_locations.php?client_id=$client_id&q=$location\">$location</a></li>";
                                }
                                ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- Domains -->
                            <?php
                            if(mysqli_num_rows($sql_domains) > 0){ ?>
                                <h5><i class="nav-icon fas fa-globe"></i> Domains</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_domains pointer to the start
                                    mysqli_data_seek($sql_domains, 0);

                                    // Showing linked domains
                                    while($row = mysqli_fetch_array($sql_domains)){
                                        if(!empty($row['domain_name'])){
                                            echo "<li><a href=\"client_domains.php?client_id=$client_id&q=$row[domain_name]\">$row[domain_name]</a></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- Certificates -->
                            <?php
                            if(mysqli_num_rows($sql_certificates) > 0){ ?>
                                <h5><i class="nav-icon fas fa-lock"></i> Certificates</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_certificates pointer to the start
                                    mysqli_data_seek($sql_certificates, 0);

                                    // Showing linked certs
                                    while($row = mysqli_fetch_array($sql_certificates)){
                                        if(!empty($row['certificate_name'])){
                                            echo "<li><a href=\"client_certificates.php?client_id=$client_id&q=$row[certificate_name]\">$row[certificate_name] ($row[certificate_domain])</a></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                        </div>
                    </div>


                    <!-- Right side -->
                    <div class="col-4">
                        <div class="col-12">
                            <h4>Additional Related Items</h4>
                            <br>

                            <!-- Vendors -->
                            <?php
                            // Reset the $sql_vendors pointer to the start
                            mysqli_data_seek($sql_vendors, 0);

                            if(mysqli_num_rows($sql_vendors) > 0){ ?>
                                <h5><i class="nav-icon fas fa-users"></i> Vendors</h5>
                                <ul>
                                    <?php
                                    while($row = mysqli_fetch_array($sql_vendors)){
                                        echo "<li><a href=\"client_vendors.php?client_id=$client_id&q=$row[vendor_name]\">$row[vendor_name]</a></li>";
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- Contacts -->
                            <?php
                            if(mysqli_num_rows($sql_contacts) > 0){ ?>
                                <h5><i class="nav-icon fas fa-building"></i> Contacts</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_contacts pointer to the start
                                    mysqli_data_seek($sql_contacts, 0);

                                    while($row = mysqli_fetch_array($sql_contacts)){
                                        echo "<li><a href=\"client_contacts.php?client_id=$client_id&q=$row[contact_name]\">$row[contact_name]</a></li>";
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- Logins -->
                            <?php
                            if(mysqli_num_rows($sql_assets) > 0 || mysqli_num_rows($sql_logins) > 0){ ?>
                                <h5><i class="nav-icon fas fa-key"></i> Logins</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_assets/logins pointer to the start
                                    mysqli_data_seek($sql_assets, 0);
                                    mysqli_data_seek($sql_logins, 0);

                                    // Showing logins linked to assets
                                    while($row = mysqli_fetch_array($sql_assets)){
                                        if(!empty($row['login_name'])){
                                            echo "<li><a href=\"client_logins.php?client_id=$client_id&q=$row[login_name]\">$row[login_name]</a></li>";
                                        }
                                    }

                                    // Showing explicitly linked logins
                                    while($row = mysqli_fetch_array($sql_logins)){
                                        if(!empty($row['login_name'])){
                                            echo "<li><a href=\"client_logins.php?client_id=$client_id&q=$row[login_name]\">$row[login_name]</a></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- URLs -->
                            <?php
                            if($sql_logins || $sql_assets){ ?>
                                <h5><i class="nav-icon fas fa-link"></i> URLs</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_logins pointer to the start
                                    mysqli_data_seek($sql_logins, 0);

                                    // Showing URLs linked to logins
                                    while($row = mysqli_fetch_array($sql_logins)){
                                        if(!empty($row['login_uri'])){
                                            echo "<li><a href=\"https://$row[login_uri]\">$row[login_uri]</a></li>";
                                        }
                                    }

                                    // Reset the $sql_assets pointer to the start
                                    mysqli_data_seek($sql_assets, 0);

                                    // Show URLs linked to assets, that also have logins
                                    while($row = mysqli_fetch_array($sql_assets)){
                                        if(!empty($row['login_uri'])){
                                            echo "<li><a href=\"https://$row[login_uri]\">$row[login_uri]</a></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- Documents -->
                            <?php
                            if(mysqli_num_rows($sql_docs) > 0){ ?>
                                <h5><i class="nav-icon fas fa-file-alt"></i> Documents</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_docs pointer to the start
                                    mysqli_data_seek($sql_docs, 0);

                                    while($row = mysqli_fetch_array($sql_docs)){
                                        echo "<li><a href=\"client_documents.php?client_id=$client_id&q=$row[document_name]\">$row[document_name]</a></li>";
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

<!--                            <h5><i class="nav-icon fas fa-file-alt"></i> Services</h5>-->
<!--                            <ul>-->
<!--                                <li>Related Service - Coming soon!</li>-->
<!--                            </ul>-->

                        </div>
                </div>
            </div>
        </div>
    </div>
</div>