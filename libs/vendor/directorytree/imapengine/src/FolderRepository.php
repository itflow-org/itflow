<?php

namespace DirectoryTree\ImapEngine;

use DirectoryTree\ImapEngine\Collections\FolderCollection;
use DirectoryTree\ImapEngine\Connection\Responses\UntaggedResponse;
use DirectoryTree\ImapEngine\Support\Str;

class FolderRepository implements FolderRepositoryInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        protected Mailbox $mailbox
    ) {}

    /**
     * {@inheritDoc}
     */
    public function find(string $path): ?FolderInterface
    {
        return $this->get($path)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail(string $path): FolderInterface
    {
        return $this->get($path)->firstOrFail();
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $path): FolderInterface
    {
        $this->mailbox->connection()->create(
            Str::toImapUtf7($path)
        );

        return $this->find($path);
    }

    /**
     * {@inheritDoc}
     */
    public function firstOrCreate(string $path): FolderInterface
    {
        return $this->find($path) ?? $this->create($path);
    }

    /**
     * {@inheritDoc}
     */
    public function get(?string $match = '*', ?string $reference = ''): FolderCollection
    {
        return $this->mailbox->connection()->list($reference, Str::toImapUtf7($match))->map(
            fn (UntaggedResponse $response) => new Folder(
                mailbox: $this->mailbox,
                path: $response->tokenAt(4)->value,
                flags: $response->tokenAt(2)->values(),
                delimiter: $response->tokenAt(3)->value,
            )
        )->pipeInto(FolderCollection::class);
    }
}
