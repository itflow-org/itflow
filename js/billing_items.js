/*
 * The lines on an invoice, quote or recurring invoice page
 * (includes/billing_paper.php):
 *
 *  - the add-a-line row: type a product's name or code and pick it to fill
 *    in its description, price and tax; the amount it comes to shows as you
 *    type; after adding, the page comes back here ready for the next
 *  - drag a line by its handle to move it - saved as it drops
 *  - the line just edited is picked out when the page comes back
 *
 * Call once the page has loaded:
 *
 *     itflowBillingItems({ products: [...], csrf: '...', currency: 'USD', locale: 'en-US' });
 *
 * products is what getProductsForAutocomplete() gives.
 *
 * itflowBillingLineForm(form, options) does the product pick and the amount
 * for any other line form - the Edit Line window uses it.
 */

function itflowBillingMoney(amount, currency, locale) {
    try {
        return new Intl.NumberFormat(locale || undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch (e) {
        return (Math.round(amount * 100) / 100).toFixed(2);
    }
}

/* A number from a form field - "1,250.50", "$99" - or NaN */
function itflowBillingNumber(value) {
    var text = String(value == null ? '' : value).replace(/[\s,$€£]/g, '');
    return text === '' ? NaN : Number(text);
}

/* A product's default billing in words - "Monthly, per user" - or '' for one time */
function itflowBillingBilledText(frequency, seat) {
    var label = { month: 'Monthly', quarter: 'Quarterly', year: 'Yearly' }[frequency || ''];
    if (!label) {
        return '';
    }
    var per = { contacts: 'per user', assets: 'per device' }[seat || ''];
    return label + (per ? ', ' + per : '');
}

/* Choose an option by value, when the select has it, and let the select's own handlers know */
function itflowBillingPick(select, value) {
    var has = Array.prototype.some.call(select.options, function (option) {
        return option.value === value && !option.disabled;
    });
    if (has && select.value !== value) {
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function itflowBillingLineForm(root, options) {
    if (!root) {
        return;
    }
    var opts = options || {};
    var name = root.querySelector('[data-line-name]');
    var description = root.querySelector('[data-line-description]');
    var qty = root.querySelector('[data-line-qty]');
    var price = root.querySelector('[data-line-price]');
    var tax = root.querySelector('[data-line-tax]');
    var preview = root.querySelector('[data-line-preview]');
    // The hidden product id can sit in a separate form (the add row's inputs use form="")
    var product = root.querySelector('[data-line-product]') || (opts.productInput || null);

    function update() {
        if (!preview) {
            return;
        }
        var q = itflowBillingNumber(qty && qty.value !== '' ? qty.value : '1');
        var p = itflowBillingNumber(price ? price.value : '');
        if (isNaN(q) || isNaN(p)) {
            preview.textContent = price && price.value !== '' ? 'Not a number' : '';
            return;
        }
        var option = tax && tax.options[tax.selectedIndex];
        var percent = option ? Number(option.getAttribute('data-percent') || 0) : 0;
        var subtotal = Math.round(q * p * 100) / 100;
        var total = subtotal + Math.round(subtotal * percent) / 100;
        preview.textContent = itflowBillingMoney(total, opts.currency, opts.locale);
    }

    [qty, price, tax].forEach(function (el) {
        if (el) {
            el.addEventListener('input', update);
            el.addEventListener('change', update);
        }
    });
    update();

    // The description grows with what is typed into it
    if (description && description.tagName === 'TEXTAREA') {
        var grow = function () {
            description.style.height = 'auto';
            description.style.height = Math.min(description.scrollHeight + 2, 240) + 'px';
        };
        description.addEventListener('input', grow);
        if (description.value) {
            grow();
        }
    }

    if (!name || !opts.products || !opts.products.length || typeof itflowAutocomplete !== 'function') {
        return;
    }

    itflowAutocomplete(name, {
        minLength: 1,
        source: opts.products,
        match: function (item, term) {
            return String(item.label || '').toLowerCase().indexOf(term) !== -1
                || String(item.product_name || '').toLowerCase().indexOf(term) !== -1
                || String(item.product_code || '').toLowerCase().indexOf(term) !== -1;
        },
        render: function (item) {
            var esc = itflowEscapeHtml;
            var isService = String(item.type || '').toLowerCase() === 'service';
            var tracked = String(item.track_stock) === '1';
            var stock = Number(item.available_stock || 0);
            var billed = itflowBillingBilledText(item.frequency, item.seat_source);
            return "<div class='d-flex justify-content-between align-items-start gap-3'>" +
                "<div class='min-w-0'>" +
                    "<div class='fw-semibold'>" + esc(item.label) + "</div>" +
                    (item.description ? "<div class='small text-body-secondary text-truncate'>" + esc(item.description) + "</div>" : "") +
                    "<div class='small mt-1'>" +
                        "<span class='badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal me-1'>" + (isService ? 'Service' : 'Product') + "</span>" +
                        (item.tax_percent != null ? "<span class='badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal me-1'>Tax " + esc(parseFloat(item.tax_percent)) + "%</span>" : "") +
                        (billed ? "<span class='badge rounded-pill bg-info-subtle text-info-emphasis fw-normal me-1'><i class='fas fa-redo-alt me-1'></i>" + esc(billed) + "</span>" : "") +
                        (tracked ? "<span class='badge rounded-pill fw-normal " + (stock > 0 ? "bg-success-subtle text-success-emphasis" : "bg-danger-subtle text-danger-emphasis") + "'>" + esc(stock) + " in stock</span>" : "") +
                    "</div>" +
                "</div>" +
                "<div class='fw-semibold text-nowrap'>" + esc(item.price != null && item.price !== '' ? itflowBillingMoney(Number(item.price), opts.currency, opts.locale) : '') + "</div>" +
            "</div>";
        },
        onSelect: function (item) {
            name.value = item.product_name;
            if (description) {
                description.value = item.description || '';
                description.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (qty && (qty.value === '' || qty.value === '0')) {
                qty.value = 1;
            }
            if (price) {
                price.value = item.price;
            }
            if (tax) {
                tax.value = item.tax || 0;
                if (tax.selectedIndex < 0) {
                    tax.value = 0;
                }
                tax.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (product) {
                product.value = item.prod_id;
            }
            // How it is billed by default - Billed on a quote, Counted on a recurring invoice
            var billedSelect = root.querySelector('select[name="frequency"]');
            if (billedSelect) {
                itflowBillingPick(billedSelect, item.frequency || '');
            }
            var seatSelect = root.querySelector('select[name="seat_source"]');
            if (seatSelect) {
                itflowBillingPick(seatSelect, item.seat_source || '');
            }
            update();
            if (price) {
                price.focus();
                price.select();
            }
        }
    });

    // Typing over the name by hand breaks the link to the product
    if (product) {
        name.addEventListener('input', function () {
            product.value = 0;
        });
    }
}

function itflowBillingItems(options) {
    var opts = options || {};
    var paper = document.querySelector('.itflow-paper[data-billing-type]');
    if (!paper) {
        return;
    }
    var type = paper.getAttribute('data-billing-type');
    var docId = paper.getAttribute('data-billing-id');

    var addRow = paper.querySelector('[data-billing-add]');
    var addForm = document.getElementById('billingAddLine');
    if (addRow) {
        itflowBillingLineForm(addRow, {
            products: opts.products,
            currency: opts.currency,
            locale: opts.locale,
            productInput: addForm ? addForm.querySelector('[data-line-product]') : null
        });

        // Per user / per device: the quantity is the count, not typed
        var addSeat = addRow.querySelector('[data-add-seat]');
        var addQty = addRow.querySelector('[data-line-qty]');
        if (addSeat && addQty) {
            addSeat.addEventListener('change', function () {
                var option = addSeat.options[addSeat.selectedIndex];
                var counted = addSeat.value !== '';
                if (counted) {
                    addQty.value = option.getAttribute('data-count') || '0';
                }
                addQty.readOnly = counted;
                addQty.classList.toggle('bg-body-tertiary', counted);
                addQty.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        // Enter in the name, quantity or price adds the line
        addRow.querySelectorAll('input').forEach(function (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.defaultPrevented && !document.querySelector('.itflow-ac-menu.show')) {
                    e.preventDefault();
                    if (addForm && addForm.reportValidity()) {
                        addForm.requestSubmit ? addForm.requestSubmit() : addForm.submit();
                    }
                }
            });
        });
    }

    // Back from adding a line - ready for the next; back from editing one - show which
    var hash = window.location.hash;
    if (hash === '#add-line' && addRow) {
        var nameInput = addRow.querySelector('[data-line-name]');
        addRow.scrollIntoView({ block: 'center' });
        if (nameInput) {
            nameInput.focus();
        }
    } else if (/^#line-\d+$/.test(hash)) {
        var line = document.querySelector(hash);
        if (line) {
            line.scrollIntoView({ block: 'center' });
            line.classList.add('itflow-paper-flash');
        }
    }

    // Drag to reorder
    var body = paper.querySelector('[data-billing-items] tbody');
    if (body && typeof Sortable !== 'undefined' && paper.querySelector('.drag-handle')) {
        // A quote's lines sit under a heading for how often each is billed - a line moves within its own
        var grouped = !!body.querySelector('tr.itflow-paper-group');
        new Sortable(body, {
            handle: '.drag-handle',
            draggable: 'tr[data-item-id]',
            animation: 150,
            onMove: function (evt) {
                if (!grouped) {
                    return true;
                }
                var before = evt.willInsertAfter ? evt.related : evt.related.previousElementSibling;
                return !!before && before.getAttribute('data-group') === evt.dragged.getAttribute('data-group');
            },
            onEnd: function (evt) {
                if (evt.oldIndex === evt.newIndex) {
                    return;
                }
                var ids = Array.prototype.map.call(body.querySelectorAll('tr[data-item-id]'), function (row) {
                    return row.getAttribute('data-item-id');
                });
                itflowPost('ajax.php', {
                    billing_items_order: 1,
                    type: type,
                    doc_id: docId,
                    items: ids.join(','),
                    csrf_token: opts.csrf
                }, function () {
                    if (typeof itflowToast === 'function') {
                        itflowToast('Order saved', 'success');
                    }
                }, function () {
                    if (typeof itflowToast === 'function') {
                        itflowToast("The new order didn't save - reloading", 'error');
                    }
                    setTimeout(function () { window.location.reload(); }, 1200);
                });
            }
        });
    }
}

/*
 * The seat part of a recurring invoice's line form: pick where the quantity
 * comes from - users, devices, a license's seats - and narrow it. The count
 * comes from ajax.php (recurring_seat_count) as it changes, fills in the
 * quantity, and the quantity can't be typed while it is counted.
 */
function itflowBillingSeatForm(form, recurringInvoiceId) {
    var source = form.querySelector('[data-seat-source]');
    var qty = form.querySelector('[data-line-qty]');
    var preview = form.querySelector('[data-seat-preview]');
    if (!source || !qty) {
        return;
    }
    var timer = null;

    function sections() {
        form.querySelectorAll('[data-seat-for]').forEach(function (section) {
            var on = section.getAttribute('data-seat-for') === source.value || (section.getAttribute('data-seat-for') === 'any' && source.value !== '');
            section.hidden = !on;
            // Hidden sections send nothing - the contacts' and devices' tag pickers share a name
            section.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !on;
            });
        });
        qty.readOnly = source.value !== '';
        qty.classList.toggle('bg-body-tertiary', source.value !== '');
    }

    function count() {
        if (source.value === '') {
            return;
        }
        var params = new URLSearchParams();
        params.append('recurring_seat_count', '1');
        params.append('recurring_invoice_id', recurringInvoiceId);
        params.append('source', source.value);
        form.querySelectorAll('[data-seat-for] input:not([disabled]), [data-seat-for] select:not([disabled])').forEach(function (input) {
            if ((input.type === 'checkbox' && !input.checked) || !input.name) {
                return;
            }
            params.append(input.name, input.value);
        });
        if (preview) {
            preview.textContent = 'Counting...';
        }
        fetch('ajax.php?' + params.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    preview.textContent = data.error;
                    return;
                }
                qty.value = data.quantity;
                qty.dispatchEvent(new Event('input', { bubbles: true }));
                if (preview) {
                    preview.innerHTML = '';
                    var strong = document.createElement('strong');
                    strong.textContent = data.count;
                    preview.append('Counts ', strong, ' now - ' + data.label);
                    if (Number(data.quantity) !== Number(data.count)) {
                        preview.append(' - billed at the minimum, ' + data.quantity);
                    }
                }
            })
            .catch(function () {
                if (preview) {
                    preview.textContent = "Couldn't count them just now - it is counted again when saved.";
                }
            });
    }

    function changed() {
        sections();
        clearTimeout(timer);
        timer = setTimeout(count, 150);
    }

    source.addEventListener('change', changed);
    form.querySelectorAll('[data-seat-input]').forEach(function (input) {
        input.addEventListener(input.tagName === 'INPUT' && input.type === 'text' ? 'input' : 'change', changed);
    });
    sections();
    count();
}
