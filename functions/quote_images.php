<?php

/*
 * ITFlow - pictures on quote lines and products. A quote line can show a
 * picture beside its name - on the quote, the guest link, the client portal
 * and the PDF - and a product can keep one, which a line added from it takes.
 *
 * A picture is uploaded, or pulled from a link: the image itself, or a web
 * page's main picture (its og:image). Fetching a link only reaches public
 * addresses - never this server or the network it sits on - with a size cap,
 * a time limit and at most three redirects.
 *
 * ITFlow keeps its own copy under uploads/quote_images/, redrawn at up to 800
 * pixels a side (only the pixels survive - no metadata, nothing hidden after
 * them, never SVG) with an unguessable name, so the guest link and the portal
 * can show it. Each line and product owns its own copy.
 *
 * Columns (database 3.8.8): quote_items.item_image and products.product_image -
 * the names MountaineerIT's build uses; their uploads/mit_images/ pictures show
 * as they are - and settings.config_quote_show_images (Admin > Settings > Quotes).
 */

const QUOTE_IMAGE_DIR = 'uploads/quote_images';
const QUOTE_IMAGE_MAX_BYTES = 8 * 1024 * 1024;
const QUOTE_IMAGE_MAX_SIDE = 800;

/** Whether the 3.8.8 update has run. */
function quoteImagesReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.8', '>=');
    }
    return $ready;
}

/** Whether quotes show their lines' pictures (Admin > Settings > Quotes) - on unless switched off. */
function quoteImagesShown() {
    global $mysqli;
    static $shown = null;
    if ($shown === null) {
        $shown = quoteImagesReady()
            && intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_quote_show_images FROM settings WHERE company_id = 1"))[0] ?? 1) === 1;
    }
    return $shown;
}

/** Whether a stored path is one of these pictures, and nothing else. */
function quoteImageIsOurs($relative) {
    return is_string($relative) && preg_match('#^uploads/(quote_images|mit_images)/[a-f0-9]{32}\.(jpg|png)$#', $relative) === 1;
}

/** Where a picture is on disk. */
function quoteImagePath($relative) {
    return dirname(__DIR__) . '/' . $relative;
}

/** Where a browser shows it from - '' for none. */
function quoteImageUrl($relative) {
    return quoteImageIsOurs($relative) && is_file(quoteImagePath($relative)) ? '/' . $relative : '';
}

/*
 * Keep image bytes as a picture of our own - redrawn at up to 800 pixels a
 * side, as a PNG when it can be see-through, else a JPG. Its path. Throws,
 * with what to tell the agent, when it isn't a JPG, PNG, GIF or WebP.
 */
function quoteImageStore($bytes) {
    if (strlen($bytes) === 0 || strlen($bytes) > QUOTE_IMAGE_MAX_BYTES) {
        throw new RuntimeException('The picture is empty or bigger than ' . (QUOTE_IMAGE_MAX_BYTES / 1048576) . ' MB.');
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('That is not a JPG, PNG, GIF or WebP picture.');
    }
    // The whole bitmap is held in memory - about 4 bytes a pixel
    if ($info[0] * $info[1] > 25000000) {
        throw new RuntimeException('That picture is too large to work with.');
    }
    $source = @imagecreatefromstring($bytes);
    if (!$source) {
        throw new RuntimeException('That picture could not be read.');
    }
    $width = imagesx($source);
    $height = imagesy($source);
    $scale = min(1, QUOTE_IMAGE_MAX_SIDE / max($width, $height));
    $new_width = max(1, intval(round($width * $scale)));
    $new_height = max(1, intval(round($height * $scale)));
    $alpha = in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
    $image = imagecreatetruecolor($new_width, $new_height);
    if ($alpha) {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
    } else {
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    }
    imagecopyresampled($image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    imagedestroy($source);

    $dir = quoteImagePath(QUOTE_IMAGE_DIR);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        throw new RuntimeException('The pictures folder could not be made in uploads.');
    }
    $relative = QUOTE_IMAGE_DIR . '/' . bin2hex(random_bytes(16)) . ($alpha ? '.png' : '.jpg');
    $saved = $alpha ? imagepng($image, quoteImagePath($relative), 6) : imagejpeg($image, quoteImagePath($relative), 85);
    imagedestroy($image);
    if (!$saved) {
        throw new RuntimeException('The picture could not be saved.');
    }
    return $relative;
}

