<?php

// Default Column Sortby Filter
$sort = "ai_model_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT * FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id ORDER BY $sort $order");

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-robot mr-2"></i>AI Models</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAIModelModal"><i class="fas fa-plus mr-2"></i>Add Model</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ai_model_name&order=<?php echo $disp; ?>">
                            Model <?php if ($sort == 'ai_model_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ai_provider_name&order=<?php echo $disp; ?>">
                            Provider <?php if ($sort == 'ai_provider_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ai_model_use_case&order=<?php echo $disp; ?>">
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

                while ($row = mysqli_fetch_array($sql)) {
                    $provider_id = intval($row['ai_provider_id']);
                    $provider_name = nullable_htmlentities($row['ai_provider_name']);
                    $model_id = intval($row['ai_model_id']);
                    $model_name = nullable_htmlentities($row['ai_model_name']);
                    $use_case = nullable_htmlentities($row['ai_model_use_case']);
                    $prompt = nl2br(nullable_htmlentities($row['ai_model_prompt']));

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark text-bold ajax-modal" href="#"
                                data-modal-url="modals/ai/ai_model_edit.php?id=<?= $model_id ?>">
                                <?php echo $model_name; ?>
                            </a>
                        </td>
                        <td><?php echo $provider_name; ?></td>
                        <td><?php echo $use_case; ?></td>
                        <td><?php echo $prompt; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/ai/ai_model_edit.php?id=<?= $model_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ai_model=<?php echo $model_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                }

                if ($num_rows == 0) {
                    echo "<h3 class='text-secondary mt-3' style='text-align: center'>No Records Here</h3>";
                }

                ?>

                </tbody>
            </table>

        </div>
    </div>
</div>

<?php
require_once "modals/ai/ai_model_add.php";
require_once "../includes/footer.php";
