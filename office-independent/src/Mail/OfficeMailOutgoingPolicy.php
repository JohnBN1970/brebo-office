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

  /** Sending is blocked until mailbox-specific can_send ACL and delivery logging are connected. */
  public static function assertReadyToSend(): void {
    throw new \RuntimeException('Office e-mail verzenden is nog niet vrijgegeven.');
  }
}
