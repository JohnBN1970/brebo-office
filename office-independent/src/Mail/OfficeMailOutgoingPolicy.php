<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use InvalidArgumentException;

/**
 * Fail-closed preparation for native Office outgoing mail.
 * This class never sends, queues, or stores messages.
 */
final class OfficeMailOutgoingPolicy {
  public static function enabled(): bool {
    return getenv('OFFICE_MAIL_OUTBOUND_ENABLED') === 'enabled';
  }

  public static function validateDraft(string $recipient, string $subject, string $body): array {
    $recipient=trim($recipient);
    $subject=trim($subject);
    if (!filter_var($recipient,FILTER_VALIDATE_EMAIL)
      || strlen($recipient)>254
      || preg_match('/[\r\n\x00-\x1F\x7F]/',$recipient)) {
      throw new InvalidArgumentException('Ongeldig ontvangstadres.');
    }
    if ($subject==='' || strlen($subject)>512
      || preg_match('/[\r\n\x00-\x1F\x7F]/',$subject)) {
      throw new InvalidArgumentException('Ongeldig onderwerp.');
    }
    if (trim($body)==='' || strlen($body)>2097152) {
      throw new InvalidArgumentException('Ongeldige berichttekst.');
    }
    return ['recipient'=>$recipient,'subject'=>$subject,'body'=>$body];
  }

  /**
   * Resolve a sender from the authenticated operator, never from browser input.
   * Functional mailboxes require an explicit send grant; read access is insufficient.
   * Personal mailboxes can only be used by their owner.
   */
  public static function authorizedSender(\PDO $db, string $operatorId, int $mailboxId): array {
    if ($operatorId==='' || strlen($operatorId)>128 || $mailboxId<=0) {
      throw new InvalidArgumentException('Ongeldige afzenderselectie.');
    }
    $stmt=$db->prepare("SELECT b.id,b.address,b.label,b.mailbox_type
      FROM office_mail_mailboxes b
      WHERE b.id=:mailbox AND b.active=1
      AND ((b.mailbox_type='personal' AND b.owner_operator_id=:owner)
        OR (b.mailbox_type='functional' AND EXISTS
          (SELECT 1 FROM office_mail_mailbox_access a
           WHERE a.mailbox_id=b.id AND a.operator_id=:operator AND a.can_send=1)))
      LIMIT 1");
    $stmt->execute([':mailbox'=>$mailboxId,':owner'=>$operatorId,':operator'=>$operatorId]);
    $row=$stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row || !filter_var((string)$row['address'],FILTER_VALIDATE_EMAIL)) {
      throw new \RuntimeException('Geen verzendrechten voor deze mailbox.');
    }
    return $row;
  }

  /** Sending is blocked until mailbox-specific can_send ACL and delivery logging are connected. */
  public static function assertReadyToSend(): void {
    throw new \RuntimeException('Office e-mail verzenden is nog niet vrijgegeven.');
  }
}
