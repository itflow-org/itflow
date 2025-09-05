<div class="modal" id="viewFileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header bg-dark">
                <h6 class="modal-title" id="modalTitle"></h6>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="position-relative text-center">
                <!-- Left arrow -->
                <button type="button" class="btn btn-dark position-absolute" style="left:10px; top:50%; transform:translateY(-50%);" onclick="prevFile()">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <img id="modalImage" class="img-fluid my-3" src="" alt="">

                <!-- Right arrow -->
                <button type="button" class="btn btn-dark position-absolute" style="right:10px; top:50%; transform:translateY(-50%);" onclick="nextFile()">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>
