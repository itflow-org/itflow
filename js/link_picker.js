/*
 * ITFlow - Link picker: a Link button opens the picker every field has
 * (js/itflow_select.js) under it, rather than a window that held only that.
 *
 *   <button data-link-picker data-link-from="contact" data-link-id="4"
 *           data-link-to="asset" data-csrf="...">  - linkPickerButton()
 *
 * data-link-to empty is everything a document or file can be linked to, in
 * groups. The choices come from agent/ajax.php (link_picker), the ones linked
 * already ticked: tick to link, untick to unlink, and the changes are saved as
 * the picker closes (link_picker_save) - then the page loads again with the
 * message, so every list and count on it is right. In a window (a quick look)
 * the window loads again instead, and the message is a toast.
 *
 * In a .dropdown-menu the picker opens under the menu's button, as the menu
 * closes - and any menu opening closes the picker. functions/link_picker.php
 * has the server side.
 */
(function () {
    'use strict';

    if (window.itflowLinkPicker) {
        return; // re-run by an ajax modal - the listeners are already in place
    }
    window.itflowLinkPicker = true;

    var open = null; // {button, select} while a picker is up

    function toast(message, type) {
        if (typeof window.itflowToast === 'function') {
            window.itflowToast(message, type);
        }
    }

    // A menu item's picker hangs off the menu's button
    function anchorFor(button) {
        var menu = button.closest('.dropdown-menu');
        var toggle = menu && menu.parentElement ? menu.parentElement.querySelector('[data-bs-toggle="dropdown"]') : null;
        return toggle || button;
    }

    function chosen(select) {
        return Array.prototype.filter.call(select.options, function (option) {
            return option.selected;
        }).map(function (option) {
            return option.value;
        });
    }

    function json(response) {
        return response.json().catch(function () {
            return { error: 'That didn\'t work - try again, or reload the page.' };
        });
    }

    // The window it was in, loaded again - its lists show the change
    function reloadModal(modal) {
        fetch(modal.dataset.modalUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(json)
            .then(function (response) {
                var content = modal.querySelector('.modal-content');
                if (!content || !response.content) {
                    return;
                }
                content.innerHTML = response.content;
                if (typeof window.runScriptsInOrder === 'function') {
                    window.runScriptsInOrder(Array.prototype.slice.call(content.querySelectorAll('script')));
                }
                if (typeof window.itflowTabFold === 'function') {
                    window.itflowTabFold();
                }
            });
    }

    function save(button, add, remove) {
        var modal = button.closest('.modal');
        var inModal = !!(modal && modal.dataset.modalUrl);
        var body = new URLSearchParams();
        body.append('link_picker_save', '1');
        body.append('csrf_token', button.dataset.csrf || '');
        body.append('from', button.dataset.linkFrom);
        body.append('id', button.dataset.linkId);
        body.append('to', button.dataset.linkTo || '');
        add.forEach(function (value) {
            body.append('add[]', value);
        });
        remove.forEach(function (value) {
            body.append('remove[]', value);
        });
        if (!inModal) {
            body.append('flash', '1');
        }
        fetch('ajax.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(json)
            .then(function (answer) {
                if (answer.error) {
                    toast(answer.error, 'error');
                    return;
                }
                if (inModal) {
                    toast(answer.message.replace(/<[^>]*>/g, ''), answer.type);
                    reloadModal(modal);
                } else {
                    window.location.reload();
                }
            })
            .catch(function () {
                toast('That didn\'t save - try again.', 'error');
            });
    }

    function show(button, data) {
        // A select no one sees, in the same window as the button, so the panel opens there
        var holder = document.createElement('div');
        holder.hidden = true;
        (button.closest('.modal-content') || document.body).appendChild(holder);
        var select = document.createElement('select');
        select.multiple = true;
        select.className = 'form-select';
        select.dataset.placeholder = data.title;
        select.dataset.search = 'true';
        select.dataset.recent = 'false';
        select.innerHTML = data.options;
        holder.appendChild(select);

        var before = chosen(select);
        var picker = window.itflowSelect(select);
        if (!picker) {
            holder.remove();
            return;
        }
        picker.anchor = anchorFor(button);
        open = { button: button, select: select };
        button.setAttribute('aria-expanded', 'true');

        select.addEventListener('itflow:close', function () {
            open = null;
            button.setAttribute('aria-expanded', 'false');
            var after = chosen(select);
            var add = after.filter(function (value) {
                return before.indexOf(value) < 0;
            });
            var remove = before.filter(function (value) {
                return after.indexOf(value) < 0;
            });
            setTimeout(function () {
                holder.remove();
            });
            if (add.length || remove.length) {
                save(button, add, remove);
            }
        }, { once: true });
        picker.open();
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-link-picker]');
        if (!button) {
            return;
        }
        e.preventDefault();
        // Its own button again closes it
        if (open && open.button === button) {
            open.select.itflowSelect.close(true, false);
            return;
        }
        if (button.dataset.loading) {
            return;
        }
        button.dataset.loading = '1';
        var query = new URLSearchParams({
            link_picker: '1',
            from: button.dataset.linkFrom || '',
            id: button.dataset.linkId || '',
            to: button.dataset.linkTo || ''
        });
        fetch('ajax.php?' + query.toString(), { credentials: 'same-origin' })
            .then(json)
            .then(function (data) {
                delete button.dataset.loading;
                if (data.error) {
                    toast(data.error, 'error');
                } else if (!data.count) {
                    toast('There is nothing of this client\'s to link here yet.', 'info');
                } else {
                    show(button, data);
                }
            })
            .catch(function () {
                delete button.dataset.loading;
                toast('That didn\'t load - try again.', 'error');
            });
    });

    // Opening a menu closes the picker, as a click anywhere else would
    document.addEventListener('show.bs.dropdown', function () {
        if (open) {
            open.select.itflowSelect.close(true, false);
        }
    });
})();
