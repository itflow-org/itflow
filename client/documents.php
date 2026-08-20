<?php
/*
 * Client Portal
 * Docs for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

enforceContactCan('itdoc');

$documents_sql = mysqli_query($mysqli, "SELECT document_id, document_name, document_created_at, folder_name FROM documents LEFT JOIN folders ON document_folder_id = folder_id WHERE document_client_visible = 1 AND document_client_id = $session_client_id AND document_archived_at IS NULL ORDER BY folder_id, document_name DESC");
?>

<div class="row">
    <div class="col">
        <h3><i class="fas fa-file-alt me-2"></i>Documents</h3>
    </div>
    <div class="col-auto">
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                <i class="fas fa-plus me-2"></i>New Document
            </button>
            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#uploadFileDocumentModal">
                <i class="fas fa-upload me-2"></i>Upload File
            </button>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <table class="table table-bordered border border-dark">
            <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Created</th>
                <th class="text-center">Actions</th>
            </tr>
            </thead>
            <tbody>

            <?php
            while ($row = mysqli_fetch_assoc($documents_sql)) {
                $document_id = intval($row['document_id']);
                $folder_name = escapeHtml($row['folder_name']);
                $document_name = escapeHtml($row['document_name']);
                $document_created_at = escapeHtml($row['document_created_at']);

                ?>

                <tr>
                    <td>
                        <a href="document.php?id=<?= $document_id ?>">
                            <i class="fas fa-file-alt me-2"></i>
                            <?php
                            if (!empty($folder_name)) {
                                echo "$folder_name / ";
                            }
                            echo $document_name;
                            ?>
                        </a>
                    </td>
                    <td><?= date('M j, Y', strtotime($document_created_at)) ?></td>
                    <td class="text-center">
                        <a href="document.php?id=<?= $document_id ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php } ?>

            </tbody>
        </table>
    </div>
</div>

<!-- New Document Modal -->
<div class="modal" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt me-2"></i>Create New Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body bg-white">
                    <div class="mb-3">
                        <label>Document Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                            <input type="text" class="form-control" name="document_name" placeholder="Enter document name" required maxlength="200">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Description</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                            <input type="text" class="form-control" name="document_description" placeholder="Brief description (optional)" maxlength="255">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Content <strong class="text-danger">*</strong></label>
                        <textarea class="form-control" name="document_content" rows="8" placeholder="Enter document content..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="client_add_document" class="btn btn-primary"><i class="fa fa-check me-2"></i>Create Document</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload File Document Modal -->
<div class="modal" id="uploadFileDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-upload me-2"></i>Upload Document File</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body bg-white">
                    <div class="mb-3">
                        <label>Document Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                            <input type="text" class="form-control" name="document_name" placeholder="Enter document name" required maxlength="200">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Description</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                            <input type="text" class="form-control" name="document_description" placeholder="Brief description (optional)" maxlength="255">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Upload File <strong class="text-danger">*</strong></label>
                        <input type="file" class="form-control" name="document_file" id="documentFileInput"
                               accept=".pdf,.doc,.docx,.txt,.md,.odt,.rtf" required>
                        <small class="text-secondary">Supported formats: PDF, Word documents, text files</small>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="client_upload_document" class="btn btn-primary"><i class="fa fa-upload me-2"></i>Upload Document</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once "includes/footer.php";
