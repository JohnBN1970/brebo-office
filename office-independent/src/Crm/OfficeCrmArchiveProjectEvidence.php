<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;
require_once __DIR__.'/OfficeCrmProjectHandoff.php';

/** Read-only partial lifecycle evidence; not an archival authorization. */
final class OfficeCrmArchiveProjectEvidence
{
    public function __construct(private readonly PDO $db) {}

    public function allOrdersAndProjectsCompleted(int $relationId): bool
    {
        if ($relationId < 1) return false;
        $q = $this->db->prepare('SELECT id, stage, status FROM office_crm_opportunities WHERE relation_id=?');
        $q->execute([$relationId]);
        $deals = $q->fetchAll(PDO::FETCH_ASSOC);
        $handoff = new OfficeCrmProjectHandoff($this->db);
        $project = $this->db->prepare('SELECT status FROM office_projects WHERE id=?');
        $closure = $this->db->prepare("SELECT status FROM office_process_form_instances WHERE context_type='project' AND context_id=? AND document_code='CL-600' ORDER BY id DESC");
        $seen = false;
        foreach ($deals as $deal) {
            if ($deal['status'] === 'closed' && $deal['stage'] === 'lost') continue;
            if ($deal['status'] !== 'closed' || $deal['stage'] !== 'won') return false;
            $id = $handoff->projectId((int)$deal['id']);
            if ($id === null) return false;
            $project->execute([$id]);
            $status = $project->fetchColumn();
            if (!is_string($status) || !in_array(strtolower(trim($status)), ['gereed','completed'], true)) return false;
            $closure->execute([$id]);
            $statuses = $closure->fetchAll(PDO::FETCH_COLUMN);
            if (!$statuses || count(array_filter($statuses, static fn($status) => $status !== 'akkoord')) !== 0) return false;
            $seen = true;
        }
        return $seen;
    }

    public function noOpenCrmWork(int $relationId): bool
    {
        if ($relationId < 1) return false;
        $q = $this->db->prepare("SELECT 1 FROM office_crm_leads WHERE relation_id=? AND status='active' LIMIT 1");
        $q->execute([$relationId]);
        if ($q->fetchColumn()) return false;
        $q = $this->db->prepare("SELECT 1 FROM office_crm_opportunities WHERE relation_id=? AND (status<>'closed' OR stage NOT IN ('won','lost')) LIMIT 1");
        $q->execute([$relationId]);
        return !$q->fetchColumn();
    }
}
