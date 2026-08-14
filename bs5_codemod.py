#!/usr/bin/env python3
"""
ITFlow: AdminLTE 3.2.0 / Bootstrap 4.6 -> AdminLTE 4.3.1 / Bootstrap 5.3 codemod.
Mechanical pass only. Hand-written work (layout skeletons, data-toggle="buttons",
plugin swaps) is applied separately.
"""
import os, re, sys, collections

ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
EXTS = ('.php', '.js', '.css', '.html')
SKIP_DIRS = {'.git', 'libs', 'node_modules', 'uploads'}

stats = collections.Counter()
unresolved = []


def sub(pattern, repl, text, key, flags=0):
    new, n = re.subn(pattern, repl, text, flags=flags)
    if n:
        stats[key] += n
    return new


# ---------------------------------------------------------------- data API
# Bootstrap namespaced its data attributes. Two ITFlow-owned values must NOT
# migrate: data-toggle="ajax-modal" (custom JS) and data-toggle="password"
# (Show-Hide-Passwords). data-toggle="buttons" is removed in BS5 entirely and
# is left alone here so the hand-fix pass can find it.
BS_TOGGLE_VALUES = {'pill', 'dropdown', 'modal', 'collapse', 'tooltip', 'tab', 'popover'}

DATA_ATTRS = [
    'dismiss', 'target', 'parent', 'ride', 'slide', 'slide-to', 'backdrop',
    'keyboard', 'placement', 'content', 'trigger', 'offset', 'html', 'animation',
    'delay', 'boundary', 'container', 'template', 'title-attr', 'focus',
    'autohide', 'spy', 'interval', 'touch', 'wrap', 'pause', 'display',
]


def data_api(t):
    def toggle_repl(m):
        return f'data-bs-toggle="{m.group(1)}"' if m.group(1) in BS_TOGGLE_VALUES else m.group(0)
    t = sub(r'\bdata-toggle="([a-z-]+)"', toggle_repl, t, 'data-toggle -> data-bs-toggle')
    for a in DATA_ATTRS:
        t = sub(rf'\bdata-{a}=', f'data-bs-{a}=', t, f'data-{a} -> data-bs-{a}')
    return t


# ------------------------------------------------------- direction utilities
def utilities(t):
    t = sub(r'\bml-(auto|[0-5])\b', r'ms-\1', t, 'ml-* -> ms-*')
    t = sub(r'\bmr-(auto|[0-5])\b', r'me-\1', t, 'mr-* -> me-*')
    t = sub(r'\bpl-(auto|[0-5])\b', r'ps-\1', t, 'pl-* -> ps-*')
    t = sub(r'\bpr-(auto|[0-5])\b', r'pe-\1', t, 'pr-* -> pe-*')
    for bp in ('sm', 'md', 'lg', 'xl'):
        t = sub(rf'\bml-{bp}-(auto|[0-5])\b', rf'ms-{bp}-\1', t, 'ml-*-* -> ms-*-*')
        t = sub(rf'\bmr-{bp}-(auto|[0-5])\b', rf'me-{bp}-\1', t, 'mr-*-* -> me-*-*')
        t = sub(rf'\bpl-{bp}-(auto|[0-5])\b', rf'ps-{bp}-\1', t, 'pl-*-* -> ps-*-*')
        t = sub(rf'\bpr-{bp}-(auto|[0-5])\b', rf'pe-{bp}-\1', t, 'pr-*-* -> pe-*-*')

    t = sub(r'\btext-left\b', 'text-start', t, 'text-left -> text-start')
    t = sub(r'\btext-right\b', 'text-end', t, 'text-right -> text-end')
    for bp in ('sm', 'md', 'lg', 'xl'):
        t = sub(rf'\btext-{bp}-left\b', f'text-{bp}-start', t, 'text-*-left -> text-*-start')
        t = sub(rf'\btext-{bp}-right\b', f'text-{bp}-end', t, 'text-*-right -> text-*-end')

    t = sub(r'\bfloat-left\b', 'float-start', t, 'float-left -> float-start')
    t = sub(r'\bfloat-right\b', 'float-end', t, 'float-right -> float-end')
    for bp in ('sm', 'md', 'lg', 'xl'):
        t = sub(rf'\bfloat-{bp}-left\b', f'float-{bp}-start', t, 'float-*-left -> float-*-start')
        t = sub(rf'\bfloat-{bp}-right\b', f'float-{bp}-end', t, 'float-*-right -> float-*-end')

    t = sub(r'\bborder-left\b', 'border-start', t, 'border-left -> border-start')
    t = sub(r'\bborder-right\b', 'border-end', t, 'border-right -> border-end')
    t = sub(r'\brounded-left\b', 'rounded-start', t, 'rounded-left -> rounded-start')
    t = sub(r'\brounded-right\b', 'rounded-end', t, 'rounded-right -> rounded-end')

    t = sub(r'\bfont-weight-(bold|bolder|normal|light|lighter)\b', r'fw-\1', t, 'font-weight-* -> fw-*')
    t = sub(r'\bfont-italic\b', 'fst-italic', t, 'font-italic -> fst-italic')
    t = sub(r'\btext-monospace\b', 'font-monospace', t, 'text-monospace -> font-monospace')
    t = sub(r'\bsr-only-focusable\b', 'visually-hidden-focusable', t, 'sr-only-focusable')
    t = sub(r'\bsr-only\b', 'visually-hidden', t, 'sr-only -> visually-hidden')
    t = sub(r'\bno-gutters\b', 'g-0', t, 'no-gutters -> g-0')
    t = sub(r'\belevation-[1-5]\b', 'shadow', t, 'elevation-* -> shadow')
    t = sub(r'\bdropdown-menu-right\b', 'dropdown-menu-end', t, 'dropdown-menu-right -> -end')
    t = sub(r'\bdropdown-menu-left\b', 'dropdown-menu-start', t, 'dropdown-menu-left -> -start')
    t = sub(r'\bdropleft\b', 'dropstart', t, 'dropleft -> dropstart')
    t = sub(r'\bdropright\b', 'dropend', t, 'dropright -> dropend')
    t = sub(r'\bthead-light\b', 'table-light', t, 'thead-light -> table-light')
    t = sub(r'\bthead-dark\b', 'table-dark', t, 'thead-dark -> table-dark')
    return t


