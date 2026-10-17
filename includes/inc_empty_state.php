<?php
/*
 * Empty state for listing pages.
 *
 * Required from filter_footer.php, which every listing page includes and
 * which already has $num_rows. When the list has nothing, this draws the one
 * empty state every list shares (emptyStateHtml() in functions/format.php):
 * "No vendors yet" with the page's New buttons, or "No vendors match" with
 * Clear filters when a search or filters left nothing.
 *
 * The page says what its list holds with $empty_state - its icon, the thing
 * it lists, a line on what goes there, its New buttons and, where it knows
 * better than the GET string, whether it is filtered. A page that lays out
 * an empty state of its own (files, notifications, racks) calls
 * emptyStateHtml() there and sets $empty_state_skip.
 *
 * $page_title is set from the script name by includes/page_title.php, so a
 * page that sets nothing still reads "No clients yet".
 */

if (!isset($num_rows) || intval($num_rows[0]) > 0 || !empty($empty_state_skip)) {
    return;
}

echo emptyStateHtml(($empty_state ?? []) + ['thing' => strtolower($page_title ?? 'records'), 'archived' => !empty($archived)]);
