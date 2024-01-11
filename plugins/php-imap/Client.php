<?php
/*
* File:     Client.php
* Category: -
* Author:   M. Goldenbaum
* Created:  19.01.17 22:21
* Updated:  -
*
* Description:
*  -
*/

namespace Webklex\PHPIMAP;

use ErrorException;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Connection\Protocols\LegacyProtocol;
use Webklex\PHPIMAP\Connection\Protocols\ProtocolInterface;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\EventNotFoundException;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;
use Webklex\PHPIMAP\Exceptions\ProtocolNotSupportedException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Support\FolderCollection;
use Webklex\PHPIMAP\Support\Masks\AttachmentMask;
use Webklex\PHPIMAP\Support\Masks\MessageMask;
use Webklex\PHPIMAP\Traits\HasEvents;

/**
 * Class Client
 *
 * @package Webklex\PHPIMAP
 */
class Client {
    use HasEvents;

    /**
     * Connection resource
     *
     * @var ?ProtocolInterface
     */
    public ?ProtocolInterface $connection = null;

    /**
     * Server hostname.
     *
     * @var string
     */
    public string $host;

    /**
     * Server port.
     *
     * @var int
     */
    public int $port;

    /**
     * Service protocol.
     *
     * @var string
     */
    public string $protocol;

    /**
     * Server encryption.
     * Supported: none, ssl, tls, starttls or notls.
     *
     * @var string
     */
    public string $encryption;

    /**
     * If server has to validate cert.
     *
     * @var bool
     */
    public bool $validate_cert = true;

    /**
     * Proxy settings
     * @var array
     */
    protected array $proxy = [
        'socket' => null,
        'request_fulluri' => false,
        'username' => null,
        'password' => null,
    ];

    /**
     * Connection timeout
     * @var int $timeout
     */
    public int $timeout;

    /**
     * Account username
     *
     * @var string
     */
    public string $username;

    /**
     * Account password.
     *
     * @var string
     */
    public string $password;

    /**
     * Additional data fetched from the server.
     *
     * @var array
     */
    public array $extensions;

    /**
     * Account authentication method.
     *
     * @var ?string
     */
    public ?string $authentication;

    /**
     * Active folder path.
     *
     * @var ?string
     */
    protected ?string $active_folder = null;

    /**
     * Default message mask
     *
     * @var string $default_message_mask
     */
    protected string $default_message_mask = MessageMask::class;

    /**
     * Default attachment mask
     *
     * @var string $default_attachment_mask
     */
    protected string $default_attachment_mask = AttachmentMask::class;

    /**
     * Used default account values
     *
     * @var array $default_account_config
     */
    protected array $default_account_config = [
        'host' => 'localhost',
        'port' => 993,
        'protocol'  => 'imap',
        'encryption' => 'ssl',
        'validate_cert' => true,
        'username' => '',
        'password' => '',
        'authentication' => null,
        "extensions" => [],
        'proxy' => [
            'socket' => null,
            'request_fulluri' => false,
            'username' => null,
            'password' => null,
        ],
        "timeout" => 30
    ];

    /**
     * Client constructor.
     * @param array $config
     *
     * @throws MaskNotFoundException
     */
    public function __construct(array $config = []) {
        $this->setConfig($config);
        $this->setMaskFromConfig($config);
        $this->setEventsFromConfig($config);
    }

    /**
     * Client destructor
     *
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Clone the current Client instance
     *
     * @return Client
     */
    public function clone(): Client {
        $client = new self();
        $client->events = $this->events;
        $client->timeout = $this->timeout;
        $client->active_folder = $this->active_folder;
        $client->default_account_config = $this->default_account_config;
        $config = $this->getAccountConfig();
        foreach($config as $key => $value) {
            $client->setAccountConfig($key, $config, $this->default_account_config);
        }
        $client->default_message_mask = $this->default_message_mask;
        $client->default_attachment_mask = $this->default_message_mask;
        return $client;
    }

