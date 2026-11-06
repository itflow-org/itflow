<?php

/*
 * Add Instance and Edit Instance (Admin > Managed Instances) - one window for both: what it is
 * and whose, then what this manager sets for it - seat limit and updates, white label, and
 * whether its people can use it. ?id= edits; without one it's new. Posts add_managed_instance
 * or edit_managed_instance (admin/post/managed_instances.php).
 */

require_once '../../includes/modal_header.php';

enforceAdminPermission();

$instance = managerEnabled() && isset($_GET['id']) ? managedInstanceRow(intval($_GET['id'])) : null;
$is_new = $instance === null;

ob_start();

if (!managerEnabled()) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-server me-2"></i>Instance</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">Managing other ITFlow installs is off in this install's config.php.</div>
<?php } elseif (!$is_new && !empty($instance['managed_instance_archived_at'])) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-server me-2"></i><?= escapeHtml($instance['managed_instance_name']) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">It has been removed, so there's nothing to set for it.</div>
<?php } elseif (isset($_GET['id']) && $is_new) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-server me-2"></i>Instance</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That instance is gone - it may have been deleted.</div>
<?php } else {

    $form = $instance ?? [
        'managed_instance_id' => 0,
        'managed_instance_name' => '',
        'managed_instance_client_id' => 0,
        'managed_instance_notes' => '',
        'managed_instance_seat_limit' => 0,
        'managed_instance_update_policy' => 'manual',
        'managed_instance_update_delay_days' => 7,
        'managed_instance_white_label_key' => '',
        'managed_instance_mode' => 'active',
        'managed_instance_mode_message' => '',
    ];
    $form_id = 'managedInstanceForm' . intval($form['managed_instance_id']);
    $value = function ($key) use ($form) {
        return escapeHtml((string) ($form[$key] ?? ''));
    };
    $client_id = intval($form['managed_instance_client_id']);
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-<?= $is_new ? 'plus' : 'server' ?> me-2"></i><?= $is_new ? 'Add Instance' : $value('managed_instance_name') ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if (!$is_new) { ?>
        <input type="hidden" name="managed_instance_id" value="<?= intval($form['managed_instance_id']) ?>">
    <?php } ?>

    <div class="modal-body">

        <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-server me-2 d-none d-lg-inline"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Limits"><i class="fas fa-fw fa-sliders-h me-2 d-none d-lg-inline"></i>Seats &amp; updates</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Access"><i class="fas fa-fw fa-door-open me-2 d-none d-lg-inline"></i>Access</a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- What it is, and whose -->
            <div class="tab-pane fade show active" id="<?= $form_id ?>Details">
                <div class="mb-3">
                    <label class="form-label">Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-server"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Ravenwood Dental ITFlow" maxlength="200" value="<?= $value('managed_instance_name') ?>" required <?= $is_new ? 'autofocus' : '' ?>>
                    </div>
                    <div class="form-text">What it's called here - its own company name shows from its reports.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Client</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                        <select class="form-select select2" name="client_id">
                            <option value="0">- None - one of your own -</option>
                            <?php
                            $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL OR client_id = $client_id ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients)) { ?>
                                <option value="<?= intval($row['client_id']) ?>" <?= intval($row['client_id']) === $client_id ? 'selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">Whose it is, if it's a client's - its notifications and audit entries go on that client.</div>
                </div>

                <div class="mb-0">
                    <label class="form-label">Notes</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-sticky-note"></i></span>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Where it's hosted, who to call, the plan they're on..."><?= $value('managed_instance_notes') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Seats, updates and white label -->
            <div class="tab-pane fade" id="<?= $form_id ?>Limits">
                <div class="mb-3">
                    <label class="form-label">Seat limit</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-users"></i></span>
                        <input type="number" class="form-control" name="seat_limit" min="0" max="100000" step="1" placeholder="No limit" value="<?= intval($form['managed_instance_seat_limit']) ?: '' ?>">
                        <span class="input-group-text">agents</span>
                    </div>
                    <div class="form-text">How many active agents it can have - invites count until they're used. Left empty, its own config.php decides. It can't be changed there while it's set here.</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-7">
                        <label class="form-label">Updates</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-download"></i></span>
                            <select class="form-select" name="update_policy">
                                <option value="manual" <?= $form['managed_instance_update_policy'] !== 'auto' ? 'selected' : '' ?>>By hand, from here</option>
                                <option value="auto" <?= $form['managed_instance_update_policy'] === 'auto' ? 'selected' : '' ?>>Automatically</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label">Wait</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-hourglass-half"></i></span>
                            <input type="number" class="form-control" name="update_delay_days" min="0" max="90" step="1" value="<?= intval($form['managed_instance_update_delay_days']) ?>">
                            <span class="input-group-text">days</span>
                        </div>
                    </div>
                    <div class="col-12 form-text mt-1">Its own admins can't update it either way. Automatically, a new version goes on once it's been out that long - a security release straight away.</div>
                </div>

                <div class="mb-0">
                    <label class="form-label">White-label key</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                        <textarea class="form-control font-monospace small" name="white_label_key" rows="3" placeholder="Paste a key from the ITFlow project"><?= $value('managed_instance_white_label_key') ?></textarea>
                    </div>
                    <div class="form-text">Takes "Powered by ITFlow" off it - its admins can't change it while it's set here. Left empty, white label is theirs to set.</div>
                </div>
            </div>

            <!-- Whether its people can use it -->
            <div class="tab-pane fade" id="<?= $form_id ?>Access">
                <div class="mb-3">
                    <label class="form-label d-block">Mode</label>
                    <div class="d-grid gap-2">
                        <?php foreach ([
                            'active' => ['fa-check-circle', 'success', 'Everything works as normal.'],
                            'read_only' => ['fa-lock', 'warning', 'Its people can sign in, look, export and download - nothing can be changed. Mail still goes out.'],
                            'suspended' => ['fa-ban', 'danger', 'Nobody can use it - agents, the portal, guest links and the API get a notice. Backups and updates still run.'],
                        ] as $mode_key => [$icon, $tone, $text]) { ?>
                            <input type="radio" class="btn-check" name="mode" value="<?= $mode_key ?>" id="<?= $form_id . $mode_key ?>" <?= $form['managed_instance_mode'] === $mode_key ? 'checked' : '' ?>>
                            <label class="btn btn-outline-<?= $tone ?> text-start d-flex gap-2 align-items-start" for="<?= $form_id . $mode_key ?>">
                                <i class="fas fa-fw <?= $icon ?> mt-1"></i>
                                <span><strong><?= escapeHtml(MANAGED_MODES[$mode_key]) ?></strong><span class="d-block small"><?= escapeHtml($text) ?></span></span>
                            </label>
                        <?php } ?>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label">Message</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-comment"></i></span>
                        <input type="text" class="form-control" name="mode_message" maxlength="500" placeholder="e.g. Your subscription has ended - call us to carry on" value="<?= $value('managed_instance_mode_message') ?>">
                    </div>
                    <div class="form-text">What its people see while it's read-only or suspended, with your company's email and phone. Left empty, a plain notice.</div>
                </div>
            </div>

        </div>

        <?php if ($is_new) { ?>
            <div class="small text-body-secondary mt-3"><i class="fas fa-fw fa-info-circle me-1"></i>Next you get a code to paste into that ITFlow's <strong>Admin &gt; Managed By</strong> page.</div>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="<?= $is_new ? 'add_managed_instance' : 'edit_managed_instance' ?>" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= $is_new ? 'Add' : 'Save' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
