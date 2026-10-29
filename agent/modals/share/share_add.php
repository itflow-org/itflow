<?php

/*
 * Share something with someone outside - a login, a document or a file, a
 * secure note typed here, or a request for them to send us a login. One
 * window for all of them (functions/share.php, js/share_add.js), opened with
 * ?type=Credential|Document|File&id=N, or ?type=Note|Request&client_id=N.
 *
 * The link is made by agent/ajax.php share_create and shown once, with a QR
 * code - its secret is not kept, so it cannot be shown again later.
 */

require_once '../../../includes/modal_header.php';

$type = (string) ($_GET['type'] ?? '');
$related_id = intval($_GET['id'] ?? 0);

if (!shareCanMake($type)) {
    exit(json_encode(['error' => "You don't have access to share that."]));
}

$has_otp = false;
$item_label = '';
if ($type === 'Credential') {
    $credential = credentialRow($related_id);
    if (!$credential) {
        exit(json_encode(['error' => 'That credential no longer exists.']));
    }
    $client_id = intval($credential['credential_client_id']);
    $item_label = $credential['credential_name'];
    $has_otp = credentialOtpSeed($credential['credential_otp_secret']) !== '' && credentialOtpSeed($credential['credential_otp_secret']) !== false;
} elseif ($type === 'Document') {
    $document = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_client_id FROM documents WHERE document_id = $related_id LIMIT 1"));
    if (!$document) {
        exit(json_encode(['error' => 'That document no longer exists.']));
    }
    $client_id = intval($document['document_client_id']);
    $item_label = $document['document_name'];
} elseif ($type === 'File') {
    $file = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name, file_client_id FROM files WHERE file_id = $related_id LIMIT 1"));
    if (!$file) {
        exit(json_encode(['error' => 'That file no longer exists.']));
    }
    $client_id = intval($file['file_client_id']);
    $item_label = $file['file_name'];
} else {
    $related_id = 0;
    $client_id = intval($_GET['client_id'] ?? 0);
}

if (!$client_id || !getFieldById('clients', $client_id, 'client_name')) {
    exit(json_encode(['error' => 'That client no longer exists.']));
}
enforceClientAccess();

$uid = 'shareAdd' . preg_replace('/[^A-Za-z0-9]/', '', randomString(8));
$defaults = shareDefaults($type);
$mail_ready = shareMailReady();
$must_protect = shareNeedsProtection($type);
$suggested_passphrase = generateReadablePassword();

// Who it might go to - the client's people with an email, or anyone typed in
$recipients = [];
$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_title, contact_photo, contact_client_id FROM contacts
    WHERE contact_client_id = $client_id AND contact_email <> '' AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $recipients[] = $row;
}

