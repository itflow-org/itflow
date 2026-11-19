/*
 * The ticket page's reply form (agent/ticket.php):
 *
 * - Drafts. What is typed is kept as it is written (agent/ajax.php ticket_reply_draft ->
 *   functions/ticket_reply_draft.php) - a moment after typing stops, and as the page is left - and
 *   the page puts it back in the reply box next time. Discard it drops it. Sending drops it too,
 *   on the server.
 * - Files dropped on the reply box go in its Attach files field.
 * - +15m, +30m and +1h add to Time worked. The timer stops at the new total, as it does when the
 *   time is typed.
 * - A reply with something in it and no time, on a billable ticket, asks first.
 */
(function () {
    'use strict';

    var form = document.getElementById('ticketReplyForm');
    if (!form) {
        return;
    }
    var ticketId = form.querySelector('[name="ticket_id"]').value;
    var csrf = form.querySelector('[name="csrf_token"]').value;
    var composer = document.getElementById('replyComposer');
    var status = form.querySelector('[data-reply-draft-status]');

    function editor() {
        return window.tinymce ? tinymce.get('ticket_reply') : null;
    }

    function content() {
        var ed = editor();
        if (ed) {
            return ed.getContent();
        }
        var textarea = document.getElementById('ticket_reply');
        return textarea ? textarea.value : '';
    }

    function hasWords() {
        var ed = editor();
        var text = ed ? ed.getContent({ format: 'text' }) : content().replace(/<[^>]*>/g, '');
        return text.replace(/ /g, ' ').trim() !== '' || /<img\b/i.test(content());
    }

    function composerOpen() {
        return !composer || composer.classList.contains('show');
    }

    // ---- Drafts

    var drafts = form.dataset.drafts === '1';
    var saveTimer = null;
    var lastSaved = null;
    var sending = false;

    function setStatus(text) {
        if (status) {
            status.textContent = text;
        }
    }

    function saveDraft() {
        saveTimer = null;
        var html = content();
        if (sending || lastSaved === null || html === lastSaved) {
            return;
        }
        itflowPost('ajax.php', { ticket_reply_draft: 1, csrf_token: csrf, ticket_id: ticketId, html: html }, function (answer) {
            lastSaved = html;
            var ed = editor();
            // Kept, so leaving the page loses nothing - no need to warn about it
            if (ed && content() === html) {
                ed.setDirty(false);
            }
            var data = {};
            try {
                data = JSON.parse(answer);
            } catch (err) {
                data = {};
            }
            setStatus(data.kept ? 'Draft saved ' + data.time : '');
        }, function () {
            setStatus('Draft not saved - check your connection');
        });
    }

    function queueSave() {
        if (!drafts || sending) {
            return;
        }
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveDraft, 1500);
    }

    function watchEditor() {
        var ed = editor();
        if (ed) {
            lastSaved = ed.getContent();
            // SetContent is a canned response or an AI rewrite going in
            ed.on('input change keyup SetContent Undo Redo', queueSave);
            return true;
        }
        return false;
    }

    if (drafts) {
        var tries = 0;
        (function waitForEditor() {
            if (watchEditor()) {
                return;
            }
            if (!window.tinymce || ++tries > 50) {
                var textarea = document.getElementById('ticket_reply');
                if (textarea) {
                    lastSaved = textarea.value;
                    textarea.addEventListener('input', queueSave);
                }
                return;
            }
            setTimeout(waitForEditor, 200);
        })();

        // Leaving with something not yet kept - it goes now
        window.addEventListener('pagehide', function () {
            if (sending || lastSaved === null || !navigator.sendBeacon) {
                return;
            }
            var html = content();
            if (html === lastSaved) {
                return;
            }
            var data = new FormData();
            data.append('ticket_reply_draft', '1');
            data.append('csrf_token', csrf);
            data.append('ticket_id', ticketId);
            data.append('html', html);
            navigator.sendBeacon('ajax.php', data);
        });

        var discard = form.querySelector('[data-reply-draft-discard]');
        if (discard) {
            discard.addEventListener('click', function (e) {
                e.preventDefault();
                var drop = function () {
                    clearTimeout(saveTimer);
                    itflowPost('ajax.php', { ticket_reply_draft: 1, csrf_token: csrf, ticket_id: ticketId, html: '' }, function () {
                        var ed = editor();
                        if (ed) {
                            ed.setContent('');
                            ed.setDirty(false);
                        } else if (document.getElementById('ticket_reply')) {
                            document.getElementById('ticket_reply').value = '';
                        }
                        lastSaved = content();
                        var note = form.querySelector('[data-reply-draft-note]');
                        if (note) {
                            note.remove();
                        }
                        setStatus('');
                    });
                };
                if (typeof Swal === 'undefined') {
                    drop();
                    return;
                }
                Swal.fire({
                    theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
                    title: 'Discard your unsent reply?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Discard',
                    cancelButtonText: 'Keep it',
                    reverseButtons: true,
                    focusCancel: true,
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-danger mx-1', cancelButton: 'btn btn-secondary mx-1' }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        drop();
                    }
                });
            });
        }
    }

    // ---- Files dropped on the reply box

    var fileInput = document.getElementById('ticket_reply_attachments');
    var dropBox = form.querySelector('.card');
    var filesNote = form.querySelector('[data-reply-files-note]');
    var depth = 0;

    function carriesFiles(e) {
        return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1;
    }

    function showFilesNote() {
        if (!filesNote || !fileInput) {
            return;
        }
        var count = fileInput.files.length;
        filesNote.hidden = !count || composerOpen();
        filesNote.innerHTML = '<i class="fas fa-fw fa-paperclip me-1"></i>';
        filesNote.appendChild(document.createTextNode(count + (count === 1 ? ' file is' : ' files are') + ' ready to attach - pick how to send your reply.'));
    }

    if (fileInput && dropBox) {
        dropBox.addEventListener('dragenter', function (e) {
            if (carriesFiles(e)) {
                depth++;
                dropBox.classList.add('itflow-reply-drop');
            }
        });
        dropBox.addEventListener('dragleave', function (e) {
            if (carriesFiles(e)) {
                depth = Math.max(0, depth - 1);
                if (!depth) {
                    dropBox.classList.remove('itflow-reply-drop');
                }
            }
        });
        dropBox.addEventListener('dragover', function (e) {
            if (carriesFiles(e)) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
            }
        });
        dropBox.addEventListener('drop', function (e) {
            if (!carriesFiles(e)) {
                return;
            }
            e.preventDefault();
            depth = 0;
            dropBox.classList.remove('itflow-reply-drop');
            var transfer = new DataTransfer();
            Array.prototype.forEach.call(fileInput.files, function (file) {
                transfer.items.add(file);
            });
            // A folder comes as an entry with no size and no type - left out
            Array.prototype.forEach.call(e.dataTransfer.files, function (file) {
                if (file.size || file.type) {
                    transfer.items.add(file);
                }
            });
            fileInput.files = transfer.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            showFilesNote();
        });
        if (composer) {
            composer.addEventListener('shown.bs.collapse', showFilesNote);
        }
    }

    // ---- +15m, +30m, +1h

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    form.addEventListener('click', function (e) {
        var button = e.target.closest('[data-time-add-minutes]');
        if (!button) {
            return;
        }
        var h = document.getElementById('hours');
        var m = document.getElementById('minutes');
        var s = document.getElementById('seconds');
        if (!h || !m || !s) {
            return;
        }
        // The timer stops where it is first, as when the time is typed (agent/js/ticket_time_tracking.js)
        m.dispatchEvent(new Event('focus'));
        var total = (parseInt(h.value, 10) || 0) * 3600 + (parseInt(m.value, 10) || 0) * 60 + (parseInt(s.value, 10) || 0)
            + parseInt(button.dataset.timeAddMinutes, 10) * 60;
        total = Math.min(total, 23 * 3600 + 59 * 60 + 59);
        h.value = pad(Math.floor(total / 3600));
        m.value = pad(Math.floor((total % 3600) / 60));
        s.value = pad(total % 60);
        [h, m, s].forEach(function (field) {
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // ---- No time on a billable ticket

    function timeWorked() {
        var total = 0;
        [['hours', 3600], ['minutes', 60], ['seconds', 1]].forEach(function (part) {
            var field = document.getElementById(part[0]);
            total += field ? (parseInt(field.value, 10) || 0) * part[1] : 0;
        });
        return total;
    }

    // After the page's own checks (the composer shut, the To line) - they are added as the page is read
    function onSubmit(e) {
        if (e.defaultPrevented) {
            return;
        }
        if (form.dataset.billable === '1' && form.dataset.noTimeOk !== '1' && composerOpen() && hasWords() && timeWorked() === 0
            && document.getElementById('hours') && typeof Swal !== 'undefined') {
            e.preventDefault();
            var submitter = e.submitter || null;
            Swal.fire({
                theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
                title: 'No time on this reply',
                text: 'This ticket is billable. Send the reply without any time?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Send without time',
                cancelButtonText: 'Add time',
                reverseButtons: true,
                focusCancel: true,
                returnFocus: false,
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-primary mx-1', cancelButton: 'btn btn-secondary mx-1' }
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.dataset.noTimeOk = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitter);
                    } else {
                        form.submit();
                    }
                } else {
                    var minutes = document.getElementById('minutes');
                    if (minutes) {
                        minutes.focus();
                    }
                }
            });
            return;
        }
        // Going, unless something after this holds it back - nothing more to keep as a draft
        setTimeout(function () {
            if (!e.defaultPrevented) {
                sending = true;
                clearTimeout(saveTimer);
            }
        }, 0);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            form.addEventListener('submit', onSubmit);
        });
    } else {
        form.addEventListener('submit', onSubmit);
    }
})();
