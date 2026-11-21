<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/**
 * Read-only sent-delivery listing, scoped to the authenticated Office operator.
 * "accepted" means transport acceptance, not recipient delivery.
 */
final class OfficeMailSentRepository {
  public function __construct(private readonly PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function accepted(string $operatorId, int $limit = 50, int $offset = 0): array {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      throw new RuntimeException('Office-sessie ontbreekt.');
    }
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $sql = "SELECT d.id,d.mailbox_id,d.recipient,d.subject,d.transport_reference,d.created_at,d.updated_at,
      b.address AS sender_address
      FROM office_mail_outbound_deliveries d
      INNER JOIN office_mail_mailboxes b ON b.id=d.mailbox_id
      WHERE d.status='accepted' AND b.active=1
      AND ((b.mailbox_type='personal' AND b.owner_operator_id=:owner)
        OR (b.mailbox_type='functional' AND EXISTS
          (SELECT 1 FROM office_mail_mailbox_access a
           WHERE a.mailbox_id=b.id AND a.operator_id=:operator AND a.can_read=1)))
      ORDER BY d.created_at DESC,d.id DESC
      LIMIT $limit OFFSET $offset";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':owner'=>$operatorId,':operator'=>$operatorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
