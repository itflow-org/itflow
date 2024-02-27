<?php
require_once "inc_portal.php";



$sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_client_id = $session_client_id");


?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-key mr-2"></i>Logins</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark">
                    <tr>
                        <th><a class="text-secondary"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=login_name&order=<?php echo $disp; ?>">Name</a>
                        </th>
                        <th><a class="text-secondary"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=login_description&order=<?php echo $disp; ?>">Description</a>
                        </th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>OTP</th>
                        <th><a class="text-secondary"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=login_uri&order=<?php echo $disp; ?>">URI</a>
                        </th>
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
                        $login_uri_2 = nullable_htmlentities($row['login_uri_2']);
                        $login_username = nullable_htmlentities(decryptContactLoginEntry($row['login_username']));
                        if (empty($login_username)) {
                            $login_username_display = "-";
                        } else {
                            $login_username_display = "$login_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_username'><i class='far fa-copy text-secondary'></i></button>";
                        }
                        $login_password = nullable_htmlentities(decryptContactLoginEntry($row['login_password']));
                        $login_otp_secret = nullable_htmlentities($row['login_otp_secret']);
                        $login_id_with_secret = '"' . $row['login_id'] . '","' . $row['login_otp_secret'] . '"';
                        if (empty($login_otp_secret)) {
                            $otp_display = "-";
                        } else {
                            $otp_display = "<span onmouseenter='showOTPViaLoginID($login_id)'><i class='far fa-clock'></i> <span id='otp_$login_id'><i>Hover..</i></span></span>";
                        }
                        $login_note = nullable_htmlentities($row['login_note']);
                        $login_important = intval($row['login_important']);
                        $login_contact_id = intval($row['login_contact_id']);
                        $login_vendor_id = intval($row['login_vendor_id']);
                        $login_asset_id = intval($row['login_asset_id']);
                        $login_software_id = intval($row['login_software_id']);

                    ?>
                    <tr class="<?php if (!empty($login_important)) {
                                        echo "text-bold";
                                    } ?>">
                        <td>
                            <i class="fa fa-fw fa-key text-secondary"></i>
                            <a class="text-dark" href="#" data-toggle="modal"
                                data-target="#editLoginModal<?php echo $login_id; ?>">
                                <?php echo $login_name; ?>
                            </a>
                        </td>
                        <td><?php echo $login_description_display; ?></td>
                        <td><?php echo $login_username_display; ?></td>
                        <td>
                            <?php //echo $login_password;
                                    echo $login_password; ?>
                        </td>
                        <td><?php echo $otp_display; ?></td>
                        <td><?php echo $login_uri_display; ?></td>

                    </tr>
                    <?php } ?>


                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Include script to get TOTP code via the login ID -->
<script src="../js/logins_show_otp_via_id.js"></script>

<!-- Include script to generate readable passwords for login entries -->
<script src="../js/logins_generate_password.js"></script>
