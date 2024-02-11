document.getElementById('rewordButton').addEventListener('click', function() {
    var textInput = document.getElementById('textInput');
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
        body: JSON.stringify({ text: textInput.value }),
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
