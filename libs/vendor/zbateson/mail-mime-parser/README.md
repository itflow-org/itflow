# zbateson/mail-mime-parser

Testable and PSR-compliant mail mime parser alternative to PHP's imap* functions and Pear libraries for reading messages in _Internet Message Format_ [RFC 822](http://tools.ietf.org/html/rfc822) (and later revisions [RFC 2822](http://tools.ietf.org/html/rfc2822), [RFC 5322](http://tools.ietf.org/html/rfc5322)).

[![Build Status](https://github.com/zbateson/mail-mime-parser/actions/workflows/tests.yml/badge.svg)](https://github.com/zbateson/mail-mime-parser/actions/workflows/tests.yml)
[![Total Downloads](https://poser.pugx.org/zbateson/mail-mime-parser/downloads)](//packagist.org/packages/zbateson/mail-mime-parser)
[![Latest Stable Version](https://poser.pugx.org/zbateson/mail-mime-parser/v)](//packagist.org/packages/zbateson/mail-mime-parser)

The goals of this project are to be:

* Well written
* Standards-compliant but forgiving
* Tested where possible

To include it for use in your project, install it via composer:

```
composer require zbateson/mail-mime-parser
```

## Sponsors

[![SecuMailer](https://mail-mime-parser.org/sponsors/logo-secumailer.png)](https://secumailer.com)

A huge thank you to [all my sponsors](https://github.com/sponsors/zbateson). <3

If this project's helped you, please consider [sponsoring me](https://github.com/sponsors/zbateson).

## New in 4.0

Version 4.0 requires PHP 8.1+ and focuses on API cleanup and improved configurability.  For details, see the [4.0 Upgrade Guide](https://mail-mime-parser.org/upgrade-4.0).

## Requirements

MailMimeParser requires PHP 8.1 or newer.  Tested on PHP 8.1, 8.2, 8.3, 8.4 and 8.5.

## Usage

```php
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Header\HeaderConsts;

// use an instance of MailMimeParser as a class dependency
$mailParser = new MailMimeParser();

// parse() accepts a string, resource or Psr7 StreamInterface
// pass `true` as the second argument to attach the passed $handle and close
// it when the returned IMessage is destroyed.
$handle = fopen('file.mime', 'r');
$message = $mailParser->parse($handle, false);         // returns `IMessage`

// OR: use this procedurally (Message::from also accepts a string,
// resource or Psr7 StreamInterface
// true or false as second parameter doesn't matter if passing a string.
$string = "Content-Type: text/plain\r\nSubject: Test\r\n\r\nMessage";
$message = Message::from($string, false);

echo $message->getHeaderValue(HeaderConsts::FROM);     // user@example.com
echo $message
    ->getHeader(HeaderConsts::FROM)                    // AddressHeader
    ->getPersonName();                                 // Person Name
echo $message->getSubject();                           // The email's subject
echo $message
    ->getHeader(HeaderConsts::TO)                      // also AddressHeader
    ->getAddresses()[0]                                // AddressPart
    ->getPersonName();                                 // Person Name
echo $message
    ->getHeader(HeaderConsts::CC)                      // also AddressHeader
    ->getAddresses()[0]                                // AddressPart
    ->getEmail();                                      // user@example.com

echo $message->getTextContent();                       // or getHtmlContent()

echo $message->getHeader('X-Foo');                     // for custom or undocumented headers

$att = $message->getAttachmentPart(0);                 // first attachment
echo $att->getHeaderValue(HeaderConsts::CONTENT_TYPE); // e.g. "text/plain"
echo $att->getHeaderParameter(                         // value of "charset" part
    HeaderConsts::CONTENT_TYPE,
    'charset'
);
echo $att->getContent();                               // get the attached file's contents
$stream = $att->getContentStream();                    // the file is decoded automatically
$dest = \GuzzleHttp\Psr7\stream_for(
    fopen('my-file.ext')
);
\GuzzleHttp\Psr7\copy_to_stream(
    $stream, $dest
);
// OR: more simply if saving or copying to another stream
$att->saveContent('my-file.ext');               // writes to my-file.ext
$att->saveContent($stream);                     // copies to the stream

// close only when $message is no longer being used.
fclose($handle);

```

## Encryption and Signing Plugins

Optional companion packages add S/MIME and PGP/MIME support for decrypting,
encrypting, signing and verifying messages:

* [zbateson/mmp-crypt-smime](https://github.com/zbateson/mmp-crypt-smime) -- S/MIME via PHP's OpenSSL extension
* [zbateson/mmp-crypt-gpg](https://github.com/zbateson/mmp-crypt-gpg) -- PGP/MIME via PEAR's Crypt_GPG

Install either package and encrypted/signed messages are automatically detected
and decrypted during parsing. See the [Usage Guide](https://mail-mime-parser.org/#encryption-and-signing)
for examples of reading encrypted messages and composing signed/encrypted ones.

## Documentation

* [Usage Guide](https://mail-mime-parser.org/)
* [API Reference](https://mail-mime-parser.org/api/4.0)

## Upgrade guides

* [1.x Upgrade Guide](https://mail-mime-parser.org/upgrade-1.0)
* [2.x Upgrade Guide](https://mail-mime-parser.org/upgrade-2.0)
* [3.x Upgrade Guide](https://mail-mime-parser.org/upgrade-3.0)
* [4.x Upgrade Guide](https://mail-mime-parser.org/upgrade-4.0)

## License

BSD licensed - please see [license agreement](https://github.com/zbateson/mail-mime-parser/blob/master/LICENSE).
