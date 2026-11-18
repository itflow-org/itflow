<?php
/*
 * The Knowledge Base picker above a ticket reply, beside the canned responses -
 * a button opening the published articles that look like they answer the
 * ticket (kbFind() on its subject and what was asked), counted on the button,
 * and a search over the rest. Each says how the ticket's client could read
 * it (kbReachFor()): Link puts a link to it in the reply - the public help
 * center's, or their portal's - and Insert puts what it says in, pictures
 * and all (agent/js/kb_picker.js, through kb_picker and kb_article_reply in
 * agent/ajax.php).
 *
 * Set before including it:
 *   $kb_picker_editor  the reply box's textarea id
 *   $kb_picker_ticket  the ticket's row - its id, client, subject and details
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

if (kbCan(1)) {
    $kb_picker_ticket_id = intval($kb_picker_ticket['ticket_id']);
    $kb_picker_rows = kbFind($kb_picker_ticket['ticket_subject'] . ' ' . mb_substr(strip_tags(str_replace('<', ' <', (string) $kb_picker_ticket['ticket_details'])), 0, 300), '', 5);
    ?>
    <div class="dropdown itflow-kb-picker mb-2" data-kb-picker data-editor="<?= escapeHtml($kb_picker_editor) ?>" data-ticket-id="<?= $kb_picker_ticket_id ?>">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-kb-pick-toggle
            title="<?= $kb_picker_rows ? count($kb_picker_rows) . ' article' . (count($kb_picker_rows) === 1 ? '' : 's') . ' might help with this ticket' : 'Find an article to link to or put in the reply' ?>">
            <i class="fas fa-fw fa-book me-1"></i>Knowledge Base<?php if ($kb_picker_rows) { ?><span class="badge rounded-pill text-bg-primary ms-1"><?= count($kb_picker_rows) ?></span><?php } ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 itflow-kb-pick-menu">
            <div class="p-2 border-bottom d-flex align-items-center gap-1">
                <input type="search" class="form-control form-control-sm" placeholder="Search the Knowledge Base..." aria-label="Search the Knowledge Base" autocomplete="off" data-kb-pick-search>
                <button type="button" class="btn btn-sm btn-link text-decoration-none flex-shrink-0" title="Close the list and go back to the reply (Esc)" data-kb-pick-close>Cancel</button>
            </div>
            <div class="itflow-kb-pick-list" data-kb-pick-list>
                <h6 class="dropdown-header text-start">Might help with this ticket</h6>
                <?php if ($kb_picker_rows) { ?>
                    <?= kbPickerRowsHtml($kb_picker_rows, intval($kb_picker_ticket['ticket_client_id'])) ?>
                <?php } else { ?>
                    <div class="px-3 pb-3 small text-body-secondary">Nothing looks like it fits this ticket - search for an article.</div>
                <?php } ?>
            </div>
            <div class="border-top py-1">
                <a class="dropdown-item small" href="/agent/kb.php" target="_blank" title="Opens in a new tab - your reply stays here"><i class="fas fa-fw fa-book text-secondary me-2"></i>Open the Knowledge Base</a>
            </div>
        </div>
        <div class="small text-body-secondary mt-1" data-kb-pick-said hidden></div>
    </div>
    <script src="<?= staticFileUrl('/agent/js/kb_picker.js') ?>"></script>
<?php }
