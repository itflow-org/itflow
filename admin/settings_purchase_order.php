<?php

/*
 * Admin > Settings > Purchase orders - how they are numbered, and whether one
 * needs approving before it is ordered (functions/purchase_orders.php).
 * Saved by edit_purchase_order_settings (admin/post/settings_purchase_order.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('purchase_order');

if (!poReady()) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-database me-2"></i>Purchase orders need the database update - run it from Maintenance > Update first.</div>
<?php
    require_once "../includes/footer.php";
    exit;
}

$po_settings = poSettings();

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('numbering', 'Numbering', 'Each purchase order gets the next number, after its prefix.') ?>
                <?= settingsNumberingFields('purchase_order', $po_settings['prefix'], $po_settings['next']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('approval', 'Approval', 'A purchase order can need approving by someone with full Purchasing access, or an admin, before it is ordered. Give roles Purchasing in Admin > Roles.') ?>
                <?= settingsSwitch('config_po_approval', $po_settings['approval'] ? 1 : 0, ['label' => 'Purchase orders need approval',
                    'help' => 'Off, a purchase order is marked ordered or emailed to the vendor straight from its draft. On, one over the amount below - or with a line that has no cost - goes to the approvers first. Whoever made it can\'t approve it, unless they are an admin or nobody else can.']) ?>
                <?= settingsInput('config_po_approval_over', $po_settings['approval_over'] > 0 ? number_format($po_settings['approval_over'], 2, '.', '') : '', ['icon' => 'fa-dollar-sign', 'inputmode' => 'decimal',
                    'label' => 'Approve purchase orders over', 'placeholder' => '0.00', 'help' => 'Its total, shipping included. Empty or 0 - every purchase order needs approving.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_purchase_order_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
