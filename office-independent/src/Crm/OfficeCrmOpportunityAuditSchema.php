<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

/**
 * Proposal only. Never executed automatically or during a request.
 * Must be reviewed against the actual existing opportunity schema first.
 */
final class OfficeCrmOpportunityAuditSchema {
  public static function statement(): string {
    return "CREATE TABLE office_crm_opportunity_stage_audit (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      opportunity_id BIGINT UNSIGNED NOT NULL,
      previous_stage VARCHAR(32) NOT NULL,
      next_stage VARCHAR(32) NOT NULL,
      operator_id VARCHAR(128) NOT NULL,
      completed_checks_json TEXT NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_crm_stage_audit_opportunity (opportunity_id, id),
      CONSTRAINT fk_crm_stage_audit_opportunity FOREIGN KEY (opportunity_id)
        REFERENCES office_crm_opportunities(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  }
}
