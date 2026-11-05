<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Bank payment submission preflight. Inputs must be resolved on the server,
 * never copied from POST/GET or client-side state.
 * This gate is additive: existing batch approval and four-eyes checks remain mandatory.
 */
final class OfficeBankSubmissionReadiness
{
    public const OPERATOR_AUTHORIZED = 'operator_authorized';
    public const BANK_PAYMENTS_ENABLED = 'bank_payments_enabled';
    public const BANK_PROVIDER_CONTRACT_VERIFIED = 'bank_provider_contract_verified';

    public static function evaluate(array $serverChecks): array
    {
        $checks = [];
        foreach ([
            self::OPERATOR_AUTHORIZED,
            self::BANK_PAYMENTS_ENABLED,
            self::BANK_PROVIDER_CONTRACT_VERIFIED,
        ] as $name) {
            // Only an explicit boolean true passes; missing or malformed data blocks.
            $checks[$name] = ($serverChecks[$name] ?? null) === true;
        }
        return [
            'ready' => !in_array(false, $checks, true),
            'checks' => $checks,
            'blockers' => array_keys(array_filter($checks, static fn (bool $pass): bool => !$pass)),
        ];
    }
}
