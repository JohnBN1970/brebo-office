<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

/**
 * Escaped, read-only inbox fragment for an authenticated, authorized Office route.
 * Never call this directly from an unauthenticated public endpoint.
 */
final class OfficeCommunicationInboxView
{
    /** @param list<array<string,mixed>> $messages */
    public static function render(array $messages): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars(
            (string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
        );
        $html = '<section class="office-communication-inbox"><h1>Communicatie</h1>'
            . '<p>Inkomende berichten ter beoordeling. Verzenden is uitgeschakeld.</p>'
            . '<table><thead><tr><th>Ontvangen</th><th>Afzender</th>'
            . '<th>Onderwerp</th><th>Bijlagen</th><th>Verwerking</th></tr></thead><tbody>';
        if ($messages === []) {
            $html .= '<tr><td colspan="5">Er staan geen berichten klaar voor beoordeling.</td></tr>';
        }
        foreach ($messages as $message) {
            $html .= '<tr><td>' . $e($message['received_at'] ?? '') . '</td>'
                . '<td>' . $e(OfficeCommunicationHeader::decode($message['sender_address'] ?? '')) . '</td>'
                . '<td><a href="/office-test/communicatie/bericht?id=' . (int)($message['id'] ?? 0) . '">' . $e(OfficeCommunicationHeader::decode($message['subject'] ?? '')) . '</a></td>'
                . '<td>' . $e((int) ($message['attachment_count'] ?? 0)) . '</td>'
                . '<td>' . $e($message['processing_status'] ?? '') . '</td></tr>';
        }
        return $html . '</tbody></table></section>';
    }
}
