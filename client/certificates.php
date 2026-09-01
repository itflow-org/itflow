<?php
/*
* Client Portal
* Certificate listing for PTC / technical contacts
*/

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

enforceContactCan('itdoc');

$certificates_sql = mysqli_query($mysqli, "SELECT certificate_id, certificate_name, certificate_domain, certificate_issued_by, certificate_expire FROM certificates WHERE certificate_client_id = $session_client_id AND certificate_archived_at IS NULL ORDER BY certificate_expire ASC");
?>

    <h3>Web Certificates</h3>
    <div class="row">

        <div class="col-md-10">

            <?php if (mysqli_num_rows($certificates_sql) == 0) { ?>
                <?= portalEmptyState('There are no web certificates on this account yet.') ?>
            <?php } else { ?>
            <table class="table table-bordered border border-dark">
                <thead class="table-dark">
                <tr>
                    <th>Certificate Name</th>
                    <th>FQDN</th>
                    <th>Issuer</th>
                    <th>Expiry</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_assoc($certificates_sql)) {
                    $certificate_name = escapeHtml($row['certificate_name']);
                    $certificate_domain = escapeHtml($row['certificate_domain']);
                    $certificate_issued_by = escapeHtml($row['certificate_issued_by']);
                    $certificate_expire = escapeHtml($row['certificate_expire']);

                    ?>

                    <tr>
                        <td><?= $certificate_name ?></td>
                        <td><?= $certificate_domain ?></td>
                        <td><?= $certificate_issued_by ?></td>
                        <td><?= $certificate_expire ?></td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>
            <?php } ?>

        </div>

    </div>

<?php
require_once "includes/footer.php";
