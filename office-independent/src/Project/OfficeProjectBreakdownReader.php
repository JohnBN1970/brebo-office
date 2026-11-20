<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

/**
 * Read-only WBS/RBS model. Caller must enforce project read ACL before invocation.
 * No financial values or risk scores are copied into breakdown storage.
 */
final class OfficeProjectBreakdownReader
{
    public function __construct(private readonly PDO $db) {}

    public function read(int $projectId): array
    {
        if ($projectId < 1) throw new \InvalidArgumentException('Project-ID ontbreekt.');

        $wbs = $this->rows(
            'SELECT id, parent_id, code, title, description, sort_order FROM office_project_wbs_nodes WHERE project_id=? ORDER BY sort_order, code, id',
            [$projectId]
        );
        $rbs = $this->rows(
            'SELECT id, parent_id, code, title, sort_order FROM office_project_rbs_categories WHERE project_id=? ORDER BY sort_order, code, id',
            [$projectId]
        );
        $wbsLinks = $this->rows(
            'SELECT wbs_node_id, source_type, source_id FROM office_project_wbs_links WHERE project_id=? ORDER BY wbs_node_id, source_type, source_id',
            [$projectId]
        );
        $riskLinks = $this->rows(
            'SELECT category_id, risk_id FROM office_project_rbs_risk_links WHERE project_id=? ORDER BY category_id, risk_id',
            [$projectId]
        );
        // The risk ledger remains authoritative; links are scoped to the same project.
        $risks = $this->rows(
            'SELECT id, status, payload_json FROM office_risks WHERE project_id=? ORDER BY id',
            [$projectId]
        );
        $decoded = [];
        foreach ($risks as $risk) {
            $payload = json_decode((string)$risk['payload_json'], true, 32, JSON_THROW_ON_ERROR);
            $decoded[] = ['id'=>(int)$risk['id'], 'status'=>$risk['status'], 'payload'=>$payload];
        }
        return [
            'wbs' => OfficeProjectBreakdown::tree($wbs),
            'rbs' => OfficeProjectBreakdown::tree($rbs),
            'wbs_links' => $wbsLinks,
            'rbs_risk_links' => $riskLinks,
            'risk_matrix' => OfficeProjectBreakdown::riskMatrix($decoded),
            'risks' => $decoded,
        ];
    }

    private function rows(string $sql, array $params): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
