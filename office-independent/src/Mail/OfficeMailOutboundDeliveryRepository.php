<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/**
 * Persistent outbound delivery ledger in the existing Office database.
 * No mail is sent by this repository.
 */
final class OfficeMailOutboundDeliveryRepository {
  public function __construct(private readonly PDO $db) {}

  public function schemaReady(): bool {
    $required = [
      'id', 'idempotency_key', 'mailbox_id', 'operator_id', 'recipient',
      'subject', 'body_sha256', 'status', 'transport_reference',
      'created_at', 'updated_at',
    ];
    $stmt = $this->db->prepare(
      'SELECT COLUMN_NAME FROM information_schema.COLUMNS ' .
      'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table'
    );
    $stmt->execute([':table'=>'office_mail_outbound_deliveries']);
    $found = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    foreach ($required as $column) {
      if (!isset($found[$column])) return false;
    }
    return true;
  }

  /**
   * Reserve one outbound attempt before handing it to a transport.
   * A repeated request cannot silently send the same message twice.
   * The caller must enforce authenticated sender permissions first.
   */
  public function reserve(string $key, int $mailboxId, string $operatorId, array $draft): int {
    if (!$this->schemaReady()) throw new RuntimeException('Verzendregistratie is niet geinstalleerd.');
    if (!preg_match('/^[a-f0-9]{64}$/D', $key) || $mailboxId <= 0 || $operatorId === '') {
      throw new RuntimeException('Ongeldige verzendaanvraag.');
    }
    $stmt = $this->db->prepare(
      "INSERT INTO office_mail_outbound_deliveries
       (idempotency_key,mailbox_id,operator_id,recipient,subject,body_sha256,status,created_at,updated_at)
       VALUES (:key,:mailbox,:operator,:recipient,:subject,:hash,'reserved',UTC_TIMESTAMP(),UTC_TIMESTAMP())"
    );
    try {
      $stmt->execute([
        ':key'=>$key, ':mailbox'=>$mailboxId, ':operator'=>$operatorId,
        ':recipient'=>(string)$draft['recipient'],
        ':subject'=>(string)$draft['subject'],
        ':hash'=>hash('sha256',(string)$draft['body']),
      ]);
    } catch (\PDOException $e) {
      // Unique idempotency keys must never be retried as a fresh delivery.
      if ($e->getCode() === '23000') throw new RuntimeException('Verzendaanvraag is al geregistreerd.', 0, $e);
      throw $e;
    }
    return (int)$this->db->lastInsertId();
  }

  public function markAccepted(int $id, string $reference): void {
    if ($id <= 0 || $reference === '' || strlen($reference) > 255) {
      throw new RuntimeException('Ongeldige transportbevestiging.');
    }
    $stmt = $this->db->prepare(
      "UPDATE office_mail_outbound_deliveries
       SET status='accepted',transport_reference=:reference,updated_at=UTC_TIMESTAMP()
       WHERE id=:id AND status='reserved'"
    );
    $stmt->execute([':id'=>$id, ':reference'=>$reference]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Verzendstatus kon niet worden bevestigd.');
  }
}
