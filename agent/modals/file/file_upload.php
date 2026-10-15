<?php

/*
 * Upload files into a folder - dropped onto the box, picked with the button,
 * or dropped straight onto the files list (agent/js/files.js opens this and
 * hands them over). Opened from an asset, a contact or a license, the uploads
 * are also linked to it.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$folder_id = folderOwnedId($_GET['folder_id'] ?? 0, $client_id);
$contact_id = assetClientOwnedId('contact', $_GET['contact_id'] ?? 0, $client_id);
$asset = linkRecord('asset', $_GET['asset_id'] ?? 0);
$asset_id = $asset && intval($asset['client_id']) === $client_id ? intval($asset['id']) : 0;
$software = linkRecord('software', $_GET['software_id'] ?? 0);
$software_id = $software && intval($software['client_id']) === $client_id ? intval($software['id']) : 0;
$link_name = $asset_id ? $asset['name'] : ($contact_id ? getFieldById('contacts', $contact_id, 'contact_name') : ($software_id ? $software['name'] : ''));

// The extensions agent/post/file.php accepts - offered to the picker so the wrong kind is never chosen
$upload_extensions = ['jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'csv', 'xls', 'xlsx', 'ods',
    'pptx', 'odp', 'zip', 'tar', 'gz', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn', 'cfg', 'ps1', 'vsdx',
    'drawio', 'pfx', 'pages', 'numbers', 'unf', 'unifi', 'key', 'stk', 'bat', 'swb'];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cloud-upload-alt me-2"></i>Upload files</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off" data-upload-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <input type="hidden" name="software_id" value="<?= $software_id ?>">
    <div class="modal-body">

        <label class="itflow-upload-drop" data-upload-drop>
            <input type="file" class="visually-hidden" name="file[]" multiple data-upload-input
                accept="<?= escapeHtml(implode(',', array_map(function ($extension) { return ".$extension"; }, $upload_extensions))) ?>">
            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
            <span class="fw-semibold">Drop files here, or click to choose</span>
            <span class="small text-body-secondary">Up to 20 at a time, 500 MB in all</span>
        </label>
        <ul class="list-unstyled mb-0 mt-2 itflow-upload-list" data-upload-list></ul>
        <div class="text-danger small mt-1" data-upload-error hidden></div>

        <div class="mt-3 mb-3">
            <label class="form-label">Folder</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                <select class="form-select select2" name="folder_id">
                    <?= folderOptionsHtml($client_id, $folder_id) ?>
                </select>
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label">Description <span class="text-body-secondary small">- optional, given to every file</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" maxlength="250" placeholder="What these are">
            </div>
        </div>

        <?php if ($link_name) { ?>
            <div class="small text-body-secondary mt-3">
                <i class="fas fa-link me-1"></i>They will be linked to <strong><?= escapeHtml($link_name) ?></strong>.
            </div>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="upload_files" class="btn btn-primary text-bold" data-upload-submit disabled><i class="fa fa-upload me-2"></i>Upload</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        var form = document.querySelector('form[data-upload-form]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';

        var MAX_FILES = 20;
        var MAX_BYTES = 500 * 1024 * 1024;
        var input = form.querySelector('[data-upload-input]');
        var drop = form.querySelector('[data-upload-drop]');
        var list = form.querySelector('[data-upload-list]');
        var error = form.querySelector('[data-upload-error]');
        var submit = form.querySelector('[data-upload-submit]');

        function size(bytes) {
            var units = ['B', 'KB', 'MB', 'GB'], i = 0;
            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            return (i ? bytes.toFixed(1) : bytes) + ' ' + units[i];
        }

        function show() {
            var files = Array.prototype.slice.call(input.files || []);
            var total = files.reduce(function (sum, file) { return sum + file.size; }, 0);
            list.innerHTML = '';
            files.forEach(function (file) {
                var li = document.createElement('li');
                li.className = 'd-flex align-items-center gap-2 small';
                var icon = document.createElement('i');
                icon.className = 'fas fa-fw fa-file text-body-secondary';
                var name = document.createElement('span');
                name.className = 'text-truncate';
                name.textContent = file.name;
                var bytes = document.createElement('span');
                bytes.className = 'text-body-secondary ms-auto text-nowrap';
                bytes.textContent = size(file.size);
                li.append(icon, name, bytes);
                list.appendChild(li);
            });
            var problem = files.length > MAX_FILES ? 'That is ' + files.length + ' files - upload up to ' + MAX_FILES + ' at a time.'
                : total > MAX_BYTES ? 'That is ' + size(total) + ' - keep each upload under 500 MB.' : '';
            error.textContent = problem;
            error.hidden = !problem;
            submit.disabled = !files.length || !!problem;
            drop.classList.toggle('has-files', files.length > 0);
        }

        input.addEventListener('change', show);
        ['dragenter', 'dragover'].forEach(function (type) {
            drop.addEventListener(type, function (e) {
                e.preventDefault();
                drop.classList.add('is-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            drop.addEventListener(type, function () {
                drop.classList.remove('is-over');
            });
        });
        drop.addEventListener('drop', function (e) {
            e.preventDefault();
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                show();
            }
        });
        form.addEventListener('submit', function (e) {
            if (submit.disabled) {
                e.preventDefault();
                return;
            }
            // Disabled after the browser has read the submitter, so upload_files still posts
            setTimeout(function () {
                submit.disabled = true;
                submit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            }, 0);
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
