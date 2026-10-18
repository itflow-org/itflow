/*
 * The canned response picker (agent/includes/inc_canned_response_picker.php),
 * on a ticket's reply and in Update & Reply.
 *
 * - The list searches as you type (by name and what it says); Enter picks the
 *   first one shown, the down arrow goes into the list.
 * - Picking one fetches it filled in for the ticket (agent/ajax.php
 *   get_canned_response), puts it in the reply where the cursor is, notes it on
 *   the form (canned_response_ids[], for how often each is used) and sets up
 *   what it sets up - who sees the reply, the status after it and time worked
 *   added - saying under the picker what it did. Typing / in the reply box
 *   picks through window.itflowCannedPick (js/app.js).
 * - Sending a reply a response went into, with [something] in square brackets
 *   still in it, asks first - Fill them in selects the first one in the reply.
 *   In Update & Reply the placeholders are filled in for each ticket as it is
 *   sent, so only the others are asked about.
 *
 * Update & Reply is an ajax window that runs this each time it opens, so the
 * listeners are bound once.
 */
(function () {
    if (window.itflowCannedPickerBound) {
        return;
    }
    window.itflowCannedPickerBound = true;

    // A word in square brackets - TICKET_PLACEHOLDER_NAME, with the editor's non-breaking spaces
    const BRACKETS = /\[([A-Za-z][A-Za-z0-9  _\-\/]{0,40})\]/g;

    function itemsOf(picker) {
        return Array.from(picker.querySelectorAll('[data-canned-id]'));
    }

    function filter(picker, value) {
        const words = value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;
        picker.querySelectorAll('[data-canned-group]').forEach(function (group) {
            let inGroup = 0;
            group.querySelectorAll('[data-canned-id]').forEach(function (item) {
                const match = words.every(function (word) {
                    return item.dataset.cannedSearchText.indexOf(word) !== -1;
                });
                item.hidden = !match;
                inGroup += match ? 1 : 0;
            });
            group.hidden = inGroup === 0;
            shown += inGroup;
        });
        const none = picker.querySelector('[data-canned-none]');
        if (none) {
            none.hidden = shown > 0;
        }
    }

    function say(picker, text, error) {
        const line = picker.querySelector('[data-canned-applied]');
        if (!line) {
            return;
        }
        line.textContent = text;
        line.classList.toggle('text-danger', !!error);
        line.classList.toggle('text-body-secondary', !error);
        line.hidden = false;
    }

    function insert(editorId, html) {
        const editor = window.tinymce ? tinymce.get(editorId) : null;
        if (editor) {
            editor.insertContent(html);
            editor.focus();
            return;
        }
        const textarea = document.getElementById(editorId);
        if (textarea) {
            textarea.value += html;
        }
    }

    function setSelect(select, value) {
        if (select.itflowSelect) {
            select.itflowSelect.setValue(value);
        } else {
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    // Set up on the form what the response sets up - what was done, in words
    function apply(form, data) {
        const done = [];
        if (!form) {
            return done;
        }

        // Who sees it: the ticket's reply types, or Update & Reply's internal box
        if (data.reply_type !== null) {
            const radios = form.querySelectorAll('input[name="public_reply_type"]');
            const internal = form.querySelector('input[type="checkbox"][name="bulk_private_reply"]');
            if (radios.length) {
                let radio = form.querySelector('input[name="public_reply_type"][value="' + data.reply_type + '"]');
                let label = data.reply_type_name;
                if (!radio && data.reply_type === 2) {
                    // No email address to send to - a public note is the nearest
                    radio = form.querySelector('input[name="public_reply_type"][value="1"]');
                    label = 'Public note, as there is nobody to email';
                }
                if (radio) {
                    if (!radio.checked) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    done.push('set the reply to ' + label);
                }
            } else if (internal) {
                internal.checked = data.reply_type === 0;
                done.push(data.reply_type === 0 ? 'marked it internal' : 'made it a public reply');
            }
        }

        // The status after it
        if (data.ticket_status !== null) {
            const select = form.querySelector('select[name="status"], select[name="bulk_status"]');
            if (select && select.querySelector('option[value="' + data.ticket_status + '"]')) {
                setSelect(select, String(data.ticket_status));
                done.push('set the status to ' + data.status_name);
            } else if (select) {
                done.push('left the status - ' + data.status_name + ' isn\'t on offer here');
            }
        }

        // Time worked, added: the reply's hours, minutes and seconds (the timer
        // reads them back on change), or Update & Reply's HH:MM:SS
        if (data.minutes > 0) {
            const hours = form.querySelector('input[name="hours"]');
            const minutes = form.querySelector('input[name="minutes"]');
            const seconds = form.querySelector('input[name="seconds"]');
            const time = form.querySelector('input[name="time"]');
            let total = null;
            if (hours && minutes && seconds) {
                total = (parseInt(hours.value, 10) || 0) * 3600 + (parseInt(minutes.value, 10) || 0) * 60 + (parseInt(seconds.value, 10) || 0) + data.minutes * 60;
                hours.value = pad(Math.floor(total / 3600));
                minutes.value = pad(Math.floor(total % 3600 / 60));
                seconds.value = pad(total % 60);
                minutes.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (time) {
                const parts = time.value.split(':').map(function (part) {
                    return parseInt(part, 10) || 0;
                });
                total = (parts[0] || 0) * 3600 + (parts[1] || 0) * 60 + (parts[2] || 0) + data.minutes * 60;
                time.value = pad(Math.min(23, Math.floor(total / 3600))) + ':' + pad(Math.floor(total % 3600 / 60)) + ':' + pad(total % 60);
            }
            if (total !== null) {
                done.push('added ' + data.minutes_text + ' to the time worked');
            }
        }
        return done;
    }

    function pick(picker, id) {
        const toggle = picker.querySelector('[data-canned-toggle]');
        if (toggle && window.bootstrap) {
            bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        }
        return fetch('/agent/ajax.php?get_canned_response=' + encodeURIComponent(id) + '&ticket_id=' + encodeURIComponent(picker.dataset.ticketId || '0'), { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.found) {
                    say(picker, 'That canned response is gone - it may have been archived or deleted.', true);
                    return;
                }
                const form = picker.closest('form');
                if (form && !form.querySelector('input[name="canned_response_ids[]"][value="' + data.id + '"]')) {
                    const used = document.createElement('input');
                    used.type = 'hidden';
                    used.name = 'canned_response_ids[]';
                    used.value = data.id;
                    form.appendChild(used);
                }
                insert(picker.dataset.editor, data.body);
                const done = apply(form, data);
                say(picker, data.name + ' went in' + (done.length ? ', and ' + done.join(', ') : '') + '.');
            })
            .catch(function () {
                say(picker, 'Could not load that canned response.', true);
            });
    }

    // For the / menu in the reply box (js/app.js)
    window.itflowCannedPick = function (pickerId, id) {
        const picker = document.getElementById(pickerId);
        if (picker) {
            pick(picker, id);
        }
    };

    // Search as you type
    document.addEventListener('input', function (e) {
        const search = e.target.closest('[data-canned-search]');
        if (search) {
            filter(search.closest('[data-canned-picker]'), search.value);
        }
    });

    // Opened: an empty search, ready to type into
    document.addEventListener('shown.bs.dropdown', function (e) {
        const picker = e.target.closest ? e.target.closest('[data-canned-picker]') : null;
        if (!picker) {
            return;
        }
        const search = picker.querySelector('[data-canned-search]');
        search.value = '';
        filter(picker, '');
        search.focus();
    });

    // Enter picks the first one shown; the down arrow goes into the list
    document.addEventListener('keydown', function (e) {
        const search = e.target.closest ? e.target.closest('[data-canned-search]') : null;
        if (!search) {
            return;
        }
        const picker = search.closest('[data-canned-picker]');
        const first = itemsOf(picker).find(function (item) {
            return !item.hidden;
        });
        if (e.key === 'Enter') {
            e.preventDefault();
            if (first) {
                pick(picker, first.dataset.cannedId);
            }
        } else if (e.key === 'ArrowDown' && first) {
            e.preventDefault();
            first.focus();
        }
    });

    // A click on one picks it
    document.addEventListener('click', function (e) {
        const item = e.target.closest('[data-canned-picker] [data-canned-id]');
        if (item) {
            e.preventDefault();
            pick(item.closest('[data-canned-picker]'), item.dataset.cannedId);
        }
    });

    // The first [word] left in the reply, selected for typing over
    function selectBracket(editor, name) {
        const pattern = new RegExp('\\[' + name.split(/[  ]+/).map(function (word) {
            return word.replace(/[.*+?^${}()|[\]\\\/-]/g, '\\$&');
        }).join('[ \\u00a0]+') + '\\]');
        const walker = editor.getDoc().createTreeWalker(editor.getBody(), NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            const match = pattern.exec(node.nodeValue);
            if (match) {
                const range = editor.dom.createRng();
                range.setStart(node, match.index);
                range.setEnd(node, match.index + match[0].length);
                editor.selection.setRng(range);
                editor.selection.scrollIntoView();
                break;
            }
        }
        editor.focus();
    }

    // Sending a reply a response went into with [words] still in it asks first
    document.addEventListener('submit', function (e) {
        const form = e.target;
        const picker = form.querySelector ? form.querySelector('[data-canned-picker]') : null;
        if (!picker || !form.querySelector('input[name="canned_response_ids[]"]') || typeof Swal === 'undefined') {
            return;
        }
        if (form.dataset.cannedSendAnyway === '1') {
            delete form.dataset.cannedSendAnyway;
            return;
        }
        const editor = window.tinymce ? tinymce.get(picker.dataset.editor) : null;
        const textarea = document.getElementById(picker.dataset.editor);
        const text = editor ? editor.getContent({ format: 'text' }) : (textarea ? textarea.value : '');
        // In Update & Reply these are filled in for each ticket as it is sent
        const filledLater = (picker.dataset.ticketId === '0' ? (picker.dataset.cannedFilled || '').split('|') : []).map(function (name) {
            return name.toLowerCase();
        });
        const left = [];
        let match;
        BRACKETS.lastIndex = 0;
        while ((match = BRACKETS.exec(text)) !== null) {
            const name = match[1].replace(/[  ]+/g, ' ').trim();
            if (filledLater.indexOf(name.toLowerCase()) === -1 && left.indexOf(name) === -1) {
                left.push(name);
            }
        }
        if (!left.length) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        const submitter = e.submitter || null;
        const list = document.createElement('div');
        list.textContent = 'Your reply still has ' + left.map(function (name) {
            return '[' + name + ']';
        }).join(', ') + ' in it.';
        Swal.fire({
            theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
            title: 'Still to fill in',
            html: list,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Send anyway',
            cancelButtonText: 'Fill them in',
            reverseButtons: true,
            focusCancel: true,
            // Back to the reply box, not the Send button
            returnFocus: false,
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary mx-1',
                cancelButton: 'btn btn-secondary mx-1'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                form.dataset.cannedSendAnyway = '1';
                if (form.requestSubmit) {
                    form.requestSubmit(submitter);
                } else {
                    form.submit();
                }
            } else if (editor) {
                setTimeout(function () {
                    selectBracket(editor, left[0]);
                }, 0);
            }
        });
    }, true);
})();
