<?php

/*
 * An announcement's fields - for the client portal's (admin/modals/portal_announcement/)
 * and the banners for agents (agent/modals/agent_banner/), which set $announcement (null
 * when new) and, for a banner, $announcement_for_agents. A banner added from a client's
 * page sets $announcement_client_fixed to that client, so it can't be pointed elsewhere.
 * What it posts is read by announcementFromPost() (functions/portal.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

$a = $announcement ?? [];
$a_agents = !empty($announcement_for_agents);
$a_tone = isset(PORTAL_ANNOUNCEMENT_TONES[$a['announcement_tone'] ?? '']) ? $a['announcement_tone'] : 'info';
$a_client = intval($announcement_client_fixed ?? ($a['announcement_client_id'] ?? 0));
$a_local = function ($value) {
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
};
?>

<div class="mb-3">
    <label class="form-label">Title <strong class="text-danger">*</strong></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fas fa-fw fa-bullhorn"></i></span>
        <input type="text" class="form-control" name="title" maxlength="200" placeholder="<?= $a_agents ? 'On credit hold - no work without approval' : 'Planned maintenance Saturday night' ?>" value="<?= escapeHtml($a['announcement_title'] ?? '') ?>" required autofocus>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Message</label>
    <textarea class="form-control" name="body" rows="<?= $a_agents ? 3 : 5 ?>" maxlength="5000" placeholder="<?= $a_agents ? 'What anyone working for them needs to know, and who to ask - web addresses become links' : 'What is happening, when, and what they need to do - web addresses become links' ?>"><?= escapeHtml($a['announcement_body'] ?? '') ?></textarea>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Shown as</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-flag"></i></span>
            <select class="form-select" name="tone">
                <?php foreach (PORTAL_ANNOUNCEMENT_TONES as $key => $tone) { ?>
                    <option value="<?= $key ?>"<?= $a_tone === $key ? ' selected' : '' ?>><?= escapeHtml($tone['label']) ?></option>
                <?php } ?>
            </select>
        </div>
        <?php if ($a_agents) { ?>
            <small class="text-secondary">Urgent is big and red, and nobody can hide it</small>
        <?php } ?>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">For</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-users"></i></span>
            <?php if (isset($announcement_client_fixed)) {
                $a_client_name = mysqli_fetch_row(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $a_client"))[0] ?? ''; ?>
                <input type="hidden" name="client_id" value="<?= $a_client ?>">
                <input type="text" class="form-control" value="<?= escapeHtml($a_client_name) ?>" readonly>
            <?php } else { ?>
                <select class="form-select select2" name="client_id">
                    <option value="0">Every client</option>
                    <?php
                    $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE (client_archived_at IS NULL OR client_id = $a_client)" . clientNotLeadSql($a_client) . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_clients)) { ?>
                        <option value="<?= intval($row['client_id']) ?>"<?= intval($row['client_id']) === $a_client ? ' selected' : '' ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            <?php } ?>
        </div>
        <?php if ($a_agents) { ?>
            <small class="text-secondary"><?= isset($announcement_client_fixed) ? 'Along the top of their pages and tickets' : 'Every client is along the top of every page' ?></small>
        <?php } ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Show from</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-calendar-plus"></i></span>
            <input type="datetime-local" class="form-control" name="starts_at" value="<?= escapeHtml($a_local($a['announcement_starts_at'] ?? '')) ?>">
        </div>
        <small class="text-secondary">Empty is straight away</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Until</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-calendar-times"></i></span>
            <input type="datetime-local" class="form-control" name="ends_at" value="<?= escapeHtml($a_local($a['announcement_ends_at'] ?? '')) ?>">
        </div>
        <small class="text-secondary">Empty is until you archive it</small>
    </div>
</div>
