function populateFileDeleteModal(file_id, file_name) {
    // Dynamically populate the file delete modal with the file id (hidden) and name
    document.getElementById("file_delete_id").value = file_id;
    document.getElementById("file_delete_name").innerText = file_name;
}
