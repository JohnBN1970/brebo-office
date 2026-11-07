<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use RuntimeException;

/**
 * Explicit review-only approval gate. No implicit user permissions or writes.
 * Caller must authenticate actor; callback must verify management access to the actual target building.
 * Database tables must already exist after separately approved migration.
 */
final class OfficeResidentAddressApproval {
  public function __construct(private readonly PDO $db) {}

  public function approve(int $proposalId, int $addressId, string $actorId, callable $canManageBuilding): array {
    if (trim($actorId) === '') {
      throw new RuntimeException('Not authorized to approve resident data');
    }
    if ($proposalId < 1 || $addressId < 1) throw new \InvalidArgumentException('Invalid identity');
    $this->db->beginTransaction();
    try {
      $stmt = $this->db->prepare(
        'SELECT id, candidate_building_id, candidate_address_id, status, proposed_resident_json '
        . 'FROM office_address_intake_proposals WHERE id = ? FOR UPDATE'
      );
      $stmt->execute([$proposalId]);
      $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$proposal || $proposal['status'] !== 'needs_review') {
        throw new RuntimeException('Proposal is not awaiting review');
      }
      $stmt = $this->db->prepare(
        'SELECT a.id, a.building_id FROM office_building_addresses a WHERE a.id = ?'
      );
      $stmt->execute([$addressId]);
      $address = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$address) throw new RuntimeException('Target address not found');
      if ($canManageBuilding($actorId, (string)$address['building_id']) !== true) {
        throw new RuntimeException('Not authorized for target building');
      }
      if ($proposal['candidate_building_id'] !== null
        && (string)$proposal['candidate_building_id'] !== (string)$address['building_id']) {
        throw new RuntimeException('Building mismatch');
      }
      if ($proposal['candidate_address_id'] !== null
        && (string)$proposal['candidate_address_id'] !== (string)$addressId) {
        throw new RuntimeException('Address mismatch');
      }
      // Approval does not silently overwrite any existing resident.
      // Approval of a proposal only records its verified destination;
      // subsequent resident creation is a separate explicit action.
      $update = $this->db->prepare(
        "UPDATE office_address_intake_proposals SET status = 'approved', "
        . 'candidate_building_id = ?, candidate_address_id = ?, reviewed_by = ?, reviewed_at = NOW() '
        . "WHERE id = ? AND status = 'needs_review'"
      );
      $update->execute([$address['building_id'], $addressId, $actorId, $proposalId]);
      if ($update->rowCount() !== 1) throw new RuntimeException('Approval race');
      $audit = $this->db->prepare(
        'INSERT INTO office_address_resident_audit (entity_type, entity_id, action, actor_id) '
        . "VALUES ('proposal', ?, 'approve', ?)"
      );
      $audit->execute([$proposalId, $actorId]);
      $this->db->commit();
      return ['status' => 'approved', 'proposal_id' => $proposalId, 'address_id' => $addressId];
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
