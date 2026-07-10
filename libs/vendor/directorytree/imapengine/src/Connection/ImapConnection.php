<?php

namespace DirectoryTree\ImapEngine\Connection;

use DirectoryTree\ImapEngine\Collections\ResponseCollection;
use DirectoryTree\ImapEngine\Connection\Loggers\LoggerInterface;
use DirectoryTree\ImapEngine\Connection\Responses\ContinuationResponse;
use DirectoryTree\ImapEngine\Connection\Responses\Data\Data;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;
use DirectoryTree\ImapEngine\Connection\Responses\Response;
use DirectoryTree\ImapEngine\Connection\Responses\TaggedResponse;
use DirectoryTree\ImapEngine\Connection\Responses\UntaggedResponse;
use DirectoryTree\ImapEngine\Connection\Streams\FakeStream;
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Enums\ImapSortKey;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionClosedException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionFailedException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionTimedOutException;
use DirectoryTree\ImapEngine\Exceptions\ImapResponseException;
use DirectoryTree\ImapEngine\Exceptions\ImapStreamException;
use DirectoryTree\ImapEngine\Support\Str;
use Exception;
use Generator;
use LogicException;
use Throwable;

class ImapConnection implements ConnectionInterface
{
    /**
     * Sequence number used to generate unique command tags.
     */
    protected int $sequence = 0;

    /**
     * The result instance.
     */
    protected ?Result $result = null;

    /**
     * The parser instance.
     */
    protected ?ImapParser $parser = null;

    /**
     * Constructor.
     */
    public function __construct(
        protected StreamInterface $stream,
        protected ?LoggerInterface $logger = null,
    ) {}

    /**
     * Create a new connection with a fake stream.
     */
    public static function fake(array $responses = []): static
    {
        $stream = new FakeStream;

        $stream->open();

        $stream->feed($responses);

        return new static($stream);
    }

    /**
     * Tear down the connection.
     */
    public function __destruct()
    {
        if (! $this->connected()) {
            return;
        }

        try {
            @$this->logout();
        } catch (Exception $e) {
            // Do nothing.
        }
    }

    /**
     * {@inheritDoc}
     */
    public function connect(string $host, ?int $port = null, array $options = []): void
    {
        $transport = strtolower($options['encryption'] ?? '') ?: 'tcp';

        if (in_array($transport, ['ssl', 'tls'])) {
            $port ??= 993;
        } else {
            $port ??= 143;
        }

        $this->setParser(
            $this->newParser($this->stream)
        );

        $this->stream->open(
            $transport === 'starttls' ? 'tcp' : $transport,
            $host,
            $port,
            $options['timeout'] ?? 30,
            $this->getDefaultSocketOptions(
                $transport,
                $options['proxy'] ?? [],
                $options['validate_cert'] ?? true
            )
        );

        $this->assertNextResponse(
            fn (Response $response) => $response instanceof UntaggedResponse,
            fn (UntaggedResponse $response) => $response->type()->is('OK'),
            fn () => new ImapConnectionFailedException("Connection to $host:$port failed")
        );

        if ($transport === 'starttls') {
            $this->startTls();
        }
    }

    /**
     * Get the default socket options for the given transport.
     *
     * @param  'ssl'|'tls'|'starttls'|'tcp'  $transport
     */
    protected function getDefaultSocketOptions(string $transport, array $proxy = [], bool $validateCert = true): array
    {
        $options = [];

        $key = match ($transport) {
            'ssl', 'tls' => 'ssl',
            'starttls', 'tcp' => 'tcp',
        };

        if (in_array($transport, ['ssl', 'tls'])) {
            $options[$key] = [
                'verify_peer' => $validateCert,
                'verify_peer_name' => $validateCert,
            ];
        }

        if (! isset($proxy['socket'])) {
            return $options;
        }

        $options[$key]['proxy'] = $proxy['socket'];
        $options[$key]['request_fulluri'] = $proxy['request_fulluri'] ?? false;

        if (isset($proxy['username'])) {
            $auth = base64_encode($proxy['username'].':'.$proxy['password']);

            $options[$key]['header'] = ["Proxy-Authorization: Basic $auth"];
        }

        return $options;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        $this->stream->close();
    }

    /**
     * {@inheritDoc}
     */
    public function connected(): bool
    {
        return $this->stream->opened();
    }

