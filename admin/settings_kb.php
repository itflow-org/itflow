<?php

/*
 * Admin > Settings > Knowledge Base - whether the public help center is open:
 * the articles anyone can read (guest/kb.php), for anyone with the link, no
 * sign-in needed (functions/kb.php). Saved by edit_kb_settings
 * (admin/post/settings_kb.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('kb');

if (!isset($config_kb_public_enable)) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-database me-2"></i>The Knowledge Base needs the database update - run it from Maintenance > Update first.</div>
<?php
    require_once "../includes/footer.php";
    exit;
}

$kb_public_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM kb_articles WHERE " . kbPublicSql()))[0]);
$kb_public_link = "https://$config_base_url/guest/kb.php";

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('public', 'Public help center', 'The articles set to be read by anyone, on a page of their own with your logo - search and categories included. No sign-in needed: anyone with the link can read them, so keep anything about a client out of them.') ?>
                <?= settingsSwitch('config_kb_public_enable', $config_kb_public_enable, ['label' => 'Public help center',
                    'help' => 'Off, the page says there is nothing here, and the articles are only in your clients\' portals. ' . $kb_public_count . ' published article' . ($kb_public_count === 1 ? ' is' : 's are') . ' set for anyone to read.']) ?>
                <div class="mt-3">
                    <label class="form-label">Link</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" value="<?= escapeHtml($kb_public_link) ?>" readonly aria-label="The public help center's link">
                        <button type="button" class="btn btn-outline-secondary clipboardjs" title="Copy the link" data-clipboard-text="<?= escapeHtml($kb_public_link) ?>"><i class="far fa-fw fa-copy"></i></button>
                        <a class="btn btn-outline-secondary" href="<?= escapeHtml($kb_public_link) ?>" target="_blank" rel="noopener" title="Open it"><i class="fas fa-fw fa-external-link-alt"></i></a>
                    </div>
                    <div class="form-text">Put it on your website, or in your email signature. The client portal's sign-in page links to it while it's on.</div>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_kb_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
