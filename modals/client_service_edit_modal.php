<div class="modal" id="editServiceModal<?php echo $service_id ?>" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream mr-2"></i>Editing service: <strong><?php echo $service_name; ?></strong></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id ?>">
        <input type="hidden" name="service_id" value="<?php echo $service_id ?>">

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

            <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-overview<?php echo $service_id ?>">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-stream"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name of Service" value="<?php echo $service_name ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label>Description <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                  </div>
                  <input type="text" class="form-control" name="description" placeholder="Description of Service" value="<?php echo $service_description ?>" required>
                </div>
              </div>

              <!--   //TODO: Integrate with company wide categories: /categories.php  -->
              <div class="form-group">
                <label>Category</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                  </div>
                  <input type="text" class="form-control" name="category" placeholder="Category" value="<?php echo $service_category ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Importance</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                  </div>
                  <select class="form-control select2" name="importance" required>
                    <option <?php if ($service_importance == 'Low') { echo "selected"; } ?> >Low</option>
                    <option <?php if ($service_importance == 'Medium') { echo "selected"; } ?> >Medium</option>
                    <option <?php if ($service_importance == 'High') { echo "selected"; } ?> >High</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Backup</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-hdd"></i></span>
                  </div>
                  <input type="text" class="form-control" name="backup" placeholder="Backup strategy" value="<?php echo $service_backup ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Notes</label>
                <textarea class="form-control" rows="3" placeholder="Enter some notes" name="note"><?php echo $service_notes ?></textarea>
              </div>
            </div>

            <div class="tab-pane fade" id="pills-general<?php echo $service_id ?>">

              <div class="form-group">
                <label for="contacts">Contacts</label>
                <select multiple class="form-control select2" name="contacts[]">
                  <?php
                  // Get just the currently selected contact IDs
                  $selected_ids = array_column(mysqli_fetch_all($sql_contacts, MYSQLI_ASSOC), "contact_id");

                  // Get all contacts
                  // NOTE: These are called $sql_all and $row_all for a reason - anything overwriting $sql or $row will break the current while loop we are in from client_services.php

                  $sql_all = mysqli_query($mysqli, "SELECT * FROM contacts WHERE (contact_archived_at > '$service_created_at' OR contact_archived_at IS NULL) AND contact_client_id = $client_id");

                  while ($row_all = mysqli_fetch_array($sql_all)) {
                    $contact_id = intval($row_all['contact_id']);
                    $contact_name = nullable_htmlentities($row_all['contact_name']);

                    if (in_array($contact_id, $selected_ids)) {
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
                <select multiple class="form-control select2" name="vendors[]">
                  <?php
                  $selected_ids = array_column(mysqli_fetch_all($sql_vendors, MYSQLI_ASSOC), "vendor_id");

                  $sql_all = mysqli_query($mysqli, "SELECT * FROM vendors WHERE (vendor_archived_at > '$service_created_at' OR vendor_archived_at IS NULL) AND vendor_template = 0 AND vendor_client_id = $client_id");
                  while ($row_all = mysqli_fetch_array($sql_all)) {
                    $vendor_id = intval($row_all['vendor_id']);
                    $vendor_name = nullable_htmlentities($row_all['vendor_name']);

                    if (in_array($vendor_id, $selected_ids)) {
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
                <select multiple class="form-control select2" name="documents[]">
                  <?php
                  $selected_ids = array_column(mysqli_fetch_all($sql_docs, MYSQLI_ASSOC), "document_id");

                  $sql_all = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_archived_at IS NULL AND document_client_id = $client_id");
                  while ($row_all = mysqli_fetch_array($sql_all)) {
                    $document_id = intval($row_all['document_id']);
                    $document_name = nullable_htmlentities($row_all['document_name']);

                    if (in_array($document_id, $selected_ids)) {
                      echo "<option value=\"$document_id\" selected>$document_name</option>";
                    }
                    else{
                      echo "<option value=\"$document_id\">$document_name</option>";
                    }

                  }
                  ?>
                </select>
              </div>

              <!-- TODO: Services related to other services -->

            </div>


            <div class="tab-pane fade" id="pills-assets<?php echo $service_id ?>">

              <div class="form-group">
                <label for="assets">Assets</label>
                <select multiple class="form-control select2" name="assets[]">
                  <?php
                  $selected_ids = array_column(mysqli_fetch_all($sql_assets, MYSQLI_ASSOC), "asset_id");

                  $sql_all = mysqli_query($mysqli, "SELECT * FROM assets WHERE (asset_archived_at > '$service_created_at' OR asset_archived_at IS NULL) AND asset_client_id = $client_id");
                  while ($row_all = mysqli_fetch_array($sql_all)) {
                    $asset_id = intval($row_all['asset_id']);
                    $asset_name = nullable_htmlentities($row_all['asset_name']);

                    if (in_array($asset_id, $selected_ids)) {
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
                <select multiple class="form-control select2" name="logins[]">
                  <?php
                  $selected_ids = array_column(mysqli_fetch_all($sql_logins, MYSQLI_ASSOC), "login_id");

                  $sql_all = mysqli_query($mysqli, "SELECT * FROM logins WHERE (login_archived_at > '$service_created_at' OR login_archived_at IS NULL) AND login_client_id = $client_id");
                  while ($row_all = mysqli_fetch_array($sql_all)) {
                    $login_id = intval($row_all['login_id']);
                    $login_name = nullable_htmlentities($row_all['login_name']);

                    if (in_array($login_id, $selected_ids)) {
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
                <select multiple class="form-control select2" name="domains[]">
                  <?php
                  $selected_ids = array_column(mysqli_fetch_all($sql_domains, MYSQLI_ASSOC), "domain_id");

                  $sql_all = mysqli_query($mysqli, "SELECT * FROM domains WHERE (domain_archived_at > '$service_created_at' OR domain_archived_at IS NULL) AND domain_client_id = $client_id");
                  while ($row_all = mysqli_fetch_array($sql_all)) {
                    $domain_id = intval($row_all['domain_id']);
                    $domain_name = nullable_htmlentities($row_all['domain_name']);

                    if (in_array($domain_id, $selected_ids)) {
                      echo "<option value=\"$domain_id\" selected>$domain_name</option>";
                    }
                    else{
                      echo "<option value=\"$domain_id\">$domain_name</option>";
                    }
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label for="certificates">Certificates</label>
                <select multiple class="form-control select2" name="certificates[]">
                  <?php
                  $selected_ids = array_column(mysqli_fetch_all($sql_certificates, MYSQLI_ASSOC), "certificate_id");

                  $sql_all = mysqli_query($mysqli, "SELECT * FROM certificates WHERE (certificate_archived_at > '$service_created_at' OR certificate_archived_at IS NULL) AND certificate_client_id = $client_id");
                  while ($row_all = mysqli_fetch_array($sql_all)) {
                    $cert_id = intval($row_all['certificate_id']);
                    $cert_name = nullable_htmlentities($row_all['certificate_name']);

                    if (in_array($cert_id, $selected_ids)) {
                      echo "<option value=\"$cert_id\" selected>$cert_name</option>";
                    }
                    else{
                      echo "<option value=\"$cert_id\">$cert_name</option>";
                    }
                  }
                  ?>
                </select>
              </div>
            </div>
            
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="edit_service" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
