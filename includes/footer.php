<?php
?>

<?php
if (basename(dirname($_SERVER['REQUEST_URI'])) === 'guest') { ?>
<p class="text-center">
    <?php
        echo escapeHtml($session_company_name);
        if (!$config_whitelabel_enabled) {
            echo '<br><small class="text-muted">Powered by ITFlow</small>';
        }
    ?>
</p>
<?php } ?>

</div><!-- /.container-fluid -->
</div> <!-- /.app-content -->
</main> <!-- /.app-main -->

<?php /* AdminLTE 4's .app-wrapper is a three row grid - header, main, footer -
         and the footer row is already reserved (grid-template-rows:
         min-content 1fr min-content). Sitting in that row is what pins this to
         the bottom: the 1fr main row absorbs the leftover height, so on a short
         page the bar lands on the bottom edge of the viewport instead of
         floating directly under the content.

         It has to be a direct child of .app-wrapper - inside .app-main it is
         just content again. */ ?>
<?php if (basename(dirname($_SERVER['REQUEST_URI'])) === 'admin') { ?>
<footer class="app-footer py-2 d-flex align-items-center">
    <div class="w-100 text-end fw-light">ITFlow <?= APP_VERSION ?> &nbsp; · &nbsp; <a target="_blank" href="https://docs.itflow.org">Docs</a> &nbsp; · &nbsp; <a target="_blank" href="https://forum.itflow.org">Forum</a> &nbsp; · &nbsp; <a target="_blank" href="https://services.itflow.org">Services</a></div>
</footer>
<?php } ?>

</div> <!-- /.app-wrapper -->

<!-- Set the browser window title to the clients name -->
<script>document.title = <?= json_encode("$tab_title - $page_title") ?>;</script>

<!-- REQUIRED SCRIPTS -->

<?php /* Tom Select goes FIRST, ahead of Bootstrap itself. Order is load-bearing:
         until js/tom_select.js runs, the browser is showing the raw <select>
         controls it painted while parsing the body, so every byte in front of
         it is time spent looking at the wrong widget. Nothing here is needed to
         turn a <select> into a Tom Select, so nothing goes in front of it.
         includes/header.php preloads both files. See js/tom_select.js.

         Bootstrap moving down is safe: it has always loaded in the footer, so
         no markup above this point could ever have used the `bootstrap` global
         at parse time. Every call site is inside an event handler or waits for
         DOMContentLoaded - includes/inc_alert_feedback.php says so in its own
         comment - and all of those still run well after this block. */ ?>
<script src="/libs/tom-select/js/tom-select.complete.min.js"></script>
<script src="/js/tom_select.js"></script>

<!-- Bootstrap 5 -->
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/http.js"></script>

<!-- Custom js-->
<script src="/libs/chart.js/chart.umd.min.js"></script>
<script src="/libs/flatpickr/js/flatpickr.min.js"></script>
<script src="/libs/imask/js/imask.min.js"></script>
<script src="/libs/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/libs/clipboardjs/clipboard.min.js"></script>
<script src="/js/keepalive.js"></script>
<script src="/libs/DataTables/datatables.min.js"></script>
<script src="/libs/intl-tel-input/js/intlTelInputWithUtils.min.js"></script>
<script src="/js/phone_inputs.js"></script>

<!-- AdminLTE App -->
<script src="/libs/adminlte/js/adminlte.min.js"></script>
<script src="/libs/sweetalert2/js/sweetalert2.min.js"></script>
<script src="/js/autocomplete.js"></script>
<script src="/js/app.js"></script>
<script src="/js/ajax_modal.js"></script>
<script src="/js/confirm_modal.js"></script>
<script src="/js/date_filter.js"></script>

</body>
</html>

<?php

// Calculate Execution time Uncomment for test

//$time_end = microtime(true);
//$execution_time = ($time_end - $time_start);
//echo '<h2>Total Execution Time: '.number_format((float) $execution_time, 10) .' seconds</h2>';
