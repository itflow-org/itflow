/*
 * TinyMCE's defaults, set once for every page - loaded straight after
 * tinymce.min.js, before any editor is set up.
 *
 * TinyMCE fetches the rest of itself when an editor opens - its plugins, theme,
 * icons, model and skin stylesheets - so those never pass through a <script> or
 * <link> tag of ours, and staticFileUrl() can't version them. cache_suffix puts
 * a version on everything it fetches: the same ?v= as tinymce.min.js's own tag,
 * which changes when an update brings a new TinyMCE. Without it, browsers ran
 * the new editor with the old copies of its parts still in their cache.
 */
(function () {
    if (!window.tinymce) {
        return;
    }

    var tag = document.querySelector('script[src*="/libs/tinymce/tinymce.min.js"]');
    var version = tag ? new URL(tag.src, document.baseURI).searchParams.get('v') : null;

    if (version) {
        tinymce.overrideDefaults({ cache_suffix: '?v=' + version });
    }
})();

/*
 * A picture copied by itself - Copy image in a browser, a picture copied out of
 * Word or Outlook - puts two things on the clipboard: the picture, and a scrap
 * of HTML that is only an <img> pointing at where it came from. TinyMCE takes
 * the HTML when there is any, so the editor got a link to the other site: the
 * person pasting saw the picture, signed in over there as they are, and nobody
 * else did. When the clipboard holds one picture and nothing else, the paste
 * is handed back to TinyMCE with only the picture on it, and it is kept like a
 * pasted screenshot. Text copied with pictures in it is left alone - the
 * browser doesn't put those pictures on the clipboard, only where they are.
 */
(function () {
    if (!window.tinymce || typeof DataTransfer !== 'function' || typeof DOMParser !== 'function') {
        return;
    }

    // The picture, when it is all that was copied - null for anything else
    function pictureCopiedAlone(clipboard) {
        if (!clipboard || !clipboard.items || Array.prototype.indexOf.call(clipboard.types || [], 'x-tinymce/html') !== -1) {
            return null;
        }

        var pictures = [];
        for (var i = 0; i < clipboard.items.length; i++) {
            var item = clipboard.items[i];
            if (item.kind === 'file' && /^image\/(png|jpeg|gif|webp|bmp)$/.test(item.type)) {
                pictures.push(item.getAsFile());
            }
        }
        if (pictures.length !== 1 || !pictures[0]) {
            return null;
        }

        // With no HTML beside the picture TinyMCE keeps it already
        var html = clipboard.getData('text/html');
        if (!html) {
            return null;
        }

        // Only an <img> that points somewhere, with no words or anything else around it
        var body = new DOMParser().parseFromString(html, 'text/html').body;
        var images = body.querySelectorAll('img');
        var alone = images.length === 1
            && body.textContent.trim() === ''
            && !body.querySelector('table, video, audio, iframe, object, embed, svg, canvas, hr, input')
            && !/^data:/i.test(images[0].getAttribute('src') || '');
        return alone ? pictures[0] : null;
    }

    tinymce.on('AddEditor', function (added) {
        var editor = added.editor;

        // Ahead of TinyMCE's own paste handling, which leaves a paste that was already dealt with alone
        editor.on('paste', function (event) {
            if (event.isDefaultPrevented() || !editor.options.get('paste_data_images')) {
                return;
            }
            var picture = pictureCopiedAlone(event.clipboardData);
            if (!picture) {
                return;
            }
            var only = new DataTransfer();
            only.items.add(picture);
            event.preventDefault();
            editor.dispatch('paste', { clipboardData: only });
        }, true);
    });
})();
