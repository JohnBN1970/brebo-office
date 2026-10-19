<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPlanningWorkbench.php';

use Brebo\Office\Inzet\OfficeInzetPlanningWorkbench;

$projects = [
  ['id'=>17,'code'=>'BR-17','title'=>'Kozijnen & glas','status'=>'active','building_count'=>2],
  ['id'=>18,'code'=>'BR-18','title'=>'<script>alert(1)</script>','status'=>'planned','building_count'=>0],
];
$html = OfficeInzetPlanningWorkbench::render($projects, '/office-test');
if (!str_contains($html, 'inzet-project-cards')
  || !str_contains($html, '/office-test/projecten/detail?id=17#inzet')
  || !str_contains($html, '2 gekoppelde gebouwen')
  || !str_contains($html, 'Kozijnen &amp; glas')) {
  throw new RuntimeException('Native Inzet project cards not rendered from authorized project data');
}
if (str_contains($html, '<script>') || !str_contains($html, '&lt;script&gt;')) {
  throw new RuntimeException('Untrusted project title not escaped');
}
if (str_contains($html, 'Niet aangesloten')) {
  throw new RuntimeException('Outdated Drupal-era project UI placeholder still rendered');
}
if (!str_contains(OfficeInzetPlanningWorkbench::render([], '/office-test'), 'Geen projecten beschikbaar')) {
  throw new RuntimeException('Empty authorized project state missing');
}
try {
  OfficeInzetPlanningWorkbench::render($projects, '/untrusted');
  throw new RuntimeException('Untrusted mount accepted');
} catch (InvalidArgumentException $expected) {
  // Expected.
}
echo "Native Office Inzet project cards and escaping OK\n";
