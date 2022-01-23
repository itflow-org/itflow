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
                            <h4>Service Overview: <?php echo $service_name; ?></h4>
                            <p>Service Importance: <?php echo $service_importance_display; ?>
                            <p><?php echo $service_description; ?></p>

                            <h5><i class="nav-icon fas fa-sticky-note"></i> Notes</h5>
                            <p><?php echo $service_notes; ?></p>
                            <hr>

                            <!-- Assets -->
                            <?php
                            if($sql_assets){ ?>
                                <h5><i class="nav-icon fas fa-desktop"></i> Assets</h5>
                                <ul>
                                    <?php
                                    while($row = mysqli_fetch_array($sql_assets)){
                                        echo "<li><a href=\"client.php?client_id=$client_id&tab=assets&q=$row[asset_name]\">$row[asset_name]</a></li>";
                                    }
                                    ?>
                                </ul>
                            <?php
                            }
                            ?>

                            <!-- Networks -->
                            <?php
                            if($sql_assets){

                                $network_names = [];
                                $network_vlans = [];

                                // Reset the $sql_assets pointer to the start - as we've already cycled through once
                                mysqli_data_seek($sql_assets, 0);

                                // Get networks linked to assets - push their name and vlan to arrays
                                while($row = mysqli_fetch_array($sql_assets)){
                                    if(!empty($row['network_name'])){
                                        array_push($network_names, $row['network_name']);
                                        array_push($network_vlans, $row['network_vlan']);
                                    }
                                }

                                // Remove duplicates
                                $network_names = array_unique($network_names);
                                $network_vlans = array_unique($network_vlans);

                                // Display
                                if(!empty($network_names)){ ?>
                                    <h5><i class="nav-icon fas fa-network-wired"></i> Networks</h5>
                                    <ul>
                                <?php
                                }
                                foreach($network_names as $network){
                                    foreach($network_vlans as $vlan){
                                        echo "<li><a href=\"client.php?client_id=$client_id&tab=networks&q=$network\">$network (VLAN: $vlan)</a></li>";
                                    }
                                }

                                // Not showing/haven't added explicitly linked networks - can't see a need for a network that doesn't have an asset on it?
                                // Can add at a later date if there is a use case for this
                                ?>
                                </ul>
                            <?php
                            }
                            ?>

                            <!-- Domains -->
                            <?php
                            if($sql_assets){ ?>
                                <h5><i class="nav-icon fas fa-map-marker-alt"></i> Locations</h5>
                                <ul>
                                    <?php

                                    // Reset the $sql_assets pointer to the start - as we've already cycled through once
                                    mysqli_data_seek($sql_assets, 0);

                                    // Showing linked locations (from assets)
                                    while($row = mysqli_fetch_array($sql_assets)){
                                        if(!empty($row['location_name'])){
                                            echo "<li><a href=\"client.php?client_id=$client_id&tab=locations&q=$row[location_name]\">$row[location_name]</a></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- Domains -->
                            <?php
                            if($sql_domains){ ?>
                                <h5><i class="nav-icon fas fa-globe"></i> Domains</h5>
                                <ul>
                                    <?php
                                    // Showing linked domains
                                    while($row = mysqli_fetch_array($sql_domains)){
                                        if(!empty($row['domain_name'])){
                                            echo "<li><a href=\"client.php?client_id=$client_id&tab=domains&q=$row[domain_name]\">$row[domain_name]</a></li>";
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

                            <h5><i class="nav-icon fas fa-users"></i> Vendors</h5>
                            <ul>
                                <li>Contoso Developer</li>
                            </ul>

                            <h5><i class="nav-icon fas fa-building"></i> Contacts</h5>
                            <ul>
                                <li>Client Contact</li>
                                <li>Developer Contact</li>
                            </ul>

                            <!-- Logins -->
                            <?php
                            if($sql_assets OR $sql_logins){ ?>
                                <h5><i class="nav-icon fas fa-key"></i> Logins</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_assets pointer to the start - as we've already cycled this once
                                    mysqli_data_seek($sql_assets, 0);

                                    // Showing logins linked to assets
                                    while($row = mysqli_fetch_array($sql_assets)){
                                        if(!empty($row['login_name'])){
                                            echo "<li><a href=\"client.php?client_id=$client_id&tab=logins&q=$row[login_name]\">$row[login_name]</a></li>";
                                        }
                                    }

                                    // Showing explicitly linked logins
                                    while($row = mysqli_fetch_array($sql_logins)){
                                        if(!empty($row['login_name'])){
                                            echo "<li><a href=\"client.php?client_id=$client_id&tab=logins&q=$row[login_name]\">$row[login_name]</a></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <!-- URLs -->
                            <?php
                            if($sql_logins OR $sql_urls){ ?>
                                <h5><i class="nav-icon fas fa-link"></i> URLs</h5>
                                <ul>
                                    <?php
                                    // Reset the $sql_assets pointer to the start
                                    mysqli_data_seek($sql_assets, 0);

                                    // Showing URLs linked to logins
                                    while($row = mysqli_fetch_array($sql_assets)){
                                        if(!empty($row['login_uri'])){
                                            echo "<li><a href=\"$row[login_uri]\">$row[login_uri]</a></li>";
                                        }
                                    }


                                    // Showing explicitly linked URLs
                                    while($row = mysqli_fetch_array($sql_urls)){
                                        if(!empty($row['service_uri'])){
                                            echo "<li><a href=\"$row[service_uri]\">$row[service_uri]</a></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>

                            <h5><i class="nav-icon fas fa-lock"></i> Certificates</h5>
                            <ul>
                                <li>SSLs related to a domain</li>
                            </ul>

                            <h5><i class="nav-icon fas fa-hdd"></i> Backed up by</h5>
                            <ul>
                                <li>Asset</li>
                            </ul>

                            <h5><i class="nav-icon fas fa-file-alt"></i> Documents</h5>
                            <ul>
                                <li>SOP: New user for client finance app</li>
                            </ul>

                            <h5><i class="nav-icon fas fa-file-alt"></i> Services</h5>
                            <ul>
                                <li>Related Service</li>
                            </ul>



                        </div>
                </div>
            </div>
        </div>
    </div>
</div>