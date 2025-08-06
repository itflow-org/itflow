<?php
require_once $path_prefix . "includes/inc_confirm_modal.php";
?>

<?php
if (basename(dirname($_SERVER['REQUEST_URI'])) === 'admin') { ?>
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
<script src="<?= $path_prefix ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom js-->
<script src="<?= $path_prefix ?>plugins/moment/moment.min.js"></script>
<script src="<?= $path_prefix ?>plugins/chart.js/Chart.min.js"></script>
<script src="<?= $path_prefix ?>plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="<?= $path_prefix ?>plugins/daterangepicker/daterangepicker.js"></script>
<script src="<?= $path_prefix ?>plugins/select2/js/select2.min.js"></script>
<script src="<?= $path_prefix ?>plugins/inputmask/jquery.inputmask.min.js"></script>
<script src="<?= $path_prefix ?>plugins/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="<?= $path_prefix ?>plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<script src="<?= $path_prefix ?>plugins/clipboardjs/clipboard.min.js"></script>
<script src="<?= $path_prefix ?>js/keepalive.js"></script>
<script src="<?= $path_prefix ?>plugins/DataTables/datatables.min.js"></script>
<script src="<?= $path_prefix ?>plugins/intl-tel-input/js/intlTelInput.min.js"></script>

<!-- AdminLTE App -->
<script src="<?= $path_prefix ?>plugins/adminlte/js/adminlte.min.js"></script>
<script src="<?= $path_prefix ?>js/app.js"></script>
<script src="<?= $path_prefix ?>js/ajax_modal.js"></script>
<script src="<?= $path_prefix ?>js/confirm_modal.js"></script>

</body>
</html>

<?php

// Calculate Execution time Uncomment for test

//$time_end = microtime(true);
//$execution_time = ($time_end - $time_start);
//echo '<h2>Total Execution Time: '.number_format((float) $execution_time, 10) .' seconds</h2>';
