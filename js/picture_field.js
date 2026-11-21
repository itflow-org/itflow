/*
 * Picture fields (pictureField(), functions/picture.php) - a person's picture,
 * or a photo of an asset, a location or a rack, shown as the form shows it, on
 * a button with a camera badge. Clicking it opens the Picture window, over the
 * form's own window when it is in one:
 *
 *   - drop a picture on it, paste one, or choose one - or, on a phone or
 *     tablet, Take a photo, which opens the camera straight away
 *   - crop it to fit the shape it's shown in - drag it, zoom it (the wheel, a
 *     pinch, the slider, or + and -), turn it a quarter at a time, or pick
 *     another shape where the field has more than one
 *   - Use this picture, or Remove the one there is
 *
 * A field with data-crop="free" - a receipt, a logo - has the whole picture
 * with a frame over it, its corners and edges dragged in to any shape, and
 * with data-picture-trim a Trim the edges button that pulls the frame in to
 * what's on the picture. A field whose file input takes application/pdf takes
 * a PDF as it is. With data-picture-submit the form is sent once the picture
 * is used - the receipt on an expense's quick look.
 *
 * The crop goes in the field's hidden file input (through a DataTransfer),
 * as a JPG for a JPG, else - so see-through parts stay see-through - a WebP
 * where the field takes WebP (PNG where the browser can't write it) or a PNG,
 * no bigger than data-crop-max and without the
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

    // A touch screen with a camera to hand - a phone or tablet - gets Take a photo
    var CAMERA = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

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
        if (this.source) {
            this.setSource(turnedQuarter(this.source));
        }
    };

    // What is in the frame, the longest side no more than $max - its size, before encoding
    Cropper.prototype.outputSize = function (max) {
        var sw = this.fw / this.scale;
        var sh = this.fh / this.scale;
        var k = Math.min(1, max / Math.max(sw, sh));
        return [Math.max(1, Math.round(sw * k)), Math.max(1, Math.round(sh * k))];
    };

    // ... as a file - { blob, width, height }
    Cropper.prototype.encode = function (type, max) {
        var sw = this.fw / this.scale;
        var sh = this.fh / this.scale;
        return encodeCanvas(this.source, this.cx - sw / 2, this.cy - sh / 2, sw, sh, this.outputSize(max), type);
    };

    /*
     * The free crop - the whole picture on the stage, and a frame over it
     * dragged by its corners and edges to any shape (data-crop="free"), or
     * moved by its middle. Its state is the frame on the picture, in the
     * picture's own pixels.
     */
    var HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];

    function FreeCropper(stage, frame) {
        var self = this;
        this.stage = stage;
        this.frame = frame;
        this.onchange = function () {};
        HANDLES.forEach(function (handle) {
            var dot = el('span', 'itflow-free-handle is-' + handle);
            dot.setAttribute('data-handle', handle);
            frame.appendChild(dot);
        });

        var drag = null;
        stage.addEventListener('pointerdown', function (e) {
            if (!self.source || (e.pointerType === 'mouse' && e.button !== 0)) {
                return;
            }
            var handle = e.target.closest('[data-handle]');
            var onFrame = e.target.closest('.itflow-free-frame');
            if (!handle && !onFrame) {
                return;
            }
            stage.setPointerCapture(e.pointerId);
            drag = { id: e.pointerId, x: e.clientX, y: e.clientY, handle: handle ? handle.getAttribute('data-handle') : 'move', start: Object.assign({}, self.rect) };
            stage.classList.add('is-moving');
            e.preventDefault();
        });
        stage.addEventListener('pointermove', function (e) {
            if (!drag || drag.id !== e.pointerId) {
                return;
            }
            self.dragTo(drag, (e.clientX - drag.x) / self.scale, (e.clientY - drag.y) / self.scale);
        });
        var end = function (e) {
            if (drag && drag.id === e.pointerId) {
                drag = null;
                stage.classList.remove('is-moving');
            }
        };
        stage.addEventListener('pointerup', end);
        stage.addEventListener('pointercancel', end);

        frame.addEventListener('keydown', function (e) {
            if (!self.source) {
                return;
            }
            var step = (e.shiftKey ? 40 : 8) / self.scale;
            var moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (!moves[e.key]) {
                return;
            }
            e.preventDefault();
            self.dragTo({ handle: 'move', start: Object.assign({}, self.rect) }, moves[e.key][0], moves[e.key][1]);
        });

        if (window.ResizeObserver) {
            new ResizeObserver(function () {
                self.layout();
            }).observe(stage);
        }
    }

    FreeCropper.prototype.setSource = function (source, rect) {
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
        this.rect = rect ? Object.assign({}, rect) : { x: 0, y: 0, w: source.width, h: source.height };
        this.layout();
    };

    FreeCropper.prototype.state = function () {
        return Object.assign({}, this.rect);
    };

    // The picture fitted to the stage, and the frame drawn over it
    FreeCropper.prototype.layout = function () {
        var source = this.source;
        var width = this.stage.clientWidth;
        if (!source || !width) {
            return;
        }
        // A receipt is tall - up to about half the screen high, to read it while cropping
        var high = Math.max(FRAME_MAX_HEIGHT, Math.min(560, Math.round(window.innerHeight * 0.55)));
        this.scale = Math.min((width - MARGIN * 2) / source.width, high / source.height);
        this.ox = (width - source.width * this.scale) / 2;
        this.oy = MARGIN;
        this.stage.style.height = Math.round(source.height * this.scale + MARGIN * 2) + 'px';
        source.style.transform = 'translate(' + this.ox + 'px, ' + this.oy + 'px) scale(' + this.scale + ')';
        var r = this.rect;
        this.frame.style.left = (this.ox + r.x * this.scale) + 'px';
        this.frame.style.top = (this.oy + r.y * this.scale) + 'px';
        this.frame.style.width = (r.w * this.scale) + 'px';
        this.frame.style.height = (r.h * this.scale) + 'px';
        this.onchange();
    };

    // A handle (or the middle) moved by (dx, dy) picture pixels from where the drag began - kept on the picture, no smaller than 24 pixels on screen
    FreeCropper.prototype.dragTo = function (drag, dx, dy) {
        var source = this.source;
        var min = Math.min(24 / this.scale, source.width, source.height);
        var s = drag.start;
        var left = s.x, top = s.y, right = s.x + s.w, bottom = s.y + s.h;
        if (drag.handle === 'move') {
            dx = Math.min(source.width - right, Math.max(-left, dx));
            dy = Math.min(source.height - bottom, Math.max(-top, dy));
            left += dx;
            right += dx;
            top += dy;
            bottom += dy;
        } else {
            if (drag.handle.indexOf('w') !== -1) {
                left = Math.min(right - min, Math.max(0, left + dx));
            }
            if (drag.handle.indexOf('e') !== -1) {
                right = Math.max(left + min, Math.min(source.width, right + dx));
            }
            if (drag.handle.indexOf('n') !== -1) {
                top = Math.min(bottom - min, Math.max(0, top + dy));
            }
            if (drag.handle.indexOf('s') !== -1) {
                bottom = Math.max(top + min, Math.min(source.height, bottom + dy));
            }
        }
        this.rect = { x: left, y: top, w: right - left, h: bottom - top };
        this.layout();
    };

    FreeCropper.prototype.fit = function () {
        if (this.source) {
            this.rect = { x: 0, y: 0, w: this.source.width, h: this.source.height };
            this.layout();
        }
    };

    FreeCropper.prototype.rotate = function () {
        if (this.source) {
            this.setSource(turnedQuarter(this.source));
        }
    };

    /*
     * Trim - the frame pulled in to what is on the picture: what isn't see-
     * through, or for a picture with no see-through parts, what isn't the
     * colour of its corners. False when there is nothing to trim to.
     */
    FreeCropper.prototype.trim = function () {
        var source = this.source;
        if (!source) {
            return false;
        }
        var k = Math.min(1, 400 / Math.max(source.width, source.height));
        var w = Math.max(1, Math.round(source.width * k));
        var h = Math.max(1, Math.round(source.height * k));
        var probe = document.createElement('canvas');
        probe.width = w;
        probe.height = h;
        var context = probe.getContext('2d', { willReadFrequently: true });
        context.drawImage(source, 0, 0, w, h);
        var data = context.getImageData(0, 0, w, h).data;
        var clear = false;
        for (var i = 3; i < data.length; i += 4) {
            if (data[i] < 250) {
                clear = true;
                break;
            }
        }
        // The background - the four corners' average
        var bg = [0, 0, 0];
        [0, w - 1, (h - 1) * w, h * w - 1].forEach(function (p) {
            for (var c = 0; c < 3; c++) {
                bg[c] += data[p * 4 + c] / 4;
            }
        });
        var minX = w, minY = h, maxX = -1, maxY = -1;
        for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
                var p = (y * w + x) * 4;
                var content = clear ? data[p + 3] > 16
                    : Math.abs(data[p] - bg[0]) > 28 || Math.abs(data[p + 1] - bg[1]) > 28 || Math.abs(data[p + 2] - bg[2]) > 28;
                if (content) {
                    minX = Math.min(minX, x);
                    maxX = Math.max(maxX, x);
                    minY = Math.min(minY, y);
                    maxY = Math.max(maxY, y);
                }
            }
        }
        if (maxX < 0) {
            return false;
        }
        // A pixel of room round it, back in the picture's own pixels
        var pad = 1;
        var left = Math.max(0, (minX - pad) / k);
        var top = Math.max(0, (minY - pad) / k);
        var right = Math.min(source.width, (maxX + 1 + pad) / k);
        var bottom = Math.min(source.height, (maxY + 1 + pad) / k);
        this.rect = { x: left, y: top, w: right - left, h: bottom - top };
        this.layout();
        return true;
    };

    FreeCropper.prototype.outputSize = function (max) {
        var k = Math.min(1, max / Math.max(this.rect.w, this.rect.h));
        return [Math.max(1, Math.round(this.rect.w * k)), Math.max(1, Math.round(this.rect.h * k))];
    };

    FreeCropper.prototype.encode = function (type, max) {
        var r = this.rect;
        return encodeCanvas(this.source, r.x, r.y, r.w, r.h, this.outputSize(max), type);
    };

    // A picture turned a quarter clockwise
    function turnedQuarter(source) {
        var turned = document.createElement('canvas');
        turned.width = source.height;
        turned.height = source.width;
        var context = turned.getContext('2d');
        context.translate(turned.width, 0);
        context.rotate(Math.PI / 2);
        context.drawImage(source, 0, 0);
        return turned;
    }

    // Part of a picture, drawn at a size, as a file - { blob, width, height }. A JPG gets a white background; WebP falls back to PNG
    function encodeCanvas(source, sx, sy, sw, sh, size, type) {
        var out = document.createElement('canvas');
        out.width = size[0];
        out.height = size[1];
        var context = out.getContext('2d');
        context.imageSmoothingQuality = 'high';
        if (type === 'image/jpeg') {
            context.fillStyle = '#fff';
            context.fillRect(0, 0, out.width, out.height);
        }
        context.drawImage(source, sx, sy, sw, sh, 0, 0, out.width, out.height);
        return toBlob(out, type).then(function (blob) {
            return blob.type === type || type !== 'image/webp' ? blob : toBlob(out, 'image/png');
        }).then(function (blob) {
            return { blob: blob, width: out.width, height: out.height };
        });
    }

    // What a field's files are called, from its accept list - "JPG, PNG, GIF or WebP", "PNG or JPG", "... or PDF"
    function typesText(accept) {
        var names = { 'image/jpeg': 'JPG', 'image/png': 'PNG', 'image/gif': 'GIF', 'image/webp': 'WebP', 'image/*': 'JPG, PNG, GIF, WebP', 'application/pdf': 'PDF' };
        var parts = [];
        accept.split(',').forEach(function (type) {
            (names[type.trim()] || '').split(', ').forEach(function (name) {
                if (name && parts.indexOf(name) === -1) {
                    parts.push(name);
                }
            });
        });
        return parts.length > 1 ? parts.slice(0, -1).join(', ') + ' or ' + parts[parts.length - 1] : parts.join('');
    }

    // A file the field takes as it is - a PDF - on its button and in the window
    function fileTile(name) {
        var tile = el('span', 'itflow-picture-none is-file');
        tile.appendChild(el('i', 'fas fa-file-pdf'));
        tile.appendChild(el('span', 'text-truncate mw-100 px-2', name));
        return tile;
    }

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
                        '<h5 class="modal-title" id="itflowPictureTitle"><i class="fas fa-fw fa-camera me-2" data-icon></i><span data-title></span></h5>' +
                        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<div class="itflow-picture-pick" data-pick tabindex="0" role="button">' +
                            '<div class="itflow-picture-pick-now" data-now></div>' +
                            '<div class="fw-semibold mt-3" data-pick-drop></div>' +
                            '<div class="small text-body-secondary mb-3">or paste one, or</div>' +
                            '<div class="d-flex flex-wrap justify-content-center gap-2">' +
                                '<span class="btn btn-primary"><i class="fas fa-upload me-2"></i><span data-pick-choose></span></span>' +
                                '<button type="button" class="btn btn-outline-primary" data-camera hidden><i class="fas fa-camera me-2"></i>Take a photo</button>' +
                            '</div>' +
                            '<div class="form-text mt-2" data-pick-types></div>' +
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
                        '<div data-free-area hidden>' +
                            '<div class="itflow-crop-stage is-free">' +
                                '<div class="itflow-crop-frame itflow-free-frame" tabindex="0" aria-label="What is kept - drag its corners and edges, or move it with the arrow keys"></div>' +
                            '</div>' +
                            '<div class="itflow-crop-bar">' +
                                '<div class="btn-group btn-group-sm">' +
                                    '<button type="button" class="btn btn-outline-secondary" data-rotate title="Turn it a quarter" aria-label="Turn it a quarter"><i class="fas fa-fw fa-redo"></i></button>' +
                                    '<button type="button" class="btn btn-outline-secondary" data-fit title="The whole picture" aria-label="The whole picture"><i class="fas fa-fw fa-expand"></i></button>' +
                                '</div>' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary" data-trim><i class="fas fa-crop-alt me-1"></i>Trim the edges</button>' +
                                '<span class="ms-auto small text-body-secondary text-nowrap" data-size></span>' +
                            '</div>' +
                            '<div class="small text-body-secondary mt-2">Drag the corners and edges in to what you want to keep.</div>' +
                        '</div>' +
                        '<div class="itflow-picture-doc" data-doc-area hidden></div>' +
                        '<div class="alert alert-danger small mt-3 mb-0" data-error hidden></div>' +
                        '<input type="file" hidden data-file>' +
                        '<input type="file" hidden data-camera-file accept="image/*" capture="environment">' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-outline-danger me-auto" data-remove><i class="fas fa-trash me-2"></i>Remove</button>' +
                        '<button type="button" class="btn btn-outline-secondary" data-another hidden><i class="fas fa-images me-2"></i>Choose another</button>' +
                        '<button type="button" class="btn btn-primary text-bold" data-use disabled><i class="fas fa-check me-2"></i><span data-use-label>Use this picture</span></button>' +
                        '<button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);

        var crop = modal.querySelector('[data-crop-area]');
        var free = modal.querySelector('[data-free-area]');
        var w = {
            modal: modal,
            icon: modal.querySelector('[data-icon]'),
            title: modal.querySelector('[data-title]'),
            pick: modal.querySelector('[data-pick]'),
            now: modal.querySelector('[data-now]'),
            pickDrop: modal.querySelector('[data-pick-drop]'),
            pickChoose: modal.querySelector('[data-pick-choose]'),
            pickTypes: modal.querySelector('[data-pick-types]'),
            area: crop,
            freeArea: free,
            docArea: modal.querySelector('[data-doc-area]'),
            shapes: crop.querySelector('[data-shapes]'),
            trimButton: free.querySelector('[data-trim]'),
            error: modal.querySelector('[data-error]'),
            file: modal.querySelector('[data-file]'),
            camera: modal.querySelector('[data-camera]'),
            cameraFile: modal.querySelector('[data-camera-file]'),
            remove: modal.querySelector('[data-remove]'),
            another: modal.querySelector('[data-another]'),
            use: modal.querySelector('[data-use]'),
            useLabel: modal.querySelector('[data-use-label]'),
            field: null,
            picked: null
        };
        w.crop = new Cropper(crop.querySelector('.itflow-crop-stage'), crop.querySelector('.itflow-crop-frame'), crop.querySelector('input[type=range]'));
        w.free = new FreeCropper(free.querySelector('.itflow-crop-stage'), free.querySelector('.itflow-crop-frame'));
        [[w.crop, crop], [w.free, free]].forEach(function (pair) {
            var size = pair[1].querySelector('[data-size]');
            pair[0].onchange = function () {
                if (w.field && pair[0].source) {
                    var made = pair[0].outputSize(w.field.max);
                    size.textContent = made[0] + ' × ' + made[1];
                }
            };
        });

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

        // A phone's or tablet's camera, straight away - the button sits on the pick area, so it keeps its clicks to itself
        w.camera.addEventListener('click', function (e) {
            e.stopPropagation();
            w.cameraFile.value = '';
            w.cameraFile.click();
        });
        w.camera.addEventListener('keydown', function (e) {
            e.stopPropagation();
        });
        w.cameraFile.addEventListener('change', function () {
            if (w.cameraFile.files && w.cameraFile.files[0]) {
                load(w, w.cameraFile.files[0]);
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
                return i.kind === 'file' && takes(w.field, i.type);
            })[0];
            if (item) {
                e.preventDefault();
                load(w, item.getAsFile());
            }
        });

        modal.querySelectorAll('[data-rotate]').forEach(function (button) {
            button.addEventListener('click', function () {
                cropperOf(w).rotate();
            });
        });
        modal.querySelectorAll('[data-fit]').forEach(function (button) {
            button.addEventListener('click', function () {
                cropperOf(w).fit();
            });
        });
        w.trimButton.addEventListener('click', function () {
            if (!w.free.trim()) {
                showError(w, 'There are no edges to trim - the picture fills it.');
            }
        });
        w.use.addEventListener('click', function () {
            use(w);
        });
        w.remove.addEventListener('click', function () {
            remove(w);
        });
        modal.addEventListener('shown.bs.modal', function () {
            var cropper = cropperOf(w);
            cropper.layout();
            (w.picked && w.picked.kind === 'picture' ? (w.field.free ? w.free.frame : w.crop.stage) : (w.picked ? w.use : w.pick)).focus();
        });
        return w;
    }

    // The crop the field uses - the free one, or the frame of a shape
    function cropperOf(w) {
        return w.field && w.field.free ? w.free : w.crop;
    }

    // Does the field take a file of this type?
    function takes(field, type) {
        return !!field && (TYPES.test(type) && field.pictures.test(type) || (field.pdf && type === 'application/pdf'));
    }

    function showError(w, text) {
        w.error.textContent = text || '';
        w.error.hidden = !text;
    }

    // What the window shows - the pick, the crop, or a file taken as it is
    function show(w, what) {
        var free = !!(w.field && w.field.free);
        w.pick.hidden = what !== 'pick';
        w.area.hidden = what !== 'picture' || free;
        w.freeArea.hidden = what !== 'picture' || !free;
        w.docArea.hidden = what !== 'file';
        w.another.hidden = what === 'pick';
        w.use.disabled = what === 'pick';
        w.useLabel.textContent = what === 'file' ? 'Use this file' : 'Use this picture';
    }

    // A file picked - a picture into the crop, a PDF as it is
    function load(w, file) {
        var field = w.field;
        if (!takes(field, file.type)) {
            showError(w, 'That isn\'t ' + (field.pdf ? 'a picture or a PDF' : 'a ' + w.pickTypes.textContent + ' picture') + '.');
            return;
        }
        showError(w, '');
        if (file.type === 'application/pdf') {
            w.picked = { kind: 'file', file: file, name: file.name };
            w.docArea.replaceChildren(fileTile(file.name));
            show(w, 'file');
            w.use.focus();
            return;
        }
        readPicture(file).then(function (source) {
            if (w.field !== field) {
                return;
            }
            w.picked = { kind: 'picture', name: file.name, type: outputType(field, file.type) };
            show(w, 'picture');
            cropperOf(w).setSource(source);
            (field.free ? w.free.frame : w.crop.stage).focus();
        }).catch(function () {
            showError(w, 'That picture couldn\'t be read - try saving it as a JPG or PNG.');
        });
    }

    // What a crop is saved as - a JPG stays a JPG; anything else keeps any see-through parts, as WebP where the field takes it, else PNG
    function outputType(field, type) {
        if (type === 'image/jpeg') {
            return 'image/jpeg';
        }
        return field.pictures.test('image/webp') ? 'image/webp' : 'image/png';
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
            var crop = String(node.getAttribute('data-crop') || 'square').split(',').map(function (shape) {
                return shape.trim();
            });
            var shapes = crop.filter(function (shape) {
                return SHAPES[shape];
            });
            var input = node.querySelector('[data-picture-input]');
            var accept = input.getAttribute('accept') || 'image/jpeg,image/png,image/gif,image/webp';
            var pictures = accept.split(',').map(function (type) {
                return type.trim();
            }).filter(function (type) {
                return /^image\//.test(type);
            });
            node.itflowPicture = {
                node: node,
                input: input,
                accept: accept,
                // The picture types it takes - image/* is any of them
                pictures: pictures.indexOf('image/*') !== -1 ? TYPES : new RegExp('^(' + pictures.join('|').replace(/[.+]/g, '\\$&') + ')$'),
                pdf: accept.indexOf('application/pdf') !== -1,
                free: crop.indexOf('free') !== -1,
                trim: node.getAttribute('data-picture-trim') === '1',
                submit: node.getAttribute('data-picture-submit') === '1',
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
        w.icon.className = 'fas fa-fw me-2 ' + (field.pdf ? 'fa-receipt' : field.trim ? 'fa-image' : 'fa-camera');
        w.modal.querySelector('.modal-dialog').classList.toggle('modal-lg', !field.round && (field.free || field.shapes.indexOf('whole') !== -1));
        w.now.innerHTML = field.preview.innerHTML;
        w.now.classList.toggle('is-round', field.round);
        w.pickDrop.textContent = field.pdf ? 'Drop a photo or a PDF here' : 'Drop a picture here';
        w.pickChoose.textContent = field.pdf ? 'Choose a photo or PDF' : 'Choose a picture';
        w.pickTypes.textContent = typesText(field.accept);
        w.pick.setAttribute('aria-label', w.pickChoose.textContent);
        w.file.setAttribute('accept', field.accept);
        w.camera.hidden = !CAMERA;
        w.trimButton.hidden = !field.trim;
        w.crop.round = field.round;
        showError(w, '');

        // Opened again with something picked - as it was left
        var pending = field.pending;
        w.crop.shape = pending && pending.shape ? pending.shape : field.shapes[0];
        shapeButtons(w, field);
        w.crop.setSource(null);
        w.free.setSource(null);
        if (pending && pending.kind === 'file') {
            w.picked = pending;
            w.docArea.replaceChildren(fileTile(pending.name));
            show(w, 'file');
        } else if (pending) {
            w.picked = { kind: 'picture', name: pending.name, type: pending.type };
            show(w, 'picture');
            cropperOf(w).setSource(pending.source, pending.state);
        } else {
            w.picked = null;
            show(w, 'pick');
        }
        w.remove.hidden = !(field.has || field.pending);
        bootstrap.Modal.getOrCreateInstance(w.modal).show();
    }

    // The file in the field's file input, and on its button - then the form sent, for a field that saves straight away
    function fill(w, file, previewNode) {
        var field = w.field;
        var transfer = new DataTransfer();
        transfer.items.add(file);
        field.input.files = transfer.files;
        if (field.removeInput) {
            field.removeInput.value = '';
        }
        field.preview.replaceChildren(previewNode);
        field.node.classList.add('is-changed');
        field.input.dispatchEvent(new Event('change', { bubbles: true }));
        bootstrap.Modal.getOrCreateInstance(w.modal).hide();
        if (field.submit && field.input.form) {
            field.input.form.requestSubmit();
        }
    }

    // Use this picture (or file)
    function use(w) {
        var field = w.field;
        var picked = w.picked;
        if (!field || !picked) {
            return;
        }
        if (picked.kind === 'file') {
            field.pending = picked;
            fill(w, picked.file, fileTile(picked.name));
            return;
        }
        var cropper = cropperOf(w);
        if (!cropper.source) {
            return;
        }
        w.use.disabled = true;
        cropper.encode(picked.type, field.max).then(function (made) {
            var extension = { 'image/jpeg': '.jpg', 'image/webp': '.webp', 'image/png': '.png' }[made.blob.type] || '.png';
            var name = (picked.name.replace(/\.[^.]*$/, '') || 'picture') + extension;
            field.pending = { kind: 'picture', source: cropper.source, state: cropper.state(), shape: field.free ? null : w.crop.shape, name: picked.name, type: picked.type };
            if (field.url) {
                URL.revokeObjectURL(field.url);
            }
            field.url = URL.createObjectURL(made.blob);
            var img = el('img', field.imgClass);
            img.alt = '';
            img.src = field.url;
            fill(w, new File([made.blob], name, { type: made.blob.type, lastModified: Date.now() }), img);
        }).catch(function () {
            showError(w, 'That crop couldn\'t be saved - try another picture.');
        }).finally(function () {
            w.use.disabled = !w.picked;
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
