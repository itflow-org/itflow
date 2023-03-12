<?php
/*
 * Client Portal
 * HTML Footer
 */
?>

<!-- Close container -->
</div>

<br>
<hr>

<p class="text-center"><?php echo htmlentities($config_app_name); ?></p>


<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<script src="../plugins/summernote/summernote-bs4.min.js"></script>

<script>
// Summernote
$('.summernote').summernote({
  toolbar: [
    ['style', ['style']],
    ['font', ['bold', 'underline', 'clear']],
    ['fontname', ['fontname']],
    ['color', ['color']],
    ['para', ['ul', 'ol', 'paragraph']],
    ['table', ['table']],
    ['insert', ['link', 'picture', 'video']],
    ['view', ['codeview']],
  ],
    height: 200
});

</script>