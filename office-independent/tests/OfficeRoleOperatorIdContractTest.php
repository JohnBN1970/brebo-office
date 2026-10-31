<?php
declare(strict_types=1);

// Regression guard: Office operator IDs are VARCHAR(128), never numeric-only.
$root = dirname(__DIR__);
$expect = [
    'src/Offer/OfficeRoleSettings.php' => ["string \$actorOperatorId = ''", "if (\$actorOperatorId !== '')"],
    'src/Offer/OfficeRoleSettingsController.php' => ['save(string $operatorId', "strlen(\$operatorId) > 128"],
    'src/Offer/OfficeRoleSettingsRead.php' => ['rolesForManager(string $operatorId)'],
    'src/Offer/OfficeRoleSettingsPage.php' => ['render(string $operatorId'],
    'src/Offer/OfficeRoleSettingsEndpoint.php' => ['handle(string $method, string $operatorId'],
    'public/office-role-settings.php' => ['(string) $operator->operatorId'],
];
foreach ($expect as $file => $needles) {
    $source = file_get_contents($root . '/' . $file);
    if ($source === false) {
        throw new RuntimeException("Missing source: $file");
    }
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            throw new RuntimeException("Operator ID contract missing in $file: $needle");
        }
    }
}
echo "Office role operator ID contract: PASS\n";
