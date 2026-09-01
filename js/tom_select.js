/**
 * Tom Select integration.
 *
 * Replaces Select2. Tom Select is vanilla JS and exposes its instance on the
 * element as `el.tomselect`, so nothing here needs jQuery.
 *
 * Select2 concepts and their equivalents, for anyone reading this later:
 *   $(el).select2({tags:true})        -> create: true
 *   $(el).val(null).trigger('change') -> el.tomselect.clear()
 *   $(el).trigger('change.select2')   -> el.tomselect.sync()   (options replaced)
 *   $(el).on('select2:select', fn)    -> el.tomselect.on('change', fn)
 *
 * WHY THIS IS ITS OWN FILE RATHER THAN PART OF js/app.js
 *
 * The visible flash on page load was a timing problem, not a styling one. The
 * browser paints the native <select> as soon as it parses it, and the swap to
 * Tom Select could not happen until app.js ran - which is dead last in
 * includes/footer.php, behind roughly a megabyte of parser-blocking library
 * code (tinymce 486K, intl-tel-input 316K, DataTables 123K, sweetalert2 49K,
 * adminlte 28K and the rest). Every byte of that had to download, parse and
 * execute first, and the native controls sat on screen for the whole of it.
 *
 * Splitting the Tom Select layer out lets footer.php load it immediately after
 * the library itself, ahead of everything that has nothing to do with it. Only
 * bootstrap.bundle and http.js are still in front of it.
 *
 * The sweep at the bottom runs synchronously rather than on DOMContentLoaded,
 * on purpose: DOMContentLoaded does not fire until every parser-blocking
 * script in the body has run, which is the exact wait this file exists to
 * avoid. Running inline is safe because this script tag sits after the closing
 * .app-wrapper div, so the page markup above it is already parsed.
 *
 * js/app.js keeps its own tom-select step. That is not redundant - it is what
 * enhances selects inside ajax modals, whose markup does not exist yet at this
 * point and whose footer (includes/modal_footer.php) re-executes app.js. On a
 * normal page load that later pass finds everything already enhanced and the
 * guard in initTomSelect makes it a no-op.
 */

function initTomSelect(el, options) {
    if (!el || el.tomselect) {
        return el ? el.tomselect : null;
    }
    /*
     * Only ever enhance a real form control.
     *
     * Tom Select copies the source element's classes onto its own wrapper, so
     * an enhanced <select class="form-select select2"> leaves a sibling
     * <div class="ts-wrapper form-select select2 ..."> behind it. That means a
     * querySelectorAll('.select2') run AFTER any enhancement matches the
     * wrapper too, and the wrapper has no .tomselect of its own so the guard
     * above waves it through. Constructing Tom Select on a <div> yields a
     * second, empty control - no options, no placeholder - stacked on top of
     * the working one, which reads as "the select is blank".
     *
     * The sweeps below and in js/app.js are scoped to `select.select2` so this
     * cannot happen, but the check belongs here as well: initTomSelect is
     * called directly from agent/js/share_modal.js and anywhere else that
     * grows a call site later.
     */
    if (el.tagName !== 'SELECT' && el.tagName !== 'INPUT') {
        return null;
    }
    var settings = Object.assign({
        create: false,
        allowEmptyOption: true,
        plugins: el.multiple ? ['remove_button'] : [],
        placeholder: el.getAttribute('data-placeholder') || undefined
    }, options || {});
    return new TomSelect(el, settings);
}

/** Re-read the <option> list after it has been replaced server-side. */
function refreshTomSelect(el) {
    if (el && el.tomselect) {
        el.tomselect.sync();
    }
}

/** Clear a selection (single or multiple) without firing a server round-trip. */
function clearTomSelect(el) {
    if (el && el.tomselect) {
        el.tomselect.clear(true);
    }
}

/**
 * Set a select's value from code. A plain el.value = x (or jQuery .val()) does
 * not repaint a Tom Select widget - the underlying <select> changes but the
 * visible control does not. Falls back to a native change event when the
 * element was never enhanced.
 */
function setTomSelectValue(el, value) {
    if (!el) {
        return;
    }
    if (el.tomselect) {
        el.tomselect.setValue(value);
        return;
    }
    el.value = value;
    el.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * Enhance every .select2 already in the document.
 *
 * Wrapped in try/catch for the same reason app.js wraps its init steps: one
 * malformed select must not stop the rest of the page's scripts from running.
 */
(function () {
    try {
        document.querySelectorAll('select.select2').forEach(function (el) {
            initTomSelect(el);
        });
    } catch (e) {
        console.error('itflow init [tom-select-eager] failed:', e);
    }
})();
