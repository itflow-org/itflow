<?php

    // Default Column Sort by Filter
    $sort = "document_name";
    $order = "ASC";

    require_once "includes/inc_all_admin.php";

    // Search query SQL snippet
    if (!empty($q)) {
        $query_snippet = "AND (MATCH(document_content_raw) AGAINST ('$q') OR document_name LIKE '%$q%')";
    } else {
        $query_snippet = ""; // empty
    }

    // Rebuild URL
    $url_query_strings_sort = http_build_query($get_copy);

    $sql = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS * FROM documents
        LEFT JOIN users ON document_created_by = user_id
        WHERE document_template = 1
        $query_snippet
        ORDER BY $sort $order LIMIT $record_from, $record_to"
    );

    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file mr-2"></i>Document Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDocumentTemplateModal">
                <i class="fas fa-plus mr-2"></i>New Template
            </button>
        </div>
    </div>
    <div class="card-body">

        <form autocomplete="off">
            <div class="input-group">
                <input type="search" class="form-control " name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search templates">
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
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_name&order=<?php echo $disp; ?>">
                                Template Name <?php if ($sort == 'document_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_created_at&order=<?php echo $disp; ?>">
                                Created <?php if ($sort == 'document_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_updated_at&order=<?php echo $disp; ?>">
                                Updated <?php if ($sort == 'document_updated_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $document_id = intval($row['document_id']);
                            $document_name = nullable_htmlentities($row['document_name']);
                            $document_description = nullable_htmlentities($row['document_description']);
                            $document_content = nullable_htmlentities($row['document_content']);
                            $document_created_by_name = nullable_htmlentities($row['user_name']);
                            $document_created_at = nullable_htmlentities($row['document_created_at']);
                            $document_updated_at = nullable_htmlentities($row['document_updated_at']);
                            $document_folder_id = intval($row['document_folder_id']);

                    ?>

                    <tr>
                        <td>
                            <a class="text-bold" href="admin_document_template_details.php?document_id=<?php echo $document_id; ?>"><i class="fas fa-fw fa-file-alt text-dark"></i> <?php echo $document_name; ?></a>
                            <div class="mt-1 text-secondary"><?php echo $document_description; ?></div>
                        </td>
                        <td>
                            <?php echo $document_created_at; ?>
                            <div class="text-secondary"><?php echo $document_created_by_name; ?></div>
                        </td>
                        <td><?php echo $document_updated_at; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editDocumentTemplateModal<?php echo $document_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_document=<?php echo $document_id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                            require "modals/admin_document_template_edit_modal.php";

                        }

                    ?>

                </tbody>
            </table>
            <br>
        </div>
        <?php require_once "includes/filter_footer.php"; ?>
    </div>
</div>

<?php require_once "modals/admin_document_template_add_modal.php"; ?>
<?php require_once "includes/footer.php"; ?>

<script>
$(document).ready(function(){

    $('#generateAIContent').on('click', function(){
        var prompt = $('#aiPrompt').val().trim();
        if(prompt === '') {
            alert('Please enter a prompt.');
            return;
        }

        $('#generateAIContent').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

        $.ajax({
            url: 'post.php?ai_create_document_template', // The PHP script that calls the OpenAI API
            method: 'POST',
            data: { prompt: prompt },
            dataType: 'html',
            success: function(response) {
                // Assuming you have exactly one TinyMCE instance on the page
                // and it's targeting the .tinymce textarea:
                tinymce.activeEditor.setContent(response);
            },
            error: function() {
                alert('Error generating content. Please try again.');
            },
            complete: function() {
                $('#generateAIContent').prop('disabled', false).html('<i class="fa fa-fw fa-magic mr-1"></i>Generate with AI');
            }
        });
    });
});
</script>
