<?php

require_once '../includes/ajax_header.php';

$client_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_name = nullable_htmlentities($row['client_name']);
$client_is_lead = intval($row['client_lead']);
$client_type = nullable_htmlentities($row['client_type']);
$client_website = nullable_htmlentities($row['client_website']);
$client_referral = nullable_htmlentities($row['client_referral']);
$client_currency_code = nullable_htmlentities($row['client_currency_code']);
$client_net_terms = intval($row['client_net_terms']);
$client_tax_id_number = nullable_htmlentities($row['client_tax_id_number']);
$client_abbreviation = nullable_htmlentities($row['client_abbreviation']);
$client_rate = floatval($row['client_rate']);
$client_notes = nullable_htmlentities($row['client_notes']);
$client_created_at = nullable_htmlentities($row['client_created_at']);
$client_archived_at = nullable_htmlentities($row['client_archived_at']);

// Client Tags
$client_tag_id_array = array();
$sql_client_tags = mysqli_query($mysqli, "SELECT tag_id FROM client_tags WHERE client_id = $client_id");
while ($row = mysqli_fetch_array($sql_client_tags)) {
    $client_tag_id = intval($row['tag_id']);
    $client_tag_id_array[] = $client_tag_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class='fa fa-fw fa-user-edit mr-2'></i>Editing Client: <strong><?php echo $client_name; ?></strong></h5>
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
            <?php if ($config_module_enable_accounting) { ?>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="pill" href="#pills-client-billing<?php echo $client_id; ?>">Billing</a>
                </li>
            <?php } ?>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-client-notes<?php echo $client_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-client-details<?php echo $client_id; ?>">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Is Lead</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Name or Company" maxlength="200"
                               value="<?php echo $client_name; ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="lead" value="1" <?php if($client_is_lead == 1){ echo "checked"; } ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Shortened Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        </div>
                        <input type="text" class="form-control" name="abbreviation" placeholder="Shortned name for client - Max chars 6" value="<?php echo $client_abbreviation; ?>" maxlength="6" oninput="this.value = this.value.toUpperCase()">
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
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" name="referral">
                            <option value="">- Select Referral -</option>
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
                        <input type="text" class="form-control" name="website" placeholder="ex. google.com" maxlength="200"
                               value="<?php echo $client_website; ?>">
                    </div>
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
                            <input type="text" class="form-control" name="tax_id_number" maxlength="255"
                                   placeholder="Tax ID Number" value="<?php echo $client_tax_id_number; ?>">
                        </div>
                    </div>

                </div>

            <?php } ?>

            <div class="tab-pane fade" id="pills-client-notes<?php echo $client_id; ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="10" placeholder="Enter some notes"
                       name="notes"><?php echo $client_notes; ?>    
                    </textarea>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_client" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
