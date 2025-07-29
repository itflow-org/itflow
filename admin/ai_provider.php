<?php

// Default Column Sortby Filter
$sort = "ai_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT * FROM ai_providers ORDER BY $sort $order");

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-robot mr-2"></i>AI Providers</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAIProviderModal"><i class="fas fa-plus mr-2"></i>Add Provider</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ai_provider_name&order=<?php echo $disp; ?>">
                            Provider <?php if ($sort == 'ai_provider_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ai_provider_api_url&order=<?php echo $disp; ?>">
                            URL <?php if ($sort == 'ai_provider_api_url') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ai_provider_api_key&order=<?php echo $disp; ?>">
                            Key <?php if ($sort == 'ai_provider_api_key') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark">Models</a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $provider_id = intval($row['ai_provider_id']);
                    $provider_name = nullable_htmlentities($row['ai_provider_name']);
                    $url = nullable_htmlentities($row['ai_provider_api_url']);
                    $key = nullable_htmlentities($row['ai_provider_api_key']);

                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ai_model_id') AS ai_model_count FROM ai_models WHERE ai_model_ai_provider_id = $provider_id"));
                    $ai_model_count = intval($row['ai_model_count']);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark text-bold" href="#"
                                data-toggle="ajax-modal"
                                data-ajax-url="ajax/ajax_ai_provider_edit.php"
                                data-ajax-id="<?php echo $provider_id; ?>"
                                >
                                <?php echo $provider_name; ?>
                            </a>
                        </td>
                        <td><?php echo $url; ?></td>
                        <td><?php echo $key; ?></td>
                        <td><?php echo $ai_model_count; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#"
                                        data-toggle="ajax-modal"
                                        data-ajax-url="ajax/ajax_ai_provider_edit.php"
                                        data-ajax-id="<?php echo $provider_id; ?>"
                                        >
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ai_provider=<?php echo $provider_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
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
require_once "modals/admin_ai_provider_add_modal.php";
require_once "../includes/footer.php";
