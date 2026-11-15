<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;
use Throwable;

/**
 * Fail-closed bridge to the existing Calc draft identity.
 *
 * Never equate CRM draft row IDs to Calc draft IDs. The caller must provide
 * independently authorized, persistence-backed resolvers for the CRM draft,
 * canonical Calc draft and the exact stored opportunity->Calc association.
 * This class does not create, change, or authorize database records.
 */
final class OfficeCalcCrmCanonicalDraftGate
{
    public static function resolve(
        int $operatorId,
        int $opportunityId,
        int $crmRevision,
        callable $crmDraftReader,
        callable $canonicalDraftReader,
        callable $associationReader
    ): int {
        if ($operatorId < 1 || $opportunityId < 1 || $crmRevision < 1) {
            throw new DomainException('Invalid CRM Calc identity');
        }

        try {
            $crm = $crmDraftReader($operatorId, $opportunityId, $crmRevision);
            if (!is_array($crm)
                || !self::matches($crm, 'opportunity_id', $opportunityId)
                || !self::matches($crm, 'crm_revision', $crmRevision)
                || !self::matches($crm, 'created_by', $operatorId)
                || !array_key_exists('project_id', $crm)
                || $crm['project_id'] !== null
                || !self::positive($crm['id'] ?? null)) {
                throw new DomainException('CRM Calc draft not authorized');
            }
            $crmDraftId = (int) $crm['id'];

            $link = $associationReader($crmDraftId, $opportunityId, $crmRevision);
            if (!is_array($link)
                || !self::matches($link, 'crm_draft_id', $crmDraftId)
                || !self::matches($link, 'opportunity_id', $opportunityId)
                || !self::matches($link, 'crm_revision', $crmRevision)
                || !self::positive($link['calc_draft_id'] ?? null)) {
                throw new DomainException('Canonical Calc association missing');
            }
            $calcDraftId = (int) $link['calc_draft_id'];

            $calc = $canonicalDraftReader($operatorId, $calcDraftId);
            if (!is_array($calc)
                || !self::matches($calc, 'id', $calcDraftId)
                || !self::matches($calc, 'operator_id', $operatorId)
                || !self::matches($calc, 'opportunity_id', $opportunityId)
                || !self::matches($calc, 'crm_revision', $crmRevision)
                || !array_key_exists('project_id', $calc)
                || $calc['project_id'] !== null) {
                throw new DomainException('Canonical Calc draft not authorized');
            }
            return $calcDraftId;
        } catch (Throwable $e) {
            throw new DomainException('CRM Calc canonical draft unavailable', 0, $e);
        }
    }

    private static function matches(array $row, string $key, int $value): bool
    {
        return self::positive($row[$key] ?? null) && (int) $row[$key] === $value;
    }

    private static function positive(mixed $value): bool
    {
        if (is_int($value)) return $value > 0;
        return is_string($value)
            && preg_match('/^[1-9][0-9]*$/D', $value) === 1
            && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
}
