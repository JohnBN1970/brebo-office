<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Server-side mapping of Office string identities to Calc numeric actor IDs. */
final class OfficeCalcActorIdRepository {
  public function __construct(private readonly PDO $db) {}

  public function findActorId(string $operatorId): ?int {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      return null;
    }
    $stmt = $this->db->prepare(
      'SELECT m.calc_actor_id FROM office_calc_actor_mapping m
       JOIN office_operator_accounts a ON a.id = m.operator_id
       WHERE m.operator_id = :operator_id AND a.active = 1 LIMIT 1'
    );
    $stmt->execute([':operator_id' => $operatorId]);
    $value = $stmt->fetchColumn();
    if ($value === false || !ctype_digit((string) $value)) {
      return null;
    }
    $actorId = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $actorId === false ? null : $actorId;
  }
}
