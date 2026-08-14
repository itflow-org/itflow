<!-- Open Tickets Modal -->
<div class="modal fade" id="openTicketsModal" tabindex="-1" role="dialog" aria-labelledby="openTicketsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title" id="openTicketsModalLabel">Open Tickets</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <div class="alert alert-danger" role="alert">
                    This "Open Tickets" tracker will be removed in a future version of ITFlow. Time tracking will still be a feature.
                </div>

                <div id="openTicketsContainer">
                    <!-- Open tickets will be loaded here via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="clearAllTimers">Clear All</button>
            </div>
        </div>
    </div>
</div>
