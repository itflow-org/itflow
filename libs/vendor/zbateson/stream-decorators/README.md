# zbateson/stream-decorators

PSR-7 stream decorators for character set conversion and common mail format content encodings.

[![Build Status](https://github.com/zbateson/stream-decorators/actions/workflows/tests.yml/badge.svg)](https://github.com/zbateson/stream-decorators/actions/workflows/tests.yml)
[![Total Downloads](https://poser.pugx.org/zbateson/stream-decorators/downloads)](//packagist.org/packages/zbateson/stream-decorators)
[![Latest Stable Version](https://poser.pugx.org/zbateson/stream-decorators/v)](//packagist.org/packages/zbateson/stream-decorators)

```
composer require zbateson/stream-decorators
```

## Sponsors

[![SecuMailer](https://mail-mime-parser.org/sponsors/logo-secumailer.png)](https://secumailer.com)

A huge thank you to [all my sponsors](https://github.com/sponsors/zbateson). <3

If this project's helped you, please consider [sponsoring me](https://github.com/sponsors/zbateson).

## Requirements

PHP 8.1 or newer. Tested on PHP 8.1, 8.2, 8.3, 8.4, and 8.5.

## Description

The library provides the following `Psr\Http\Message\StreamInterface` implementations:

* `Base64Stream` - decodes on read and encodes on write to base64
* `CharsetStream` - encodes from `$streamCharset` to `$stringCharset` on read, and vice-versa on write
* `ChunkSplitStream` - splits written characters into lines of `$lineLength` long (stream implementation of PHP's `chunk_split`)
* `DecoratedCachingStream` - a caching stream that writes to a decorated stream, and reads from the cached undecorated stream
* `NonClosingStream` - overrides `close()` and `detach()`, and simply unsets the attached stream without closing it
* `PregReplaceFilterStream` - calls `preg_replace` with passed arguments on every `read()` call
* `QuotedPrintableStream` - decodes on read and encodes on write to quoted-printable
* `SeekingLimitStream` - similar to GuzzleHttp's `LimitStream`, but maintains an internal current read position
* `TellZeroStream` - `tell()` always returns `0` -- used by `DecoratedCachingStream` to wrap a `BufferStream` in a `CachingStream`
* `UUStream` - decodes on read, encodes on write to uu-encoded

## Usage

```php
$stream = GuzzleHttp\Psr7\Utils::streamFor($handle);
$b64Stream = new ZBateson\StreamDecorators\Base64Stream($stream);
$charsetStream = new ZBateson\StreamDecorators\CharsetStream($b64Stream, 'UTF-32', 'UTF-8');

while (($line = GuzzleHttp\Psr7\Utils::readLine()) !== false) {
    echo $line, "\r\n";
}
```

Note that `CharsetStream`, depending on the target encoding, may return multiple bytes when a single 'char' is read. If using PHP's `fread`, this will result in a warning. It is recommended to **not** convert to a stream handle (with `StreamWrapper`) when using `CharsetStream`.

## License

BSD licensed - please see [license agreement](https://github.com/zbateson/stream-decorators/blob/master/LICENSE).
