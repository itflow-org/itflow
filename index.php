<?php 

// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);

if (file_exists("config.php")) {
    include "inc_all.php";
 ?>
    <!-- Breadcrumbs-->
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="index.php"><?php echo lang('dashboard'); ?></a>
      </li>
      <li class="breadcrumb-item active"><?php echo lang('blank_page'); ?></li>
    </ol>

    <!-- Page Content -->
    <h1><?php echo lang('blank_page'); ?></h1>
    <hr>
    <?php 

    include "footer.php";

} else {
    header("Location: setup.php");
}

?>
