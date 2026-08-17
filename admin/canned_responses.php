<?php

// Default Column Sortby Filter
$sort = "canned_response_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

/*
 * A canned response either belongs to one ticket category or, with category id 0, offers
 * itself on every ticket. The LEFT JOIN is what puts the category name on the row; a
 * response whose category was deleted since falls back to the general case below.
 */
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS canned_response_id, canned_response_name, canned_response_body,
            canned_response_category_id, category_name
     FROM canned_responses
     LEFT JOIN categories ON canned_response_category_id = category_id AND category_type = 'Ticket'
     WHERE (canned_response_name LIKE '%$q%' OR canned_response_body LIKE '%$q%')
     AND canned_response_archived_at IS NULL
     ORDER BY $sort $order
     LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-comment-dots me-2"></i>Canned Responses</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/canned_response/canned_response_add.php" data-modal-size="lg"><i class="fas fa-plus me-2"></i>New Canned Response</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">

                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Canned Responses">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                    </div>
                </div>

                <div class="col-md-8">
                </div>

            </div>
        </form>
    </div>
    <div class="table-responsive-sm">
        <table class="table table-striped table-borderless table-hover mb-0">
            <thead class="text-dark <?php if($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th>
                    <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=canned_response_name&order=<?= $disp ?>">
                        Response <?php if ($sort == 'canned_response_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">
                        Ticket Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php

            while($row = mysqli_fetch_assoc($sql)){
                $canned_response_id = intval($row['canned_response_id']);
                $canned_response_name = escapeHtml($row['canned_response_name']);
                $canned_response_category_id = intval($row['canned_response_category_id']);
                $canned_response_category_name = escapeHtml($row['category_name']);

                // The body is HTML from TinyMCE. This is a preview, not a render - tags
                // are stripped rather than escaped so the column reads as the sentence
                // the agent will actually be inserting
                $canned_response_preview = escapeHtml(mb_strimwidth(trim(html_entity_decode(strip_tags($row['canned_response_body']), ENT_QUOTES, 'UTF-8')), 0, 120, '...'));

                ?>
                <tr>
                    <td>
                        <div class="d-flex">
                            <i class="fa fa-fw fa-2x fa-comment-dots me-3"></i>
                            <div class="flex-grow-1">
                                <div>
                                    <a class="ajax-modal" href="#" data-modal-url="modals/canned_response/canned_response_edit.php?id=<?= $canned_response_id ?>" data-modal-size="lg">
                                        <?= $canned_response_name ?>
                                    </a>
                                </div>
                                <div><small class="text-secondary"><?= $canned_response_preview ?></small></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($canned_response_category_id && $canned_response_category_name) { ?>
                            <span class="badge bg-secondary"><?= $canned_response_category_name ?></span>
                        <?php } else { ?>
                            <span class="badge bg-dark">All ticket categories</span>
                        <?php } ?>
                    </td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/canned_response/canned_response_edit.php?id=<?= $canned_response_id ?>" data-modal-size="lg">
                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                </a>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
    <?php require_once "../includes/filter_footer.php";
 ?>
</div>

<?php
require_once "../includes/footer.php";
