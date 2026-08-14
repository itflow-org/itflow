#!/usr/bin/env bash
# ITFlow: swap the vendored front-end libs for their AdminLTE 4 / Bootstrap 5
# equivalents. Run from the repo root, on a branch, with a clean tree.
#
# These are binaries-by-another-name (300KB+ minified bundles), so they are NOT
# in the patch -- carrying them as diff hunks would make it unreviewable.
#
#   ./fetch_adminlte4_libs.sh
#
set -euo pipefail

[ -d libs ] || { echo "run me from the ITFlow repo root" >&2; exit 1; }

ADMINLTE=4.3.1
BOOTSTRAP=5.3.8
TEMPUS=6.9.4

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

echo "==> AdminLTE ${ADMINLTE}"
curl -fsSL "https://registry.npmjs.org/admin-lte/-/admin-lte-${ADMINLTE}.tgz" -o "$tmp/adminlte.tgz"
tar xzf "$tmp/adminlte.tgz" -C "$tmp"
rm -rf libs/adminlte
mkdir -p libs/adminlte/css libs/adminlte/js
cp "$tmp/package/dist/css/adminlte.min.css"         libs/adminlte/css/
cp "$tmp/package/dist/css/adminlte.min.css.map"     libs/adminlte/css/
# Select2 compatibility theme now ships with AdminLTE, replacing
# libs/select2-bootstrap4-theme entirely.
cp "$tmp/package/dist/css/adminlte-select2.min.css" libs/adminlte/css/
cp "$tmp/package/dist/js/adminlte.min.js"           libs/adminlte/js/
cp "$tmp/package/dist/js/adminlte.min.js.map"       libs/adminlte/js/ 2>/dev/null || true

echo "==> Bootstrap ${BOOTSTRAP} JS"
# AdminLTE 4's CSS bundles Bootstrap, but its JS does NOT -- modals, dropdowns,
# collapse and tooltips still come from Bootstrap's own bundle.
curl -fsSL "https://registry.npmjs.org/bootstrap/-/bootstrap-${BOOTSTRAP}.tgz" -o "$tmp/bs.tgz"
tar xzf "$tmp/bs.tgz" -C "$tmp" --one-top-level=bs
cp "$tmp/bs/package/dist/js/bootstrap.bundle.min.js"     libs/bootstrap/js/
cp "$tmp/bs/package/dist/js/bootstrap.bundle.min.js.map" libs/bootstrap/js/ 2>/dev/null || true

echo "==> Tempus Dominus ${TEMPUS} (replaces tempusdominus-bootstrap-4)"
# tempusdominus-bootstrap-4 is abandoned and BS4-only. v6 is vanilla JS and
# drops the moment.js dependency for the picker.
curl -fsSL "https://registry.npmjs.org/@eonasdan/tempus-dominus/-/tempus-dominus-${TEMPUS}.tgz" -o "$tmp/td.tgz"
tar xzf "$tmp/td.tgz" -C "$tmp" --one-top-level=td
rm -rf libs/tempusdominus-bootstrap-4
mkdir -p libs/tempus-dominus/css libs/tempus-dominus/js
cp "$tmp/td/package/dist/css/tempus-dominus.min.css" libs/tempus-dominus/css/
cp "$tmp/td/package/dist/js/tempus-dominus.min.js"   libs/tempus-dominus/js/

echo "==> retiring BS4-only libs"
# Superseded by AdminLTE's own adminlte-select2.min.css
rm -rf libs/select2-bootstrap4-theme
# No Bootstrap 5 release; replaced by the vanilla toggle added to js/app.js
rm -rf libs/Show-Hide-Passwords-Bootstrap-4

cat <<'NOTE'

Done. Still needs doing by hand:

  * libs/DataTables -- rebuild the bundle at datatables.net/download with the
    Bootstrap 5 styling option instead of Bootstrap 4. One call site
    (js/app.js: new DataTable('.dataTables')), so only the CSS changes.

  * libs/daterangepicker -- works under BS5 but styles itself against BS4
    variables; check the popup against your theme.

Unaffected and left alone: tinymce, clipboardjs, SortableJS, chart.js,
fullcalendar, dropzone, intl-tel-input, inputmask, toastr, jquery, jquery-ui,
moment, popper, select2, TCPDF, PHPMailer, stripe-php, htmlpurifier.
NOTE
