<?php

declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/**
 * Native Office staging store: proposals are never dossier mutations.
 * Requires the office_mail_push_proposals table from the accompanying SQL.
 */
final class OfficeMailPushProposalRepository {
  public function __construct(private readonly PDO $db) {}

  /**
   * @param array<string,mixed> $proposal
   * @return array<string,mixed>
   */
  /** @return list<array<string,mixed>> */
  public function pendingForReviewer(int $reviewerUid, bool $mayReviewAll = FALSE, int $limit = 50): array {
    if ($reviewerUid <= 0) {
      throw new RuntimeException('Office-gebruiker ontbreekt.');
    }
    $limit = max(1, min(100, $limit));
    // Personal proposals stay owner-only unless Office authorization
    // explicitly grants organization-wide review.
    $sql = 'SELECT id, owner_uid, actor_uid, subject, status, created_at '
      . 'FROM office_mail_push_proposals WHERE status = :status';
    if (!$mayReviewAll) {
      $sql .= ' AND owner_uid = :owner';
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;
    $stmt = $this->db->prepare($sql);
    $params = [':status' => 'pending_review'];
    if (!$mayReviewAll) {
      $params[':owner'] = $reviewerUid;
    }
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  public function stage(array $proposal): array {
    $source = trim((string) ($proposal['source_id'] ?? ''));
    $owner = (int) ($proposal['owner_uid'] ?? 0);
    $actor = (int) ($proposal['actor_uid'] ?? 0);
    $subject = trim((string) ($proposal['subject'] ?? ''));
    if ($source === '' || $owner <= 0 || $actor !== $owner) {
      throw new RuntimeException('Ongeldige persoonlijke mailoverdracht.');
    }
    $identity = hash('sha256', $owner . ':' . $source);
    $sql = 'INSERT INTO office_mail_push_proposals '
      . '(identity_hash, source_id, owner_uid, actor_uid, subject, status, created_at, updated_at) '
      . 'VALUES (:identity, :source, :owner, :actor, :subject, :status, UTC_TIMESTAMP(), UTC_TIMESTAMP()) '
      . 'ON DUPLICATE KEY UPDATE updated_at = updated_at';
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':identity' => $identity,
      ':source' => $source,
      ':owner' => $owner,
      ':actor' => $actor,
      ':subject' => $subject,
      ':status' => 'pending_review',
    ]);
    $read = $this->db->prepare('SELECT id, status FROM office_mail_push_proposals WHERE identity_hash = :identity');
    $read->execute([':identity' => $identity]);
    $row = $read->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      throw new RuntimeException('Mailvoorstel kon niet worden teruggelezen.');
    }
    return ['id' => (int) $row['id'], 'status' => (string) $row['status']];
  }
}
