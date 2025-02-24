function populateCertificateEditModal(client_id, certificate_id) {

    // Send a GET request to post.php as post.php?certificate_get_json_details=true&client_id=NUM&certificate_id=NUM
    jQuery.get(
        "ajax.php",
        {certificate_get_json_details: 'true', client_id: client_id, certificate_id: certificate_id},
        function(data) {

            // If we get a response from post.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the certificate (one) and domains (multiple)
            const certificate = response.certificate[0];
            const domains = response.domains;

            // Populate the cert modal fields
            document.getElementById("editCertificateHeader").innerText = certificate.certificate_name;
            document.getElementById("editCertificateId").value = certificate_id;
            document.getElementById("editCertificateName").value = certificate.certificate_name;
            document.getElementById("editCertificateDescription").value = certificate.certificate_description;
            document.getElementById("editCertificateDomain").value = certificate.certificate_domain;
            document.getElementById("editCertificateIssuedBy").value = certificate.certificate_issued_by;
            document.getElementById("editCertificateExpire").value = certificate.certificate_expire;
            document.getElementById("editCertificatePublicKey").value = certificate.certificate_public_key;
            document.getElementById("editCertificateNotes").value = certificate.certificate_notes;

            // Select the domain dropdown
            var domainDropdown = document.getElementById("editDomainId");

            // Clear domain dropdown
            var i, L = domainDropdown.options.length -1;
            for(i = L; i >= 0; i--) {
                domainDropdown.remove(i);
            }
            domainDropdown[domainDropdown.length] = new Option('- Domain -', '0');

            // Populate domain dropdown
            domains.forEach(domain => {
                if (parseInt(domain.domain_id) == parseInt(certificate.certificate_domain_id)) {
                    // Selected domain
                    domainDropdown[domainDropdown.length] = new Option(domain.domain_name, domain.domain_id, true, true);
                }
                else{
                    domainDropdown[domainDropdown.length] = new Option(domain.domain_name, domain.domain_id);
                }
            });
        }
    );
}
