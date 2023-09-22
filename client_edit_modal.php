<div class="modal" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-edit mr-2"></i>Editing: <strong><?php echo $client_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-client-details<?php echo $client_id; ?>">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-client-notes<?php echo $client_id; ?>">Notes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-client-tag<?php echo $client_id; ?>">Tag</a>
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
                                    <input type="text" class="form-control" name="name" placeholder="Name or Company" value="<?php echo $client_name; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Industry</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="type" placeholder="Industry" value="<?php echo $client_type; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Referral</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-smile-wink"></i></span>
                                    </div>
                                    <select class="form-control select2" name="referral">
                                        <option value="">N/A</option>
                                        <?php

                                        $referral_sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Referral' AND (category_archived_at > '$client_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                                        while ($row = mysqli_fetch_array($referral_sql)) {
                                            $referral = nullable_htmlentities($row['category_name']);
                                            ?>
                                            <option <?php if ($client_referral == $referral) { echo "selected"; } ?> > <?php echo $referral; ?></option>

                                            <?php
                                        }
                                        ?>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickReferralModal"><i class="fas fa-fw fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Website</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="website" placeholder="ex. google.com" value="<?php echo $client_website; ?>">
                                </div>
                            </div>

                            <?php if ($config_module_enable_accounting) { ?>

                                <div class="form-group">
                                    <label>Hourly Rate</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                                        </div>
                                        <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="rate" placeholder="0.00" value="<?php echo $client_rate; ?>">
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
                                            <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                                <option <?php if ($client_currency_code == $currency_code) { echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
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
                                            <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
                                                <option <?php if ($net_term_value == $client_net_terms) { echo "selected"; } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
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
                                        <input type="text" class="form-control" name="tax_id_number" placeholder="Tax ID Number" value="<?php echo $client_tax_id_number; ?>">
                                    </div>
                                </div>

                            <?php } else { ?>
                                <input type="hidden" name="currency_code" value="<?php if(empty($currency_code)) { echo $session_company_currency; } else { echo $currency_code; } ?>">
                                <input type="hidden" name="net_terms" value="<?php echo $net_term_value; ?>">
                            <?php } ?>

                        </div>

                        <div class="tab-pane fade" id="pills-client-notes<?php echo $client_id; ?>">

                            <div class="form-group">
                                <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?php echo $client_notes; ?></textarea>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-client-tag<?php echo $client_id; ?>">

                            <ul class="list-group">

                                <?php

                                $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 1 ORDER BY tag_name ASC");

                                while ($row = mysqli_fetch_array($sql_tags_select)) {
                                    $tag_id_select = intval($row['tag_id']);
                                    $tag_name_select = nullable_htmlentities($row['tag_name']);
                                    $tag_color_select = nullable_htmlentities($row['tag_color']);
                                    if (empty($tag_color_select)) {
                                        $tag_color_select = "dark";
                                    }
                                    $tag_icon_select = nullable_htmlentities($row['tag_icon']);
                                    if (empty($tag_icon_select)) {
                                        $tag_icon_select = "tag";
                                    }

                                    ?>
                                    <li class="list-group-item">
                                        <div class="custom-control custom-checkbox">
                                            <input class="custom-control-input" type="checkbox" id="tagCheckbox<?php echo "$tag_id_select$client_id"; ?>" name="tags[]" value="<?php echo $tag_id_select; ?>" <?php if (in_array($tag_id_select, $client_tag_id_array)) { echo "checked"; } ?>>
                                            <label for="tagCheckbox<?php echo "$tag_id_select$client_id"; ?>" class="custom-control-label">
                                                <span class="badge bg-<?php echo $tag_color_select; ?>">
                                                    <?php echo "<i class='fa fw fa-$tag_icon_select mr-2'></i>"; ?><?php echo $tag_name_select; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </li>

                                <?php } ?>

                            </ul>

                            <?php if (mysqli_num_rows($sql_tags_select) == 0){ ?>

                                <div class='my-3 text-center'>
                                    <i class='fa fa-fw fa-6x fa-tags text-secondary'></i>
                                    <h3 class='text-secondary mt-3'>No Tags Found!</h3>
                                    <a href="settings_tags.php">Try adding a few <b>Settings > Tags</b></a>
                                </div>

                            <?php } ?>

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
