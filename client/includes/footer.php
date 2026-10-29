<?php
/*
 * Client Portal
 * HTML Footer
 */
?>

</main>

<?php /* The body is a flex column (header.php), so mt-auto here takes up the
         height a short page leaves and the bar sits on the bottom of the
         window rather than right under the content. On a phone it clears the
         tab bar along the bottom. */ ?>
<footer class="itflow-portal-footer mt-auto">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
        <span><?= escapeHtml($session_company_name) ?></span>
        <?php if (!$config_whitelabel_enabled) { ?>
            <span>Powered by ITFlow</span>
        <?php } ?>
    </div>
</footer>

<script src="/js/itflow_select.js"></script>
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/http.js"></script>

<!--- TinyMCE - set up on any .tinymce by js/portal.js -->
<script src="/libs/tinymce/tinymce.min.js" referrerpolicy="origin"></script>

<script src="/js/pretty_content.js"></script>

<script src="/libs/sweetalert2/js/sweetalert2.min.js"></script>
<script src="/js/confirm_modal.js"></script>
<script src="/js/autosubmit.js"></script>

<?php if (!empty($portal_load_phone_inputs)) { ?>
    <script src="/libs/intl-tel-input/js/intlTelInputWithUtils.min.js"></script>
    <script src="/js/phone_inputs.js"></script>
<?php } ?>

<?php if (!empty($portal_load_datatables)) { ?>
    <script src="/libs/DataTables/datatables.min.js"></script>
    <script src="/js/portal_datatables.js"></script>
<?php } ?>

<script src="/js/keepalive.js"></script>
<script src="/js/portal.js"></script>

<?php if (!empty($portal_stripe)) { ?>
    <!-- Taking a card - Stripe's own script, then the portal's use of it -->
    <script src="https://js.stripe.com/v3/"></script>
    <script src="/js/portal_stripe.js"></script>
<?php } ?>

</body>
</html>
