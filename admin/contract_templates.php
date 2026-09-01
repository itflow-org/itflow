<?php

// Default Column Sort by Filter
$sort = "contract_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// Search query
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM contract_templates
    WHERE contract_template_name LIKE '%$q%' OR contract_template_type LIKE '%$q%'
        OR contract_template_description LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-contract me-2"></i>Contract Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contract_template/contract_template_add.php" data-modal-size="lg">
                <i class="fas fa-plus me-2"></i>New Template
            </button>
        </div>
    </div>
    <div class="card-header py-3">

        <form autocomplete="off">
            <div class="input-group">
                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search templates">
                    <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
            </div>
        </form>
    </div>

    <div class="table-responsive-sm">
        <table class="table table-striped table-borderless table-hover mb-0">
            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th class="ps-3">
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_name&order=<?= $disp ?>">
                            Template Name <?php if ($sort == 'contract_template_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_type&order=<?= $disp ?>">
                            Type <?php if ($sort == 'contract_template_type') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_renewal_frequency&order=<?= $disp ?>">
                            Update Frequency <?php if ($sort == 'contract_template_renewal_frequency') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>SLA (L/M/H Response)</th>
                    <th>SLA (L/M/H Resolution)</th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_rate_standard&order=<?= $disp ?>">
                            Hourly Rate <?php if ($sort == 'contract_template_rate_standard') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_rate_after_hours&order=<?= $disp ?>">
                            After Hours Rate <?php if ($sort == 'contract_template_rate_after_hours') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_support_hours&order=<?= $disp ?>">
                            Support Hours <?php if ($sort == 'contract_template_support_hours') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_net_terms&order=<?= $disp ?>">
                            Net Terms <?php if ($sort == 'contract_template_net_terms') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_created_at&order=<?= $disp ?>">
                            Created <?php if ($sort == 'contract_template_created_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=contract_template_updated_at&order=<?= $disp ?>">
                            Updated <?php if ($sort == 'contract_template_updated_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $id = intval($row['contract_template_id']);
                        $name = escapeHtml($row['contract_template_name']);
                        $type = escapeHtml($row['contract_template_type']);
                        $freq = escapeHtml($row['contract_template_renewal_frequency']);
                        $sla_low_resp = escapeHtml($row['contract_template_sla_low_response_time']);
                        $sla_med_resp = escapeHtml($row['contract_template_sla_medium_response_time']);
                        $sla_high_resp = escapeHtml($row['contract_template_sla_high_response_time']);
                        $sla_low_res = escapeHtml($row['contract_template_sla_low_resolution_time']);
                        $sla_med_res = escapeHtml($row['contract_template_sla_medium_resolution_time']);
                        $sla_high_res = escapeHtml($row['contract_template_sla_high_resolution_time']);
                        $hourly_rate = escapeHtml($row['contract_template_rate_standard']);
                        $after_hours = escapeHtml($row['contract_template_rate_after_hours']);
                        $support_hours = escapeHtml($row['contract_template_support_hours']);
                        $net_terms = escapeHtml($row['contract_template_net_terms']);
                        $created = escapeHtml($row['contract_template_created_at']);
                        $updated = escapeHtml($row['contract_template_updated_at']);
                ?>
                <tr>
                    <td class="ps-3">
                        <a class="text-bold ajax-modal" href="#"
                            data-modal-size="xl"
                            data-modal-url="modals/contract_template/contract_template_edit.php?id=<?= $id ?>">
                            <i class="fas fa-fw fa-file-alt text-dark"></i> <?= $name ?>
                        </a>
                        <div class="mt-1 text-secondary"><?= escapeHtml($row['contract_template_description']) ?></div>
                    </td>
                    <td><?= $type ?></td>
                    <td><?= $freq ?></td>
                    <td><?= "$sla_low_resp / $sla_med_resp / $sla_high_resp" ?></td>
                    <td><?= "$sla_low_res / $sla_med_res / $sla_high_res" ?></td>
                    <td><?= $hourly_rate ?></td>
                    <td><?= $after_hours ?></td>
                    <td><?= $support_hours ?></td>
                    <td><?= $net_terms ?></td>
                    <td><?= $created ?></td>
                    <td><?= $updated ?></td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-size="xl"
                                    data-modal-url="modals/contract_template/contract_template_edit.php?id=<?= $id ?>">
                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold" href="post.php?delete_contract_template=<?= $id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php require_once "../includes/footer.php"; ?>
