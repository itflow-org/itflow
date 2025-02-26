<?php

require_once "includes/inc_all_client.php";


//Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['document_id'])) {
    $document_id = intval($_GET['document_id']);
}

$folder_location = 0;

$sql_document = mysqli_query($mysqli, "SELECT * FROM documents 
    LEFT JOIN folders ON document_folder_id = folder_id
    LEFT JOIN users ON document_created_by = user_id
    WHERE document_client_id = $client_id AND document_id = $document_id"
);

$row = mysqli_fetch_array($sql_document);

$folder_name = nullable_htmlentities($row['folder_name']);
$document_name = nullable_htmlentities($row['document_name']);
$document_description = nullable_htmlentities($row['document_description']);
$document_content = $purifier->purify($row['document_content']);
$document_created_by_id = intval($row['document_created_by']);
$document_created_by_name = nullable_htmlentities($row['user_name']);
$document_created_at = nullable_htmlentities($row['document_created_at']);
$document_updated_at = nullable_htmlentities($row['document_updated_at']);
$document_archived_at = nullable_htmlentities($row['document_archived_at']);
$document_folder_id = intval($row['document_folder_id']);
$document_parent = intval($row['document_parent']);
$document_client_visible = intval($row['document_client_visible']);

// Override Tab Title // No Sanitizing needed as this var will opnly be used in the tab title
$page_title = $row['document_name'];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
    </li>
    <li class="breadcrumb-item">
        <a href="client_documents.php?client_id=<?php echo $client_id; ?>">Documents</a>
    </li>
    <?php
    // Build the full folder path
    $folder_id = $document_folder_id;
    $folder_path = array();

    while ($folder_id > 0) {
        $sql_folder = mysqli_query($mysqli, "SELECT folder_name, parent_folder FROM folders WHERE folder_id = $folder_id");
        if ($row_folder = mysqli_fetch_assoc($sql_folder)) {
            $folder_name = nullable_htmlentities($row_folder['folder_name']);
            $parent_folder = intval($row_folder['parent_folder']);

            // Prepend the folder to the beginning of the array
            array_unshift($folder_path, array('folder_id' => $folder_id, 'folder_name' => $folder_name));

            // Move up to the parent folder
            $folder_id = $parent_folder;
        } else {
            // If the folder is not found, break the loop
            break;
        }
    }

    // Output breadcrumb items for each folder in the path
    foreach ($folder_path as $folder) {
        $bread_crumb_folder_id = $folder['folder_id']; // Sanitized before put in array
        $bread_crumb_folder_name = $folder['folder_name']; // Sanitized before put in array
        ?>
        <li class="breadcrumb-item">
            <a href="client_documents.php?client_id=<?php echo $client_id; ?>&folder_id=<?php echo $bread_crumb_folder_id; ?>">
                <i class="fas fa-fw fa-folder-open mr-2"></i><?php echo $bread_crumb_folder_name; ?>
            </a>
        </li>
        <?php
    }
    ?>
    <li class="breadcrumb-item active">
        <i class="fas fa-file"></i> <?php echo $document_name; ?> 
        <?php if (!empty($document_archived_at)) { 
            echo "<span class='text-danger ml-2'>(ARCHIVED on $document_archived_at)</span>"; 
        } ?>
    </li>
</ol>

