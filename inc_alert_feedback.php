<?php

//Alert Feedback
if (!empty($_SESSION['alert_message'])) {
    if (!isset($_SESSION['alert_type'])) {
        $_SESSION['alert_type'] = "success";
    }
    ?>

    <script type="text/javascript">

        toastr.options = {
            "closeButton": false,
            "debug": false,
            "newestOnTop": false,
            "progressBar": false,
            "positionClass": "toast-top-center",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        }

        toastr["<?php echo $_SESSION['alert_type']; ?>"]("<?php echo $_SESSION['alert_message']; ?>")

    </script>

    <?php

    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);

}

?>
