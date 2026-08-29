<?php

// Default Column Sortby Filter
$sort = "ai_model_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS ai_model_id, ai_model_name, ai_model_prompt, ai_model_use_case, ai_provider_id,
    ai_provider_name FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id
    WHERE ai_model_name LIKE '%$q%' OR ai_provider_name LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="/admin">Admin</a>
    </li>
    <li class="breadcrumb-item">
        <a href="ai_providers.php">AI Providers</a>
    </li>
    <li class="breadcrumb-item active">AI Models</li>
</ol>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-robot me-2"></i>AI Models</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ai/ai_model_add.php"><i class="fas fa-plus me-2"></i>Add Model</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search AI Models">
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
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ai_model_name&order=<?= $disp ?>">
                        Model <?php if ($sort == 'ai_model_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ai_provider_name&order=<?= $disp ?>">
                        Provider <?php if ($sort == 'ai_provider_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ai_model_use_case&order=<?= $disp ?>">
                        Use Case<?php if ($sort == 'ai_model_use_case') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark">Prompt</a>
                </th>
                <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php

            while ($row = mysqli_fetch_assoc($sql)) {
                $provider_id = intval($row['ai_provider_id']);
                $provider_name = escapeHtml($row['ai_provider_name']);
                $model_id = intval($row['ai_model_id']);
                $model_name = escapeHtml($row['ai_model_name']);
                $use_case = escapeHtml($row['ai_model_use_case']);
                $prompt = nl2br(escapeHtml($row['ai_model_prompt']));

                ?>
                <tr>
                    <td class="ps-3">
                        <a class="text-dark text-bold ajax-modal" href="#"
                            data-modal-url="modals/ai/ai_model_edit.php?id=<?= $model_id ?>">
                            <?= $model_name ?>
                        </a>
                    </td>
                    <td><?= $provider_name ?></td>
                    <td><?= $use_case ?></td>
                    <td><?= $prompt ?></td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/ai/ai_model_edit.php?id=<?= $model_id ?>">
                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ai_model=<?= $model_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
