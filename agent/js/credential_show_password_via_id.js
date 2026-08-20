function showPasswordViaCredentialID(button, credential_id) {
    // Send a GET request to ajax.php as ajax.php?get_credential_via_id=true&credential_id=ID
    itflowGet(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = JSON.parse(data);

            // (Re)create the popover with the fetched password and show it
            //  trigger: focus dismisses it when the user clicks away
            bootstrap.Popover.getInstance(button)?.dispose();
            new bootstrap.Popover(button, {
                content: credential.password,
                placement: 'top',
                trigger: 'focus'
            }).show();
        }
    );
}

function copyPasswordViaCredentialID(button, credential_id) {
    itflowGet(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = JSON.parse(data);

            navigator.clipboard.writeText(credential.password).then(function() {
                // Same "Copied!" flash the ClipboardJS handler in app.js uses
                flashTooltip(button, 'Copied!');
            });
        }
    );
}