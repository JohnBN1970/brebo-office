<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Read-only, project-scoped review queue.
 * The caller must authenticate and authorize access to the requested project.
 * Does not infer OnSite hours or payable amounts from claims.
 */
final class OfficeExternalHoursClaimsReviewRepository
{
    public function __construct(private PDO $db) {}

    public function forProject(int $projectId, int $limit = 100, int $beforeId = PHP_INT_MAX): array
    {
        if ($projectId < 1 || $limit < 1 || $limit > 100 || $beforeId < 1) {
            throw new \DomainException('Invalid review query scope');
        }
        $stmt=$this->db->prepare('SELECT id,project_id,person_id,work_date,claimed_seconds,
            supplier_reference,source_document_sha256,source_line_reference,source_line_sha256,
            imported_by,imported_at
            FROM office_inzet_external_hours_claims
            WHERE project_id=:project AND id<:before
            ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':project',$projectId,PDO::PARAM_INT);
        $stmt->bindValue(':before',$beforeId,PDO::PARAM_INT);
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static function(array $row):array {
            foreach (['id','project_id','person_id','claimed_seconds'] as $field) {
                $row[$field]=(int)$row[$field];
            }
            $row['review_status']='pending_reconciliation';
            return $row;
        },$rows);
    }
}
