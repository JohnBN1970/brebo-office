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
            'environment' => 'office-test',
            'project' => [
                'code' => self::PROJECT_CODE,
                'title' => 'TEST - Renovatie 232 woningen',
                'housing_units' => 232,
            ],
            'stages' => [
                'crm' => ['lead', 'customer', 'contact'],
                'projecten' => ['project', 'building_links'],
                'gebouwen' => ['buildings', 'housing_units', 'building_elements'],
                'inspectie' => ['inspections', 'findings', 'measures'],
                'calculatie' => ['quantities', 'cost_estimate', 'sale_price'],
                'offerte' => ['offer', 'offer_lines', 'acceptance'],
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
