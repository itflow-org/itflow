/*
 * agent/files.php - the folder tree, the grid's previewer, and dropping files
 * onto the list to upload them.
 */
(function () {
    'use strict';

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

    // ---- Drop files on the list to upload them into the folder on show ----

    var main = document.querySelector('[data-files-drop]');
    var overlay = document.querySelector('.itflow-files-dropzone');
    if (!main || !overlay) {
        return;
    }
    var depth = 0;

    function carriesFiles(e) {
        return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1;
    }

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
        var files = e.dataTransfer.files;
        if (!files.length) {
            return;
        }
        // Open the upload modal, then hand it the dropped files once it is on screen
        var trigger = document.createElement('a');
        trigger.className = 'ajax-modal d-none';
        trigger.href = '#';
        trigger.dataset.modalUrl = main.dataset.filesDrop;
        document.body.appendChild(trigger);
        trigger.click();
        trigger.remove();
        var tries = 0;
        (function handOver() {
            var input = document.querySelector('.modal.show [data-upload-input]');
            if (input) {
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
    });
})();
