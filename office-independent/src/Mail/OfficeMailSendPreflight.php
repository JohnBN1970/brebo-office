<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/**
 * Server-side, read-only outbound preflight. Never queues or sends mail.
 * The operator ID must come from the authenticated Office session.
 */
final class OfficeMailSendPreflight {
  public static function check(PDO $db, string $operatorId, int $mailboxId, string $recipient, string $subject, string $body): array {
    if (!OfficeMailOutgoingPolicy::enabled()) {
      throw new RuntimeException('Uitgaande Office e-mail staat uit.');
    }
    $draft = OfficeMailOutgoingPolicy::validateDraft($recipient, $subject, $body);
    $sender = OfficeMailOutgoingPolicy::authorizedSender($db, $operatorId, $mailboxId);
    return [
      'sender_mailbox_id' => (int) $sender['id'],
      'from' => (string) $sender['address'],
      'to' => $draft['recipient'],
      'subject' => $draft['subject'],
      'text' => $draft['body'],
      'delivery_status' => 'not_sent',
    ];
  }
}
