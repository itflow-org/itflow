<?php 

require_once("inc_all_client.php");

// Sort by
if (!empty($_GET['sb'])) {
  $sb = sanitizeInput($_GET['sb']);
} else {
  $sb = "document_name";
}

// Search query SQL snippet
if (!empty($q)) {
  $query_snippet = "AND (MATCH(document_content_raw) AGAINST ('$q') OR document_name LIKE '%$q%')";
}else{
  $query_snippet = ""; // empty
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM documents
    WHERE document_template = 1
    $query_snippet
    ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-file mr-2"></i>Document Templates</h3>
    <button type="button" class="btn btn-dark dropdown-toggle ml-1" data-toggle="dropdown"></button>
    <div class="dropdown-menu">
      <a class="dropdown-item text-dark" href="client_documents.php?client_id=<?php echo $client_id; ?>">Documents</a>
    </div>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDocumentTemplateModal">
        <i class="fas fa-plus mr-2"></i>New Template
      </button>
    </div>
  </div>
  <div class="card-body">

    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <div class="input-group">
        <input type="search" class="form-control " name="q" value="<?php if (isset($q)) { echo stripslashes(htmlentities($q)); } ?>" placeholder="Search templates">
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
              <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=document_name&o=<?php echo $disp; ?>">Template Name</a>
            </th>
            <th>
              <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=document_created_at&o=<?php echo $disp; ?>">Created</a>
            </th>
            <th>
              <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=document_updated_at&o=<?php echo $disp; ?>">Updated</a>
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
            $document_name = htmlentities($row['document_name']);
            $document_content = htmlentities($row['document_content']);
            $document_created_at = htmlentities($row['document_created_at']);
            $document_updated_at = htmlentities($row['document_updated_at']);
            $document_folder_id = intval($row['document_folder_id']);

          ?>

          <tr>
            <td>
              <a href="client_document_template_details.php?client_id=<?php echo $client_id; ?>&document_id=<?php echo $document_id; ?>"><i class="fas fa-fw fa-file-alt"></i> <?php echo $document_name; ?></a>
            </td>
            <td><?php echo $document_created_at; ?></td>
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
                  <?php if ($session_user_role == 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger text-bold" href="post.php?delete_document=<?php echo $document_id; ?>">
                      <i class="fas fa-fw fa-trash mr-2"></i>Delete
                    </a>
                  <?php } ?>
                </div>
              </div>
            </td>
          </tr>

          <?php

          include("client_document_template_edit_modal.php");
          }

          ?>

        </tbody>
      </table>
      <br>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>


<?php include("client_document_template_add_modal.php"); ?>

<?php include("footer.php"); ?>