    /**
     * Set the Client configuration
     * @param array $config
     *
     * @return self
     */
    public function setConfig(array $config): Client {
        $default_account = ClientManager::get('default');
        $default_config  = ClientManager::get("accounts.$default_account");

        foreach ($this->default_account_config as $key => $value) {
            $this->setAccountConfig($key, $config, $default_config);
        }

        return $this;
    }

    /**
     * Get the current config
     *
     * @return array
     */
    public function getConfig(): array {
        $config = [];
        foreach($this->default_account_config as $key => $value) {
            $config[$key] = $this->$key;
        }
        return $config;
    }

    /**
     * Set a specific account config
     * @param string $key
     * @param array $config
     * @param array $default_config
     */
    private function setAccountConfig(string $key, array $config, array $default_config): void {
        $value = $this->default_account_config[$key];
        if(isset($config[$key])) {
            $value = $config[$key];
        }elseif(isset($default_config[$key])) {
            $value = $default_config[$key];
        }
        $this->$key = $value;
    }

    /**
     * Get the current account config
     *
     * @return array
     */
    public function getAccountConfig(): array {
        $config = [];
        foreach($this->default_account_config as $key => $value) {
            if(property_exists($this, $key)) {
                $config[$key] = $this->$key;
            }
        }
        return $config;
    }

    /**
     * Look for a possible events in any available config
     * @param $config
     */
    protected function setEventsFromConfig($config): void {
        $this->events = ClientManager::get("events");
        if(isset($config['events'])){
            foreach($config['events'] as $section => $events) {
                $this->events[$section] = array_merge($this->events[$section], $events);
            }
        }
    }

    /**
     * Look for a possible mask in any available config
     * @param $config
     *
     * @throws MaskNotFoundException
     */
    protected function setMaskFromConfig($config): void {

        if(isset($config['masks'])){
            if(isset($config['masks']['message'])) {
                if(class_exists($config['masks']['message'])) {
                    $this->default_message_mask = $config['masks']['message'];
                }else{
                    throw new MaskNotFoundException("Unknown mask provided: ".$config['masks']['message']);
                }
            }else{
                $default_mask  = ClientManager::getMask("message");
                if($default_mask != ""){
                    $this->default_message_mask = $default_mask;
                }else{
                    throw new MaskNotFoundException("Unknown message mask provided");
                }
            }
            if(isset($config['masks']['attachment'])) {
                if(class_exists($config['masks']['attachment'])) {
                    $this->default_attachment_mask = $config['masks']['attachment'];
                }else{
                    throw new MaskNotFoundException("Unknown mask provided: ". $config['masks']['attachment']);
                }
            }else{
                $default_mask  = ClientManager::getMask("attachment");
                if($default_mask != ""){
                    $this->default_attachment_mask = $default_mask;
                }else{
                    throw new MaskNotFoundException("Unknown attachment mask provided");
                }
            }
        }else{
            $default_mask  = ClientManager::getMask("message");
            if($default_mask != ""){
                $this->default_message_mask = $default_mask;
            }else{
                throw new MaskNotFoundException("Unknown message mask provided");
            }

            $default_mask  = ClientManager::getMask("attachment");
            if($default_mask != ""){
                $this->default_attachment_mask = $default_mask;
            }else{
                throw new MaskNotFoundException("Unknown attachment mask provided");
            }
        }
    }

    /**
     * Get the current imap resource
     *
     * @return ProtocolInterface
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function getConnection(): ProtocolInterface {
        $this->checkConnection();
        return $this->connection;
    }

    /**
     * Determine if connection was established.
     *
     * @return bool
     */
    public function isConnected(): bool {
        return $this->connection && $this->connection->connected();
    }

