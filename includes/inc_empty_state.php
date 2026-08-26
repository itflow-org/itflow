<?php
/*
 * Empty state for listing pages.
 *
 * Required from filter_footer.php, which every listing page already includes
 * and which already receives $num_rows - so this reaches all 48 of them
 * without touching each one.
 *
 * The tables already hide their <thead> when $num_rows[0] is 0, so an empty
 * result was rendering as a card with a filter bar and nothing under it. This
 * fills that gap and, more usefully, distinguishes the two reasons a list can
 * be empty:
 *
 *   - nothing has been created yet    -> say so plainly
 *   - the filters exclude everything  -> say THAT, and offer a way out
 *
 * The second is the one worth getting right: a filtered-to-nothing list looks
 * identical to an empty install, and the fix (clear the filters) is invisible.
 *
 * $page_title is set from the script name by includes/page_title.php, which
 * every listing page pulls in via inc_all / inc_all_client / inc_all_admin,
 * so the label is already correct per page ("Clients", "Recurring Invoices")
 * with nothing to maintain.
 */

if (!isset($num_rows) || intval($num_rows[0]) > 0) {
    return;
}

/*
 * Which GET keys are page furniture rather than a filter the user chose.
 * canned_date is here because filter_header.php WRITES it on every request
 * when absent, so its presence says nothing; the dates it resolves to are
 * what count, and those arrive as dtf/dtt.
 *
 * client_id is scope, not a filter - 33 of these pages run inside a client via
 * inc_all_client.php. Counting it would tell someone their empty client has
 * "no invoices matching the current filters" and offer a Clear filters link
 * that quietly drops them out of the client entirely.
 */
$empty_state_ignored_params = array('page', 'sort', 'order', 'canned_date', 'show_column', 'client_id');

$empty_state_filtered = false;
foreach ($_GET as $empty_state_key => $empty_state_value) {
    if (in_array($empty_state_key, $empty_state_ignored_params, true)) {
        continue;
    }
    if (is_array($empty_state_value) ? count($empty_state_value) : strlen(trim((string)$empty_state_value))) {
        $empty_state_filtered = true;
        break;
    }
}

$empty_state_thing = strtolower($page_title ?? 'records');

?>

<div class="text-center text-secondary py-5 px-3">
    <?php if ($empty_state_filtered) { ?>

        <i class="fa fa-4x fa-filter mb-3 d-block" aria-hidden="true"></i>
        <h6>No <?= escapeHtml($empty_state_thing) ?> match the current filters.</h6>
        <p class="small mb-3">Try widening the date range or clearing the search.</p>
        <?php /* keep the client scope, drop everything else */ ?>
        <a href="<?= escapeHtml(strtok($_SERVER['REQUEST_URI'], '?') . (isset($_GET['client_id']) ? '?client_id=' . intval($_GET['client_id']) : '')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-fw fa-times me-2" aria-hidden="true"></i>Clear filters
        </a>

    <?php } else { ?>

        <i class="fa fa-4x fa-inbox mb-3 d-block" aria-hidden="true"></i>
        <h4>No <?= escapeHtml($empty_state_thing) ?> yet.</h4>
        <h6>Anything you add will show up here.</h6>

    <?php } ?>
</div>
