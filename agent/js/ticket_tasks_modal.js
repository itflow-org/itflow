// Editable task rows for the ticket and recurring ticket add/edit modals, and the
// ticket template picker (agent/includes/inc_ticket_template_select.php) that
// fills in the form - its fields marked data-template-field - and draws what the
// template asks for (its fill-ins) under it.
//
// Rows submit as parallel tasks[] and task_estimates[] arrays, aligned by their
// order in the form, so removing a row removes both of its inputs together. A
// template task that is only for some answers - "Order a laptop" when Needs a
// laptop is Yes - is hidden, and left out of the form, while the answer is
// another one; until it is answered it stays.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again in one
// page load, which re-runs this file - top-level const/let would throw on the
// second run. The document-wide listeners are bound on the first run only (the
// window flag), or every modal opened would add one more of each and "Add Task"
// would add a row per time the window had been opened.

(function () {

    // Builds one task row. Values are assigned as properties rather than built into
    // markup, so a task name containing quotes or angle brackets needs no escaping.
    // when: {fill_in, value} - the answer the task is for, or nothing.
    function buildTaskRow(taskName, taskEstimate, when) {

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

        if (when && when.fill_in) {
            row.dataset.whenFillIn = when.fill_in;
            row.dataset.whenValue = when.value || '';
            const note = document.createElement("div");
            note.className = "form-text mt-1";
            note.innerHTML = '<i class="fas fa-fw fa-code-branch me-1"></i>';
            note.appendChild(document.createTextNode('Only when ' + when.fill_in + ' is ' + (when.value || '')));
            nameColumn.appendChild(note);
        }

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

    function addTaskRow(taskName, taskEstimate, when) {

        const container = document.getElementById("ticketTasksContainer");

        if (!container) {
            return;
        }

        container.appendChild(buildTaskRow(taskName, taskEstimate, when));
    }

    // Replaces the whole list - used when a template is picked
    function setTaskRows(tasks) {

        const container = document.getElementById("ticketTasksContainer");

        if (!container) {
            return;
        }

        container.innerHTML = '';

        (tasks || []).forEach(task => {
            addTaskRow(task.name, task.estimate, task.when);
        });
    }

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

    function parseJson(text, fallback) {
        try {
            return JSON.parse(text || '') || fallback;
        } catch (error) {
            return fallback;
        }
    }

    /* ---------- fill-ins ---------- */

    const FILL_IN_ICONS = { text: 'fa-font', date: 'fa-calendar-day', list: 'fa-list-ul', yesno: 'fa-toggle-on' };

    function fillInBox(select) {
        const form = select.closest('form');
        return form ? form.querySelector('[data-fill-ins-box]') : null;
    }

    // The answers in the form now - lowercase name => value
    function fillInAnswers(form) {
        const answers = {};
        form.querySelectorAll('[data-fill-in-name]').forEach(function (control) {
            answers[control.dataset.fillInName.toLowerCase()] = control.value;
        });
        return answers;
    }

    // The fill-in's field in the form, by name, whatever its case
    function fillInControl(form, name) {
        return Array.from(form.querySelectorAll('[data-fill-in-name]')).find(c => c.dataset.fillInName.toLowerCase() === name.toLowerCase()) || null;
    }

    function buildFillInControl(fillIn, value) {
        let control;
        if (fillIn.type === 'list' || fillIn.type === 'yesno') {
            control = document.createElement('select');
            control.className = 'form-select' + (fillIn.type === 'list' ? ' select2' : '');
            const choices = fillIn.type === 'yesno' ? ['Yes', 'No'] : (fillIn.options || []);
            control.appendChild(new Option('- Choose -', ''));
            choices.forEach(function (choice) {
                control.appendChild(new Option(choice, choice));
            });
            const match = choices.find(c => c.toLowerCase() === String(value || '').toLowerCase());
            control.value = match || '';
        } else {
            control = document.createElement('input');
            control.type = fillIn.type === 'date' ? 'date' : 'text';
            control.className = 'form-control';
            if (fillIn.type !== 'date') {
                control.maxLength = 500;
            }
            control.value = value || '';
        }
        control.name = 'fill_ins[' + fillIn.name + ']';
        control.dataset.fillInName = fillIn.name;
        control.required = !!fillIn.required;
        control.setAttribute('aria-label', fillIn.name);
        return control;
    }

    // Draw what the picked template asks for - keeping anything already answered
    // under the same name, or the answers the form was opened with
    function renderFillIns(select) {
        const box = fillInBox(select);
        if (!box) {
            return;
        }
        const form = select.closest('form');
        const option = select.options[select.selectedIndex];
        const fillIns = option && parseInt(option.value, 10) ? parseJson(option.dataset.templateFillIns, []) : [];
        const values = box.dataset.rendered ? fillInAnswers(form) : {};
        if (!box.dataset.rendered) {
            const start = parseJson(box.dataset.fillInValues, {});
            Object.keys(start).forEach(function (name) {
                values[name.toLowerCase()] = start[name];
            });
        }
        box.dataset.rendered = '1';
        box.innerHTML = '';
        box.hidden = !fillIns.length;
        if (!fillIns.length) {
            updateTaskConditions(form);
            return;
        }

        box.className = 'itflow-fill-ins border rounded p-3 mt-2 bg-body-tertiary';
        const heading = document.createElement('div');
        heading.className = 'fw-semibold mb-1';
        heading.innerHTML = '<i class="fas fa-fw fa-pen-square text-secondary me-1"></i>';
        heading.appendChild(document.createTextNode(option.text.replace(/ \(archived\)$/, '') + ' asks for'));
        box.appendChild(heading);
        const intro = document.createElement('div');
        intro.className = 'small text-secondary mb-2';
        intro.textContent = 'Each answer goes in wherever its [Name] is, and stays on the ticket. Leave one blank to answer it later.';
        box.appendChild(intro);

        const grid = document.createElement('div');
        grid.className = 'row g-2';
        fillIns.forEach(function (fillIn) {
            const col = document.createElement('div');
            col.className = 'col-md-6';

            const label = document.createElement('label');
            label.className = 'form-label mb-1';
            label.textContent = fillIn.name;
            if (fillIn.required) {
                label.insertAdjacentHTML('beforeend', ' <strong class="text-danger">*</strong>');
            }
            col.appendChild(label);

            const group = document.createElement('div');
            group.className = 'input-group';
            const icon = document.createElement('span');
            icon.className = 'input-group-text';
            icon.innerHTML = '<i class="fas fa-fw ' + (FILL_IN_ICONS[fillIn.type] || 'fa-font') + '"></i>';
            group.appendChild(icon);
            group.appendChild(buildFillInControl(fillIn, values[fillIn.name.toLowerCase()]));
            col.appendChild(group);

            if (fillIn.help) {
                const help = document.createElement('div');
                help.className = 'form-text';
                help.textContent = fillIn.help;
                col.appendChild(help);
            }
            grid.appendChild(col);
        });
        box.appendChild(grid);
        updateTaskConditions(form);
    }

    // Hide, and leave out of the form, the template tasks for another answer. One
    // not answered yet stays, as does one for an answer its fill-in no longer offers.
    function updateTaskConditions(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('.ticket-task-row[data-when-fill-in]').forEach(function (row) {
            const control = fillInControl(form, row.dataset.whenFillIn);
            const want = (row.dataset.whenValue || '').toLowerCase();
            const offered = control && control.tagName === 'SELECT' && Array.from(control.options).some(o => o.value !== '' && o.value.toLowerCase() === want);
            const answer = control ? control.value : '';
            const applies = !offered || answer === '' || answer.toLowerCase() === want;
            row.hidden = !applies;
            row.querySelectorAll('input').forEach(function (input) {
                input.disabled = !applies;
            });
        });
    }

    // Write a template into its form: what it sets, field by field. A field
    // the form doesn't have is skipped, and anything the template leaves
    // alone ('' on the option) stays as it is.
    function applyTemplate(select) {

        const option = select.options[select.selectedIndex];
        const form = select.closest('form');

        // Selecting "- No Template -" only unlinks the template - it must not wipe
        // whatever the user has already written or added. Its questions go.
        if (!option || !parseInt(option.value, 10) || !form) {
            renderFillIns(select);
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

        if (form.querySelector('#ticketTasksContainer')) {
            setTaskRows(parseJson(data.templateTasks, []));
        }

        renderFillIns(select);
    }

    if (!window.itflowTicketTemplateBound) {
        window.itflowTicketTemplateBound = true;

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

        document.addEventListener('change', function (e) {
            const select = e.target.closest('[data-template-picker]');
            if (select) {
                applyTemplate(select);
                return;
            }
            if (e.target.closest('[data-fill-in-name]')) {
                updateTaskConditions(e.target.closest('form'));
            }
        });
    }

    // Opened for one template - New ticket from it - it is filled in straight
    // away; a form opened with a template already chosen (a recurring ticket
    // being edited) just gets its questions, with the answers it has
    document.querySelectorAll('[data-template-picker]').forEach(function (select) {
        if (select.hasAttribute('data-template-apply')) {
            select.removeAttribute('data-template-apply');
            applyTemplate(select);
        } else {
            const box = fillInBox(select);
            if (box && !box.dataset.rendered) {
                renderFillIns(select);
            }
        }
    });

})();
