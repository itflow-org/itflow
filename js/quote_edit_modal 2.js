function populateQuoteEditModal(quote_id) {

    // Send a GET request to ajax.php as ajax.php?quote_get_json_details=true&quote_id=NUM
    jQuery.get(
        "ajax.php",
        {quote_get_json_details: 'true', quote_id: quote_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the quote info (one) and categories (multiple)
            const quote = response.quote[0];
            const categories = response.categories;

            // Populate the quote modal fields
            document.getElementById("editQuoteHeaderID").innerText = quote.quote_prefix + quote.quote_number;
            document.getElementById("editQuoteHeaderClient").innerText = quote.client_name;
            document.getElementById("editQuoteID").value = quote.quote_id;
            document.getElementById("editQuoteDate").value = quote.quote_date;
            document.getElementById("editQuoteExpire").value = quote.quote_expire;
            document.getElementById("editQuoteScope").value = quote.quote_scope;

            /* DROPDOWNS */

            // Category dropdown
            var categoryDropdown = document.getElementById("editQuoteCategory");

            // Clear Category dropdown
            var i, L = categoryDropdown.options.length -1;
            for (i = L; i >= 0; i--) {
                categoryDropdown.remove(i);
            }
            categoryDropdown[categoryDropdown.length] = new Option('- Category -', '0');

            // Populate dropdown
            categories.forEach(category => {
                if (parseInt(category.category_id) == parseInt(quote.quote_category_id)) {
                    // Selected quote
                    categoryDropdown[categoryDropdown.length] = new Option(category.category_name, category.category_id, true, true);
                }
                else{
                    categoryDropdown[categoryDropdown.length] = new Option(category.category_name, category.category_id);
                }
            });

        }
    );
}
