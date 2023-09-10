<?php

// Default Column Sortby Filter
$sort = "login_name";
$order = "ASC";

require_once("inc_all_client.php");

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM logins
    WHERE login_client_id = $client_id
    AND (login_name LIKE '%$q%' OR login_description LIKE '%$q%' OR login_uri LIKE '%$q%')
    ORDER BY login_important DESC, $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-key mr-2"></i>Passwords</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLoginModal"><i class="fas fa-plus mr-2"></i>Create</button>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Passwords">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="float-right">
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportLoginModal"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importLoginModal"><i class="fa fa-fw fa-upload mr-2"></i>Import</button>
                    </div>
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=login_name&order=<?php echo $disp; ?>">Name</a></th>
                    <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=login_description&order=<?php echo $disp; ?>">Description</a></th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>OTP</th>
                    <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=login_uri&order=<?php echo $disp; ?>">URI</a></th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $login_id = intval($row['login_id']);
                    $login_name = nullable_htmlentities($row['login_name']);
                    $login_description = nullable_htmlentities($row['login_description']);
                    if (empty($login_description)) {
                        $login_description_display = "-";
                    } else {
                        $login_description_display = $login_description;
                    }
                    $login_uri = nullable_htmlentities($row['login_uri']);
                    if (empty($login_uri)) {
                        $login_uri_display = "-";
                    } else {
                        $login_uri_display = "$login_uri<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_uri'><i class='far fa-copy text-secondary'></i></button><a href='$login_uri' target='_blank'><i class='fa fa-external-link-alt text-secondary'></i></a>";
                    }
                    $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                    if (empty($login_username)) {
                        $login_username_display = "-";
                    } else {
                        $login_username_display = "$login_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_username'><i class='far fa-copy text-secondary'></i></button>";
                    }
                    $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));
                    $login_otp_secret = nullable_htmlentities($row['login_otp_secret']);
                    $login_id_with_secret = '"' . $row['login_id'] . '","' . $row['login_otp_secret'] . '"';
                    if (empty($login_otp_secret)) {
                        $otp_display = "-";
                    } else {
                        $otp_display = "<span onmouseenter='showOTP($login_id_with_secret)'><i class='far fa-clock'></i> <span id='otp_$login_id'><i>Hover..</i></span></span>";
                    }
                    $login_note = nullable_htmlentities($row['login_note']);
                    $login_important = intval($row['login_important']);
                    $login_contact_id = intval($row['login_contact_id']);
                    $login_vendor_id = intval($row['login_vendor_id']);
                    $login_asset_id = intval($row['login_asset_id']);
                    $login_software_id = intval($row['login_software_id']);

                    ?>
                    <tr class="<?php if(!empty($login_important)) { echo "text-bold"; }?>">
                        <td>
                            <i class="fa fa-fw fa-key text-secondary"></i>
                            <a class="text-dark" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                                <?php echo $login_name; ?>
                            </a>
                        </td>
                        <td><?php echo $login_description_display; ?></td>
                        <td><?php echo $login_username_display; ?></td>
                        <td>
                            <a tabindex="0" href="#" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?php echo $login_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></a><button class="btn btn-sm clipboardjs" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button>
                        </td>
                        <td><?php echo $otp_display; ?></td>
                        <td><?php echo $login_uri_display; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Login', $login_id"; ?>)">
                                        <i class="fas fa-fw fa-share mr-2"></i>Share
                                    </a>
                                    <?php if ($session_user_role == 3) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold" href="post.php?delete_login=<?php echo $login_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                    require("client_login_edit_modal.php");
                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once("pagination.php"); ?>
    </div>
</div>

<script>
    function showOTP(id, secret) {
        //Send a GET request to ajax.php as ajax.php?get_totp_token=true&totp_secret=SECRET
        jQuery.get(
            "ajax.php",
            {get_totp_token: 'true', totp_secret: secret},
            function(data) {
                //If we get a response from post.php, parse it as JSON
                const token = JSON.parse(data);

                document.getElementById("otp_" + id).innerText = token

            }
        );
    }

    function generatePassword() {
        document.getElementById("password").value = "<?php echo randomString(); ?>"
    }
</script>

<?php

require_once("client_login_add_modal.php");
require_once("share_modal.php");
require_once("client_login_import_modal.php");
require_once("client_login_export_modal.php");
require_once("footer.php");
