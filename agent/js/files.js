/*
 * agent/files.php - the folder tree, the grid's previewer, dragging files,
 * documents and folders onto a folder to move them, dropping files and folders
 * from the computer to upload them, and renaming from the name on the list.
 */
(function () {
    'use strict';

    // What is being dragged within the page - files and documents, or a folder - while it is
    var dragging = null;

    /** The folders dropped, as entries to walk - read during the drop, while they can be - or null when it was only files. */
    function droppedFolders(e) {
        var entries = Array.prototype.map.call((e.dataTransfer && e.dataTransfer.items) || [], function (item) {
            return item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
        }).filter(Boolean);
        return entries.some(function (entry) { return entry.isDirectory; }) ? entries : null;
    }

    /** Open the upload window at url, and hand it the dropped files - or folders, which it walks - once it is on screen. */
    function uploadInto(url, files, folders) {
        var trigger = document.createElement('a');
        trigger.className = 'ajax-modal d-none';
        trigger.href = '#';
        trigger.dataset.modalUrl = url;
        document.body.appendChild(trigger);
        trigger.click();
        trigger.remove();
        var tries = 0;
        (function handOver() {
            var input = document.querySelector('.modal.show [data-upload-input]');
            if (input && folders && input.itflowWalk) {
                Promise.all(folders.map(function (entry) { return input.itflowWalk(entry, ''); })).then(function (lists) {
                    input.itflowTakeFolder([].concat.apply([], lists));
                });
            } else if (input && !folders) {
                var transfer = new DataTransfer();
                Array.prototype.forEach.call(files, function (file) {
                    transfer.items.add(file);
                });
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (++tries < 50) {
                setTimeout(handOver, 100);
            }
        })();
    }

    /** Whether a drag carries files from the computer - not something dragged within the page. */
    function carriesFiles(e) {
        return !dragging && e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1;
    }

    // ---- Folder tree: subfolders open and close in place, remembered per client ----

    var tree = document.querySelector('.itflow-folder-tree');
    var storeKey = tree ? 'itflow.folders.' + tree.dataset.clientId : '';

    function remembered() {
        try {
            return JSON.parse(localStorage.getItem(storeKey) || '[]');
        } catch (e) {
            return [];
        }
    }

    function remember() {
        var open = [];
        tree.querySelectorAll('.itflow-folder.is-open[data-folder-id]').forEach(function (li) {
            open.push(li.dataset.folderId);
        });
        try {
            localStorage.setItem(storeKey, JSON.stringify(open));
        } catch (e) {
            // Private window - the tree just forgets
        }
    }

    if (tree) {
        // The path to the folder on show is opened by the server; reopen anything else left open last time
        remembered().forEach(function (id) {
            var li = tree.querySelector('.itflow-folder[data-folder-id="' + CSS.escape(String(id)) + '"]');
            if (li) {
                li.classList.add('is-open');
            }
        });
        tree.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-folder-toggle]');
            if (toggle) {
                toggle.closest('.itflow-folder').classList.toggle('is-open');
                remember();
            }
        });
        document.querySelectorAll('[data-folder-expand-all], [data-folder-collapse-all]').forEach(function (button) {
            button.addEventListener('click', function () {
                var open = button.hasAttribute('data-folder-expand-all');
                tree.querySelectorAll('.itflow-folder[data-folder-id]').forEach(function (li) {
                    if (li.querySelector('ul')) {
                        li.classList.toggle('is-open', open);
                    }
                });
                remember();
            });
        });
    }

    // ---- Grid previewer: arrows page through everything on show ----

    var items = window.itflowFilesPreview || [];
    var current = 0;
    var modal = document.getElementById('viewFileModal');

    function el(id) {
        return document.getElementById(id);
    }

    function draw() {
        var item = items[current];
        var image = el('modalImage'), frame = el('modalFrame'), fallback = el('modalFallback'),
            text = el('modalText'), doc = el('modalDocument');

        el('modalTitle').textContent = item.name;
        el('modalMeta').textContent = item.meta;
        el('modalPosition').textContent = (current + 1) + ' of ' + items.length;
        var action = el('modalDownload');
        action.href = item.download;
        action.innerHTML = '<i class="fas fa-fw fa-' + (item.kind === 'document' ? 'arrow-right' : 'download') + ' me-2"></i>';
        action.appendChild(document.createTextNode(item.action));
        var details = el('modalDetails');
        details.classList.toggle('d-none', !item.details);
        details.dataset.modalUrl = item.details || '';

        // Blank the last one first, or a heavy PDF keeps rendering behind the next image
        [image, frame].forEach(function (node) {
            node.classList.add('d-none');
            node.removeAttribute('src');
        });
        [fallback, text, doc].forEach(function (node) {
            node.classList.add('d-none');
        });
        doc.innerHTML = '';

        if (item.kind === 'document') {
            var requested = current;
            doc.innerHTML = '<p class="text-secondary mb-0">Loading...</p>';
            doc.classList.remove('d-none');
            fetch(item.preview, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (requested !== current) {
                        return;
                    }
                    // HTMLPurifier output - the same the document page renders
                    doc.innerHTML = data.content || '<p class="text-secondary mb-0">This document is empty.</p>';
                    if (typeof prettyContent === 'function') {
                        prettyContent();
                    }
                })
                .catch(function () {
                    if (requested === current) {
                        doc.innerHTML = '<p class="text-danger mb-0">Could not load this document.</p>';
                    }
                });
        } else if (item.kind === 'image') {
            image.src = item.preview;
            image.alt = item.name;
            image.classList.remove('d-none');
        } else if (item.kind === 'pdf' || item.kind === 'text') {
            frame.src = item.preview;
            frame.classList.remove('d-none');
        } else {
            el('modalFallbackIcon').className = 'fas fa-' + item.icon + ' fa-4x text-secondary';
            fallback.classList.remove('d-none');
        }

        var single = items.length < 2;
        el('modalPrev').classList.toggle('d-none', single);
        el('modalNext').classList.toggle('d-none', single);
    }

    function step(by) {
        current = (current + by + items.length) % items.length;
        draw();
    }

    if (modal && items.length) {
        document.addEventListener('click', function (e) {
            var tile = e.target.closest('[data-files-preview]');
            if (!tile) {
                return;
            }
            e.preventDefault();
            current = parseInt(tile.dataset.filesPreview, 10) || 0;
            draw();
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
        el('modalPrev').addEventListener('click', function () { step(-1); });
        el('modalNext').addEventListener('click', function () { step(1); });
        document.addEventListener('keydown', function (e) {
            if (!modal.classList.contains('show') || items.length < 2) {
                return;
            }
            if (e.key === 'ArrowRight') {
                step(1);
            } else if (e.key === 'ArrowLeft') {
                step(-1);
            }
        });
        // Details is a quick look - close the previewer first so the two never stack
        el('modalDetails').addEventListener('click', function () {
            var url = this.dataset.modalUrl;
            modal.addEventListener('hidden.bs.modal', function open() {
                modal.removeEventListener('hidden.bs.modal', open);
                var trigger = document.createElement('a');
                trigger.className = 'ajax-modal d-none';
                trigger.href = '#';
                trigger.dataset.modalUrl = url;
                trigger.dataset.modalSize = 'lg';
                document.body.appendChild(trigger);
                trigger.click();
                trigger.remove();
            });
            bootstrap.Modal.getInstance(modal).hide();
        });
    }

    // ---- Drag files, documents and folders onto a folder to move them ----
    // A row (or a card in the grid) or a folder in the tree drags onto a folder in the tree or the path above
    // the list. Dragging a ticked one takes every ticked one. Holding it over a closed folder opens it. The
    // move is Bulk Action's (bulk_move_files) or move_folder, and the page comes back saying what moved.
    // Files from the computer dropped on a folder in the tree upload into it.

    var mover = document.querySelector('[data-files-move]');
    if (mover) {
        var lit = null, springRow = null, springTimer = null, leaveTimer = null;

        // A link or picture in a row would drag its address instead of the row
        mover.querySelectorAll('[data-files-item] a, [data-files-item] img, [data-folder-drag] a').forEach(function (node) {
            node.draggable = false;
        });

        function folderItem(id) {
            return id && tree ? tree.querySelector('.itflow-folder[data-folder-id="' + CSS.escape(String(id)) + '"]') : null;
        }

        /** Whether what is being dragged can go into the folder target stands for. */
        function accepts(target, e) {
            var to = parseInt(target.dataset.folderDrop, 10) || 0;
            if (!dragging) {
                // Files from the computer, onto a folder in the tree
                return !!mover.dataset.filesUpload && carriesFiles(e) && !!target.closest('.itflow-folder-tree');
            }
            if (dragging.type === 'items') {
                return dragging.items.some(function (item) {
                    return item.folder !== to;
                });
            }
            // A folder - not where it is already, and never into itself or below itself
            var into = folderItem(to);
            return to !== dragging.parent && !(into && dragging.li.contains(into));
        }

        function light(target) {
            if (lit === target) {
                return;
            }
            if (lit) {
                lit.classList.remove('is-drop-target');
            }
            lit = target;
            if (lit) {
                lit.classList.add('is-drop-target');
            }
        }

        // A closed folder held over for a moment opens, to reach the folders in it
        function spring(row) {
            if (row === springRow) {
                return;
            }
            clearTimeout(springTimer);
            springRow = row;
            var item = row && row.closest('.itflow-folder');
            if (item && item.querySelector('ul') && !item.classList.contains('is-open')) {
                springTimer = setTimeout(function () {
                    item.classList.add('is-open');
                }, 700);
            }
        }

        function finish() {
            clearTimeout(leaveTimer);
            light(null);
            spring(null);
            if (dragging) {
                dragging.sources.forEach(function (source) {
                    source.classList.remove('is-dragging');
                });
            }
            dragging = null;
        }

        function post(fields) {
            var form = document.createElement('form');
            form.method = 'post';
            form.action = 'post.php';
            form.hidden = true;
            fields.unshift(['csrf_token', mover.dataset.filesMove]);
            fields.forEach(function (field) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = field[0];
                input.value = field[1];
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }

        mover.addEventListener('dragstart', function (e) {
            var item = e.target.closest('[data-files-item]');
            var folder = !item && e.target.closest('[data-folder-drag]');
            if (!item && !folder) {
                return;
            }
            var label, icon;
            if (item) {
                var box = item.querySelector('.bulk-select');
                var sources = box && box.checked
                    ? Array.prototype.map.call(document.querySelectorAll('#bulkActions .bulk-select:checked'), function (checked) {
                        return checked.closest('[data-files-item]');
                    }).filter(Boolean)
                    : [item];
                dragging = {
                    type: 'items',
                    sources: sources,
                    items: sources.map(function (source) {
                        return { kind: source.dataset.filesItem, id: source.dataset.id, folder: parseInt(source.dataset.folderId, 10) || 0 };
                    })
                };
                label = sources.length === 1 ? sources[0].dataset.name : sources.length + ' items';
                icon = sources.length > 1 ? 'fa-copy' : (sources[0].dataset.filesItem === 'document' ? 'fa-file-alt' : 'fa-file');
            } else {
                var li = folder.closest('.itflow-folder');
                var parent = li.parentElement.closest('.itflow-folder[data-folder-id]');
                dragging = {
                    type: 'folder',
                    sources: [folder],
                    id: parseInt(li.dataset.folderId, 10),
                    parent: parent ? parseInt(parent.dataset.folderId, 10) : 0,
                    li: li
                };
                label = folder.dataset.name;
                icon = 'fa-folder';
            }

            e.dataTransfer.clearData();
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', label);
            // What follows the pointer: the name, or how many
            var ghost = document.createElement('div');
            ghost.className = 'itflow-drag-ghost';
            ghost.innerHTML = '<i class="fas fa-fw ' + icon + ' me-2"></i><span class="text-truncate"></span>';
            ghost.lastChild.textContent = label;
            document.body.appendChild(ghost);
            try {
                e.dataTransfer.setDragImage(ghost, 16, 16);
            } catch (err) {
                // The browser's own picture of the row then
            }
            setTimeout(function () {
                ghost.remove();
            }, 0);
            dragging.sources.forEach(function (source) {
                source.classList.add('is-dragging');
            });
        });

        mover.addEventListener('dragover', function (e) {
            if (!dragging && !carriesFiles(e)) {
                return;
            }
            clearTimeout(leaveTimer);
            var target = e.target.closest('[data-folder-drop]');
            var ok = target && accepts(target, e);
            light(ok ? target : null);
            spring(e.target.closest('.itflow-folder-tree .itflow-folder-row'));
            if (ok) {
                e.preventDefault();
                e.dataTransfer.dropEffect = dragging ? 'move' : 'copy';
            }
        });

        // Left the page's folders and list - unless the next dragover says otherwise, as it does when
        // the pointer only crossed into an icon (Chrome often gives no relatedTarget to go by)
        mover.addEventListener('dragleave', function (e) {
            if (e.relatedTarget && mover.contains(e.relatedTarget)) {
                return;
            }
            clearTimeout(leaveTimer);
            leaveTimer = setTimeout(function () {
                light(null);
                spring(null);
            }, 100);
        });

        mover.addEventListener('drop', function (e) {
            var target = e.target.closest('[data-folder-drop]');
            if (!target || !accepts(target, e)) {
                return;
            }
            e.preventDefault();
            var to = parseInt(target.dataset.folderDrop, 10) || 0;
            if (!dragging) {
                light(null);
                spring(null);
                var folders = droppedFolders(e);
                if (folders || e.dataTransfer.files.length) {
                    uploadInto(mover.dataset.filesUpload + to, e.dataTransfer.files, folders);
                }
                return;
            }
            var moving = dragging;
            finish();
            if (moving.type === 'items') {
                var fields = [['bulk_move_files', '1'], ['bulk_folder_id', to]];
                moving.items.forEach(function (item) {
                    if (item.folder !== to) {
                        fields.push([item.kind + '_ids[]', item.id]);
                    }
                });
                moving.sources.forEach(function (source) {
                    source.classList.add('is-moved');
                });
                post(fields);
            } else {
                moving.li.classList.add('is-moved');
                post([['move_folder', '1'], ['folder_id', moving.id], ['parent_folder', to]]);
            }
        });

        document.addEventListener('dragend', finish);
    }

    // ---- Drop files on the list to upload them into the folder on show ----

    var main = document.querySelector('[data-files-drop]');
    var overlay = document.querySelector('.itflow-files-dropzone');
    if (!main || !overlay) {
        return;
    }
    var depth = 0;

    main.addEventListener('dragenter', function (e) {
        if (!carriesFiles(e)) {
            return;
        }
        depth++;
        overlay.hidden = false;
        var box = main.getBoundingClientRect(), card = main.closest('.card').getBoundingClientRect();
        overlay.style.cssText = 'top:' + (box.top - card.top) + 'px;left:' + (box.left - card.left) + 'px;width:' + box.width + 'px;height:' + box.height + 'px';
    });
    main.addEventListener('dragover', function (e) {
        if (carriesFiles(e)) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        }
    });
    main.addEventListener('dragleave', function () {
        depth = Math.max(0, depth - 1);
        if (!depth) {
            overlay.hidden = true;
        }
    });
    main.addEventListener('drop', function (e) {
        if (!carriesFiles(e)) {
            return;
        }
        e.preventDefault();
        depth = 0;
        overlay.hidden = true;
        var folders = droppedFolders(e);
        if (folders || e.dataTransfer.files.length) {
            uploadInto(main.dataset.filesDrop, e.dataTransfer.files, folders);
        }
    });
})();

