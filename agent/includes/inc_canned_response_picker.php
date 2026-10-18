<?php
/*
 * The canned response picker above a reply box - a button opening a list of
 * the responses on offer to search, each with a line of what it says and
 * what it sets up. Picking one puts it into the reply, filled in for the
 * ticket, and sets up what it sets up (agent/js/canned_response_picker.js).
 * Typing / in the reply box offers the same ones (js/app.js reads them from
 * here - the editor's textarea names this picker in data-canned-picker). The
 * files that go with one show under it, each one that can be left off, and
 * go with the reply (canned_response_file_ids[]).
 *
 * The agent's own come first, as Yours. On a ticket the list ends with Save
 * your reply as a canned response (agent/user/modals/canned_response_save.php)
 * - so the picker is there on a ticket even with none on offer yet - and a
 * link to their own in Your account.
 *
 * Set before including it:
 *   $canned_picker_groups    cannedResponsePickerGroups() - nothing is drawn when it is empty, but on a ticket
 *   $canned_picker_id        the picker's id, for data-canned-picker
 *   $canned_picker_editor    the reply box's textarea id
 *   $canned_picker_ticket_id the ticket it is filled in for; 0 fills it in
 *                            for each ticket as the reply is sent (Update & Reply)
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

if (!empty($canned_picker_groups) || intval($canned_picker_ticket_id) > 0) { ?>
    <div class="dropdown itflow-canned-picker mb-2" id="<?= escapeHtml($canned_picker_id) ?>" data-canned-picker data-editor="<?= escapeHtml($canned_picker_editor) ?>" data-ticket-id="<?= intval($canned_picker_ticket_id) ?>" data-canned-filled="<?= escapeHtml(implode('|', array_keys(CANNED_RESPONSE_PLACEHOLDERS))) ?>">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-canned-toggle>
            <i class="fas fa-fw fa-comment-dots me-1"></i>Canned response
        </button>
        <span class="small text-body-secondary ms-2 d-none d-md-inline">or type <kbd>/</kbd> in the reply</span>
        <div class="dropdown-menu p-0 itflow-canned-menu">
            <div class="p-2 border-bottom">
                <input type="search" class="form-control form-control-sm" placeholder="Search canned responses..." aria-label="Search canned responses" autocomplete="off" data-canned-search>
            </div>
            <div class="itflow-canned-list" data-canned-list>
                <?php foreach ($canned_picker_groups as $canned_group_label => $canned_group_rows) { ?>
                    <div data-canned-group>
                        <h6 class="dropdown-header"><?= escapeHtml($canned_group_label) ?></h6>
                        <?php foreach ($canned_group_rows as $canned_row) {
                            $canned_sets_up = cannedResponseSetsUpHtml($canned_row); ?>
                            <button type="button" class="dropdown-item itflow-canned-item" data-canned-id="<?= intval($canned_row['canned_response_id']) ?>"
                                data-canned-name="<?= escapeHtml($canned_row['canned_response_name']) ?>" data-canned-preview="<?= escapeHtml($canned_row['preview']) ?>"
                                data-canned-search-text="<?= escapeHtml(mb_strtolower($canned_row['canned_response_name'] . ' ' . $canned_row['preview'])) ?>">
                                <span class="d-block fw-semibold text-truncate"><?= escapeHtml($canned_row['canned_response_name']) ?></span>
                                <?php if ($canned_row['preview'] !== '') { ?><span class="d-block small text-body-secondary itflow-canned-preview"><?= escapeHtml($canned_row['preview']) ?></span><?php } ?>
                                <?php if ($canned_sets_up !== '') { ?><span class="d-block mt-1"><?= $canned_sets_up ?></span><?php } ?>
                            </button>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if (empty($canned_picker_groups)) { ?>
                    <div class="px-3 py-3 small text-body-secondary" data-canned-empty>No canned responses on offer here yet.</div>
                <?php } ?>
                <div class="px-3 py-3 small text-body-secondary" data-canned-none hidden>No canned response matches.</div>
            </div>
            <div class="border-top py-1">
                <?php if (intval($canned_picker_ticket_id) > 0) { ?>
                    <a class="dropdown-item small ajax-modal" href="#" data-modal-size="lg" data-canned-save-open
                        data-modal-url="/agent/user/modals/canned_response_save.php?<?= escapeHtml(http_build_query(['ticket_id' => intval($canned_picker_ticket_id), 'editor' => $canned_picker_editor, 'picker' => $canned_picker_id])) ?>">
                        <i class="fas fa-fw fa-save text-secondary me-2"></i>Save your reply as a canned response
                    </a>
                <?php } ?>
                <a class="dropdown-item small" href="/agent/user/user_canned_responses.php" target="_blank" title="Opens in a new tab - your reply stays here">
                    <i class="fas fa-fw fa-user text-secondary me-2"></i>Your canned responses
                </a>
            </div>
        </div>
        <div class="small text-body-secondary mt-1" data-canned-applied hidden></div>
        <div class="d-flex flex-wrap align-items-center gap-1 mt-1 small" data-canned-files hidden>
            <span class="text-body-secondary me-1"><i class="fas fa-fw fa-paperclip me-1"></i>Goes with it:</span>
        </div>
    </div>
    <script src="/agent/js/canned_response_picker.js"></script>
<?php }
