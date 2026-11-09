<script src="<?= staticFileUrl('/js/http.js') ?>"></script>
<script src="<?= staticFileUrl('/js/autocomplete.js') ?>"></script>
<script src="<?= staticFileUrl('/js/itflow_select.js') ?>"></script>
<script src="<?= staticFileUrl('/js/app.js') ?>"></script>

<?php
    $content = ob_get_clean();

    // Return the title and content as a JSON response
    echo json_encode(['content' => $content]);
    exit();
?>