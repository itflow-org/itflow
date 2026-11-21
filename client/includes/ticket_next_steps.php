<?php
/*
 * Client Portal - New ticket's "What happens next" panel, beside each of its
 * forms (client/ticket_add.php). Reads $company - its phone - and $reply_times,
 * how soon each priority hears back (portalReplyTimes()).
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
        <?php if (!empty($reply_times)) { ?>
            <hr>
            <p class="small fw-semibold mb-1">How soon we reply</p>
            <ul class="list-unstyled small mb-0" data-reply-times>
                <?php foreach (array_reverse($reply_times, true) as $priority => $within) { ?>
                    <li class="d-flex justify-content-between gap-3"><span><?= escapeHtml($priority) ?></span><span class="text-body-secondary text-end"><?= escapeHtml($within) ?></span></li>
                <?php } ?>
            </ul>
        <?php } ?>
        <?php if (!empty($company['company_phone'])) { ?>
            <hr>
            <p class="small mb-1"><strong>Stopping everyone?</strong> Call us as well:</p>
            <a href="tel:<?= escapeHtml(phoneDialNumber($company['company_phone'], $company['company_phone_country_code'])) ?>"><i class="fas fa-phone me-2"></i><?= escapeHtml(formatPhoneNumber($company['company_phone'], $company['company_phone_country_code'])) ?></a>
        <?php } ?>
    </div>
</section>
