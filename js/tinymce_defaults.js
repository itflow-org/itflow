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
