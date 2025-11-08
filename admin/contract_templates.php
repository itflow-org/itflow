<?php

// Default Column Sort by Filter
$sort = "contract_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// Search query
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM contract_templates
    WHERE contract_template_name LIKE '%$q%' OR contract_template_type LIKE '%$q%' OR contract_template_name LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-contract mr-2"></i>Contract Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contract_template/contract_template_add.php" data-modal-size="lg">
                <i class="fas fa-plus mr-2"></i>New Template
            </button>
        </div>
    </div>
    <div class="card-body">

        <form autocomplete="off">
            <div class="input-group">
                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search templates">
                <div class="input-group-append">
                    <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
                </div>
            </div>
        </form>
        <hr>

        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>Template Name</th>
                        <th>Type</th>
                        <th>Update Frequency</th>
                        <th>SLA (L/M/H Response)</th>
                        <th>SLA (L/M/H Resolution)</th>
                        <th>Hourly Rate</th>
                        <th>After Hours Rate</th>
                        <th>Support Hours</th>
                        <th>Net Terms</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        while ($row = mysqli_fetch_array($sql)) {
                            $id = intval($row['contract_template_id']);
                            $name = nullable_htmlentities($row['contract_template_name']);
                            $type = nullable_htmlentities($row['contract_template_type']);
                            $freq = nullable_htmlentities($row['contract_template_update_frequency']);
                            $sla_low_resp = nullable_htmlentities($row['sla_low_response_time']);
                            $sla_med_resp = nullable_htmlentities($row['sla_medium_response_time']);
                            $sla_high_resp = nullable_htmlentities($row['sla_high_response_time']);
                            $sla_low_res = nullable_htmlentities($row['sla_low_resolution_time']);
                            $sla_med_res = nullable_htmlentities($row['sla_medium_resolution_time']);
                            $sla_high_res = nullable_htmlentities($row['sla_high_resolution_time']);
                            $hourly_rate = nullable_htmlentities($row['contract_template_hourly_rate']);
                            $after_hours = nullable_htmlentities($row['contract_template_after_hours_hourly_rate']);
                            $support_hours = nullable_htmlentities($row['contract_template_support_hours']);
                            $net_terms = nullable_htmlentities($row['contract_template_net_terms']);
                            $created = nullable_htmlentities($row['contract_template_created_at']);
                            $updated = nullable_htmlentities($row['contract_template_updated_at']);
                    ?>
                    <tr>
                        <td>
                            <a class="text-bold" href="contract_template_details.php?contract_template_id=<?php echo $id; ?>">
                                <i class="fas fa-fw fa-file-alt text-dark"></i> <?php echo $name; ?>
                            </a>
                            <div class="mt-1 text-secondary"><?php echo nullable_htmlentities($row['contract_template_description']); ?></div>
                        </td>
                        <td><?php echo $type; ?></td>
                        <td><?php echo $freq; ?></td>
                        <td><?php echo "$sla_low_resp / $sla_med_resp / $sla_high_resp"; ?></td>
                        <td><?php echo "$sla_low_res / $sla_med_res / $sla_high_res"; ?></td>
                        <td><?php echo $hourly_rate; ?></td>
                        <td><?php echo $after_hours; ?></td>
                        <td><?php echo $support_hours; ?></td>
                        <td><?php echo $net_terms; ?></td>
                        <td><?php echo $created; ?></td>
                        <td><?php echo $updated; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-size="xl"
                                        data-modal-url="modals/contract_template/contract_template_edit.php?id=<?= $id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_contract_template=<?php echo $id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <br>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
