<?php

/*
 * The placeholders a contract's terms can use (CONTRACT_PLACEHOLDERS), under
 * the terms editor in the contract and contract template windows - each
 * says what it becomes, and a click puts it in at the cursor
 * (js/contract_placeholders.js). Filled in as the contract is sent to sign,
 * and on its document and PDF.
 */

defined('CONTRACT_PLACEHOLDERS') || die("Direct file access is not allowed");

?>
<div class="mt-2 small" data-contract-placeholders>
    <span class="text-secondary me-1">Fills in from the contract:</span>
    <?php foreach (CONTRACT_PLACEHOLDERS as $placeholder_word => $placeholder_about) { ?>
        <button type="button" class="badge rounded-pill text-bg-light border font-monospace fw-normal mb-1" data-contract-placeholder="[<?= escapeHtml($placeholder_word) ?>]" title="<?= escapeHtml(ucfirst($placeholder_about)) ?>">[<?= escapeHtml($placeholder_word) ?>]</button>
    <?php } ?>
</div>
<script src="/js/contract_placeholders.js"></script>
