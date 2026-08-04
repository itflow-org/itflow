<?php

/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser;

use Composer\InstalledVersions;
use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Source\DefinitionSource;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Parser\MessageParserService;

/**
 * Parses a MIME message into an {@see IMessage} object.
 *
 * The class sets up the dependency injection container (using PHP-DI) with the
 * ability to override and/or provide specialized classes.  To override you can:
 *
 *  - Provide an array|string|DefinitionSource to the constructor to affect
 *    classes used on a single instance of MailMimeParser
 *  - Call MailMimeParser::addGlobalContainerDefinition with an
 *    array|string|DefinitionSource to to override it globally on all instances
 *    of MailMimeParser
 *  - Call MailMimeParser::getGlobalContainer(), and use set() to override
 *    individual definitions globally.
 *
 * You may also provide a LoggerInterface on the constructor for a single
 * instance, or override it globally by calling setGlobalLogger.  This is the
 * same as setting up Psr\Log\LoggerInterface with your logger class in a Php-Di
 * configuration in one of the above methods.
 *
 * To invoke the parser, call `parse` on a MailMimeParser object.
 *
 * ```php
 * $parser = new MailMimeParser();
 * // the resource is attached due to the second parameter being true and will
 * // be closed when the returned IMessage is destroyed
 * $message = $parser->parse(fopen('path/to/file.txt'), true);
 * // use $message here
 * ```
 *
 * @author Zaahid Bateson
 */
class MailMimeParser
{
    /**
     * @var string the default charset used to encode strings (or string content
     *      like streams) returned by MailMimeParser (for e.g. the string
     *      returned by calling $message->getTextContent()).
     */
    public const DEFAULT_CHARSET = 'UTF-8';

    /**
     * @var string the default definition file.
     */
    private const DEFAULT_DEFINITIONS_FILE = __DIR__ . '/di_config.php';

    /**
     * The key in a package's composer.json "extra" section that MMP looks
     * for to auto-discover plugin DI configurations.
     */
    private const PLUGIN_EXTRA_KEY = 'mail-mime-parser';

    /**
     * @var Container The instance's dependency injection container.
     */
    protected Container $container;

    /**
     * @var MessageParserService for parsing messages
     */
    protected MessageParserService $messageParser;

    /**
     * @var Container The static global container
     */
    private static ?Container $globalContainer = null;

    /**
     * @var array<array<string, mixed>|string|DefinitionSource> an array of global definitions
     *      being used.
     */
    private static array $globalDefinitions = [self::DEFAULT_DEFINITIONS_FILE];

    /**
     * Returns the default ContainerBuilder with default loaded definitions.
     *
     * @return ContainerBuilder<Container>
     */
    private static function getGlobalContainerBuilder() : ContainerBuilder
    {
        $builder = new ContainerBuilder();
        foreach (self::$globalDefinitions as $def) {
            $builder->addDefinitions($def);
        }
        foreach (self::discoverPluginConfigs() as $configFile) {
            $builder->addDefinitions($configFile);
        }
        return $builder;
    }

