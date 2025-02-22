<?php
/*
 * Pagination Body/Footer
 * Displays page number buttons
 *
 * Should not be accessed directly, but called from other pages
 * Relies upon the $num_rows variable being set correctly
 */

$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / $user_config_records_per_page);

if ($total_found_rows > 5) {
    $i = 0;

    ?>

    <hr>

    <div class="row">
        <div class="col-sm mb-2">
            <form action="post.php" method="post">
                <select onchange="this.form.submit()" class="form-control select2 col-12 col-sm-3" name="change_records_per_page">
                    <option <?php if ($user_config_records_per_page == 5) { echo "selected"; } ?> >5</option>
                    <option <?php if ($user_config_records_per_page == 10) { echo "selected"; } ?> >10</option>
                    <option <?php if ($user_config_records_per_page == 20) { echo "selected"; } ?> >20</option>
                    <option <?php if ($user_config_records_per_page == 50) { echo "selected"; } ?> >50</option>
                    <option <?php if ($user_config_records_per_page == 100) { echo "selected"; } ?> >100</option>
                    <option <?php if ($user_config_records_per_page == 500) { echo "selected"; } ?> >500</option>
                </select>
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

        <div class="col-sm mb-2">
            <p class="text-center">
              Showing <strong><?php echo $start; ?></strong> to <strong><?php echo $end; ?></strong> of <strong><?php echo $total_found_rows; ?></strong> records
            </p>


            <!--<p class="text-center mt-2"><?php echo $total_found_rows; ?></p> -->
        </div>
        <div class="col-sm mb-2">

            <ul class="pagination justify-content-sm-end">

                <?php

                if ($total_pages <= 100) {
                    $pages_split = 10;
                }
                if (($total_pages <= 1000) && ($total_pages > 100)) {
                    $pages_split = 100;
                }
                if (($total_pages <= 10000) && ($total_pages > 1000)) {
                    $pages_split = 1000;
                }
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
                    echo "<li class='page-item $prev_class'><a class='page-link' href='?$url_query_strings_page&page=$prev_page'>Prev</a></li>";
                }

                while ($i < $total_pages) {
                    $i++;
                    if (($i == 1) || (($page <= 3) && ($i <= 6)) || (($i >  $total_pages - 6) && ($page > $total_pages - 3)) || (is_int($i / $pages_split)) || (($page > 3) && ($i >= $page - 2) && ($i <= $page + 3)) || ($i == $total_pages)) {
                        if ($page == $i) {
                            $page_class = "active";
                        } else {
                            $page_class = "";
                        }
                        echo "<li class='page-item $page_class'><a class='page-link' href='?$url_query_strings_page&page=$i'>$i</a></li>";
                    }
                }

                if ($page <> $total_pages) {
                    echo "<li class='page-item $next_class'><a class='page-link' href='?$url_query_strings_page&page=$next_page'>Next</a></li>";
                }

                ?>

            </ul>
        </div>
    </div>

    <?php

}

if ($total_found_rows == 0) {
    echo "<center class='my-3'><i class='far fa-fw fa-6x fa-meh-rolling-eyes text-secondary'></i><h3 class='text-secondary mt-3'>No Results</h3></center>";
}

?>
