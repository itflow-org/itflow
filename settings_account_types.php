<?php
require_once("inc_all_settings.php");

// Default Column Sortby Filter
$sort = "account_type_id";
$order = "ASC";

if (isset($_GET['account_type'])) {
    $account_type = sanitizeInput($_GET['account_type']);
    switch ($account_type) {
        case "Assets":
            $account_type_id_min = "10";
            $account_type_id_max = "19";
            break;
        case "Liabilities":
            $account_type_id_min = "20";
            $account_type_id_max = "29";
            break;
        case "Equity":
            $account_type_id_min = "30";
            $account_type_id_max = "39";
            break;
        default:
            $account_type_id_min = "10";
            $account_type_id_max = "39";
    }
} else {
        $account_type_id_min = "10";
        $account_type_id_max = "39";
}

 

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM account_types
    WHERE account_type_$archive_query
    AND account_type_id >= $account_type_id_min
    AND account_type_id <= $account_type_id_max
    ORDER BY $sort $order"
);

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-money-bill-wave mr-2"></i>Finance Account Types</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAccountTypeModal"><i
                    class="fas fa-plus mr-2"></i>Create Account Type</button>
        </div>
    </div>
    <div class="card-body">
        <div class="col-sm-8">
            <div class="btn-group float-right">
                <a href="?account_type=Assets" class="btn <?php if ($account_type == 'Assets') {
                    echo 'btn-primary';
                } else {
                    echo 'btn-default';
                } ?>">Assets</a>
                <a href="?account_type=Liabilities" class="btn <?php if ($account_type == 'Liabilities') {
                    echo 'btn-primary';
                } else {
                    echo 'btn-default';
                } ?>">Liabilities</a>
                <a href="?account_type=Equity" class="btn <?php if ($account_type == 'Equity') {
                    echo 'btn-primary';
                } else {
                    echo 'btn-default';
                } ?>">Equity</a>
                <a href="?archived=1" class="btn <?php if ($_GET['archived']) {
                    echo 'btn-primary';
                } else {
                    echo 'btn-default';
                } ?>"><i class="fas fa-fw fa-archive mr-2"></i>Archived</a>
            </div>
        </div>
        <form action="post.php" method="post" autocomplete="off">
            <table class="table table-striped table-borderless table-hover">
                <thead>
                    <tr>
                        <th>Account Type ID</th>
                        <th>Account Type Name</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $account_type_id = nullable_htmlentities($row['account_type_id']);
                        $account_type_name = nullable_htmlentities($row['account_type_name']);
                        $account_type_description = nullable_htmlentities($row['account_type_description']);
                        ?>
                        <tr>
                            <td><a class="text-dark text-bold" href="#" data-toggle="modal"
                                    data-target="#editAccountTypeModal<?php echo $account_type_id; ?>">
                                    <?php echo $account_type_id; ?>
                                </a></td>
                            <td>
                                <?php echo $account_type_name; ?>
                            </td>
                            <td>
                                <?php echo $account_type_description; ?>
                            </td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal"
                                            data-target="#editAccountTypeModal<?php echo $account_type_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($archived == NULL) { ?>
                                        <a class="dropdown-item text-danger confirm-link"
                                            href="post.php?archive_account_type=<?php echo $account_type_id; ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                        </a>
                                        <?php } else { ?>
                                        <a class="dropdown-item text-success confirm-link"
                                            href="post.php?unarchive_account_type=<?php echo $account_type_id; ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Unarchive
                                        </a>
                                        <?php } ?>
                                        
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("settings_account_types_edit_modal.php");
                    }

                    if ($num_rows == 0) {
                        echo "<h3 class='text-secondary mt-3' style='text-align: center'>No Records Here</h3>";
                    }

                    ?>
                </tbody>
            </table>
        </form>
    </div>
</div>

<?php
require_once("settings_account_types_add_modal.php");
require_once("footer.php");

?>