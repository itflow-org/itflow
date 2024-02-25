<?php
/*
 * Client Portal
 * Docs for PTC / technical contacts
 */

header("Content-Security-Policy: default-src 'self' fonts.googleapis.com fonts.gstatic.com");

require_once "inc_portal.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

//Initialize the HTML Purifier to prevent XSS
require_once "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

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
$document_name = nullable_htmlentities($row['document_name']);
$document_content = $purifier->purify($row['document_content']);

?>

<div class="card">
    <div class="card-body prettyContent">
        <h3><?php echo $document_name; ?></h3>
        <?php echo $document_content; ?>
    </div>
</div>

<script src="../js/pretty_content.js"></script>


<?php
require_once "portal_footer.php";
