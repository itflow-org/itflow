<?php

namespace DirectoryTree\ImapEngine\Enums;

enum ImapSortKey: string
{
    case Cc = 'CC';
    case To = 'TO';
    case Date = 'DATE';
    case From = 'FROM';
    case Size = 'SIZE';
    case Arrival = 'ARRIVAL';
    case Subject = 'SUBJECT';
}
