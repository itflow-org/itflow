<div class="modal" id="editServiceTemplateModal<?php echo $service_template_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-rocket mr-2"></i>Editing template: <strong><?php echo $service_template_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">

                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-overview">Overview</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-billing">Billing</a>
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
                                    <input type="text" class="form-control" name="name" value="<?php echo $service_template_name; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" value="<?php echo $service_template_description; ?>" required>
                                </div>
                            </div>

                            <!--   //TODO: Integrate with company wide categories: /categories.php  -->
                            <div class="form-group">
                                <label>Category</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="category" value="<?php echo $service_template_category;?> ">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Importance</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                    </div>
                                    <select class="form-control select2" name="importance" required>
                                        <?php foreach ($importance_dict as $importance_id => $importance_name) { ?>
                                            <option <?php if ($service_template_importance == $importance_id) {
                                                        echo "selected";
                                                    } ?> value="<?php echo $importance_id; ?>"><?php echo $importance_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Backup</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-hdd"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="backup" value="<?php echo $service_template_backup; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" rows="3" value="<?php echo $service_template_notes; ?>" name="note"></textarea>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pills-billing">
                            <div class="form-group">
                                <label>Price</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="price" value="<?php echo $service_template_price; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Cost</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="price" value="<?php echo $service_template_cost; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Seats</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="price" value="<?php echo $service_template_price; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Currency <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                                    </div>
                                    <select class="form-control select2" name="currency_code" required>
                                        <option value="">- Currency -</option>
                                        <?php foreach ($currencies_array as $currency_code => $currency_name) { ?>
                                            <option <?php if ($session_company_currency == $currency_code) {
                                                        echo "selected";
                                                    } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>



                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_service_template_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save Template</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>