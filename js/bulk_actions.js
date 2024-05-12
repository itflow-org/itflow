// Allow selecting and editing multiple records at once

var form = document.getElementById("bulkActions"); // Get the form element by its id
var checkboxes = form.querySelectorAll('input[type="checkbox"].bulk-select'); // Select only checkboxes with class "bulk-select"
var selectedCount = document.getElementById("selectedCount");
var selectAllCheckbox = document.getElementById("selectAllCheckbox"); // The "select all" checkbox

// Event listener for each checkbox
for (var i = 0; i < checkboxes.length; i++) {
  checkboxes[i].addEventListener("click", updateSelectedCount);
}

// Function to update the count of selected checkboxes
function updateSelectedCount() {
  var count = 0;
  for (var i = 0; i < checkboxes.length; i++) {
    if (checkboxes[i].checked) {
      count++;
    }
  }
  selectedCount.textContent = count; // Display the count

  // Show or hide the multi-action button
  document.getElementById("bulkActionButton").hidden = count === 0;
}

// Function to check/uncheck all checkboxes
function checkAll(source) {
  for (var i = 0; i < checkboxes.length; i++) {
    checkboxes[i].checked = source.checked;
  }
  updateSelectedCount(); // Update the count after changing checkbox states
}

// Event listener for the "select all" checkbox
if (selectAllCheckbox) {
  selectAllCheckbox.addEventListener("click", function() {
    checkAll(this);
  });
}
