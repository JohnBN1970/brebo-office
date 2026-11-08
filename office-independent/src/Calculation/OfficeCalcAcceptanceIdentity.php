<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;
use InvalidArgumentException;

/**
 * Deterministic, fail-closed acceptance identity for an eventual atomic
 * CRM -> project -> work-budget transaction. No DB writes here.
 */
final class OfficeCalcAcceptanceIdentity
{
    public static function key(array $acceptance): string
    {
        if (($acceptance['status'] ?? null) !== 'accepted'
            || !is_string($acceptance['crm_opportunity_id'] ?? null)
            || trim($acceptance['crm_opportunity_id']) === ''
            || !is_int($acceptance['calculation_id'] ?? null)
            || $acceptance['calculation_id'] < 1
            || !is_string($acceptance['accepted_revision'] ?? null)
            || trim($acceptance['accepted_revision']) === '') {
            throw new InvalidArgumentException('Verified CRM acceptance required');
        }
        return hash('sha256', json_encode([
            'crm_opportunity_id' => $acceptance['crm_opportunity_id'],
            'calculation_id' => $acceptance['calculation_id'],
            'accepted_revision' => $acceptance['accepted_revision'],
        ], JSON_THROW_ON_ERROR));
    }

    public static function assertNotTransferred(array $acceptance, ?array $existing): void
    {
        $key = self::key($acceptance);
        if ($existing === null) {
            return;
        }
        if (($existing['acceptance_key'] ?? null) !== $key) {
            throw new DomainException('CRM opportunity already transferred from another acceptance');
        }
        if (!is_int($existing['project_id'] ?? null) || $existing['project_id'] < 1
            || !is_int($existing['work_budget_id'] ?? null) || $existing['work_budget_id'] < 1) {
            throw new DomainException('Incomplete existing acceptance transfer');
        }
    }
}
