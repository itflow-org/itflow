<?php
session_start();
// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);
?>

<div class="modal" id="editVendorModal<?php echo $vendor_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-building mr-2"></i><?php echo lang('editing_vendor'); ?>: <strong><?php echo $vendor_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                <div class="modal-body bg-white">

                    <!-- Navigation Tabs -->
                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $vendor_id; ?>"><?php echo lang('details'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-support<?php echo $vendor_id; ?>"><?php echo lang('support'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $vendor_id; ?>"><?php echo lang('notes'); ?></a>
                        </li>
                    </ul>

                    <hr>

                    <!-- Tab Contents -->
                    <div class="tab-content">

                        <!-- Details Tab -->
                        <div class="tab-pane fade show active" id="pills-details<?php echo $vendor_id; ?>">

                            <div class="form-group">
                                <label><?php echo lang('vendor_name'); ?> <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="<?php echo lang('vendor_name_placeholder'); ?>" value="<?php echo "$vendor_name"; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label><?php echo lang('description'); ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" placeholder="<?php echo lang('description_placeholder'); ?>" value="<?php echo $vendor_description; ?>">
                                </div>
                            </div>

                            <!-- Account Number -->
                            <div class="form-group">
                                <label><?php echo lang('account_number'); ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="account_number" placeholder="<?php echo lang('account_number_placeholder'); ?>" value="<?php echo $vendor_account_number; ?>">
                                </div>
                            </div>

                            <!-- Account Manager -->
                            <div class="form-group">
                                <label><?php echo lang('account_manager'); ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="contact_name" placeholder="<?php echo lang('account_manager_placeholder'); ?>" value="<?php echo $vendor_contact_name; ?>">
                                </div>
                            </div>

                            <!-- Template Base -->
                            <div class="form-group">
                                <label><?php echo lang('template_base'); ?></label>
                                <select name='vendor_template_id' id='template' value='<?php  ?>'></select>

                                <!-- Template Dropdown -->
                                <?php
                                $sql_vendor_templates = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_template = 1 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($sql_vendor_templates)) {
                                    $vendor_template_id_select = $row['vendor_id'];
                                    $vendor_template_name_select = nullable_htmlentities($row['vendor_name']); ?>
                                    <option <?php if ($vendor_template_id == $vendor_template_id_select) { echo "selected"; } ?> value="<?php echo $vendor_template_id_select; ?>"><?php echo $vendor_template_name_select; ?></option>

                                <?php } ?>
                            </select>

                        </div>
                        <div class="tab-pane fade" id="pills-support<?php echo $vendor_id; ?>">

<label><?php echo lang('support_phone'); ?></label>
<div class="form-row">
    <div class="col-8">
        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                </div>
                <input type="text" class="form-control" name="phone" placeholder="<?php echo lang('phone_number_placeholder'); ?>" value="<?php echo $vendor_phone; ?>">
            </div>
        </div>
    </div>
    <div class="col-4">
        <input type="text" class="form-control" name="extension" placeholder="<?php echo lang('prompts_placeholder'); ?>" value="<?php echo $vendor_extension; ?>">
    </div>
</div>

<div class="form-group">
    <label><?php echo lang('support_hours'); ?></label>
    <div class="input-group">
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
        </div>
        <input type="text" class="form-control" name="hours" placeholder="<?php echo lang('support_hours_placeholder'); ?>" value="<?php echo $vendor_hours; ?>">
    </div>
</div>

<div class="form-group">
    <label><?php echo lang('support_email'); ?></label>
    <div class="input-group">
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
        </div>
        <input type="email" class="form-control" name="email" placeholder="<?php echo lang('support_email_placeholder'); ?>" value="<?php echo $vendor_email; ?>">
    </div>
</div>

<div class="form-group">
    <label><?php echo lang('support_website_url'); ?></label>
    <div class="input-group">
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
        </div>
        <input type="text" class="form-control" name="website" placeholder="<?php echo lang('website_placeholder'); ?>" value="<?php echo $vendor_website; ?>">
    </div>
</div>

<div class="form-group">
    <label><?php echo lang('sla'); ?></label>
    <div class="input-group">
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
        </div>
        <input type="text" class="form-control" name="sla" placeholder="<?php echo lang('sla_placeholder'); ?>" value="<?php echo $vendor_sla; ?>">
    </div>
</div>

<div class="form-group">
    <label><?php echo lang('pin_code'); ?></label>
    <div class="input-group">
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
        </div>
        <input type="text" class="form-control" name="code" placeholder="<?php echo lang('access_code_placeholder'); ?>" value="<?php echo $vendor_code; ?>">
    </div>
</div>

</div>

<div class="tab-pane fade" id="pills-notes<?php echo $vendor_id; ?>">

<div class="form-group">
    <textarea 
        class="form-control"
        rows="12"
        placeholder="<?php echo lang('notes_placeholder'); ?>"
        name="notes"><?php echo $vendor_notes; ?></textarea>
                            </div>

                            <p class="text-muted text-right"><?php echo lang('vendor_id_label'); ?>: <?= $vendor_id ?></p>

                            </div>

                        </div>
                    </div>

                </div>
            <div class="modal-footer bg-white">
                <!-- Save Button -->
                <button type="submit" name="edit_vendor" class="btn btn-primary text-bold">
                <i class="fas fa-check mr-2"></i><?php echo lang('save'); ?>
                </button>

                <!-- Cancel Button -->
                <button type="button" class="btn btn-light" data-dismiss="modal">
                <i class="fa fa-times mr-2"></i><?php echo lang('cancel'); ?>
                </button>
            </div>

            </form>
        </div>
    </div>
</div>
