// bulk_actions.js
// Allow selecting and editing multiple records at once (no <form> dependency)

// --- Helpers ---
function getCheckboxes() {
    // Always query fresh in case rows are re-rendered
    return Array.from(document.querySelectorAll('input[type="checkbox"].bulk-select'));
}

function getSelectedIds() {
    return getCheckboxes()
        .filter(cb => cb.checked)
        .map(cb => cb.value);
}

function updateSelectedCount() {
    const count = getSelectedIds().length;
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
    getCheckboxes().forEach(cb => {
        cb.checked = source.checked;
    });
    updateSelectedCount();
}

// Wire select-all checkbox if present
const selectAllCheckbox = document.getElementById('selectAllCheckbox');
if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('click', function () {
        checkAll(this);
    });
}

// --- Per-row Checkbox Handling ---
// Use event delegation so it still works if table rows are re-rendered
document.addEventListener('click', function (e) {
    const cb = e.target.closest('input[type="checkbox"].bulk-select');
    if (!cb) return;
    updateSelectedCount();
});

// --- Initialize count on page load ---
document.addEventListener('DOMContentLoaded', updateSelectedCount);
