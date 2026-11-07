<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use RuntimeException;

/**
 * Explicit, authorized materialization of an approved resident proposal.
 * Never replaces existing resident rows. No automatic execution on intake.
 * Caller authenticates actor; callback verifies current building permission.
 */
final class OfficeResidentAddressMaterializer {
  public function __construct(private readonly PDO $db) {}

  public function materialize(int $proposalId, string $actorId, callable $canManageBuilding): array {
    if ($proposalId < 1 || trim($actorId) === '') throw new \InvalidArgumentException('Missing actor or proposal');
    $this->db->beginTransaction();
    try {
      $query = $this->db->prepare(
        'SELECT id, status, candidate_building_id, candidate_address_id, proposed_resident_json '
        . 'FROM office_address_intake_proposals WHERE id = ? FOR UPDATE'
      );
      $query->execute([$proposalId]);
      $proposal = $query->fetch(PDO::FETCH_ASSOC);
      if (!$proposal || $proposal['status'] !== 'approved') throw new RuntimeException('Proposal is not approved');
      $addressId = (int)($proposal['candidate_address_id'] ?? 0);
      if ($addressId < 1) throw new RuntimeException('Approved address missing');
      $query = $this->db->prepare('SELECT id, building_id FROM office_building_addresses WHERE id = ?');
      $query->execute([$addressId]);
      $address = $query->fetch(PDO::FETCH_ASSOC);
      if (!$address || (string)$address['building_id'] !== (string)$proposal['candidate_building_id']) {
        throw new RuntimeException('Approved building mismatch');
      }
      if ($canManageBuilding($actorId, (string)$address['building_id']) !== true) {
        throw new RuntimeException('Not authorized for target building');
      }
      $data = json_decode((string)($proposal['proposed_resident_json'] ?? 'null'), true, 32, JSON_THROW_ON_ERROR);
      if (!is_array($data)) throw new RuntimeException('No resident data in proposal');
      $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : null;
      $phone = isset($data['phone']) && is_string($data['phone']) ? trim($data['phone']) : null;
      $email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : null;
      if ($name === '') $name = null;
      if ($phone === '') $phone = null;
      if ($email === '') $email = null;
      if ($name === null && $phone === null && $email === null) {
        throw new RuntimeException('Resident identity not supplied');
      }
      if (($name !== null && mb_strlen($name) > 160)
        || ($phone !== null && mb_strlen($phone) > 40)
        || ($email !== null && (mb_strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)))) {
        throw new RuntimeException('Invalid resident data');
      }
      // No resident can be inserted if the address already has one. Review required.
      $query = $this->db->prepare('SELECT id FROM office_address_residents WHERE address_id = ? LIMIT 1 FOR UPDATE');
      $query->execute([$addressId]);
      if ($query->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('Resident already exists: manual review required');
      $insert = $this->db->prepare(
        'INSERT INTO office_address_residents (address_id, resident_name, phone, email) VALUES (?, ?, ?, ?)'
      );
      $insert->execute([$addressId, $name, $phone, $email]);
      $residentId = (int)$this->db->lastInsertId();
      $update = $this->db->prepare(
        "UPDATE office_address_intake_proposals SET status = 'materialized' WHERE id = ? AND status = 'approved'"
      );
      $update->execute([$proposalId]);
      if ($update->rowCount() !== 1) throw new RuntimeException('Materialization race');
      $audit = $this->db->prepare(
        'INSERT INTO office_address_resident_audit (entity_type, entity_id, action, actor_id) '
        . "VALUES ('resident', ?, 'create_from_approved_proposal', ?)"
      );
      $audit->execute([$residentId, $actorId]);
      $this->db->commit();
      return ['status' => 'materialized', 'resident_id' => $residentId, 'address_id' => $addressId];
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
