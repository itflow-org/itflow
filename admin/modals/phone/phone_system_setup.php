<?php

/*
 * Admin > Phone Systems - how to set a phone system up to send ITFlow what happens on it: the
 * lines to put in its configuration, with this system's addresses and key in them, each with Copy.
 * Nothing in ITFlow connects to it - it sends, to api/phone/ (functions/phone.php).
 */

require_once '../../includes/modal_header.php';

$row = phoneSystemRow($_GET['id'] ?? 0);
if (!$row || !phoneKind($row)) {
    echo json_encode(['error' => $row ? 'This ITFlow doesn\'t know that phone system\'s kind.' : 'That phone system no longer exists.']);
    exit;
}
$phone_system_id = intval($row['phone_system_id']);
$name = phoneSystemName($row);
$kind = phoneKind($row);
$heard = $row['phone_system_heard_at'];

// What goes in FreeSWITCH's XML - & written as &amp; there
$xml = function (string $url) {
    return htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8');
};
$cdr_url = phoneUrl($row, 'cdr');
$cid_url = phoneUrl($row, 'cid', 'number=${caller_id_number}&domain=${domain_name}');
$hook_url = phoneUrl($row, 'hook');

// Yealink action URLs - the phone fills in its own $variables
$account = 'line=$active_user&domain=$active_host';
$yealink = [
    'setup_completed' => 'e=setup&mac=$mac&ip=$ip&model=$model&fw=$firmware',
    'registered' => "e=registered&mac=\$mac&ip=\$ip&model=\$model&fw=\$firmware&$account",
    'unregistered' => "e=unregistered&mac=\$mac&$account",
    'register_failed' => "e=failed&mac=\$mac&$account",
    'incoming_call' => "e=ringing&mac=\$mac&$account&number=\$remote&name=\$display_remote&call=\$call_id",
    'outgoing_call' => "e=outgoing&mac=\$mac&$account&number=\$remote&name=\$display_remote&call=\$call_id",
    'call_established' => "e=answered&mac=\$mac&$account&number=\$remote&call=\$call_id",
    'call_terminated' => "e=ended&mac=\$mac&$account&number=\$remote&call=\$call_id",
];
$yealink_lines = [];
foreach ($yealink as $event => $query) {
    $yealink_lines[] = "action_url.$event = $hook_url&$query";
}

$snippets = [
    'cdr' => '<param name="url" value="' . $xml($cdr_url) . "\"/>\n<param name=\"log-http-and-disk\" value=\"true\"/>\n<param name=\"retries\" value=\"2\"/>\n<param name=\"delay\" value=\"5\"/>",
    'cid' => '<param name="url" value="' . $xml($cid_url) . "\"/>\n<param name=\"cache\" value=\"false\"/>",
    'yealink' => implode("\n", $yealink_lines),
    'polycom' => '<telNotification apps.telNotification.URL="' . $xml($hook_url) . "\"\n  apps.telNotification.incomingEvent=\"1\" apps.telNotification.outgoingEvent=\"1\"\n  apps.telNotification.callStateChangeEvent=\"1\" apps.telNotification.lineRegistrationEvent=\"1\"\n  apps.telNotification.uiInitializationEvent=\"1\"/>",
    'dialplan' => "<extension name=\"itflow_caller_id\" continue=\"true\">\n  <condition field=\"\${caller_id_number}\" expression=\"^\\+?\\d{7,15}\$\">\n    <action application=\"set\" data=\"itflow_cid=\${cidlookup(\${caller_id_number})}\" inline=\"true\"/>\n  </condition>\n  <condition field=\"\${itflow_cid}\" expression=\"^(?!UNKNOWN\$|-ERR).+\$\">\n    <action application=\"set\" data=\"effective_caller_id_name=\${itflow_cid}\"/>\n  </condition>\n</extension>",
];

