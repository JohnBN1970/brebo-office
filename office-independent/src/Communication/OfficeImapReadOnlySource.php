<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;
use RuntimeException;

/**
 * Provider-neutral IMAP receiver extracted from the legacy sboffice approach.
 * Only reads; no flags, deletes, folder moves or provider-side writes.
 * Caller controls secrets, mailbox authorization and scheduled execution.
 */
final class OfficeImapReadOnlySource
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $folder = 'INBOX',
        private readonly ?OfficeImapMimeExtractor $extractor = null
    ) {
        if ($host === '' || !preg_match('/^[a-z0-9.-]+$/iD', $host)
            || $port < 1 || $port > 65535 || $username === ''
            || $password === '' || $folder !== 'INBOX') {
            throw new InvalidArgumentException('Invalid read-only IMAP configuration.');
        }
    }

    /**
     * @return array{uidvalidity:int,messages:list<array{uid:int,source:array<string,mixed>}>}
     */
    public function fetchAfter(int $afterUid, int $limit = 25): array
    {
        if ($afterUid < 0 || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Invalid IMAP cursor or batch size.');
        }
        if (!function_exists('imap_open')) {
            throw new RuntimeException('PHP IMAP extension unavailable.');
        }
        $mailbox = sprintf('{%s:%d/imap/ssl/validate-cert}%s', $this->host, $this->port, $this->folder);
        $stream = @imap_open($mailbox, $this->username, $this->password, OP_READONLY, 1);
        if ($stream === false) {
            throw new RuntimeException('IMAP connection failed.');
        }
        try {
            $status = imap_status($stream, $mailbox, SA_UIDVALIDITY);
            $validity = (int) ($status->uidvalidity ?? 0);
            if ($validity < 1) {
                throw new RuntimeException('IMAP UIDVALIDITY unavailable.');
            }
            $uids = imap_search($stream, 'ALL', SE_UID) ?: [];
            $uids = array_values(array_filter(array_map('intval', $uids),
                static fn (int $uid): bool => $uid > $afterUid));
            sort($uids, SORT_NUMERIC);
            $result = [];
            foreach (array_slice($uids, 0, $limit) as $uid) {
                $overview = imap_fetch_overview($stream, (string) $uid, FT_UID);
                $row = $overview[0] ?? null;
                if (!is_object($row)) {
                    throw new RuntimeException('IMAP overview unavailable.');
                }
                $header = imap_fetchheader($stream, $uid, FT_UID);
                $body = imap_body($stream, $uid, FT_UID | FT_PEEK);
                if (!is_string($header) || !is_string($body)) {
                    throw new RuntimeException('IMAP message unavailable.');
                }
                $decoded = $this->extractor?->extract($stream, $uid);
                $source = [
                    'source_id' => isset($row->message_id) ? 'message-id:' . trim((string) $row->message_id) : '',
                    'from' => (string) ($row->from ?? ''),
                    'subject' => (string) ($row->subject ?? ''),
                    'body' => $decoded['body'] ?? $body,
                    'attachments' => $decoded['attachments'] ?? [],
                    'mime_decoded' => $decoded !== null,
                    'received_at' => isset($row->date) && strtotime((string) $row->date) !== false
                        ? gmdate(DATE_ATOM, strtotime((string) $row->date)) : gmdate(DATE_ATOM),
                    'raw_headers' => $header,
                ];
                $result[] = ['uid' => $uid, 'source' => $source];
            }
            return ['uidvalidity' => $validity, 'messages' => $result];
        } finally {
            imap_close($stream);
        }
    }
}
