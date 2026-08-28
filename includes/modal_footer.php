<script src="/js/http.js"></script>
<script src="/js/autocomplete.js"></script>
<script src="/js/tom_select.js"></script>
<script src="/js/app.js"></script>

<?php
    $content = ob_get_clean();

    // Return the title and content as a JSON response
    echo json_encode(['content' => $content]);
    exit();
?>