<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/** Native mailbox data access; no Drupal bootstrap or user IDs. */
final class OfficeNativeMailboxRepository {
  public function __construct(private readonly PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function visibleMailboxes(int $operatorId): array {
    if ($operatorId <= 0) throw new RuntimeException('Office-sessie ontbreekt.');
    $sql = "SELECT id, address, label, mailbox_type FROM office_mail_mailboxes
      WHERE active = 1 AND ((mailbox_type = 'functional' AND EXISTS
        (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id = office_mail_mailboxes.id
         AND a.operator_id = :owner AND a.can_read = 1))
        OR (mailbox_type = 'personal' AND owner_operator_id = :personal_owner))
      ORDER BY mailbox_type, label";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':owner' => $operatorId, ':personal_owner' => $operatorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  /** @return list<array<string,mixed>> */
  public function inbox(int $mailboxId, int $operatorId, int $limit = 50): array {
    if ($mailboxId <= 0 || $operatorId <= 0) throw new RuntimeException('Ongeldige mailboxselectie.');
    $limit = max(1, min(100, $limit));
    $sql = "SELECT m.id, m.sender, m.subject, m.received_at, m.classification_status
      FROM office_mail_messages m
      INNER JOIN office_mail_mailboxes b ON b.id = m.mailbox_id
      WHERE b.id = :mailbox AND b.active = 1
      AND ((b.mailbox_type = 'functional' AND EXISTS
        (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id = b.id
         AND a.operator_id = :operator AND a.can_read = 1))
        OR (b.mailbox_type = 'personal' AND b.owner_operator_id = :owner))
      ORDER BY m.received_at DESC, m.id DESC LIMIT " . $limit;
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':mailbox' => $mailboxId, ':operator' => $operatorId, ':owner' => $operatorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  /** Only an active functional mailbox may be ingested by background jobs. */
  public function assertFunctionalIngestAllowed(int $mailboxId): void {
    $stmt = $this->db->prepare(
      "SELECT id FROM office_mail_mailboxes WHERE id = :id AND active = 1 AND mailbox_type = 'functional'"
    );
    $stmt->execute([':id' => $mailboxId]);
    if ($stmt->fetchColumn() === false) throw new RuntimeException('Automatische persoonlijke mailboximport is verboden.');
  }
}