    /**
     * Determine if connection was established and connect if not.
     * Returns true if the connection was closed and has been reopened.
     *
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function checkConnection(): bool {
        try {
            if (!$this->isConnected()) {
                $this->connect();
                return true;
            }
        } catch (\Throwable) {
            $this->connect();
        }
        return false;
    }

    /**
     * Force the connection to reconnect
     *
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function reconnect(): void {
        if ($this->isConnected()) {
            $this->disconnect();
        }
        $this->connect();
    }

    /**
     * Connect to server.
     *
     * @return $this
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function connect(): Client {
        $this->disconnect();
        $protocol = strtolower($this->protocol);

        if (in_array($protocol, ['imap', 'imap4', 'imap4rev1'])) {
            $this->connection = new ImapProtocol($this->validate_cert, $this->encryption);
            $this->connection->setConnectionTimeout($this->timeout);
            $this->connection->setProxy($this->proxy);
        }else{
            if (extension_loaded('imap') === false) {
                throw new ConnectionFailedException("connection setup failed", 0, new ProtocolNotSupportedException($protocol." is an unsupported protocol"));
            }
            $this->connection = new LegacyProtocol($this->validate_cert, $this->encryption);
            if (str_starts_with($protocol, "legacy-")) {
                $protocol = substr($protocol, 7);
            }
            $this->connection->setProtocol($protocol);
        }

        if (ClientManager::get('options.debug')) {
            $this->connection->enableDebug();
        }

        if (!ClientManager::get('options.uid_cache')) {
            $this->connection->disableUidCache();
        }

        try {
            $this->connection->connect($this->host, $this->port);
        } catch (ErrorException|RuntimeException $e) {
            throw new ConnectionFailedException("connection setup failed", 0, $e);
        }
        $this->authenticate();

        return $this;
    }

    /**
     * Authenticate the current session
     *
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws ResponseException
     */
    protected function authenticate(): void {
        if ($this->authentication == "oauth") {
            if (!$this->connection->authenticate($this->username, $this->password)->validatedData()) {
                throw new AuthFailedException();
            }
        } elseif (!$this->connection->login($this->username, $this->password)->validatedData()) {
            throw new AuthFailedException();
        }
    }

    /**
     * Disconnect from server.
     *
     * @return $this
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     */
    public function disconnect(): Client {
        if ($this->isConnected()) {
            $this->connection->logout();
        }
        $this->active_folder = null;

        return $this;
    }

