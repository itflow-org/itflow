function populateDomainEditModal(client_id, domain_id) {

    // Send a GET request to post.php as post.php?domain_get_json_details=true&client_id=NUM&domain_id=NUM
    jQuery.get(
        "ajax.php",
        {domain_get_json_details: 'true', client_id: client_id, domain_id: domain_id},
        function(data) {

            // If we get a response from post.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the domain info (one), registrars (multiple) and webhosts (multiple)
            const domain = response.domain[0];
            const vendors = response.vendors;

            // Populate the domain modal fields
            document.getElementById("editDomainHeader").innerText = domain.domain_name;
            document.getElementById("editDomainId").value = domain_id;
            document.getElementById("editDomainName").value = domain.domain_name;
            document.getElementById("editDomainDescription").value = domain.domain_description;
            document.getElementById("editDomainExpire").value = domain.domain_expire;
            document.getElementById("editDomainNotes").value = domain.domain_notes;
            document.getElementById("editDomainIP").value = domain.domain_ip;
            document.getElementById("editDomainNameServers").value = domain.domain_name_servers;
            document.getElementById("editDomainMailServers").value = domain.domain_mail_servers;
            document.getElementById("editDomainTxtRecords").value = domain.domain_txt;
            document.getElementById("editDomainRawWhois").value = domain.domain_raw_whois;

            /* DROPDOWNS */

            // Registrar dropdown
            var registrarDropdown = document.getElementById("editDomainRegistrarId");

            // Clear registrar dropdown
            var i, L = registrarDropdown.options.length -1;
            for(i = L; i >= 0; i--) {
                registrarDropdown.remove(i);
            }
            registrarDropdown[registrarDropdown.length] = new Option('- Vendor -', '0');

            // Populate dropdown
            vendors.forEach(vendor => {
                if (parseInt(vendor.vendor_id) == parseInt(domain.domain_registrar)) {
                    // Selected domain
                    registrarDropdown[registrarDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id, true, true);
                }
                else{
                    registrarDropdown[registrarDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id);
                }
            });

            // Webhost dropdown
            var webhostDropdown = document.getElementById("editDomainWebhostId");

            // Clear registrar dropdown
            var i, L = webhostDropdown.options.length -1;
            for(i = L; i >= 0; i--) {
                webhostDropdown.remove(i);
            }
            webhostDropdown[webhostDropdown.length] = new Option('- Vendor -', '0');

            // Populate dropdown
            vendors.forEach(vendor => {
                if (parseInt(vendor.vendor_id) == parseInt(domain.domain_webhost)) {
                    // Selected domain
                    webhostDropdown[webhostDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id, true, true);
                }
                else{
                    webhostDropdown[webhostDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id);
                }
            });


        }
    );
}
