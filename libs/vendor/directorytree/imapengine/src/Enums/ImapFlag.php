<?php

namespace DirectoryTree\ImapEngine\Enums;

enum ImapFlag: string
{
    case Seen = '\Seen';
    case Draft = '\Draft';
    case Recent = '\Recent';
    case Flagged = '\Flagged';
    case Deleted = '\Deleted';
    case Answered = '\Answered';
}
