/*
 * Product autocomplete for the add-item row on invoices, quotes and recurring
 * invoices.
 *
 * The three pages used to carry a copy of this each, and they drifted - the
 * quote and recurring invoice copies were fed a four column product query, so
 * item.product_name was undefined and selecting a product wrote the literal
 * string "undefined" into the item name.
 *
 * Call with the array emitted by getProductsForAutocomplete():
 *
 *     initProductAutocomplete(<?= $json_products ?? '[]' ?>);
 */
function initProductAutocomplete(availableProducts) {

    var nameInput = document.getElementById('name');
    if (!nameInput) {
        return;
    }

    // Quote and recurring invoice do not store the product link yet, so treat
    // the hidden input as optional rather than throwing on every keystroke.
    var productIdInput = document.getElementById('product_id');

    itflowAutocomplete(nameInput, {
        minLength: 1,
        source: availableProducts || [],
        match: function (item, term) {
            return String(item.label || '').toLowerCase().indexOf(term) !== -1
                || String(item.product_name || '').toLowerCase().indexOf(term) !== -1
                || String(item.product_code || '').toLowerCase().indexOf(term) !== -1;
        },
        render: function (item) {
            var esc = itflowEscapeHtml;
            var typeText = item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1).toLowerCase() : "";
            var showStock = (typeText.toLowerCase() !== "service");
            var taxText = (item.tax_percent != null) ? (parseFloat(item.tax_percent) + "%") : "No tax";
            var priceText = (item.price != null && item.price !== "") ? String(item.price) : "";
            var stockText = (item.available_stock ?? 0);

            return "<div class='d-flex justify-content-between align-items-start'>" +
                       "<div class='flex-fill pe-2'>" +
                           "<div class='fw-bold'>" + esc(item.label) +
                               (typeText ? " <small class='text-muted'>(" + esc(typeText) + ")</small>" : "") +
                           "</div>" +
                           "<div class='small text-muted'>" + esc(item.description) + "</div>" +
                           "<div class='mt-1'>" +
                               "<span class='badge bg-secondary me-1'>Tax: " + esc(taxText) + "</span>" +
                               (showStock ? "<span class='badge " + (stockText > 0 ? "bg-success" : "bg-danger") + "'>Stock: " + esc(stockText) + "</span>" : "") +
                           "</div>" +
                       "</div>" +
                       "<div class='text-end'>" +
                           "<div class='fw-bold'>" + esc(priceText) + "</div>" +
                       "</div>" +
                   "</div>";
        },
        onSelect: function (item) {
            nameInput.value = item.product_name;
            document.getElementById('desc').value = item.description;
            document.getElementById('qty').value = 1;
            document.getElementById('price').value = item.price;
            setTomSelectValue(document.getElementById('tax'), item.tax);
            if (productIdInput) {
                productIdInput.value = item.prod_id;
            }
        }
    });

    // Typing over the name by hand breaks the link to the product
    if (productIdInput) {
        nameInput.addEventListener('input', function () {
            productIdInput.value = 0;
        });
    }

}
