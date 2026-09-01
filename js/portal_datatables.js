/*
 * ITFlow - DataTables for the client portal.
 *
 * The agent side initialises DataTables from js/app.js, which the portal cannot
 * load: app.js calls TinyMCE, Tom Select, Flatpickr and IMask unconditionally
 * and none of them exist here. This is the portal's own one-liner rather than a
 * shared extraction, because unlike the phone-input logic there is no real
 * behaviour to keep in step - just a constructor.
 *
 * A separate file, not an inline <script>, because portal pages send
 * Content-Security-Policy: default-src 'self', which blocks inline script.
 */
function initPortalDataTables() {
    if (typeof DataTable !== 'function') {
        return;
    }

    document.querySelectorAll('table.dataTables').forEach(function (el) {
        if (DataTable.isDataTable(el)) {
            return;
        }

        new DataTable(el, {
            // The server already ordered these rows - newest first for an
            // activity log. DataTables' default is to re-sort on column 0
            // ascending, which would silently reverse that.
            order: [],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100]
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPortalDataTables);
} else {
    initPortalDataTables();
}
