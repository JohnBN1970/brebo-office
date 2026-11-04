<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionCatalog.php';

use Brebo\Office\Finance\OfficeFinanceBatchPermissionCatalog as Catalog;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) throw new RuntimeException($message);
  ++$checks;
};
$permissions = Catalog::permissions();
$assert(count($permissions) === 3, 'Expected three batch permissions');
$assert(count(array_unique($permissions)) === 3, 'Permission keys must be unique');
$roles = Catalog::suggestedRoleGrants();
foreach (['administration','financial_manager','directie','projectleider','medewerker','digital_secretary'] as $role) {
  $assert(array_key_exists($role, $roles), 'Missing suggested role: ' . $role);
  foreach ($roles[$role] as $grant) {
    $assert(in_array($grant, $permissions, true), 'Unknown grant in ' . $role);
  }
}
$assert(!in_array(Catalog::RELEASE, $roles['digital_secretary'], true), 'Assistant cannot release by default');
$assert(!in_array(Catalog::RELEASE, $roles['administration'], true), 'Administration cannot release by default');
$assert(in_array(Catalog::RELEASE, $roles['directie'], true), 'Director may be granted release');
$assert(in_array(Catalog::RELEASE, $roles['financial_manager'], true), 'Finance manager may be granted release');
$assert(!Catalog::hasGrant([], Catalog::RELEASE), 'No grants must deny');
$assert(!Catalog::hasGrant(['finance.payment_batch.unknown'], Catalog::RELEASE), 'Unrelated grant must deny');
$assert(!Catalog::hasGrant([Catalog::RELEASE], 'finance.payment_batch.unknown'), 'Unknown permission must deny');
$assert(Catalog::hasGrant([Catalog::RELEASE], Catalog::RELEASE), 'Explicit server-side grant must allow');
echo 'OfficeFinanceBatchPermissionCatalogTest: ' . $checks . " checks passed\n";
