<?php
/*
 * Client Portal
 * Docs for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self'");

require_once "inc_portal.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

$documents_sql = mysqli_query($mysqli, "SELECT document_id, document_name, document_created_at, folder_name FROM documents LEFT JOIN folders ON document_folder_id = folder_id WHERE document_client_visible = 1 AND document_client_id = $session_client_id AND document_template = 0 AND document_archived_at IS NULL ORDER BY folder_id, document_name DESC");
?>

<h3>Documents</h3>
<div class="row">

    <div class="col-md-10">

        <table class="table tabled-bordered border border-dark">
            <thead class="thead-dark">
            <tr>
                <th>Name</th>
                <th>Created</th>
            </tr>
            </thead>
            <tbody>

            <?php
            while ($row = mysqli_fetch_array($documents_sql)) {
                $document_id = intval($row['document_id']);
                $folder_name = nullable_htmlentities($row['folder_name']);
                $document_name = nullable_htmlentities($row['document_name']);
                $document_created_at = nullable_htmlentities($row['document_created_at']);

                ?>

                <tr>
                    <td><a href="document.php?id=<?php echo $document_id?>">
                            <?php
                            if (!empty($folder_name)) {
                                echo "$folder_name / ";
                            }
                            echo $document_name;
                            ?>
                        </a>
                    </td>
                    <td><?php echo $document_created_at; ?></td>
                </tr>
            <?php } ?>

            </tbody>
        </table>

    </div>

</div>

<?php
require_once "portal_footer.php";
