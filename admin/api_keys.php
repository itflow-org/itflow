<?php

/*
 * Admin > API Keys - the keys other systems use the API with. Each acts as
 * an agent, so each shows who, what their role opens and which clients
 * they see, whether the key works (it expires, and an agent who is
 * disabled, archived or has no role turns every call away) and when it was
 * last used. The work is in functions/api_keys.php; the handlers are
 * admin/post/api_keys.php.
 */

require_once "includes/inc_all_admin.php";

$search = trim((string) ($_GET['q'] ?? ''));
$keys = apiKeyRows($search);
$roles = roleRows();
$access = userClientAccess(array_filter(array_map('intval', array_column($keys, 'api_key_user_id'))));
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-key me-2"></i>API Keys</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/api/api_key_add.php"><i class="fas fa-plus me-2"></i>New API Key</button>
        </div>
    </div>

    <div class="p-2 border-bottom">
        <p class="small text-secondary mb-2">A key lets another system use ITFlow's API as one of your agents - it can do what their <a href="roles.php">role</a> allows, for the clients they see, until it expires.</p>
        <form autocomplete="off" class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm w-auto me-auto">
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($search) ?>" placeholder="Search keys and agents">
                <button class="btn btn-primary" title="Search"><i class="fa fa-search"></i></button>
            </div>
            <div class="dropdown" id="bulkActionButton" hidden>
                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-layer-group me-1"></i>Bulk Action (<span id="selectedCount">0</span>)
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_api_keys"
                        data-confirm-title="Delete these keys?" data-confirm-text="Anything using them stops working." data-confirm-button="Delete">
                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                    </button>
                </div>
            </div>
        </form>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="table-responsive-sm">
            <table class="table table-borderless table-hover align-middle mb-0">
                <?php if ($keys) { ?>
                    <thead class="text-dark">
                    <tr>
                        <th class="ps-3" style="width: 1%">
                            <div class="form-check mb-0">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all">
                            </div>
                        </th>
                        <th>Key</th>
                        <th class="d-none d-md-table-cell">Acts as</th>
                        <th class="d-none d-lg-table-cell">Can open</th>
                        <th class="d-none d-sm-table-cell">Last used</th>
                        <th class="text-center" style="width: 1%">Action</th>
                    </tr>
                    </thead>
                <?php } ?>
                <tbody>
                <?php foreach ($keys as $api_key_id => $row) {
                    $name = escapeHtml($row['api_key_name']);
                    $role = $roles[intval($row['user_role_id'])] ?? null;
                    $expired = apiKeyExpired($row);
                    $agent_name = trim(str_replace(' (archived)', '', (string) $row['user_name']));
                    $last_used = $row['api_key_last_used_at'] ? '<span title="' . escapeHtml($row['api_key_last_used_at']) . '">' . escapeHtml(timeAgo($row['api_key_last_used_at'])) . '</span>' : '<span class="text-secondary">Never</span>';
                    ob_start(); ?>
                    <?php if ($row['user_name'] !== null && intval($row['user_type']) === 1) { ?>
                        <div class="d-flex align-items-center gap-2">
                            <?= userAvatar($row['api_key_user_id'], $agent_name, $row['user_avatar'], 'sm') ?>
                            <div class="min-w-0">
                                <div class="text-truncate"><?= escapeHtml($agent_name) ?></div>
                                <div class="small text-body-secondary text-truncate"><i class="fas fa-fw <?= $role && intval($role['role_is_admin']) ? 'fa-user-shield' : 'fa-user-tag' ?> me-1"></i><?= $role ? escapeHtml($role['role_name']) : 'No role' ?></div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <span class="small text-danger-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>Nobody</span>
                    <?php }
                    $agent_html = ob_get_clean();
                    $can_html = $role ? roleAccessHtml($role) . '<div class="small text-body-secondary mt-1"><i class="fas fa-fw fa-building me-1"></i>' . escapeHtml(userClientAccessText($access[intval($row['api_key_user_id'])] ?? ['allow' => [], 'deny' => []], intval($role['role_is_admin']))) . '</div>' : '<span class="small text-secondary">Nothing</span>';
                    ?>
                    <tr data-api-key-id="<?= $api_key_id ?>" class="<?= $expired ? 'text-body-secondary' : '' ?>">
                        <td class="ps-3">
                            <div class="form-check mb-0">
                                <input class="form-check-input bulk-select" type="checkbox" name="api_key_ids[]" value="<?= $api_key_id ?>" aria-label="Select <?= $name ?>">
                            </div>
                        </td>
                        <td>
                            <a class="ajax-modal fw-semibold <?= $expired ? 'text-body-secondary' : 'text-body' ?>" href="#" data-modal-url="modals/api/api_key_edit.php?id=<?= $api_key_id ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                            <div class="small text-body-secondary"><span class="font-monospace">••••<?= escapeHtml(substr((string) $row['api_key_secret'], -4)) ?></span> · made <?= date('M j, Y', strtotime($row['api_key_created_at'])) ?></div>
                            <div class="mt-1"><?= apiKeyStatePill($row, $role) ?></div>
                            <!-- On a phone, who it acts as and when it was used come along -->
                            <div class="d-md-none mt-2"><?= $agent_html ?></div>
                            <div class="d-lg-none mt-2"><?= $can_html ?></div>
                            <div class="d-sm-none small mt-1">Last used <?= $last_used ?></div>
                        </td>
                        <td class="d-none d-md-table-cell"><?= $agent_html ?></td>
                        <td class="d-none d-lg-table-cell"><?= $can_html ?></td>
                        <td class="d-none d-sm-table-cell small text-nowrap"><?= $last_used ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/api/api_key_edit.php?id=<?= $api_key_id ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                    <div class="dropdown-divider"></div>
                                    <?php if (!$expired) { // revoked first, then deleted ?>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?revoke_api_key=<?= $api_key_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Revoke <?= $name ?>?" data-confirm-text="It stops working now. Anything using it is turned away." data-confirm-button="Revoke"><i class="fas fa-fw fa-ban me-2"></i>Revoke</a>
                                    <?php } else { ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_api_key=<?= $api_key_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Delete <?= $name ?>?" data-confirm-text="It has expired, so nothing can be using it." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php if (!$keys) { ?>
        <?= emptyStateHtml([
            'thing' => 'API keys',
            'icon' => 'fa-key',
            'text' => 'A key lets another system read and write ITFlow through its API, as one of your agents.',
            'filtered' => $search !== '',
            'clear_url' => 'api_keys.php',
            'actions' => [['label' => 'New API Key', 'modal' => 'modals/api/api_key_add.php']],
        ]) ?>
    <?php } ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
