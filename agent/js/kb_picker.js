/*
 * The Knowledge Base picker above a ticket's reply (agent/includes/inc_kb_picker.php).
 *
 * - It opens on the articles that might help with the ticket; typing searches
 *   the rest (agent/ajax.php kb_picker, which answers the rows as HTML for the
 *   ticket's client), and clearing the search goes back to the suggestions.
 * - Link puts a link to the article in the reply where the cursor is - the
 *   public help center's, or their portal's - under its title.
 * - Insert fetches what the article says (agent/ajax.php kb_article_reply) and
 *   puts it in the reply, pictures and all.
 * - Either way the list closes and a line under the button says what went in.
 *   Cancel, Escape or a click into the reply closes it too.
 */
(function () {
    if (window.itflowKbPickerBound) {
        return;
    }
    window.itflowKbPickerBound = true;

    const suggested = new WeakMap();
    let timer = null;

    function say(picker, text, error) {
        const line = picker.querySelector('[data-kb-pick-said]');
        line.textContent = text;
        line.classList.toggle('text-danger', !!error);
        line.classList.toggle('text-body-secondary', !error);
        line.hidden = false;
    }

    function close(picker) {
        const toggle = picker.querySelector('[data-kb-pick-toggle]');
        if (toggle && window.bootstrap) {
            bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        }
    }

    function insert(picker, html) {
        const editor = window.tinymce ? tinymce.get(picker.dataset.editor) : null;
        if (editor) {
            editor.insertContent(html);
            editor.focus();
            return;
        }
        const textarea = document.getElementById(picker.dataset.editor);
        if (textarea) {
            textarea.value += html;
        }
    }

    function search(picker, q) {
        const list = picker.querySelector('[data-kb-pick-list]');
        if (!suggested.has(picker)) {
            suggested.set(picker, list.innerHTML);
        }
        clearTimeout(timer);
        if (q === '') {
            list.innerHTML = suggested.get(picker);
            return;
        }
        timer = setTimeout(function () {
            fetch('/agent/ajax.php?kb_picker=' + encodeURIComponent(q) + '&ticket_id=' + encodeURIComponent(picker.dataset.ticketId), { credentials: 'same-origin' })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (picker.querySelector('[data-kb-pick-search]').value.trim() !== q) {
                        return;
                    }
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    list.innerHTML = data.html;
                })
                .catch(function (error) {
                    const note = document.createElement('div');
                    note.className = 'px-3 py-3 small text-danger';
                    note.textContent = error && error.message ? error.message : 'The search didn\'t work - try again.';
                    list.replaceChildren(note);
                });
        }, 250);
    }

    document.addEventListener('input', function (e) {
        const box = e.target.closest ? e.target.closest('[data-kb-picker] [data-kb-pick-search]') : null;
        if (box) {
            search(box.closest('[data-kb-picker]'), box.value.trim());
        }
    });

    document.addEventListener('shown.bs.dropdown', function (e) {
        const picker = e.target.closest ? e.target.closest('[data-kb-picker]') : null;
        if (picker) {
            picker.querySelector('[data-kb-pick-search]').focus();
        }
    });

    document.addEventListener('click', function (e) {
        const cancel = e.target.closest('[data-kb-picker] [data-kb-pick-close]');
        if (cancel) {
            const picker = cancel.closest('[data-kb-picker]');
            close(picker);
            const editor = window.tinymce ? tinymce.get(picker.dataset.editor) : null;
            if (editor) {
                editor.focus();
            }
            return;
        }

        const button = e.target.closest('[data-kb-picker] [data-kb-pick-link], [data-kb-picker] [data-kb-pick-insert]');
        if (!button) {
            return;
        }
        e.preventDefault();
        const picker = button.closest('[data-kb-picker]');
        const item = button.closest('[data-kb-pick-item]');
        const title = item.dataset.kbTitle;

        if (button.hasAttribute('data-kb-pick-link')) {
            const p = document.createElement('p');
            const a = document.createElement('a');
            a.href = item.dataset.kbUrl;
            a.textContent = title;
            p.appendChild(a);
            insert(picker, p.outerHTML);
            close(picker);
            say(picker, 'A link to "' + title + '" is in the reply.');
            return;
        }

        button.disabled = true;
        fetch('/agent/ajax.php?kb_article_reply=' + encodeURIComponent(item.dataset.kbId) + '&ticket_id=' + encodeURIComponent(picker.dataset.ticketId), { credentials: 'same-origin' })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.html) {
                    throw new Error(data.error || '');
                }
                insert(picker, data.html);
                close(picker);
                say(picker, 'What "' + title + '" says is in the reply - read it through for this ticket.');
            })
            .catch(function (error) {
                say(picker, error && error.message ? error.message : 'It couldn\'t be put in the reply - try again.', true);
            })
            .finally(function () {
                button.disabled = false;
            });
    });
})();
