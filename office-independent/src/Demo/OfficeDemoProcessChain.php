<?php

declare(strict_types=1);

namespace Brebo\Office\Demo;

/**
 * Deterministic, non-mutating demo scenario contract.
 * Seeders must use the canonical Office repositories and must not silently
 * fabricate records for modules whose schema is not yet available.
 */
final class OfficeDemoProcessChain
{
    public const PROJECT_CODE = 'TEST-2026-001';

    public static function scenario(): array
    {
        return [
            'scenario_key' => 'renovatie-232-woningen-v1',
            'lifecycle' => [
                'origin' => 'crm_intake',
                'initial_measurement' => 'crm_opportunity',
                'inspection' => 'separate_commission_optional',
                'project_creation_gate' => 'accepted_execution_contract',
                'final_measurement' => 'optional_after_execution_contract',
                'legacy_demo_project_preexists' => true,
            ],
            'environment' => 'office-test',
            'project' => [
                'code' => self::PROJECT_CODE,
                'title' => 'TEST - Renovatie 232 woningen',
                'housing_units' => 232,
            ],
            'stages' => [
                'crm' => ['lead', 'customer', 'contact'],
                'opportunity' => ['initial_measurement', 'commercial_scope'],
                'inspectieopdracht' => ['commission', 'inspection_report'],
                'calculatie' => ['quantities', 'cost_estimate', 'sale_price'],
                'offerte' => ['offer', 'offer_lines', 'acceptance'],
                'contractacceptatie' => ['accepted_execution_contract'],
                'projecten' => ['project', 'building_links'],
                'gebouwen' => ['buildings', 'housing_units', 'building_elements'],
                'definitieve_inmeting' => ['optional_final_measurement'],
                'contracten' => ['contract', 'terms', 'change_orders'],
                'inkoop' => ['suppliers', 'purchase_orders', 'commitments'],
                'personeel' => ['people', 'roles'],
                'inzet_onsite' => ['shifts', 'presence', 'hours'],
                'uitvoering' => ['milestones', 'progress', 'risks'],
                'finance' => ['sales_invoices', 'purchase_invoices', 'payments'],
                'mjop' => ['maintenance_measures', 'reserves'],
                'rapportage' => ['project_kpis', 'financial_kpis'],
            ],
        ];
    }

    /**
     * Read-only chain monitor. Unknown integrations remain unverified, never green.
     * Evidence must be supplied by authorized module-specific checks; this class
     * deliberately performs no database writes or external API calls.
     *
     * @param array<string,array{schema?:bool,records?:bool,workflow?:bool,reason?:string}> $evidence
     */
    public static function monitor(array $evidence = []): array
    {
        $scenario = self::scenario();
        self::validate($scenario);
        $result = [];
        foreach ($scenario['stages'] as $stage => $entities) {
            $check = $evidence[$stage] ?? [];
            $schema = $check['schema'] ?? null;
            $records = $check['records'] ?? null;
            $workflow = $check['workflow'] ?? null;
            $status = $schema === false ? 'BLOCKED_SCHEMA'
                : ($schema === true && $records === true && $workflow === true
                    ? 'READY' : ($schema === true && ($records === true || $workflow === true)
                        ? 'PARTIAL' : 'UNVERIFIED'));
            $result[$stage] = [
                'status' => $status,
                'required_entities' => $entities,
                'schema_verified' => $schema === true,
                'records_verified' => $records === true,
                'workflow_verified' => $workflow === true,
                'reason' => (string) ($check['reason'] ?? ''),
            ];
        }
        return [
            'project_code' => self::PROJECT_CODE,
            'environment' => 'office-test',
            'stages' => $result,
            'ready_count' => count(array_filter($result, static fn(array $item): bool => $item['status'] === 'READY')),
            'total_count' => count($result),
        ];
    }

    public static function validate(array $scenario): void
    {
        if (($scenario['environment'] ?? null) !== 'office-test') {
            throw new \InvalidArgumentException('Demo data is restricted to Office-test');
        }
        if (($scenario['project']['code'] ?? null) !== self::PROJECT_CODE) {
            throw new \InvalidArgumentException('Unexpected demo project identity');
        }
        if (($scenario['project']['housing_units'] ?? null) !== 232) {
            throw new \InvalidArgumentException('Housing-unit count must be 232');
        }
        if (!isset($scenario['stages']) || !is_array($scenario['stages'])) {
            throw new \InvalidArgumentException('Missing process stages');
        }
        foreach ($scenario['stages'] as $name => $entities) {
            if (!is_string($name) || $name === '' || !is_array($entities) || $entities === []) {
                throw new \InvalidArgumentException('Invalid demo process stage');
            }
        }
    }

    public static function assertTestEnvironment(string $basePath, string $databaseName): void
    {
        if ($basePath !== '/office-test' || !str_ends_with($databaseName, '_office_test')) {
            throw new \RuntimeException('Refusing demo writes outside isolated Office-test');
        }
    }
}
