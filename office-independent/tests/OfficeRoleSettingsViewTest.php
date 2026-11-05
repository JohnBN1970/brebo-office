<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsView.php';
use Brebo\Office\Offer\OfficeRoleSettingsView;
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
};
$roles = [['id' => 3, 'label' => 'Directie']];
$modules = [
    'offers' => ['enabled' => true, 'permissions' => ['read'], 'scope' => 'assigned'],
    'finance' => ['enabled' => true, 'permissions' => ['payment_batch.review'], 'scope' => 'none'],
];
$html = OfficeRoleSettingsView::render($roles, $modules, '/office-test/instellingen/rollen', 'csrf');
$assert(substr_count($html, '<form') === 2, 'Two separate module forms');
$assert(str_contains($html, 'value="payment_batch.create"'), 'Finance create shown');
$assert(str_contains($html, 'value="payment_batch.review" checked'), 'Finance review selected');
$assert(str_contains($html, 'value="payment_batch.release"'), 'Finance release shown');
$assert(str_contains($html, 'value="issue"'), 'Existing offers issue preserved');
$assert(substr_count($html, 'name="scope"') === 2, 'Scope present in each form');
$assert(str_contains($html, 'name="scope" value="none"'), 'Finance fixed none scope');
$assert(str_contains($html, 'Toegewezen projecten'), 'Offer project scopes preserved');
$assert(str_contains($html, 'pas effectief'), 'Role grant warning');
$assert(!str_contains($html, 'payment_batch.submit'), 'No bank submit control');
$escaped = OfficeRoleSettingsView::render([['id'=>4,'label'=>'<script>']], $modules, '/roles', '<csrf>');
$assert(!str_contains($escaped, '<script>'), 'Role label escaped');
$assert(str_contains($escaped, '&lt;csrf&gt;'), 'CSRF escaped');
$unknown = OfficeRoleSettingsView::render($roles, ['unknown'=>['enabled'=>true]], '/roles', 'csrf');
$assert(!str_contains($unknown, '<form'), 'Unknown module not rendered');
echo "OfficeRoleSettingsViewTest OK ($checks checks)\n";
