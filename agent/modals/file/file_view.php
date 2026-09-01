<?php
/*
 * ITFlow - File preview modal for the gallery view on agent/files.php
 *
 * Included ONCE, after the tile loop - it used to be required inside it, which
 * emitted a copy per file, all sharing these element ids.
 *
 * What renders is chosen per file by updateModalContent() in files.php, from
 * the "kind" the page worked out against getInlineViewableMimeTypes(). Anything
 * not viewable inline gets the fallback panel rather than a broken frame.
 */
?>
<div class="modal" id="viewFileModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header bg-dark">
                <h6 class="modal-title text-truncate" id="modalTitle"></h6>
                <span class="text-secondary ms-2 small" id="modalMeta"></span>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>

            <div class="position-relative text-center">

                <button type="button" class="btn btn-dark position-absolute top-50 start-0 translate-middle-y ms-2"
                    id="modalPrev" onclick="prevFile()">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <img id="modalImage" class="img-fluid my-3 d-none" src="" alt="">

                <iframe id="modalFrame" class="w-100 my-3 d-none bg-white" style="height:75vh; border:0;"
                    title="File preview"></iframe>

                <pre id="modalText" class="text-start text-white bg-black p-3 my-3 mx-5 d-none overflow-auto"
                    style="max-height:70vh; white-space:pre-wrap; word-break:break-word;"></pre>

                <?php /* Documents render as HTML rather than in a frame. White panel and
                         prettyContent so they read the same as document.php. */ ?>
                <div id="modalDocument" class="text-start bg-white text-dark p-4 my-3 mx-5 d-none overflow-auto prettyContent"
                    style="max-height:70vh;"></div>

                <div id="modalFallback" class="my-5 d-none">
                    <i class="fas fa-file fa-4x text-secondary" id="modalFallbackIcon"></i>
                    <p class="mt-3 mb-0">This file type cannot be previewed in the browser.</p>
                </div>

                <button type="button" class="btn btn-dark position-absolute top-50 end-0 translate-middle-y me-2"
                    id="modalNext" onclick="nextFile()">
                    <i class="fas fa-chevron-right"></i>
                </button>

            </div>

            <div class="modal-footer">
                <span class="text-secondary small me-auto" id="modalPosition"></span>
                <a class="btn btn-primary" id="modalDownload" href="#">
                    <i class="fas fa-fw fa-cloud-download-alt me-2"></i>Download
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-fw fa-times me-2"></i>Close
                </button>
            </div>

        </div>
    </div>
</div>
