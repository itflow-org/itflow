<?php

require_once("inc_all_client.php");

//Initialize the HTML Purifier to prevent XSS
require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['document_id'])) {
	$document_id = intval($_GET['document_id']);
}

$folder_location = 0;

$sql_document = mysqli_query($mysqli, "SELECT * FROM documents LEFT JOIN folders ON document_folder_id = folder_id WHERE document_client_id = $client_id AND document_id = $document_id");

$row = mysqli_fetch_array($sql_document);

$folder_name = nullable_htmlentities($row['folder_name']);
$document_name = nullable_htmlentities($row['document_name']);
$document_content = $purifier->purify($row['document_content']);
$document_created_at = nullable_htmlentities($row['document_created_at']);
$document_updated_at = nullable_htmlentities($row['document_updated_at']);
$document_folder_id = intval($row['document_folder_id']);

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item">
    <a href="client_documents.php?client_id=<?php echo $client_id; ?>">Documents</a>
  </li>
  <?php if ($document_folder_id > 0) { ?>
  <li class="breadcrumb-item">
    <a href="client_documents.php?client_id=<?php echo $client_id; ?>&folder_id=<?php echo $document_folder_id; ?>"><i class="fas fa-fw fa-folder-open mr-2"></i><?php echo $folder_name; ?></a>
  </li>
  <?php } ?>
  <li class="breadcrumb-item active"><i class="fas fa-file"></i> <?php echo $document_name; ?></li>
</ol>

<div class="row">

  <div class="col-md-9">
    <div class="tinymcePreview"><?php echo $document_content; ?></div>
  </div>

	<div class="col-md-3 d-print-none">
    <div class="card bg-light">
      <div class="card-body">
        <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#editDocumentModal<?php echo $document_id; ?>">
          <i class="fas fa-fw fa-edit mr-2"></i>Edit
        </button>
        <button type="button" class="btn btn-secondary btn-block" data-toggle="modal" data-target="#shareModal"
          onclick="populateShareModal(<?php echo "$client_id, 'Document', $document_id"; ?>)">
          <i class="fas fa-fw fa-share mr-2"></i>Share
        </button>
        <button type="button" class="btn btn-secondary btn-block" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        <hr>
        <h5>Related</h5>
        <h6>
          <i class="fas fa-fw fa-paperclip text-secondary mr-2"></i>Files
          <button type="button" class="btn btn-link btn-sm">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <h6>
          <i class="fas fa-fw fa-key text-secondary mr-2"></i>Passwords
          <button type="button" class="btn btn-link btn-sm">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <h6>
          <i class="fas fa-fw fa-users text-secondary mr-2"></i>Contacts
          <button type="button" class="btn btn-link btn-sm">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <h6>
          <i class="fas fa-fw fa-laptop text-secondary mr-2"></i>Assets
          <button type="button" class="btn btn-link btn-sm">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <h6>
          <i class="fas fa-fw fa-cube text-secondary mr-2"></i>Software
          <button type="button" class="btn btn-link btn-sm">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <h6>
          <i class="fas fa-fw fa-building text-secondary mr-2"></i>Vendors
          <button type="button" class="btn btn-link btn-sm">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>

      </div>
    </div>

	</div>

</div>

<?php

require_once("client_document_edit_modal.php");
require_once("share_modal.php");
require_once("footer.php");

?>
