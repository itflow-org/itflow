// The signing pad (includes/contract_signature_pad.php) - on the guest signing
// page, in the client portal and in the agent's Countersign window. Draw
// with a mouse, finger or pen, or type the name instead; as the form is sent
// the drawing goes into it as a PNG. A form with nothing drawn isn't sent.
// The ink is dark on a white pad in either theme - it is going on paper.
// The server checks it all again.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file. No inline styles or scripts:
// the client portal's Content-Security-Policy allows neither.

(function () {

    function wire(pad) {
        if (pad.hasAttribute('data-wired')) {
            return;
        }
        pad.setAttribute('data-wired', '1');

        const form = pad.closest('form');
        const canvas = pad.querySelector('[data-signature-canvas]');
        const hint = pad.querySelector('[data-signature-hint]');
        const mode = pad.querySelector('[data-signature-mode]');
        const data = pad.querySelector('[data-signature-data]');
        const name = pad.querySelector('[data-signature-name]');
        const typed = pad.querySelector('[data-signature-typed]');
        const context = canvas.getContext('2d');
        const strokes = [];
        let stroke = null;

        // The canvas matches its box, sharp on high-density screens; the strokes are kept to draw again after a resize
        function size() {
            const ratio = window.devicePixelRatio || 1;
            const box = canvas.getBoundingClientRect();
            if (!box.width) {
                return;
            }
            canvas.width = Math.round(box.width * ratio);
            canvas.height = Math.round(box.height * ratio);
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            redraw();
        }

        function redraw() {
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.lineWidth = 2.4;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            context.strokeStyle = '#1d2433';
            context.fillStyle = '#1d2433';
            strokes.forEach(function (points) {
                if (points.length === 1) {
                    context.beginPath();
                    context.arc(points[0][0], points[0][1], 1.3, 0, Math.PI * 2);
                    context.fill();
                    return;
                }
                context.beginPath();
                context.moveTo(points[0][0], points[0][1]);
                for (let i = 1; i < points.length; i++) {
                    context.lineTo(points[i][0], points[i][1]);
                }
                context.stroke();
            });
        }

        function point(event) {
            const box = canvas.getBoundingClientRect();
            return [event.clientX - box.left, event.clientY - box.top];
        }

        function setHint(text, error) {
            hint.textContent = text;
            hint.classList.toggle('is-error', Boolean(error));
            hint.hidden = !text;
        }

        canvas.addEventListener('pointerdown', function (event) {
            event.preventDefault();
            canvas.setPointerCapture(event.pointerId);
            stroke = [point(event)];
            strokes.push(stroke);
            setHint('', false);
            redraw();
        });
        canvas.addEventListener('pointermove', function (event) {
            if (!stroke) {
                return;
            }
            event.preventDefault();
            stroke.push(point(event));
            redraw();
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (type) {
            canvas.addEventListener(type, function () {
                stroke = null;
            });
        });

        pad.querySelector('[data-signature-clear]').addEventListener('click', function () {
            strokes.length = 0;
            redraw();
            setHint('Sign here with your mouse, finger or pen', false);
        });

        function drawTyped() {
            const value = name ? name.value.trim() : '';
            typed.textContent = value || 'Write your full name above';
            typed.classList.toggle('is-empty', !value);
        }
        if (name) {
            name.addEventListener('input', drawTyped);
        }

        pad.querySelectorAll('[data-signature-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const which = tab.getAttribute('data-signature-tab');
                mode.value = which;
                pad.querySelectorAll('[data-signature-tab]').forEach(function (other) {
                    const on = other === tab;
                    other.classList.toggle('active', on);
                    other.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                pad.querySelectorAll('[data-signature-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-signature-panel') !== which;
                });
                if (which === 'draw') {
                    size();
                } else {
                    drawTyped();
                }
            });
        });

        if (form) {
            form.addEventListener('submit', function (event) {
                if (mode.value !== 'draw') {
                    data.value = '';
                    return;
                }
                const drawn = strokes.reduce(function (total, points) {
                    return total + points.length;
                }, 0);
                if (drawn < 6) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    setHint('Draw your signature here first - or choose Type', true);
                    canvas.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    return;
                }
                data.value = canvas.toDataURL('image/png');
            }, true);
        }

        // A pad in a modal or a hidden tab has no size until it is shown
        size();
        if (window.ResizeObserver) {
            let width = canvas.getBoundingClientRect().width;
            new ResizeObserver(function () {
                const now = canvas.getBoundingClientRect().width;
                if (now && now !== width) {
                    width = now;
                    size();
                }
            }).observe(canvas);
        } else {
            window.addEventListener('resize', size);
        }
        drawTyped();
    }

    function wireAll() {
        document.querySelectorAll('[data-signature-pad]').forEach(wire);
    }

    wireAll();
    // Countersign opens in a modal loaded after the page
    document.addEventListener('shown.bs.modal', wireAll);
})();
