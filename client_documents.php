<?php

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$config_records_per_page;
  $record_to = $config_records_per_page;
}else{
  $record_from = 0;
  $record_to = $config_records_per_page;
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

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));
 
$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM documents 
  WHERE documents.client_id = $client_id
  AND documents.company_id = $session_company_id
  AND (document_name LIKE '%$q%' OR document_details LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-alt"></i> Documents</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDocumentModal"><i class="fas fa-fw fa-plus"></i> New Document</button>
    </div>
  </div>
  <div class="card-body">
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
            $document_details = $row['document_details'];
            $document_created_at = $row['document_created_at'];
            $document_updated_at = $row['document_updated_at'];

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
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?delete_document=<?php echo $document_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("view_document_modal.php"); ?>      
            </td>
          </tr>

          <?php
          
          include("edit_document_modal.php");
          }

          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php include("add_document_modal.php"); ?>