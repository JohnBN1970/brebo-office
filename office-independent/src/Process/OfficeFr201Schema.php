<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * Additive FR-201 supplement storage for existing CRM leads.
 * Migration must be executed explicitly; no runtime DDL.
 */
final class OfficeFr201Schema
{
    /**
     * Read-only deployment preflight. The feature flag may be enabled only
     * after both tables and the revision/audit constraints are present.
     * @return array{ready:bool,missing:list<string>}
     */
    public static function verify(\PDO $db): array
    {
        $missing = [];
        $expected = [
            'office_crm_lead_fr201' => ['lead_id','document_code','document_version','revision','data_json','updated_by','updated_at'],
            'office_crm_lead_fr201_events' => ['id','lead_id','revision','actor_operator_id','reason','data_json','created_at'],
        ];
        foreach ($expected as $table => $columns) {
            try {
                $stmt = $db->query('SHOW COLUMNS FROM `'.$table.'`');
                $actual = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                foreach ($columns as $column) {
                    if (!in_array($column, $actual, true)) $missing[] = $table.'.'.$column;
                }
            } catch (\PDOException $e) {
                $missing[] = $table;
            }
        }
        try {
            $stmt = $db->query("SHOW INDEX FROM office_crm_lead_fr201_events");
            $indexes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $unique = [];
            foreach ($indexes as $index) {
                if ((int)$index['Non_unique'] !== 0) continue;
                $unique[$index['Key_name']][(int)$index['Seq_in_index']] = $index['Column_name'];
            }
            $found = false;
            foreach ($unique as $cols) {
                ksort($cols);
                if (array_values($cols) === ['lead_id','revision']) { $found = true; break; }
            }
            if (!$found) $missing[] = 'office_crm_lead_fr201_events.unique(lead_id,revision)';
        } catch (\PDOException $e) {
            $missing[] = 'office_crm_lead_fr201_events.indexes';
        }
        // Verify referential integrity: orphan supplements and events must be impossible.
        foreach (['office_crm_lead_fr201','office_crm_lead_fr201_events'] as $table) {
            try {
                $stmt = $db->query("SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$table."' AND COLUMN_NAME='lead_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
                $references = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (!in_array('office_crm_leads', $references, true)) {
                    $missing[] = $table.'.foreign_key(lead_id)';
                }
            } catch (\PDOException $e) {
                $missing[] = $table.'.foreign_key(lead_id)';
            }
        }
        // The lead reference must match the parent key type exactly; mismatched
        // signedness prevents reliable FK installation on MySQL.
        try {
            $stmt = $db->query("SELECT TABLE_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((COLUMN_NAME='lead_id' AND TABLE_NAME IN ('office_crm_lead_fr201','office_crm_lead_fr201_events')) OR (COLUMN_NAME='id' AND TABLE_NAME='office_crm_leads'))");
            $typeRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($typeRows) !== 3) $missing[] = 'lead_id.column_types.incomplete';
            foreach ($typeRows as $column) {
                if (strtolower((string)$column['COLUMN_TYPE']) !== 'bigint unsigned') {
                    $missing[] = $column['TABLE_NAME'].'.'.($column['TABLE_NAME'] === 'office_crm_leads' ? 'id' : 'lead_id').'.type';
                }
            }
        } catch (\PDOException $e) {
            $missing[] = 'lead_id.column_types';
        }
        return ['ready'=>$missing === [],'missing'=>$missing];
    }

    /** @return list<string> */
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_crm_lead_fr201 (
                lead_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                document_code VARCHAR(16) NOT NULL DEFAULT 'FR-201',
                document_version VARCHAR(16) NOT NULL DEFAULT '1.0',
                revision INT UNSIGNED NOT NULL DEFAULT 1,
                data_json LONGTEXT NOT NULL,
                updated_by VARCHAR(128) NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_crm_lead_fr201_lead FOREIGN KEY (lead_id) REFERENCES office_crm_leads(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_lead_fr201_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                lead_id BIGINT UNSIGNED NOT NULL,
                revision INT UNSIGNED NOT NULL,
                actor_operator_id VARCHAR(128) NOT NULL,
                reason VARCHAR(500) NOT NULL,
                data_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_crm_lead_fr201_event_revision (lead_id, revision),
                CONSTRAINT fk_crm_lead_fr201_event_lead FOREIGN KEY (lead_id) REFERENCES office_crm_leads(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
