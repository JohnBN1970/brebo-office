<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use RuntimeException;

/**
 * Extract MIME parts from an already-open, read-only IMAP stream.
 * Returns attachment bytes for private persistence; never drops a part
 * with a filename or attachment disposition.
 */
final class OfficeImapMimeExtractor
{
    /** @return array{body:string,html:?string,attachments:list<array{filename:string,mime:string,bytes:string}>} */
    public function extract(mixed $stream, int $uid): array
    {
        $structure = imap_fetchstructure($stream, $uid, FT_UID);
        if (!is_object($structure)) {
            throw new RuntimeException('MIME structure unavailable.');
        }
        $attachments = [];
        $body = '';
        $html = null;
        $this->walk($stream, $uid, $structure, '', $body, $html, $attachments);
        return ['body' => $body !== '' ? $body : ($html !== null ? html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''), 'html' => $html, 'attachments' => $attachments];
    }

    /** @param list<array{filename:string,mime:string,bytes:string}> $attachments */
    private function walk(mixed $stream, int $uid, object $part, string $number, string &$body, ?string &$html, array &$attachments): void
    {
        if (isset($part->parts) && is_array($part->parts) && $part->parts !== []) {
            foreach ($part->parts as $index => $child) {
                if (!is_object($child)) throw new RuntimeException('Invalid MIME part.');
                $childNumber = ($number === '' ? '' : $number . '.') . ($index + 1);
                $this->walk($stream, $uid, $child, $childNumber, $body, $html, $attachments);
            }
            return;
        }
        $type = (int) ($part->type ?? 7);
        $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));
        $mime = ([0=>'text',1=>'multipart',2=>'message',3=>'application',4=>'audio',5=>'image',6=>'video'][ $type ] ?? 'other') . '/' . $subtype;
        $filename = '';
        foreach (['dparameters','parameters'] as $property) {
            foreach ((array) ($part->{$property} ?? []) as $parameter) {
                if (!is_object($parameter)) continue;
                if (in_array(strtolower((string) ($parameter->attribute ?? '')), ['filename','name'], true)) {
                    $filename = (string) ($parameter->value ?? '');
                }
            }
        }
        $disposition = strtolower((string) ($part->disposition ?? ''));
        $raw = $number === ''
            ? imap_body($stream, $uid, FT_UID | FT_PEEK)
            : imap_fetchbody($stream, $uid, $number, FT_UID | FT_PEEK);
        if (!is_string($raw)) throw new RuntimeException('MIME part unavailable.');
        $bytes = match ((int) ($part->encoding ?? 0)) {
            0,1,2 => $raw,
            3 => base64_decode($raw, true),
            4 => quoted_printable_decode($raw),
            default => false,
        };
        if (!is_string($bytes)) throw new RuntimeException('MIME decoding failed.');
        if ($filename !== '' || $disposition === 'attachment') {
            if ($filename === '') throw new RuntimeException('Unnamed attachment requires review.');
            if (strlen($bytes) < 1 || strlen($bytes) > 15 * 1024 * 1024) {
                throw new RuntimeException('Attachment exceeds size policy.');
            }
            $attachments[] = ['filename'=>$filename, 'mime'=>$mime, 'bytes'=>$bytes];
            return;
        }
        if (($type === 2 && $subtype === 'delivery-status')
            || ($type === 0 && $subtype === 'rfc822-headers')) {
            if (strlen($bytes) < 1 || strlen($bytes) > 15 * 1024 * 1024) {
                throw new RuntimeException('Delivery report exceeds size policy.');
            }
            $label = $type === 2 ? 'delivery-status' : 'original-headers';
            $attachments[] = [
                'filename' => $label . '-' . $uid . '-' . str_replace('.', '-', $number) . '.txt',
                'mime' => $mime,
                'bytes' => $bytes,
            ];
            return;
        }
        if ($type === 0 && $subtype === 'calendar') {
            if (strlen($bytes) < 1 || strlen($bytes) > 15 * 1024 * 1024) throw new RuntimeException('Calendar payload exceeds size policy.');
            $attachments[] = ['filename' => 'agenda-' . $uid . '-' . str_replace('.', '-', $number) . '.ics', 'mime' => 'text/calendar', 'bytes' => $bytes];
            return;
        }
        if ($type === 0 && $subtype === 'html') {
            if ($html === null) $html = $bytes;
            return;
        }
        if ($type === 0 && $subtype === 'plain' && $body === '') {
            $body = $bytes;
            return;
        }
        // Multipart alternatives and related inline resources must not cause
        // a false attachment or silent import of an unsupported payload.
        if ($disposition === 'inline' && $type === 5 && $filename === '') {
            return; // inline image, not an invoice attachment
        }
        // multipart/alternative may include both plain and HTML representations.
        // Do not mistake the second representation for an attachment or an error.
        if ($type === 0 && in_array($subtype, ['plain', 'html'], true) && $body !== '') {
            return;
        }
        throw new RuntimeException('Unsupported MIME part requires review.');
    }
}