/** An uploaded file ($_FILES entry) kept - '' when none was sent. Throws on a bad one. */
function quoteImageFromUpload($file) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The picture didn\'t come through - try again, up to ' . (QUOTE_IMAGE_MAX_BYTES / 1048576) . ' MB.');
    }
    return quoteImageStore((string) file_get_contents($file['tmp_name']));
}

/** A copy of one of these pictures, for a line or product of its own - '' when there is none. */
function quoteImageCopy($relative) {
    if (!quoteImageIsOurs($relative) || !is_file(quoteImagePath($relative))) {
        return '';
    }
    $dir = quoteImagePath(QUOTE_IMAGE_DIR);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return '';
    }
    $copy = QUOTE_IMAGE_DIR . '/' . bin2hex(random_bytes(16)) . '.' . pathinfo($relative, PATHINFO_EXTENSION);
    return @copy(quoteImagePath($relative), quoteImagePath($copy)) ? $copy : '';
}

/** Throw one of these pictures away. */
function quoteImageDelete($relative) {
    if (quoteImageIsOurs($relative)) {
        @unlink(quoteImagePath($relative));
    }
}

/*
 * Whether an address is on the public internet - not private, loopback,
 * link-local, reserved, carrier-grade NAT, or IPv6 that reaches an IPv4
 * address through a gateway (NAT64, 6to4, Teredo) or by mapping.
 */
function quoteImageIpIsPublic($ip) {
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    if (defined('FILTER_FLAG_GLOBAL_RANGE')) {
        $flags |= FILTER_FLAG_GLOBAL_RANGE;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (ip2long($ip) & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
        return false;
    }
    if (stripos($ip, '::ffff:') === 0) {
        return quoteImageIpIsPublic(substr($ip, 7));
    }
    $bytes = @inet_pton($ip);
    if ($bytes !== false && strlen($bytes) === 16) {
        $hex = bin2hex($bytes);
        foreach (['0064ff9b0000000000000000', '0064ff9b0001', '2002', '20010000'] as $prefix) {
            if (strpos($hex, $prefix) === 0) {
                return false;
            }
        }
    }
    return true;
}

/*
 * Fetch a web address for a picture: http(s) on the usual ports only, every
 * address the name has checked public and the connection held to the one
 * checked, at most three redirects (each checked again), a size cap and one
 * time limit for it all. [body, content type]. Throws with what to tell the agent.
 */
function quoteImageFetch($url, $deadline) {
    for ($hop = 0; $hop < 4; $hop++) {
        $time_left = intval(floor($deadline - microtime(true)));
        if ($time_left < 2) {
            throw new RuntimeException('The website took too long to answer.');
        }
        $parts = parse_url((string) $url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Use a web address that starts with http:// or https://.');
        }
        $port = intval($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443, 8080, 8443], true)) {
            throw new RuntimeException('That web address uses a port ITFlow won\'t fetch from.');
        }
        $host_plain = trim($host, '[]');
        $ips = filter_var($host_plain, FILTER_VALIDATE_IP) ? [$host_plain] : array_merge(
            (array) @gethostbynamel($host_plain),
            array_column((array) @dns_get_record($host_plain, DNS_AAAA), 'ipv6')
        );
        $ips = array_values(array_filter($ips));
        if (!$ips) {
            throw new RuntimeException("$host couldn't be found.");
        }
        foreach ($ips as $ip) {
            if (!quoteImageIpIsPublic($ip)) {
                throw new RuntimeException('That address is on a private or local network - ITFlow only fetches from public websites.');
            }
        }
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => min(15, $time_left),
            CURLOPT_CONNECTTIMEOUT => min(8, $time_left),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // No proxy from the environment - it would look the name up again itself
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            // To the address checked above - looked up again, the name could point somewhere else
            CURLOPT_RESOLVE => ["$host_plain:$port:" . (strpos($ips[0], ':') !== false ? "[{$ips[0]}]" : $ips[0])],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ITFlow picture fetch)',
            CURLOPT_HTTPHEADER => ['Accept: image/*,text/html;q=0.9,*/*;q=0.5'],
            CURLOPT_ENCODING => '',
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($body) > QUOTE_IMAGE_MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        $type = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $error = curl_error($ch);
        curl_close($ch);
        if (strlen($body) > QUOTE_IMAGE_MAX_BYTES) {
            throw new RuntimeException('That file is bigger than ' . (QUOTE_IMAGE_MAX_BYTES / 1048576) . ' MB.');
        }
        if ($status >= 300 && $status < 400 && $redirect !== '') {
            $url = $redirect;
            continue;
        }
        if ($status !== 200) {
            throw new RuntimeException('The website answered ' . ($status ?: ($error !== '' ? $error : 'nothing')) . '.');
        }
        return [$body, $type];
    }
    throw new RuntimeException('That web address redirects too many times.');
}

