<?php

/*
 * A role's fields - for role_add.php (new, or a copy) and role_edit.php,
 * which set $role (a roleRow() to fill from, or null) and $role_admin_blocker
 * (why it can't stop being an admin role, '' when it can). An admin role
 * opens everything; any other has a level of each module, each showing what
 * it lets them do. js/role_form.js hides the levels for an admin role and
 * ticks off what a level allows as it is picked.
 */

isset($mysqli) || die("Direct file access is not allowed");

$r = $role ?? null;
$r_admin = $r ? intval($r['role_is_admin']) : 0;
$r_levels = $r['levels'] ?? [];
$r_admin_locked = ($role_admin_blocker ?? '') !== '';

?>

<div data-role-form>
    <div class="mb-3">
        <label>Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-user-tag"></i></span>
            <input type="text" class="form-control" name="name" maxlength="200" placeholder="Help Desk, Billing, Field Tech" value="<?= escapeHtml($r['role_name'] ?? '') ?>" required autofocus>
        </div>
    </div>

    <div class="mb-3">
        <label>Description</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" maxlength="200" placeholder="Who it is for" value="<?= escapeHtml($r['role_description'] ?? '') ?>">
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="admin" value="1" id="roleAdmin" data-role-admin<?= $r_admin ? ' checked' : '' ?><?= $r_admin_locked ? ' disabled' : '' ?>>
            <label class="form-check-label fw-semibold" for="roleAdmin"><i class="fas fa-fw fa-user-shield me-1"></i>Admin</label>
            <div class="form-text mt-0">Every part of ITFlow, every client, and Admin - users, roles and settings.</div>
            <?php if ($r_admin_locked) { ?>
                <input type="hidden" name="admin" value="1">
                <div class="form-text mt-0 text-warning-emphasis"><i class="fas fa-fw fa-lock me-1"></i>It stays an admin role: <?= escapeHtml($role_admin_blocker) ?></div>
            <?php } ?>
        </div>
    </div>

    <div data-role-levels<?= $r_admin ? ' hidden' : '' ?>>
        <label class="mb-2">What its agents can open</label>
        <?php
        // Each client's pages need Clients - most of the rest is on them
        $r_by_name = [];
        foreach (roleModules() as $module_id => $module) {
            $r_by_name[$module['module_name']] = intval($r_levels[$module_id] ?? 0);
        }
        $r_needs_clients = empty($r_by_name['module_client'])
            && max($r_by_name['module_support'] ?? 0, $r_by_name['module_credential'] ?? 0, $r_by_name['module_sales'] ?? 0, $r_by_name['module_financial'] ?? 0) > 0;
        ?>
        <div class="alert alert-warning small py-2" data-role-needs-clients<?= $r_needs_clients ? '' : ' hidden' ?>><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Each client's pages need Clients at Read, and most of what is picked here is on them - with Clients at None, its agents are turned away from most of it.</div>
        <?php foreach (roleModules() as $module_id => $module) {
            $level = min(intval($r_levels[$module_id] ?? 0), $module['max']);
            $label = escapeHtml($module['label']); ?>
            <div class="border rounded p-2 mb-2" data-role-module="<?= escapeHtml($module['module_name']) ?>">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                        <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw <?= $module['icon'] ?>"></i></span>
                        <div class="min-w-0">
                            <div class="fw-semibold"><?= $label ?></div>
                            <div class="small text-secondary"><?= escapeHtml($module['about']) ?></div>
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="<?= $label ?>">
                        <?php foreach (ROLE_LEVELS as $value => $level_label) {
                            if ($value > $module['max']) {
                                break;
                            }
                            $id = "roleLevel{$module_id}_$value"; ?>
                            <input class="btn-check" type="radio" name="levels[<?= $module_id ?>]" id="<?= $id ?>" value="<?= $value ?>" autocomplete="off"<?= $level === $value ? ' checked' : '' ?>>
                            <label class="btn btn-outline-<?= ROLE_LEVEL_TONES[$value] ?>" for="<?= $id ?>"><?= $level_label ?></label>
                        <?php } ?>
                    </div>
                </div>
                <ul class="list-unstyled small mb-0 mt-2 itflow-role-level-list">
                    <?php foreach ($module['levels'] as $value => $text) {
                        $on = $value <= $level; ?>
                        <li data-role-level="<?= $value ?>"<?= $on ? '' : ' class="is-off"' ?>><i class="fas fa-fw <?= $on ? 'fa-check text-success' : 'fa-times' ?> me-1" aria-hidden="true"></i><?= escapeHtml($text) ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
    </div>
    <div class="small text-secondary" data-role-admin-note<?= $r_admin ? '' : ' hidden' ?>><i class="fas fa-fw fa-info-circle me-1"></i>An admin role opens every part of ITFlow, so there are no levels to set. Its levels are kept, for if it stops being one.</div>
</div>

<script src="/js/role_form.js"></script>