// ---- Rename a file or document from its name on the list (agent/ajax.php files_rename) ----
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-files-rename]');
        if (!button) {
            return;
        }
        e.preventDefault();
        var cell = button.closest('td');
        var link = cell && cell.querySelector('[data-files-name]');
        if (!link || cell.querySelector('.itflow-files-rename-box')) {
            return;
        }
        var row = cell.closest('tr');
        var wasDraggable = row && row.draggable;
        var box = document.createElement('input');
        box.type = 'text';
        box.maxLength = 200;
        box.className = 'form-control form-control-sm itflow-above-stretch itflow-files-rename-box';
        box.value = link.textContent;
        box.setAttribute('aria-label', 'New name');
        link.hidden = true;
        button.hidden = true;
        if (row) {
            row.draggable = false; // so dragging in the box selects text
        }
        link.after(box);
        box.focus();
        // A file's name without its extension, ready to type over
        var dot = button.dataset.filesRename === 'file' ? box.value.lastIndexOf('.') : -1;
        box.setSelectionRange(0, dot > 0 ? dot : box.value.length);

        var finished = false;
        function put(back) {
            box.remove();
            link.hidden = false;
            button.hidden = false;
            if (row) {
                row.draggable = wasDraggable;
            }
            if (back) {
                button.focus();
            }
        }
        function finish(save) {
            if (finished) {
                return;
            }
            finished = true;
            var name = box.value.trim();
            if (!save || name === '' || name === link.textContent) {
                put(!save);
                return;
            }
            box.disabled = true;
            var body = new FormData();
            body.append('files_rename', '1');
            body.append('kind', button.dataset.filesRename);
            body.append('id', button.dataset.id);
            body.append('name', name);
            body.append('csrf_token', button.dataset.csrf);
            fetch('/agent/ajax.php', { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (answer) {
                    if (answer.ok) {
                        link.textContent = answer.name;
                        if (row && row.dataset.name !== undefined) {
                            row.dataset.name = answer.name;
                        }
                    } else if (typeof itflowToast === 'function') {
                        itflowToast(answer.error || 'Couldn’t rename it', 'danger');
                    }
                })
                .catch(function () {
                    if (typeof itflowToast === 'function') {
                        itflowToast('Couldn’t rename it', 'danger');
                    }
                })
                .finally(function () {
                    put(false);
                });
        }
        box.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                finish(true);
            } else if (ev.key === 'Escape') {
                ev.preventDefault();
                ev.stopPropagation();
                finish(false);
            }
        });
        box.addEventListener('blur', function () {
            finish(true);
        });
    });
})();
