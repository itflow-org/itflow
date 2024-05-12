function fetchSSL(type)
{
    // Get the domain name input & issued/expire/key fields, based on whether this is a new cert or updating an existing
    if (type == 'new') {
        var domain = document.getElementById("domain").value;
        var issuedBy = document.getElementById("issuedBy");
        var expire = document.getElementById("expire");
        var publicKey = document.getElementById("publicKey");

    }
    if (type == 'edit') {
        var domain = document.getElementById("editCertificateDomain").value;
        var issuedBy = document.getElementById("editCertificateIssuedBy");
        var expire = document.getElementById("editCertificateExpire");
        var publicKey = document.getElementById("editCertificatePublicKey");
    }

    //Send a GET request to post.php as post.php?certificate_fetch_parse_json_details=TRUE&domain=DOMAIN
    jQuery.get(
        "ajax.php",
        {certificate_fetch_parse_json_details: 'TRUE', domain: domain},
        function(data) {
            //If we get a response from post.php, parse it as JSON
            const ssl_data = JSON.parse(data);

            if (ssl_data.success == "TRUE") {
                // Fill the form fields with the cert data
                issuedBy.value = ssl_data.issued_by;
                expire.value = ssl_data.expire;
                publicKey.value = ssl_data.public_key;
            }
            else{
                alert("Error whilst parsing/retrieving details for domain")
            }
        }
    );
}
