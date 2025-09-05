<?php
/*
 * Client Portal
 * Docs for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:");

require_once "includes/inc_all.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: post.php?logout");
    exit();
}

//Initialize the HTML Purifier to prevent XSS
require_once "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

// Check for a document ID
if (!isset($_GET['id']) && !intval($_GET['id'])) {
    header("Location: documents.php");
    exit();
}

$document_id = intval($_GET['id']);
$sql_document = mysqli_query($mysqli,
        "SELECT document_id, document_name, document_content, document_description
        FROM documents
        WHERE document_id = $document_id AND document_client_visible = 1 AND document_client_id = $session_client_id AND document_archived_at IS NULL
        LIMIT 1"
);

$row = mysqli_fetch_array($sql_document);

if ($row) {
    $document_id = intval($row['document_id']);
    $document_name = nullable_htmlentities($row['document_name']);
    $document_content = $purifier->purify($row['document_content']);
    $document_description = nullable_htmlentities($row['document_description']);
} else {
    header("Location: post.php?logout");
    exit();
}

// Check for associated files
$sql_files = mysqli_query($mysqli,
    "SELECT f.file_id, f.file_name, f.file_reference_name, f.file_ext, f.file_size, f.file_mime_type
     FROM files f
     INNER JOIN document_files df ON f.file_id = df.file_id
     WHERE df.document_id = $document_id AND f.file_client_id = $session_client_id
     ORDER BY f.file_name ASC"
);

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="index.php">Home</a>
    </li>
    <li class="breadcrumb-item">
        <a href="documents.php">Documents</a>
    </li>
    <li class="breadcrumb-item active">
        Document
    </li>
</ol>

<?php
// Check if this document has attached files and handle accordingly
if (mysqli_num_rows($sql_files) > 0) {
    $file_row = mysqli_fetch_array($sql_files);
    $file_id = intval($file_row['file_id']);
    $file_name = nullable_htmlentities($file_row['file_name']);
    $file_reference_name = nullable_htmlentities($file_row['file_reference_name']);
    $file_ext = strtolower($file_row['file_ext']);
    $file_size = intval($file_row['file_size']);
    $file_mime_type = nullable_htmlentities($file_row['file_mime_type']);
    $file_size_formatted = formatBytes($file_size);
    
    $file_path = "../uploads/clients/$session_client_id/$file_reference_name";
    
    // For PDF files, display them inline
    if ($file_ext == 'pdf') {
        ?>
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="mb-0"><i class="fas fa-file-pdf text-danger mr-2"></i><?php echo $document_name; ?></h3>
                        <?php if (!empty($document_description)) { ?>
                            <small class="text-muted"><?php echo $document_description; ?></small>
                        <?php } ?>
                    </div>
                    <div class="col-auto">
                        <a href="<?php echo $file_path; ?>" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt mr-2"></i>Open in New Tab
                        </a>
                        <a href="<?php echo $file_path; ?>" download="<?php echo $file_name; ?>" class="btn btn-secondary">
                            <i class="fas fa-download mr-2"></i>Download
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <embed src="<?php echo $file_path; ?>" type="application/pdf" width="100%" height="800px" />
            </div>
        </div>
        <?php
    }
    // For images, display them inline
    elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        ?>
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="mb-0"><i class="fas fa-image text-primary mr-2"></i><?php echo $document_name; ?></h3>
                        <?php if (!empty($document_description)) { ?>
                            <small class="text-muted"><?php echo $document_description; ?></small>
                        <?php } ?>
                    </div>
                    <div class="col-auto">
                        <a href="<?php echo $file_path; ?>" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt mr-2"></i>View Full Size
                        </a>
                        <a href="<?php echo $file_path; ?>" download="<?php echo $file_name; ?>" class="btn btn-secondary">
                            <i class="fas fa-download mr-2"></i>Download
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body text-center">
                <img src="<?php echo $file_path; ?>" alt="<?php echo $file_name; ?>" class="img-fluid" style="max-height: 600px;">
            </div>
        </div>
        <?php
    }
    // For other file types, show download option with preview of content
    else {
        $file_icon = getFileIcon($file_ext);
        ?>
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="mb-0"><i class="fas fa-<?php echo $file_icon; ?> mr-2"></i><?php echo $document_name; ?></h3>
                        <?php if (!empty($document_description)) { ?>
                            <small class="text-muted"><?php echo $document_description; ?></small>
                        <?php } ?>
                    </div>
                    <div class="col-auto">
                        <a href="<?php echo $file_path; ?>" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt mr-2"></i>Open File
                        </a>
                        <a href="<?php echo $file_path; ?>" download="<?php echo $file_name; ?>" class="btn btn-secondary">
                            <i class="fas fa-download mr-2"></i>Download
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-<?php echo $file_icon; ?> fa-3x text-secondary"></i>
                    </div>
                    <div class="col">
                        <h5><?php echo $file_name; ?></h5>
                        <p class="text-muted mb-2">
                            <strong>Type:</strong> <?php echo strtoupper($file_ext); ?> File<br>
                            <strong>Size:</strong> <?php echo $file_size_formatted; ?>
                        </p>
                        <?php if (!empty($document_content) && $document_content != "<p>Uploaded file: <strong>$file_name</strong></p><p>$document_description</p>") { ?>
                            <div class="mt-3">
                                <?php echo $document_content; ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
} else {
    // Regular text-based document (no files attached)
    ?>
    <div class="card">
        <div class="card-body prettyContent">
            <h3><?php echo $document_name; ?></h3>
            <?php echo $document_content; ?>
        </div>
    </div>
    <?php
}
?>

<?php
require_once "includes/footer.php";
