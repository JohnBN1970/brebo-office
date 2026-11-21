<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/** Idempotent persistence of messages from an explicitly configured functional mailbox. */
final class OfficeFunctionalMailIngest {
  public function __construct(
    private readonly PDO $db,
    private readonly OfficeNativeMailboxRepository $mailboxes
  ) {}

  /** @param array<string,mixed> $message */
  public function store(int $mailboxId, array $message): int {
    $this->mailboxes->assertFunctionalIngestAllowed($mailboxId);
    $source = trim((string) ($message['source_id'] ?? ''));
    $sender = trim((string) ($message['sender'] ?? ''));
    $subject = trim((string) ($message['subject'] ?? ''));
    $recipient = trim((string) ($message['recipient'] ?? ''));
    $body = $message['body_text'] ?? null;
    $received = (string) ($message['received_at'] ?? '');
    if ($source === '' || strlen($source) > 512 || strlen($sender) > 320
      || strlen($subject) > 512 || strlen($recipient) > 1000
      || ($body !== null && (!is_string($body) || strlen($body) > 2097152))
      || !preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/D', $received)) {
      throw new RuntimeException('Ongeldig functioneel e-mailbericht.');
    }
    $hash = hash('sha256', $source);
    $sql = "INSERT INTO office_mail_messages
      (mailbox_id, source_id, source_hash, sender, recipient, subject, received_at, body_text)
      VALUES (:mailbox, :source, :hash, :sender, :recipient, :subject, :received, :body)
      ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':mailbox' => $mailboxId, ':source' => $source, ':hash' => $hash,
      ':sender' => $sender, ':recipient' => $recipient, ':subject' => $subject,
      ':received' => $received, ':body' => $body,
    ]);
    $messageId = (int) $this->db->lastInsertId();
    if (getenv('OFFICE_MAIL_BOARD_SCHEMA_VERIFIED') === 'enabled') {
      require_once __DIR__ . '/OfficeMailRoutingSuggestions.php';
      $suggestion = OfficeMailRoutingSuggestions::suggest($subject, is_string($body) ? $body : '');
      $folderSuggestion = $message['suggested_module'] ?? null;
      if ($folderSuggestion !== null) {
        if (!in_array($folderSuggestion, ['crm', 'finance'], true)) {
          throw new RuntimeException('Ongeldige maproutering.');
        }
        $suggestion = ['module' => $folderSuggestion, 'reason' => 'Voorstel op basis van gecontroleerde Zoho-map'];
      }
      // Never overwrite a manual move or a previously recorded suggestion.
      $route = $this->db->prepare("INSERT IGNORE INTO office_mail_board_routes
        (message_id,module_key,suggested_module,suggestion_reason,updated_at)
        VALUES (:message,:module,:suggested,:reason,UTC_TIMESTAMP())");
      $route->execute([
        ':message' => $messageId,
        ':module' => $suggestion['module'],
        ':suggested' => $suggestion['module'],
        ':reason' => $suggestion['reason'],
      ]);
    }
    return $messageId;
  }
}
