<script>// Initialize Select2 Elements
$('.select2').select2({
  theme: 'bootstrap4',
});
</script>

<?php
    $content = ob_get_clean();

    // Return the title and content as a JSON response
    echo json_encode(['title' => $title, 'content' => $content]);
?>