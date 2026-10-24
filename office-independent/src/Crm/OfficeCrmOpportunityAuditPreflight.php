<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;
use RuntimeException;

/**
 * Read-only preflight for CRM opportunity stage audit.
 * Does not create, alter or drop any table.
 */
final class OfficeCrmOpportunityAuditPreflight {
  /** @return array{opportunities:bool,audit_exists:bool,audit_columns:bool,engine:bool} */
  public static function inspect(PDO $db): array {
    $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') throw new RuntimeException('Geen database geselecteerd');
    $tables = $db->prepare("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME IN ('office_crm_opportunities','office_crm_opportunity_stage_audit')");
    $tables->execute([':schema' => $database]);
    $found = [];
    foreach ($tables->fetchAll(PDO::FETCH_ASSOC) as $row) $found[(string)$row['TABLE_NAME']] = (string)$row['ENGINE'];
    $opps = ($found['office_crm_opportunities'] ?? '') === 'InnoDB';
    $auditExists = isset($found['office_crm_opportunity_stage_audit']);
    $columnsOk = false;
    if ($auditExists) {
      $columns = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'office_crm_opportunity_stage_audit'");
      $columns->execute([':schema' => $database]);
      $actual = $columns->fetchAll(PDO::FETCH_COLUMN);
      $required = ['id','opportunity_id','previous_stage','next_stage','operator_id','completed_checks_json','created_at'];
      $columnsOk = count(array_diff($required, $actual)) === 0;
    }
    return [
      'opportunities' => $opps,
      'audit_exists' => $auditExists,
      'audit_columns' => $columnsOk,
      'engine' => !$auditExists || $found['office_crm_opportunity_stage_audit'] === 'InnoDB',
    ];
  }
}
