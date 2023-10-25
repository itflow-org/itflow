// Description: This file contains the javascript for the ticket time tracking feature

document.addEventListener("DOMContentLoaded", function() {
    // Initialize variables
    var timerInterval = null;
    var isPaused = false;
    var ticketID = getCurrentTicketID();
    var elapsedSecs = getElapsedSeconds();
    
    // Get the ticket ID from the URL
    // Inputs: None
    // Outputs: The ticket ID from the URL
    // Document Interactions: None

    function getCurrentTicketID() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('ticket_id');
    }

    
    // Get the local storage key for the ticket
    // Inputs: The suffix to append to the key
    // Outputs: The local storage key for the ticket
    // Document Interactions: None

    function getLocalStorageKey(suffix) {
        return ticketID + "-" + suffix;
    }

    
    // Get the elapsed seconds from local storage
    // Inputs: None
    // Outputs: The elapsed seconds from local storage
    // Document Interactions: None

    function getElapsedSeconds() {
        let storedStartTime = localStorage.getItem(getLocalStorageKey("startTime"));
        let pausedTime = parseInt(localStorage.getItem(getLocalStorageKey("pausedTime")) || "0");
        // If there is no start time, return the paused time
        if (!storedStartTime) return pausedTime;
        // Otherwise, return the paused time plus the time since the start time
        let timeSinceStart = Math.floor((Date.now() - parseInt(storedStartTime)) / 1000);
        return pausedTime + timeSinceStart;
    }

    // Display the elapsed time
    // Inputs: None
    // Outputs: None
    // Document Interactions: Updates the time worked input

    function displayTime() {
        let totalSeconds = elapsedSecs;
        let hours = Math.floor(totalSeconds / 3600);
        totalSeconds %= 3600;
        let minutes = Math.floor(totalSeconds / 60);
        let seconds = totalSeconds % 60;
        // Update the time worked input
        document.getElementById("time_worked").value = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }
    
    // Pad a number with a leading zero if it is less than 10
    // Inputs: The number to pad
    // Outputs: The padded number
    // Document Interactions: None
    
    function pad(val) {
        return val < 10 ? "0" + val : val;
    }

    // Count the time
    // Inputs: None
    // Outputs: None
    // Document Interactions: Updates the elapsed time

    function countTime() {
        elapsedSecs++;
        displayTime();
    }

    
    // Start the timer
    // Inputs: None
    // Outputs: None
    // Document Interactions: None

    function startTimer() {
        if (!localStorage.getItem(getLocalStorageKey("startTime"))) {
            localStorage.setItem(getLocalStorageKey("startTime"), Date.now().toString());
        }
        if (!isPaused && timerInterval === null) {
            timerInterval = setInterval(countTime, 1000);
        }
    }

    // Pause the timer
    // Inputs: None
    // Outputs: None
    // Document Interactions: None

    function pauseTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        let currentElapsed = getElapsedSeconds();
        localStorage.setItem(getLocalStorageKey("pausedTime"), currentElapsed.toString());
        localStorage.removeItem(getLocalStorageKey("startTime"));
    }

    // Clear the time storage
    // Inputs: None
    // Outputs: None
    // Document Interactions: None

    function clearTimeStorage() {
        localStorage.removeItem(getLocalStorageKey("startTime"));
        localStorage.removeItem(getLocalStorageKey("pausedTime"));
    }

    // Add event listeners

    // When toggleTimer is clicked, toggle the timer
    document.getElementById("toggleTimer").addEventListener('click', function() {
        if (isPaused) {
            // If the timer is paused, start it
            startTimer();
            isPaused = false;
        } else {
            // If the timer is running, pause it
            pauseTimer();
            isPaused = true;
        }
    });

    // When the ticket is submitted, clear the time storage
    document.getElementById("ticket_add_reply").addEventListener('click', function() {
        pauseTimer();
        clearTimeStorage();
    });

    // Initialize on page load
    // If the timer is paused, start it
    displayTime();
    startTimer();

});
