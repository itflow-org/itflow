<div class="modal" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream mr-2"></i><?php echo $service_name; ?> </h5>
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
                                    <input type="text" class="form-control" name="name" placeholder="Name of Service" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Description of Service" required autofocus>
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

                            <!-- TODO: We need a way of adding multiple (optional) URLs? Ideas? -->
                            <div class="form-group">
                                <label>URL</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="url" placeholder="URL" autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" rows="3" placeholder="Enter some notes" name="note"></textarea>
                            </div>
                        </div>


                        <div class="tab-pane fade" id="pills-general">
                            <div class="form-group">
                                <label for="contacts">Contacts</label>
                                <p></p>
                                <select class="form-select" id="contacts" name="contacts" multiple="multiple">
                                    <option value="">- Contacts -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="vendors">Vendors</label>
                                <p></p>
                                <select class="form-select" id="vendors" name="vendors" multiple="multiple">
                                    <option value="">- Vendors -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="locations">Locations</label>
                                <p></p>
                                <select class="form-select" id="locations" name="locations" multiple="multiple">
                                    <option value="">- Locations -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="documents">Documents</label>
                                <p></p>
                                <select class="form-select" id="documents" name="documents" multiple="multiple">
                                    <option value="">- Documents -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>
                        </div>


                        <div class="tab-pane fade" id="pills-assets">
                            <div class="form-group">
                                <label for="assets">Assets</label>
                                <p></p>
                                <select class="form-select" id="assets" name="assets" multiple="multiple">
                                    <option value="">- Assets -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="logins">Logins</label>
                                <p class="text-muted">Logins associated to assets will show as related automatically</p>
                                <select class="form-select" id="logins" name="logins" multiple="multiple">
                                    <option value="">- Logins -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="networks">Networks</label>
                                <p class="text-muted">Networks associated to assets will show as related automatically</p>
                                <select class="form-select" id="networks" name="networks" multiple="multiple">
                                    <option value="">- Networks -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="domains">Domains</label>
                                <p></p>
                                <select class="form-select" id="domains" name="domains" multiple="multiple">
                                    <option value="">- Domains -</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_service" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
