<?php
/*
 * The previewer for agent/files.php's grid - one at a time, full size, with
 * the arrow keys paging through everything on show. Driven by agent/js/files.js
 * from the payload files.php writes; what each item can show was worked out
 * against getInlineViewableMimeTypes(), so anything else gets the fallback
 * panel rather than a frame of a download.
 */
?>
<div class="modal" id="viewFileModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-white" data-bs-theme="dark">
            <div class="modal-header">
                <div class="min-w-0">
                    <h6 class="modal-title text-truncate" id="modalTitle"></h6>
                    <div class="small text-body-secondary" id="modalMeta"></div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>

            <div class="position-relative text-center">
                <button type="button" class="btn btn-dark position-absolute top-50 start-0 translate-middle-y ms-2 itflow-preview-nav" title="Previous (left arrow)" id="modalPrev">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <img id="modalImage" class="img-fluid my-3 d-none" src="" alt="" style="max-height: 75vh;">

                <iframe id="modalFrame" class="w-100 my-3 d-none bg-white" style="height:75vh; border:0;" title="File preview"></iframe>

                <pre id="modalText" class="text-start text-white bg-black p-3 my-3 mx-5 d-none overflow-auto" style="max-height:70vh; white-space:pre-wrap; word-break:break-word;"></pre>

                <?php /* Documents render as HTML, on white like the document page */ ?>
                <div id="modalDocument" class="text-start bg-white text-dark p-4 my-3 mx-5 d-none overflow-auto prettyContent" data-bs-theme="light" style="max-height:70vh;"></div>

                <div id="modalFallback" class="my-5 d-none">
                    <i class="fas fa-file fa-4x text-secondary" id="modalFallbackIcon"></i>
                    <p class="mt-3 mb-0">This kind of file cannot be previewed in the browser - download it to open it.</p>
                </div>

                <button type="button" class="btn btn-dark position-absolute top-50 end-0 translate-middle-y me-2 itflow-preview-nav" title="Next (right arrow)" id="modalNext">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="modal-footer">
                <span class="text-body-secondary small me-auto" id="modalPosition"></span>
                <button type="button" class="btn btn-outline-light" id="modalDetails"><i class="fas fa-fw fa-info-circle me-2"></i>Details</button>
                <a class="btn btn-primary" id="modalDownload" href="#"><i class="fas fa-fw fa-download me-2"></i>Download</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-fw fa-times me-2"></i>Close</button>
            </div>
        </div>
    </div>
</div>
