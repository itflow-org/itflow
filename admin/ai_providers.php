<?php

// Default Column Sortby Filter
$sort = "ai_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS ai_provider_api_key, ai_provider_api_url, ai_provider_id, ai_provider_name FROM ai_providers
    WHERE ai_provider_name LIKE '%$q%' OR ai_provider_api_url LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-robot me-2"></i>AI Providers</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ai/ai_provider_add.php"><i class="fas fa-plus me-2"></i>Add Provider</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search AI Providers">
                        <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive-sm">
        <table class="table table-striped table-borderless table-hover mb-0">
            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3">
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ai_provider_name&order=<?= $disp ?>">
                        Provider <?php if ($sort == 'ai_provider_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ai_provider_api_url&order=<?= $disp ?>">
                        URL <?php if ($sort == 'ai_provider_api_url') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ai_provider_api_key&order=<?= $disp ?>">
                        Key <?php if ($sort == 'ai_provider_api_key') { echo $order_icon; } ?>
                    </a>
                </th>
                <th class="text-center">
                    <a class="text-dark">Models</a>
                </th>
                <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php

            while ($row = mysqli_fetch_assoc($sql)) {
                $provider_id = intval($row['ai_provider_id']);
                $provider_name = escapeHtml($row['ai_provider_name']);
                $url = escapeHtml($row['ai_provider_api_url']);
                $key = escapeHtml($row['ai_provider_api_key']);

                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ai_model_id') AS ai_model_count FROM ai_models WHERE ai_model_ai_provider_id = $provider_id"));
                $ai_model_count = intval($row['ai_model_count']);

                ?>
                <tr>
                    <td class="ps-3">
                        <a class="text-dark text-bold ajax-modal" href="#"
                            data-modal-url="modals/ai/ai_provider_edit.php?id=<?= $provider_id ?>">
                            <?= $provider_name ?>
                        </a>
                    </td>
                    <td><?= $url ?></td>
                    <td><?= $key ?></td>
                    <td class="text-center">
                        <a class="badge bg-dark rounded-pill p-2" href="ai_models.php"><?= $ai_model_count ?></a>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/ai/ai_provider_edit.php?id=<?= $provider_id ?>">
                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ai_provider=<?= $provider_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
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
    </div>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
