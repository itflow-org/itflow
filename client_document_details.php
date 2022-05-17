<?php 

if(isset($_GET['folder_id'])){
  $folder_id = intval($_GET['folder_id']);
}

if(isset($_GET['document_id'])){
	$document_id = intval($_GET['document_id']);
}


$sql_document = mysqli_query($mysqli,"SELECT * FROM documents LEFT JOIN folders ON document_folder_id = folder_id WHERE document_client_id = $client_id AND document_id = $document_id AND documents.company_id = $session_company_id");

$row = mysqli_fetch_array($sql_document);
$folder_name = $row['folder_name'];

$document_name = $row['document_name'];
$document_content = $row['document_content'];
$document_created_at = $row['document_created_at'];
$document_updated_at = $row['document_updated_at'];
$document_folder_id = $row['document_folder_id'];

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="invoices.php">Home</a>
  </li>
  <li class="breadcrumb-item">
    <a href="clients.php">Clients</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=overview"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=documents">Documents</a>
  </li>
  <?php if($folder_id > 0){ ?>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=documents&folder_id=<?php echo $folder_id; ?>"><?php echo $folder_name; ?></a>
  </li>
  <?php } ?>
  <li class="breadcrumb-item active"><?php echo "$document_name"; ?></li>
</ol>

<div class="row">
	
  <div class="col-md-9">
		<div class="card">
		  <div class="card-header">
		    <h3 class="card-title"><?php echo $document_name; ?></h3>
		    <div class="card-tools">
		      <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#editDocumentModal">Edit</button>
		    </div>
		  </div>
		  <div class="card-body">
        <?php echo $document_content; ?>
      </div>
    </div>
	</div>

	<div class="col-md-3">
    <div class="card bg-light">
      <div class="card-header">
        <h3 class="card-title">Related</h3>
      </div>
      <div class="card-body">
        kljlljhk
        
      </div>
    </div>

	</div>

</div>