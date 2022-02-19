<?php

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$_SESSION['records_per_page'];
  $record_to = $_SESSION['records_per_page'];
}else{
  $record_from = 0;
  $record_to = $_SESSION['records_per_page'];
  $p = 1;
}
  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "document_name";
}

if(isset($_GET['o'])){
  if($_GET['o'] == 'ASC'){
    $o = "ASC";
    $disp = "DESC";
  }else{
    $o = "DESC";
    $disp = "ASC";
  }
}else{
  $o = "ASC";
  $disp = "DESC";
}

# Tag from GET
if (isset($_GET['tag'])) {
    $tag = intval($_GET['tag']);
    # Avoid doubling up
    unset($_GET['tag']);
}
else {
    $tag = '';
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

# Currently using two separate queries: one with and one without tags
# If we use a query with tags with no tags set (or even %), then documents appear twice

$sql_no_tag = "SELECT SQL_CALC_FOUND_ROWS * FROM documents
  WHERE document_client_id = $client_id
  AND documents.company_id = $session_company_id
  AND (document_name LIKE '%$q%' OR document_content LIKE '%$q%')
  ORDER BY $sb $o LIMIT $record_from, $record_to";

$sql_with_tag = "SELECT SQL_CALC_FOUND_ROWS * FROM documents
  LEFT JOIN documents_tagged ON documents.document_id = documents_tagged.document_id
  WHERE document_client_id = $client_id
  AND documents.company_id = $session_company_id
  AND (document_name LIKE '%$q%' OR document_content LIKE '%$q%')
  AND documents_tagged.tag_id LIKE '%$tag%'
  ORDER BY $sb $o LIMIT $record_from, $record_to";

if (empty($tag)) {
    $sql = mysqli_query($mysqli, $sql_no_tag);
}
else {
    $sql = mysqli_query($mysqli, $sql_with_tag);
}

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-alt"></i> Documents</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDocumentModal"><i class="fas fa-fw fa-plus"></i> New Document</button>
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#manageTagsModal"><i class="fas fa-fw fa-tags"></i> Tags</button>
    </div>
  </div>
  <div class="card-body">
      <div class="form-group">
      <?php
      # Show client document tags from database, allow user to filter documents using these
      $document_tags = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM document_tags WHERE client_id = $client_id ORDER BY tag_name");
      if (mysqli_num_rows($document_tags) > 0) {
          foreach($document_tags as $document_tag) {
              echo "<a href='?$url_query_strings_sb&tag=$document_tag[tag_id]' class=\"btn btn-outline-primary btn-lg mr-1\">"; echo htmlentities($document_tag['tag_name']); echo "</a>";
          }
      }
      else {
          $document_tags = FALSE;
      }
       ?>
      </div>
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="input-group">
        <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
        <div class="input-group-append">
          <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>

    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th>
              <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=document_name&o=<?php echo $disp; ?>">Name</a>
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
      
          while($row = mysqli_fetch_array($sql)){
            $document_id = $row['document_id'];
            $document_name = $row['document_name'];
            $document_content = $row['document_content'];
            $document_created_at = $row['document_created_at'];
            $document_updated_at = $row['document_updated_at'];

            // Get the tags set for the current document, store them as $document_tags_set
            // There is probably a nicer way to do this, but this works.
            $query_tags_set = mysqli_query($mysqli, "SELECT tag_id FROM documents_tagged WHERE document_id = $document_id");
            $document_tags_set = array();
            if ($query_tags_set){
                foreach($query_tags_set as $query_tag) {
                    array_push($document_tags_set, $query_tag['tag_id']);
                }
            }

          ?>

          <tr>
            <td>
              <a href="#" data-toggle="modal" data-target="#viewDocumentModal<?php echo $document_id; ?>"><?php echo $document_name; ?></a>
            </td>
            <td><?php echo $document_created_at; ?></td>
            <td><?php echo $document_updated_at; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editDocumentModal<?php echo $document_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Document', $document_id"; ?>)">Share</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_document=<?php echo $document_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("client_document_view_modal.php"); ?>      
            </td>
          </tr>

          <?php
          
          include("client_document_edit_modal.php");
          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("share_modal.php"); ?>
<?php include("client_document_add_modal.php"); ?>
<?php include("client_document_tags_modal.php"); ?>