    /**
     * {@inheritDoc}
     */
    public function login(string $user, string $password): TaggedResponse
    {
        $this->send('LOGIN', Str::literal([$user, $password]), $tag);

        return $this->assertTaggedResponse($tag, fn (TaggedResponse $response) => (
            ImapCommandException::make($this->result->command()->redacted(), $response)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function logout(): void
    {
        $this->send('LOGOUT', tag: $tag);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(string $user, string $token): TaggedResponse
    {
        $this->send('AUTHENTICATE', ['XOAUTH2', Str::credentials($user, $token)], $tag);

        return $this->assertTaggedResponse($tag, fn (TaggedResponse $response) => (
            ImapCommandException::make($this->result->command()->redacted(), $response)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function startTls(): void
    {
        $this->send('STARTTLS', tag: $tag);

        $this->assertTaggedResponse($tag);

        $this->stream->setSocketSetCrypto(true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    }

    /**
     * {@inheritDoc}
     */
    public function select(string $folder = 'INBOX'): ResponseCollection
    {
        return $this->examineOrSelect('SELECT', $folder);
    }

    /**
     * {@inheritDoc}
     */
    public function examine(string $folder = 'INBOX'): ResponseCollection
    {
        return $this->examineOrSelect('EXAMINE', $folder);
    }

    /**
     * Examine and select have the same response.
     */
    protected function examineOrSelect(string $command = 'EXAMINE', string $folder = 'INBOX'): ResponseCollection
    {
        $this->send($command, [Str::literal($folder)], $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged();
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $folder = 'INBOX', array $arguments = ['MESSAGES', 'UNSEEN', 'RECENT', 'UIDNEXT', 'UIDVALIDITY']): UntaggedResponse
    {
        $this->send('STATUS', [
            Str::literal($folder),
            Str::list($arguments),
        ], $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->firstWhere(
            fn (UntaggedResponse $response) => $response->type()->is('STATUS')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $folder): ResponseCollection
    {
        $this->send('CREATE', [Str::literal($folder)], $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->filter(
            fn (UntaggedResponse $response) => $response->type()->is('LIST')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $folder): TaggedResponse
    {
        $this->send('DELETE', [Str::literal($folder)], tag: $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function rename(string $oldPath, string $newPath): TaggedResponse
    {
        $this->send('RENAME', Str::literal([$oldPath, $newPath]), tag: $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function subscribe(string $folder): TaggedResponse
    {
        $this->send('SUBSCRIBE', [Str::literal($folder)], tag: $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function unsubscribe(string $folder): TaggedResponse
    {
        $this->send('UNSUBSCRIBE', [Str::literal($folder)], tag: $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function quota(string $root): UntaggedResponse
    {
        $this->send('GETQUOTA', [Str::literal($root)], tag: $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->firstOrFail(
            fn (UntaggedResponse $response) => $response->type()->is('QUOTA')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function quotaRoot(string $mailbox): ResponseCollection
    {
        $this->send('GETQUOTAROOT', [Str::literal($mailbox)], tag: $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->filter(
            fn (UntaggedResponse $response) => $response->type()->is('QUOTA')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function list(string $reference = '', string $folder = '*'): ResponseCollection
    {
        $this->send('LIST', Str::literal([$reference, $folder]), $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->filter(
            fn (UntaggedResponse $response) => $response->type()->is('LIST')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function append(string $folder, string $message, ?array $flags = null): TaggedResponse
    {
        $tokens = [];

        $tokens[] = Str::literal($folder);

        if ($flags) {
            $tokens[] = Str::list($flags);
        }

        $tokens[] = Str::literal($message);

        $this->send('APPEND', $tokens, tag: $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $folder, array|int $from, ?int $to = null): TaggedResponse
    {
        $this->send('UID COPY', [
            Str::set($from, $to),
            Str::literal($folder),
        ], $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $folder, array|int $from, ?int $to = null): TaggedResponse
    {
        $this->send('UID MOVE', [
            Str::set($from, $to),
            Str::literal($folder),
        ], $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function store(array|string $flags, array|int $from, ?int $to = null, ?string $mode = null, bool $silent = true, ?string $item = null): ResponseCollection
    {
        $set = Str::set($from, $to);

        $flags = Str::list((array) $flags);

        $item = ($mode == '-' ? '-' : '+').(is_null($item) ? 'FLAGS' : $item).($silent ? '.SILENT' : '');

        $this->send('UID STORE', [$set, $item, $flags], tag: $tag);

        $this->assertTaggedResponse($tag);

        return $silent ? new ResponseCollection : $this->result->responses()->untagged()->filter(
            fn (UntaggedResponse $response) => $response->type()->is('FETCH')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function uid(int|array $ids, ImapFetchIdentifier $identifier): ResponseCollection
    {
        return $this->fetch(['UID'], (array) $ids, null, $identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function bodyText(int|array $ids, bool $peek = true): ResponseCollection
    {
        return $this->fetch([$peek ? 'BODY.PEEK[TEXT]' : 'BODY[TEXT]'], (array) $ids);
    }

    /**
     * {@inheritDoc}
     */
    public function bodyHeader(int|array $ids, bool $peek = true): ResponseCollection
    {
        return $this->fetch([$peek ? 'BODY.PEEK[HEADER]' : 'BODY[HEADER]'], (array) $ids);
    }

    /**
     * Fetch the BODYSTRUCTURE for the given message(s).
     */
    public function bodyStructure(int|array $ids): ResponseCollection
    {
        return $this->fetch(['BODYSTRUCTURE'], (array) $ids);
    }

    /**
     * Fetch a specific part of the message BODY, such as BODY[1], BODY[1.2], etc.
     */
    public function bodyPart(string $partIndex, int|array $ids, bool $peek = false): ResponseCollection
    {
        $part = $peek ? "BODY.PEEK[$partIndex]" : "BODY[$partIndex]";

        return $this->fetch([$part], (array) $ids);
    }

    /**
     * {@inheritDoc}
     */
    public function flags(int|array $ids): ResponseCollection
    {
        return $this->fetch(['FLAGS'], (array) $ids);
    }

    /**
     * {@inheritDoc}
     */
    public function size(int|array $ids): ResponseCollection
    {
        return $this->fetch(['RFC822.SIZE'], (array) $ids);
    }

    /**
     * {@inheritDoc}
     */
    public function search(array $params): UntaggedResponse
    {
        $this->send('UID SEARCH', $params, tag: $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->firstOrFail(
            fn (UntaggedResponse $response) => $response->type()->is('SEARCH')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function sort(ImapSortKey $key, string $direction, array $params): UntaggedResponse
    {
        $sortCriteria = $direction === 'desc' ? "REVERSE {$key->value}" : $key->value;

        $this->send('UID SORT', ["({$sortCriteria})", 'UTF-8', ...$params], tag: $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->firstOrFail(
            fn (UntaggedResponse $response) => $response->type()->is('SORT')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function capability(): UntaggedResponse
    {
        $this->send('CAPABILITY', tag: $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->firstOrFail(
            fn (UntaggedResponse $response) => $response->type()->is('CAPABILITY')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function id(?array $ids = null): UntaggedResponse
    {
        $token = 'NIL';

        if (is_array($ids) && ! empty($ids)) {
            $token = '(';

            foreach ($ids as $id) {
                $token .= '"'.Str::escape($id).'" ';
            }

            $token = rtrim($token).')';
        }

        $this->send('ID', [$token], tag: $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged()->firstOrFail(
            fn (UntaggedResponse $response) => $response->type()->is('ID')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function expunge(): ResponseCollection
    {
        $this->send('EXPUNGE', tag: $tag);

        $this->assertTaggedResponse($tag);

        return $this->result->responses()->untagged();
    }

    /**
     * {@inheritDoc}
     */
    public function noop(): TaggedResponse
    {
        $this->send('NOOP', tag: $tag);

        return $this->assertTaggedResponse($tag);
    }

    /**
     * {@inheritDoc}
     */
    public function idle(int $timeout): Generator
    {
        $this->stream->setTimeout($timeout);

        $this->send('IDLE', tag: $tag);

        $this->assertNextResponse(
            fn (Response $response) => $response instanceof ContinuationResponse,
            fn (ContinuationResponse $response) => true,
            fn (ContinuationResponse $response) => ImapCommandException::make(new ImapCommand('', 'IDLE'), $response),
        );

        while ($response = $this->nextReply()) {
            yield $response;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function done(): void
    {
        $this->write('DONE');

        // After issuing a "DONE" command, the server must eventually respond with a
        // tagged response to indicate that the IDLE command has been successfully
        // terminated and the server is ready to accept further commands.
        $this->assertNextResponse(
            fn (Response $response) => $response instanceof TaggedResponse,
            fn (TaggedResponse $response) => $response->successful(),
            fn (TaggedResponse $response) => ImapCommandException::make(new ImapCommand('', 'DONE'), $response),
        );
    }

    /**
     * Send an IMAP command.
     *
     * @param-out string $tag
     */
    public function send(string $name, array $tokens = [], ?string &$tag = null): void
    {
        if (! $tag) {
            $this->sequence++;
            $tag = 'TAG'.$this->sequence;
        }

        $command = new ImapCommand($tag, $name, $tokens);

        // After every command, we'll overwrite any previous result
        // with the new command and its responses, so that we can
        // easily access the commands responses for assertion.
        $this->setResult(new Result($command));

        foreach ($command->compile() as $line) {
            $this->write($line);
        }
    }

    /**
     * Write data to the connected stream.
     */
    protected function write(string $data): void
    {
        if ($this->stream->fwrite($data."\r\n") === false) {
            throw new ImapStreamException('Failed to write data to stream');
        }

        $this->logger?->sent($data);
    }

    /**
     * Fetch one or more items for one or more messages.
     */
    public function fetch(array|string $items, array|int $from, mixed $to = null, ImapFetchIdentifier $identifier = ImapFetchIdentifier::Uid): ResponseCollection
    {
        $prefix = ($identifier === ImapFetchIdentifier::Uid) ? 'UID' : '';

        $this->send(trim($prefix.' FETCH'), [
            Str::set($from, $to),
            Str::list((array) $items),
        ], $tag);

        $this->assertTaggedResponse($tag);

        // Some IMAP servers can send unsolicited untagged responses along with fetch
        // requests. We'll need to filter these out so that we can return only the
        // responses that are relevant to the fetch command. For example:
        // >> TAG123 FETCH (UID 456 BODY[TEXT])
        // << * 123 FETCH (UID 456 BODY[TEXT] {14}\nHello, World!)
        // << * 123 FETCH (FLAGS (\Seen)) <-- Unsolicited response
        return $this->result->responses()->untagged()->filter(function (UntaggedResponse $response) use ($items, $identifier) {
            // Skip over any untagged responses that are not FETCH responses.
            // The third token should always be the list of data items.
            if (! ($data = $response->tokenAt(3)) instanceof ListData) {
                return false;
            }

            return match ($identifier) {
                // If we're fetching UIDs, we can check if a UID token is contained in the list.
                ImapFetchIdentifier::Uid => $data->contains('UID'),

                // If we're fetching message numbers, we can check if the requested items are all contained in the list.
                ImapFetchIdentifier::MessageNumber => $data->contains($items),
            };
        });
    }

    /**
     * Set the current result instance.
     */
    protected function setResult(Result $result): void
    {
        $this->result = $result;
    }

    /**
     * Set the current parser instance.
     */
    protected function setParser(ImapParser $parser): void
    {
        $this->parser = $parser;
    }

    /**
     * Create a new parser instance.
     */
    protected function newParser(StreamInterface $stream): ImapParser
    {
        return new ImapParser($this->newTokenizer($stream));
    }

    /**
     * Create a new tokenizer instance.
     */
    protected function newTokenizer(StreamInterface $stream): ImapTokenizer
    {
        return new ImapTokenizer($stream);
    }

    /**
     * Assert the next response is a successful tagged response.
     */
    protected function assertTaggedResponse(string $tag, ?callable $exception = null): TaggedResponse
    {
        /** @var TaggedResponse $response */
        $response = $this->assertNextResponse(
            fn (Response $response) => (
                $response instanceof TaggedResponse && $response->tag()->is($tag)
            ),
            fn (TaggedResponse $response) => (
                $response->successful()
            ),
            $exception ?? fn (TaggedResponse $response) => (
                ImapCommandException::make($this->result->command(), $response)
            ),
        );

        return $response;
    }

    /**
     * Assert the next response matches the given filter and assertion.
     *
     * @template T of Response
     *
     * @param  callable(Response): bool  $filter
     * @param  callable(T): bool  $assertion
     * @param  callable(T): Throwable  $exception
     * @return T
     *
     * @throws ImapResponseException
     */
    protected function assertNextResponse(callable $filter, callable $assertion, callable $exception): Response
    {
        while ($response = $this->nextResponse($filter)) {
            if ($assertion($response)) {
                return $response;
            }

            throw $exception($response);
        }

        throw new ImapResponseException('No matching response found');
    }

    /**
     * Returns the next response matching the given filter.
     *
     * @template T of Response
     *
     * @param  callable(T): bool  $filter
     * @return T|null
     */
    protected function nextResponse(callable $filter): ?Response
    {
        if (! $this->parser) {
            throw new LogicException('No parser instance set');
        }

        while ($response = $this->nextReply()) {
            if (! $response instanceof Response) {
                continue;
            }

            $this->result?->addResponse($response);

            if ($filter($response)) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Read the next reply from the stream.
     */
    protected function nextReply(): Data|Token|Response|null
    {
        if (! $reply = $this->parser->next()) {
            $meta = $this->stream->meta();

            throw match (true) {
                $meta['timed_out'] ?? false => new ImapConnectionTimedOutException('Stream timed out, no response'),
                $meta['eof'] ?? false => new ImapConnectionClosedException('Server closed the connection (EOF)'),
                default => new ImapConnectionFailedException('Unknown stream error. Metadata: '.json_encode($meta)),
            };
        }

        $this->logger?->received($reply);

        return $reply;
    }
}
