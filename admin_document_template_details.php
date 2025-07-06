<?php

require_once "includes/inc_all_admin.php";


//Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['document_template_id'])) {
    $document_template_id = intval($_GET['document_template_id']);
}

$sql_document = mysqli_query($mysqli, "SELECT * FROM document_templates WHERE document_template_id = $document_template_id");

$row = mysqli_fetch_array($sql_document);

$document_template_name = nullable_htmlentities($row['document_template_name']);
$document_template_description = nullable_htmlentities($row['document_template_description']);
$document_template_content = $purifier->purify($row['document_template_content']);
$document_template_created_at = nullable_htmlentities($row['document_template_created_at']);
$document_template_updated_at = nullable_htmlentities($row['document_template_updated_at']);

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="clients.php">Home</a>
    </li>
    <li class="breadcrumb-item">
        <a href="admin_user.php">Admin</a>
    </li>
    <li class="breadcrumb-item">
        <a href="admin_document_template.php">Document Templates</a>
    </li>
    <li class="breadcrumb-item active"><i class="fas fa-file mr-2"></i><?php echo $document_template_name; ?></li>
</ol>

<div class="card card-dark">
    <div class="card-header py-2">

        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file mr-2"></i><?php echo $document_template_name; ?></h3>

        <div class="card-tools">
            <button type="button" class="btn btn-primary"
                data-toggle="ajax-modal"
                data-modal-size="xl"
                data-ajax-url="ajax/ajax_document_template_edit.php"
                data-ajax-id="<?php echo $document_template_id; ?>"
                >
                <i class="fas fa-edit mr-2"></i>Edit
            </button>
        </div>
    </div>
    <div class="card-body prettyContent">
        <?php echo $document_template_content; ?>
    </div>
</div>

<script src="js/pretty_content.js"></script>

<?php
require_once "includes/footer.php";
