<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettings.php';

use Brebo\Office\Offer\OfficeRoleSettings;

$ref = new ReflectionClass(OfficeRoleSettings::class);
if (!$ref->hasMethod('setModule')) {
    throw new RuntimeException('Role module settings operation missing');
}
$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeRoleSettings.php');
foreach (['beginTransaction()', 'FOR UPDATE', 'DELETE FROM office_role_permissions', 'DELETE FROM office_role_scopes', 'array_unique($permissions)', 'rollBack()'] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing transactional safety: ' . $required);
    }
}
echo "OfficeRoleSettingsContractTest: PASS\n";
