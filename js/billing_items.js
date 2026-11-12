/*
 * The lines on an invoice, quote or recurring invoice page
 * (includes/billing_paper.php), while they can be changed:
 *
 *  - the add field: type a product's name or code and pick it, and it goes on
 *    as a line with its description, price and tax; or type anything and
 *    press Enter for a line of its own. The new line's quantity is then ready
 *    to type over, and Enter goes back to the field for the next
 *  - a line's name, description, quantity, price and tax change where they
 *    sit: click one, or Tab to it and press Enter. Enter or leaving it saves,
 *    Escape puts it back, Tab moves on to the next
 *  - drag a line by its handle to move it - saved as it drops
 *  - the line just edited in the Edit Line window is picked out when the page
 *    comes back
 *
 * Nothing reloads. A change goes to ajax.php (billing_item_add,
 * billing_item_set), one at a time and in order, and then the page is asked
 * for again and what follows the lines is taken from it: the lines
 * themselves, and every part marked data-billing-live - the totals, the
 * amount due, the header, the tax warning and the cards beside the paper. So
 * how any of it looks is written once, in PHP. A field being typed in is
 * left alone while that happens.
 *
 * Call once the page has loaded:
 *
 *     itflowBillingItems({ csrf: '...', currency: 'USD', locale: 'en-US' });
 *
 * The products the add field offers are in the paper, as
 * getProductsForAutocomplete() gives them, and are read again after each
 * change so what is in stock stays right.
 *
 * itflowBillingLineForm(form, options) shows what a line comes to as its
 * form is filled in - the Edit Line window uses it.
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

/* Whether a product is one for what was typed - its name or its code */
function itflowBillingProductMatch(item, term) {
    return String(item.label || '').toLowerCase().indexOf(term) !== -1
        || String(item.product_name || '').toLowerCase().indexOf(term) !== -1
        || String(item.product_code || '').toLowerCase().indexOf(term) !== -1;
}

/*
 * A product's row in the add field's list, as the select picker draws one:
 * what it is in front, its name, a quieter line of its code, how it is billed,
 * its tax and what it says, and its price - with its stock - at the end
 */
function itflowBillingProductRow(item, opts) {
    var isService = String(item.type || '').toLowerCase() === 'service';
    var tracked = String(item.track_stock) === '1';
    var stock = Number(item.available_stock || 0);
    var billed = itflowBillingBilledText(item.frequency, item.seat_source);
    var facts = [];
    if (item.product_code) {
        facts.push(item.product_code);
    }
    if (billed) {
        facts.push(billed);
    }
    if (item.tax_percent != null) {
        facts.push('Tax ' + parseFloat(item.tax_percent) + '%');
    }
    if (item.description) {
        facts.push(item.description);
    }
    return {
        icon: isService ? 'fas fa-wrench' : 'fas fa-box-open',
        iconTitle: isService ? 'Service' : 'Product',
        label: item.product_name || item.label,
        description: facts.join(' \u00b7 '),
        aside: item.price != null && item.price !== '' ? itflowBillingMoney(Number(item.price), opts.currency, opts.locale) : '',
        asideNote: tracked ? stock + ' in stock' : '',
        asideTone: tracked && stock <= 0 ? 'danger' : ''
    };
}

function itflowBillingLineForm(root, options) {
    if (!root) {
        return;
    }
    var opts = options || {};
    var description = root.querySelector('[data-line-description]');
    var qty = root.querySelector('[data-line-qty]');
    var price = root.querySelector('[data-line-price]');
    var tax = root.querySelector('[data-line-tax]');
    var preview = root.querySelector('[data-line-preview]');

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
}

