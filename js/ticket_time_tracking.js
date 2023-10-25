document.addEventListener("DOMContentLoaded", function() {

    var hours = 0;
    var minutes = 0;
    var seconds = 0;
    var timerInterval = null;
    var isPaused = false;

    var ticketID = getCurrentTicketID();

    // Load stored time if available
    loadTimeFromStorage();

    function getCurrentTicketID() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('ticket_id');
    }

    function getLocalStorageKey() {
        return 'time-' + ticketID;
    }

    function loadTimeFromStorage() {
        var storedTime = localStorage.getItem(getLocalStorageKey());
        if (storedTime) {
            var parsed = JSON.parse(storedTime);
            hours = parsed.hours || 0;
            minutes = parsed.minutes || 0;
            seconds = parsed.seconds || 0;
        }
    }

    function storeTimeToStorage() {
        var timeData = {
            hours: hours,
            minutes: minutes,
            seconds: seconds
        };
        localStorage.setItem(getLocalStorageKey(), JSON.stringify(timeData));
    }

    function startTimer() {
        if (timerInterval === null) {
            timerInterval = setInterval(countTime, 1000);
        }
    }

    function countTime() {
        ++seconds;
        if (seconds == 60) {
            seconds = 0;
            minutes++;
        }
        if (minutes == 60) {
            minutes = 0;
            hours++;
        }

        var time_worked = pad(hours) + ":" + pad(minutes) + ":" + pad(seconds);
        document.getElementById("time_worked").value = time_worked;
        storeTimeToStorage();
    }

    function pad(val) {
        var valString = val + "";
        if (valString.length < 2) {
            return "0" + valString;
        } else {
            return valString;
        }
    }

    function pauseTimer() {
        if (timerInterval !== null) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    function toggleTimer() {
        var button = document.getElementById("toggleTimer");
        if (isPaused) {
            startTimer();
            button.innerHTML = '<i class="fas fa-pause"></i>';
            isPaused = false;
        } else {
            pauseTimer();
            button.innerHTML = '<i class="fas fa-play"></i>';
            isPaused = true;
        }
    }

    function setTime() {
        var time_as_text = document.getElementById("time_worked").value;
        const time_text_array = time_as_text.split(":");
        hours = parseInt(time_text_array[0]);
        minutes = parseInt(time_text_array[1]);
        seconds = parseInt(time_text_array[2]);
        if (!isPaused) {
            startTimer();
        }
    }

    function pauseForEdit() {
        var wasRunningBeforeEdit = !isPaused; 
        pauseTimer();
    }

    function restartAfterEdit() {
        var wasRunningBeforeEdit = !isPaused;
        if (wasRunningBeforeEdit) {
            startTimer();
        }
    }

    // Start timer when page is loaded
    startTimer();

    // Event listeners
    document.getElementById("time_worked").addEventListener('change', setTime);
    document.getElementById("toggleTimer").addEventListener('click', toggleTimer);
    document.getElementById("time_worked").addEventListener('focus', pauseForEdit);
    document.getElementById("time_worked").addEventListener('blur', restartAfterEdit);

});
