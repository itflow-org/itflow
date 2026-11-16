/*
 * Picture fields (pictureField(), functions/picture.php) - a person's picture,
 * or a photo of an asset, a location or a rack, shown as the form shows it, on
 * a button with a camera badge. Clicking it opens the Picture window, over the
 * form's own window when it is in one:
 *
 *   - drop a picture on it, paste one, or choose one
 *   - crop it to fit the shape it's shown in - drag it, zoom it (the wheel, a
 *     pinch, the slider, or + and -), turn it a quarter at a time, or pick
 *     another shape where the field has more than one
 *   - Use this picture, or Remove the one there is
 *
 * The crop goes in the field's hidden file input (through a DataTransfer),
 * as a JPG, or a WebP for a picture that may be see-through (PNG where the
 * browser can't write WebP), no bigger than data-crop-max and without the
 * photo's own data - where it was taken, the camera. Remove sets the field's
 * remove input to 1. The form posts them as it always did. Opened again, the
 * window has the picture picked as it was left, to change.
 *
 * A person's initials, where they show, follow the form field named in
 * data-picture-name as it is typed.
 *
 * A browser that can't put a file in a field opens the file picker instead,
 * and the picture goes as picked.
 */
(function () {
    'use strict';

    if (window.itflowPictureField) {
        return;
    }
    window.itflowPictureField = true;

    var SHAPES = {
        'square': { w: 1, h: 1, label: 'Square' },
        '4:3': { w: 4, h: 3, label: '4:3' },
        '3:4': { w: 3, h: 4, label: '3:4' },
        '16:9': { w: 16, h: 9, label: '16:9' },
        'whole': { w: 0, h: 0, label: 'Whole' }
    };
    var TYPES = /^image\/(jpeg|png|gif|webp)$/;
    var MARGIN = 24;          // pixels of the picture shown, dimmed, round the frame
    var FRAME_MAX_HEIGHT = 340;
    var SOURCE_MAX = 4096;    // a bigger picture is scaled down to this first
    var ZOOM_MAX = 8;         // times the smallest zoom that fills the frame

    var canReplace = (function () {
        try {
            return typeof DataTransfer === 'function' && !!new DataTransfer().items && typeof File === 'function';
        } catch (e) {
            return false;
        }
    })();

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    // The picture, upright as the camera meant it (an <img> follows its orientation), no bigger than SOURCE_MAX
    function readPicture(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                var k = Math.min(1, SOURCE_MAX / Math.max(img.naturalWidth, img.naturalHeight));
                var canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(img.naturalWidth * k));
                canvas.height = Math.max(1, Math.round(img.naturalHeight * k));
                var context = canvas.getContext('2d');
                context.imageSmoothingQuality = 'high';
                context.drawImage(img, 0, 0, canvas.width, canvas.height);
                resolve(canvas);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('unreadable'));
            };
            img.src = url;
        });
    }

    function toBlob(canvas, type) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('not encoded'));
                }
            }, type, 0.9);
        });
    }

    /*
     * The crop - a picture in a frame on the stage, the rest of it dimmed.
     * Its state is the frame's centre on the picture (cx, cy) and how far it
     * is zoomed past filling the frame (zoom), so a stage that changes size,
     * or another shape, keeps what is in the frame.
     */
    function Cropper(stage, frame, slider) {
        var self = this;
        this.stage = stage;
        this.frame = frame;
        this.slider = slider;
        this.pointers = {};
        this.round = false;
        this.shape = 'square';
        this.onchange = function () {};

        stage.addEventListener('pointerdown', function (e) {
            if (!self.source || (e.pointerType === 'mouse' && e.button !== 0)) {
                return;
            }
            stage.setPointerCapture(e.pointerId);
            self.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            stage.classList.add('is-moving');
            e.preventDefault();
        });
        stage.addEventListener('pointermove', function (e) {
            var point = self.pointers[e.pointerId];
            if (!point) {
                return;
            }
            var ids = Object.keys(self.pointers);
            if (ids.length === 1) {
                self.move(e.clientX - point.x, e.clientY - point.y);
            } else if (ids.length === 2) {
                // A pinch zooms about the middle of the two fingers
                var other = self.pointers[ids[0] === String(e.pointerId) ? ids[1] : ids[0]];
                var before = Math.hypot(point.x - other.x, point.y - other.y);
                var after = Math.hypot(e.clientX - other.x, e.clientY - other.y);
                var rect = stage.getBoundingClientRect();
                if (before > 0) {
                    self.zoomAt(self.scale * after / before, (e.clientX + other.x) / 2 - rect.left, (e.clientY + other.y) / 2 - rect.top);
                }
            }
            point.x = e.clientX;
            point.y = e.clientY;
        });
        var end = function (e) {
            delete self.pointers[e.pointerId];
            if (!Object.keys(self.pointers).length) {
                stage.classList.remove('is-moving');
            }
        };
        stage.addEventListener('pointerup', end);
        stage.addEventListener('pointercancel', end);

        stage.addEventListener('wheel', function (e) {
            if (!self.source) {
                return;
            }
            e.preventDefault();
            var rect = stage.getBoundingClientRect();
            var delta = e.deltaMode === 1 ? e.deltaY * 16 : e.deltaY;
            self.zoomAt(self.scale * Math.exp(-delta * 0.0015), e.clientX - rect.left, e.clientY - rect.top);
        }, { passive: false });

        stage.addEventListener('keydown', function (e) {
            if (!self.source) {
                return;
            }
            var step = e.shiftKey ? 40 : 10;
            var moves = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
            if (moves[e.key]) {
                self.move(moves[e.key][0], moves[e.key][1]);
            } else if (e.key === '+' || e.key === '=') {
                self.zoomAt(self.scale * 1.15);
            } else if (e.key === '-' || e.key === '_') {
                self.zoomAt(self.scale / 1.15);
            } else if (e.key === '0') {
                self.fit();
            } else {
                return;
            }
            e.preventDefault();
        });

        slider.addEventListener('input', function () {
            self.zoomAt(self.minScale * Math.pow(ZOOM_MAX, slider.value / 1000));
        });

        if (window.ResizeObserver) {
            new ResizeObserver(function () {
                self.layout();
            }).observe(stage);
        }
    }

    Cropper.prototype.setSource = function (source, state) {
        if (this.source && this.source.parentNode) {
            this.source.remove();
        }
        this.source = source;
        if (!source) {
            return;
        }
        source.className = 'itflow-crop-image';
        source.setAttribute('aria-hidden', 'true');
        source.style.width = source.width + 'px';
        source.style.height = source.height + 'px';
        this.stage.insertBefore(source, this.frame);
        state = state || {};
        this.zoom = state.zoom || 1;
        this.cx = state.cx !== undefined ? state.cx : source.width / 2;
        this.cy = state.cy !== undefined ? state.cy : source.height / 2;
        this.layout();
    };

    Cropper.prototype.state = function () {
        return { zoom: this.zoom, cx: this.cx, cy: this.cy };
    };

    // The frame for the stage's width and the shape, and the picture placed in it
    Cropper.prototype.layout = function () {
        var source = this.source;
        var width = this.stage.clientWidth;
        if (!source || !width) {
            return;
        }
        var shape = SHAPES[this.shape] || SHAPES.square;
        var ratio = shape.w ? shape.w / shape.h : source.width / source.height;
        var fw = Math.min(width - MARGIN * 2, this.round ? 300 : Infinity);
        var fh = fw / ratio;
        if (fh > FRAME_MAX_HEIGHT) {
            fh = FRAME_MAX_HEIGHT;
            fw = fh * ratio;
        }
        this.fw = fw;
        this.fh = fh;
        this.fx = (width - fw) / 2;
        this.fy = MARGIN;
        this.stage.style.height = Math.round(fh + MARGIN * 2) + 'px';
        this.frame.style.left = this.fx + 'px';
        this.frame.style.top = this.fy + 'px';
        this.frame.style.width = fw + 'px';
        this.frame.style.height = fh + 'px';
        this.frame.classList.toggle('is-round', this.round);

        this.minScale = Math.max(fw / source.width, fh / source.height);
        this.zoom = Math.min(ZOOM_MAX, Math.max(1, this.zoom || 1));
        this.scale = this.minScale * this.zoom;
        var halfW = fw / 2 / this.scale;
        var halfH = fh / 2 / this.scale;
        this.cx = Math.min(source.width - halfW, Math.max(halfW, this.cx));
        this.cy = Math.min(source.height - halfH, Math.max(halfH, this.cy));
        var x = this.fx + fw / 2 - this.cx * this.scale;
        var y = this.fy + fh / 2 - this.cy * this.scale;
        source.style.transform = 'translate(' + x + 'px, ' + y + 'px) scale(' + this.scale + ')';
        this.slider.value = String(Math.round(Math.log(this.zoom) / Math.log(ZOOM_MAX) * 1000));
        this.onchange();
    };

    Cropper.prototype.fit = function () {
        if (!this.source) {
            return;
        }
        this.zoom = 1;
        this.cx = this.source.width / 2;
        this.cy = this.source.height / 2;
        this.layout();
    };

    Cropper.prototype.setShape = function (shape) {
        this.shape = shape;
        this.zoom = 1;
        this.layout();
    };

    Cropper.prototype.move = function (dx, dy) {
        this.cx -= dx / this.scale;
        this.cy -= dy / this.scale;
        this.layout();
    };

    // Zoom keeping the point under (x, y) on the stage still - the frame's centre when not given
    Cropper.prototype.zoomAt = function (scale, x, y) {
        if (!this.source || !this.fw) {
            return;
        }
        scale = Math.min(this.minScale * ZOOM_MAX, Math.max(this.minScale, scale));
        if (x === undefined) {
            x = this.fx + this.fw / 2;
            y = this.fy + this.fh / 2;
        }
        var left = this.fx + this.fw / 2 - this.cx * this.scale;
        var top = this.fy + this.fh / 2 - this.cy * this.scale;
        var px = (x - left) / this.scale;
        var py = (y - top) / this.scale;
        this.cx = (this.fx + this.fw / 2 - (x - px * scale)) / scale;
        this.cy = (this.fy + this.fh / 2 - (y - py * scale)) / scale;
        this.zoom = scale / this.minScale;
        this.layout();
    };

    Cropper.prototype.rotate = function () {
        var source = this.source;
        if (!source) {
            return;
        }
        var turned = document.createElement('canvas');
        turned.width = source.height;
        turned.height = source.width;
        var context = turned.getContext('2d');
        context.translate(turned.width, 0);
        context.rotate(Math.PI / 2);
        context.drawImage(source, 0, 0);
        this.setSource(turned);
    };

    // What is in the frame, the longest side no more than $max - its size, before encoding
    Cropper.prototype.outputSize = function (max) {
        var sw = this.fw / this.scale;
        var sh = this.fh / this.scale;
        var k = Math.min(1, max / Math.max(sw, sh));
        return [Math.max(1, Math.round(sw * k)), Math.max(1, Math.round(sh * k))];
    };

    // ... as a file: a JPG for a JPG, else WebP (PNG where WebP can't be written) - { blob, width, height }
    Cropper.prototype.encode = function (type, max) {
        var size = this.outputSize(max);
        var sw = this.fw / this.scale;
        var sh = this.fh / this.scale;
        var out = document.createElement('canvas');
        out.width = size[0];
        out.height = size[1];
        var context = out.getContext('2d');
        context.imageSmoothingQuality = 'high';
        if (type === 'image/jpeg') {
            context.fillStyle = '#fff';
            context.fillRect(0, 0, out.width, out.height);
        }
        context.drawImage(this.source, this.cx - sw / 2, this.cy - sh / 2, sw, sh, 0, 0, out.width, out.height);
        return toBlob(out, type).then(function (blob) {
            return blob.type === type || type === 'image/jpeg' ? blob : toBlob(out, 'image/png');
        }).then(function (blob) {
            return { blob: blob, width: out.width, height: out.height };
        });
    };

    /*
     * The Picture window - one, set up for the field that opens it.
     */
    var win = null;

    function buildWindow() {
        var modal = el('div', 'modal fade');
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('aria-labelledby', 'itflowPictureTitle');
        modal.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
                '<div class="modal-content border-dark">' +
                    '<div class="modal-header bg-dark">' +
                        '<h5 class="modal-title" id="itflowPictureTitle"><i class="fas fa-fw fa-camera me-2"></i><span data-title></span></h5>' +
                        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<div class="itflow-picture-pick" data-pick tabindex="0" role="button" aria-label="Choose a picture">' +
                            '<div class="itflow-picture-pick-now" data-now></div>' +
                            '<div class="fw-semibold mt-3">Drop a picture here</div>' +
                            '<div class="small text-body-secondary mb-3">or paste one, or</div>' +
                            '<span class="btn btn-primary"><i class="fas fa-upload me-2"></i>Choose a picture</span>' +
                            '<div class="form-text mt-2">JPG, PNG, GIF or WebP</div>' +
                        '</div>' +
                        '<div data-crop-area hidden>' +
                            '<div class="itflow-crop-stage" tabindex="0" aria-label="The picture - drag or use the arrow keys to move it, scroll, pinch or use + and - to zoom">' +
                                '<div class="itflow-crop-frame"></div>' +
                            '</div>' +
                            '<div class="itflow-crop-bar">' +
                                '<label class="itflow-crop-zoom"><i class="fas fa-image small"></i>' +
                                    '<input type="range" class="form-range" min="0" max="1000" value="0" aria-label="Zoom">' +
                                    '<i class="fas fa-image"></i></label>' +
                                '<div class="btn-group btn-group-sm" role="group" aria-label="Shape" data-shapes></div>' +
                                '<div class="btn-group btn-group-sm">' +
                                    '<button type="button" class="btn btn-outline-secondary" data-rotate title="Turn it a quarter" aria-label="Turn it a quarter"><i class="fas fa-fw fa-redo"></i></button>' +
                                    '<button type="button" class="btn btn-outline-secondary" data-fit title="Fit it again" aria-label="Fit it again"><i class="fas fa-fw fa-compress"></i></button>' +
                                '</div>' +
                            '</div>' +
                            '<div class="d-flex align-items-baseline gap-2 small mt-2">' +
                                '<span class="text-body-secondary">Drag it into place, and scroll or pinch to zoom.</span>' +
                                '<span class="ms-auto text-body-secondary text-nowrap" data-size></span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="alert alert-danger small mt-3 mb-0" data-error hidden></div>' +
                        '<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden data-file>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-outline-danger me-auto" data-remove><i class="fas fa-trash-alt me-2"></i>Remove</button>' +
                        '<button type="button" class="btn btn-outline-secondary" data-another hidden><i class="fas fa-images me-2"></i>Choose another</button>' +
                        '<button type="button" class="btn btn-primary text-bold" data-use disabled><i class="fas fa-check me-2"></i>Use this picture</button>' +
                        '<button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);

        var w = {
            modal: modal,
            title: modal.querySelector('[data-title]'),
            pick: modal.querySelector('[data-pick]'),
            now: modal.querySelector('[data-now]'),
            area: modal.querySelector('[data-crop-area]'),
            shapes: modal.querySelector('[data-shapes]'),
            size: modal.querySelector('[data-size]'),
            error: modal.querySelector('[data-error]'),
            file: modal.querySelector('[data-file]'),
            remove: modal.querySelector('[data-remove]'),
            another: modal.querySelector('[data-another]'),
            use: modal.querySelector('[data-use]'),
            field: null,
            picked: null
        };
        w.crop = new Cropper(modal.querySelector('.itflow-crop-stage'), modal.querySelector('.itflow-crop-frame'), modal.querySelector('input[type=range]'));
        w.crop.onchange = function () {
            if (w.field && w.crop.source) {
                var size = w.crop.outputSize(w.field.max);
                w.size.textContent = size[0] + ' × ' + size[1];
            }
        };

        var choose = function () {
            w.file.value = '';
            w.file.click();
        };
        w.pick.addEventListener('click', choose);
        w.pick.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                choose();
            }
        });
        w.another.addEventListener('click', choose);
        w.file.addEventListener('change', function () {
            if (w.file.files && w.file.files[0]) {
                load(w, w.file.files[0]);
            }
        });

        // Dropped on the window, or pasted while it's open
        ['dragenter', 'dragover'].forEach(function (type) {
            modal.addEventListener(type, function (e) {
                e.preventDefault();
                w.pick.classList.add('is-over');
            });
        });
        modal.addEventListener('dragleave', function (e) {
            if (!modal.contains(e.relatedTarget)) {
                w.pick.classList.remove('is-over');
            }
        });
        modal.addEventListener('drop', function (e) {
            e.preventDefault();
            w.pick.classList.remove('is-over');
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) {
                load(w, file);
            }
        });
        document.addEventListener('paste', function (e) {
            if (!modal.classList.contains('show') || !e.clipboardData) {
                return;
            }
            var items = Array.prototype.slice.call(e.clipboardData.items || []);
            var item = items.filter(function (i) {
                return i.kind === 'file' && TYPES.test(i.type);
            })[0];
            if (item) {
                e.preventDefault();
                load(w, item.getAsFile());
            }
        });

        modal.querySelector('[data-rotate]').addEventListener('click', function () {
            w.crop.rotate();
        });
        modal.querySelector('[data-fit]').addEventListener('click', function () {
            w.crop.fit();
        });
        w.use.addEventListener('click', function () {
            use(w);
        });
        w.remove.addEventListener('click', function () {
            remove(w);
        });
        modal.addEventListener('shown.bs.modal', function () {
            w.crop.layout();
            (w.crop.source ? w.crop.stage : w.pick).focus();
        });
        return w;
    }

    function showError(w, text) {
        w.error.textContent = text || '';
        w.error.hidden = !text;
    }

    function showCrop(w, on) {
        w.pick.hidden = on;
        w.area.hidden = !on;
        w.another.hidden = !on;
        w.use.disabled = !on;
    }

    // A picture picked - into the crop
    function load(w, file) {
        if (!TYPES.test(file.type)) {
            showError(w, 'That isn\'t a JPG, PNG, GIF or WebP picture.');
            return;
        }
        showError(w, '');
        var field = w.field;
        readPicture(file).then(function (source) {
            if (w.field !== field) {
                return;
            }
            w.picked = { name: file.name, type: file.type === 'image/jpeg' ? 'image/jpeg' : 'image/webp' };
            showCrop(w, true);
            w.crop.setSource(source);
            w.crop.stage.focus();
        }).catch(function () {
            showError(w, 'That picture couldn\'t be read - try saving it as a JPG or PNG.');
        });
    }

    function shapeButtons(w, field) {
        w.shapes.textContent = '';
        w.shapes.hidden = field.shapes.length < 2;
        field.shapes.forEach(function (shape) {
            var button = el('button', 'btn btn-outline-secondary', SHAPES[shape].label);
            button.type = 'button';
            button.setAttribute('aria-pressed', shape === w.crop.shape ? 'true' : 'false');
            button.classList.toggle('active', shape === w.crop.shape);
            button.addEventListener('click', function () {
                w.crop.setShape(shape);
                Array.prototype.forEach.call(w.shapes.children, function (other) {
                    other.classList.toggle('active', other === button);
                    other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                });
            });
            w.shapes.appendChild(button);
        });
    }

    // The field's settings, read off it
    function fieldOf(node) {
        if (!node.itflowPicture) {
            var shapes = String(node.getAttribute('data-crop') || 'square').split(',').map(function (shape) {
                return shape.trim();
            }).filter(function (shape) {
                return SHAPES[shape];
            });
            node.itflowPicture = {
                node: node,
                input: node.querySelector('[data-picture-input]'),
                removeInput: node.querySelector('[data-picture-remove]'),
                preview: node.querySelector('[data-picture-preview]'),
                empty: node.querySelector('template[data-picture-empty]'),
                title: node.getAttribute('data-picture-title') || 'Picture',
                imgClass: node.getAttribute('data-picture-class') || 'itflow-picture-photo',
                has: node.getAttribute('data-picture-has') === '1',
                round: node.getAttribute('data-crop-shape') === 'circle',
                shapes: shapes.length ? shapes : ['square'],
                max: parseInt(node.getAttribute('data-crop-max'), 10) || 2048,
                pending: null
            };
        }
        return node.itflowPicture;
    }

    function open(node) {
        var field = fieldOf(node);
        win = win || buildWindow();
        var w = win;
        w.field = field;
        w.title.textContent = field.title;
        w.modal.querySelector('.modal-dialog').classList.toggle('modal-lg', !field.round && field.shapes.indexOf('whole') !== -1);
        w.now.innerHTML = field.preview.innerHTML;
        w.now.classList.toggle('is-round', field.round);
        w.crop.round = field.round;
        showError(w, '');

        // Opened again with a picture picked - as it was left
        var pending = field.pending;
        w.crop.shape = pending ? pending.shape : field.shapes[0];
        shapeButtons(w, field);
        if (pending) {
            w.picked = { name: pending.name, type: pending.type };
            showCrop(w, true);
            w.crop.setSource(pending.source, pending.state);
        } else {
            w.picked = null;
            w.crop.setSource(null);
            showCrop(w, false);
        }
        w.remove.hidden = !(field.has || field.pending);
        bootstrap.Modal.getOrCreateInstance(w.modal).show();
    }

    // Use this picture - its crop in the field's file input, and on the button
    function use(w) {
        var field = w.field;
        if (!field || !w.crop.source || !w.picked) {
            return;
        }
        w.use.disabled = true;
        var picked = w.picked;
        w.crop.encode(picked.type, field.max).then(function (made) {
            var extension = { 'image/jpeg': '.jpg', 'image/webp': '.webp', 'image/png': '.png' }[made.blob.type] || '.png';
            var name = (picked.name.replace(/\.[^.]*$/, '') || 'picture') + extension;
            var transfer = new DataTransfer();
            transfer.items.add(new File([made.blob], name, { type: made.blob.type, lastModified: Date.now() }));
            field.input.files = transfer.files;
            if (field.removeInput) {
                field.removeInput.value = '';
            }
            field.pending = { source: w.crop.source, state: w.crop.state(), shape: w.crop.shape, name: picked.name, type: picked.type };
            if (field.url) {
                URL.revokeObjectURL(field.url);
            }
            field.url = URL.createObjectURL(made.blob);
            var img = el('img', field.imgClass);
            img.alt = '';
            img.src = field.url;
            field.preview.replaceChildren(img);
            field.node.classList.add('is-changed');
            field.input.dispatchEvent(new Event('change', { bubbles: true }));
            bootstrap.Modal.getOrCreateInstance(w.modal).hide();
        }).catch(function () {
            showError(w, 'That crop couldn\'t be saved - try another picture.');
        }).finally(function () {
            w.use.disabled = !w.crop.source;
        });
    }

    // Remove - no new picture, and the one there was taken off when the form is saved
    function remove(w) {
        var field = w.field;
        if (!field) {
            return;
        }
        field.input.value = '';
        field.pending = null;
        if (field.removeInput && field.has) {
            field.removeInput.value = '1';
        }
        field.has = false;
        field.preview.replaceChildren(field.empty.content.cloneNode(true));
        field.node.classList.add('is-changed');
        field.input.dispatchEvent(new Event('change', { bubbles: true }));
        bootstrap.Modal.getOrCreateInstance(w.modal).hide();
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest ? e.target.closest('[data-picture-open]') : null;
        if (!button) {
            return;
        }
        var node = button.closest('[data-picture-field]');
        if (!node) {
            return;
        }
        e.preventDefault();
        if (!canReplace || !window.bootstrap) {
            // Without the window - the file as picked
            fieldOf(node).input.click();
            return;
        }
        open(node);
    });

    // A person's initials follow their name as it is typed - on the button, and for when the picture is removed
    document.addEventListener('input', function (e) {
        var input = e.target;
        if (!input || !input.form || !input.name) {
            return;
        }
        input.form.querySelectorAll('[data-picture-field][data-picture-name]').forEach(function (node) {
            if (node.getAttribute('data-picture-name') !== input.name) {
                return;
            }
            // The first letters of the first two words, as personInitials() makes them (functions/format.php)
            var text = input.value.trim().split(/\s+/).filter(Boolean).slice(0, 2).map(function (word) {
                return word.charAt(0).toUpperCase();
            }).join('') || '?';
            [node.querySelector('[data-picture-preview]'), node.querySelector('template[data-picture-empty]').content].forEach(function (holder) {
                var initials = holder && holder.querySelector('span.itflow-contact-avatar');
                if (initials) {
                    initials.textContent = text;
                }
            });
        });
    });

    // Without the window, a picked file still shows on the button
    document.addEventListener('change', function (e) {
        if (canReplace && window.bootstrap) {
            return;
        }
        var input = e.target;
        var node = input && input.matches && input.matches('[data-picture-input]') ? input.closest('[data-picture-field]') : null;
        if (node && input.files && input.files[0]) {
            var field = fieldOf(node);
            var img = el('img', field.imgClass);
            img.alt = '';
            img.src = URL.createObjectURL(input.files[0]);
            field.preview.replaceChildren(img);
        }
    });
})();
