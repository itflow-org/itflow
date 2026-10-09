<?php

/*
 * Full search results - where Enter in the search palette lands.
 *
 * Same search as the palette (functions/search.php), with three differences:
 * up to 25 per type instead of 5, a total for each type, and $deep, which
 * also searches ticket details, ticket conversations and document text.
 *
 * Credential passwords are never in this page's markup. They are revealed and
 * copied through the same audited endpoint as the credentials list.
 */

require_once "includes/inc_all.php";

$search_query = globalSearchTerms($_GET['query'] ?? '')['raw'];
$search_query_display = escapeHtml($search_query);

$search_results = [];
if (mb_strlen($search_query) >= 2) {
    $search_results = globalSearch($search_query, 25, true, true);
}

$search_total = array_sum(array_column($search_results, 'total'));

// Usernames for the credentials shown - decrypted here, the same as the
// credentials list does, and only for rows that already passed the search's gates
$search_has_credentials = false;
$credential_usernames = [];
foreach ($search_results as $search_group) {
    if ($search_group['key'] === 'credentials') {
        $search_has_credentials = true;
        $credential_ids = implode(',', array_map('intval', array_column($search_group['items'], 'id')));
        $sql_credential_usernames = mysqli_query($mysqli, "SELECT credential_id, credential_username FROM credentials WHERE credential_id IN ($credential_ids)");
        while ($row = mysqli_fetch_assoc($sql_credential_usernames)) {
            $credential_usernames[intval($row['credential_id'])] = decryptCredentialEntry($row['credential_username']);
        }
    }
}

/** Escaped text with every occurrence of the query wrapped in <mark>. */
function searchResultHighlight($text, $query) {
    $text = (string) $text;
    if ($query === '' || $text === '') {
        return escapeHtml($text);
    }

    $output = '';
    $offset = 0;
    $length = mb_strlen($query);
    while (($position = mb_stripos($text, $query, $offset)) !== false) {
        $output .= escapeHtml(mb_substr($text, $offset, $position - $offset));
        $output .= '<mark>' . escapeHtml(mb_substr($text, $position, $length)) . '</mark>';
        $offset = $position + $length;
    }

    return $output . escapeHtml(mb_substr($text, $offset));
}

