<?php

require_once '../../../includes/modal_header.php';

/*
 * An email as it arrived - one the mail parser kept with a ticket or a client's reply
 * (Original-parsed-email.eml, cron/ticket_email_parser.php). Who it was from and to, when, its
 * headers, and all of what it said - the reply line trims the reply on the ticket, and this shows
 * what was cut. The .eml itself downloads from here too.
 */

enforceUserPermission('module_support', 1);

$attachment_id = intval($_GET['attachment_id'] ?? 0);
$attachment = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_attachment_name, ticket_attachment_reference_name, ticket_attachment_ticket_id
    FROM ticket_attachments WHERE ticket_attachment_id = $attachment_id LIMIT 1"));
if (!$attachment || strtolower(pathinfo((string) $attachment['ticket_attachment_name'], PATHINFO_EXTENSION)) !== 'eml') {
    echo json_encode(['error' => 'That email is no longer kept.']);
    exit;
}
$row = ticketForModal($attachment['ticket_attachment_ticket_id'], 1);
$ticket_id = intval($row['ticket_id']);

$path = dirname(__DIR__, 3) . "/uploads/tickets/$ticket_id/" . basename((string) $attachment['ticket_attachment_reference_name']);
$too_big = is_file($path) && filesize($path) > 25 * 1024 * 1024;
$raw = is_file($path) && !$too_big ? (string) file_get_contents($path) : '';

$fields = [];
$header_text = '';
$body_text = '';
$attachment_names = [];
if ($raw !== '') {
    require_once dirname(__DIR__, 3) . '/libs/vendor/autoload.php'; // ImapEngine
    // The header block as it came - everything before the first blank line, folded lines kept
    $split = preg_split("/\r?\n\r?\n/", $raw, 2);
    $header_text = $split[0] ?? '';
    try {
        $message = new \DirectoryTree\ImapEngine\FileMessage($raw);
        $people = function ($addresses) {
            return implode(', ', array_map(function ($address) {
                $name = trim((string) $address->name());
                return $name !== '' ? "$name <" . $address->email() . '>' : (string) $address->email();
            }, array_filter((array) $addresses)));
        };
        $from = $message->from();
        $fields = array_filter([
            'From' => $from ? $people([$from]) : '',
            'To' => $people($message->to()),
            'Cc' => $people($message->cc()),
            'Date' => $message->date() ? $message->date()->format('Y-m-d H:i:s T') : '',
            'Subject' => (string) $message->subject(),
        ], function ($value) { return $value !== ''; });
        $body_text = (string) $message->text();
        if (trim($body_text) === '' && $message->html()) {
            $html = preg_replace('/<(style|script|head)\b[^>]*>.*?<\/\1>/is', '', (string) $message->html());
            $html = preg_replace('/<(br|\/p|\/div|\/tr|\/li|\/h[1-6])[^>]*>/i', "\n", $html);
            $body_text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }
        $body_text = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $body_text)));
        foreach ($message->attachments() as $part) {
            $attachment_names[] = (string) $part->filename();
        }
    } catch (Throwable $e) {
        $body_text = '';
    }
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-envelope-open-text me-2"></i>The Email as It Arrived <small class="ms-1 opacity-75"><?= escapeHtml(ticketReference($row)) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <?php if ($too_big) { ?>
        <p class="mb-0">This email is too large to show here - download it and open it in your email app.</p>
    <?php } elseif ($raw === '') { ?>
        <p class="mb-0">The email's file is missing.</p>
    <?php } else { ?>
        <dl class="row small mb-3">
            <?php foreach ($fields as $label => $value) { ?>
                <dt class="col-sm-2 text-body-secondary fw-normal"><?= $label ?></dt>
                <dd class="col-sm-10 mb-1 text-break"><?= escapeHtml($value) ?></dd>
            <?php } ?>
            <?php if ($attachment_names) { ?>
                <dt class="col-sm-2 text-body-secondary fw-normal">Attachments</dt>
                <dd class="col-sm-10 mb-1 text-break"><?= escapeHtml(implode(', ', $attachment_names)) ?></dd>
            <?php } ?>
        </dl>

        <ul class="nav nav-pills nav-pills-sm mb-2" role="tablist">
            <li class="nav-item"><button type="button" class="nav-link py-1 active" data-bs-toggle="pill" data-bs-target="#emailSourceText">What it said</button></li>
            <li class="nav-item"><button type="button" class="nav-link py-1" data-bs-toggle="pill" data-bs-target="#emailSourceHeaders">Headers</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="emailSourceText">
                <pre class="border rounded p-2 small mb-0 bg-body-tertiary" style="white-space: pre-wrap; max-height: 55vh; overflow: auto"><?= $body_text !== '' ? escapeHtml($body_text) : '<span class="text-body-secondary">No text in it.</span>' ?></pre>
            </div>
            <div class="tab-pane fade" id="emailSourceHeaders">
                <pre class="border rounded p-2 small mb-0 bg-body-tertiary" style="white-space: pre-wrap; max-height: 55vh; overflow: auto"><?= escapeHtml($header_text) ?></pre>
            </div>
        </div>
    <?php } ?>
</div>
<div class="modal-footer">
    <a class="btn btn-primary" href="ticket_attachment.php?attachment_id=<?= $attachment_id ?>"><i class="fas fa-download me-2"></i>Download .eml</a>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