    /**
     * Discovers plugin DI config files from installed Composer packages.
     *
     * Uses Composer's InstalledVersions runtime API to enumerate installed
     * packages and reads each package's composer.json for the
     * "extra.mail-mime-parser.di_config" entry.
     *
     * Returns an empty array in non-Composer environments.
     *
     * @return string[] Absolute paths to discovered config files
     */
    private static function discoverPluginConfigs() : array
    {
        if (!\class_exists(InstalledVersions::class)) {
            return [];
        }

        $configs = [];
        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            $installPath = InstalledVersions::getInstallPath($packageName);
            if ($installPath === null) {
                continue;
            }
            $configFile = self::readPluginConfigPath($installPath);
            if ($configFile !== null) {
                $configs[] = $configFile;
            }
        }
        return $configs;
    }

    /**
     * Reads a package's composer.json and returns the absolute path to its
     * MMP DI config file, or null if the package is not an MMP plugin.
     *
     * Looks for "extra.mail-mime-parser.di_config" in the package's
     * composer.json.
     */
    public static function readPluginConfigPath(string $packageInstallPath) : ?string
    {
        $composerJsonPath = $packageInstallPath . '/composer.json';
        if (!\file_exists($composerJsonPath)) {
            return null;
        }
        $data = \json_decode(\file_get_contents($composerJsonPath), true);
        if (!\is_array($data)) {
            return null;
        }
        $extra = $data['extra'][self::PLUGIN_EXTRA_KEY] ?? null;
        if (!\is_array($extra) || !isset($extra['di_config'])) {
            return null;
        }
        $configFile = \realpath($packageInstallPath . '/' . $extra['di_config']);
        if ($configFile === false || !\file_exists($configFile)) {
            return null;
        }
        return $configFile;
    }

    /**
     * Sets global configuration for php-di.  Overrides all previously set
     * definitions.  You can optionally not use the default MMP definitions file
     * by passing 'false' to the $useDefaultDefinitionsFile argument.
     *
     * @param array<array<string, mixed>|string|DefinitionSource> $phpDiConfigs array of definitions
     */
    public static function setGlobalPhpDiConfigurations(array $phpDiConfigs, bool $useDefaultDefinitionsFile = true) : void
    {
        self::$globalDefinitions = \array_merge(
            ($useDefaultDefinitionsFile) ? [self::DEFAULT_DEFINITIONS_FILE] : [],
            $phpDiConfigs
        );
        self::$globalContainer = null;
    }

    /**
     * @param array<string, mixed>|string|DefinitionSource $phpDiConfig
     */
    public static function addGlobalPhpDiContainerDefinition(array|string|DefinitionSource $phpDiConfig) : void
    {
        self::$globalDefinitions[] = $phpDiConfig;
        self::$globalContainer = null;
    }

    public static function resetGlobalPhpDiContainerDefinitions() : void
    {
        self::$globalDefinitions = [self::DEFAULT_DEFINITIONS_FILE];
        self::$globalContainer = null;
    }

    /**
     * Returns the global php-di container instance.
     *
     */
    public static function getGlobalContainer() : Container
    {
        if (self::$globalContainer === null) {
            $builder = self::getGlobalContainerBuilder();
            self::$globalContainer = $builder->build();
        }
        return self::$globalContainer;
    }

    /**
     * Sets the fallback charset used for text/* content parts that don't
     * declare a charset.  Defaults to 'ISO-8859-1' per RFC 2045.
     *
     * Many modern messages omit the charset and are actually UTF-8, so you
     * may want to set this to 'UTF-8'.
     */
    public static function setFallbackCharset(string $charset) : void
    {
        self::$globalDefinitions[] = ['defaultFallbackCharset' => $charset];
        self::$globalContainer = null;
    }

    /**
     * Registers the provided logger globally.
     */
    public static function setGlobalLogger(LoggerInterface $logger) : void
    {
        self::$globalDefinitions[] = [LoggerInterface::class => $logger];
        self::$globalContainer = null;
    }

    /**
     * Provide custom php-di configuration to customize dependency injection, or
     * provide a custom logger for the instance only.
     *
     * Note: this only affects instances created through this instance of the
     * MailMimeParser, or the container itself.  Calling 'new MimePart()'
     * directly for instance, would use the global service locator to setup any
     * dependencies MimePart needs.  This applies to a provided $logger too --
     * it would only affect instances of objects created through the provided
     * MailMimeParser.
     *
     * Passing false to $useGlobalDefinitions will cause MMP to not use any
     * global definitions.  The default definitions file
     * MailMimeParser::DEFAULT_DEFINITIONS_FILE will still be added though.
     *
     * @see MailMimeParser::setGlobalPhpDiConfiguration() to register
     *      configuration globally.
     * @see MailMimeParser::setGlobalLogger() to set a global logger
     *
     * @param array<string, mixed>|string|DefinitionSource|null $phpDiContainerConfig
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        array|string|DefinitionSource|null $phpDiContainerConfig = null,
        bool $useGlobalDefinitions = true
    ) {
        if ($phpDiContainerConfig !== null || $logger !== null) {
            if ($useGlobalDefinitions) {
                $builder = self::getGlobalContainerBuilder();
            } else {
                $builder = new ContainerBuilder();
                $builder->addDefinitions(self::DEFAULT_DEFINITIONS_FILE);
            }
            if ($phpDiContainerConfig !== null) {
                $builder->addDefinitions($phpDiContainerConfig);
            }
            if ($logger !== null) {
                $builder->addDefinitions([LoggerInterface::class => $logger]);
            }
            $this->container = $builder->build();
        } else {
            $this->container = self::getGlobalContainer();
        }
        $this->messageParser = $this->container->get(MessageParserService::class);
    }

    /**
     * Parses the passed stream handle or string into an {@see IMessage} object
     * and returns it.
     *
     * If the passed $resource is a resource handle or StreamInterface, the
     * resource must remain open while the returned IMessage object exists.
     * Pass true as the second argument to have the resource automatically
     * closed when the returned IMessage is destroyed, or pass false to
     * manage the resource lifecycle yourself.
     *
     * @param resource|StreamInterface|string $resource The resource handle to
     *        the input stream of the mime message, or a string containing a
     *        mime message.
     * @param bool $autoClose pass true to have the resource closed
     *        automatically when the returned IMessage is destroyed.
     */
    public function parse(mixed $resource, bool $autoClose) : IMessage
    {
        $stream = Utils::streamFor(
            $resource,
            ['metadata' => ['mmp-detached-stream' => ($autoClose !== true)]]
        );
        if (!$stream->isSeekable()) {
            $stream = new CachingStream($stream);
        }
        return $this->messageParser->parse($stream);
    }
}
