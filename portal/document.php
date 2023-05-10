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

//Initialize the HTML Purifier to prevent XSS
require_once("../plugins/htmlpurifier/HTMLPurifier.standalone.php");
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);


// Check for a document ID
if (!isset($_GET['id']) && !intval($_GET['id'])) {
    header("Location: documents.php");
    exit();
}

$document_id = intval($_GET['id']);
$sql_document = mysqli_query($mysqli, "SELECT document_id, document_name, document_content FROM documents WHERE document_id = $document_id AND document_client_id = $session_client_id AND document_template = 0 LIMIT 1");

$row = mysqli_fetch_array($sql_document);

$document_id = intval($row['document_id']);
$document_name = htmlentities($row['document_name']);
$document_content = $purifier->purify($row['document_content']);

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
    
    <div class="card">
        <div class="card-body">
            <h3><?php echo $document_name; ?></h3>
            <?php echo $document_content; ?>
        </div>
    </div>


<?php
require_once("portal_footer.php");
