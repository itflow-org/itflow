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
          <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkFileToDocumentModal">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <ul>
          <?php
          $sql_files = mysqli_query($mysqli, "SELECT * FROM files, document_files
            WHERE document_files.file_id = files.file_id 
            AND document_files.document_id = $document_id
            ORDER BY file_name ASC"
          );
          
          $linked_files = array();

          while ($row = mysqli_fetch_array($sql_files)) {
            $file_id = intval($row['file_id']);
            $file_name = nullable_htmlentities($row['file_name']);

            $linked_files[] = $file_id;

            ?>
            <li>
              <?php echo $file_name; ?> 
              <a href="post.php?unlink_file_from_document&file_id=<?php echo $file_id; ?>&document_id=<?php echo $document_id; ?>">
                <i class="fas fa-fw fa-times text-secondary ml-2"></i>
              </a>
            </li>
            <?php
            }
            ?>
        </ul>
        <h6>
          <i class="fas fa-fw fa-users text-secondary mr-2"></i>Contacts
          <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkContactToDocumentModal">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <ul>
          <?php
          $sql_contacts = mysqli_query($mysqli, "SELECT * FROM contacts, contact_documents
            WHERE contacts.contact_id = contact_documents.contact_id 
            AND contact_documents.document_id = $document_id
            ORDER BY contact_name ASC"
          );
          
          $linked_contacts = array();

          while ($row = mysqli_fetch_array($sql_contacts)) {
            $contact_id = intval($row['contact_id']);
            $contact_name = nullable_htmlentities($row['contact_name']);

            $linked_contacts[] = $contact_id;

            ?>
            <li>
              <?php echo $contact_name; ?> 
              <a href="post.php?unlink_contact_from_document&contact_id=<?php echo $contact_id; ?>&document_id=<?php echo $document_id; ?>">
                <i class="fas fa-fw fa-times text-secondary ml-2"></i>
              </a>
            </li>
            <?php
            }
            ?>
        </ul>
        <h6>
          <i class="fas fa-fw fa-laptop text-secondary mr-2"></i>Assets
          <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkAssetToDocumentModal">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <ul>
          <?php
          $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets, asset_documents
            WHERE assets.asset_id = asset_documents.asset_id 
            AND asset_documents.document_id = $document_id
            ORDER BY asset_name ASC"
          );
          
          $linked_assets = array();

          while ($row = mysqli_fetch_array($sql_assets)) {
            $asset_id = intval($row['asset_id']);
            $asset_name = nullable_htmlentities($row['asset_name']);

            $linked_assets[] = $asset_id;

            ?>
            <li>
              <?php echo $asset_name; ?> 
              <a href="post.php?unlink_asset_from_document&asset_id=<?php echo $asset_id; ?>&document_id=<?php echo $document_id; ?>">
                <i class="fas fa-fw fa-times text-secondary ml-2"></i>
              </a>
            </li>
            <?php
            }
            ?>
        </ul>
        <h6>
          <i class="fas fa-fw fa-cube text-secondary mr-2"></i>Licenses
          <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkSoftwareToDocumentModal">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <ul>
          <?php
          $sql_software = mysqli_query($mysqli, "SELECT * FROM software, software_documents
            WHERE software.software_id = software_documents.software_id 
            AND software_documents.document_id = $document_id
            ORDER BY software_name ASC"
          );
          
          $linked_software = array();

          while ($row = mysqli_fetch_array($sql_software)) {
            $software_id = intval($row['software_id']);
            $software_name = nullable_htmlentities($row['software_name']);

            $linked_software[] = $software_id;

            ?>
            <li>
              <?php echo $software_name; ?> 
              <a href="post.php?unlink_software_from_document&software_id=<?php echo $software_id; ?>&document_id=<?php echo $document_id; ?>">
                <i class="fas fa-fw fa-times text-secondary ml-2"></i>
              </a>
            </li>
            <?php
            }
            ?>
        </ul>
        <h6>
          <i class="fas fa-fw fa-building text-secondary mr-2"></i>Vendors
          <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkVendorToDocumentModal">
            <i class="fas fa-fw fa-plus"></i>
          </button>
        </h6>
        <ul>
          <?php
          $sql_vendors = mysqli_query($mysqli, "SELECT * FROM vendors, vendor_documents
            WHERE vendors.vendor_id = vendor_documents.vendor_id 
            AND vendor_documents.document_id = $document_id
            ORDER BY vendor_name ASC"
          );
          
          $associated_vendors = array();

          while ($row = mysqli_fetch_array($sql_vendors)) {
            $vendor_id = intval($row['vendor_id']);
            $vendor_name = nullable_htmlentities($row['vendor_name']);

            $associated_vendors[] = $vendor_id;

            ?>
            <li>
              <?php echo $vendor_name; ?> 
              <a href="post.php?unlink_vendor_from_document&vendor_id=<?php echo $vendor_id; ?>&document_id=<?php echo $document_id; ?>">
                <i class="fas fa-fw fa-times text-secondary ml-2"></i>
              </a>
            </li>
            <?php
            }
            ?>
        </ul>
      </div>
    </div>

	</div>

</div>

<?php

require_once("client_document_edit_modal.php");
require_once("client_document_link_file_modal.php");
require_once("client_document_link_contact_modal.php");
require_once("client_document_link_asset_modal.php");
require_once("client_document_link_software_modal.php");
require_once("client_document_link_vendor_modal.php");
require_once("share_modal.php");
require_once("footer.php");

?>
