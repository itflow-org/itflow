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
        echo escapeHtml($session_company_name);
        if (!$config_whitelabel_enabled) {
            echo '<br><small class="text-muted">Powered by ITFlow</small>';
        }
    ?>
</p>



<!-- jQuery -->

<!-- Bootstrap 4 -->
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/http.js"></script>

<!--- TinyMCE -->
<script src="/libs/tinymce/tinymce.min.js" referrerpolicy="origin"></script>

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
        license_key: 'gpl',
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
        toolbar: 'undo bold italic styles',
    },
        plugins: 'link image lists table code codesample fullscreen autoresize',
    });

</script>

<script src="/js/pretty_content.js"></script>

<script src="/libs/sweetalert2/js/sweetalert2.min.js"></script>
<script src="/js/confirm_modal.js"></script>

<?php if (!empty($portal_load_phone_inputs)) { ?>
    <script src="/libs/intl-tel-input/js/intlTelInputWithUtils.min.js"></script>
    <script src="/js/phone_inputs.js"></script>
<?php } ?>

<?php if (!empty($portal_load_datatables)) { ?>
    <script src="/libs/DataTables/datatables.min.js"></script>
    <script src="/js/portal_datatables.js"></script>
<?php } ?>

<script src="/js/keepalive.js"></script>

</body>
</html>
