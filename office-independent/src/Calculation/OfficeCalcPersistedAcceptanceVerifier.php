<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Read-only verification adapter for the proposed durable CRM Calc acceptance
 * source. Call only inside OfficeCalcCrmTransferService's transaction.
 * This adapter never treats a won opportunity as a Calc acceptance.
 */
final class OfficeCalcPersistedAcceptanceVerifier
{
    public function __invoke(PDO $db, array $acceptance, array $lines): bool
    {
        if (!$db->inTransaction() || ($acceptance['status'] ?? null) !== 'accepted') {
            return false;
        }
        $suffix = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $db->prepare(
            'SELECT a.calculation_id, a.accepted_revision, a.status, o.stage, o.status AS opportunity_status ' .
            'FROM office_crm_calc_acceptances a ' .
            'INNER JOIN office_crm_opportunities o ON o.id = a.opportunity_id ' .
            'WHERE a.opportunity_id = ?' . $suffix
        );
        $statement->execute([$acceptance['crm_opportunity_id']]);
        $stored = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($stored)
            || $stored['status'] !== 'accepted'
            || $stored['stage'] !== 'won'
            || $stored['opportunity_status'] !== 'closed'
            || (int)$stored['calculation_id'] !== $acceptance['calculation_id']
            || (string)$stored['accepted_revision'] !== $acceptance['accepted_revision']) {
            return false;
        }
        $statement = $db->prepare(
            'SELECT source_line_id, cost_type, quantity, unit_cost_cents, activity_ref ' .
            'FROM office_crm_calc_accepted_lines WHERE opportunity_id = ? ORDER BY source_line_id' . $suffix
        );
        $statement->execute([$acceptance['crm_opportunity_id']]);
        $storedLines = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($storedLines) !== count($lines) || $storedLines === []) {
            return false;
        }
        $requested = [];
        foreach ($lines as $line) {
            if (!is_array($line)) return false;
            $requested[] = [
                'source_line_id' => $line['line_id'] ?? null,
                'cost_type' => $line['cost_type'] ?? null,
                'quantity' => $line['quantity'] ?? null,
                'unit_cost_cents' => $line['unit_cost_cents'] ?? null,
                'activity_ref' => $line['activity_ref'] ?? null,
            ];
        }
        usort($requested, static fn(array $a, array $b): int =>
            strcmp((string)$a['source_line_id'], (string)$b['source_line_id']));
        foreach ($storedLines as $index => $storedLine) {
            $line = $requested[$index];
            if ($storedLine['source_line_id'] !== $line['source_line_id']
                || $storedLine['cost_type'] !== $line['cost_type']
                || !is_int($line['quantity']) || (int)$storedLine['quantity'] !== $line['quantity']
                || !is_int($line['unit_cost_cents']) || (int)$storedLine['unit_cost_cents'] !== $line['unit_cost_cents']
                || $storedLine['activity_ref'] !== $line['activity_ref']) {
                return false;
            }
        }
        return true;
    }
}
