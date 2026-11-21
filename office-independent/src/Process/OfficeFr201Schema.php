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
