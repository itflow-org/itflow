// Editable task rows for the ticket and recurring ticket add/edit modals, and the
// ticket template picker (agent/includes/inc_ticket_template_select.php) that
// fills in the form - its fields marked data-template-field.
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

    // The form's field for one thing a template sets - marked data-template-field
    function templateField(form, name) {
        return form.querySelector('[data-template-field="' + name + '"]');
    }

    // Pick a value in a <select> when it has it - the picker redraws by itself
    function pickOption(select, value) {
        if (!select || value === '' || value === undefined) {
            return;
        }
        const option = Array.from(select.options).find(o => o.value === String(value));
        if (option) {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // Write a template into its form: what it sets, field by field. A field
    // the form doesn't have is skipped, and anything the template leaves
    // alone ('' on the option) stays as it is.
    function applyTemplate(select) {

        const option = select.options[select.selectedIndex];
        const form = select.closest('form');

        // Selecting "- No Template -" only unlinks the template - it must not wipe
        // whatever the user has already written or added
        if (!option || !parseInt(option.value, 10) || !form) {
            return;
        }
        const data = option.dataset;

        const subject = templateField(form, 'subject') || document.getElementById('subjectInput');
        if (subject && data.templateSubject) {
            subject.value = data.templateSubject;
        }

        const details = templateField(form, 'details') || document.getElementById('detailsInput');
        if (details && data.templateDetails) {
            const editor = window.tinymce && details.id ? tinymce.get(details.id) : null;
            if (editor) {
                editor.setContent(data.templateDetails);
            } else {
                details.value = data.templateDetails;
            }
        }

        pickOption(templateField(form, 'priority'), data.templatePriority);
        pickOption(templateField(form, 'category'), data.templateCategory);
        pickOption(templateField(form, 'assigned'), data.templateAssigned);

        const billable = templateField(form, 'billable');
        if (billable && data.templateBillable !== '' && data.templateBillable !== undefined) {
            billable.checked = data.templateBillable === '1';
        }

        const due = templateField(form, 'due');
        if (due && data.templateDue) {
            due.value = data.templateDue;
        }

        // Its tags are added to any already picked
        const tags = templateField(form, 'tags');
        if (tags && data.templateTags) {
            data.templateTags.split(',').forEach(function (id) {
                const tag = Array.from(tags.options).find(o => o.value === id);
                if (tag) {
                    tag.selected = true;
                }
            });
            tags.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (document.getElementById('ticketTasksContainer')) {
            let tasks = [];
            try {
                tasks = JSON.parse(data.templateTasks || '[]');
            } catch (error) {
                tasks = [];
            }
            setTaskRows(tasks);
        }
    }

    document.addEventListener('change', function (e) {
        const select = e.target.closest('[data-template-picker]');
        if (select) {
            applyTemplate(select);
        }
    });

    // Opened for one template - New ticket from it - it is filled in straight away
    document.querySelectorAll('[data-template-picker][data-template-apply]').forEach(function (select) {
        select.removeAttribute('data-template-apply');
        applyTemplate(select);
    });

})();
