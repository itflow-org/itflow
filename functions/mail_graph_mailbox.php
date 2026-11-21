<?php

/*
 * The mailbox read through Microsoft Graph instead of IMAP, for
 * cron/ticket_email_parser.php (mailGraphMailbox() in functions/mail_graph.php).
 *
 * These stand in for the parts of DirectoryTree\ImapEngine the parser uses - the
 * mailbox, its folders, the query for unread mail and each message - so the
 * parser reads either the same way. A message is downloaded whole (its MIME
 * source) and read by ImapEngine's own FileMessage; marking it read or flagged
 * and moving it go to Graph. mailGraphMailbox() loads ImapEngine first.
 */

use DirectoryTree\ImapEngine\FileMessage;

class MailGraphMailbox {

    private $token;
    private $base;
    private $folder_ids = [];

    public function __construct($token, $username) {
        $this->token = $token;
        $this->base = $username !== '' ? '/users/' . rawurlencode($username) : '/me';
    }

    /* A Graph call for this mailbox - throws with Microsoft's reason unless the status is one of $ok */
    public function call($method, $path, $body = null, array $ok = [200]) {
        $response = mailGraph($method, $this->base . $path, $this->token, $body);
        if (!in_array($response['status'], $ok, true)) {
            throw new RuntimeException(mailGraphError($response, "Microsoft Graph $method " . strtok($path, '?')));
        }
        return $response;
    }

    /* The next page of a list - only a link back to Graph itself is followed */
    public function next($url) {
        if (strpos($url, MAIL_GRAPH_URL . '/') !== 0) {
            throw new RuntimeException('Microsoft Graph: the next page of the list pointed somewhere else');
        }
        $response = mailGraph('GET', substr($url, strlen(MAIL_GRAPH_URL)), $this->token);
        if ($response['status'] !== 200) {
            throw new RuntimeException(mailGraphError($response, 'Microsoft Graph GET (the next page)'));
        }
        return $response;
    }

    /* A message's MIME source */
    public function mime($message_id) {
        $response = mailGraphHttp('GET', MAIL_GRAPH_URL . $this->base . '/messages/' . rawurlencode($message_id) . '/$value',
            ['Authorization: Bearer ' . $this->token], null, 300);
        if ($response['status'] !== 200) {
            throw new RuntimeException(mailGraphError($response, 'Downloading an email from Microsoft Graph'));
        }
        return $response['body'];
    }

    public function connect() {
        $this->call('GET', '/mailFolders/inbox?$select=id');
    }

    public function disconnect() {
    }

    public function inbox() {
        return new MailGraphFolder($this, 'INBOX', 'inbox');
    }

    public function folders() {
        return new MailGraphFolders($this);
    }

    /* IMAP's SUBSCRIBE has nothing to do here - Outlook shows every folder */
    public function connection() {
        return new class {
            public function subscribe($folder) {
            }
        };
    }

    /* A folder's Graph ID from its IMAP-style path ("ITFlow", "INBOX/ITFlow"), or null */
    public function folderId($path) {
        if (array_key_exists($path, $this->folder_ids)) {
            return $this->folder_ids[$path];
        }
        $parent = '/mailFolders';
        $id = null;
        foreach (explode('/', $path) as $i => $name) {
            if ($i === 0 && strcasecmp($name, 'INBOX') === 0) {
                $id = 'inbox';
            } else {
                $filter = rawurlencode("displayName eq '" . str_replace("'", "''", $name) . "'");
                $found = $this->call('GET', "$parent?\$filter=$filter&\$select=id");
                $id = $found['json']['value'][0]['id'] ?? null;
                if ($id === null) {
                    break;
                }
            }
            $parent = '/mailFolders/' . rawurlencode($id) . '/childFolders';
        }
        return $this->folder_ids[$path] = $id;
    }

    public function rememberFolder($path, $id) {
        $this->folder_ids[$path] = $id;
    }
}

class MailGraphFolders {

    private $mailbox;

    public function __construct(MailGraphMailbox $mailbox) {
        $this->mailbox = $mailbox;
    }

    public function find($path) {
        $id = $this->mailbox->folderId($path);
        return $id === null ? null : new MailGraphFolder($this->mailbox, $path, $id);
    }

    public function findOrFail($path) {
        $folder = $this->find($path);
        if (!$folder) {
            throw new RuntimeException("Microsoft Graph: there is no folder $path");
        }
        return $folder;
    }

    public function create($path) {
        $parts = explode('/', $path);
        $name = array_pop($parts);
        $parent_id = $parts ? $this->mailbox->folderId(implode('/', $parts)) : null;
        if ($parts && $parent_id === null) {
            throw new RuntimeException('Microsoft Graph: there is no folder ' . implode('/', $parts));
        }
        $created = $this->mailbox->call('POST', $parent_id === null ? '/mailFolders' : '/mailFolders/' . rawurlencode($parent_id) . '/childFolders',
            ['displayName' => $name], [201]);
        $this->mailbox->rememberFolder($path, $created['json']['id']);
        return new MailGraphFolder($this->mailbox, $path, $created['json']['id']);
    }
}

class MailGraphFolder {

    private $mailbox;
    private $path;
    private $id;

    public function __construct(MailGraphMailbox $mailbox, $path, $id) {
        $this->mailbox = $mailbox;
        $this->path = $path;
        $this->id = $id;
    }

    public function path() {
        return $this->path;
    }

    public function delimiter() {
        return '/';
    }

    public function messages() {
        return new MailGraphMessageQuery($this->mailbox, $this->id);
    }

    /* Graph moves straight away - nothing to expunge */
    public function expunge() {
    }
}

