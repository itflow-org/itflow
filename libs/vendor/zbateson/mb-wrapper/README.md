# zbateson/mb-wrapper

Charset conversion and string manipulation wrapper with a large defined set of aliases.

[![Build Status](https://github.com/zbateson/mb-wrapper/actions/workflows/tests.yml/badge.svg)](https://github.com/zbateson/mb-wrapper/actions/workflows/tests.yml)
[![Total Downloads](https://poser.pugx.org/zbateson/mb-wrapper/downloads)](https://packagist.org/packages/zbateson/mb-wrapper)
[![Latest Stable Version](https://poser.pugx.org/zbateson/mb-wrapper/version)](https://packagist.org/packages/zbateson/mb-wrapper)

```
composer require zbateson/mb-wrapper
```

## Sponsors

[![SecuMailer](https://mail-mime-parser.org/sponsors/logo-secumailer.png)](https://secumailer.com)

A huge thank you to [all my sponsors](https://github.com/sponsors/zbateson). <3

If this project's helped you, please consider [sponsoring me](https://github.com/sponsors/zbateson).

## Requirements

PHP 8.1 or newer. Tested on PHP 8.1, 8.2, 8.3, 8.4, and 8.5.

## Description

MbWrapper is intended for use wherever `mb_*` or `iconv_*` is used. It scans supported charsets returned by `mb_list_encodings()`, and prefers `mb_*` functions, but will fallback to `iconv` if a charset isn't supported by the `mb_*` functions.

A list of aliased charsets is maintained for both `mb_*` and `iconv`, where a supported charset exists for an alias. This is useful for mail and http parsing as other systems may report encodings not recognized by `mb_*` or `iconv`.

Charset lookup is done by removing non-alphanumeric characters as well, so `UTF8` will always be matched to `UTF-8`, etc.

## Usage

The following wrapper methods are exposed:
* `mb_convert_encoding`, `iconv` with `MbWrapper::convert`
* `mb_substr`, `iconv_substr` with `MbWrapper::getSubstr`
* `mb_strlen`, `iconv_strlen` with `MbWrapper::getLength`
* `mb_check_encoding`, `iconv` (for verification) with `MbWrapper::checkEncoding`

```php
$mbWrapper = new \ZBateson\MbWrapper\MbWrapper();
$fromCharset = 'ISO-8859-1';
$toCharset = 'UTF-8';

$mbWrapper->convert('data', $fromCharset, $toCharset);
$mbWrapper->getLength('data', 'UTF-8');
$mbWrapper->substr('data', 'UTF-8', 1, 2);

if ($mbWrapper->checkEncoding('data', 'UTF-8')) {
    echo 'Compatible';
}
```

## License

BSD licensed - please see [license agreement](https://github.com/zbateson/mb-wrapper/blob/master/LICENSE).