$titles = [
    'Credential' => ['fa-key', 'Share <strong>' . escapeHtml($item_label) . '</strong>'],
    'Document' => ['fa-file-alt', 'Share <strong>' . escapeHtml($item_label) . '</strong>'],
    'File' => ['fa-paperclip', 'Share <strong>' . escapeHtml($item_label) . '</strong>'],
    'Note' => ['fa-user-secret', 'Send a secure note'],
    'Request' => ['fa-inbox', 'Ask for a login'],
];
[$title_icon, $title_html] = $titles[$type];
$intros = [
    'Credential' => 'They get a link to the username and password' . ($has_otp ? ', and the 2FA code if you include it' : '') . '. What they see is fixed now - changing the credential later does not change the link.',
    'Document' => 'They get a link to read the document as it is when they open it, and print it.',
    'File' => 'They get a link to download the file.',
    'Note' => 'Type a password, a recovery key or anything else private. It is encrypted, and only the link opens it - it is not saved anywhere else.',
    'Request' => 'They get a form for the website, username, password and 2FA secret. What they send is encrypted, and you save it to the client\'s credentials from Shared Links.',
];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw <?= $title_icon ?> me-2"></i><?= $title_html ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form autocomplete="off" data-share-add id="<?= $uid ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="type" value="<?= escapeHtml($type) ?>">
    <input type="hidden" name="id" value="<?= $related_id ?>">

    <div class="modal-body" data-share-form>
        <p class="small text-body-secondary"><?= $intros[$type] ?></p>
        <div class="alert alert-danger py-2 small" role="alert" data-share-error hidden></div>

        <?php if ($type === 'Note') { ?>
            <div class="mb-3">
                <label class="form-label" for="<?= $uid ?>Name">Title</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-heading"></i></span>
                    <input type="text" class="form-control" id="<?= $uid ?>Name" name="name" maxlength="200" placeholder="Wi-Fi password, recovery key, new starter's login">
                </div>
                <div class="form-text">Shown to them, and in Shared Links - keep the secret itself out of it.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="<?= $uid ?>Text">Secret <strong class="text-danger">*</strong></label>
                <textarea class="form-control font-monospace" id="<?= $uid ?>Text" name="text" rows="5" maxlength="<?= SHARE_NOTE_MAX ?>" required></textarea>
            </div>
        <?php } elseif ($type === 'Request') { ?>
            <div class="mb-3">
                <label class="form-label" for="<?= $uid ?>Name">What do you need? <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                    <input type="text" class="form-control" id="<?= $uid ?>Name" name="name" maxlength="200" placeholder="Microsoft 365 global admin, domain registrar, ISP portal" required>
                </div>
                <div class="form-text">They see this, and it becomes the credential's name when you save it.</div>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="<?= $uid ?>Recipient"><?= $type === 'Request' ? 'Ask' : 'Send to' ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                <select class="form-select select2" id="<?= $uid ?>Recipient" name="recipient" data-placeholder="Pick a contact or type an email" data-tags="true" data-share-recipient>
                    <option value=""></option>
                    <?php foreach ($recipients as $recipient) { ?>
                        <option value="<?= escapeHtml($recipient['contact_email']) ?>"<?= selectOptionContact($recipient['contact_client_id'], $recipient['contact_name'], $recipient['contact_photo'], $recipient['contact_email'], $recipient['contact_id']) ?>><?= escapeHtml($recipient['contact_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php if ($mail_ready) { ?>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="send" value="1" id="<?= $uid ?>Send" data-share-send disabled>
                    <label class="form-check-label" for="<?= $uid ?>Send">Email them the link</label>
                </div>
            <?php } else { ?>
                <div class="form-text">Mail is not set up, so copy the link and send it yourself.</div>
            <?php } ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="<?= $type === 'Request' ? 'col-12' : 'col-sm-6' ?>">
                <label class="form-label" for="<?= $uid ?>Expires">Works for</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-clock"></i></span>
                    <select class="form-select" id="<?= $uid ?>Expires" name="expires">
                        <?php foreach (shareExpiryOptions() as $hours => $label) { ?>
                            <option value="<?= $hours ?>" <?= $hours === $defaults['expires'] ? 'selected' : '' ?>><?= $label ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <?php if ($type !== 'Request') { ?>
                <div class="col-sm-6">
                    <label class="form-label" for="<?= $uid ?>Views">Can be opened</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                        <select class="form-select" id="<?= $uid ?>Views" name="views">
                            <?php foreach (shareViewOptions() as $views => $label) { ?>
                                <option value="<?= $views ?>" <?= $views === $defaults['views'] ? 'selected' : '' ?>><?= $label ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
        </div>

        <label class="form-label">Before it opens</label>
        <div class="btn-group w-100 mb-2" role="group" data-share-protect>
            <input class="btn-check" type="radio" name="protect" value="" id="<?= $uid ?>ProtectNone" <?= $must_protect ? 'disabled' : 'checked' ?>>
            <label class="btn btn-outline-secondary" for="<?= $uid ?>ProtectNone" <?= $must_protect ? 'title="Your sharing policy needs a passphrase or a code for this"' : '' ?>><i class="fas fa-unlock me-1"></i>Nothing</label>
            <input class="btn-check" type="radio" name="protect" value="passphrase" id="<?= $uid ?>ProtectPassphrase" <?= $must_protect ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary" for="<?= $uid ?>ProtectPassphrase"><i class="fas fa-key me-1"></i>Passphrase</label>
            <input class="btn-check" type="radio" name="protect" value="code" id="<?= $uid ?>ProtectCode" <?= $mail_ready ? '' : 'disabled' ?>>
            <label class="btn btn-outline-secondary" for="<?= $uid ?>ProtectCode" <?= $mail_ready ? '' : 'title="Mail is not set up"' ?>><i class="fas fa-envelope-open-text me-1"></i>Emailed code</label>
        </div>
        <div class="mb-3" data-share-passphrase hidden>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                <input type="text" class="form-control font-monospace" name="passphrase" minlength="<?= SHARE_PASSPHRASE_MIN ?>" maxlength="200" value="<?= escapeHtml($suggested_passphrase) ?>" autocomplete="off" spellcheck="false" aria-label="Passphrase">
                <button class="btn btn-outline-secondary" type="button" title="Make up another" data-share-generate><i class="fas fa-fw fa-magic"></i></button>
            </div>
            <div class="form-text">Tell them by phone or text - never in the same email as the link.</div>
        </div>
        <div class="form-text mb-3" data-share-code hidden>When they open the link, a 6-digit code is emailed to the address above, so a forwarded link is no use to anyone else.</div>
        <div class="form-text mb-3" data-share-none <?= $must_protect ? 'hidden' : '' ?>><?= in_array($type, ['Credential', 'Note']) ? 'Anyone with the link can open it - a passphrase or code is safer for a password.' : 'Anyone with the link can open it.' ?></div>

        <?php if ($type === 'Credential' && $has_otp) { ?>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="otp" value="1" id="<?= $uid ?>Otp" checked>
                <label class="form-check-label" for="<?= $uid ?>Otp">Include the 2FA code <span class="small text-body-secondary">- they see the current code, never the secret</span></label>
            </div>
        <?php } ?>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" name="notify" value="1" id="<?= $uid ?>Notify" checked>
            <label class="form-check-label" for="<?= $uid ?>Notify"><?= $type === 'Request' ? 'Tell me when they send it' : 'Tell me when it is opened' ?></label>
        </div>

        <label class="form-label" for="<?= $uid ?>Note">Note for them</label>
        <textarea class="form-control" id="<?= $uid ?>Note" name="note" rows="2" maxlength="1000" placeholder="<?= $type === 'Request' ? 'What it is for, and anything they should know' : 'What it is for, and to change the password once they are in' ?>"></textarea>
    </div>

    <div class="modal-body" data-share-done hidden>
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-check-circle fa-2x text-success"></i>
            <div>
                <div class="fw-semibold">The link is ready<span data-share-copied hidden>, and copied</span></div>
                <div class="small text-body-secondary" data-share-summary></div>
            </div>
        </div>
        <label class="form-label" for="<?= $uid ?>Link">Link</label>
        <div class="input-group mb-1">
            <span class="input-group-text"><i class="fas fa-fw fa-link"></i></span>
            <input type="text" class="form-control" id="<?= $uid ?>Link" readonly data-share-link>
            <button class="btn btn-outline-secondary" type="button" title="Copy link" data-share-copy="link"><i class="far fa-fw fa-copy"></i></button>
        </div>
        <div class="form-text mb-3">Shown once - copy it now. It cannot be shown again; make another if it is lost.</div>
        <div data-share-passphrase-done hidden>
            <label class="form-label" for="<?= $uid ?>PassphraseDone">Passphrase</label>
            <div class="input-group mb-1">
                <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                <input type="text" class="form-control font-monospace" id="<?= $uid ?>PassphraseDone" readonly data-share-passphrase-value>
                <button class="btn btn-outline-secondary" type="button" title="Copy passphrase" data-share-copy="passphrase"><i class="far fa-fw fa-copy"></i></button>
            </div>
            <div class="form-text mb-3">Give it to them another way - by phone or text, not with the link.</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-share-qr-toggle><i class="fas fa-qrcode me-2"></i>QR code</button>
        <div class="text-center mt-3 p-3 bg-white rounded border" data-share-qr hidden></div>
    </div>

    <div class="modal-footer">
        <button type="submit" class="btn btn-primary text-bold" data-share-make><i class="fas fa-<?= $type === 'Request' ? 'paper-plane' : 'link' ?> me-2"></i><?= $type === 'Request' ? 'Make the request' : 'Make the link' ?></button>
        <button type="button" class="btn btn-outline-secondary" data-share-again hidden><i class="fas fa-redo me-2"></i>Make another</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><span data-share-close>Cancel</span></button>
    </div>
</form>

<script src="/agent/js/share_add.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