<div class="row">

    <div class="col-md-9">
        <div class="card">
            <div class="card-header bg-dark">

                <h3><?php echo $document_name; ?> <?php if (!empty($document_description)) { ?><span class="h6 text-muted">(<?php echo $document_description; ?>)</span><?php } ?></h3>

                <div class="row">
                    <div class="col"><strong>Date:</strong> <?php echo date('Y-m-d', strtotime($document_created_at)); ?></div>
                    <?php if(!empty($document_created_by_name)){ ?>
                    <div class="col"><strong>Prepared By:</strong> <?php echo $document_created_by_name; ?></div>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body prettyContent">
                <?php echo $document_content; ?>
                <hr>
                <h4>Documentation Revision History</h4>

                <table class="table table-sm">
                    <thead class="thead-light">
                        <th>Revision</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Author</th>
                    </thead>
                    <tbody>
                        <?php
                        $sql_document_revisions = mysqli_query($mysqli, "SELECT * FROM documents
                            LEFT JOIN users ON document_updated_by = user_id
                            WHERE document_parent = $document_parent
                            ORDER BY document_created_at ASC"
                        );

                        $revision_count = 1; // Initialize the revision counter

                        while ($row = mysqli_fetch_array($sql_document_revisions)) {
                            $revision_document_id = intval($row['document_id']);
                            $revision_document_name = nullable_htmlentities($row['document_name']);
                            $revision_document_description = nullable_htmlentities($row['document_description']);
                            if ($revision_document_description ) {
                                $revision_document_description_display = $revision_document_description;
                            } else {
                                $revision_document_description_display = "-";
                            }
                            $revision_document_author = nullable_htmlentities($row['user_name']);
                            if (empty($revision_document_author)) {
                                $revision_document_author = $document_created_by_name;
                            }
                            $revision_document_created_date = date('Y-m-d', strtotime($row['document_created_at']));

                        ?>
                        <tr>
                            <td><?php echo "$revision_count.0"; ?></td>
                            <td><?php echo $revision_document_created_date; ?></td>
                            <td><?php echo $revision_document_description_display; ?></td>
                            <td><?php echo $revision_document_author; ?></td>
                        </tr>
                        <?php 
                        $revision_count++; // Increment the counter
                        } 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-3 d-print-none">
        <div class="row">
            <div class="col-12 mb-3">
                <button type="button" class="btn btn-primary mr-2"
                    data-toggle="ajax-modal"
                    data-modal-size="lg"
                    data-ajax-url="ajax/ajax_document_edit.php"
                    data-ajax-id="<?php echo $document_id; ?>"
                    >
                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                </button>
                <button type="button" class="btn btn-secondary mr-2" data-toggle="modal" data-target="#shareModal"
                    onclick="populateShareModal(<?php echo "$client_id, 'Document', $document_id"; ?>)">
                    <i class="fas fa-fw fa-share mr-2"></i>Share
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
            </div>
        </div>
        <div class="card card-body bg-light">
            <h5 class="mb-3"><i class="fas fa-tags mr-2"></i>Related Items</h5>
            <h6>
                <i class="fas fa-fw fa-paperclip text-secondary mr-2"></i>Files
                <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkFileToDocumentModal">
                    <i class="fas fa-fw fa-plus"></i>
                </button>
            </h6>
            <?php
            $sql_files = mysqli_query($mysqli, "SELECT * FROM files, document_files
                WHERE document_files.file_id = files.file_id 
                AND document_files.document_id = $document_id
                ORDER BY file_name ASC"
            );

            $linked_files = array();

            while ($row = mysqli_fetch_array($sql_files)) {
                $file_id = intval($row['file_id']);
                $folder_id = intval($row['file_folder_id']);
                $file_name = nullable_htmlentities($row['file_name']);

                $linked_files[] = $file_id;

                ?>
                <div class="ml-2">
                    <a href="client_files.php?client_id=<?php echo $client_id; ?>&folder_id=<?php echo $folder_id; ?>&q=<?php echo $file_name; ?>" target="_blank"><?php echo $file_name; ?></a>
                    <a class="confirm-link" href="post.php?unlink_file_from_document&file_id=<?php echo $file_id; ?>&document_id=<?php echo $document_id; ?>">
                        <i class="fas fa-fw fa-trash-alt text-secondary float-right"></i>
                    </a>
                </div>
                <?php
                }
                ?>
            <h6>
                <i class="fas fa-fw fa-users text-secondary mt-3 mr-2"></i>Contacts
                <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkContactToDocumentModal">
                    <i class="fas fa-fw fa-plus"></i>
                </button>
            </h6>
            <?php
            $sql_contacts = mysqli_query($mysqli, "SELECT contacts.contact_id, contact_name FROM contacts, contact_documents
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
                <div class="ml-2">
                    <a href="#"
                        data-toggle="ajax-modal"
                        data-modal-size="lg"
                        data-ajax-url="ajax/ajax_contact_details.php"
                        data-ajax-id="<?php echo $contact_id; ?>">
                        <?php echo $contact_name; ?></a>
                    <a class="confirm-link float-right" href="post.php?unlink_contact_from_document&contact_id=<?php echo $contact_id; ?>&document_id=<?php echo $document_id; ?>">
                        <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                    </a>
                </div>
                <?php
                }
                ?>
            <h6>
                <i class="fas fa-fw fa-laptop text-secondary mr-2 mt-3"></i>Assets
                <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkAssetToDocumentModal">
                    <i class="fas fa-fw fa-plus"></i>
                </button>
            </h6>
            <?php
            $sql_assets = mysqli_query($mysqli, "SELECT assets.asset_id, asset_name FROM assets, asset_documents
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
                <div class="ml-2">
                    <a href="#"
                        data-toggle="ajax-modal"
                        data-modal-size="lg"
                        data-ajax-url="ajax/ajax_asset_details.php"
                        data-ajax-id="<?php echo $asset_id; ?>">
                        <?php echo $asset_name; ?></a>
                    <a class="confirm-link float-right" href="post.php?unlink_asset_from_document&asset_id=<?php echo $asset_id; ?>&document_id=<?php echo $document_id; ?>">
                        <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                    </a>
                </div>
            <?php
            }
            ?>
            <h6>
                <i class="fas fa-fw fa-cube text-secondary mr-2 mt-3"></i>Licenses
                <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkSoftwareToDocumentModal">
                    <i class="fas fa-fw fa-plus"></i>
                </button>
            </h6>
            <?php
            $sql_software = mysqli_query($mysqli, "SELECT software.software_id, software_name FROM software, software_documents
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
                <div class="ml-2">
                    <a href="software.php?client_id=<?php echo $client_id; ?>&q=<?php echo $software_name; ?>" target="_blank"><?php echo $software_name; ?></a>
                    <a class="confirm-link float-right" href="post.php?unlink_software_from_document&software_id=<?php echo $software_id; ?>&document_id=<?php echo $document_id; ?>">
                        <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                    </a>
                </div>
                <?php
                }
                ?>
            <h6>
                <i class="fas fa-fw fa-building text-secondary mr-2 mt-3"></i>Vendors
                <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#linkVendorToDocumentModal">
                    <i class="fas fa-fw fa-plus"></i>
                </button>
            </h6>
            <?php
            $sql_vendors = mysqli_query($mysqli, "SELECT vendors.vendor_id, vendor_name FROM vendors, vendor_documents
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
                <div class="ml-2">
                    <a href="#"
                        data-toggle="ajax-modal"
                        data-ajax-url="ajax/ajax_vendor_details.php"
                        data-ajax-id="<?php echo $vendor_id; ?>">
                        <?php echo $vendor_name; ?>        
                    </a>
                    <a class="confirm-link float-right" href="post.php?unlink_vendor_from_document&vendor_id=<?php echo $vendor_id; ?>&document_id=<?php echo $document_id; ?>">
                        <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                    </a>
                </div>
            <?php
            }
            ?>
        </div>

        <?php if ($config_client_portal_enable) { ?>
            <div class="card card-body bg-light">
                <h6><i class="fas fa-handshake mr-2"></i>Portal Collaboration</h6>
                <div class="mt-1">
                    <i class="fa fa-fw fa-eye<?php if (!$document_client_visible) { echo '-slash'; } ?> text-secondary mr-2"></i>Document is
                    <a href="#" data-toggle="modal" data-target="#editDocumentClientVisibileModal">
                        <?php
                        if ($document_client_visible) {
                            echo "<span class='text-bold text-dark'>visible</span>";
                        } else {
                            echo "<span class='text-muted'>not visible</span>";
                        }
                        ?>
                    </a>
                </div>
            </div>
        <?php } ?>

        <div class="card card-body bg-light">
            <h6><i class="fas fa-history mr-2"></i>Revisions</h6>
            <?php

            $sql_document_revisions = mysqli_query($mysqli, "SELECT * FROM documents
                LEFT JOIN users ON document_created_by = user_id
                WHERE document_parent = $document_parent
                ORDER BY document_created_at DESC"
            );

            while ($row = mysqli_fetch_array($sql_document_revisions)) {
                $revision_document_id = intval($row['document_id']);
                $revision_document_name = nullable_htmlentities($row['document_name']);
                $revision_document_description = nullable_htmlentities($row['document_description']);
                $revision_document_created_by_name = nullable_htmlentities($row['user_name']);
                $revision_document_created_date = nullable_htmlentities($row['document_created_at']);

                ?>
                <div class="mt-1 <?php if($document_id == $revision_document_id){ echo "text-bold"; } ?>">
                    <i class="fas fa-fw fa-history text-secondary mr-2"></i><a href="?client_id=<?php echo $client_id; ?>&document_id=<?php echo $revision_document_id; ?>"><?php echo "  $revision_document_created_date"; ?></a><?php if($document_parent == $revision_document_id){ echo "<span class='float-right'>(Parent)</span>"; 
                        } else { ?>
                        <a class="confirm-link float-right" href="post.php?delete_document_version=<?php echo $revision_document_id; ?>">
                            <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                        </a>
                    <?php 
                    } 
                    ?>
                </div>
                <?php
                }
                ?>
        </div>

    </div>

</div>

<script src="js/pretty_content.js"></script>

<?php

require_once "modals/client_document_link_file_modal.php";
require_once "modals/client_document_link_contact_modal.php";
require_once "modals/client_document_link_asset_modal.php";
require_once "modals/client_document_link_software_modal.php";
require_once "modals/client_document_link_vendor_modal.php";
require_once "modals/document_edit_visibility_modal.php";
require_once "modals/share_modal.php";
require_once "includes/footer.php";
