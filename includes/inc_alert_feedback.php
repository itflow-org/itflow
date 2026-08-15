<?php

/**
 * Flash alert rendering.
 *
 * Renders the session flash message as a Bootstrap 5 toast. Bootstrap's Toast
 * component ships in bootstrap.bundle.min.js, which is already loaded, so this
 * replaces toastr (and its jQuery dependency) without adding a library.
 *
 * SECURITY: the message is rendered into HTML here, not interpolated into a
 * JavaScript string literal as it was previously. 429 of the ~653 flashAlert()
 * call sites interpolate PHP variables, many of them user-controlled (custom
 * link names, ticket status names, saved payment descriptions), and the old
 * form allowed a stored value containing a quote or </script> to break out and
 * execute. Everything is escaped, then a fixed allowlist of formatting tags is
 * restored so the existing <strong> markup in those messages still renders.
 */

if (!empty($_SESSION['alert_message'])) {

    $alert_type = $_SESSION['alert_type'] ?? 'success';

    // One mapping for both renderers - see alertStyleClass() in
    // functions/sanitize.php. flashAlert() is called with more type names than
    // Bootstrap has classes ('danger', 'alert' and a typo'd 'errpr' among
    // them), and an unmapped value used to resolve to nothing at all.
    $alert_style = 'text-bg-' . alertStyleClass($alert_type);
    // text-bg-info and text-bg-warning are the two Bootstrap pairs with dark text
    $alert_dark_text = in_array(alertStyleClass($alert_type), ['warning', 'info'], true);

    // Escaping lives in one place now - see alertMessageHtml() in functions/sanitize.php
    $alert_safe_message = alertMessageHtml($_SESSION['alert_message']);

    ?>

    <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1090;">
        <div id="itflowFlashToast"
             class="toast fade align-items-center <?= $alert_style ?> border-0"
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?= $alert_safe_message ?></div>
                <button type="button"
                        class="btn-close <?= $alert_dark_text ? '' : 'btn-close-white' ?> me-2 m-auto"
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            // This include runs near the top of the page but bootstrap.bundle
            // loads in the footer, so the toast has to wait for scripts to
            // finish. toastr did not need this - it was loaded in the header.
            function showFlashToast() {
                var el = document.getElementById('itflowFlashToast');
                if (el && window.bootstrap && bootstrap.Toast) {
                    bootstrap.Toast.getOrCreateInstance(el, {
                        animation: true,
                        autohide: true,
                        delay: 5000
                    }).show();
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', showFlashToast);
            } else {
                showFlashToast();
            }
        })();
    </script>

    <?php

    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);

}

?>
