<?php

/*
 * Export a client's data (functions/client_export.php) - two tabs, each its
 * own form: the documentation PDF, and the full export as a zip. Only the
 * sections this agent may read are offered; the handler checks them again.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id"));
if (!$row) {
    exit(json_encode(['content' => '<div class="modal-body">That client no longer exists.</div>']));
}
$client_name = escapeHtml($row['client_name']);
$allowed = clientExportAllowedSections();
$can_secrets = isset($allowed['credentials']);
$password_min = CLIENT_EXPORT_PASSWORD_MIN;

// The password pair for either form, shown once passwords are asked for
$password_fields = function ($form) use ($password_min) {
    ?>
    <div class="border rounded p-3 mt-2 bg-body-tertiary" data-client-export-password hidden>
        <div class="mb-2">
            <label for="<?= $form ?>Password">Password for the file</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                <input type="password" class="form-control" id="<?= $form ?>Password" name="password" minlength="<?= $password_min ?>" maxlength="200" autocomplete="new-password" disabled>
            </div>
        </div>
        <div class="mb-2">
            <label for="<?= $form ?>PasswordConfirm">Confirm password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                <input type="password" class="form-control" id="<?= $form ?>PasswordConfirm" name="password_confirm" minlength="<?= $password_min ?>" maxlength="200" autocomplete="new-password" disabled>
            </div>
        </div>
        <div class="small text-body-secondary">
            At least <?= $password_min ?> characters. Send it separately from the file - it cannot be recovered.
            <?php if ($form === 'clientExportArchive') { ?>
                The zip is encrypted with AES-256: open it with 7-Zip, WinRAR, Keka or The Unarchiver, as the tools built into Windows and macOS cannot.
            <?php } else { ?>
                The PDF asks for it when opened.
            <?php } ?>
        </div>
    </div>
    <?php
};

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-file-export me-2"></i>Export <?= $client_name ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<ul class="modal-header nav nav-pills nav-justified">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#clientExportPdfPane"><i class="fas fa-fw fa-file-pdf me-1"></i>Documentation PDF</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#clientExportArchivePane"><i class="fas fa-fw fa-file-archive me-1"></i>Full export</a>
    </li>
</ul>

<div class="tab-content" id="clientExport">

    <div class="tab-pane fade show active" id="clientExportPdfPane">
        <form action="post.php" method="post" autocomplete="off" data-client-export>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <input type="hidden" name="download_token" value="">
            <div class="modal-body">
                <p class="text-body-secondary small">
                    A printable runbook to hand over or keep on file: a cover, contents with page numbers, an overview with the key contacts,
                    then a chapter for each part you pick, and every document in full.
                </p>
                <div class="d-flex align-items-baseline mb-1">
                    <label class="fw-semibold me-auto">Include</label>
                    <a href="#" class="small me-2" data-client-export-all>All</a>
                    <a href="#" class="small" data-client-export-none>None</a>
                </div>
                <?= clientExportSectionPicker($client_id, 'pdf') ?>
                <?php if ($can_secrets) { ?>
                    <div class="form-check form-switch mt-3" data-client-export-secrets-row hidden>
                        <input class="form-check-input" type="checkbox" role="switch" name="secrets" value="1" id="clientExportPdfSecrets" data-client-export-secrets>
                        <label class="form-check-label" for="clientExportPdfSecrets">Include passwords and 2FA secrets</label>
                    </div>
                    <?php $password_fields('clientExportPdf'); ?>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="submit" name="export_client_pdf" value="1" class="btn btn-primary text-bold" data-client-export-submit>
                    <i class="fas fa-fw fa-file-pdf me-2"></i>Download PDF
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
            </div>
        </form>
    </div>

    <div class="tab-pane fade" id="clientExportArchivePane">
        <form action="post.php" method="post" autocomplete="off" data-client-export>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <input type="hidden" name="download_token" value="">
            <div class="modal-body">
                <p class="text-body-secondary small">
                    Everything as one zip: every record as a spreadsheet (CSV) and as JSON for moving to another system, documents as web pages,
                    the files in their folders, ticket conversations with their attachments, photos, the documentation PDF, and a README to find your way around.
                </p>
                <div class="d-flex align-items-baseline mb-1">
                    <label class="fw-semibold me-auto">Include</label>
                    <a href="#" class="small me-2" data-client-export-all>All</a>
                    <a href="#" class="small" data-client-export-none>None</a>
                </div>
                <?= clientExportSectionPicker($client_id, 'archive') ?>
                <hr>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="with_pdf" value="1" id="clientExportArchivePdf" checked>
                    <label class="form-check-label" for="clientExportArchivePdf">The documentation PDF</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="archived" value="1" id="clientExportArchiveArchived" checked>
                    <label class="form-check-label" for="clientExportArchiveArchived">Archived records <span class="small text-body-secondary">- dated in their Archived column</span></label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="internal" value="1" id="clientExportArchiveInternal">
                    <label class="form-check-label" for="clientExportArchiveInternal">Internal notes <span class="small text-body-secondary">- ticket notes for your team, and the client's quick notes</span></label>
                </div>
                <?php if ($can_secrets) { ?>
                    <div class="form-check form-switch" data-client-export-secrets-row>
                        <input class="form-check-input" type="checkbox" role="switch" name="secrets" value="1" id="clientExportArchiveSecrets" data-client-export-secrets>
                        <label class="form-check-label" for="clientExportArchiveSecrets">Passwords and 2FA secrets <span class="small text-body-secondary">- the zip is then locked with a password</span></label>
                    </div>
                    <?php $password_fields('clientExportArchive'); ?>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="submit" name="export_client_archive" value="1" class="btn btn-primary text-bold" data-client-export-submit>
                    <i class="fas fa-fw fa-file-archive me-2"></i>Download ZIP
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
            </div>
        </form>
    </div>

</div>

<script src="/agent/js/client_export.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
