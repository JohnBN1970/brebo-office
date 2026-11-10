<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Dashboard/OfficePersonalDashboard.php';
use Brebo\Office\Dashboard\OfficePersonalDashboard;

function check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
$permitted = OfficePersonalDashboard::permittedWidgets(
  ['projectleider', 'uitvoerder'],
  ['projects', 'quality', 'hours', 'invoices', 'tasks', 'finance']
);
check($permitted === ['projects', 'quality', 'hours', 'tasks', 'finance'], 'Role and server permission intersection failed');
check(OfficePersonalDashboard::permittedWidgets(['unknown'], ['finance']) === [], 'Unknown role leaked widgets');
check(OfficePersonalDashboard::layout(['invoices', 'tasks', 'tasks', 'quality'], $permitted) === ['tasks', 'quality'], 'Preferences not filtered');
check(count(OfficePersonalDashboard::layout([], $permitted)) === 5, 'Default layout missing');
$markup = OfficePersonalDashboard::render(['tasks'], ['tasks' => ['label' => '<script>', 'value' => '5', 'note' => '<b>']]);
check(!str_contains($markup, '<script>') && !str_contains($markup, '<b>'), 'HTML output not escaped');
check(str_contains($markup, '&lt;script&gt;'), 'HTML escaping absent');
echo "Personal dashboard contract checks passed\n";