/* Unread, unflagged mail in a folder, oldest first - the parser's IMAP query */
class MailGraphMessageQuery {

    private $mailbox;
    private $folder_id;
    private $limit = 50;

    public function __construct(MailGraphMailbox $mailbox, $folder_id) {
        $this->mailbox = $mailbox;
        $this->folder_id = $folder_id;
    }

    public function limit($limit) {
        $this->limit = max(1, intval($limit));
        return $this;
    }

    /* withHeaders(), withBody(), withFlags(), leaveUnread(), unseen(), unflagged() - always so here */
    public function __call($name, $arguments) {
        return $this;
    }

    public function get() {
        // Oldest first, as IMAP reads them (Graph wants the $orderby property first in the $filter). Flagged mail - what
        // the parser couldn't take - is skipped here, so pages are read on (up to 20) until there are enough to work on:
        // a pile of flagged mail at the oldest end mustn't hide newer mail for good
        $filter = rawurlencode('receivedDateTime ge 1900-01-01T00:00:00Z and isRead eq false');
        $path = '/mailFolders/' . rawurlencode($this->folder_id)
            . "/messages?\$filter=$filter&\$orderby=receivedDateTime%20asc&\$select=id,receivedDateTime,flag&\$top=" . min(1000, $this->limit * 4);
        $rows = [];
        $skipped = 0;
        $next = '';
        for ($page = 0; $page < 20 && ($page === 0 || $next !== '') && count($rows) < $this->limit; $page++) {
            try {
                $list = $page === 0 ? $this->mailbox->call('GET', $path) : $this->mailbox->next($next);
            } catch (Throwable $e) {
                // Throttled, or a passing hiccup: no mail this run, and the next tries again. Anything else - the
                // permission taken away, the sign-in refused - stops the job, so Maintenance > Cron shows it
                if (preg_match('/\(HTTP (429|502|503|504)\)/', $e->getMessage())) {
                    logApp("Cron-Email-Parser", "warning", "Microsoft Graph: couldn't list new mail this run - " . $e->getMessage());
                    break;
                }
                throw $e;
            }
            foreach ($list['json']['value'] ?? [] as $row) {
                if (($row['flag']['flagStatus'] ?? '') === 'flagged') {
                    $skipped++;
                } else {
                    $rows[] = $row;
                }
            }
            $next = (string) ($list['json']['@odata.nextLink'] ?? '');
        }
        if (!$rows && $skipped >= $this->limit * 4) {
            logApp("Cron-Email-Parser", "warning", "Microsoft Graph: $skipped unread emails in the mailbox are flagged - ones the parser couldn't take - look at them and clear the flags");
        }

        $messages = [];
        $bytes = 0;
        foreach (array_slice($rows, 0, $this->limit) as $row) {
            // A run holds every email it reads in memory - past 64 MB the rest wait for the next run, so a pile of
            // big emails can't run PHP out of memory and stop the parser on the same ones every minute
            if ($bytes > 64 * 1024 * 1024) {
                break;
            }
            try {
                $mime = $this->mailbox->mime($row['id']);
                $bytes += strlen($mime);
                $messages[] = new MailGraphMessage($this->mailbox, $row['id'], $mime);
            } catch (Throwable $e) {
                // One that won't download mustn't hold up the rest - it is flagged, as the parser does with an email it
                // can't take, so it isn't tried again every minute
                logApp("Cron-Email-Parser", "warning", "Microsoft Graph: skipped an email that couldn't be downloaded (flagged in the mailbox) - " . $e->getMessage());
                try {
                    $this->mailbox->call('PATCH', '/messages/' . rawurlencode($row['id']), ['flag' => ['flagStatus' => 'flagged']]);
                } catch (Throwable $ignored) {
                }
            }
        }
        return $messages;
    }
}

/* An email as ImapEngine reads it from its MIME source, marked read or flagged and moved through Graph */
class MailGraphMessage extends FileMessage {

    private $mailbox;
    private $graph_id;

    public function __construct(MailGraphMailbox $mailbox, $graph_id, $mime) {
        parent::__construct($mime);
        $this->mailbox = $mailbox;
        $this->graph_id = $graph_id;
    }

    /* The raw body (all after the headers) - the parser's last resort for an email with no text or HTML part */
    public function body(): string {
        $parts = preg_split("/\r?\n\r?\n/", $this->contents, 2);
        return $parts[1] ?? '';
    }

    /* IMAP UIDs are numbers and Graph IDs aren't - a steady number for the logs */
    public function uid(): int {
        return intval(sprintf('%u', crc32($this->graph_id)));
    }

    public function flag(BackedEnum|string $flag, string $operation, bool $expunge = false): void {
        $flag = $flag instanceof BackedEnum ? $flag->value : $flag;
        $on = $operation === '+';
        if ($flag === '\Seen') {
            $this->mailbox->call('PATCH', '/messages/' . rawurlencode($this->graph_id), ['isRead' => $on]);
        } elseif ($flag === '\Flagged') {
            $this->mailbox->call('PATCH', '/messages/' . rawurlencode($this->graph_id), ['flag' => ['flagStatus' => $on ? 'flagged' : 'notFlagged']]);
        }
    }

    public function move(string $folder, bool $expunge = false): ?int {
        $folder_id = $this->mailbox->folderId($folder);
        if ($folder_id === null) {
            throw new RuntimeException("Microsoft Graph: there is no folder $folder");
        }
        $moved = $this->mailbox->call('POST', '/messages/' . rawurlencode($this->graph_id) . '/move', ['destinationId' => $folder_id], [200, 201]);
        $this->graph_id = $moved['json']['id'] ?? $this->graph_id;
        return null;
    }
}
