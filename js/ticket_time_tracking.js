// Ticket time tracking

// Default values
var hours = 0;
var minutes = 0;
var seconds = 0;
setInterval(countTime, 1000);

// Counter
function countTime()
{
    ++seconds;
    if (seconds == 60) {
        seconds = 0;
        minutes++;
    }
    if (minutes == 60) {
        minutes = 0;
        hours++;
    }

    // Total timeworked
    var time_worked = pad(hours) + ":" + pad(minutes) + ":" + pad(seconds);
    document.getElementById("time_worked").value = time_worked;
}

// Allows manually adjusting the timer
function setTime()
{
    var time_as_text = document.getElementById("time_worked").value;
    const time_text_array = time_as_text.split(":");
    hours = parseInt(time_text_array[0]);
    minutes = parseInt(time_text_array[1]);
    seconds = parseInt(time_text_array[2]);
}

// This function "pads" out the values, adding zeros if they are required
function pad(val)
{
    var valString = val + "";
    if (valString.length < 2)
    {
        return "0" + valString;
    }
    else
    {
        return valString;
    }
}