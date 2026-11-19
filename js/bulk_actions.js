// bulk_actions.js
// Shared bulk helper: selection count + simple URL enrichment for bulk actions

// --- Helpers ---
function getBulkCheckboxes() {
    // Always query fresh in case rows are re-rendered
    return Array.from(document.querySelectorAll('input[type="checkbox"].bulk-select'));
}

function getSelectedCount() {
    return getBulkCheckboxes().filter(cb => cb.checked).length;
}

function updateSelectedCount() {
    const count = getSelectedCount();

    const selectedCountEl = document.getElementById('selectedCount');
    if (selectedCountEl) {
        selectedCountEl.textContent = count;
    }

    const bulkBtn = document.getElementById('bulkActionButton');
    if (bulkBtn) {
        bulkBtn.hidden = count === 0;
    }
}

// --- Select All Handling ---
function checkAll(source) {
    getBulkCheckboxes().forEach(cb => {
        cb.checked = source.checked;
    });
    updateSelectedCount();
}

// --- Wire up once DOM is ready ---
document.addEventListener('DOMContentLoaded', function () {

    // Initialize count
    updateSelectedCount();

    // Wire select-all checkbox if present
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('click', function () {
            checkAll(this);
        });
    }

    // Per-row checkbox handling. Shift-click ticks (or unticks) every box between this one and the
    // last one clicked, as the clicked one now is.
    let lastClicked = null;
    document.addEventListener('click', function (e) {
        const cb = e.target.closest('input[type="checkbox"].bulk-select');
        if (!cb) return;
        if (e.shiftKey && lastClicked && lastClicked !== cb && lastClicked.isConnected && lastClicked.form === cb.form) {
            const boxes = getBulkCheckboxes().filter(box => box.form === cb.form);
            const from = boxes.indexOf(lastClicked);
            const to = boxes.indexOf(cb);
            if (from !== -1 && to !== -1) {
                boxes.slice(Math.min(from, to), Math.max(from, to) + 1).forEach(box => {
                    if (box.checked !== cb.checked) {
                        box.checked = cb.checked;
                        box.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                // Shift-click also selects the text between the two clicks
                const selection = window.getSelection ? window.getSelection() : null;
                if (selection) {
                    selection.removeAllRanges();
                }
            }
        }
        lastClicked = cb;
        updateSelectedCount();
    });

    // ------------------------------------------------------
    // Generic bulk handler
    //
    // For ANY element with data-bulk="true":
    //   - Reads base URL from data-modal-url or href
    //   - Collects ALL checked .bulk-select checkboxes
    //   - Appends:
    //        <cb.name> = cb.value
    //     e.g. file_ids[]=12, document_ids[]=27, client_ids[]=3, etc.
    //   - Writes URL back to data-modal-url or href
    //   - DOES NOT prevent default, so ajax-modal / normal links still work
    // ------------------------------------------------------
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-bulk="true"]');
        if (!trigger) return;

        const base = trigger.getAttribute('data-modal-url') || trigger.getAttribute('href');
        if (!base || base === '#') return;

        let url;
        try {
            url = new URL(base, window.location.href);
        } catch (err) {
            // Invalid URL, bail
            return;
        }

        const params = url.searchParams;
        const checked = getBulkCheckboxes().filter(cb => cb.checked);

        if (!checked.length) {
            // Nothing selected; do nothing (no ids appended)
            return;
        }

        // Collect all unique names (file_ids[], document_ids[], client_ids[], etc.)
        const namesToClear = new Set();
        checked.forEach(cb => {
            if (cb.name) {
                namesToClear.add(cb.name);
            }
        });

        // Clear any existing values for those names
        namesToClear.forEach(name => params.delete(name));

        // Append each checked checkbox as name=value
        checked.forEach(cb => {
            if (!cb.name) return;
            params.append(cb.name, cb.value);
        });

        const finalUrl = url.pathname + '?' + params.toString();

        if (trigger.hasAttribute('data-modal-url')) {
            trigger.setAttribute('data-modal-url', finalUrl);
        } else {
            trigger.setAttribute('href', finalUrl);
        }

        // IMPORTANT:
        // We do NOT call preventDefault().
        // Your existing ajax-modal handler / normal link click will continue normally,
        // now using the updated URL.
    }, true); // capture so we run before other click handlers
});
