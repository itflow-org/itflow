/*
 * Bootstrap's defaults, set once for every page - loaded straight after
 * bootstrap.bundle.min.js, before anything is opened, which is when Bootstrap
 * reads them.
 *
 * Dropdown menus are placed against the window (Popper's fixed strategy)
 * rather than inside whatever holds them. A list's table sits in a
 * .table-responsive wrapper, which scrolls sideways and so clips anything that
 * sticks out of it: with only a row or two in a list, a row's actions menu was
 * cut off at the bottom of the table. Fixed, the menu opens over the page
 * instead, and still turns upward when there's no room below.
 */
if (window.bootstrap && bootstrap.Dropdown) {
    bootstrap.Dropdown.Default.popperConfig = { strategy: 'fixed' };
}
