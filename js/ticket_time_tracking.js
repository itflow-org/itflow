document.addEventListener("DOMContentLoaded", function() {

    var timerInterval = null;
    var isPaused = false;
    var ticketID = getCurrentTicketID();
    var elapsedSecs = getElapsedSeconds();

    function getCurrentTicketID() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('ticket_id');
    }

    function getLocalStorageKey(suffix) {
        return ticketID + "-" + suffix;
    }

    function getElapsedSeconds() {
        let storedStartTime = localStorage.getItem(getLocalStorageKey("startTime"));
        let pausedTime = parseInt(localStorage.getItem(getLocalStorageKey("pausedTime")) || "0");

        if (!storedStartTime) return pausedTime;

        let timeSinceStart = Math.floor((Date.now() - parseInt(storedStartTime)) / 1000);
        return pausedTime + timeSinceStart;
    }

    function displayTime() {
        let totalSeconds = elapsedSecs;
        let hours = Math.floor(totalSeconds / 3600);
        totalSeconds %= 3600;
        let minutes = Math.floor(totalSeconds / 60);
        let seconds = totalSeconds % 60;

        document.getElementById("time_worked").value = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }

    function pad(val) {
        return val < 10 ? "0" + val : val;
    }

    function countTime() {
        elapsedSecs++;
        displayTime();
    }

    function startTimer() {
        if (!localStorage.getItem(getLocalStorageKey("startTime"))) {
            localStorage.setItem(getLocalStorageKey("startTime"), Date.now().toString());
        }
        if (!isPaused && timerInterval === null) {
            timerInterval = setInterval(countTime, 1000);
        }
    }

    function pauseTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        let currentElapsed = getElapsedSeconds();
        localStorage.setItem(getLocalStorageKey("pausedTime"), currentElapsed.toString());
        localStorage.removeItem(getLocalStorageKey("startTime"));
    }

    function clearTimeStorage() {
        localStorage.removeItem(getLocalStorageKey("startTime"));
        localStorage.removeItem(getLocalStorageKey("pausedTime"));
    }

    document.getElementById("toggleTimer").addEventListener('click', function() {
        if (isPaused) {
            startTimer();
            isPaused = false;
        } else {
            pauseTimer();
            isPaused = true;
        }
    });

    document.getElementById("ticket_add_reply").addEventListener('click', function() {
        pauseTimer();
        clearTimeStorage();
    });

    // Initialize on page load
    displayTime();
    startTimer();

});
