<?php

namespace DirectoryTree\ImapEngine;

trait ComparesFolders
{
    /**
     * Determine if two folders are the same.
     */
    protected function isSameFolder(FolderInterface $a, FolderInterface $b): bool
    {
        return $a->path() === $b->path()
            && $a->mailbox()->config('host') === $b->mailbox()->config('host')
            && $a->mailbox()->config('username') === $b->mailbox()->config('username');
    }
}
