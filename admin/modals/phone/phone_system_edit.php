<?php

/*
 * Admin > Phone Systems - one phone system's window: Add (no id) or Edit. Which kind it is is
 * picked when it is added and stays. Its address is only for links into it - ITFlow never
 * connects to it; it sends ITFlow what happens, to the addresses its Setup window shows. Saved by
 * save_phone_system (admin/post/phone.php).
 */

require_once '../../includes/modal_header.php';

$phone_system_id = intval($_GET['id'] ?? 0);
$row = $phone_system_id ? phoneSystemRow($phone_system_id) : null;
if ($phone_system_id && !$row) {
    echo json_encode(['error' => 'That phone system no longer exists.']);
    exit;
}
if ($row && !phoneKind($row)) {
    echo json_encode(['error' => 'This ITFlow doesn\'t know that phone system\'s kind.']);
    exit;
}

$kinds = phoneSystemKinds();
$kind_key = $row ? (string) $row['phone_system_kind'] : (string) array_key_first($kinds);
$kind = $kinds[$kind_key];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="<?= escapeHtml($kind['icon']) ?> fa-fw me-2"></i><?= $row ? '<strong>' . escapeHtml(phoneSystemName($row)) . '</strong>' : 'New Phone System' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="phone_system_id" value="<?= $phone_system_id ?>">

    <div class="modal-body">

        <?php if ($row) { ?>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="active" value="1" id="phoneSystemActive" <?= intval($row['phone_system_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="phoneSystemActive">On - what it sends is kept</label>
                </div>
                <div class="form-text ps-5">Off, calls aren't kept and caller ID gets no names from ITFlow.</div>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="phoneSystemKind">Phone system <?= $row ? '' : '<strong class="text-danger">*</strong>' ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-shapes"></i></span>
                <select class="form-select select2" name="kind" id="phoneSystemKind" <?= $row ? 'disabled' : '' ?>>
                    <?php foreach ($kinds as $key => $info) { ?>
                        <option value="<?= escapeHtml($key) ?>" <?= $key === $kind_key ? 'selected' : '' ?><?= selectOptionData(['icon' => $info['icon'], 'description' => $info['about']]) ?>><?= escapeHtml($info['name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php if ($row) { ?><div class="form-text">Which kind it is stays - add another phone system for another one.</div><?php } ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="phoneSystemName">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="name" id="phoneSystemName" maxlength="100" required value="<?= escapeHtml($row ? $row['phone_system_name'] : $kind['name']) ?>" placeholder="What you call it">
            </div>
            <div class="form-text">What calls and the audit log call it. Two of the same kind need names of their own.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="phoneSystemWebUrl">Address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-globe"></i></span>
                <input type="url" class="form-control" name="web_url" id="phoneSystemWebUrl" maxlength="500" spellcheck="false" placeholder="<?= escapeHtml($kind['web_placeholder']) ?>" value="<?= escapeHtml($row['phone_system_web_url'] ?? '') ?>">
            </div>
            <div class="form-text">Where you sign in to it, for links to it and to each call's page there with its recording. ITFlow never connects to it.</div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="save_phone_system" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i><?= $row ? 'Save' : 'Create' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>
<?php
require_once '../../../includes/modal_footer.php';
