/**
 * Maintenance > Backup - uploading a backup to restore, and opening its Restore window.
 *
 * The file goes in pieces (backup_upload_start, backup_upload_chunk and backup_upload_finish in
 * admin/post/backup.php), so a backup bigger than PHP's or a proxy's request limit still gets
 * there. A piece refused before it reached ITFlow - a proxy's limit - is sent again smaller,
 * down to 1 MB. Without this script the form posts the file in one go (backup_upload).
 *
 * After an upload the page opens with ?restore=<id>, and the hidden button for its Restore
 * window is clicked.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        const autoOpen = document.querySelector('[data-backup-restore-autoopen]');
        if (autoOpen) {
            autoOpen.click();
        }

        const form = document.querySelector('[data-backup-upload]');
        if (!form || !window.fetch || !window.FormData || !window.Blob) {
            return;
        }

        const box = form.querySelector('[data-backup-upload-progress]');
        const bar = box.querySelector('.progress-bar');
        const text = box.querySelector('[data-backup-upload-text]');
        const button = form.querySelector('[data-backup-upload-button]');
        const megabytes = function (n) {
            return (n / 1048576).toFixed(n < 104857600 ? 1 : 0) + ' MB';
        };

        // What answered when it wasn't ITFlow's JSON - a proxy, the web server, or a sign-in that ran out
        const refused = function (status, body) {
            const doc = new DOMParser().parseFromString(body || '', 'text/html');
            const said = (doc.body ? doc.body.textContent : '').replace(/\s+/g, ' ').trim().slice(0, 200);
            let why = 'The server answered ' + status + (said ? ': "' + said + '"' : '') + '.';
            if (status === 413) {
                why += ' A piece is over the web server\'s request limit.';
            } else if (/csrf|sign in|log in/i.test(body || '')) {
                why += ' Your sign-in no longer matches this page - reload it and upload again.';
            }
            return why;
        };

        const post = function (fields, piece) {
            const data = new FormData();
            data.append('csrf_token', form.querySelector('[name=csrf_token]').value);
            Object.keys(fields).forEach(function (name) {
                data.append(name, fields[name]);
            });
            if (piece) {
                data.append('chunk', piece, 'chunk');
            }
            return fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin' }).then(function (response) {
                return response.text().then(function (body) {
                    let json = null;
                    try {
                        json = JSON.parse(body);
                    } catch (e) {
                        json = null;
                    }
                    if (json && response.ok && !json.error) {
                        return json;
                    }
                    const error = new Error(json && json.error ? json.error : refused(response.status, body));
                    error.status = response.status;
                    error.ours = json !== null;
                    throw error;
                });
            });
        };

        form.addEventListener('submit', function (event) {
            const file = form.querySelector('[name=backup_file]').files[0];
            if (!file) {
                return;
            }
            event.preventDefault();

            bar.classList.remove('bg-danger');
            bar.style.width = '0%';
            box.hidden = false;
            if (file.size > Number(form.dataset.max)) {
                text.textContent = 'A backup up to ' + megabytes(Number(form.dataset.max)) + ' can be uploaded here - restore a bigger one from the command line.';
                bar.classList.add('bg-danger');
                return;
            }
            button.disabled = true;

            // js/auto_reload.js waits while one of these is on the page, so the upload isn't cut off by a reload
            const busy = document.createElement('div');
            busy.className = 'itflow-busy';
            busy.hidden = true;
            busy.style.setProperty('display', 'none', 'important');
            document.body.appendChild(busy);

            const key = form.querySelector('[name=restore_key]').value;
            let id = '';
            let size = 0;

            const send = function (offset) {
                if (offset >= file.size) {
                    text.textContent = 'Checking the backup...';
                    return post({ backup_upload_finish: 1, id: id, restore_key: key });
                }
                return post({ backup_upload_chunk: 1, id: id, offset: offset }, file.slice(offset, offset + size)).catch(function (error) {
                    // Refused before it reached ITFlow: smaller pieces, down to 1 MB
                    if (!error.ours && (error.status === 403 || error.status === 413) && size > 1048576) {
                        size = Math.max(1048576, Math.floor(size / 4));
                        text.textContent = 'The server refused pieces that size - sending ' + megabytes(size) + ' pieces instead';
                        return { received: offset };
                    }
                    throw new Error('At ' + megabytes(offset) + ' of ' + megabytes(file.size) + ': ' + error.message);
                }).then(function (answer) {
                    bar.style.width = Math.floor(100 * answer.received / file.size) + '%';
                    text.textContent = megabytes(answer.received) + ' of ' + megabytes(file.size) + ' sent - keep this page open';
                    return send(answer.received);
                });
            };

            post({ backup_upload_start: 1, name: file.name, size: file.size }).then(function (start) {
                id = start.id;
                size = start.chunk;
                return send(0);
            }).then(function (done) {
                window.location.href = done.redirect;
            }).catch(function (error) {
                busy.remove();
                button.disabled = false;
                bar.classList.add('bg-danger');
                text.textContent = 'The upload stopped: ' + error.message;
            });
        });
    });
})();
