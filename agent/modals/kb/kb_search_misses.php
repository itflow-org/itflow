<?php

/*
 * Not found - what readers searched Help in the client portal, or the public
 * help center, for in the last 90 days and found nothing (kbSearchMisses()):
 * the words, where - the public help center, or which clients' portals - how
 * many times and when last, the most searched first.
 * Write an article starts one with the words as its title; the x takes one
 * off the list, and Clear the list all of them (agent/post/kb.php) - searched
 * again, one comes back. Reading takes Support; changing it, Support edit.
 */

require_once '../../../includes/modal_header.php';

$kb_closed = kbClosedText(1);
if ($kb_closed !== '') {
    exit(json_encode(['error' => $kb_closed]));
}

$misses = kbSearchMisses();
$can_edit = kbCan(2);
$csrf = $_SESSION['csrf_token'];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-search-minus me-2"></i>Not found</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body p-0">
    <p class="small text-body-secondary px-3 pt-3 mb-2">What readers searched Help and the public help center for in the last <?= KB_SEARCH_MISS_DAYS ?> days, and found nothing - an article to write, or words to add to one that's there.</p>
    <?php if ($misses) { ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th class="ps-3">Searched for</th>
                    <th>Where</th>
                    <th class="text-end">Times</th>
                    <th>Last</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($misses as $query => $miss) { ?>
                    <tr>
                        <td class="ps-3 fw-semibold text-break"><?= escapeHtml($query) ?></td>
                        <td class="small">
                            <?php foreach ($miss['places'] as $place) { ?>
                                <div>
                                    <?php if ($place['source'] === 'public') { ?>
                                        <i class="fas fa-fw fa-globe text-body-secondary me-1"></i>Public help center
                                    <?php } elseif ($place['client_name'] !== null) { ?>
                                        <i class="fas fa-fw fa-users text-body-secondary me-1"></i><a href="client_overview.php?client_id=<?= $place['client_id'] ?>"><?= escapeHtml($place['client_name']) ?></a>
                                    <?php } else { ?>
                                        <i class="fas fa-fw fa-users text-body-secondary me-1"></i>Client portal
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td class="text-end"><?= $miss['searches'] ?></td>
                        <td class="small text-nowrap" title="<?= formatDateTime($miss['last_at']) ?>"><?= timeAgo($miss['last_at']) ?></td>
                        <td class="text-end text-nowrap pe-3">
                            <?php if ($can_edit) { ?>
                                <a class="btn btn-sm btn-light border ajax-modal" href="#" data-bs-dismiss="modal" data-modal-size="xl"
                                    data-modal-url="modals/kb/kb_article_add.php?<?= escapeHtml(http_build_query(['title' => ucfirst($query)])) ?>" title="A new article, starting with these words as its title">
                                    <i class="fas fa-plus me-1"></i>Write an article
                                </a>
                                <a class="btn btn-sm btn-link text-secondary" href="post.php?<?= escapeHtml(http_build_query(['dismiss_kb_search_miss' => $query, 'csrf_token' => $csrf])) ?>" title="Take it off the list"><i class="fas fa-times"></i></a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <?= emptyStateHtml(['title' => 'Nothing on the list', 'text' => 'Every search in the last ' . KB_SEARCH_MISS_DAYS . ' days found something.', 'icon' => 'fa-check', 'filtered' => false]) ?>
    <?php } ?>
</div>
<div class="modal-footer">
    <?php if ($can_edit && $misses) { ?>
        <a class="btn btn-outline-danger me-auto confirm-link" href="post.php?dismiss_kb_search_misses=1&csrf_token=<?= $csrf ?>"
            data-confirm-title="Clear the list?" data-confirm-text="Every search on it comes off. One searched again comes back." data-confirm-button="Clear the list">
            <i class="fas fa-broom me-2"></i>Clear the list
        </a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
</div>
<?php
require_once '../../../includes/modal_footer.php';
