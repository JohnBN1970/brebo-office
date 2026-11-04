<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Demo/OfficeCommercialProfileSchema.php';
use Brebo\Office\Demo\OfficeCommercialProfileSchema as S;
$statements = S::statements();
if (count($statements) !== 3) throw new RuntimeException('Expected three additive tables');
foreach (['office_commercial_profiles', 'office_commercial_profile_rules', 'office_crm_commercial_context'] as $i => $table) {
    if (!str_contains($statements[$i], $table) || !str_contains($statements[$i], 'CREATE TABLE IF NOT EXISTS')) throw new RuntimeException('Unexpected DDL');
}
echo "CRM commercial profile schema contract OK\n";