function searchResultBadge($badge) {
    if (empty($badge['text'])) {
        return '';
    }

    $text = escapeHtml($badge['text']);

    if (!empty($badge['variant']) && in_array($badge['variant'], ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true)) {
        return "<span class='badge rounded-pill text-bg-{$badge['variant']}'>$text</span>";
    }

    if (!empty($badge['hex']) && preg_match('/^#[0-9a-f]{3,8}$/i', $badge['hex'])) {
        return "<span class='badge rounded-pill text-white' style='background-color: {$badge['hex']}'>$text</span>";
    }

    return "<span class='badge rounded-pill text-bg-secondary'>$text</span>";
}

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-search me-2"></i>Search</h3>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="input-group">
                <input type="search" class="form-control" name="query" value="<?= $search_query_display ?>"
                    placeholder="Search clients, tickets, assets, credentials, documents and more" maxlength="100" autofocus>
                <button class="btn btn-primary" type="submit" title="Search"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <?php if (count($search_results) > 1) { ?>
            <nav class="d-flex flex-wrap gap-1 mt-3" aria-label="Jump to a result type">
                <?php foreach ($search_results as $search_group) { ?>
                    <a class="btn btn-sm btn-light border" href="#search-<?= escapeHtml($search_group['key']) ?>">
                        <i class="fas fa-fw fa-<?= escapeHtml($search_group['icon']) ?> me-1"></i><?= escapeHtml($search_group['label']) ?>
                        <span class="badge text-bg-secondary ms-1"><?= intval($search_group['total']) ?></span>
                    </a>
                <?php } ?>
            </nav>
        <?php } ?>
    </div>

    <div class="card-body">

        <?php if ($search_query === '') { ?>

            <div class="text-center text-secondary py-5">
                <i class="fas fa-4x fa-search mb-3 d-block" aria-hidden="true"></i>
                <h5>Search everything in one place</h5>
                <p class="mb-0">
                    Names, emails, phone numbers, IP and MAC addresses, serials, ticket, invoice and quote numbers -
                    plus ticket conversations and document text.<br>
                    Press <kbd>Ctrl</kbd> + <kbd>K</kbd> on any page to search without leaving it.
                </p>
            </div>

        <?php } elseif (mb_strlen($search_query) < 2) { ?>

            <div class="text-center text-secondary py-5">
                <i class="fas fa-4x fa-keyboard mb-3 d-block" aria-hidden="true"></i>
                <h5>Type at least two characters</h5>
            </div>

        <?php } elseif (!$search_results) { ?>

            <div class="text-center text-secondary py-5">
                <i class="fas fa-4x fa-search mb-3 d-block" aria-hidden="true"></i>
                <h5>Nothing matches "<?= $search_query_display ?>"</h5>
                <p class="mb-0">Check the spelling, or try part of a name, a number or an address.</p>
            </div>

        <?php } else { ?>

            <p class="text-secondary mb-3">
                <?= $search_total ?> result<?= $search_total === 1 ? '' : 's' ?> for "<strong><?= $search_query_display ?></strong>"
            </p>

            <?php foreach ($search_results as $search_group) {
                $group_key = escapeHtml($search_group['key']);
                $group_icon = escapeHtml($search_group['icon']);
                $group_label = escapeHtml($search_group['label']);
                $group_total = intval($search_group['total']);
                $group_shown = count($search_group['items']);
                ?>

                <div class="card mb-3 global-search-group" id="search-<?= $group_key ?>">
                    <div class="card-header py-2 d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">
                            <i class="fas fa-fw fa-<?= $group_icon ?> me-2 text-secondary"></i><?= $group_label ?>
                            <span class="badge text-bg-secondary ms-1"><?= $group_total ?></span>
                        </h3>
                        <?php if ($search_group['list_url']) { ?>
                            <a class="small" href="<?= escapeHtml($search_group['list_url']) ?>">
                                Open in <?= $group_label ?><i class="fas fa-fw fa-arrow-right ms-1"></i>
                            </a>
                        <?php } ?>
                    </div>

                    <div class="list-group list-group-flush">
                        <?php foreach ($search_group['items'] as $item) {
                            $item_url = escapeHtml($item['url']);
                            ?>
                            <div class="list-group-item d-flex align-items-center gap-3 py-2">
                                <div class="flex-grow-1 text-truncate">
                                    <a href="<?= $item_url ?>">
                                        <?php if (!empty($item['ref'])) { ?>
                                            <span class="font-monospace text-secondary small me-2"><?= searchResultHighlight($item['ref'], $search_query) ?></span>
                                        <?php } ?>
                                        <?= searchResultHighlight($item['title'], $search_query) ?>
                                    </a>
                                    <?php if (!empty($item['subtitle'])) { ?>
                                        <div class="small text-secondary text-truncate"><?= searchResultHighlight($item['subtitle'], $search_query) ?></div>
                                    <?php } ?>
                                    <?php if (!empty($item['snippet'])) { ?>
                                        <div class="small text-secondary text-wrap"><?= searchResultHighlight($item['snippet'], $search_query) ?></div>
                                    <?php } ?>
                                </div>

                                <?php if ($search_group['key'] === 'credentials') {
                                    $credential_id = intval($item['id']);
                                    $credential_username = escapeHtml($credential_usernames[$credential_id] ?? '');
                                    ?>
                                    <div class="text-nowrap d-none d-md-block">
                                        <?php if ($credential_username !== '') { ?>
                                            <span class="small"><?= $credential_username ?></span><button class="btn btn-sm btn-link clipboardjs" title="Copy username" type="button" data-clipboard-text="<?= $credential_username ?>"><i class="far fa-copy text-secondary"></i></button>
                                        <?php } ?>
                                    </div>
                                    <div class="text-nowrap">
                                        <button class="btn p-0" aria-label="Show password" type="button" onclick="showPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="fas fa-ellipsis-h text-secondary"></i><i class="fas fa-ellipsis-h text-secondary"></i></button><button class="btn btn-sm" title="Copy password" type="button" onclick="copyPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="far fa-copy text-secondary"></i></button>
                                    </div>
                                <?php } ?>

                                <?php if (!empty($item['badge'])) { ?>
                                    <div class="text-nowrap"><?= searchResultBadge($item['badge']) ?></div>
                                <?php } ?>

                                <?php if (!empty($item['context'])) { ?>
                                    <div class="small text-secondary text-truncate d-none d-sm-block global-search-context"><?= escapeHtml($item['context']) ?></div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>

                    <?php if ($group_total > $group_shown) { ?>
                        <div class="card-footer small text-secondary py-2">
                            Showing <?= $group_shown ?> of <?= $group_total ?>.
                            <?php if ($search_group['list_url']) { ?>
                                <a href="<?= escapeHtml($search_group['list_url']) ?>">See them all in <?= $group_label ?></a>
                            <?php } else { ?>
                                Add more words to narrow it down.
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

            <?php } ?>

        <?php } ?>

    </div>
</div>

<?php if ($search_has_credentials) { ?>
    <script src="js/credential_show_password_via_id.js"></script>
<?php } ?>

<?php
require_once "../includes/footer.php";
