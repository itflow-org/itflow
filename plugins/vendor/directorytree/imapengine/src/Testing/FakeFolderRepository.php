<?php

namespace DirectoryTree\ImapEngine\Testing;

use DirectoryTree\ImapEngine\Collections\FolderCollection;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\FolderRepositoryInterface;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\Support\Str;
use Illuminate\Support\ItemNotFoundException;

class FakeFolderRepository implements FolderRepositoryInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        protected MailboxInterface $mailbox,
        /** @var FolderInterface[] */
        protected array $folders = []
    ) {}

    /**
     * {@inheritDoc}
     */
    public function find(string $path): ?FolderInterface
    {
        try {
            return $this->findOrFail($path);
        } catch (ItemNotFoundException) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail(string $path): FolderInterface
    {
        return $this->get()->firstOrFail(
            fn (FolderInterface $folder) => strtolower($folder->path()) === strtolower($path)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $path): FolderInterface
    {
        return $this->folders[] = new FakeFolder($path, mailbox: $this->mailbox);
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
        $folders = FolderCollection::make($this->folders);

        // If we're not matching all, filter the folders by the match pattern.
        if (! in_array($match, ['*', null])) {
            return $folders->filter(
                fn (FolderInterface $folder) => Str::is($match, $folder->path())
            );
        }

        return $folders;
    }
}
