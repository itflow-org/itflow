<?php

/*
 * Maintenance > Mail Queue - one email: where it stands, and why it last
 * failed or who cancelled it; who it is to and from; when it was queued,
 * tried, sent or given up on; its attachments and calendar file; and what it
 * says, until it is sent - that is cleared then (cron/mail_queue.php). The
 * email is shown as it will arrive, in a sandboxed frame that runs nothing
 * and can't reach this page. Retry now, Cancel and Delete as on its row
 * (admin/post/mail_queue.php).
 */

require_once '../../includes/modal_header.php';

$email_id = intval($_GET['id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT email_id, email_status, email_attempts, email_from, email_from_name, email_recipient, email_recipient_name,
    email_subject, email_content, email_cal_str, email_attachments, email_queued_at, email_failed_at, email_sent_at, " . mailQueueErrorColumn() . "
    FROM email_queue WHERE email_id = $email_id LIMIT 1"));

ob_start();

if (!$row) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-envelope me-2"></i>Email</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5" data-email-gone>That email is no longer in the queue - it may have been deleted.</div>
<?php } else {
    $state = mailQueueState($row);
    $status = intval($row['email_status']);
    $tries = intval($row['email_attempts']);
    $csrf = $_SESSION['csrf_token'];
    $when = function ($datetime) {
        return $datetime ? date('M j, Y g:i A', strtotime($datetime)) : '';
    };
    $person = function ($name, $address) {
        $name = trim((string) $name);
        $address = trim((string) $address);
        if ($address === '') {
            return ($name !== '' ? escapeHtml($name) . ' ' : '') . '<span class="text-danger-emphasis">no address</span>';
        }
        if ($name === '' || strcasecmp($name, $address) === 0) {
            return escapeHtml($address);
        }
        return escapeHtml($name) . ' <span class="text-body-secondary">&lt;' . escapeHtml($address) . '&gt;</span>';
    };
    $attachments = [];
    foreach ((array) json_decode((string) $row['email_attachments'], true) as $attachment) {
        if (is_array($attachment) && trim((string) ($attachment['name'] ?? '')) !== '') {
            $attachments[] = basename((string) $attachment['name']);
        }
    }
    $has_invite = trim((string) $row['email_cal_str']) !== '';
    $content = (string) $row['email_content'];
    $subject = trim((string) $row['email_subject']) !== '' ? escapeHtml($row['email_subject']) : '(no subject)';

    // How many tries, in words
    if ($tries > MAIL_QUEUE_TRIES) {
        $tries_text = 'Not tried again';
    } elseif ($status === 3) {
        $tries_text = $tries > 1 ? "Went on try $tries" : 'Went first time';
    } elseif ($tries > 0) {
        $tries_text = "$tries of " . MAIL_QUEUE_TRIES;
    } else {
        $tries_text = 'Not tried yet';
    }
    ?>

    <div class="modal-header bg-dark">
        <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-envelope-open me-2"></i><strong><?= $subject ?></strong></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" data-email-view="<?= intval($row['email_id']) ?>">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge <?= $state['badge'] ?> fs-6" data-email-status><i class="fas fa-fw <?= $state['icon'] ?> me-1"></i><?= $state['label'] ?></span>
            <span class="text-body-secondary" title="<?= escapeHtml($state['title']) ?>"><?= escapeHtml($state['detail']) ?></span>
        </div>

        <?php if ($state['error'] !== '') { ?>
            <div class="alert <?= $state['key'] === 'cancelled' ? 'alert-secondary' : ($state['key'] === 'failed' ? 'alert-danger' : 'alert-warning') ?> py-2" data-email-error>
                <?php if ($state['key'] === 'cancelled') { ?>
                    <i class="fas fa-fw fa-ban me-1"></i><?= escapeHtml($state['error']) ?>
                <?php } else { ?>
                    <div class="small fw-semibold mb-1"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Why it failed</div>
                    <div class="small text-break font-monospace"><?= escapeHtml($state['error']) ?></div>
                <?php } ?>
            </div>
        <?php } ?>

        <dl class="row small mb-3 itflow-mail-queue-facts">
            <dt class="col-sm-2 text-body-secondary fw-normal">To</dt>
            <dd class="col-sm-10 text-break"><?= $person($row['email_recipient_name'], $row['email_recipient']) ?></dd>
            <dt class="col-sm-2 text-body-secondary fw-normal">From</dt>
            <dd class="col-sm-10 text-break"><?= $person($row['email_from_name'], $row['email_from']) ?></dd>
            <dt class="col-sm-2 text-body-secondary fw-normal">Queued</dt>
            <dd class="col-sm-10"><?= escapeHtml($when($row['email_queued_at'])) ?> <span class="text-body-secondary">· #<?= intval($row['email_id']) ?></span></dd>
            <dt class="col-sm-2 text-body-secondary fw-normal">Tries</dt>
            <dd class="col-sm-10" data-email-tries><?= escapeHtml($tries_text) ?></dd>
            <?php if ($status === 3 && $row['email_sent_at']) { ?>
                <dt class="col-sm-2 text-body-secondary fw-normal">Sent</dt>
                <dd class="col-sm-10"><?= escapeHtml($when($row['email_sent_at'])) ?></dd>
            <?php } elseif ($row['email_failed_at'] && in_array($state['key'], ['retrying', 'failed', 'cancelled'], true)) { ?>
                <dt class="col-sm-2 text-body-secondary fw-normal"><?= $state['key'] === 'cancelled' ? 'Cancelled' : 'Last failed' ?></dt>
                <dd class="col-sm-10"><?= escapeHtml($when($row['email_failed_at'])) ?></dd>
            <?php } ?>
            <?php if ($attachments) { ?>
                <dt class="col-sm-2 text-body-secondary fw-normal">Attachments</dt>
                <dd class="col-sm-10" data-email-attachments>
                    <?php foreach ($attachments as $name) { ?>
                        <span class="badge text-bg-light border fw-normal me-1"><i class="fas fa-fw fa-paperclip me-1"></i><?= escapeHtml($name) ?></span>
                    <?php } ?>
                </dd>
            <?php } ?>
            <?php if ($has_invite) { ?>
                <dt class="col-sm-2 text-body-secondary fw-normal">Calendar</dt>
                <dd class="col-sm-10" data-email-invite><i class="fas fa-fw fa-calendar-plus text-body-secondary me-1"></i>A calendar file goes with it</dd>
            <?php } ?>
        </dl>

        <?php if ($content === '') { ?>
            <div class="text-center text-body-secondary border rounded py-4 px-3" data-email-cleared>
                <i class="fas fa-fw fa-eraser fa-lg mb-2 d-block"></i>
                <?= $status === 3
                    ? 'What it said was cleared when it was sent - an email can carry share links and temporary passwords, so it isn\'t kept.'
                    : 'There is nothing in it.' ?>
            </div>
        <?php } else { ?>
            <!-- As it will arrive - sandboxed: nothing in it runs, and it can't reach this page; its links open on their own -->
            <iframe class="itflow-mail-queue-frame w-100 border rounded" title="The email" sandbox="allow-popups allow-popups-to-escape-sandbox" data-email-frame
                srcdoc="<?= escapeHtml('<!doctype html><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="script-src \'none\'"><base target="_blank">' . $content) ?>"></iframe>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if (mailQueueCan($row, 'delete')) { ?>
            <a class="btn btn-outline-danger confirm-link me-auto" href="post.php?delete_mail=<?= intval($row['email_id']) ?>&csrf_token=<?= $csrf ?>" data-email-delete
                data-confirm-title="Delete this email?" data-confirm-text="<?= $status === 3 ? 'It has been sent - this only takes it off the list.' : 'It won\'t be sent.' ?>" data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
        <?php } ?>
        <?php if (mailQueueCan($row, 'retry')) { ?>
            <a class="btn btn-primary" href="post.php?retry_mail=<?= intval($row['email_id']) ?>&csrf_token=<?= $csrf ?>" title="From the start, at the next run" data-email-retry><i class="fas fa-fw fa-redo-alt me-2"></i>Retry now</a>
        <?php } ?>
        <?php if (mailQueueCan($row, 'cancel')) { ?>
            <a class="btn btn-outline-secondary" href="post.php?cancel_mail=<?= intval($row['email_id']) ?>&csrf_token=<?= $csrf ?>" data-email-cancel><i class="fas fa-fw fa-ban me-2"></i>Cancel it</a>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    </div>
<?php }

require_once '../../../includes/modal_footer.php';
