<?php

/*
 * Onboarding - billing: who gets the invoices, the client's tax ID, tax rate
 * or exemption, payment reminders - and, for an agent who can bill, the
 * recurring invoice that bills the agreement (client_onboarding_billing). It
 * runs while the contract is active: a contract out to sign has it paused
 * until it is countersigned.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_c = $co_client;
$co_billing_people = [];
$co_billing_now = 0;
$co_sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_billing FROM contacts
    WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_billing DESC, contact_primary DESC, contact_name ASC");
while ($co_person = mysqli_fetch_assoc($co_sql)) {
    $co_billing_people[intval($co_person['contact_id'])] = $co_person['contact_name'] . (trim((string) $co_person['contact_email']) !== '' ? ' - ' . $co_person['contact_email'] : ' - no email');
    if (!$co_billing_now && intval($co_person['contact_billing'])) {
        $co_billing_now = intval($co_person['contact_id']);
    }
}
$co_can_bill = contractBillingReady() && lookupUserPermission('module_sales') >= 2;
$co_contract = $co_can_bill ? clientOnboardingContract($client_id, $co_row) : null;
$co_contract_recurring = $co_contract ? contractRecurringRow($co_contract) : null;
$co_offer_recurring = $co_contract && !$co_contract_recurring && (contractRecurringShouldRun($co_contract) || $co_contract['contract_status'] === 'Draft');

?>
<form action="post.php" method="post" autocomplete="off" data-gs-form data-co-form="billing">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <?php if ($co_billing_people) { ?>
                <?= settingsSelect('billing_contact_id', (string) $co_billing_now, ['0' => '- Nobody in particular -'] + $co_billing_people, ['id' => 'coBillingContact', 'icon' => 'fa-user-tag', 'label' => 'Invoices go to',
                    'help' => 'Marked as their billing contact - invoices, quotes and statements go to them.']) ?>
            <?php } else { ?>
                <div class="itflow-settings-field">
                    <label class="form-label">Invoices go to</label>
                    <div class="small text-body-secondary">Nobody yet - add their people on <a href="<?= clientOnboardingUrl($client_id, 'people') ?>">People</a>.</div>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('tax_id_number', $co_c['client_tax_id_number'] ?? '', ['id' => 'coTaxId', 'icon' => 'fa-balance-scale', 'label' => 'Tax ID', 'maxlength' => 255, 'placeholder' => 'Their tax number, for invoices']) ?>
        </div>
    </div>

    <?php
    $client_tax = $co_c;
    $client_tax_key = 'Onboarding';
    require dirname(__DIR__) . '/modals/client/client_tax_fields.php';
    ?>

    <div class="mb-4">
        <input type="hidden" name="reminders_field" value="1">
        <?= settingsSwitch('reminders', intval($co_c['client_reminders'] ?? 1) ? '1' : '', ['id' => 'coReminders', 'label' => 'Payment reminders and statements',
            'help' => 'Emailed as set in Admin &gt; Settings &gt; Invoice. Off, they get neither - invoices can still be sent by hand.']) ?>
    </div>

    <?php if ($co_can_bill) { ?>
        <h2 class="h6 fw-semibold">The agreement's recurring invoice</h2>
        <?php if (!$co_contract) { ?>
            <p class="small text-body-secondary" data-co-no-contract>There's no agreement to bill yet - start one on <a href="<?= clientOnboardingUrl($client_id, 'agreement') ?>">Agreement</a>, or add a recurring invoice on the client's <a href="/agent/recurring_invoices.php?client_id=<?= $client_id ?>">Recurring Invoices</a>.</p>
        <?php } elseif ($co_contract_recurring) { ?>
            <p class="small" data-co-recurring="<?= intval($co_contract_recurring['recurring_invoice_id']) ?>"><i class="fas fa-check-circle text-success me-1"></i>
                <a href="/agent/recurring_invoice.php?recurring_invoice_id=<?= intval($co_contract_recurring['recurring_invoice_id']) ?>"><?= escapeHtml(billingNumber('recurring', $co_contract_recurring)) ?></a>
                bills <?= escapeHtml($co_contract['contract_name']) ?> - <?= escapeHtml(contractRecurringAmountText($co_contract_recurring)) ?>.</p>
        <?php } elseif (!$co_offer_recurring) { ?>
            <p class="small text-body-secondary"><?= escapeHtml($co_contract['contract_name']) ?> has ended, so it isn't billed - renew it from its page first.</p>
        <?php } else { ?>
            <div class="itflow-gs-optional mb-3" data-co-recurring-form>
                <?= settingsSwitch('recurring', '1', ['id' => 'coRecurring', 'label' => 'Bill ' . $co_contract['contract_name'] . ' with a recurring invoice', 'data' => ['gs-reveal' => 'coRecurringFields'],
                    'help' => $co_contract['contract_status'] === 'Draft' ? 'It is paused until the contract is countersigned and active, and paused again once it ends.' : 'It runs while the contract is active, and is paused once it ends.']) ?>
                <div id="coRecurringFields" class="row g-3 mt-0">
                    <div class="col-sm-6">
                        <?= settingsInput('amount', '', ['id' => 'coAmount', 'icon' => 'fa-dollar-sign', 'label' => 'Amount each time', 'inputmode' => 'decimal', 'placeholder' => '1,500.00']) ?>
                    </div>
                    <div class="col-sm-6">
                        <?= settingsSelect('frequency', 'month', recurringInvoiceFrequencies(), ['id' => 'coFrequency', 'icon' => 'fa-sync-alt', 'label' => 'How often']) ?>
                    </div>
                    <div class="col-sm-6">
                        <?= settingsInput('first', contractRecurringFirstDate($co_contract), ['id' => 'coFirst', 'icon' => 'fa-calendar-day', 'type' => 'date', 'label' => 'First invoice', 'max' => '2999-12-31']) ?>
                    </div>
                    <div class="col-sm-6">
                        <?= settingsSelect('category', '0', [], ['id' => 'coCategory', 'icon' => 'fa-tag', 'label' => 'Category',
                            'options_html' => '<option value="0">- Category -</option>' . categoryOptionsHtml('Income', settingsValue('category', '0'))]) ?>
                    </div>
                    <div class="col-12">
                        <?= settingsInput('item_name', $co_contract['contract_name'], ['id' => 'coItemName', 'icon' => 'fa-cube', 'label' => 'Line', 'maxlength' => 200]) ?>
                    </div>
                    <div class="col-12">
                        <?= settingsInput('description', contractTypeLabel($co_contract['contract_type']) . ' - {period}', ['id' => 'coItemDescription', 'icon' => 'fa-align-left', 'label' => 'Description', 'maxlength' => 1000,
                            'help' => '{month}, {quarter}, {year} and {period} become the dates each invoice covers.']) ?>
                    </div>
                    <div class="col-sm-6">
                        <?= settingsSelect('tax_id', (string) taxDefaultFor($client_id, 'goods'), [], ['id' => 'coItemTax', 'icon' => 'fa-percent', 'label' => 'Tax',
                            'options_html' => taxOptionsHtml(billingTaxes(), intval(settingsValue('tax_id', taxDefaultFor($client_id, 'goods'))))]) ?>
                    </div>
                    <div class="col-sm-6 d-flex align-items-end">
                        <?= settingsSwitch('auto_send', '1', ['id' => 'coAutoSend', 'label' => 'Email each invoice by itself']) ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>

    <button type="submit" name="client_onboarding_billing" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-check me-2"></i>Save</button>
</form>
