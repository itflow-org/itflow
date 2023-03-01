var checkboxes = document.querySelectorAll('form input[type="checkbox"]');
var selectedCount = document.getElementById("selectedCount");

for (var i = 0; i < checkboxes.length; i++) {
  checkboxes[i].addEventListener("click", updateSelectedCount);
}

function updateSelectedCount() {
  var count = 0;
  for (var i = 0; i < checkboxes.length; i++) {
    if (checkboxes[i].checked) {
      count++;
    }
  }
  selectedCount.textContent = count;
  if (count > 0) {
    document.getElementById("multiActionButton").hidden = false;
  }

  if (count === 0) {
    document.getElementById("multiActionButton").hidden = true;
  }
}

function checkAll(source) {
  for (var i = 0; i < checkboxes.length; i++) {
    checkboxes[i].checked = source.checked;
  }
}
