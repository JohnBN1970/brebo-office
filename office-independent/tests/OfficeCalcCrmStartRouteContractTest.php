<?php
declare(strict_types=1);

// Structural release gate: no Office-test rollout or schema writes in CI.
$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$start = file_get_contents($root . '/public/office-calc-crm-start.php');
$sql = file_get_contents($root . '/deployment/hostinger/sql/office-calc-crm-drafts-proposed.sql');
if (!is_string($router) || !is_string($start) || !is_string($sql)) {
    throw new RuntimeException('Missing CRM-first launch source');
}
$required = [
    'router' => [$router, "'/office-test/calculatie/start' => 'office-calc-crm-start.php'"],
    'disabled-by-default' => [$start, "getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED') !== 'enabled'"],
    'schema-gate' => [$start, "getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED') !== 'enabled'"],
    'no-project-required' => [$start, "'/office-test/calculatie?draft_id='"],
    'revision-check' => [$start, '$launchInput[\'revision\']'],
    'csrf' => [$start, 'hash_equals($csrf, $submitted)'],
    'missing-origin-denied' => [$start, "'ORIGIN_MISSING'"],
    'proposed-schema-only' => [$sql, 'PROPOSAL ONLY'],
    'opportunity-uniqueness' => [$sql, 'UNIQUE KEY uq_calc_crm_draft_opportunity'],
];
foreach ($required as $name => [$haystack, $needle]) {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('CRM-first contract missing: ' . $name);
    }
}
if (str_contains($start, "OFFICE_CALC_LAUNCH_ENABLED")) {
    throw new RuntimeException('CRM-first route must not depend on project-first Calc grants');
}
echo "CRM_FIRST_START_ROUTE=PASS\n";
