// Editable task rows for the ticket and recurring ticket add/edit modals, and the
// ticket template picker that pre-fills them.
//
// Rows submit as parallel tasks[] and task_estimates[] arrays, aligned by their
// order in the form, so removing a row removes both of its inputs together.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again in one
// page load, which re-runs this file - top-level const/let would throw on the
// second run. Delegated handlers are namespaced and unbound first for the same
// reason, otherwise "Add Task" would add one row per time the modal was opened.

(function () {

    // Builds one task row. Values are assigned as properties rather than built into
    // markup, so a task name containing quotes or angle brackets needs no escaping.
    function buildTaskRow(taskName, taskEstimate) {

        const row = document.createElement("div");
        row.className = "row g-2 mb-2 ticket-task-row";

        const nameColumn = document.createElement("div");
        nameColumn.className = "col-7";

        const nameInput = document.createElement("input");
        nameInput.type = "text";
        nameInput.className = "form-control";
        nameInput.name = "tasks[]";
        nameInput.placeholder = "Task name";
        nameInput.maxLength = 255;
        nameInput.value = taskName || '';
        nameColumn.appendChild(nameInput);

        const estimateColumn = document.createElement("div");
        estimateColumn.className = "col-3";

        const estimateInput = document.createElement("input");
        estimateInput.type = "number";
        estimateInput.className = "form-control";
        estimateInput.name = "task_estimates[]";
        estimateInput.placeholder = "Mins";
        estimateInput.min = 0;
        estimateInput.value = taskEstimate || '';
        estimateColumn.appendChild(estimateInput);

        const removeColumn = document.createElement("div");
        removeColumn.className = "col-2";

        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "btn btn-secondary w-100 ticket-task-remove";
        removeButton.title = "Remove task";
        removeButton.innerHTML = '<i class="fa fa-fw fa-trash"></i>';
        removeColumn.appendChild(removeButton);

        row.appendChild(nameColumn);
        row.appendChild(estimateColumn);
        row.appendChild(removeColumn);

        return row;
    }

    function addTaskRow(taskName, taskEstimate) {

        const container = document.getElementById("ticketTasksContainer");

        if (!container) {
            return;
        }

        container.appendChild(buildTaskRow(taskName, taskEstimate));
    }

    // Replaces the whole list - used when a template is picked
    function setTaskRows(tasks) {

        const container = document.getElementById("ticketTasksContainer");

        if (!container) {
            return;
        }

        container.innerHTML = '';

        (tasks || []).forEach(task => {
            addTaskRow(task.name, task.estimate);
        });
    }

    // dataset always gives a string; jQuery used to auto-parse JSON here, so
    // parse it explicitly and still tolerate an already-parsed value
    function readTemplateTasks(option) {

        const tasks = option.dataset.tasks;

        if (!tasks) {
            return [];
        }

        if (typeof tasks === 'string') {
            try {
                return JSON.parse(tasks);
            } catch (error) {
                return [];
            }
        }

        return tasks;
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#ticketTaskAdd')) {
            return;
        }
        addTaskRow('', '');
    });

    document.addEventListener('click', function (e) {
        const remove = e.target.closest('.ticket-task-remove');
        if (!remove) {
            return;
        }
        const row = remove.closest('.ticket-task-row');
        if (row) {
            row.remove();
        }
    });

    // Ticket template picker - fills in the subject, details and task rows
    document.addEventListener('change', function (e) {
        const select = e.target.closest('#ticket_template_select');
        if (!select) {
            return;
        }

        const option = select.options[select.selectedIndex];

        // Selecting "- No Template -" only unlinks the template - it must not wipe
        // whatever the user has already written or added
        if (!parseInt(option.value, 10)) {
            return;
        }

        const templateSubject = option.dataset.subject || '';
        const templateDetails = option.dataset.details || '';

        document.getElementById('subjectInput').value = templateSubject;

        if (window.tinymce) {
            const editor = tinymce.get('detailsInput');
            if (editor) {
                editor.setContent(templateDetails);
            } else {
                document.getElementById('detailsInput').value = templateDetails;
            }
        } else {
            document.getElementById('detailsInput').value = templateDetails;
        }

        setTaskRows(readTemplateTasks(option));
    });

})();
