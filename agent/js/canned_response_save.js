/*
 * Save your reply as a canned response (agent/user/modals/canned_response_save.php),
 * from the picker above a ticket's reply.
 *
 * - The reply being written comes into the window's editor with the ticket's
 *   details swapped for placeholders (agent/ajax.php canned_response_unfill),
 *   saying which. Turning the swap off puts the reply back as it was written;
 *   when it has been changed in the window since, it asks first.
 * - Saving posts the window (agent/user/post/canned_response.php, answered in
 *   JSON) without leaving the ticket, so the reply stays as it is. The new one
 *   goes into the picker where it is offered on this ticket, and a toast says
 *   it was saved.
 *
 * The window is an ajax modal that runs this each time it opens: each opening
 * sets up its own form, and the save listener is bound once.
 */
(function () {

    function editorReady(id, then, tries) {
        const editor = window.tinymce ? tinymce.get(id) : null;
        if (editor && editor.initialized) {
            then(editor);
        } else if ((tries || 0) < 200) {
            setTimeout(function () {
                editorReady(id, then, (tries || 0) + 1);
            }, 50);
        }
    }

    function showError(form, text) {
        const box = form.querySelector('[data-canned-save-error]');
        if (box) {
            box.textContent = text;
            box.hidden = false;
        }
    }

    function swappedText(placeholders) {
        return placeholders.length
            ? 'Swapped for placeholders: ' + placeholders.map(function (name) {
                return '[' + name + ']';
            }).join(', ') + ' - filled in from each ticket it goes into.'
            : "None of this ticket's details were in it to swap.";
    }

    // Set up the window just opened
    function setUp(form) {
        form.dataset.cannedSaveReady = '1';
        const source = window.tinymce ? tinymce.get(form.dataset.editor) : null;
        const textarea = document.getElementById(form.dataset.editor);
        const written = source ? source.getContent() : (textarea ? textarea.value : '');
        const swapped = form.querySelector('[data-canned-save-swapped]');
        const toggle = form.querySelector('[data-canned-save-unfill]');
        const state = { original: written, unfilled: written, placeholders: [], lastSet: null };

        function put(html) {
            editorReady('cannedResponseBody', function (editor) {
                editor.setContent(html);
                state.lastSet = editor.getContent();
            });
        }

        const body = new FormData();
        body.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
        body.append('ticket_id', form.dataset.ticketId);
        body.append('html', written);
        fetch('/agent/ajax.php?canned_response_unfill', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                state.original = data.original;
                state.unfilled = data.html;
                state.placeholders = data.placeholders || [];
                swapped.textContent = written.trim() === '' ? 'Your reply is empty - write the response here.' : swappedText(state.placeholders);
                put(toggle.checked ? state.unfilled : state.original);
            })
            .catch(function () {
                swapped.textContent = "Couldn't swap this ticket's details - your reply is here as you wrote it.";
                toggle.checked = false;
                toggle.disabled = true;
                put(written);
            });

        // Swapped, or as written - asking first when it was changed here since
        toggle.addEventListener('change', function () {
            const editor = window.tinymce ? tinymce.get('cannedResponseBody') : null;
            if (!editor) {
                return;
            }
            const wanted = toggle.checked ? state.unfilled : state.original;
            const go = function () {
                put(wanted);
                swapped.textContent = toggle.checked ? swappedText(state.placeholders) : 'As you wrote it, with this ticket\'s details in it.';
            };
            if (state.lastSet === null || editor.getContent() === state.lastSet || typeof Swal === 'undefined') {
                go();
                return;
            }
            Swal.fire({
                theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
                title: 'Start again from your reply?',
                text: 'What you changed here goes.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Start again',
                cancelButtonText: 'Keep my changes',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary mx-1',
                    cancelButton: 'btn btn-secondary mx-1'
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    go();
                } else {
                    toggle.checked = !toggle.checked;
                }
            });
        });
    }

    // The saved one, into the picker where it is offered on this ticket
    function addToPicker(pickerId, data) {
        const picker = pickerId ? document.getElementById(pickerId) : null;
        const list = picker ? picker.querySelector('[data-canned-list]') : null;
        if (!list || !data.group) {
            return;
        }
        let group = Array.from(list.querySelectorAll('[data-canned-group]')).find(function (each) {
            const header = each.querySelector('.dropdown-header');
            return header && header.textContent === data.group;
        });
        if (!group) {
            group = document.createElement('div');
            group.setAttribute('data-canned-group', '');
            const header = document.createElement('h6');
            header.className = 'dropdown-header';
            header.textContent = data.group;
            group.appendChild(header);
            // Yours first, Every ticket last, a category's before it
            const groups = list.querySelectorAll('[data-canned-group]');
            const every = Array.from(groups).find(function (each) {
                const h = each.querySelector('.dropdown-header');
                return h && h.textContent === 'Every ticket';
            });
            const end = list.querySelector('[data-canned-empty], [data-canned-none]');
            list.insertBefore(group, (data.group === 'Yours' ? groups[0] : every) || end);
        }
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'dropdown-item itflow-canned-item';
        item.dataset.cannedId = data.id;
        item.dataset.cannedName = data.name;
        item.dataset.cannedPreview = data.preview;
        item.dataset.cannedSearchText = (data.name + ' ' + data.preview).toLowerCase();
        const name = document.createElement('span');
        name.className = 'd-block fw-semibold text-truncate';
        name.textContent = data.name;
        item.appendChild(name);
        if (data.preview) {
            const preview = document.createElement('span');
            preview.className = 'd-block small text-body-secondary itflow-canned-preview';
            preview.textContent = data.preview;
            item.appendChild(preview);
        }
        // What it sets up, as the server drew it
        if (data.sets_up) {
            const setsUp = document.createElement('span');
            setsUp.className = 'd-block mt-1';
            setsUp.innerHTML = data.sets_up;
            item.appendChild(setsUp);
        }
        // In the group by name
        const after = Array.from(group.querySelectorAll('[data-canned-id]')).find(function (each) {
            return each.dataset.cannedName.localeCompare(data.name, undefined, { sensitivity: 'base', numeric: true }) > 0;
        });
        group.insertBefore(item, after || null);
        const none = picker.querySelector('[data-canned-empty]');
        if (none) {
            none.remove();
        }
    }

    document.querySelectorAll('form[data-canned-save]').forEach(function (form) {
        if (!form.dataset.cannedSaveReady) {
            setUp(form);
        }
    });

    if (window.itflowCannedSaveBound) {
        return;
    }
    window.itflowCannedSaveBound = true;

    // Saved without leaving the ticket
    document.addEventListener('submit', function (e) {
        const form = e.target.closest ? e.target.closest('form[data-canned-save]') : null;
        if (!form) {
            return;
        }
        e.preventDefault();
        if (window.tinymce) {
            tinymce.triggerSave();
        }
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        form.querySelector('[data-canned-save-error]').hidden = true;
        fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(response.status === 403 ? 'your session may have expired - reload the page and try again' : 'HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.ok) {
                    showError(form, data.error || "It wasn't saved.");
                    button.disabled = false;
                    return;
                }
                addToPicker(form.dataset.picker, data);
                const modal = form.closest('.modal');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).hide();
                }
                if (typeof itflowToast === 'function') {
                    itflowToast(data.message, data.refused && data.refused.length ? 'warning' : 'success');
                }
            })
            .catch(function (error) {
                showError(form, "Couldn't save it - " + error.message + '.');
                button.disabled = false;
            });
    });
})();
