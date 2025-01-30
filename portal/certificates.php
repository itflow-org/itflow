<?php

// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);

/*
* Client Portal
* Certificate listing for PTC / technical contacts
*/

header("Content-Security-Policy: default-src 'self'");

require_once "inc_portal.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

$certificates_sql = mysqli_query($mysqli, "SELECT certificate_id, certificate_name, certificate_domain, certificate_issued_by, certificate_expire FROM certificates WHERE certificate_client_id = $session_client_id AND certificate_archived_at IS NULL ORDER BY certificate_expire ASC");
?>

    <h3><?php echo lang('web_certificates'); ?></h3>
    <div class="row">

        <div class="col-md-10">

            <table class="table tabled-bordered border border-dark">
                <thead class="thead-dark">
                <tr>
                    <th><?php echo lang('certificate_name'); ?></th>
                    <th><?php echo lang('fqdn'); ?></th>
                    <th><?php echo lang('issuer'); ?></th>
                    <th><?php echo lang('expiry'); ?></th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($certificates_sql)) {
                    $certificate_name = nullable_htmlentities($row['certificate_name']);
                    $certificate_domain = nullable_htmlentities($row['certificate_domain']);
                    $certificate_issued_by = nullable_htmlentities($row['certificate_issued_by']);
                    $certificate_expire = nullable_htmlentities($row['certificate_expire']);
                    ?>

                    <tr>
                        <td><?php echo $certificate_name; ?></td>
                        <td><?php echo $certificate_domain; ?></td>
                        <td><?php echo $certificate_issued_by; ?></td>
                        <td><?php echo $certificate_expire; ?></td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>

        </div>

    </div>

<?php
require_once "portal_footer.php";
