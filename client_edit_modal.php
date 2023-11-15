<div class="modal" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-edit mr-2"></i>Editing: <strong>
                        <?php echo $client_name; ?>
                    </strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="lead" value="0">
                <input type="hidden" name="currency_code" value="<?php if (empty($currency_code)) {
                    echo $session_company_currency;
                } else {
                    echo $currency_code;
                } ?>">
                <input type="hidden" name="net_terms" value="<?php echo $client_net_terms; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-client-details<?php echo $client_id; ?>">Details</a>
                        </li>
                        <?php if ($config_module_enable_accounting) { ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-client-billing<?php echo $client_id; ?>">Billing</a>
                        </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-client-more<?php echo $client_id; ?>">More</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-client-details<?php echo $client_id; ?>">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Name or Company"
                                        value="<?php echo $client_name; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Industry</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="type" placeholder="Industry"
                                        value="<?php echo $client_type; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Referral</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-smile-wink"></i></span>
                                    </div>
                                    <select class="form-control select2" data-tags="true" name="referral">
                                        <option value="">N/A</option>
                                        <?php

                                        $referral_sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Referral' AND (category_archived_at > '$client_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                                        while ($row = mysqli_fetch_array($referral_sql)) {
                                            $referral = nullable_htmlentities($row['category_name']);
                                            ?>
                                            <option <?php if ($client_referral == $referral) {
                                                echo "selected";
                                            } ?>>
                                                <?php echo $referral; ?>
                                            </option>

                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Website</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="website" placeholder="ex. google.com"
                                        value="<?php echo $client_website; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Is Lead <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <input type="checkbox" name="lead" value="1"<?php if ($client_is_lead == 1) {
                                            echo "checked";
                                        } ?>>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <?php if ($config_module_enable_accounting) { ?>

                        <div class="tab-pane fade" id="pills-client-billing<?php echo $client_id; ?>">     

                            <div class="form-group">
                                <label>Hourly Rate</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                                    </div>
                                    <input type="text" class="form-control" inputmode="numeric"
                                        pattern="[0-9]*\.?[0-9]{0,2}" name="rate" placeholder="0.00"
                                        value="<?php echo number_format($client_rate, 2, '.', ''); ?>">
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
                                            <option <?php if ($client_currency_code == $currency_code) {
                                                echo "selected";
                                            } ?> value="<?php echo $currency_code; ?>">
                                                <?php echo "$currency_code - $currency_name"; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Invoice Net Terms</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <select class="form-control select2" name="net_terms">
                                        <option value="">- Net Terms -</option>
                                        <?php foreach ($net_terms_array as $net_term_value => $net_term_name) { ?>
                                            <option <?php if ($net_term_value == $client_net_terms) {
                                                echo "selected";
                                            } ?> value="<?php echo $net_term_value; ?>">
                                                <?php echo $net_term_name; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Tax ID</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="tax_id_number"
                                        placeholder="Tax ID Number" value="<?php echo $client_tax_id_number; ?>">
                                </div>
                            </div>

                        </div>

                        <?php } ?>

                        <div class="tab-pane fade" id="pills-client-more<?php echo $client_id; ?>">

                            <div class="form-group">
                                <textarea class="form-control" rows="8" placeholder="Enter some notes"
                                    name="notes"><?php echo $client_notes; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Tags</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                                    </div>
                                    <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                                        <?php

                                        $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 1 ORDER BY tag_name ASC");
                                        while ($row = mysqli_fetch_array($sql_tags_select)) {
                                            $tag_id_select = intval($row['tag_id']);
                                            $tag_name_select = nullable_htmlentities($row['tag_name']);
                                            ?>
                                            <option value="<?php echo $tag_id_select; ?>" <?php if (in_array($tag_id_select, $client_tag_id_array)) { echo "selected"; } ?>><?php echo $tag_name_select; ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_client" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>