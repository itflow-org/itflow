<?php

/*
 * Credential quick look - opened from the credentials list, assets,
 * contacts, vendors, licenses, the client overview and search. The login
 * (username, the password on asking, the 2FA code, where it opens), how old
 * the password is and who last looked at it, what it belongs to and is
 * linked to, the notes, the share links still open, and its history.
 * Anyone who may see credentials gets this; changing one takes edit rights.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential');

$credential_id = intval($_GET['id'] ?? 0);
$credential = credentialRow($credential_id);
if (!$credential) {
    echo json_encode(['error' => 'That credential no longer exists.']);
    exit;
}
$client_id = intval($credential['credential_client_id']);
enforceClientAccess();

$credential_name = escapeHtml($credential['credential_name']);
$credential_archived = !empty($credential['credential_archived_at']);
$can_edit = credentialCan(2) && !$credential_archived;
// Making a share link is a support action as well as a credential one (agent/ajax.php share_generate_link)
$can_share = !$credential_archived && lookupUserPermission('module_support') >= 2;
$age = credentialAge($credential['credential_password_changed_at']);
$last_viewed = credentialLastViewed($credential_id);
$links = credentialLinks([$credential_id])[$credential_id];
$shares = credentialShares([$credential_id])[$credential_id];
$history = credentialHistory($credential_id, 8);
$tags = tagsForRecord('credential', $credential_id);
$has_otp = trim((string) $credential['credential_otp_secret']) !== '';
$link_types = linkTypesFor('credential');

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= credentialTile($credential) ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $credential_name ?></span>
                <?php if (intval($credential['credential_favorite'])) { ?><i class="fas fa-star text-warning small" title="Starred"></i><?php } ?>
                <?php if ($credential_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <span><?= escapeHtml($credential['client_name']) ?></span>
                <?php if ($credential['credential_description']) { ?><span><?= escapeHtml($credential['credential_description']) ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <!-- The login -->
    <dl class="itflow-asset-dl itflow-credential-dl mb-0">
        <dt>Username</dt>
        <dd class="d-flex align-items-center min-w-0"><?= credentialUsernameHtml(credentialUsername($credential), 'None') ?></dd>
        <dt>Password</dt>
        <dd><?= credentialPasswordHtml($credential_id) ?></dd>
        <?php if ($has_otp) { ?>
            <dt>2FA code</dt>
            <dd><?= credentialOtpHtml($credential_id, true) ?></dd>
        <?php } ?>
        <?php if ($credential['credential_uri'] || $credential['credential_uri_2']) { ?>
            <dt>Opens at</dt>
            <dd class="min-w-0">
                <?php foreach (array_filter([$credential['credential_uri'], $credential['credential_uri_2']]) as $uri) { ?>
                    <div class="text-truncate"><?= credentialUriHtml($uri, 60) ?></div>
                <?php } ?>
            </dd>
        <?php } ?>
    </dl>

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3 small text-body-secondary">
        <?= credentialAgePill($age) ?>
        <?php if ($last_viewed) { ?>
            <span title="<?= escapeHtml($last_viewed['log_created_at']) ?>"><i class="far fa-eye me-1"></i>Last looked at by <?= escapeHtml($last_viewed['user_name'] ?: 'someone') ?>, <?= timeAgo($last_viewed['log_created_at']) ?></span>
        <?php } ?>
        <?php if ($tags) { ?><span><?= tagPills('credential', $credential_id, $tags, "credentials.php?client_id=$client_id&", false) ?></span><?php } ?>
    </div>

    <!-- What it belongs to and is linked to -->
    <?php if ($credential['asset_id'] || $credential['contact_id'] || $links || ($can_edit && $link_types)) { ?>
        <div class="d-flex align-items-center mt-3 mb-1">
            <span class="small text-body-secondary fw-semibold text-uppercase me-auto">For</span>
            <?php if ($can_edit && $link_types) { ?>
                <a class="small ajax-modal" href="#" data-modal-url="modals/link/link.php?from=credential&id=<?= $credential_id ?>&to=vendor"><i class="fas fa-link me-1"></i>Link a vendor, license or service</a>
            <?php } ?>
        </div>
        <ul class="list-unstyled mb-0 itflow-links">
            <?php if ($credential['asset_id']) { ?>
                <li class="itflow-link-row">
                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($credential['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($credential['asset_type']) ?>"></i></span>
                    <div class="min-w-0 flex-grow-1"><div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= intval($credential['asset_id']) ?>"><?= escapeHtml($credential['asset_name']) ?></a></div><div class="small text-body-secondary">Asset</div></div>
                </li>
            <?php } ?>
            <?php if ($credential['contact_id']) { ?>
                <li class="itflow-link-row">
                    <?= contactAvatar($client_id, $credential['contact_id'], $credential['contact_name'], $credential['contact_photo'], 'sm') ?>
                    <div class="min-w-0 flex-grow-1"><div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/contact/contact.php?id=<?= intval($credential['contact_id']) ?>"><?= escapeHtml($credential['contact_name']) ?></a></div><div class="small text-body-secondary">Contact<?= $credential['contact_title'] ? ' · ' . escapeHtml($credential['contact_title']) : '' ?></div></div>
                </li>
            <?php } ?>
            <?php foreach ($links as [$type, $id, $name]) { ?>
                <li class="itflow-link-row">
                    <?php if ($type === 'vendor') { ?>
                        <?= vendorTile($name, 'sm') ?>
                        <div class="min-w-0 flex-grow-1"><div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/vendor/vendor.php?id=<?= $id ?>"><?= escapeHtml($name) ?></a></div><div class="small text-body-secondary">Vendor</div></div>
                    <?php } elseif ($type === 'software') { ?>
                        <span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw fa-cube"></i></span>
                        <div class="min-w-0 flex-grow-1"><div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/software/software.php?id=<?= $id ?>"><?= escapeHtml($name) ?></a></div><div class="small text-body-secondary">License</div></div>
                    <?php } else { ?>
                        <span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw fa-stream"></i></span>
                        <div class="min-w-0 flex-grow-1"><div class="d-flex"><a class="text-truncate" href="<?= escapeHtml(serviceUrl($id, $client_id)) ?>"><?= escapeHtml($name) ?></a></div><div class="small text-body-secondary">Service</div></div>
                    <?php } ?>
                    <?php if ($can_edit) { ?>
                        <a class="itflow-link-remove" href="post.php?unlink_item&from=credential&id=<?= $credential_id ?>&to=<?= $type ?>&other=<?= $id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Unlink"><i class="fas fa-unlink"></i></a>
                    <?php } ?>
                </li>
            <?php } ?>
            <?php if (!$credential['asset_id'] && !$credential['contact_id'] && !$links) { ?>
                <li class="small text-body-secondary">Not linked to anything yet.</li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if (trim((string) $credential['credential_note']) !== '') { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Notes</div>
        <div class="itflow-credential-note small"><?= nl2br(escapeHtml($credential['credential_note'])) ?></div>
    <?php } ?>

    <!-- Share links still open -->
    <?php if ($shares) { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Shared</div>
        <ul class="list-unstyled mb-0 itflow-links">
            <?php foreach ($shares as $share) {
                $views = intval($share['item_views']);
                $limit = intval($share['item_view_limit']); ?>
                <li class="itflow-link-row">
                    <span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw fa-share-alt"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-truncate"><?= escapeHtml($share['item_recipient'] ?: 'A share link') ?></div>
                        <div class="small text-body-secondary"><?= $views ?><?= $limit ? " of $limit" : '' ?> view<?= ($limit ?: $views) === 1 ? '' : 's' ?>, expires <?= timeAgo($share['item_expire_at']) ?></div>
                    </div>
                    <a class="btn btn-sm btn-link text-danger confirm-link" href="post.php?deactivate_shared_item=<?= intval($share['item_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Turn off this link"><i class="fas fa-fw fa-unlink"></i></a>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <!-- History -->
    <?php if ($history) { ?>
        <details class="mt-3">
            <summary class="small text-body-secondary fw-semibold text-uppercase">History</summary>
            <ul class="list-unstyled small mt-2 mb-0">
                <?php foreach ($history as $entry) { ?>
                    <li class="d-flex gap-2 py-1 border-bottom">
                        <span class="flex-grow-1 min-w-0"><?= escapeHtml($entry['log_description']) ?></span>
                        <span class="text-body-secondary text-nowrap" title="<?= escapeHtml($entry['log_created_at']) ?>"><?= timeAgo($entry['log_created_at']) ?></span>
                    </li>
                <?php } ?>
            </ul>
        </details>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($can_share) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal me-auto" data-modal-url="modals/credential/credential_share.php?id=<?= $credential_id ?>"><i class="fas fa-share-alt me-2"></i>Share</button>
    <?php } ?>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/credential/credential_rotate.php?id=<?= $credential_id ?>"><i class="fas fa-sync-alt me-2"></i>Change password</button>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
