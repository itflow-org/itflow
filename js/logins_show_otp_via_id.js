function showOTPViaLoginID(login_id) {
    // Send a GET request to ajax.php as ajax.php?get_totp_token_via_id=true&login_id=ID
    jQuery.get(
        "ajax.php", {
            get_totp_token_via_id: 'true',
            login_id: login_id
        },
        function(data) {
            //If we get a response from post.php, parse it as JSON
            const token = JSON.parse(data);

            document.getElementById("otp_" + login_id).innerText = token

        }
    );
}
