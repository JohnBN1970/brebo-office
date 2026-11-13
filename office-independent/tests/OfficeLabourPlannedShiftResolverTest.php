<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeLabourPlannedShiftResolver.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeLabourBudgetDebitPolicy.php';
use Brebo\Office\Inzet\OfficeLabourPlannedShiftResolver;
use Brebo\Office\Inzet\OfficeLabourBudgetDebitPolicy;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    echo "PLANNED_SHIFT_RESOLVER_SKIPPED_SQLITE_MISSING\n";
    exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_inzet_shifts (id INTEGER PRIMARY KEY, project_id INTEGER, person_id INTEGER, starts_at TEXT, ends_at TEXT, status TEXT)');
$db->exec("INSERT INTO office_inzet_shifts VALUES (1,42,5,'2026-11-12 06:00:00','2026-11-12 14:00:00','confirmed')");
$resolver = new OfficeLabourPlannedShiftResolver($db);
$shift = $resolver->resolve(42,5,'2026-11-12');
$hours = OfficeLabourBudgetDebitPolicy::calculate(21600,$shift['planned_seconds']);
if ($shift['shift_id'] !== 1 || $hours['budget_debit_seconds'] !== 28800 || $hours['variance_seconds'] !== -7200) {
    throw new RuntimeException('Expected 6 actual and 8 planned hours');
}
foreach ([[42,6,'2026-11-12'],[42,5,'2026-11-13']] as $case) {
    try { $resolver->resolve(...$case); throw new RuntimeException('Expected missing shift rejection'); }
    catch (DomainException $expected) {}
}
$db->exec("INSERT INTO office_inzet_shifts VALUES (2,42,5,'2026-11-12 08:00:00','2026-11-12 10:00:00','planned')");
try { $resolver->resolve(42,5,'2026-11-12'); throw new RuntimeException('Expected ambiguous shift rejection'); }
catch (DomainException $expected) {}
echo "PLANNED_SHIFT_RESOLVER_OK\n";
