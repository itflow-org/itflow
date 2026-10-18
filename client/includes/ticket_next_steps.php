<?php
/*
 * Client Portal - New ticket's "What happens next" panel, beside each of its
 * forms (client/ticket_add.php). Reads $company - its phone.
 */

isset($company) || die("Direct file access is not allowed");
?>
<section class="itflow-portal-panel">
    <div class="itflow-portal-panel-head">
        <h2><i class="fas fa-route"></i>What happens next</h2>
    </div>
    <div class="itflow-portal-panel-body">
        <ol class="ps-3 mb-0 small">
            <li class="mb-2">We get your ticket straight away and pick it up.</li>
            <li class="mb-2">You hear from us by email, and every reply shows on the ticket here.</li>
            <li class="mb-2">Answer us by replying to the email or on the ticket - whichever is easier.</li>
            <li>When it is done we mark it resolved, and you close it - or reopen it if it is not.</li>
        </ol>
        <?php if (!empty($company['company_phone'])) { ?>
            <hr>
            <p class="small mb-1"><strong>Stopping everyone?</strong> Call us as well:</p>
            <a href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', ($company['company_phone_country_code'] ? '+' . $company['company_phone_country_code'] : '') . $company['company_phone'])) ?>"><i class="fas fa-phone me-2"></i><?= escapeHtml(formatPhoneNumber($company['company_phone'], $company['company_phone_country_code'])) ?></a>
        <?php } ?>
    </div>
</section>