/* A link found on a page, made whole against the page's address - '' for anything but http(s). */
function quoteImageResolveUrl($base, $relative) {
    $relative = trim($relative);
    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }
    $b = parse_url($base);
    if (empty($b['scheme']) || empty($b['host']) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $relative)) {
        return '';
    }
    if (strpos($relative, '//') === 0) {
        return $b['scheme'] . ':' . $relative;
    }
    $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (strpos($relative, '/') === 0) {
        return $origin . $relative;
    }
    if (strpos($relative, '?') === 0) {
        return $origin . ($b['path'] ?? '/') . $relative;
    }
    $segments = [];
    foreach (explode('/', preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/') . $relative) as $segment) {
        if ($segment === '..') {
            array_pop($segments);
        } elseif ($segment !== '.') {
            $segments[] = $segment;
        }
    }
    return $origin . '/' . ltrim(implode('/', $segments), '/');
}

/* A picture from a link - the image itself, or a web page's main picture. Its path. Throws with what to tell the agent. */
function quoteImageFromUrl($url) {
    $url = trim((string) $url);
    $deadline = microtime(true) + 25;
    [$body, $type] = quoteImageFetch($url, $deadline);
    if (strpos($type, 'text/html') !== false || preg_match('/^\s*<(!doctype|html)/i', substr($body, 0, 200))) {
        $image_url = '';
        foreach (['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src'] as $name) {
            $quoted = preg_quote($name, '/');
            if (preg_match('/<meta[^>]+(?:property|name)=["\']' . $quoted . '["\'][^>]*content=["\']([^"\']+)["\']/i', $body, $match)
                || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . $quoted . '["\']/i', $body, $match)) {
                $image_url = html_entity_decode($match[1], ENT_QUOTES);
                break;
            }
        }
        if ($image_url === '' && preg_match('/<link[^>]+rel=["\']image_src["\'][^>]*href=["\']([^"\']+)["\']/i', $body, $match)) {
            $image_url = html_entity_decode($match[1], ENT_QUOTES);
        }
        if ($image_url === '') {
            throw new RuntimeException('That page doesn\'t name a main picture - right-click the picture you want, copy its image address and use that.');
        }
        $image_url = quoteImageResolveUrl($url, $image_url);
        if ($image_url === '') {
            throw new RuntimeException('That page\'s main picture isn\'t at a web address - copy the image address instead.');
        }
        [$body] = quoteImageFetch($image_url, $deadline);
    }
    return quoteImageStore($body);
}

/*
 * The picture a form asks for - a new upload in $files[$field], else a link in
 * <field>_url. Its path, or '' for nothing new; $error says why one asked for
 * couldn't be had.
 */
function quoteImageFromForm(array $in, array $files, $field, &$error = '') {
    $error = '';
    try {
        $upload = quoteImageFromUpload($files[$field] ?? null);
        if ($upload !== '') {
            return $upload;
        }
        $url = trim((string) ($in[$field . '_url'] ?? ''));
        return $url !== '' ? quoteImageFromUrl($url) : '';
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return '';
    }
}

/*
 * Save what a form asked for to a row's picture column - a new one (the old
 * one thrown away) or <field>_remove. $table quote_items or products. '' or
 * why the new one couldn't be had (the rest is saved either way).
 */
