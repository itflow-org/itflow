<?php
/*
 * Client Portal
 * Docs for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self' https: fonts.googleapis.com");

require_once("inc_portal.php");

if ($session_contact_id !== $session_client_primary_contact_id && !$session_contact_is_technical_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

$documents_sql = mysqli_query($mysqli, "SELECT document_id, document_name, document_created_at, folder_name FROM documents LEFT JOIN folders ON document_folder_id = folder_id WHERE document_client_id = $session_client_id AND document_template = 0 ORDER BY folder_id, document_name DESC");
?>

    <div class="row">
        <div class="col-md-1 text-center">
            <?php if (!empty($session_contact_photo)) { ?>
                <img src="<?php echo "../uploads/clients/$session_client_id/$session_contact_photo"; ?>" alt="..." height="50" width="50" class="img-circle img-responsive">
            <?php } else { ?>
                <span class="fa-stack fa-2x rounded-left">
                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                <span class="fa fa-stack-1x text-white"><?php echo $session_contact_initials; ?></span>
            </span>
            <?php } ?>
        </div>

        <div class="col-md-11 p-0">
            <h4>Welcome, <strong><?php echo $session_contact_name ?></strong>!</h4>
            <hr>
        </div>

    </div>

    <br>

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
                    $folder_name = htmlentities($row['folder_name']);
                    $document_name = htmlentities($row['document_name']);
                    $document_created_at = htmlentities($row['document_created_at']);

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
require_once("portal_footer.php");
