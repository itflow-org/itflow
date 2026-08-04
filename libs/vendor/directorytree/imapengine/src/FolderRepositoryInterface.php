<?php

namespace DirectoryTree\ImapEngine;

use DirectoryTree\ImapEngine\Collections\FolderCollection;

interface FolderRepositoryInterface
{
    /**
     * Find a folder.
     */
    public function find(string $path): ?FolderInterface;

    /**
     * Find a folder or throw an exception.
     */
    public function findOrFail(string $path): FolderInterface;

    /**
     * Create a new folder.
     */
    public function create(string $path): FolderInterface;

    /**
     * Find or create a folder.
     */
    public function firstOrCreate(string $path): FolderInterface;

    /**
     * Get the mailboxes folders.
     */
    public function get(?string $match = '*', ?string $reference = ''): FolderCollection;
}
