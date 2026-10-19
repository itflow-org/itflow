<?php

/*
 * Export the audit log to CSV or PDF - pre-filled from the list's filters and
 * view, every one editable: the export runs what this form posts, not what
 * the page happened to show (export_audit_logs, admin/post/audit_log.php).
 */

require_once '../../includes/modal_header.php';

$views = auditLogViews();
$q_filter = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
$view_filter = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$client_filter = is_scalar($_GET['client'] ?? null) ? intval($_GET['client']) : 0;
$user_filter = is_string($_GET['user'] ?? null) ? $_GET['user'] : '';
$type_filter = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$action_filter = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
$entity_filter = is_scalar($_GET['entity'] ?? null) ? intval($_GET['entity']) : 0;
$date_from_filter = (is_string($_GET['dtf'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? $_GET['dtf'] : '';
$date_to_filter = (is_string($_GET['dtt'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? $_GET['dtt'] : '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Audit Logs</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($entity_filter) { ?><input type="hidden" name="entity" value="<?= $entity_filter ?>"><?php } ?>

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <?php if ($entity_filter) { ?>
            <div class="alert alert-info small py-2"><i class="fas fa-fw fa-filter me-1"></i>Just one record: <?= escapeHtml($type_filter !== '' ? $type_filter : 'record') ?> #<?= $entity_filter ?></div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= escapeHtml($q_filter) ?>" placeholder="What was done, who, the client, the IP address or the browser">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Showing</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                <select class="form-select select2" name="view">
                    <?php foreach ($views as $key => $definition) { ?>
                        <option value="<?= $key ?>" <?= $view_filter === $key ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . $definition['icon'], 'description' => $definition['title']]) ?>><?= $key === '' ? 'Everything' : escapeHtml($definition['label']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Who</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="user">
                        <option value="">- Anyone -</option>
                        <option value="system" <?= $user_filter === 'system' ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas fa-cog']) ?>>ITFlow itself</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, user_type FROM users WHERE EXISTS (SELECT 1 FROM logs WHERE log_user_id = user_id) ORDER BY user_type ASC, user_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['user_id']); ?>
                            <option value="<?= $option_id ?>" <?= $user_filter === (string) $option_id ? 'selected' : '' ?><?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?><?= intval($row['user_type']) === 2 ? ' data-description="Portal"' : '' ?>><?= escapeHtml($row['user_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Client</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="client">
                        <option value="">- Any client -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE EXISTS (SELECT 1 FROM logs WHERE log_client_id = client_id) ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['client_id']); ?>
                            <option value="<?= $option_id ?>" <?= $client_filter === $option_id ? 'selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Type</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-shapes"></i></span>
                    <select class="form-select select2" name="type">
                        <option value="">- Any type -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT DISTINCT log_type FROM logs ORDER BY log_type ASC");
                        while ($row = mysqli_fetch_row($sql)) { ?>
                            <option value="<?= escapeHtml($row[0]) ?>" <?= $type_filter === $row[0] ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . auditLogTypeIcon($row[0])]) ?>><?= escapeHtml($row[0]) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Action</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-bolt"></i></span>
                    <select class="form-select select2" name="action">
                        <option value="">- Any action -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT DISTINCT log_action FROM logs ORDER BY log_action ASC");
                        while ($row = mysqli_fetch_row($sql)) { ?>
                            <option value="<?= escapeHtml($row[0]) ?>" <?= $action_filter === $row[0] ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . auditLogActionStyle($row[0])['icon']]) ?>><?= escapeHtml($row[0]) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">From</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">To</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
                </div>
            </div>
        </div>

        <?php exportTabsColumns('audit_logs'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_audit_logs'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
