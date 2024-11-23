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

<p class="text-center">
    <?php
        echo nullable_htmlentities($session_company_name);
        if (!$config_whitelabel_enabled) {
            echo '<br><small class="text-muted">Powered by ITFlow</small>';
        }
    ?>
</p>


<?php require_once "../inc_confirm_modal.php"; ?>

<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!--- TinyMCE -->
<script src="../plugins/tinymce/tinymce.min.js" referrerpolicy="origin"></script>

<script>
    
    // Initialize TinyMCE
    tinymce.init({
        selector: '.tinymce',
        browser_spellcheck: true,
        resize: true,
        min_height: 300,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: [
            { name: 'styles', items: [ 'styles' ] },
            { name: 'formatting', items: [ 'bold', 'italic', 'forecolor' ] },
            { name: 'lists', items: [ 'bullist', 'numlist' ] },
            { name: 'alignment', items: [ 'alignleft', 'aligncenter', 'alignright', 'alignjustify' ] },
            { name: 'indentation', items: [ 'outdent', 'indent' ] },
            { name: 'table', items: [ 'table' ] },
            { name: 'extra', items: [ 'fullscreen' ] }
        ],
        mobile: {
        menubar: false,
        plugins: 'autosave lists autolink',
        toolbar: 'undo bold italic styles'
    },
        plugins: 'link image lists table code codesample fullscreen autoresize',
    });

</script>

<script src="../js/pretty_content.js"></script>

<script src="../js/confirm_modal.js"></script>
