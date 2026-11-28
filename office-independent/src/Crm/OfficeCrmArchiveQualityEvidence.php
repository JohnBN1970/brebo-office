<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;
require_once __DIR__.'/OfficeCrmProjectHandoff.php';

final class OfficeCrmArchiveQualityEvidence
{
    public function __construct(private readonly PDO $db) {}

    /** Partial evidence only: project task and service sources still require integration. */
    public function knownRestpointsAndCrmActivitiesClosed(int $relationId): bool
    {
        if ($relationId < 1) return false;
        $q = $this->db->prepare('SELECT 1 FROM office_crm_opportunity_activities a JOIN office_crm_opportunities o ON o.id=a.opportunity_id WHERE o.relation_id=? AND a.completed_at IS NULL LIMIT 1');
        $q->execute([$relationId]);
        if ($q->fetchColumn()) return false;
        $q = $this->db->prepare("SELECT id FROM office_crm_opportunities WHERE relation_id=? AND stage='won' AND status='closed'");
        $q->execute([$relationId]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return false;
        $handoff = new OfficeCrmProjectHandoff($this->db);
        $check = $this->db->prepare("SELECT 1 FROM office_quality_checks WHERE project_id=? AND status<>'accepted' LIMIT 1");
        foreach ($ids as $id) {
            $projectId = $handoff->projectId((int)$id);
            if ($projectId === null) return false;
            $check->execute([$projectId]);
            if ($check->fetchColumn()) return false;
        }
        return true;
    }
}
