<?php

namespace DirectoryTree\ImapEngine\Collections;

use DirectoryTree\ImapEngine\FolderInterface;
use Illuminate\Support\Collection;

/**
 * @template-extends Collection<array-key, FolderInterface>
 */
class FolderCollection extends Collection {}
