<?php
/*
 * Pagination Body/Footer
 * Displays page number buttons
 *
 * Should not be accessed directly, but called from other pages
 * Relies upon the $num_rows variable being set correctly
 */

/*
 * Nothing to paginate means nothing rendered below the filter bar at all - the
 * tables already hide their <thead> at zero rows. Handled here rather than in
 * each of the 48 listing pages because this file is the one thing they all
 * include, and it already has $num_rows.
 */
require __DIR__ . '/inc_empty_state.php';

$total_found_rows = $num_rows[0];
$total_pages = (int) ceil($total_found_rows / $user_config_records_per_page);

if ($total_found_rows > 5) {
    ?>

<div class="card-footer itflow-filter-footer">

    <div class="row align-items-center g-2">
        <div class="col-auto col-sm">
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token'] ?? '') ?>">
                <div class="d-flex align-items-center">
                    <select onchange="this.form.submit()" class="form-select form-select-sm w-auto" name="change_records_per_page" aria-label="Records per page">
                        <option <?php if ($user_config_records_per_page == 5) { echo "selected"; } ?> >5</option>
                        <option <?php if ($user_config_records_per_page == 10) { echo "selected"; } ?> >10</option>
                        <option <?php if ($user_config_records_per_page == 20) { echo "selected"; } ?> >20</option>
                        <option <?php if ($user_config_records_per_page == 50) { echo "selected"; } ?> >50</option>
                        <option <?php if ($user_config_records_per_page == 100) { echo "selected"; } ?> >100</option>
                        <option <?php if ($user_config_records_per_page == 500) { echo "selected"; } ?> >500</option>
                    </select>
                    <span class="text-muted small ms-2 d-none d-sm-inline">per page</span>
                </div>
            </form>
        </div>

        <?php
        // Number of records per page
        $per_page = $user_config_records_per_page;

        // Current page (make sure $page is set; default to 1)
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;

        // Calculate start and end item indexes
        $start = ($page - 1) * $per_page + 1;
        $end   = $page * $per_page;

        // Prevent $end from exceeding total found rows
        if ($end > $total_found_rows) {
            $end = $total_found_rows;
        }

        // Now output something like "Showing X to Y of Z records"
        ?>

        <div class="col col-sm">
            <p class="text-center mb-0">
              Showing <strong><?= $start ?></strong> to <strong><?= $end ?></strong> of <strong><?= $total_found_rows ?></strong> records
            </p>
        </div>
        <div class="col-12 col-xl-auto">
            <ul class="pagination justify-content-center justify-content-xl-end mb-0">

                <?php

                if ($page > 1) {
                    $prev_class = "";
                } else {
                    $prev_class = "disabled";
                }
                if ($page <> $total_pages) {
                    $next_class = "";
                } else {
                    $next_class = "disabled";
                }
                $get_copy = $_GET; // create a copy of the $_GET array
                // Unset Array Var to prevent Duplicate Get VARs
                unset($get_copy['page']);
                $url_query_strings_page = http_build_query($get_copy);
                $prev_page = $page - 1;
                $next_page  = $page + 1;

                if ($page > 1) {
                    echo "<li class='page-item $prev_class'><a class='page-link' href='?$url_query_strings_page&page=$prev_page' aria-label='Previous page'><span class='d-sm-none' aria-hidden='true'>&lsaquo;</span><span class='d-none d-sm-inline'>Prev</span></a></li>";
                }

                // Prev and Next are arrows on a phone. The first and last pages and the ones either side of this one, with a gap
                // between - one short row however many pages there are
                if ($page <= 4) {
                    $from = 1;
                    $to = min($total_pages, 5);
                } elseif ($page >= $total_pages - 3) {
                    $from = max(1, $total_pages - 4);
                    $to = $total_pages;
                } else {
                    $from = $page - 1;
                    $to = $page + 1;
                }
                $page_numbers = range($from, $to);
                // A gap of one page shows that page, not "..." in its place
                if ($from > 1) {
                    array_unshift($page_numbers, 1, $from === 3 ? 2 : ($from > 3 ? 0 : null));
                }
                if ($to < $total_pages) {
                    array_push($page_numbers, $to === $total_pages - 2 ? $total_pages - 1 : ($to < $total_pages - 2 ? 0 : null), $total_pages);
                }
                foreach (array_filter($page_numbers, function ($n) { return $n !== null; }) as $i) {
                    if ($i === 0) {
                        echo "<li class='page-item disabled'><span class='page-link'>&hellip;</span></li>";
                        continue;
                    }
                    $page_class = $page == $i ? "active" : "";
                    echo "<li class='page-item $page_class'><a class='page-link' href='?$url_query_strings_page&page=$i'>$i</a></li>";
                }

                if ($page <> $total_pages) {
                    echo "<li class='page-item $next_class'><a class='page-link' href='?$url_query_strings_page&page=$next_page' aria-label='Next page'><span class='d-sm-none' aria-hidden='true'>&rsaquo;</span><span class='d-none d-sm-inline'>Next</span></a></li>";
                }

                ?>

            </ul>
        </div>
    </div>
</div>

    <?php

}