# ------------------------------------------------------------------- forms
def forms(t):
    t = sub(r'\bform-group\b', 'mb-3', t, 'form-group -> mb-3')
    t = sub(r'\bform-row\b', 'row g-2', t, 'form-row -> row g-2')
    t = sub(r'\bcustom-control-input\b', 'form-check-input', t, 'custom-control-input')
    t = sub(r'\bcustom-control-label\b', 'form-check-label', t, 'custom-control-label')
    t = sub(r'\bcustom-control\s+custom-switch\b', 'form-check form-switch', t, 'custom-switch')
    t = sub(r'\bcustom-control\s+custom-checkbox\b', 'form-check', t, 'custom-checkbox')
    t = sub(r'\bcustom-control\s+custom-radio\b', 'form-check', t, 'custom-radio')
    t = sub(r'\bcustom-switch\b', 'form-switch', t, 'custom-switch (bare)')
    t = sub(r'\bcustom-checkbox\b', 'form-check', t, 'custom-checkbox (bare)')
    t = sub(r'\bcustom-radio\b', 'form-check', t, 'custom-radio (bare)')
    t = sub(r'\bcustom-control\b', 'form-check', t, 'custom-control (bare)')
    t = sub(r'\bcustom-select-(sm|lg)\b', r'form-select-\1', t, 'custom-select-* -> form-select-*')
    t = sub(r'\bcustom-select\b', 'form-select', t, 'custom-select -> form-select')
    t = sub(r'\bcustom-range\b', 'form-range', t, 'custom-range -> form-range')
    t = sub(r'\bform-control-file\b', 'form-control', t, 'form-control-file -> form-control')
    t = sub(r'\bform-control-range\b', 'form-range', t, 'form-control-range -> form-range')
    t = sub(r'\bcustom-file-input\b', 'form-control', t, 'custom-file-input -> form-control')
    return t


# -------------------------------------------------------------- components
BADGE_DARK_TEXT = {'warning', 'info', 'light'}