    /**
     * Get a folder instance by a folder name
     * @param string $folder_name
     * @param string|null $delimiter
     * @param bool $utf7
     * @return Folder|null
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function getFolder(string $folder_name, ?string $delimiter = null, bool $utf7 = false): ?Folder {
        // Set delimiter to false to force selection via getFolderByName (maybe useful for uncommon folder names)
        $delimiter = is_null($delimiter) ? ClientManager::get('options.delimiter', "/") : $delimiter;

        if (str_contains($folder_name, (string)$delimiter)) {
            return $this->getFolderByPath($folder_name, $utf7);
        }

        return $this->getFolderByName($folder_name);
    }

    /**
     * Get a folder instance by a folder name
     * @param $folder_name
     * @param bool $soft_fail If true, it will return null instead of throwing an exception
     *
     * @return Folder|null
     * @throws FolderFetchingException
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function getFolderByName($folder_name, bool $soft_fail = false): ?Folder {
        return $this->getFolders(false, null, $soft_fail)->where("name", $folder_name)->first();
    }

    /**
     * Get a folder instance by a folder path
     * @param $folder_path
     * @param bool $utf7
     * @param bool $soft_fail If true, it will return null instead of throwing an exception
     *
     * @return Folder|null
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function getFolderByPath($folder_path, bool $utf7 = false, bool $soft_fail = false): ?Folder {
        if (!$utf7) $folder_path = EncodingAliases::convert($folder_path, "utf-8", "utf7-imap");
        return $this->getFolders(false, null, $soft_fail)->where("path", $folder_path)->first();
    }

    /**
     * Get folders list.
     * If hierarchical order is set to true, it will make a tree of folders, otherwise it will return flat array.
     *
     * @param boolean $hierarchical
     * @param string|null $parent_folder
     * @param bool $soft_fail If true, it will return an empty collection instead of throwing an exception
     *
     * @return FolderCollection
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function getFolders(bool $hierarchical = true, string $parent_folder = null, bool $soft_fail = false): FolderCollection {
        $this->checkConnection();
        $folders = FolderCollection::make([]);

        $pattern = $parent_folder.($hierarchical ? '%' : '*');
        $items = $this->connection->folders('', $pattern)->validatedData();

        if(!empty($items)){
            foreach ($items as $folder_name => $item) {
                $folder = new Folder($this, $folder_name, $item["delimiter"], $item["flags"]);

                if ($hierarchical && $folder->hasChildren()) {
                    $pattern = $folder->full_name.$folder->delimiter.'%';

                    $children = $this->getFolders(true, $pattern, $soft_fail);
                    $folder->setChildren($children);
                }

                $folders->push($folder);
            }

            return $folders;
        }else if (!$soft_fail){
            throw new FolderFetchingException("failed to fetch any folders");
        }

        return $folders;
    }

    /**
     * Get folders list.
     * If hierarchical order is set to true, it will make a tree of folders, otherwise it will return flat array.
     *
     * @param boolean $hierarchical
     * @param string|null $parent_folder
     * @param bool $soft_fail If true, it will return an empty collection instead of throwing an exception
     *
     * @return FolderCollection
     * @throws FolderFetchingException
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function getFoldersWithStatus(bool $hierarchical = true, string $parent_folder = null, bool $soft_fail = false): FolderCollection {
        $this->checkConnection();
        $folders = FolderCollection::make([]);

        $pattern = $parent_folder.($hierarchical ? '%' : '*');
        $items = $this->connection->folders('', $pattern)->validatedData();

        if(!empty($items)){
            foreach ($items as $folder_name => $item) {
                $folder = new Folder($this, $folder_name, $item["delimiter"], $item["flags"]);

                if ($hierarchical && $folder->hasChildren()) {
                    $pattern = $folder->full_name.$folder->delimiter.'%';

                    $children = $this->getFoldersWithStatus(true, $pattern, $soft_fail);
                    $folder->setChildren($children);
                }

                $folder->loadStatus();
                $folders->push($folder);
            }

            return $folders;
        }else if (!$soft_fail){
            throw new FolderFetchingException("failed to fetch any folders");
        }

        return $folders;
    }

    /**
     * Open a given folder.
     * @param string $folder_path
     * @param boolean $force_select
     *
     * @return array
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function openFolder(string $folder_path, bool $force_select = false): array {
        if ($this->active_folder == $folder_path && $this->isConnected() && $force_select === false) {
            return [];
        }
        $this->checkConnection();
        $this->active_folder = $folder_path;
        return $this->connection->selectFolder($folder_path)->validatedData();
    }

    /**
     * Set active folder
     * @param string|null $folder_path
     *
     * @return void
     */
    public function setActiveFolder(?string $folder_path = null): void {
        $this->active_folder = $folder_path;
    }

    /**
     * Get active folder
     *
     * @return string|null
     */
    public function getActiveFolder(): ?string {
        return $this->active_folder;
    }

    /**
     * Create a new Folder
     * @param string $folder_path
     * @param boolean $expunge
     * @param bool $utf7
     * @return Folder
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws ResponseException
     * @throws RuntimeException
     */
    public function createFolder(string $folder_path, bool $expunge = true, bool $utf7 = false): Folder {
        $this->checkConnection();

        if (!$utf7) $folder_path = EncodingAliases::convert($folder_path, "utf-8", "UTF7-IMAP");

        $status = $this->connection->createFolder($folder_path)->validatedData();

        if($expunge) $this->expunge();

        $folder = $this->getFolderByPath($folder_path, true);
        if($status && $folder) {
            $event = $this->getEvent("folder", "new");
            $event::dispatch($folder);
        }

        return $folder;
    }

