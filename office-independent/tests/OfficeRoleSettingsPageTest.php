<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsPage.php';

use Brebo\Office\Offer\OfficeRoleSettingsPage;

$reflection = new ReflectionClass(OfficeRoleSettingsPage::class);
if (!$reflection->hasMethod('render')) {
    throw new RuntimeException('Settings page render missing');
}
$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeRoleSettingsPage.php');
foreach (['OfficeRoleSettingsRead', 'rolesForManager(', 'OfficeRoleSettingsView::render(', 'CSRF token required', 'Er zijn nog geen actieve rollen ingericht', '<!doctype html>', '</body></html>'] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing settings page integration: ' . $required);
    }
}
echo "OfficeRoleSettingsPageTest: PASS\n";
