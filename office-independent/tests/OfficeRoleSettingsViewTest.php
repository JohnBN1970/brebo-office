<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsView.php';

use Brebo\Office\Offer\OfficeRoleSettingsView;

$html = OfficeRoleSettingsView::render(
    [['id' => 3, 'label' => '<Projectleider>']],
    ['offers' => ['enabled' => true, 'permissions' => ['read', 'create'], 'scope' => 'assigned']],
    '/office-test/instellingen/rollen',
    'csrf<token>'
);
foreach (['&lt;Projectleider&gt;', 'csrf&lt;token&gt;', 'name="enabled"', 'name="permissions[]"', 'name="scope"', 'Toegewezen projecten', 'name="role_id"', 'name="module"'] as $expected) {
    if (!str_contains($html, $expected)) {
        throw new RuntimeException('Settings form missing: ' . $expected);
    }
}
if (str_contains($html, '<Projectleider>') || str_contains($html, 'csrf<token>')) {
    throw new RuntimeException('Unescaped user content');
}
echo "OfficeRoleSettingsViewTest: PASS\n";
