/**
 * Bootstrap-styles user-authored HTML inside div.prettyContent (documents,
 * ticket replies, templates, queued mail): tables get .table, images get
 * .img-fluid so they stay inside their container - and one shrunk by that
 * opens full size when clicked. Idempotent - safe to run again over content
 * it has already touched.
 */
function prettyContent() {
    // Add class to tables
    document.querySelectorAll('div.prettyContent table').forEach(function (el) {
        el.classList.add('table');
    });

    // Add img-fluid class to img tags - and one shrunk to fit opens full size when clicked
    document.querySelectorAll('div.prettyContent img').forEach(function (el) {
        el.classList.add('img-fluid');
        prettyContentZoomable(el);
    });
}

/** Marks a picture shown smaller than it is - not one in a link, which goes where the link does. */
function prettyContentZoomable(img) {
    if (img.closest('a')) {
        return;
    }
    const check = function () {
        if (img.naturalWidth > img.clientWidth + 8 || img.naturalHeight > img.clientHeight + 8) {
            img.classList.add('itflow-zoomable');
            img.style.cursor = 'zoom-in';
            img.title = img.title || 'Click to see it full size';
        }
    };
    if (img.complete) {
        check();
    } else {
        img.addEventListener('load', check, { once: true });
    }
}

/*
 * A picture marked above opens over the page at full size, as far as the window allows, on a
 * dark shade. A click anywhere or Escape closes it - Escape without closing a window under it.
 */
if (!window.itflowImageZoom) {
    window.itflowImageZoom = true;
    document.addEventListener('click', function (e) {
        const img = e.target.closest ? e.target.closest('div.prettyContent img.itflow-zoomable') : null;
        if (!img) {
            return;
        }
        e.preventDefault();
        const layer = document.createElement('div');
        layer.setAttribute('role', 'dialog');
        layer.setAttribute('aria-label', 'Picture, full size');
        layer.style.cssText = 'position:fixed;inset:0;z-index:2100;display:flex;align-items:center;justify-content:center;'
            + 'padding:1rem;background:rgba(0,0,0,.85);cursor:zoom-out;overflow:auto';
        const big = document.createElement('img');
        big.src = img.currentSrc || img.src;
        big.alt = img.alt || '';
        big.style.cssText = 'max-width:100%;max-height:100%;object-fit:contain;background:#fff;box-shadow:0 .5rem 2rem rgba(0,0,0,.5)';
        layer.appendChild(big);
        const onKey = function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
            }
        };
        const close = function () {
            layer.remove();
            document.removeEventListener('keydown', onKey, true);
        };
        layer.addEventListener('click', close);
        document.addEventListener('keydown', onKey, true);
        document.body.appendChild(layer);
    });
}

/*
 * Not a bare DOMContentLoaded listener: ajax modal payloads (document_view,
 * document_version_view, mail_queue_message_view) load this file long after
 * DOMContentLoaded has fired, so the listener never ran there. Not
 * itflowReady() either - full pages load this before app.js, and the client
 * and guest portals never load app.js at all.
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', prettyContent);
} else {
    prettyContent();
}
