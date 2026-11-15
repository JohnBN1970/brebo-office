<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;

/** Finance-ACL scoped caller only. Read-only posted actual labour costs. */
final class OfficeActualLabourCostProjectReader
{
    public function __construct(private PDO $db) {}
    public function forProject(int $projectId): ?array
    {
        if ($projectId < 1) throw new \InvalidArgumentException('Invalid project');
        $q=$this->db->prepare('SELECT COUNT(*) AS entries, COALESCE(SUM(amount_cents),0) AS total_cents,
            COALESCE(SUM(approved_seconds),0) AS total_seconds
            FROM office_inzet_actual_labour_costs WHERE project_id=?');
        $q->execute([$projectId]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !ctype_digit((string)$row['entries'])
            || !ctype_digit((string)$row['total_cents'])
            || !ctype_digit((string)$row['total_seconds'])
            || (float)$row['total_cents']>PHP_INT_MAX
            || (float)$row['total_seconds']>PHP_INT_MAX) {
            throw new \DomainException('Invalid posted labour cost totals');
        }
        return ['entries'=>(int)$row['entries'],'actual_cost_cents'=>(int)$row['total_cents'],
            'approved_seconds'=>(int)$row['total_seconds']];
    }
}
