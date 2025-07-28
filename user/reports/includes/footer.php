<?php
require_once "../includes/inc_confirm_modal.php";
?>

<?php
if (str_contains(basename($_SERVER["PHP_SELF"]), "admin_")) { ?>
    <p class="text-right font-weight-light">ITFlow <?php echo APP_VERSION ?> &nbsp; · &nbsp; <a target="_blank" href="https://docs.itflow.org">Docs</a> &nbsp; · &nbsp; <a target="_blank" href="https://forum.itflow.org">Forum</a> &nbsp; · &nbsp; <a target="_blank" href="https://services.itflow.org">Services</a></p>
    <br>
<?php } ?>

</div><!-- /.container-fluid -->
</div> <!-- /.content -->
</div> <!-- /.content-wrapper -->
</div> <!-- ./wrapper -->

<!-- Set the browser window title to the clients name -->
<script>document.title = <?php echo json_encode("$tab_title - $page_title"); ?>;</script>

<!-- REQUIRED SCRIPTS -->

<!-- Bootstrap 4 -->
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom js-->
<script src="../../plugins/moment/moment.min.js"></script>
<script src="../../plugins/chart.js/Chart.min.js"></script>
<script src="../../plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src='../../plugins/daterangepicker/daterangepicker.js'></script>
<script src='../../plugins/select2/js/select2.min.js'></script>
<script src='../../plugins/inputmask/jquery.inputmask.min.js'></script>
<script src="../../plugins/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="../../plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<script src="../../plugins/clipboardjs/clipboard.min.js"></script>
<script src="../../js/keepalive.js"></script>
<script src="../../plugins/DataTables/datatables.min.js"></script>
<script src="../../plugins/intl-tel-input/js/intlTelInput.min.js"></script>

<!-- AdminLTE App -->
<script src="../../plugins/adminlte/js/adminlte.min.js"></script>
<script src="../../js/app.js"></script>
<script src="../../js/ajax_modal.js"></script>
<script src="../../js/confirm_modal.js"></script>

</body>
</html>

<?php

// Calculate Execution time Uncomment for test

//$time_end = microtime(true);
//$execution_time = ($time_end - $time_start);
//echo '<h2>Total Execution Time: '.number_format((float) $execution_time, 10) .' seconds</h2>';
