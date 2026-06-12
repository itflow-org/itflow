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
use ZBateson\MailMimeParser\Message\Factory\PartStreamContainerFactory;
use ZBateson\MailMimeParser\Message\PartStreamContainer;
use ZBateson\MailMimeParser\Parser\Part\ParserPartStreamContainerFactory;
use ZBateson\MailMimeParser\Stream\StreamFactory;

return [
    LoggerInterface::class => new AutowireDefinitionHelper(NullLogger::class),

    // only affects reading part content, not for instance decoding mime encoded
    // header parts
    'throwExceptionReadingPartContentFromUnsupportedCharsets' => false,

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
];
