<?php

/*
 * Admin > Settings > Quotes - how quotes are numbered, the footer under each
 * one, who hears when a client accepts or declines one online, and how a
 * line's price comes from its cost (functions/quote_pricing.php). Saved by
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

            <?php if (quotePricingReady()) {
                $pricing = quotePricingSettings(); ?>
                <?= settingsSectionStart('pricing', 'Pricing', 'A quote line can carry what it costs you, and its price can come from that cost - a markup or a margin. Only agents see the cost, in a line\'s Edit window and the quote\'s Margin - never the PDF, the guest link or the portal.') ?>
                    <?= settingsSelect('config_quote_price_method', $pricing['method'], ['markup' => 'Markup - a % added to the cost', 'margin' => 'Margin - a % of the price'], ['icon' => 'fa-calculator',
                        'help' => 'What a line is priced by when a cost is typed in. Markup 25% on a cost of 80.00 is 100.00; margin 25% on 80.00 is 106.67.']) ?>
                    <?= settingsInput('config_quote_price_percent', quotePricePercentText($pricing['percent']), ['icon' => 'fa-percent', 'inputmode' => 'decimal', 'maxlength' => 8,
                        'help' => 'The % a line starts with.']) ?>
                    <?= settingsInput('config_quote_price_presets', implode(', ', array_map('quotePricePercentText', $pricing['presets'])), ['icon' => 'fa-hand-pointer', 'maxlength' => 100,
                        'placeholder' => '15, 20, 25, 35', 'help' => 'The % buttons in a quote line\'s Edit window, to pick one in a click - up to eight, separated by commas.']) ?>
                <?= settingsSectionEnd() ?>
            <?php } ?>

            <?php if (quoteImagesReady()) { ?>
                <?= settingsSectionStart('pictures', 'Pictures', 'A quote line can have a picture - uploaded, pulled from a link, or taken from its product.') ?>
                    <?= settingsSwitch('config_quote_show_images', quoteImagesShown() ? 1 : 0, ['label' => 'Show pictures on quotes',
                        'help' => 'Beside each line on the quote, the guest link, the client portal and the PDF - and the Picture field in a line\'s Edit window. Off, they are kept but not shown.']) ?>
                <?= settingsSectionEnd() ?>
            <?php } ?>

            <?= settingsSaveBar('edit_quote_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
