<div class="modal" id="editNetworkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-network-wired mr-2"></i>Editing network: <span class="text-bold" id="editNetworkHeader"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="network_id" id="editNetworkId" value="">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pillsEditDetails">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pillsEditNetwork">Network</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pillsEditDNS">DNS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pillsEditNotes">Notes</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

                        <div class="tab-pane fade show active" id="pillsEditDetails">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkName" name="name" placeholder="Network name (VLAN, WAN, LAN2 etc)" maxlength="200" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkDescription" name="description" placeholder="Short Description">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Location</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <select class="form-control select2" id="editNetworkLocation" name="location">
                                        <option value="">- Location -</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pillsEditNetwork">

                            <div class="form-group">
                                <label>vLAN</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" id="editNetworkVlan" name="vlan" placeholder="ex. 20">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>IP / Network <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkCidr" name="network" placeholder="Network or IP ex 192.168.1.0/24" maxlength="200" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Subnet Mask</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-mask"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkSubnet" name="subnet" placeholder="ex 255.255.255.0" maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Gateway <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkGw" name="gateway" placeholder="ex 192.168.1.1" maxlength="200" data-inputmask="'alias': 'ip'" data-mask required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>DHCP Range / IPs</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkDhcp" name="dhcp_range" placeholder="ex 192.168.1.11-199" maxlength="200">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pillsEditDNS">

                            <div class="form-group">
                                <label>Primary DNS</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkPrimaryDNS" name="primary_dns" placeholder="ex 9.9.9.9" maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Secondary DNS</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editNetworkSecondaryDNS" name="secondary_dns" placeholder="ex 1.1.1.1" maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pillsEditNotes">

                            <div class="form-group">
                                <textarea class="form-control" rows="12" id="editNetworkNotes" name="notes" placeholder="Enter some notes"></textarea>
                            </div>

                            <p class="text-muted text-right" id="showNetworkId"></p>
                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_network" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
