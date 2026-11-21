<?php

/*
 * Edit a document on a page of its own - the editor gets the room a long document needs. The
 * same form as the Edit window had (modals/document/document_form.php), posting to the same
 * edit_document; saving keeps what it said before as a version and goes back to the document.
 *
 * The editor is .tinymce-page (js/app.js): as tall as the window, with its toolbar always in
 * view. Leaving with changes unsaved asks first - js/app.js does that for any editor on a page.
 */

require_once "includes/inc_all_client.php";

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['document_id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_visible, document_content, document_description, document_folder_id,
    document_name, document_archived_at FROM documents WHERE document_client_id = $client_id AND document_id = $document_id LIMIT 1"));

if (!$row || !empty($row['document_archived_at'])) {
    echo "<div class='text-center'><h1 class='text-secondary mt-5'>Nothing to edit here</h1><a class='btn btn-lg btn-secondary mt-3' href='files.php?client_id=$client_id'><i class='fas fa-fw fa-arrow-left me-2'></i>Back to files</a></div>";
    require_once "../includes/footer.php";
    exit();
}

$document_form_mode = 'edit';
$document_name = escapeHtml($row['document_name']);
$document_description = escapeHtml($row['document_description']);
$document_content = escapeHtml($row['document_content']);
$document_folder_id = intval($row['document_folder_id']);
$document_client_visible = intval($row['document_client_visible']);

$folder_tree = folderTree($client_id);
$folder_path = folderPath($folder_tree, isset($folder_tree[$document_folder_id]) ? $document_folder_id : 0);

// Tab title - used raw, the header escapes it
$page_title = 'Editing ' . $row['document_name'];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></li>
    <li class="breadcrumb-item"><a href="files.php?client_id=<?= $client_id ?>">Files</a></li>
    <?php foreach ($folder_path as $crumb) { ?>
        <li class="breadcrumb-item"><a href="files.php?client_id=<?= $client_id ?>&folder_id=<?= $crumb['folder_id'] ?>"><?= escapeHtml($crumb['folder_name']) ?></a></li>
    <?php } ?>
    <li class="breadcrumb-item"><a href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>"><?= $document_name ?></a></li>
    <li class="breadcrumb-item active">Edit</li>
</ol>

<form action="post.php" method="post" autocomplete="off" data-document-page-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title mb-0 me-auto text-truncate"><i class="fas fa-fw fa-edit me-2"></i>Editing <strong><?= $document_name ?></strong></h3>
            <button type="submit" name="edit_document" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Save</button>
            <a class="btn btn-light border" href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>"><i class="fas fa-times me-2"></i>Cancel</a>
        </div>
        <div class="card-body itflow-document-page-form">
            <?php
            // The page's own editor, not the window's
            ob_start();
            require 'modals/document/document_form.php';
            echo str_replace('class="form-control tinymce"', 'class="form-control tinymce-page"', ob_get_clean());
            ?>
        </div>
    </div>
</form>

<script>
    // Saving is not leaving with changes unsaved - the editor is marked clean as the form goes
    document.querySelector('form[data-document-page-form]').addEventListener('submit', function () {
        if (window.tinymce) {
            tinymce.get().forEach(function (editor) {
                editor.save();
                editor.setDirty(false);
            });
        }
    });
</script>

<?php
require_once "../includes/footer.php";
