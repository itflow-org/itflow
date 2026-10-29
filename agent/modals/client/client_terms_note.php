<?php
/*
 * The client form's Billing tab: where a client's rates, net terms and SLAs
 * come from - its contract in force, else the defaults - since a client has
 * none of its own (functions/contract_billing.php). $terms_note_client_id:
 * the client being edited, or 0 for a new one.
 */

$terms_note_client_id = intval($terms_note_client_id ?? 0);
$terms_note = $terms_note_client_id ? contractClientTerms($terms_note_client_id) : null;
$terms_note_defaults = billingDefaults();
$terms_note_money = function ($amount) use ($session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};
$terms_note_net = function ($days) {
    return $days > 0 ? "Net $days" : 'due on receipt';
};
// The contracts in force they come from, by name - "Gold", "Gold and Standard terms"
$terms_note_names = [];
if ($terms_note) {
    $terms_note_names = array_merge([$terms_note['rate']['contract_name'], $terms_note['after_hours'][1], $terms_note['net_terms'][1]], array_column($terms_note['slas'], 1));
    $terms_note_names = array_values(array_unique(array_filter(array_map('strval', $terms_note_names), 'strlen')));
}
$terms_note_from = count($terms_note_names) > 1 ? implode(', ', array_slice($terms_note_names, 0, -1)) . ' and ' . end($terms_note_names) : ($terms_note_names[0] ?? '');
?>
<div class="alert alert-light border small mb-3" data-client-terms-note>
    <div class="fw-semibold mb-1"><i class="fas fa-fw fa-file-contract me-1"></i>Rates, net terms and SLAs</div>
    <?php if ($terms_note) { ?>
        <div>
            <?= $terms_note_money($terms_note['rate']['rate']) ?>/hr<?= $terms_note['after_hours'][0] > 0 ? ', ' . $terms_note_money($terms_note['after_hours'][0]) . '/hr after hours' : '' ?>,
            <?= $terms_note_net($terms_note['net_terms'][0]) ?> -
            <?= $terms_note_from !== '' ? 'from ' . escapeHtml($terms_note_from) . (count($terms_note_names) > 1 ? ', the contracts in force, with the defaults for anything they leave empty.' : ', the contract in force, with the defaults for anything it leaves empty.') : 'the defaults, as no contract in force sets them.' ?>
        </div>
        <div class="text-body-secondary mt-1">
            They come from the client's contract, not the client.
            <a href="/agent/contracts.php?client_id=<?= $terms_note_client_id ?>">Contracts</a> - or change the defaults in Admin &gt; Settings &gt; Defaults and Admin &gt; SLAs.
        </div>
    <?php } else { ?>
        <div>
            They come from the client's contract, not the client - <?= !empty($contract_pick_templates) ? 'start one from a template above, or add one from its Contracts later' : 'add one from its Contracts once it is added' ?>. Until then the defaults apply:
            <?= $terms_note_money($terms_note_defaults['rate']) ?>/hr<?= $terms_note_defaults['after_hours'] > 0 ? ', ' . $terms_note_money($terms_note_defaults['after_hours']) . '/hr after hours' : '' ?>,
            <?= $terms_note_net($terms_note_defaults['net_terms']) ?>, and the SLAs in Admin &gt; SLAs.
        </div>
    <?php } ?>
</div>
