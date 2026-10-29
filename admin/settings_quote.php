<?php

/*
 * Admin > Settings > Quotes - how quotes are numbered, the footer under each
 * one, and who hears when a client accepts or declines one online. Saved by
 * edit_quote_settings (admin/post/settings_quote.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('quote');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('numbering', 'Numbering', 'Each quote gets the next number, after its prefix.') ?>
                <?= settingsNumberingFields('quote', $config_quote_prefix, $config_quote_next_number) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('footer', 'Footer', 'Under every quote - on the page, the PDF and the client portal. Terms, how long a quote holds, what happens on accepting it.') ?>
                <?= settingsTextarea('config_quote_footer', $config_quote_footer, ['rows' => 5, 'maxlength' => 65000, 'help' => 'Plain text - each new line is kept.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('answers', 'Answers', 'A client can accept or decline a quote online - from the client portal, or the link in the email it was sent with.') ?>
                <?= settingsInput('config_quote_notification_email', $config_quote_notification_email, ['icon' => 'fa-bell', 'type' => 'email', 'maxlength' => 200,
                    'placeholder' => 'sales@company.com', 'help' => 'A shared mailbox or list works best. Empty, nobody is emailed - the quote still shows the answer.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_quote_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
