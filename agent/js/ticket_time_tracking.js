(function() {
    document.addEventListener("DOMContentLoaded", function() {
        var timerInterval = null;
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
            let storedStartTime = parseInt(localStorage.getItem(getLocalStorageKey("startTime")) || "0");
            let pausedTime = parseInt(localStorage.getItem(getLocalStorageKey("pausedTime")) || "0");
            if (!storedStartTime) return pausedTime;
            let timeSinceStart = Math.floor((Date.now() - storedStartTime) / 1000);
            return pausedTime + timeSinceStart;
        }

        function pad(val) {
            return val < 10 ? "0" + val : val;
        }

        function displayTime() {
            let totalSeconds = elapsedSecs;
            let hours = Math.floor(totalSeconds / 3600);
            totalSeconds %= 3600;
            let minutes = Math.floor(totalSeconds / 60);
            let seconds = totalSeconds % 60;

            let hoursEl = document.getElementById("hours");
            let minutesEl = document.getElementById("minutes");
            let secondsEl = document.getElementById("seconds");

            if (hoursEl && minutesEl && secondsEl) {
                hoursEl.value = pad(hours);
                minutesEl.value = pad(minutes);
                secondsEl.value = pad(seconds);
            } else {
                console.warn("Timer input elements not found");
            }
        }

        function countTime() {
            elapsedSecs = getElapsedSeconds();
            displayTime();
        }

        function startTimer() {
            if (!localStorage.getItem(getLocalStorageKey("startTime"))) {
                localStorage.setItem(getLocalStorageKey("startTime"), Date.now().toString());
            }
            timerInterval = setInterval(countTime, 1000);
            let btn = document.getElementById("startStopTimer");
            if (btn) btn.innerHTML = "<i class='fas fa-pause'></i>";
            localStorage.setItem("ticket-timer-running-" + ticketID, "true");
        }

        function pauseTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            let currentElapsed = getElapsedSeconds();
            localStorage.setItem(getLocalStorageKey("pausedTime"), currentElapsed.toString());
            localStorage.removeItem(getLocalStorageKey("startTime"));
            let btn = document.getElementById("startStopTimer");
            if (btn) btn.innerHTML = "<i class='fas fa-play'></i>";
            localStorage.setItem("ticket-timer-running-" + ticketID, "false");
        }

        function clearTimeStorage() {
            localStorage.removeItem(getLocalStorageKey("startTime"));
            localStorage.removeItem(getLocalStorageKey("pausedTime"));
            localStorage.removeItem("ticket-timer-running-" + ticketID);
        }

        function resetTimer() {
            if (confirm("Are you sure you want to reset the timer?")) {
                clearInterval(timerInterval);
                timerInterval = null;
                elapsedSecs = 0;
                clearTimeStorage();
                displayTime();
                let btn = document.getElementById("startStopTimer");
                if (btn) btn.innerHTML = "<i class='fas fa-play'></i>";
            }
            localStorage.setItem("ticket-timer-running-" + ticketID, "false");
        }

        function forceResetTimer() {
            clearInterval(timerInterval);
            timerInterval = null;
            elapsedSecs = 0;
            clearTimeStorage();
            displayTime();
            let btn = document.getElementById("startStopTimer");
            if (btn) btn.innerHTML = "<i class='fas fa-play'></i>";
        }

        function handleInputFocus() {
            pauseTimer();
        }

        function updateTimeFromInput() {
            const hours = parseInt(document.getElementById("hours")?.value, 10) || 0;
            const minutes = parseInt(document.getElementById("minutes")?.value, 10) || 0;
            const seconds = parseInt(document.getElementById("seconds")?.value, 10) || 0;
            elapsedSecs = (hours * 3600) + (minutes * 60) + seconds;

            if (!timerInterval) {
                localStorage.setItem(getLocalStorageKey("pausedTime"), elapsedSecs.toString());
            } else {
                const newStartTime = Date.now() - (elapsedSecs * 1000);
                localStorage.setItem(getLocalStorageKey("startTime"), newStartTime.toString());
                localStorage.removeItem(getLocalStorageKey("pausedTime"));
            }
        }

        function checkStatusAndPauseTimer() {
            var statusEl = document.querySelector('select[name="status"]');
            if (statusEl) {
                var status = statusEl.value;
                if (status.includes("Pending") || status.includes("Close")) {
                    pauseTimer();
                }
            }
        }

        // Update on tab visibility change to handle background sleep
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                elapsedSecs = getElapsedSeconds();
                displayTime();
            }
        });

        // Attach input listeners with null checks
        const hoursEl = document.getElementById("hours");
        if (hoursEl) {
            hoursEl.addEventListener('change', updateTimeFromInput);
            hoursEl.addEventListener('focus', handleInputFocus);
        }

        const minutesEl = document.getElementById("minutes");
        if (minutesEl) {
            minutesEl.addEventListener('change', updateTimeFromInput);
            minutesEl.addEventListener('focus', handleInputFocus);
        }

        const secondsEl = document.getElementById("seconds");
        if (secondsEl) {
            secondsEl.addEventListener('change', updateTimeFromInput);
            secondsEl.addEventListener('focus', handleInputFocus);
        }

        const statusEl = document.querySelector('select[name="status"]');
        if (statusEl) {
            statusEl.addEventListener('change', checkStatusAndPauseTimer);
        }

        const startStopBtn = document.getElementById("startStopTimer");
        if (startStopBtn) {
            startStopBtn.addEventListener('click', function() {
                if (timerInterval === null) {
                    startTimer();
                } else {
                    pauseTimer();
                }
            });
        }

        const resetBtn = document.getElementById("resetTimer");
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                resetTimer();
            });
        }

        const addReplyBtn = document.getElementById("ticket_add_reply");
        if (addReplyBtn) {
            addReplyBtn.addEventListener('click', function() {
                setTimeout(forceResetTimer, 100);
            });
        }

        const closeBtn = document.getElementById("ticket_close");
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                setTimeout(clearTimeStorage, 100);
            });
        }

        // Final initialization logic
        try {
            displayTime();

            if (!localStorage.getItem(getLocalStorageKey("startTime")) && !localStorage.getItem(getLocalStorageKey("pausedTime"))) {
                if (typeof ticketAutoStart !== "undefined" && ticketAutoStart === 1) {
                    startTimer();
                } else {
                    pauseTimer();
                }
            } else if (localStorage.getItem(getLocalStorageKey("startTime"))) {
                startTimer();
            }

            checkStatusAndPauseTimer();

        } catch (error) {
            console.error("There was an issue initializing the timer:", error);
        }
    });
})();
