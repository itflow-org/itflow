<?php

/**
 * Flash alert rendering.
 *
 * Renders the session flash message as a toast, replacing toastr and its jQuery
 * dependency without adding a library. It borrows Bootstrap's .toast styling
 * but NOT its Toast component: the element ships with .show already on it and
 * the whole appear/linger/fade cycle is a CSS animation, so the alert is on
 * screen at first paint. Driving it with bootstrap.Toast meant waiting for
 * DOMContentLoaded, which does not fire until every parser-blocking script has
 * run - so it used to arrive well after the page had settled. See the
 * .itflow-toast block in css/itflow_custom.css.
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
    $alert_style_class = alertStyleClass($alert_type);
    $alert_style = 'text-bg-' . $alert_style_class;
    // text-bg-info and text-bg-warning are the two Bootstrap pairs with dark text
    $alert_dark_text = in_array($alert_style_class, ['warning', 'info'], true);

    // Font Awesome 5 names - the vendored build is 5.15.4, so no fa-circle-*
    // aliases. Keyed on alertStyleClass()' output rather than the raw type, so
    // 'error', 'errpr' and 'danger' all land on the same icon the same way they
    // already land on the same colour.
    $alert_icons = [
        'success'   => 'fa-check-circle',
        'info'      => 'fa-info-circle',
        'warning'   => 'fa-exclamation-triangle',
        'danger'    => 'fa-times-circle',
        'secondary' => 'fa-bell',
    ];
    $alert_icon = $alert_icons[$alert_style_class] ?? 'fa-bell';

    // Escaping lives in one place now - see alertMessageHtml() in functions/sanitize.php
    $alert_safe_message = alertMessageHtml($_SESSION['alert_message']);

    ?>

    <?php /* `show` is on the element from the server, and `fade` is gone: the
             appearance is a CSS animation now, so the alert is on screen at
             first paint instead of waiting for bootstrap.Toast. The old version
             called it on DOMContentLoaded, which does not fire until every
             parser-blocking script in the document has run - most of a megabyte
             on a page with tinymce - so the alert turned up after you had
             already started reading. toastr never had that problem because it
             loaded in the header. */ ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
        <div id="itflowFlashToast"
             class="toast show itflow-toast align-items-center <?= $alert_style ?> border-0"
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex align-items-center">
                <i class="fas fa-fw <?= $alert_icon ?> fa-lg ms-3"></i>
                <div class="toast-body"><?= $alert_safe_message ?></div>
                <?php /* No data-bs-dismiss - the click handler below covers the
                         whole toast, and this button sits inside it. */ ?>
                <button type="button"
                        class="btn-close <?= $alert_dark_text ? '' : 'btn-close-white' ?> me-2 m-auto"
                        aria-label="Close"></button>
            </div>
            <div class="itflow-toast-progress"></div>
        </div>
    </div>

    <script>
        (function () {
            // Deliberately depends on nothing. It runs inline, right after the
            // markup above, and does not wait for DOMContentLoaded - showing
            // and hiding is CSS, so if this never ran the alert would still
            // appear and still go away on its own.
            var el = document.getElementById('itflowFlashToast');
            if (!el) {
                return;
            }

            // Click anywhere on it to dismiss, the way toastr did. The close
            // button is inside the toast, so this covers it too.
            el.addEventListener('click', function () {
                el.remove();
            });

            // Remove on the fade rather than a fixed timer, so hovering to
            // finish reading a long message keeps the node alive as well as
            // visible.
            el.addEventListener('animationend', function (event) {
                if (event.animationName === 'itflow-toast-out') {
                    el.remove();
                }
            });
        })();
    </script>

    <?php

    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);

}

?>