function quoteImageSave($table, $id, array $in, array $files) {
    global $mysqli;
    if (!quoteImagesReady() || !in_array($table, ['quote_items', 'products'], true)) {
        return '';
    }
    [$column, $key] = $table === 'quote_items' ? ['item_image', 'item_id'] : ['product_image', 'product_id'];
    $id = intval($id);
    $old = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT $column FROM $table WHERE $key = $id"))[0] ?? '');
    $new = quoteImageFromForm($in, $files, $column, $error);
    if ($new !== '') {
        mysqli_query($mysqli, "UPDATE $table SET $column = '" . escapeSql($new) . "' WHERE $key = $id");
        quoteImageDelete($old);
    } elseif (!empty($in[$column . '_remove']) && $old !== '') {
        mysqli_query($mysqli, "UPDATE $table SET $column = NULL WHERE $key = $id");
        quoteImageDelete($old);
    }
    return $error;
}

/* A new quote line from a product gets its own copy of the product's picture. */
function quoteImageFromProduct($item_id, $product_id) {
    global $mysqli;
    if (!quoteImagesReady() || !intval($product_id)) {
        return;
    }
    $copy = quoteImageCopy((string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT product_image FROM products WHERE product_id = " . intval($product_id)))[0] ?? ''));
    if ($copy !== '') {
        mysqli_query($mysqli, "UPDATE quote_items SET item_image = '" . escapeSql($copy) . "' WHERE item_id = " . intval($item_id));
    }
}

/* The SET that gives a copied quote line its own copy of the picture of the one it came from ($item a billingItems() row). */
function quoteImageCopySql(array $item) {
    if (!quoteImagesReady() || empty($item['item_image'])) {
        return '';
    }
    $copy = quoteImageCopy((string) $item['item_image']);
    return $copy !== '' ? ", item_image = '" . escapeSql($copy) . "'" : '';
}

/* Throw away the pictures of these quote lines (WHERE on quote_items) - before they are deleted. */
function quoteImagesDeleteFor($where) {
    global $mysqli;
    if (!quoteImagesReady()) {
        return;
    }
    $sql = mysqli_query($mysqli, "SELECT item_image FROM quote_items WHERE item_image IS NOT NULL AND ($where)");
    while ($row = mysqli_fetch_row($sql)) {
        quoteImageDelete((string) $row[0]);
    }
}

/* A picture as a small square - '' for none. */
function quoteImageThumbHtml($relative, $size = 56) {
    $url = quoteImageUrl($relative);
    return $url !== '' ? '<img src="' . escapeHtml($url) . '" alt="" class="itflow-line-image" style="width:' . intval($size) . 'px;height:' . intval($size) . 'px" loading="lazy">' : '';
}

/*
 * The picture field in a form, as every picture in ITFlow is picked: the
 * picture on a button that opens the Picture window (pictureField(),
 * js/picture_field.js) - drop, paste or choose one, crop it to the product,
 * trim the empty edges, or remove it - and beside it a link to pull one from.
 * $name is the column (item_image, product_image); the window posts it as the
 * file and <name>_remove, the link as <name>_url (quoteImageSave()).
 */
function quoteImageField($name, $current, $help = '') {
    $url = quoteImageUrl((string) $current);
    $empty = picturePhotoEmpty('Add a picture', 'fa-image');
    $shows = $url !== '' ? '<img class="itflow-picture-logo" src="' . escapeHtml($url) . '" alt="">' : $empty;
    ob_start(); ?>
    <div class="col-12" data-quote-image-field>
        <label class="form-label">Picture</label>
        <div class="d-flex gap-3 align-items-start">
            <?= pictureField($name, $url !== '' ? $name . '_remove' : '', $shows, $empty, ['title' => 'Picture', 'shapes' => 'free', 'trim' => true,
                'box' => 'product', 'class' => 'itflow-picture-logo', 'max' => QUOTE_IMAGE_MAX_SIDE]) ?>
            <div class="flex-grow-1 min-w-0">
                <div class="small text-body-secondary mb-2">Click it to add or change it - a JPG, PNG, GIF or WebP, cropped to just the product.</div>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                    <input type="url" class="form-control" name="<?= escapeHtml($name) ?>_url" maxlength="2000" placeholder="Or paste a link - a picture, or the product's web page" data-quote-image-url>
                </div>
                <div class="form-text"><?= $help !== '' ? escapeHtml($help) . ' ' : '' ?>From a web page it takes the page's main picture. ITFlow keeps its own copy.</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
