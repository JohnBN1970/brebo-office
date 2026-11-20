<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;
use PDOException;

/**
 * Read-only link lookup. A won stage is not proof that a native project exists.
 * Supports both calculation acceptance and direct-order transfer mappings.
 */
final class OfficeCrmProjectHandoff
{
    public function __construct(private readonly PDO $db) {}

    public function projectId(int $opportunityId): ?int
    {
        if ($opportunityId < 1) return null;

        $ids = [];
        $mappingCount = 0;
        $orphan = false;
        foreach ([
            ['office_calc_crm_acceptance_transfers', 'crm_opportunity_id', 'office_project_id'],
            ['office_crm_direct_project_transfers', 'opportunity_id', 'project_id'],
        ] as [$table, $key, $projectKey]) {
            try {
                // Static SQL identifiers only; verify that a mapped project still exists.
                $sql = "SELECT t.{$projectKey} AS mapped_id, p.id AS existing_id FROM {$table} t LEFT JOIN office_projects p ON p.id=t.{$projectKey} WHERE t.{$key}=? LIMIT 2";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$opportunityId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $mappingCount++;
                    $id = (int)($row['existing_id'] ?? 0);
                    if ($id > 0) $ids[$id] = true;
                    else $orphan = true;
                }
            } catch (PDOException $e) {
                // Optional mapping may not yet be installed. Never invent a link.
                $code = (string)$e->getCode();
                $message = $e->getMessage();
                $missing = $code === '42S02' || ($code === 'HY000' && str_contains($message, 'no such table'));
                if (!$missing) throw $e;
            }
        }
        // A single opportunity must never have both a Calc and direct transfer,
        // even when they point to the same project. Fail closed on duplicates.
        return !$orphan && $mappingCount === 1 && count($ids) === 1 ? (int)array_key_first($ids) : null;
    }
}
