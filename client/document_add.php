<?php
/*
 * Client Portal
 * New document - written in the editor, or ?upload=1 a file (a PDF, Word or
 * text document) with a name and a line on what it is. Both are shared with
 * the agents straight away and show in Documents.
 *
 * No Content-Security-Policy here: the editor needs inline styles.
 *
 * SCOPING: primary and technical contacts (portalItGate()); post.php
 * (client_add_document, client_upload_document) checks the same.
 */

require_once "includes/inc_all.php";

portalItGate();

$upload = !empty($_GET['upload']);
?>

<a class="itflow-portal-back" href="documents.php"><i class="fas fa-chevron-left"></i>Documents</a>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= $upload ? 'Upload a file' : 'New document' ?></h1>
        <p><?= $upload ? 'A PDF, Word or text document to keep with your others' : 'Write something down for your team and ours - a how-to, a list, a record' ?></p>
    </div>
    <div class="btn-group" role="group" aria-label="Write or upload">
        <a class="btn btn-outline-secondary<?= !$upload ? ' active' : '' ?>" href="document_add.php"><i class="fas fa-pen me-2"></i>Write</a>
        <a class="btn btn-outline-secondary<?= $upload ? ' active' : '' ?>" href="document_add.php?upload=1"><i class="fas fa-upload me-2"></i>Upload</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-9">
        <form class="itflow-portal-panel itflow-portal-panel-body" action="post.php" method="post"<?= $upload ? ' enctype="multipart/form-data"' : '' ?> autocomplete="off" data-portal-once>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label class="form-label" for="documentName">Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                    <input type="text" class="form-control" id="documentName" name="document_name" placeholder="<?= $upload ? 'What the file is - &quot;Office floor plan&quot;' : 'In a few words - &quot;Setting up the printer&quot;' ?>" required maxlength="200" autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="documentDescription">Description</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                    <input type="text" class="form-control" id="documentDescription" name="document_description" placeholder="Optional - a line on what it is for" maxlength="255">
                </div>
            </div>

            <?php if ($upload) { ?>
                <div class="mb-4">
                    <label class="form-label">File <strong class="text-danger">*</strong></label>
                    <div class="itflow-portal-drop" data-portal-drop>
                        <input type="file" name="document_file" id="documentFile" accept=".pdf,.doc,.docx,.txt,.md,.odt,.rtf" required>
                        <i class="fas fa-paperclip me-2"></i>Drop the file here, or <label for="documentFile">choose a file</label>
                        <div class="itflow-portal-files" data-portal-files hidden></div>
                    </div>
                    <div class="form-text">PDF, Word, OpenDocument, RTF or text.</div>
                </div>
                <button type="submit" class="btn btn-primary" name="client_upload_document"><i class="fas fa-upload me-2"></i>Upload</button>
            <?php } else { ?>
                <div class="mb-4">
                    <label class="form-label">What it says <strong class="text-danger">*</strong></label>
                    <textarea class="form-control tinymce" name="document_content" data-required></textarea>
                    <div class="invalid-feedback d-block" data-editor-required-note hidden>Write something in the document.</div>
                </div>
                <button type="submit" class="btn btn-primary" name="client_add_document"><i class="fas fa-check me-2"></i>Save document</button>
            <?php } ?>
        </form>
    </div>
</div>

<?php
require_once "includes/footer.php";
