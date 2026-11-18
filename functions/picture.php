<?php

/*
 * Picture fields - a person's picture, or a photo of an asset, a location or
 * a rack, as a form shows it: the picture itself (their initials, or a camera,
 * when there isn't one) on a button with a camera badge. Clicking it opens the
 * Picture window (js/picture_field.js): drop, paste or choose a picture, crop
 * it to fit the shape it's shown in, and use it - or remove the one there is.
 * The form still posts a file field, and a field that is 1 to take the picture
 * off, as it always did, so nothing behind the forms changed.
 */

/**
 * The field, HTML. $name is the file field's name, $remove_name the field
 * posted as 1 to take the picture off ('' when there is none to take off),
 * $current what shows now and $empty what shows without a picture (HTML).
 * $opts:
 *   'round'   a person's picture - shown and cropped as a circle
 *   'title'   the window's title ('Picture', or 'Photo')
 *   'shapes'  the shapes a photo can be cropped to, the first to start with -
 *             square, 4:3, 3:4, 16:9 and whole ('square') - or 'free', any
 *             shape, by dragging the frame's corners and edges
 *   'max'     the longest side kept, in pixels (512 for a person, 2048)
 *   'class'   the classes of the <img> a new picture shows as - for a person
 *             the avatar's ('itflow-picture-photo' for a photo)
 *   'box'     a photo's box - 'square', 'wide' (4:3), 'tall' (3:4, a receipt),
 *             'logo' (wide, the picture fitted in it) or 'product' (square,
 *             the picture fitted in it on white)
 *   'name'    the form field with the person's name - their initials follow it
 *   'accept'  the file types it takes (JPG, PNG, GIF and WebP) - with
 *             application/pdf a PDF is taken as it is
 *   'trim'    a Trim the edges button for a free crop - a logo
 *   'submit'  send the form once a picture is used - a form that is only it
 *   'id'      the button's id, for a <label for>
 */
function pictureField($name, $remove_name, $current, $empty, array $opts = []) {
    $round = !empty($opts['round']);
    $noun = $round ? 'picture' : 'photo';
    $attributes = [
        'data-picture-field' => '',
        'data-picture-title' => $opts['title'] ?? ucfirst($noun),
        'data-picture-class' => $opts['class'] ?? 'itflow-picture-photo',
        'data-picture-has' => $remove_name !== '' ? '1' : '',
        'data-crop' => $opts['shapes'] ?? 'square',
        'data-crop-max' => (string) intval($opts['max'] ?? ($round ? 512 : 2048)),
        'data-crop-shape' => $round ? 'circle' : 'rect',
        'data-picture-name' => (string) ($opts['name'] ?? ''),
        'data-picture-trim' => !empty($opts['trim']) ? '1' : '',
        'data-picture-submit' => !empty($opts['submit']) ? '1' : '',
    ];
    $html = '';
    foreach ($attributes as $attribute => $value) {
        $html .= ' ' . $attribute . '="' . escapeHtml($value) . '"';
    }
    $box = $round ? 'is-round' : 'is-photo is-' . ($opts['box'] ?? 'square');
    $label = escapeHtml("Change the $noun");
    $id = isset($opts['id']) ? ' id="' . escapeHtml($opts['id']) . '"' : '';
    return '<div class="itflow-picture-field ' . $box . '"' . $html . '>'
        . '<button type="button" class="itflow-picture-button"' . $id . ' data-picture-open title="' . $label . '" aria-label="' . $label . '">'
        . '<span class="itflow-picture-preview" data-picture-preview>' . $current . '</span>'
        . '<span class="itflow-picture-badge" aria-hidden="true"><i class="fas fa-camera"></i></span>'
        . '</button>'
        . '<template data-picture-empty>' . $empty . '</template>'
        . '<input type="file" class="d-none" name="' . escapeHtml($name) . '" accept="' . escapeHtml($opts['accept'] ?? 'image/jpeg,image/png,image/gif,image/webp') . '" tabindex="-1" aria-hidden="true" data-picture-input>'
        . ($remove_name !== '' ? '<input type="hidden" name="' . escapeHtml($remove_name) . '" value="" data-picture-remove>' : '')
        . '</div>';
}

/** A photo as a picture field shows it - $src already the page's path to it. HTML. */
function picturePhoto($src, $alt = '') {
    return '<img class="itflow-picture-photo" src="' . escapeHtml($src) . '" alt="' . escapeHtml($alt) . '">';
}

/** What a photo's field shows without one - a camera (or $icon) and $text. HTML. */
function picturePhotoEmpty($text = 'Add a photo', $icon = 'fa-camera') {
    return '<span class="itflow-picture-none"><i class="fas ' . escapeHtml($icon) . '"></i><span>' . escapeHtml($text) . '</span></span>';
}

/** A file a field has that isn't a picture - a PDF receipt - as it shows: its icon and $text. HTML. */
function pictureFile($text) {
    return '<span class="itflow-picture-none is-file"><i class="fas fa-file-pdf"></i><span class="text-truncate mw-100 px-2">' . escapeHtml($text) . '</span></span>';
}
