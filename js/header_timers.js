// ticketCounter.js
(function() {
    function getRunningTicketCount() {
        let count = 0;
        for (let i = 0; i < localStorage.length; i++) {
            let key = localStorage.key(i);
            if (key.includes("ticket-timer-running")) {
                let isRunning = JSON.parse(localStorage.getItem(key));
                if (isRunning) {
                    count++;
                }
            }
        }
        return count;
    }

    function updateTicketCountDisplay() {
        let count = getRunningTicketCount();
        let countDisplay = document.getElementById("runningTicketsCount");
        if (countDisplay) {
            countDisplay.innerText = count;
        }
    }

    function getElapsedSeconds(ticketID) {
        let storedStartTime = parseInt(localStorage.getItem(ticketID + "-startTime") || "0");
        let pausedTime = parseInt(localStorage.getItem(ticketID + "-pausedTime") || "0");
        if (!storedStartTime) return pausedTime;
        let timeSinceStart = Math.floor((Date.now() - storedStartTime) / 1000);
        return pausedTime + timeSinceStart;
    }

    function formatTime(seconds) {
        let hours = Math.floor(seconds / 3600);
        let minutes = Math.floor((seconds % 3600) / 60);
        let secs = seconds % 60;
        return `${hours}h ${minutes}m ${secs}s`;
    }

    function loadOpenTickets() {
        var openTicketsContainer = document.getElementById('openTicketsContainer');
        openTicketsContainer.innerHTML = ''; // Clear existing content
    
        for (let i = 0; i < localStorage.length; i++) {
            let key = localStorage.key(i);
    
            if (key.startsWith("ticket-timer-running-")) {
                let ticketID = key.replace("ticket-timer-running-", "");
                let isRunning = JSON.parse(localStorage.getItem(key));
    
                var ticketDiv = document.createElement('div');
                ticketDiv.classList.add('card', 'card-outline', 'mb-3');
                // Add class based on ticket status
                ticketDiv.classList.add(isRunning ? 'card-info' : 'card-warning');
                ticketDiv.id = 'ticket-' + ticketID;
    
                let elapsedSecs = getElapsedSeconds(ticketID);
                let timeString = formatTime(elapsedSecs);
    
                ticketDiv.innerHTML = `
                    <div class="card-header">
                        <h3 class="card-title">Ticket ID: ${ticketID}</h3>
                        <a href="https://develop.twe.tech/ticket.php?ticket_id=${ticketID}" class="btn btn-primary float-right">View Ticket</a>
                    </div>
                    <div class="card-body">
                        <p id="time-${ticketID}">Total Time: ${timeString}</p>
                    </div>
                `;
    
                openTicketsContainer.appendChild(ticketDiv);
            }
        }
    
        requestAnimationFrame(() => updateRunningTickets());
    }
    
    

    function updateRunningTickets() {
        var runningTickets = document.querySelectorAll('[id^="ticket-"]');
        runningTickets.forEach(ticket => {
            let ticketID = ticket.id.replace("ticket-", "");
            let isRunning = JSON.parse(localStorage.getItem("ticket-timer-running-" + ticketID));

            if (isRunning) {
                let updatedTime = formatTime(getElapsedSeconds(ticketID));
                document.getElementById('time-' + ticketID).innerText = 'Total Time: ' + updatedTime;
            }
        });

        requestAnimationFrame(updateRunningTickets);
    }

    // Initial update on script load
    updateTicketCountDisplay();

    // Add event listener to modal
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('openTicketsModal');
        if (modal) {
            $('#openTicketsModal').on('show.bs.modal', loadOpenTickets);
        }
    });

})();
