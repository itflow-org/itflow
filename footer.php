<?php require_once "inc_confirm_modal.php";
 ?>

</div><!-- /.container-fluid -->
</div>
<!-- /.content -->
</div>
<!-- /.content-wrapper -->
</div>
<!-- ./wrapper -->

<!-- reset values in modals after hiding/dismissal -->
<script>
$(document).ready(function() {
    // exclude modals having an ID starting with 'add'
    $('body').on('hidden.bs.modal', 'div.modal:not([id^="add"])', function () {
       // reset the form
       $(this).find('form').trigger('reset');
    });
});
</script>

<!-- REQUIRED SCRIPTS -->

<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom js-->
<script src="plugins/moment/moment.min.js"></script>
<script src="plugins/chart.js/Chart.min.js"></script>
<script src="plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src='plugins/daterangepicker/daterangepicker.js'></script>
<script src='plugins/select2/js/select2.min.js'></script>
<script src='plugins/inputmask/jquery.inputmask.min.js'></script>
<script src="plugins/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<script src="plugins/clipboardjs/clipboard.min.js"></script>
<script src="js/keepalive.js"></script>

<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>
<script src="js/app.js"></script>
<script src="js/confirm_modal.js"></script>
</body>
</html>

<?php

// Calculate Execution time Uncomment for test

//$time_end = microtime(true);
//$execution_time = ($time_end - $time_start);
//echo 'Total Execution Time: '.number_format((float) $execution_time, 10) .' seconds';
