#!/usr/bin/env python3
"""AdminLTE 3 -> 4 hand-written layer: layout skeleton, asset loads, plugin swaps.
Runs after bs5_codemod.py. Every edit asserts its anchor was found."""
import os, re, sys

ROOT = sys.argv[1]
os.chdir(ROOT)
applied, missed = [], []


def edit(path, old, new, label, count=1):
    with open(path, encoding='utf-8', errors='surrogateescape') as f:
        t = f.read()
    n = t.count(old)
    if n != count:
        missed.append(f'{path}: {label} (found {n}, expected {count})')
        return
    with open(path, 'w', encoding='utf-8', errors='surrogateescape') as f:
        f.write(t.replace(old, new))
    applied.append(f'{path}: {label}')


def resub(path, pat, new, label, expect=None):
    with open(path, encoding='utf-8', errors='surrogateescape') as f:
        t = f.read()
    t2, n = re.subn(pat, new, t)
    if n == 0 or (expect is not None and n != expect):
        missed.append(f'{path}: {label} (matched {n}, expected {expect})')
        return
    with open(path, 'w', encoding='utf-8', errors='surrogateescape') as f:
        f.write(t2)
    applied.append(f'{path}: {label} x{n}')


# =============================================================== 1. main header
# AdminLTE 4 dropped the accent-*/dark-mode class pair; colour mode is now the
# Bootstrap 5.3 data-bs-theme attribute, set on <html>.
edit('includes/header.php',
     '<html lang="en">',
     '<html lang="en"<?php if ($user_config_theme_dark) echo \' data-bs-theme="dark"\'; ?>>',
     'colour mode -> data-bs-theme on <html>')

edit('includes/header.php', '''    <link rel="stylesheet" href="/libs/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css" >
    <link rel="stylesheet" href="/libs/select2/css/select2.min.css">
    <link rel="stylesheet" href="/libs/select2-bootstrap4-theme/select2-bootstrap4.min.css">''',
     '''    <link rel="stylesheet" href="/libs/tempus-dominus/css/tempus-dominus.min.css">
    <link rel="stylesheet" href="/libs/select2/css/select2.min.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte-select2.min.css">''',
     'stylesheet swap: tempusdominus + select2 theme')

# adminlte.min.css must load BEFORE itflow_custom.css now: v4 bundles Bootstrap
# 5.3 and ships its own cascade layer ordering, so custom overrides go last.
edit('includes/header.php', '''    <link rel="stylesheet" href="/css/itflow_custom.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">''',
     '''    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">''',
     'adminlte.css before itflow_custom.css')

edit('includes/header.php', '''<body class="
    hold-transition sidebar-mini layout-fixed layout-navbar-fixed 
    accent-<?= escapeHtml($config_theme) ?>
    <?php if ($user_config_theme_dark) echo 'dark-mode'; ?>
">
    <div class="wrapper text-sm">''',
     '''<body class="layout-fixed sidebar-expand-lg app-loaded">
    <div class="app-wrapper text-sm">''',
     'body classes + wrapper -> app-wrapper')

# ============================================================== 2. content area
edit('includes/inc_wrapper.php', '''    <div class="content-wrapper">

      <!-- Main content -->
      <div class="content mt-3 p-0 px-md-2">
        <div class="container-fluid">''',
     '''    <main class="app-main">

      <!-- Main content -->
      <div class="app-content mt-3 p-0 px-md-2">
        <div class="container-fluid">''',
     'content-wrapper -> app-main/app-content')

edit('guest/includes/inc_wrapper.php', '''<div class="content-wrapper">

    <!-- Main content -->
    <div class="content">
        <div class="container">''',
     '''<main class="app-main">

    <!-- Main content -->
    <div class="app-content">
        <div class="container">''',
     'guest content-wrapper -> app-main')

# ==================================================================== 3. footer
edit('includes/footer.php', '''</div><!-- /.container-fluid -->
</div> <!-- /.content -->
</div> <!-- /.content-wrapper -->
</div> <!-- ./wrapper -->''',
     '''</div><!-- /.container-fluid -->
</div> <!-- /.app-content -->
</main> <!-- /.app-main -->
</div> <!-- /.app-wrapper -->''',
     'closing tags match app-* skeleton')