    /**
     * Delete a given folder
     * @param string $folder_path
     * @param boolean $expunge
     *
     * @return array
     * @throws AuthFailedException
     * @throws ConnectionFailedException
     * @throws EventNotFoundException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function deleteFolder(string $folder_path, bool $expunge = true): array {
        $this->checkConnection();

        $folder = $this->getFolderByPath($folder_path);
        if ($this->active_folder == $folder->path){
            $this->active_folder = null;
        }
        $status = $this->getConnection()->deleteFolder($folder->path)->validatedData();
        if ($expunge) $this->expunge();

        $event = $this->getEvent("folder", "deleted");
        $event::dispatch($folder);

        return $status;
    }

    /**
     * Check a given folder
     * @param string $folder_path
     *
     * @return array
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function checkFolder(string $folder_path): array {
        $this->checkConnection();
        return $this->connection->examineFolder($folder_path)->validatedData();
    }

    /**
     * Get the current active folder
     *
     * @return string
     */
    public function getFolderPath(): string {
        return $this->active_folder;
    }

    /**
     * Exchange identification information
     * Ref.: https://datatracker.ietf.org/doc/html/rfc2971
     *
     * @param array|null $ids
     * @return array
     *
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function Id(array $ids = null): array {
        $this->checkConnection();
        return $this->connection->ID($ids)->validatedData();
    }

    /**
     * Retrieve the quota level settings, and usage statics per mailbox
     *
     * @return array
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function getQuota(): array {
        $this->checkConnection();
        return $this->connection->getQuota($this->username)->validatedData();
    }

    /**
     * Retrieve the quota settings per user
     * @param string $quota_root
     *
     * @return array
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function getQuotaRoot(string $quota_root = 'INBOX'): array {
        $this->checkConnection();
        return $this->connection->getQuotaRoot($quota_root)->validatedData();
    }

    /**
     * Delete all messages marked for deletion
     *
     * @return array
     * @throws ConnectionFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws AuthFailedException
     * @throws ResponseException
     */
    public function expunge(): array {
        $this->checkConnection();
        return $this->connection->expunge()->validatedData();
    }

    /**
     * Set the connection timeout
     * @param integer $timeout
     *
     * @return ProtocolInterface
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function setTimeout(int $timeout): ProtocolInterface {
        $this->timeout = $timeout;
        if ($this->isConnected()) {
            $this->connection->setConnectionTimeout($timeout);
            $this->reconnect();
        }
        return $this->connection;
    }

    /**
     * Get the connection timeout
     *
     * @return int
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     * @throws ImapBadRequestException
     * @throws ImapServerErrorException
     * @throws RuntimeException
     * @throws ResponseException
     */
    public function getTimeout(): int {
        $this->checkConnection();
        return $this->connection->getConnectionTimeout();
    }

    /**
     * Get the default message mask
     *
     * @return string
     */
    public function getDefaultMessageMask(): string {
        return $this->default_message_mask;
    }

    /**
     * Get the default events for a given section
     * @param $section
     *
     * @return array
     */
    public function getDefaultEvents($section): array {
        if (isset($this->events[$section])) {
            return is_array($this->events[$section]) ? $this->events[$section] : [];
        }
        return [];
    }

    /**
     * Set the default message mask
     * @param string $mask
     *
     * @return $this
     * @throws MaskNotFoundException
     */
    public function setDefaultMessageMask(string $mask): Client {
        if(class_exists($mask)) {
            $this->default_message_mask = $mask;

            return $this;
        }

        throw new MaskNotFoundException("Unknown mask provided: ".$mask);
    }

    /**
     * Get the default attachment mask
     *
     * @return string
     */
    public function getDefaultAttachmentMask(): string {
        return $this->default_attachment_mask;
    }

    /**
     * Set the default attachment mask
     * @param string $mask
     *
     * @return $this
     * @throws MaskNotFoundException
     */
    public function setDefaultAttachmentMask(string $mask): Client {
        if(class_exists($mask)) {
            $this->default_attachment_mask = $mask;

            return $this;
        }

        throw new MaskNotFoundException("Unknown mask provided: ".$mask);
    }
}
