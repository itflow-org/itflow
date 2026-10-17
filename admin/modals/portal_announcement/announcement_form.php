<?php

/*
 * An announcement's fields - for portal_announcement_add.php and _edit.php,
 * which set $announcement (null when new). Posts to admin/post.php
 * (admin/post/portal_announcement.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

$a = $announcement ?? [];
$a_tone = isset(PORTAL_ANNOUNCEMENT_TONES[$a['announcement_tone'] ?? '']) ? $a['announcement_tone'] : 'info';
$a_client = intval($a['announcement_client_id'] ?? 0);
$a_local = function ($value) {
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
};
?>

<div class="mb-3">
    <label class="form-label">Title <strong class="text-danger">*</strong></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-bullhorn"></i></span>
        <input type="text" class="form-control" name="title" maxlength="200" placeholder="Planned maintenance Saturday night" value="<?= escapeHtml($a['announcement_title'] ?? '') ?>" required autofocus>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Message</label>
    <textarea class="form-control" name="body" rows="5" maxlength="5000" placeholder="What is happening, when, and what they need to do - web addresses become links"><?= escapeHtml($a['announcement_body'] ?? '') ?></textarea>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Shown as</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
            <select class="form-select" name="tone">
                <?php foreach (PORTAL_ANNOUNCEMENT_TONES as $key => $tone) { ?>
                    <option value="<?= $key ?>"<?= $a_tone === $key ? ' selected' : '' ?>><?= escapeHtml($tone['label']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">For</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
            <select class="form-select select2" name="client_id">
                <option value="0">Every client</option>
                <?php
                $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL OR client_id = $a_client ORDER BY client_name ASC");
                while ($row = mysqli_fetch_assoc($sql_clients)) { ?>
                    <option value="<?= intval($row['client_id']) ?>"<?= intval($row['client_id']) === $a_client ? ' selected' : '' ?>><?= escapeHtml($row['client_name']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Show from</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar-plus"></i></span>
            <input type="datetime-local" class="form-control" name="starts_at" value="<?= escapeHtml($a_local($a['announcement_starts_at'] ?? '')) ?>">
        </div>
        <small class="text-secondary">Empty is straight away</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Until</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
            <input type="datetime-local" class="form-control" name="ends_at" value="<?= escapeHtml($a_local($a['announcement_ends_at'] ?? '')) ?>">
        </div>
        <small class="text-secondary">Empty is until you archive it</small>
    </div>
</div>
