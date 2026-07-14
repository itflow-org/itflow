<?php

    // Default Column Sort by Filter
    $sort = "document_template_name";
    $order = "ASC";

    require_once "includes/inc_all_admin.php";

    $sql = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS * FROM document_templates
        LEFT JOIN users ON document_template_created_by = user_id
        WHERE user_name LIKE '%$q%' OR document_template_name LIKE '%$q%'
        ORDER BY $sort $order LIMIT $record_from, $record_to"
    );

    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-alt mr-2"></i>Document Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/document_template/document_template_add.php" data-modal-size="xl">
                <i class="fas fa-plus mr-2"></i>New Template
            </button>
        </div>
    </div>
    <div class="card-body">

        <form autocomplete="off">
            <div class="input-group">
                <input type="search" class="form-control " name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search templates">
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
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_template_name&order=<?php echo $disp; ?>">
                                Template Name <?php if ($sort == 'document_template_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_template_created_at&order=<?php echo $disp; ?>">
                                Created <?php if ($sort == 'document_template_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_template_updated_at&order=<?php echo $disp; ?>">
                                Updated <?php if ($sort == 'document_template_updated_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                        while ($row = mysqli_fetch_assoc($sql)) {
                            $document_template_id = intval($row['document_template_id']);
                            $document_template_name = escapeHtml($row['document_template_name']);
                            $document_template_description = escapeHtml($row['document_template_description']);
                            $document_template_content = escapeHtml($row['document_template_content']);
                            $document_template_created_by_name = escapeHtml($row['user_name']);
                            $document_template_created_at = escapeHtml($row['document_template_created_at']);
                            $document_template_updated_at = escapeHtml($row['document_template_updated_at']) ?: '-';

                    ?>

                    <tr>
                        <td>
                            <a class="text-dark ajax-modal" href="#"
                                data-modal-size="xl"
                                data-modal-url="modals/document_template/document_template_edit.php?id=<?= $document_template_id ?>">
                                <div class="media">
                                    <i class="fas fa-fw fa-2x fa-file-alt mr-2"></i>
                                    <div class="media-body">
                                        <div><?= $document_template_name ?></div>
                                        <div><small class="text-secondary"><?= $document_template_description ?></small></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td>
                            <?php echo $document_template_created_at; ?>
                            <div class="text-secondary"><?php echo $document_template_created_by_name; ?></div>
                        </td>
                        <td><?php echo $document_template_updated_at; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="document_template_details.php?document_template_id=<?= $document_template_id ?>">
                                        <i class="fas fa-fw fa-eye mr-2"></i>View
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-size="xl"
                                        data-modal-url="modals/document_template/document_template_edit.php?id=<?= $document_template_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_document_template=<?php echo $document_template_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                        }

                    ?>

                </tbody>
            </table>
            <br>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>
</div>

<?php require_once "../includes/footer.php";