def components(t):
    def badge_repl(m):
        colour = m.group(1)
        out = f'badge bg-{colour}'
        if colour in BADGE_DARK_TEXT:
            out += ' text-dark'
        return out
    t = sub(r'\bbadge\s+badge-(primary|secondary|success|danger|warning|info|light|dark)\b',
            badge_repl, t, 'badge badge-* -> badge bg-*')
    t = sub(r'\bbadge-pill\b', 'rounded-pill', t, 'badge-pill -> rounded-pill')
    t = sub(r'\bbadge-(primary|secondary|success|danger|warning|info|light|dark)\b',
            r'bg-\1', t, 'badge-* (bare) -> bg-*')

    # .close -> .btn-close. The X glyph is now a background image, so the inner
    # <span>&times;</span> must go with it.
    t = sub(
        r'<button([^>]*?)class="close text-(?:white|light)"([^>]*?)>\s*'
        r'(?:<span[^>]*>\s*&times;\s*</span>\s*)?</button>',
        r'<button\1class="btn-close btn-close-white"\2></button>',
        t, 'close text-white -> btn-close btn-close-white', flags=re.S)
    t = sub(
        r'<button([^>]*?)class="close"([^>]*?)>\s*'
        r'(?:<span[^>]*>\s*&times;\s*</span>\s*)?</button>',
        r'<button\1class="btn-close"\2></button>',
        t, 'close -> btn-close', flags=re.S)

    # .media is gone; flex utilities are the documented replacement.
    t = sub(r'\bmedia-body\b', 'flex-grow-1', t, 'media-body -> flex-grow-1')
    t = sub(r'class="media"', 'class="d-flex"', t, 'media -> d-flex')
    t = sub(r'\bmedia-list\b', 'list-unstyled', t, 'media-list -> list-unstyled')

    # .btn-block is gone. w-100 is the closest drop-in for a lone button.
    t = sub(r'\bbtn-block\b', 'w-100', t, 'btn-block -> w-100')

    # AdminLTE 3 JS attributes -> AdminLTE 4
    t = sub(r'data-widget="pushmenu"', 'data-lte-toggle="sidebar"', t, 'pushmenu -> data-lte-toggle')
    t = sub(r'data-widget="treeview"', 'data-lte-toggle="treeview"', t, 'treeview -> data-lte-toggle')
    t = sub(r'data-card-widget="collapse"', 'data-lte-toggle="card-collapse"', t, 'card collapse')
    t = sub(r'data-card-widget="remove"', 'data-lte-toggle="card-remove"', t, 'card remove')
    t = sub(r'data-card-widget="maximize"', 'data-lte-toggle="card-maximize"', t, 'card maximize')
    return t


# -------------------------------------------- input-group append/prepend unwrap
OPEN_RE = re.compile(r'<div class="input-group-(?:append|prepend)[^"]*"[^>]*>')
DIV_RE = re.compile(r'<div\b|</div>')


def unwrap_input_groups(t, path):
    """BS5 removed the wrapper element entirely - the child .input-group-text or
    .btn becomes a direct child of .input-group. This deletes the opening tag and
    its matching close tag, tracking nesting depth so nested divs are safe."""
    while True:
        m = OPEN_RE.search(t)
        if not m:
            break
        depth = 1
        pos = m.end()
        close_span = None
        for d in DIV_RE.finditer(t, pos):
            depth += 1 if d.group(0) == '<div' else -1
            if depth == 0:
                close_span = d.span()
                break
        if close_span is None:
            unresolved.append((path, 'unbalanced input-group wrapper'))
            t = t[:m.start()] + '<!--ITFLOW-UNRESOLVED-->' + t[m.end():]
            continue
        # strip the close tag first so the earlier offsets stay valid
        tail = t[close_span[1]:]
        # swallow the newline+indent left behind by the removed close tag
        head = t[:close_span[0]].rstrip(' \t')
        if head.endswith('\n'):
            head = head[:-1]
        t = head + tail
        # now the open tag
        pre = t[:m.start()].rstrip(' \t')
        if pre.endswith('\n'):
            pre = pre[:-1]
        t = pre + t[m.end():]
        stats['input-group-append/prepend unwrapped'] += 1
    return t.replace('<!--ITFLOW-UNRESOLVED-->', '<div class="input-group-append">')


# -------------------------------------------------------------------- driver
def main():
    touched = 0
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            if not fn.endswith(EXTS):
                continue
            p = os.path.join(dirpath, fn)
            with open(p, encoding='utf-8', errors='surrogateescape') as fh:
                orig = fh.read()
            t = orig
            t = data_api(t)
            t = utilities(t)
            t = forms(t)
            t = components(t)
            t = unwrap_input_groups(t, p)
            if t != orig:
                with open(p, 'w', encoding='utf-8', errors='surrogateescape') as fh:
                    fh.write(t)
                touched += 1

    width = max(len(k) for k in stats) if stats else 10
    for k, v in sorted(stats.items(), key=lambda kv: -kv[1]):
        print(f'{k:<{width}}  {v}')
    print(f'\n{"TOTAL REPLACEMENTS":<{width}}  {sum(stats.values())}')
    print(f'{"FILES TOUCHED":<{width}}  {touched}')
    if unresolved:
        print('\nUNRESOLVED:')
        for p, why in unresolved:
            print(f'  {p}: {why}')


if __name__ == '__main__':
    main()
