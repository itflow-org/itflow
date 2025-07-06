<?php
/*
* Client Portal
* Domain listing for PTC / technical contacts
*/

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: post.php?logout");
    exit();
}

$domains_sql = mysqli_query($mysqli, "SELECT domain_id, domain_name, domain_expire FROM domains WHERE domain_client_id = $session_client_id AND domain_archived_at IS NULL ORDER BY domain_expire ASC");
?>

    <h3>Domains</h3>
    <div class="row">

        <div class="col-md-10">

            <table class="table tabled-bordered border border-dark">
                <thead class="thead-dark">
                <tr>
                    <th>Domain Name</th>
                    <th>Expiry</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($domains_sql)) {
                    $domain_name = nullable_htmlentities($row['domain_name']);
                    $domain_expire = nullable_htmlentities($row['domain_expire']);

                    ?>

                    <tr>
                        <td><?php echo $domain_name; ?></td>
                        <td><?php echo $domain_expire; ?></td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>

        </div>

    </div>

<?php
require_once "includes/footer.php";
