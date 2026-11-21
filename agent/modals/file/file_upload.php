<?php

/*
 * Upload files into a folder - dropped onto the box, picked with the button,
 * or dropped straight onto the files list (agent/js/files.js opens this and
 * hands them over). Opened from an asset, a contact, a license, a location or
 * a vendor, the uploads are also linked to it.
 *
 * A folder from the computer - Choose a folder, or one dropped - keeps its
 * folders: each file goes with its path under it (file_paths[]), and the
 * folders are made as needed. It goes up twenty files at a time (upload_json),
 * and upload_folder_done says how it went before the page reloads.
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
$location_id = assetClientOwnedId('location', $_GET['location_id'] ?? 0, $client_id);
$vendor_id = assetClientOwnedId('vendor', $_GET['vendor_id'] ?? 0, $client_id);
$link_name = $asset_id ? $asset['name'] : ($contact_id ? getFieldById('contacts', $contact_id, 'contact_name') : ($software_id ? $software['name'] : ($location_id ? getFieldById('locations', $location_id, 'location_name') : ($vendor_id ? getFieldById('vendors', $vendor_id, 'vendor_name') : ''))));

// The extensions agent/post/file.php accepts - offered to the picker so the wrong kind is never chosen
$upload_extensions = ['jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'csv', 'xls', 'xlsx', 'ods',
    'pptx', 'odp', 'zip', 'tar', 'gz', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn', 'cfg', 'ps1', 'vsdx',
    'drawio', 'pfx', 'pages', 'numbers', 'unf', 'unifi', 'key', 'stk', 'bat', 'swb'];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cloud-upload-alt me-2"></i>Upload Files</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off" data-upload-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <input type="hidden" name="software_id" value="<?= $software_id ?>">
    <input type="hidden" name="location_id" value="<?= $location_id ?>">
    <input type="hidden" name="vendor_id" value="<?= $vendor_id ?>">
    <div class="modal-body">

        <label class="itflow-upload-drop" data-upload-drop>
            <input type="file" class="visually-hidden" name="file[]" multiple data-upload-input
                accept="<?= escapeHtml(implode(',', array_map(function ($extension) { return ".$extension"; }, $upload_extensions))) ?>">
            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
            <span class="fw-semibold">Drop files here, or click to choose</span>
            <span class="small text-body-secondary">Up to 20 at a time, 500 MB in all</span>
        </label>
        <input type="file" class="visually-hidden" webkitdirectory multiple data-upload-folder-input>
        <div class="small mt-1">
            <button type="button" class="btn btn-link btn-sm p-0" data-upload-folder><i class="fas fa-folder-open me-1"></i>Choose a folder</button>
            <span class="text-body-secondary">- its folders come too</span>
        </div>
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
        // A folder goes up in parts, so it can be bigger
        var FOLDER_MAX_FILES = 1000;
        var FOLDER_MAX_BYTES = 2 * 1024 * 1024 * 1024;
        var PART_FILES = 20;
        var PART_BYTES = 100 * 1024 * 1024;
        var picked = null; // a folder's files: [{file, path}] - otherwise input.files
        var folderInput = form.querySelector('[data-upload-folder-input]');
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
            var items = picked || Array.prototype.map.call(input.files || [], function (file) { return { file: file, path: '' }; });
            var files = items.map(function (item) { return item.file; });
            var total = files.reduce(function (sum, file) { return sum + file.size; }, 0);
            list.innerHTML = '';
            items.slice(0, 20).forEach(function (item) {
                var file = item.file;
                var li = document.createElement('li');
                li.className = 'd-flex align-items-center gap-2 small';
                var icon = document.createElement('i');
                icon.className = 'fas fa-fw fa-file text-body-secondary';
                var name = document.createElement('span');
                name.className = 'text-truncate';
                name.textContent = item.path || file.name;
                var bytes = document.createElement('span');
                bytes.className = 'text-body-secondary ms-auto text-nowrap';
                bytes.textContent = size(file.size);
                li.append(icon, name, bytes);
                list.appendChild(li);
            });
            if (items.length > 20) {
                var more = document.createElement('li');
                more.className = 'small text-body-secondary';
                more.textContent = 'and ' + (items.length - 20) + ' more';
                list.appendChild(more);
            }
            var problem = picked
                ? (files.length > FOLDER_MAX_FILES ? 'That is ' + files.length + ' files - upload a folder of up to ' + FOLDER_MAX_FILES + '.'
                    : total > FOLDER_MAX_BYTES ? 'That is ' + size(total) + ' - keep a folder under 2 GB.' : '')
                : files.length > MAX_FILES ? 'That is ' + files.length + ' files - upload up to ' + MAX_FILES + ' at a time, or choose a folder.'
                : total > MAX_BYTES ? 'That is ' + size(total) + ' - keep each upload under 500 MB.' : '';
            error.textContent = problem;
            error.hidden = !problem;
            submit.disabled = !files.length || !!problem;
            drop.classList.toggle('has-files', files.length > 0);
        }

        // A folder's files, with where each sits under it
        function takeFolder(items) {
            picked = items.filter(function (item) {
                // What the computer keeps beside files, not anyone's files
                return !/(^|\/)(\.DS_Store|Thumbs\.db|desktop\.ini)$/i.test(item.path) && !/(^|\/)\.[^\/]*\//.test(item.path);
            });
            input.value = '';
            show();
        }
        input.itflowTakeFolder = takeFolder;

        // A folder dropped: walk it for its files
        function walk(entry, prefix) {
            return new Promise(function (resolve) {
                if (entry.isFile) {
                    entry.file(function (file) { resolve([{ file: file, path: prefix + file.name }]); }, function () { resolve([]); });
                    return;
                }
                var reader = entry.createReader();
                var all = [];
                (function more() {
                    reader.readEntries(function (entries) {
                        if (!entries.length) {
                            Promise.all(all.map(function (child) { return walk(child, prefix + entry.name + '/'); }))
                                .then(function (lists) { resolve([].concat.apply([], lists)); });
                            return;
                        }
                        all = all.concat(Array.prototype.slice.call(entries));
                        more();
                    }, function () { resolve([]); });
                })();
            });
        }
        input.itflowWalk = walk;

        input.addEventListener('change', function () {
            picked = null;
            show();
        });
        form.querySelector('[data-upload-folder]').addEventListener('click', function () {
            folderInput.click();
        });
        folderInput.addEventListener('change', function () {
            takeFolder(Array.prototype.map.call(folderInput.files || [], function (file) {
                return { file: file, path: file.webkitRelativePath || file.name };
            }));
            folderInput.value = '';
        });
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
            // Folders among what was dropped - read while the drop is still going on
            var entries = Array.prototype.map.call((e.dataTransfer && e.dataTransfer.items) || [], function (item) {
                return item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
            }).filter(Boolean);
            if (entries.some(function (entry) { return entry.isDirectory; })) {
                Promise.all(entries.map(function (entry) { return walk(entry, ''); })).then(function (lists) {
                    takeFolder([].concat.apply([], lists));
                });
                return;
            }
            if (e.dataTransfer && e.dataTransfer.files.length) {
                picked = null;
                input.files = e.dataTransfer.files;
                show();
            }
        });

        // A folder goes up twenty files at a time, then the page reloads with how it went
        function sendFolder() {
            var parts = [], part = [], bytes = 0;
            picked.forEach(function (item) {
                if (part.length && (part.length >= PART_FILES || bytes + item.file.size > PART_BYTES)) {
                    parts.push(part);
                    part = [];
                    bytes = 0;
                }
                part.push(item);
                bytes += item.file.size;
            });
            if (part.length) {
                parts.push(part);
            }
            var done = { uploaded: 0, folders: 0, skipped: [] };
            var sent = 0;
            submit.disabled = true;
            (function next(index) {
                if (index >= parts.length) {
                    var finish = new FormData();
                    finish.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
                    finish.append('upload_folder_done', '1');
                    finish.append('uploaded', done.uploaded);
                    finish.append('folders', done.folders);
                    done.skipped.forEach(function (name) { finish.append('skipped[]', name); });
                    fetch(form.action, { method: 'POST', body: finish, credentials: 'same-origin' }).finally(function () {
                        window.location.reload();
                    });
                    return;
                }
                submit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading ' + (sent + 1) + ' of ' + picked.length + '...';
                var body = new FormData(form);
                body.delete('file[]');
                body.append('upload_files', '1');
                body.append('upload_json', '1');
                parts[index].forEach(function (item) {
                    body.append('file[]', item.file, item.file.name);
                    body.append('file_paths[]', item.path);
                });
                fetch(form.action, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                    .then(function (answer) {
                        done.uploaded += answer.uploaded || 0;
                        done.folders += answer.folders || 0;
                        done.skipped = done.skipped.concat(answer.skipped || []);
                    })
                    .catch(function () {
                        done.skipped = done.skipped.concat(parts[index].map(function (item) { return item.path; }));
                    })
                    .finally(function () {
                        sent += parts[index].length;
                        next(index + 1);
                    });
            })(0);
        }

        form.addEventListener('submit', function (e) {
            if (submit.disabled) {
                e.preventDefault();
                return;
            }
            if (picked) {
                e.preventDefault();
                sendFolder();
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
