<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use DomainException;

/**
 * Calc-initiated quotation handoff contract.
 * The server receiving this intent MUST verify the authenticated Office session,
 * Calc ownership/CRM scope and source revision, and fetch sales lines server-side.
 * Never accept client-supplied prices or use GET for a mutating handoff.
 */
final class OfficeQuotationCalcLaunchContract
{
    public const ACTION = 'generate_quotation';
    public const METHOD = 'POST';

    public static function validateIntent(array $input): array
    {
        if (array_diff(array_keys($input), ['action', 'calculation_id', 'calculation_version', 'csrf']) !== []) {
            throw new DomainException('Unexpected Calc handoff fields');
        }
        if (($input['action'] ?? null) !== self::ACTION) {
            throw new DomainException('Invalid Calc handoff action');
        }
        $id = filter_var($input['calculation_id'] ?? null, FILTER_VALIDATE_INT);
        $version = filter_var($input['calculation_version'] ?? null, FILTER_VALIDATE_INT);
        $csrf = $input['csrf'] ?? null;
        if (!is_int($id) || $id < 1 || !is_int($version) || $version < 1
            || !is_string($csrf) || preg_match('/^[a-f0-9]{64}$/D', $csrf) !== 1) {
            throw new DomainException('Invalid Calc handoff intent');
        }
        return ['calculation_id' => $id, 'calculation_version' => $version];
    }
}