edit('includes/footer.php', '''<!-- Bootstrap 4 -->
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>''',
     '''<!-- Bootstrap 5 -->
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>''',
     'bootstrap comment')

edit('includes/footer.php',
     '<script src="/libs/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>',
     '<script src="/libs/tempus-dominus/js/tempus-dominus.min.js"></script>',
     'tempusdominus -> tempus-dominus 6')

# Show-Hide-Passwords is a jQuery/BS4 plugin with no BS5 release. Replaced by a
# vanilla toggle in js/app.js that keeps the same data-toggle="password" contract.
edit('includes/footer.php',
     '<script src="/libs/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>\n',
     '',
     'drop Show-Hide-Passwords-Bootstrap-4')

# ================================================================== 4. top nav
edit('includes/top_nav.php',
     '<nav class="main-header navbar navbar-expand navbar-<?= escapeHtml($config_theme) ?> navbar-dark">',
     '<nav class="app-header navbar navbar-expand bg-<?= escapeHtml($config_theme) ?>" data-bs-theme="dark">',
     'main-header -> app-header')

# .form-inline was removed in BS5; d-flex is the documented replacement.
resub('includes/top_nav.php', r'class="form-inline"', 'class="d-flex"', 'form-inline -> d-flex')

# ================================================================= 5. sidebars
SIDENAVS = [
    'admin/includes/side_nav.php',
    'agent/includes/side_nav.php',
    'agent/includes/client_side_nav.php',
    'agent/includes/client_overview_side_nav.php',
    'agent/user/includes/user_side_nav.php',
    'agent/custom/includes/custom_side_nav.php',
    'agent/reports/includes/reports_side_nav.php',
]
for p in SIDENAVS:
    # sidebar-dark-* is gone in v4; the sidebar is themed with data-bs-theme.
    resub(p, r'<aside class="main-sidebar sidebar-dark-(?:<\?= escapeHtml\(\$config_theme\) \?>|primary) d-print-none">',
          '<aside class="app-sidebar shadow d-print-none" data-bs-theme="dark">',
          'main-sidebar -> app-sidebar', expect=1)
    # .nav-sidebar was renamed .sidebar-menu (treeview.ts keys off it).
    resub(p, r'\bnav-sidebar\b', 'sidebar-menu', 'nav-sidebar -> sidebar-menu')
    # v4 wraps the scrollable menu region in .sidebar-wrapper.
    resub(p, r'<div class="sidebar">', '<div class="sidebar-wrapper">', 'sidebar -> sidebar-wrapper', expect=1)

# ============================================================== 6. js/app.js
edit('js/app.js', """    $('.select2').select2({
        theme: 'bootstrap4',""",
     """    $('.select2').select2({
        theme: 'bootstrap-5',""",
     'select2 theme bootstrap4 -> bootstrap-5')

edit('js/app.js', "    $('.datetimepicker').datetimepicker();",
     """    document.querySelectorAll('.datetimepicker').forEach(function (el) {
        new tempusDominus.TempusDominus(el);
    });""",
     'datetimepicker -> Tempus Dominus 6 (vanilla)')

# Replacement for the dropped Show-Hide-Passwords plugin. Same markup contract:
# a button with data-toggle="password" adjacent to the input it reveals.
edit('js/app.js', "    $('[data-mask]').inputmask();",
     """    $('[data-mask]').inputmask();

    // Password reveal. Replaces Show-Hide-Passwords-Bootstrap-4, which has no
    // Bootstrap 5 release. Same data-toggle="password" contract as before.
    document.querySelectorAll('[data-toggle="password"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.input-group');
            var input = group && group.querySelector('input');
            if (!input) {
                return;
            }
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !hidden);
                icon.classList.toggle('fa-eye-slash', hidden);
            }
            btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
        });
    });""",
     'vanilla password reveal replacing Show-Hide-Passwords')

print('APPLIED:')
for a in applied:
    print('  ' + a)
if missed:
    print('\nMISSED (anchor not found - needs hand review):')
    for m in missed:
        print('  ' + m)
    sys.exit(1)
