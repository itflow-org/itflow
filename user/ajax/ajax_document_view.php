<?php

require_once '../includes/ajax_header.php';

// Initialize the HTML Purifier to prevent XSS
require_once "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$document_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = $document_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$document_name = nullable_htmlentities($row['document_name']);
$document_content = $purifier->purify($row['document_content']);


// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title text-white"><i class="fa fa-fw fa-file-alt mr-2"></i><?php echo $document_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body bg-white prettyContent">
    <?php echo $document_content; ?>
</div>

<script src="../js/pretty_content.js"></script>

<?php
require_once "../includes/ajax_footer.php";

