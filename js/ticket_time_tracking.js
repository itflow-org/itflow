document.addEventListener("DOMContentLoaded", function() {

    // Default values
    var hours = 0;
    var minutes = 0;
    var seconds = 0;
    var timerInterval = null;  // variable to hold interval id
    var isPaused = false;  // variable to track if the timer is paused

    function startTimer() {
        if(timerInterval === null) {
            timerInterval = setInterval(countTime, 1000);
        }
    }

    // Counter
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

        // Total time worked
        var time_worked = pad(hours) + ":" + pad(minutes) + ":" + pad(seconds);
        document.getElementById("time_worked").value = time_worked;
    }

    // Allows manually adjusting the timer
    function setTime() {
        var time_as_text = document.getElementById("time_worked").value;
        const time_text_array = time_as_text.split(":");
        hours = parseInt(time_text_array[0]);
        minutes = parseInt(time_text_array[1]);
        seconds = parseInt(time_text_array[2]);
        if (!isPaused) {
            startTimer();  // start the timer when time is manually adjusted
        }
    }

    // This function "pads" out the values, adding zeros if they are required
    function pad(val) {
        var valString = val + "";
        if (valString.length < 2) {
            return "0" + valString;
        } else {
            return valString;
        }
    }

    // Function to pause the timer
    function pauseTimer() {
        if(timerInterval !== null) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    // Function to toggle the timer
    function toggleTimer() {
        var button = document.getElementById("toggleTimer");
        if(isPaused) {
            // If timer is paused, then start the timer and change the button icon to pause
            startTimer();
            button.innerHTML = '<i class="fas fa-pause"></i>';
            isPaused = false;
        } else {
            // If timer is running, then pause the timer and change the button icon to play
            pauseTimer();
            button.innerHTML = '<i class="fas fa-play"></i>';
            isPaused = true;
        }
    }


    function pauseForEdit() {
        wasRunningBeforeEdit = !isPaused; // check if timer was running
        pauseTimer();
    }

    function restartAfterEdit() {
        if (wasRunningBeforeEdit) {
            startTimer();
        }
    }


    // Start timer when page is loaded
    startTimer();

    // Set setTime as the onchange event handler for the time input
    document.getElementById("time_worked").addEventListener('change', setTime);

    // Toggle timer when button is clicked
    document.getElementById("toggleTimer").addEventListener('click', toggleTimer);

    // Function to pause the timer when the time input is clicked
    document.getElementById("time_worked").addEventListener('focus', pauseForEdit);
    
    // Function to restart the timer when the time input is clicked away from
    document.getElementById("time_worked").addEventListener('blur', restartAfterEdit);

});