<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsController.php';

use Brebo\Office\Offer\OfficeRoleSettingsController;

$reflection = new ReflectionClass(OfficeRoleSettingsController::class);
if (!$reflection->hasMethod('save')) {
    throw new RuntimeException('Settings save action missing');
}
$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeRoleSettingsController.php');
foreach (['hash_equals(', "p.module = 'settings'", "p.permission = 'manage'", 'a.active = 1', 'OfficeRoleSettings($this->db)', 'FILTER_VALIDATE_INT'] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Settings authorization guard missing: ' . $required);
    }
}
echo "OfficeRoleSettingsControllerTest: PASS\n";
