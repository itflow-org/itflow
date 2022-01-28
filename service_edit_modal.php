<div class="modal" id="editServiceModal<?php echo $service_id ?>" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream mr-2"></i><?php echo "Edit $service_name"; ?> </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id ?>">

                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-overview<?php echo $service_id ?>">Overview</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-general<?php echo $service_id ?>">General</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-assets<?php echo $service_id ?>">Assets</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <!-- //TODO: The multiple selects won't play nicely with the icons or just general formatting. I've just added blank <p> tags to format it better for now -->

                        <div class="tab-pane fade show active" id="pills-overview<?php echo $service_id ?>">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-stream"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Name of Service" value="<?php echo $service_name ?>" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" placeholder="Description of Service" value="<?php echo $service_description ?>" required autofocus>
                                </div>
                            </div>

                            <!--   //TODO: Integrate with company wide categories: /categories.php  -->
                            <div class="form-group">
                                <label>Category</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="category" placeholder="Category" value="<?php echo $service_category ?>" autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Importance</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                    </div>
                                    <select class="form-control select2" name="importance" required>
                                        <option <?php if($service_importance == 'Low'){ echo "selected"; } ?> >Low</option>
                                        <option <?php if($service_importance == 'Medium'){ echo "selected"; } ?> >Medium</option>
                                        <option <?php if($service_importance == 'High'){ echo "selected"; } ?> >High</option>
                                    </select>
                                </div>
                            </div>

                            <!-- TODO: We need a way of adding multiple (optional) URLs? Ideas? -->
                            <!-- <div class="form-group">
                                <label>URL</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="url" placeholder="URL" autofocus>
                                </div>
                            </div> -->

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" rows="3" placeholder="Enter some notes" name="note"><?php echo $service_notes ?></textarea>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pills-general<?php echo $service_id ?>">
                            <div class="form-group">
                                <label for="contacts">Contacts</label>
                                <p></p>
                                <select class="form-select" id="contacts" name="contacts[]" multiple="multiple">
                                    <option value="">- Contacts -</option>
                                    <?php
                                    // Get just the currently selected contact IDs
                                    $selected_ids = array_column(mysqli_fetch_all($sql_contacts,MYSQLI_ASSOC), "contact_id");

                                    // Get all contacts
                                    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = '$client_id'");

                                    while($row = mysqli_fetch_array($sql)){
                                        $contact_id = $row['contact_id'];
                                        $contact_name = $row['contact_name'];

                                        if(in_array($contact_id, $selected_ids)){
                                            echo "<option value=\"$contact_id\" selected>$contact_name</option>";
                                        }
                                        else{
                                            echo "<option value=\"$contact_id\">$contact_name</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="vendors">Vendors</label>
                                <p></p>
                                <select class="form-select" id="vendors" name="vendors[]" multiple="multiple">
                                    <option value="">- Vendors -</option>
                                    <?php
                                    $selected_ids = array_column(mysqli_fetch_all($sql_vendors,MYSQLI_ASSOC), "vendor_id");

                                    $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_client_id = '$client_id'");
                                    while($row = mysqli_fetch_array($sql)){
                                        $vendor_id = $row['vendor_id'];
                                        $vendor_name = $row['vendor_name'];

                                        if(in_array($vendor_id, $selected_ids)){
                                            echo "<option value=\"$vendor_id\" selected>$vendor_name</option>";
                                        }
                                        else{
                                            echo "<option value=\"$vendor_id\">$vendor_name</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="documents">Documents</label>
                                <p></p>
                                <select class="form-select" id="documents" name="documents[]" multiple="multiple">
                                    <option value="">- Documents -</option>
                                    <?php
                                    $selected_ids = array_column(mysqli_fetch_all($sql_docs,MYSQLI_ASSOC), "document_id");

                                    $sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_client_id = '$client_id'");
                                    while($row = mysqli_fetch_array($sql)){
                                        $document_id = $row['document_id'];
                                        $document_name = $row['document_name'];

                                        if(in_array($document_id, $selected_ids)){
                                            echo "<option value=\"$document_id\" selected>$document_name</option>";
                                        }
                                        else{
                                            echo "<option value=\"$document_id\">$document_name</option>";
                                        }

                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- TODO: Services related to other services & certificates -->

                        </div>


                        <div class="tab-pane fade" id="pills-assets<?php echo $service_id ?>">
                            <div class="form-group">
                                <label for="assets">Assets</label>
                                <p></p>
                                <select class="form-select" id="assets" name="assets[]" multiple="multiple">
                                    <option value="">- Assets -</option>
                                    <?php
                                    $selected_ids = array_column(mysqli_fetch_all($sql_assets,MYSQLI_ASSOC), "asset_id");

                                    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id = '$client_id'");
                                    while($row = mysqli_fetch_array($sql)){
                                        $asset_id = $row['asset_id'];
                                        $asset_name = $row['asset_name'];

                                        if(in_array($asset_id, $selected_ids)){
                                            echo "<option value=\"$asset_id\" selected>$asset_name</option>";
                                        }
                                        else{
                                            echo "<option value=\"$asset_id\">$asset_name</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="logins">Logins</label>
                                <p class="text-muted">Logins associated to related assets will show as related automatically</p>
                                <select class="form-select" id="logins" name="logins[]" multiple="multiple">
                                    <option value="">- Logins -</option>
                                    <?php

                                    // Reset the $sql_assets pointer to the start
                                    mysqli_data_seek($sql_assets, 0);

                                    $selected_asset_login_ids = array_column(mysqli_fetch_all($sql_assets,MYSQLI_ASSOC), "login_id");
                                    $selected_login_ids = array_column(mysqli_fetch_all($sql_logins,MYSQLI_ASSOC), "login_id");


                                    $sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_client_id = '$client_id'");
                                    while($row = mysqli_fetch_array($sql)){
                                        $login_id = $row['login_id'];
                                        $login_name = $row['login_name'];

                                        if(in_array($login_id, $selected_asset_login_ids) OR in_array($login_id, $selected_login_ids)){
                                            echo "<option value=\"$login_id\" selected>$login_name</option>";
                                        }
                                        else{
                                            echo "<option value=\"$login_id\">$login_name</option>";
                                        }

                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="domains">Domains</label>
                                <p></p>
                                <select class="form-select" id="domains" name="domains[]" multiple="multiple">
                                    <option value="">- Domains -</option>
                                    <?php
                                    $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_client_id = '$client_id'");
                                    while($row = mysqli_fetch_array($sql)){
                                        $domain_id = $row['domain_id'];
                                        $domain_name = $row['domain_name'];
                                        echo "<option value=\"$domain_id\">$domain_name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_service" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
