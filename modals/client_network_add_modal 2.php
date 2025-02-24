<div class="modal" id="addNetworkModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-network-wired mr-2"></i>New Network</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-network">Network</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-dns">DNS</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>
          </ul>

          <hr>

          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Network name (VLAN, WAN, LAN2 etc)" maxlength="200" required autofocus>
                </div>
              </div>

              <div class="form-group">
                <label>Description</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                  </div>
                  <input type="text" class="form-control" name="description" placeholder="Short Description">
                </div>
              </div>

              <div class="form-group">
                <label>Location</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <select class="form-control select2" name="location">
                    <option value="">- Location -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                      $location_id = intval($row['location_id']);
                      $location_name = nullable_htmlentities($row['location_name']);
                    ?>
                    <option value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>

                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-network">
              <div class="form-group">
                <label>vLAN</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" name="vlan" placeholder="ex. 20">
                </div>
              </div>

              <div class="form-group">
                <label>IP / Network <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                  </div>
                  <input type="text" class="form-control" name="network" placeholder="Network or IP ex 192.168.1.0/24" maxlength="200" required>
                </div>
              </div>

              <div class="form-group">
                <label>Subnet Mask</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-mask"></i></span>
                  </div>
                  <input type="text" class="form-control" name="subnet" placeholder="ex 255.255.255.0" maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                </div>
              </div>

              <div class="form-group">
                <label>Gateway <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
                  </div>
                  <input type="text" class="form-control" name="gateway" placeholder="ex 192.168.1.1" maxlength="200" data-inputmask="'alias': 'ip'" data-mask required>
                </div>
              </div>

              <div class="form-group">
                <label>DHCP Range / IPs</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                  </div>
                  <input type="text" class="form-control" name="dhcp_range" placeholder="ex 192.168.1.11-199"  maxlength="200">
                </div>
              </div>

            </div>
            
            <div class="tab-pane fade" id="pills-dns">
              <div class="form-group">
                <label>Primary DNS</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                  </div>
                  <input type="text" class="form-control" name="primary_dns" placeholder="ex 9.9.9.9"  maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                </div>
              </div>

              <div class="form-group">
                <label>Secondary DNS</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                  </div>
                  <input type="text" class="form-control" name="secondary_dns" placeholder="ex 1.1.1.1"  maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                </div>
              </div>
            </div>
  
            <div class="tab-pane fade" id="pills-notes">
              <div class="form-group">
                <textarea class="form-control" rows="12" placeholder="Enter some notes" name="notes"></textarea>
              </div>
            </div>

          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="add_network" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
