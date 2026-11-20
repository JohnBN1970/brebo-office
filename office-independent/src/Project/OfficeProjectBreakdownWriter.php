<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

/**
 * Project-scoped WBS/RBS writes. Authorization and CSRF must be checked by caller.
 * No source ledger records are created or modified.
 */
final class OfficeProjectBreakdownWriter
{
    public function __construct(private readonly PDO $db) {}

    public function create(int $projectId, int $operatorId, string $kind, string $code, string $title, ?int $parentId = null, string $description = ''): int
    {
        if ($projectId < 1 || $operatorId < 1 || !in_array($kind, ['wbs', 'rbs'], true)) {
            throw new \InvalidArgumentException('Ongeldige projectactie.');
        }
        $code = trim($code);
        $title = trim($title);
        $description = trim($description);
        if ($code === '' || mb_strlen($code) > 64 || $title === '' || mb_strlen($title) > 200) {
            throw new \InvalidArgumentException('Code (max. 64) en omschrijving (max. 200) verplicht.');
        }
        $table = $kind === 'wbs' ? 'office_project_wbs_nodes' : 'office_project_rbs_categories';
        if ($parentId !== null) {
            $check = $this->db->prepare("SELECT 1 FROM {$table} WHERE id=? AND project_id=?");
            $check->execute([$parentId, $projectId]);
            if (!$check->fetchColumn()) throw new \DomainException('Bovenliggend onderdeel hoort niet bij dit project.');
        }
        if ($kind === 'wbs') {
            $stmt = $this->db->prepare('INSERT INTO office_project_wbs_nodes (project_id,parent_id,code,title,description,created_by,updated_by) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$projectId,$parentId,$code,$title,$description,$operatorId,$operatorId]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO office_project_rbs_categories (project_id,parent_id,code,title) VALUES (?,?,?,?)');
            $stmt->execute([$projectId,$parentId,$code,$title]);
        }
        return (int)$this->db->lastInsertId();
    }

    public function assignRisk(int $projectId, int $categoryId, int $riskId): void
    {
        if ($projectId < 1 || $categoryId < 1 || $riskId < 1) {
            throw new \InvalidArgumentException('Ongeldige risicokoppeling.');
        }
        $stmt = $this->db->prepare('INSERT IGNORE INTO office_project_rbs_risk_links (project_id,category_id,risk_id) SELECT c.project_id,c.id,r.id FROM office_project_rbs_categories c INNER JOIN office_risks r ON r.project_id=c.project_id WHERE c.project_id=? AND c.id=? AND r.id=?');
        $stmt->execute([$projectId,$categoryId,$riskId]);
        if ($stmt->rowCount() === 0) {
            $check = $this->db->prepare('SELECT 1 FROM office_project_rbs_risk_links WHERE project_id=? AND category_id=? AND risk_id=?');
            $check->execute([$projectId,$categoryId,$riskId]);
            if (!$check->fetchColumn()) throw new \DomainException('Categorie en risico moeten bij hetzelfde project horen.');
        }
    }
}
