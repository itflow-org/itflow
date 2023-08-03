function addWatcher(button) {
    var container = button.previousElementSibling;
    var textFieldWrapper = document.createElement("div");
    textFieldWrapper.className = "input-group mb-3";

    var prependWrapper = document.createElement("div");
    prependWrapper.className = "input-group-prepend";
    var iconSpan = document.createElement("span");
    iconSpan.className = "input-group-text";
    iconSpan.innerHTML = "<i class='fas fa-fw fa-envelope'></i>";
    prependWrapper.appendChild(iconSpan);

    var textField = document.createElement("input");
    textField.type = "email";
    textField.className = "form-control";
    textField.name = "watchers[]";

    var removeButtonWrapper = document.createElement("div");
    removeButtonWrapper.className = "input-group-append";

    var removeButton = document.createElement("button");
    removeButton.className = "btn btn-danger";
    removeButton.type = "button";
    removeButton.innerHTML = "<i class='fas fa-fw fa-minus'></i>";
    removeButton.onclick = function() {
        removeWatcher(this);
    };

    removeButtonWrapper.appendChild(removeButton);
    textFieldWrapper.appendChild(prependWrapper);
    textFieldWrapper.appendChild(textField);
    textFieldWrapper.appendChild(removeButtonWrapper);
    container.appendChild(textFieldWrapper);
}

function removeWatcher(button) {
    var container = button.parentNode.parentNode.parentNode; // Navigate to the container
    var textFieldWrapper = button.parentNode.parentNode;
    container.removeChild(textFieldWrapper);
}