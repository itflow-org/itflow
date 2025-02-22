<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_credential');

// TODO: Default to 90 but allow input field to change this
if (isset($_GET['days'])) {
    $days = intval($_GET['days']);
} else {
    $days = 90;
}

$passwords_not_rotated_sql = mysqli_query($mysqli,
    "SELECT login_id, login_name, login_description, login_password_changed_at, login_client_id, client_id, client_name
        FROM logins
        LEFT JOIN clients ON login_client_id = client_id
        WHERE DATE(login_password_changed_at) < DATE_SUB(CURDATE(), INTERVAL $days DAY)
        ORDER BY client_name"
);

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>Client credentials not changed/rotated in the last 90 days</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
            </div>
        </div>
        <div class="card-body">

            <div class="table-responsive-sm">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th class="text-right">Credential Name</th>
                        <th class="text-right">Credential Description</th>
                        <th class="text-right">Credential Password Last Changed</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php

                    while ($row = mysqli_fetch_array($passwords_not_rotated_sql)) {

                        $login_id = intval($row['login_id']);
                        $login_name = nullable_htmlentities($row['login_name']);
                        $login_description = nullable_htmlentities($row['login_description']);
                        $login_password_changed = nullable_htmlentities($row['login_password_changed_at']);
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);

                        ?>

                        <tr>
                            <td><?php echo $client_name; ?></td>
                            <td class="text-right"><?php echo $login_name; ?></td>
                            <td class="text-right"><?php echo $login_description; ?></td>
                            <td class="text-right"><?php echo timeAgo($login_password_changed) . " (" . $login_password_changed . ")" ?></td>
                        </tr>

                    <?php } ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
require_once "includes/footer.php";

