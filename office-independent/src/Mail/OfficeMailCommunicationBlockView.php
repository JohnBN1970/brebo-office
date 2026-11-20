<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

/**
 * Accessible, escaped communication block for an authorized Office dossier.
 * Only pass rows already filtered by both source-mail and destination ACL.
 */
final class OfficeMailCommunicationBlockView
{
    public static function render(array $authorizedRows, callable $mailSummary): string
    {
        $html = '<section class="office-communication-block" aria-label="Communicatie">'
            . '<h3>Communicatie</h3>';
        if ($authorizedRows === []) {
            return $html . '<p>Geen gekoppelde e-mails.</p></section>';
        }
        $html .= '<ul class="office-communication-list">';
        foreach ($authorizedRows as $row) {
            $messageId = $row['message_id'] ?? null;
            if (!is_string($messageId) || $messageId === '') {
                continue;
            }
            // The summary provider MUST enforce the source mail ACL itself.
            $summary = $mailSummary($messageId);
            if (!is_array($summary) || ($summary['authorized'] ?? false) !== true) {
                continue;
            }
            $subject = self::escape((string) ($summary['subject'] ?? 'E-mail'));
            $sender = self::escape((string) ($summary['sender'] ?? ''));
            $date = self::escape((string) ($summary['date'] ?? ''));
            $html .= '<li class="office-communication-item"><strong>' . $subject
                . '</strong><span>' . $sender . '</span><time>' . $date
                . '</time></li>';
        }
        return $html . '</ul></section>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
