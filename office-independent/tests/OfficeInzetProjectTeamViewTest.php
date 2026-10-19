<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetProjectTeamView.php';

$people = [
  ['id'=>4,'display_name'=>'Test & Team','personnel_number'=>'BR-004'],
  ['id'=>4,'display_name'=>'Test & Team','personnel_number'=>'BR-004'],
  ['id'=>5,'display_name'=>'<script>unsafe</script>','personnel_number'=>'BR-005'],
];
$html = \Brebo\Office\Inzet\OfficeInzetProjectTeamView::render($people, 'Project & Gebouw');
if (!str_contains($html, 'Project &amp; Gebouw')
  || !str_contains($html, 'Test &amp; Team')
  || !str_contains($html, 'BR-004')
  || !str_contains($html, 'BR-005')) {
  throw new RuntimeException('Office project team cards missing authorized data');
}
if (substr_count($html, 'BR-004') !== 1 || str_contains($html, '<script>')) {
  throw new RuntimeException('Duplicate or unescaped employee data in project team');
}
if (!str_contains(\Brebo\Office\Inzet\OfficeInzetProjectTeamView::render([], 'Empty'), 'nog geen actieve medewerkers')) {
  throw new RuntimeException('Project team empty state missing');
}
echo "Office project team cards render and escaping OK\n";
