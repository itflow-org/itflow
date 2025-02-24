function generatePassword(login_id) {
    // Send a GET request to ajax.php as ajax.php?get_readable_pass=true
    jQuery.get(
        "ajax.php", {
            get_readable_pass: 'true'
        },
        function(data) {
            //If we get a response from post.php, parse it as JSON
            const password = JSON.parse(data);

            document.getElementById("password").value = password;
        }
    );
}
