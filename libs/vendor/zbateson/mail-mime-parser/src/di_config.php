<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

use DI\Definition\Helper\AutowireDefinitionHelper;
use DI\Definition\Reference;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ZBateson\MailMimeParser\Header\Consumer\Received\DomainConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\Received\GenericReceivedConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\ReceivedConsumerService;
use ZBateson\MailMimeParser\Message\Factory\IMessagePartFactory;
use ZBateson\MailMimeParser\Message\Factory\IMimePartFactory;
use ZBateson\MailMimeParser\Message\Factory\IUUEncodedPartFactory;
use ZBateson\MailMimeParser\Message\Factory\PartStreamContainerFactory;
use ZBateson\MailMimeParser\Message\PartStreamContainer;
use ZBateson\MailMimeParser\Parser\HeaderParserService;
use ZBateson\MailMimeParser\Parser\MimeParserService;
use ZBateson\MailMimeParser\Parser\Part\ParserPartStreamContainerFactory;
use ZBateson\MailMimeParser\Parser\Proxy\ParserMessageProxyFactory;
use ZBateson\MailMimeParser\Parser\Proxy\ParserMimePartProxyFactory;
use ZBateson\MailMimeParser\Parser\Proxy\ParserNonMimeMessageProxyFactory;
use ZBateson\MailMimeParser\Parser\Proxy\ParserUUEncodedPartProxyFactory;
use ZBateson\MailMimeParser\Stream\StreamFactory;

return [
    LoggerInterface::class => new AutowireDefinitionHelper(NullLogger::class),

    // only affects reading part content, not for instance decoding mime encoded
    // header parts
    'throwExceptionReadingPartContentFromUnsupportedCharsets' => false,

    // Fallback charset for text/* content parts without a declared charset.
    // Per RFC 2045, the default is ISO-8859-1 but many modern messages omit the
    // charset and are actually UTF-8.  Override this to 'UTF-8' if desired.
    'defaultFallbackCharset' => 'ISO-8859-1',

    // Maximum multipart nesting depth before parsing stops with a recorded error.
    'maxMimePartDepth' => 256,

    // Maximum header count and total header bytes before parsing stops.
    'maxHeaderCount' => 1000,
    'maxHeaderSizeBytes' => 1048576,

    'fromDomainConsumerService' => (new AutowireDefinitionHelper(DomainConsumerService::class))
        ->constructorParameter('partName', 'from'),
    'byDomainConsumerService' => (new AutowireDefinitionHelper(DomainConsumerService::class))
        ->constructorParameter('partName', 'by'),
    'viaGenericReceivedConsumerService' => (new AutowireDefinitionHelper(GenericReceivedConsumerService::class))
        ->constructorParameter('partName', 'via'),
    'withGenericReceivedConsumerService' => (new AutowireDefinitionHelper(GenericReceivedConsumerService::class))
        ->constructorParameter('partName', 'with'),
    'idGenericReceivedConsumerService' => (new AutowireDefinitionHelper(GenericReceivedConsumerService::class))
        ->constructorParameter('partName', 'id'),
    'forGenericReceivedConsumerService' => (new AutowireDefinitionHelper(GenericReceivedConsumerService::class))
        ->constructorParameter('partName', 'for'),
    ReceivedConsumerService::class => (new AutowireDefinitionHelper())
        ->constructor(
            fromDomainConsumerService: new Reference('fromDomainConsumerService'),
            byDomainConsumerService: new Reference('byDomainConsumerService'),
            viaGenericReceivedConsumerService: new Reference('viaGenericReceivedConsumerService'),
            withGenericReceivedConsumerService: new Reference('withGenericReceivedConsumerService'),
            idGenericReceivedConsumerService: new Reference('idGenericReceivedConsumerService'),
            forGenericReceivedConsumerService: new Reference('forGenericReceivedConsumerService')
        ),
    PartStreamContainer::class => (new AutowireDefinitionHelper())
        ->constructor(
            throwExceptionReadingPartContentFromUnsupportedCharsets: new Reference('throwExceptionReadingPartContentFromUnsupportedCharsets')
        ),
    PartStreamContainerFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            throwExceptionReadingPartContentFromUnsupportedCharsets: new Reference('throwExceptionReadingPartContentFromUnsupportedCharsets')
        ),
    ParserPartStreamContainerFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            throwExceptionReadingPartContentFromUnsupportedCharsets: new Reference('throwExceptionReadingPartContentFromUnsupportedCharsets')
        ),
    StreamFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            throwExceptionReadingPartContentFromUnsupportedCharsets: new Reference('throwExceptionReadingPartContentFromUnsupportedCharsets')
        ),
    IMessagePartFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            defaultFallbackCharset: new Reference('defaultFallbackCharset')
        ),
    IMimePartFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            defaultFallbackCharset: new Reference('defaultFallbackCharset')
        ),
    IUUEncodedPartFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            defaultFallbackCharset: new Reference('defaultFallbackCharset')
        ),
    ParserMimePartProxyFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            defaultFallbackCharset: new Reference('defaultFallbackCharset')
        ),
    ParserUUEncodedPartProxyFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            defaultFallbackCharset: new Reference('defaultFallbackCharset')
        ),
    ParserMessageProxyFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            defaultFallbackCharset: new Reference('defaultFallbackCharset')
        ),
    ParserNonMimeMessageProxyFactory::class => (new AutowireDefinitionHelper())
        ->constructor(
            defaultFallbackCharset: new Reference('defaultFallbackCharset')
        ),
    HeaderParserService::class => (new AutowireDefinitionHelper())
        ->constructor(
            maxHeaderCount: new Reference('maxHeaderCount'),
            maxHeaderSizeBytes: new Reference('maxHeaderSizeBytes')
        ),
    MimeParserService::class => (new AutowireDefinitionHelper())
        ->constructor(
            maxMimePartDepth: new Reference('maxMimePartDepth')
        ),
];
