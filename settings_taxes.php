<?php
require_once("inc_all_settings.php");

if (!empty($_GET['sb'])) {
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
    $sb = "tax_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM taxes
    WHERE tax_archived_at IS NULL
    AND company_id = $session_company_id
    ORDER BY $sb $o"
);

$num_rows = mysqli_num_rows($sql);

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-balance-scale"></i> Taxes</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTaxModal"><i class="fas fa-fw fa-plus"></i> New Tax</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=tax_name&o=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=tax_percent&o=<?php echo $disp; ?>">Percent</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $tax_id = $row['tax_id'];
                        $tax_name = htmlentities($row['tax_name']);
                        $tax_percent = $row['tax_percent'];

                        ?>
                        <tr>
                            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTaxModal<?php echo $tax_id; ?>"><?php echo "$tax_name"; ?></a></td>
                            <td><?php echo "$tax_percent%"; ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTaxModal<?php echo $tax_id; ?>">Edit</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="post.php?archive_tax=<?php echo $tax_id; ?>">Archive</a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("settings_tax_edit_modal.php");
                    }

                    if ($num_rows == 0) {
                        echo "<h3 class='text-secondary mt-3' style='text-align: center'>No Records Here</h3>";
                    }

                    ?>

                    </tbody>
                </table>

            </div>
        </div>
    </div>

<?php
require_once("settings_tax_add_modal.php");
require_once("footer.php");
