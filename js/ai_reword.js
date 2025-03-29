document.getElementById('rewordButton').addEventListener('click', function() {
    var textInput = this.closest('form').querySelector('textarea');
    var ticketDescription = document.getElementById('ticketDescription');
    var rewordButton = document.getElementById('rewordButton');
    var undoButton = document.getElementById('undoButton');
    var previousText = textInput.value; // Store the current text

    // Disable the Reword button and show loading state
    rewordButton.disabled = true;
    rewordButton.innerText = 'Processing...';

    fetch('post.php?ai_reword', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        // Body with the text to reword and the ticket description
        body: JSON.stringify({
            text: textInput.value,
            ticketDescription: ticketDescription.innerText.valueOf(),
        }),
    })
    .then(response => response.json())
    .then(data => {
        textInput.value = data.rewordedText || 'Error: Could not reword the text.';
        rewordButton.disabled = false;
        rewordButton.innerText = 'Reword'; // Reset button text
        undoButton.style.display = 'inline'; // Show the Undo button

        // Set up the Undo button to revert to the previous text
        undoButton.onclick = function() {
            textInput.value = previousText;
            this.style.display = 'none'; // Hide the Undo button again
        };
    })
    .catch(error => {
        console.error('Error:', error);
        rewordButton.disabled = false;
        rewordButton.innerText = 'Reword'; // Reset button text
    });
});