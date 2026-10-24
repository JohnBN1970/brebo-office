<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use Brebo\Office\Workflow\OfficeOpportunityTransitionGuard;
use PDO;
use RuntimeException;

/**
 * Internal writer only: HTTP controller must separately enforce session,
 * edit permission, CSRF, schema preflight and the write feature flag.
 * Requires a pre-provisioned office_crm_opportunity_stage_audit table.
 */
final class OfficeCrmOpportunityTransitionWriter {
  public function __construct(private PDO $db, private string $opportunityTable = 'office_crm_opportunities', private string $auditTable = 'office_crm_opportunity_stage_audit') {
    foreach ([$this->opportunityTable, $this->auditTable] as $table) {
      if (!preg_match('/^office_crm_[a-z0-9_]+$/D', $table)) throw new RuntimeException('Ongeldige tabelnaam');
    }
  }

  /** @param list<string> $completedChecks */
  public function transition(int $id, string $expectedStage, string $nextStage, array $completedChecks, string $operatorId): void {
    if ($id < 1 || $operatorId === '' || strlen($operatorId) > 128 || strlen($expectedStage) > 32 || strlen($nextStage) > 32) {
      throw new RuntimeException('Ongeldige statuswijziging');
    }
    if ($this->db->inTransaction()) throw new RuntimeException('Bestaande transactie niet ondersteund');
    $this->db->beginTransaction();
    try {
      $query = $this->db->prepare('SELECT stage FROM `' . $this->opportunityTable . '` WHERE id = :id FOR UPDATE');
      $query->execute([':id' => $id]);
      $current = $query->fetchColumn();
      if (!is_string($current) || !hash_equals($current, $expectedStage)) {
        throw new RuntimeException('Kans gewijzigd of niet gevonden');
      }
      OfficeOpportunityTransitionGuard::requireAllowed($current, $nextStage, $completedChecks);
      $update = $this->db->prepare('UPDATE `' . $this->opportunityTable . '` SET stage = :next WHERE id = :id AND stage = :current');
      $update->execute([':next' => $nextStage, ':id' => $id, ':current' => $current]);
      if ($update->rowCount() !== 1) throw new RuntimeException('Statuswijziging niet opgeslagen');
      $audit = $this->db->prepare('INSERT INTO `' . $this->auditTable . '` (opportunity_id, previous_stage, next_stage, operator_id, completed_checks_json) VALUES (:id, :previous, :next, :operator, :checks)');
      $json = json_encode(array_values($completedChecks), JSON_THROW_ON_ERROR);
      $audit->execute([':id' => $id, ':previous' => $current, ':next' => $nextStage, ':operator' => $operatorId, ':checks' => $json]);
      $this->db->commit();
    } catch (\Throwable $exception) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $exception;
    }
  }
}