$copy_block = function (string $id, string $text) {
    return '<div class="position-relative">'
        . '<pre class="itflow-phone-snippet small mb-0" id="' . $id . '">' . escapeHtml($text) . '</pre>'
        . '<button type="button" class="btn btn-sm btn-light border position-absolute top-0 end-0 m-1 clipboardjs" data-clipboard-target="#' . $id . '" title="Copy"><i class="far fa-fw fa-copy"></i></button>'
        . '</div>';
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-plug me-2"></i>Set up <strong><?= escapeHtml($name) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <p class="small mb-2">ITFlow doesn't connect to <?= escapeHtml($kind['short']) ?> - nothing is asked of its API or its database. Instead FreeSWITCH, underneath it, is set to send ITFlow what happens, to addresses with this system's key in them. Keep them to yourself - if one gets out, New Key in its Domains page's menu changes them.</p>
    <div class="small mb-3" data-phone-heard="<?= $heard ? 'yes' : 'never' ?>">
        <?php if ($heard) { ?>
            <i class="fas fa-fw fa-check-circle text-success me-1"></i>Heard from <span title="<?= formatDateTime($heard) ?>"><?= escapeHtml(timeAgo($heard)) ?></span>.
        <?php } else { ?>
            <i class="fas fa-fw fa-hourglass-half text-secondary me-1"></i>Not heard from yet - this says when it is.
        <?php } ?>
    </div>

    <h6 class="fw-semibold"><i class="fas fa-fw fa-history text-secondary me-1"></i>Calls</h6>
    <p class="small mb-2">Each call's record comes to ITFlow as it hangs up, and is kept with the contact it was with. In <?= escapeHtml($kind['short']) ?>'s <strong>Advanced &gt; XML Editor</strong>, or on the server, open <code>autoload_configs/xml_cdr.conf.xml</code> and set these in its <code>&lt;settings&gt;</code>, leaving its <code>log-dir</code> as it is so <?= escapeHtml($kind['short']) ?> goes on importing its own records:</p>
    <?= $copy_block('phoneSnippetCdr', $snippets['cdr']) ?>
    <p class="small text-body-secondary mt-2 mb-3">Then run <code>fs_cli -x 'reload mod_xml_cdr'</code>. If its <code>url</code> still points at <?= escapeHtml($kind['short']) ?>'s own <code>xml_cdr_import.php</code>, switch it to importing from files first - each record goes to one address only.</p>

    <h6 class="fw-semibold"><i class="fas fa-fw fa-id-badge text-secondary me-1"></i>Caller ID</h6>
    <p class="small mb-2">Calls coming in show the contact and their client on the phones - "Marla Whitfield - Ravenwood Dental". In <code>autoload_configs/cidlookup.conf.xml</code>, set:</p>
    <?= $copy_block('phoneSnippetCid', $snippets['cid']) ?>
    <p class="small mt-2 mb-2">Load it with <code>fs_cli -x 'reload mod_cidlookup'</code> (add <code>mod_cidlookup</code> to <code>modules.conf.xml</code> if it isn't). Then, in <strong>Dialplan &gt; Dialplan Manager</strong>, add this to the <code>public</code> context, ordered before the inbound routes - the name is only set when ITFlow knows the number, so the carrier's stays otherwise:</p>
    <?= $copy_block('phoneSnippetDialplan', $snippets['dialplan']) ?>

    <h6 class="fw-semibold mt-3"><i class="fas fa-fw fa-phone text-secondary me-1"></i>Phones</h6>
    <p class="small mb-2">Desk phones tell ITFlow their model, firmware, IP address and line as they start up and register, and each becomes a Phone asset of its domain's client, kept by its MAC address. They reach ITFlow themselves, so they need to be able to get to its address. For Yealink phones, add these lines to the template <?= escapeHtml($kind['short']) ?> provisions them with and have the phones check for new settings - or, for one phone, enter each address in its web page under <strong>Features &gt; Action URL</strong>:</p>
    <?= $copy_block('phoneSnippetYealink', $snippets['yealink']) ?>
    <p class="small mt-2 mb-2">For Polycom phones, add this to the configuration they are provisioned with:</p>
    <?= $copy_block('phoneSnippetPolycom', $snippets['polycom']) ?>

</div>
<div class="modal-footer">
    <a class="btn btn-outline-secondary me-auto" href="phone_domains.php?phone_system_id=<?= $phone_system_id ?>"><i class="fas fa-globe me-2"></i>Domains</a>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
</div>
<?php
require_once '../../../includes/modal_footer.php';
