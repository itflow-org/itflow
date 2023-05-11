<div class="modal" id="addServiceModal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream mr-2"></i>New Service</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id ?>">

        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-overview">Overview</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-general">General</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-assets">Assets</a>
            </li>
          </ul>

          <hr>

          <div class="tab-content">

            <!-- //TODO: The multiple selects won't play nicely with the icons or just general formatting. I've just added blank <p> tags to format it better for now -->

            <div class="tab-pane fade show active" id="pills-overview">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-stream"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name of Service" required>
                </div>
              </div>

              <div class="form-group">
                <label>Description <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                  </div>
                  <input type="text" class="form-control" name="description" placeholder="Description of Service" required>
                </div>
              </div>

              <!--   //TODO: Integrate with company wide categories: /categories.php  -->
              <div class="form-group">
                <label>Category</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                  </div>
                  <input type="text" class="form-control" name="category" placeholder="Category">
                </div>
              </div>

              <div class="form-group">
                <label>Importance</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                  </div>
                  <select class="form-control select2" name="importance" required>
                    <option>Low</option>
                    <option>Medium</option>
                    <option>High</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Backup</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-hdd"></i></span>
                  </div>
                  <input type="text" class="form-control" name="backup" placeholder="Backup strategy">
                </div>
              </div>

              <div class="form-group">
                <label>Notes</label>
                <textarea class="form-control" rows="3" placeholder="Enter some notes" name="note"></textarea>
              </div>
            </div>

            <div class="tab-pane fade" id="pills-general">
              <div class="form-group">
                <label for="contacts">Select related Contacts</label>
                <select multiple class="form-control" id="contacts" name="contacts[]">
                  <?php
                  $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = '$client_id'");
                  while ($row = mysqli_fetch_array($sql)) {
                    $contact_id = intval($row['contact_id']);
                    $contact_name = nullable_htmlentities($row['contact_name']);
                    echo "<option value=\"$contact_id\">$contact_name</option>";
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label for="vendors">Select related vendors</label>
                <select multiple class="form-control" id="vendors" name="vendors[]">
                  <?php
                  $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_template = 0 AND vendor_client_id = '$client_id'");
                  while ($row = mysqli_fetch_array($sql)) {
                    $vendor_id = intval($row['vendor_id']);
                    $vendor_name = nullable_htmlentities($row['vendor_name']);
                    echo "<option value=\"$vendor_id\">$vendor_name</option>";
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label for="documents">Select related documents</label>
                <select multiple class="form-control" id="documents" name="documents[]">
                  <?php
                  $sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_client_id = '$client_id'");
                  while ($row = mysqli_fetch_array($sql)) {
                    $document_id = intval($row['document_id']);
                    $document_name = nullable_htmlentities($row['document_name']);
                    echo "<option value=\"$document_id\">$document_name</option>";
                  }
                  ?>
                </select>
              </div>

              <!-- TODO: Services related to other services -->

            </div>


            <div class="tab-pane fade" id="pills-assets">

              <div class="row">

                <div class="col">
                  <div class="form-group">
                    <label for="assets">Select related assets</label>
                    <select multiple class="form-control" id="assets" name="assets[]">
                      <?php
                      $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id = '$client_id'");
                      while ($row = mysqli_fetch_array($sql)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = nullable_htmlentities($row['asset_name']);
                        echo "<option value=\"$asset_id\">$asset_name</option>";
                      }
                      ?>
                    </select>
                  </div>
                </div>

                <div class="col">
                  <div class="form-group">
                    <label for="logins">Select related logins</label>
                    <select multiple class="form-control" id="logins" name="logins[]">
                      <?php
                      $sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_client_id = '$client_id'");
                      while ($row = mysqli_fetch_array($sql)) {
                        $login_id = intval($row['login_id']);
                        $login_name = nullable_htmlentities($row['login_name']);
                        echo "<option value=\"$login_id\">$login_name</option>";
                      }
                      ?>
                    </select>
                  </div>
                </div>

              </div>


              <div class="row">

                <div class="col">
                  <div class="form-group">
                    <label for="domains">Select related domains</label>
                    <select multiple class="form-control" id="domains" name="domains[]">
                      <?php
                      $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_client_id = '$client_id'");
                      while ($row = mysqli_fetch_array($sql)) {
                        $domain_id = intval($row['domain_id']);
                        $domain_name = nullable_htmlentities($row['domain_name']);
                        echo "<option value=\"$domain_id\">$domain_name</option>";
                      }
                      ?>
                    </select>
                  </div>
                </div>

                <div class="col">
                  <div class="form-group">
                    <label for="certificates">Select related certificates</label>
                    <select multiple class="form-control" id="certificates" name="certificates[]">
                      <?php
                      $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_client_id = '$client_id'");
                      while ($row = mysqli_fetch_array($sql)) {
                        $cert_id = intval($row['certificate_id']);
                        $cert_name = nullable_htmlentities($row['certificate_name']);
                        $cert_domain = nullable_htmlentities($row['certificate_domain']);
                        echo "<option value=\"$cert_id\">$cert_name ($cert_domain)</option>";
                      }
                      ?>
                    </select>
                  </div>
                </div>

              </div>

            </div>

          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="add_service" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
