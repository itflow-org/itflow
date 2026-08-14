<?php
?>

<?php
if (basename(dirname($_SERVER['REQUEST_URI'])) === 'admin') { ?>
    <p class="text-end fw-light">ITFlow <?= APP_VERSION ?> &nbsp; · &nbsp; <a target="_blank" href="https://docs.itflow.org">Docs</a> &nbsp; · &nbsp; <a target="_blank" href="https://forum.itflow.org">Forum</a> &nbsp; · &nbsp; <a target="_blank" href="https://services.itflow.org">Services</a></p>
    <br>
<?php } ?>
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
</div> <!-- /.app-wrapper -->

<!-- Set the browser window title to the clients name -->
<script>document.title = <?= json_encode("$tab_title - $page_title") ?>;</script>

<!-- REQUIRED SCRIPTS -->

<!-- Bootstrap 5 -->
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/http.js"></script>

<!-- Custom js-->
<script src="/libs/chart.js/chart.umd.min.js"></script>
<script src="/libs/flatpickr/js/flatpickr.min.js"></script>
<script src="/libs/tom-select/js/tom-select.complete.min.js"></script>
<script src="/libs/imask/js/imask.min.js"></script>
<script src="/libs/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/libs/clipboardjs/clipboard.min.js"></script>
<script src="/js/keepalive.js"></script>
<script src="/libs/DataTables/datatables.min.js"></script>
<script src="/libs/intl-tel-input/js/intlTelInput.min.js"></script>

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
