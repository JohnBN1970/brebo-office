<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use PDO;
use RuntimeException;

/**
 * Read-only bridge from the canonical, locked Calc->CRM baseline.
 * Deliberately does not invent actuals, commitments, or approved changes.
 * Consumers must join authoritative Office sources separately.
 */
final class LockedBaselineLines
{
    public function __construct(private readonly PDO $db) {}

    public function forProject(int $projectId): ?array
    {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Invalid Office project ID.');
        }
        $statement = $this->db->prepare(
            'SELECT b.id, b.version_number, b.locked_at
             FROM office_calc_crm_acceptance_transfers t
             INNER JOIN office_calc_work_budget_baselines b ON b.acceptance_transfer_id = t.id
             WHERE t.office_project_id = :project
             ORDER BY b.version_number DESC, b.id DESC LIMIT 1'
        );
        $statement->execute(['project' => $projectId]);
        $baseline = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($baseline)) {
            return null;
        }
        if (empty($baseline['locked_at'])) {
            throw new RuntimeException('Baseline is not locked.');
        }
        $statement = $this->db->prepare(
            'SELECT source_line_id, cost_type, quantity, unit_cost_cents, budget_cents, activity_ref
             FROM office_calc_work_budget_lines WHERE baseline_id = :baseline ORDER BY id'
        );
        $statement->execute(['baseline' => $baseline['id']]);
        $lines = [];
        $seen = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $type = (string) $line['cost_type'];
            if (!in_array($type, ['labor', 'material', 'equipment', 'subcontract', 'other'], true)) {
                throw new RuntimeException('Unknown baseline cost type.');
            }
            $source = (string) $line['source_line_id'];
            if ($source === '' || isset($seen[$source])) {
                throw new RuntimeException('Missing or duplicate source line ID.');
            }
            $seen[$source] = true;
            $raw = (string) $line['budget_cents'];
            if (!preg_match('/^(0|[1-9][0-9]*)$/D', $raw)) {
                throw new RuntimeException('Invalid baseline amount.');
            }
            $amount = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (!is_int($amount)) {
                throw new RuntimeException('Baseline amount overflow.');
            }
            $lines[] = [
                'code' => $source,
                'cost_type' => $type,
                'activity_ref' => $line['activity_ref'],
                'baseline_cents' => $amount,
                'quantity' => (int) $line['quantity'],
                'unit_cost_cents' => (int) $line['unit_cost_cents'],
            ];
        }
        return [
            'project_id' => $projectId,
            'baseline_id' => (int) $baseline['id'],
            'version_number' => (int) $baseline['version_number'],
            'locked_at' => $baseline['locked_at'],
            'currency' => 'EUR',
            'vat_basis' => 'excluding',
            'lines' => $lines,
        ];
    }
}
