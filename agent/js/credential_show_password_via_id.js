function showPasswordViaCredentialID(button, credential_id) {
    // Send a GET request to ajax.php as ajax.php?get_credential_via_id=true&credential_id=ID
    jQuery.get(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = JSON.parse(data);

            // (Re)create the popover with the fetched password and show it
            //  trigger: focus dismisses it when the user clicks away
            jQuery(button).popover('dispose').popover({
                content: credential.password,
                placement: 'top',
                trigger: 'focus'
            }).popover('show');
        }
    );
}

function copyPasswordViaCredentialID(button, credential_id) {
    jQuery.get(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = JSON.parse(data);

            navigator.clipboard.writeText(credential.password).then(function() {
                // Flash "Copied!" tooltip, matching the ClipboardJS success handler in app.js
                jQuery(button).tooltip('hide')
                    .attr('data-original-title', 'Copied!')
                    .tooltip('show');

                setTimeout(function() {
                    jQuery(button).tooltip('hide');
                }, 1000);
            });
        }
    );
}