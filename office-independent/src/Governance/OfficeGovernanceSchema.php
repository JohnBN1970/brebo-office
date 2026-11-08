<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

/** Reviewable additive migration. No execution from web requests, no automatic grants. */
final class OfficeGovernanceSchema
{
    public static function statements(string $driver='mysql'): array
    {
        if (!in_array($driver,['mysql','sqlite'],true)) throw new \InvalidArgumentException('Unsupported database.');
        $pk=$driver==='mysql' ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail=$driver==='mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        $out=[];
        foreach (['office_quality_checks','office_risks','office_procurement_orders'] as $table) {
            $out[]="CREATE TABLE $table (
                id $pk, project_id BIGINT UNSIGNED NOT NULL, building_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL,
                payload_json MEDIUMTEXT NOT NULL, revision INTEGER NOT NULL,
                created_by VARCHAR(128) NOT NULL, updated_by VARCHAR(128) NOT NULL,
                created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                FOREIGN KEY (project_id,building_id) REFERENCES office_project_buildings(project_id,building_id)
            )$tail";
            $out[]="CREATE INDEX idx_{$table}_context ON $table (building_id,project_id,status)";
        }
        $out[]="CREATE TABLE office_governance_commands (
            operator_id VARCHAR(128) NOT NULL, command_key VARCHAR(64) NOT NULL,
            fingerprint CHAR(64) NOT NULL, result_json TEXT NOT NULL,
            PRIMARY KEY(operator_id,command_key)
        )$tail";
        $out[]="CREATE TABLE office_governance_audit (
            id $pk, module VARCHAR(32) NOT NULL, record_id BIGINT NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL, building_id BIGINT UNSIGNED NOT NULL,
            revision INTEGER NOT NULL, operator_id VARCHAR(128) NOT NULL,
            snapshot_json MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL,
            UNIQUE(module,record_id,revision)
        )$tail";
        return $out;
    }
}
