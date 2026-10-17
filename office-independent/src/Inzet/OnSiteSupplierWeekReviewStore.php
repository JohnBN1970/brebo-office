<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Append-only supplier-week decision persistence.
 * Caller must authenticate reviewer and authorize supplier access before calling.
 */
final class OnSiteSupplierWeekReviewStore {
  public function __construct(private PDO $db) {}

  /**
   * @param array<string,mixed> $decision validated decision DTO
   * @return int inserted audit decision ID
   */
  public function append(int $importId, array $decision): int {
    if ($importId < 1 || ($decision['recorded'] ?? null) !== false
      || ($decision['invoice_eligible'] ?? null) !== false) {
      throw new \InvalidArgumentException('Unvalidated review decision.');
    }
    $supplierId = $decision['supplier_id'] ?? null;
    $reviewerId = $decision['reviewer_id'] ?? null;
    $action = $decision['action'] ?? null;
    $reason = $decision['reason'] ?? null;
    $hash = $decision['source_sha256'] ?? null;
    if (!is_int($supplierId) || $supplierId < 1 || !is_int($reviewerId) || $reviewerId < 1
      || !in_array($action,['approve','reject','request_correction'],true)
      || !is_string($reason) || ($action !== 'approve' && trim($reason) === '')
      || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)) {
      throw new \InvalidArgumentException('Invalid review fields.');
    }
    $this->db->beginTransaction();
    try {
      $lookup = $this->db->prepare(
        'SELECT supplier_id, source_sha256 FROM office_inzet_supplier_week_imports WHERE id = :id FOR UPDATE'
      );
      $lookup->execute(['id'=>$importId]);
      $source = $lookup->fetch(PDO::FETCH_ASSOC);
      if (!$source || (int)$source['supplier_id'] !== $supplierId
        || !hash_equals((string)$source['source_sha256'],$hash)) {
        throw new \DomainException('Review source does not match registered supplier import.');
      }
      $insert = $this->db->prepare(
        'INSERT INTO office_inzet_supplier_week_decisions (import_id, reviewer_id, action, reason, source_sha256)
         VALUES (:import_id, :reviewer_id, :action, :reason, :source_sha256)'
      );
      $insert->execute([
        'import_id'=>$importId,'reviewer_id'=>$reviewerId,'action'=>$action,
        'reason'=>$reason,'source_sha256'=>$hash,
      ]);
      $id = (int)$this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
