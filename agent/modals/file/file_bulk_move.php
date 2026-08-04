<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$current_folder_id = intval($_GET['current_folder_id'] ?? 0);

// Selected IDs from JS (may be empty arrays)
$file_ids      = array_map('intval', $_GET['file_ids']      ?? []);
$document_ids  = array_map('intval', $_GET['document_ids']  ?? []);

$count_files = count($file_ids);
$count_docs  = count($document_ids);
$total       = $count_files + $count_docs;

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-exchange-alt mr-2"></i>
        Move <strong><?= $total ?></strong> Item<?= $total === 1 ? '' : 's' ?>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <?php foreach ($file_ids as $id): ?>
        <input type="hidden" name="file_ids[]" value="<?= $id ?>">
    <?php endforeach; ?>

    <?php foreach ($document_ids as $id): ?>
        <input type="hidden" name="document_ids[]" value="<?= $id ?>">
    <?php endforeach; ?>

    <div class="modal-body">

        <p>
            Files: <strong><?= $count_files ?></strong><br>
            Documents: <strong><?= $count_docs ?></strong>
        </p>

        <div class="form-group">
            <label>Target Folder</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                </div>
                <select class="form-control select2" name="bulk_folder_id">
                    <option value="0">/</option>
                    <?php
                    // NOTE: folder_location is gone now, so just use folder_client_id
                    $sql_all_folders = mysqli_query(
                        $mysqli,
                        "SELECT folder_id, folder_name, parent_folder
                         FROM folders
                         WHERE folder_client_id = $client_id
                         ORDER BY folder_name ASC"
                    );

                    $folders = [];

                    while ($row = mysqli_fetch_assoc($sql_all_folders)) {
                        $folders[$row['folder_id']] = [
                            'folder_id'    => (int)$row['folder_id'],
                            'folder_name'  => escapeHtml($row['folder_name']),
                            'parent_folder'=> (int)$row['parent_folder'],
                            'children'     => []
                        ];
                    }

                    // Build hierarchy
                    foreach ($folders as $id => &$folder) {
                        if ($folder['parent_folder'] != 0 && isset($folders[$folder['parent_folder']])) {
                            $folders[$folder['parent_folder']]['children'][] = &$folder;
                        }
                    }
                    unset($folder);

                    $root_folders = [];
                    foreach ($folders as $id => $folder) {
                        if ($folder['parent_folder'] == 0) {
                            $root_folders[] = $folder;
                        }
                    }

                    // Optional: if you want to default-select current folder, pass it in GET
                    $current_folder_id = intval($_GET['current_folder_id'] ?? 0);

                    $stack = [];
                    foreach (array_reverse($root_folders) as $folder) {
                        $stack[] = ['folder' => $folder, 'level' => 0];
                    }

                    while (!empty($stack)) {
                        $node   = array_pop($stack);
                        $folder = $node['folder'];
                        $level  = $node['level'];

                        $indentation = str_repeat('&nbsp;', $level * 4);

                        $selected = ($folder['folder_id'] === $current_folder_id) ? 'selected' : '';

                        echo "<option value=\"{$folder['folder_id']}\" $selected>$indentation{$folder['folder_name']}</option>";

                        if (!empty($folder['children'])) {
                            foreach (array_reverse($folder['children']) as $child) {
                                $stack[] = ['folder' => $child, 'level' => $level + 1];
                            }
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_move_files" class="btn btn-primary text-bold">
            <i class="fa fa-check mr-2"></i>Move Files
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