function itflowBillingItems(options) {
    var opts = options || {};
    var paper = document.querySelector('.itflow-paper[data-billing-type]');
    if (!paper) {
        return;
    }
    var type = paper.getAttribute('data-billing-type');
    var docId = paper.getAttribute('data-billing-id');
    var body = paper.querySelector('[data-billing-items] tbody');
    var addInput = paper.querySelector('[data-billing-add]');
    var products = [];

    // Back from the Edit Line window - show which line; back with a refusal from it - ready at the add field
    var hash = window.location.hash;
    if (hash === '#add-line' && addInput) {
        addInput.scrollIntoView({ block: 'center' });
        addInput.focus();
    } else if (/^#line-\d+$/.test(hash)) {
        var line = document.querySelector(hash);
        if (line) {
            line.scrollIntoView({ block: 'center' });
            line.classList.add('itflow-paper-flash');
        }
    }

    if (!body || !paper.hasAttribute('data-billing-edit')) {
        return;
    }

    function toast(message, kind) {
        if (typeof itflowToast === 'function') {
            itflowToast(message, kind || 'success');
        }
    }

    /* ---------- saving, and drawing the page again ---------- */

    var queue = Promise.resolve();  // saves go one at a time, in the order they were made
    var waiting = 0;                // saves sent and not yet answered
    var drawn = 0;                  // counts the redraws asked for, so a late one never undoes a newer
    var stale = false;              // a redraw left something alone that was in use

    /* One change to ajax.php - its answer, or a refusal carrying the reason */
    function send(action, data) {
        var params = new URLSearchParams(data);
        params.append(action, '1');
        params.append('type', type);
        params.append('doc_id', docId);
        params.append('csrf_token', opts.csrf || '');
        waiting++;
        var answer = queue.then(function () {
            return fetch('ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,    // it finishes even when the page is left straight after
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
        }).then(function (res) {
            return res.json();
        }).then(function (json) {
            if (!json || json.status !== 'success') {
                // A role that may not is told why in "error" (permissionRefuse())
                var refused = new Error((json && (json.message || json.error)) || "That didn't save.");
                refused.refused = true;
                refused.reload = !!(json && json.reload);
                throw refused;
            }
            return json;
        });
        function done() {
            waiting--;
        }
        queue = answer.then(done, done);
        return answer;
    }

    /* A save that didn't happen: say why. An answer that wasn't one - signed out, say - loads the page again */
    function failed(error) {
        if (error && error.refused) {
            toast(error.message, 'error');
        } else if (error instanceof SyntaxError) {
            toast("That didn't save - loading the page again", 'error');
        } else {
            toast("That didn't save - the server couldn't be reached", 'error');
            return;
        }
        if (!error.refused || error.reload) {
            setTimeout(function () { window.location.reload(); }, 1200);
        }
    }

    function readProducts(root) {
        var holder = root.querySelector('[data-billing-products]');
        var list = [];
        try {
            list = holder ? JSON.parse(holder.textContent) : [];
        } catch (e) {
            list = [];
        }
        // In place - the add field's list reads this array
        products.length = 0;
        Array.prototype.push.apply(products, list);
    }

    function rowKey(row) {
        return row.hasAttribute('data-item-id') ? 'line-' + row.getAttribute('data-item-id') : 'group-' + (row.getAttribute('data-group') || '');
    }

    /* One line's cells from the page as it is now - all but a cell being typed in */
    function syncRow(row, fresh) {
        row.setAttribute('data-group', fresh.getAttribute('data-group') || '');
        Array.prototype.forEach.call(fresh.children, function (cell, index) {
            var mine = row.children[index];
            if (!mine) {
                row.appendChild(document.importNode(cell, true));
            } else if (mine.querySelector('.itflow-line-editor')) {
                stale = true;
            } else if (mine.innerHTML !== cell.innerHTML) {
                // Tabbed to, not opened - it keeps the focus
                var held = mine.contains(document.activeElement) ? document.activeElement.getAttribute('data-line-field') : null;
                mine.innerHTML = cell.innerHTML;
                var again = held ? mine.querySelector('[data-line-field="' + held + '"]') : null;
                if (again) {
                    again.focus({ preventScroll: true });
                }
            }
        });
    }

    /* The lines as they are now: the ones here already are kept and brought up to date, new ones come, gone ones go */
    function syncRows(fresh) {
        if (!fresh) {
            return;
        }
        var have = {};
        Array.prototype.forEach.call(body.children, function (row) {
            have[rowKey(row)] = row;
        });
        Array.prototype.forEach.call(fresh.children, function (theirs, index) {
            var key = rowKey(theirs);
            var row = have[key];
            if (row) {
                delete have[key];
                syncRow(row, theirs);
            } else {
                row = document.importNode(theirs, true);
            }
            if (body.children[index] !== row) {
                body.insertBefore(row, body.children[index] || null);
            }
        });
        Object.keys(have).forEach(function (key) {
            have[key].remove();
        });
    }

    function draw(fresh) {
        var theirs = fresh.querySelector('.itflow-paper[data-billing-type]');
        // No longer open for changes - this change paid it off, or it was answered meanwhile
        if (!theirs || !theirs.hasAttribute('data-billing-edit')) {
            window.location.reload();
            return;
        }
        stale = false;
        syncRows(theirs.querySelector('[data-billing-items] tbody'));
        document.querySelectorAll('[data-billing-live]').forEach(function (part) {
            var now = fresh.querySelector('[data-billing-live="' + part.getAttribute('data-billing-live') + '"]');
            if (!now || now.innerHTML === part.innerHTML) {
                return;
            }
            if (part.contains(document.activeElement)) {
                stale = true;
            } else {
                part.innerHTML = now.innerHTML;
            }
        });
        readProducts(theirs);
        var add = theirs.querySelector('[data-billing-add]');
        if (add && addInput) {
            addInput.placeholder = add.placeholder;
        }
    }

    /* Ask for the page again and take what follows the lines from it */
    function refresh() {
        var mine = ++drawn;
        return fetch(window.location.href.split('#')[0], { credentials: 'same-origin', cache: 'no-store' }).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.text();
        }).then(function (html) {
            // One asked for later, or a save still out, draws it instead
            if (mine === drawn && !waiting) {
                draw(new DOMParser().parseFromString(html, 'text/html'));
            }
        }).catch(function () {
            window.location.reload();
        });
    }

    function settle() {
        if (!waiting) {
            refresh();
        }
    }

    /* ---------- a line, changed where it sits ---------- */

    var editor = null;      // the one field open: { field, row, name, input, html, value }
    var activeRow = null;   // the line being worked on - it shows its empty description, to fill in
    var addedRow = null;    // the line just added - Enter on it moves on, and then back to the add field

    function activate(row) {
        if (activeRow && activeRow !== row) {
            activeRow.classList.remove('is-active');
        }
        if (row !== addedRow) {
            addedRow = null;
        }
        activeRow = row;
        if (row) {
            row.classList.add('is-active');
        }
    }

    function clean(value) {
        return String(value == null ? '' : value).replace(/\r/g, '').trim();
    }

    /* Close the open field - saving what it holds when that is a change. Nothing typed is no change, but a description can be emptied */
    function closeEditor(save) {
        var was = editor;
        if (!was) {
            return;
        }
        editor = null;
        var value = clean(was.input.value);
        if (was.watch) {
            was.watch.disconnect();
        }
        if (was.picker && was.picker.isOpen) {
            was.picker.close(false, false);
        }
        was.field.classList.remove('is-editing');
        was.field.innerHTML = was.html;
        if (save && value !== clean(was.value) && (value !== '' || was.name === 'description')) {
            change(was, value);
        } else if (stale && !waiting) {
            refresh();
        }
    }

    /* Save one field. What was typed shows, faded, until the page's own drawing of it takes its place */
    function change(was, value) {
        var field = was.field;
        field.classList.add('is-saving');
        field.setAttribute('data-value', value);
        if (was.name !== 'tax_id') {
            var number = itflowBillingNumber(value);
            field.textContent = was.name === 'price' && !isNaN(number) ? itflowBillingMoney(number, opts.currency, opts.locale) : value;
        }
        send('billing_item_set', { item_id: was.row.getAttribute('data-item-id'), field: was.name, value: value }).catch(function (error) {
            field.classList.remove('is-saving');
            field.setAttribute('data-value', was.value);
            field.innerHTML = was.html;
            failed(error);
        }).then(settle);
    }

    function editorKey(open, e) {
        var field = open.field;
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            closeEditor(false);
            field.focus();
        } else if (e.key === 'Enter' && (open.name !== 'description' || e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            closeEditor(true);
            // On a line just added, Enter moves on to the price it still needs, and then back for the next line
            var price = open.row === addedRow && open.name === 'qty' ? open.row.querySelector('[data-line-field="price"]') : null;
            if (price && Number(price.getAttribute('data-value')) === 0) {
                openEditor(price);
            } else if (open.row === addedRow && addInput) {
                addInput.focus();
            } else {
                field.focus();
            }
        } else if (e.key === 'Tab') {
            var fields = Array.prototype.slice.call(open.row.querySelectorAll('[data-line-field]'));
            var to = fields[fields.indexOf(field) + (e.shiftKey ? -1 : 1)];
            if (to) {
                e.preventDefault();
                closeEditor(true);
                // The tax is a list to pick from - it opens when asked, not as it is passed
                if (to.getAttribute('data-line-field') === 'tax_id') {
                    to.focus();
                } else {
                    openEditor(to);
                }
            }
        }
    }

    /*
     * The tax is picked from the rates on offer, with the picker every other
     * list uses. Its list opens under the line's tax, which stays as it is
     * until a rate is picked - the picker's own field is there, unseen, for
     * the list to hang from.
     */
    function taxEditor(open) {
        var select = open.input;
        var rates = paper.querySelector('[data-billing-taxes]');
        select.innerHTML = rates ? rates.innerHTML : '';
        var offered = Array.prototype.some.call(select.options, function (option) {
            return option.value === open.value;
        });
        if (!offered) {
            // A rate no longer offered - the line keeps it until another is picked
            var label = open.field.querySelector('.itflow-paper-tax-label');
            var kept = document.createElement('option');
            kept.value = open.value;
            kept.textContent = label ? label.textContent : 'As it is';
            select.insertBefore(kept, select.firstChild);
        }
        select.value = open.value;
        select.id = 'billingLineTax';
        var heading = document.createElement('label');
        heading.htmlFor = select.id;
        heading.className = 'visually-hidden';
        heading.textContent = 'Tax';
        open.field.appendChild(heading);
        open.field.appendChild(select);
        open.picker = typeof window.itflowSelect === 'function' ? window.itflowSelect(select) : null;
        if (!open.picker) {
            select.addEventListener('change', function () { closeEditor(true); });
            select.addEventListener('blur', function () {
                if (editor === open) {
                    closeEditor(true);
                }
            });
            select.focus();
            return;
        }
        open.picker.open();
        // When its list closes it is done: a pick is saved, and Escape, Cancel or a click elsewhere left it as it was
        open.watch = new MutationObserver(function () {
            if (editor === open && !open.picker.isOpen) {
                var here = open.field.contains(document.activeElement);
                closeEditor(true);
                if (here) {
                    open.field.focus();
                }
            }
        });
        open.watch.observe(open.picker.control, { attributes: true, attributeFilter: ['aria-expanded'] });
    }

    /*
     * Where a field's editor goes. A quantity or price sits over what it
     * replaces, which stays there unseen and keeps its room - a box wider than
     * the number it holds would push the columns about. A name or description
     * takes the place of the text, as wide as the line.
     */
    function over(field, html) {
        var keep = document.createElement('span');
        keep.className = 'itflow-line-keep';
        keep.innerHTML = html;
        field.textContent = '';
        field.appendChild(keep);
        return field;
    }
    function under(field) {
        field.textContent = '';
        return field;
    }

    function openEditor(field) {
        closeEditor(true);
        var name = field.getAttribute('data-line-field');
        var input = document.createElement(name === 'description' ? 'textarea' : (name === 'tax_id' ? 'select' : 'input'));
        var open = editor = { field: field, row: field.closest('tr'), name: name, input: input, html: field.innerHTML, value: field.getAttribute('data-value') || '' };
        input.className = (name === 'tax_id' ? 'form-select form-select-sm select2' : 'form-control form-control-sm') + ' itflow-line-editor';
        input.setAttribute('aria-label', field.title);
        activate(open.row);
        field.classList.add('is-editing');
        if (name === 'tax_id') {
            taxEditor(open);
            return;
        }
        if (name === 'name') {
            input.maxLength = 200;
        } else if (name === 'description') {
            input.rows = 1;
            input.placeholder = 'What it is, the period it covers, serial numbers';
        } else {
            input.inputMode = 'decimal';
        }
        input.value = open.value;
        (name === 'qty' || name === 'price' ? over(field, open.html) : under(field)).appendChild(input);
        if (name === 'description') {
            var grow = function () {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight + 2, 240) + 'px';
            };
            input.addEventListener('input', grow);
            grow();
        }
        input.addEventListener('keydown', function (e) {
            editorKey(open, e);
        });
        input.addEventListener('blur', function () {
            if (editor === open) {
                closeEditor(true);
            }
        });
        input.focus();
        // A number is typed over; a name or description is added to
        if (name === 'qty' || name === 'price') {
            input.select();
        } else {
            input.setSelectionRange(input.value.length, input.value.length);
        }
    }

    body.addEventListener('click', function (e) {
        // How often a quote's line is billed, from its menu
        var set = e.target.closest('[data-line-set]');
        if (set) {
            if (set.getAttribute('aria-current') !== 'true') {
                send('billing_item_set', {
                    item_id: set.closest('tr').getAttribute('data-item-id'),
                    field: set.getAttribute('data-line-set'),
                    value: set.getAttribute('data-value')
                }).catch(failed).then(settle);
            }
            return;
        }
        var field = e.target.closest('[data-line-field]');
        if (field && !field.classList.contains('is-editing')) {
            openEditor(field);
        }
    });
    body.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ' || e.key === 'F2') && e.target.matches('[data-line-field]:not(.is-editing)')) {
            e.preventDefault();
            openEditor(e.target);
        }
    });
    // Working somewhere else - the line is no longer the one being worked on
    function away(e) {
        if (activeRow && e.target.nodeType === 1 && !activeRow.contains(e.target) && !e.target.closest('.itflow-select-panel, .itflow-select-backdrop')) {
            activate(null);
        }
    }
    document.addEventListener('mousedown', away);
    document.addEventListener('focusin', away);

    /* ---------- the add field ---------- */

    function busy(on) {
        var icon = addInput.parentNode.querySelector('i');
        addInput.readOnly = on;
        if (icon) {
            icon.className = on ? 'fas fa-circle-notch fa-spin' : 'fas fa-plus';
        }
    }

    /* Add a line, then open its quantity - or its price, where the quantity is counted - to type over */
    function addLine(line) {
        if (addInput.readOnly || clean(line.name) === '') {
            return;
        }
        var itemId = 0;
        addInput.value = '';
        busy(true);
        send('billing_item_add', line).then(function (answer) {
            itemId = answer.item_id;
            return refresh();
        }).then(function () {
            busy(false);
            var row = body.querySelector('tr[data-item-id="' + itemId + '"]');
            var field = row ? (row.querySelector('[data-line-field="qty"]') || row.querySelector('[data-line-field="price"]')) : null;
            if (!field) {
                addInput.focus();
                return;
            }
            addedRow = row;
            openEditor(field);
            row.scrollIntoView({ block: 'nearest' });
        }, function (error) {
            busy(false);
            addInput.value = line.name;
            addInput.focus();
            failed(error);
        });
    }

    if (addInput) {
        readProducts(paper);
        if (typeof itflowAutocomplete === 'function') {
            itflowAutocomplete(addInput, {
                minLength: 1,
                pickFirst: true,
                source: products,
                match: itflowBillingProductMatch,
                row: function (item) {
                    return itflowBillingProductRow(item, opts);
                },
                // A product goes on as it is in the list - billed as it usually is on a quote, counted as it usually is on a recurring invoice
                onSelect: function (item) {
                    var line = { name: item.product_name, description: item.description || '', price: item.price, tax_id: item.tax || 0, product_id: item.prod_id };
                    if (type === 'quote') {
                        line.frequency = item.frequency || '';
                    } else if (type === 'recurring') {
                        line.seat_source = item.seat_source === 'contacts' || item.seat_source === 'assets' ? item.seat_source : '';
                    }
                    addLine(line);
                },
                extra: function (typed) {
                    return typed === '' ? null : { icon: 'fas fa-plus', label: 'Add "' + typed + '"', description: 'A line of its own, not from your products' };
                },
                onExtra: function (typed) {
                    addLine({ name: typed });
                }
            });
        }
        // With the list closed, Enter adds what was typed
        addInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.defaultPrevented && addInput.getAttribute('aria-expanded') !== 'true') {
                e.preventDefault();
                addLine({ name: addInput.value });
            }
        });
    }

    /* ---------- drag to reorder ---------- */

    if (typeof Sortable !== 'undefined') {
        new Sortable(body, {
            handle: '.drag-handle',
            draggable: 'tr[data-item-id]',
            animation: 150,
            // A quote's lines sit under a heading for how often each is billed - a line moves within its own
            onMove: function (evt) {
                if (!body.querySelector('tr.itflow-paper-group')) {
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
                    toast('Order saved');
                }, function () {
                    toast("The new order didn't save - reloading", 'error');
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
