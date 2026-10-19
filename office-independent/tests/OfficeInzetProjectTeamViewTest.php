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
$multi = \Brebo\Office\Inzet\OfficeInzetProjectTeamView::render($people, 'Project A')
  . \Brebo\Office\Inzet\OfficeInzetProjectTeamView::render($people, 'Project B');
if (str_contains($multi, 'id="inzet-team-title"')
  || substr_count($multi, 'aria-label="Projectteam"') !== 2) {
  throw new RuntimeException('Multiple project teams must not duplicate heading IDs');
}
$invalid = \Brebo\Office\Inzet\OfficeInzetProjectTeamView::render([
  ['id'=>0,'display_name'=>'Invalid zero'],
  ['id'=>'invalid','display_name'=>'Invalid text'],
  ['id'=>9,'display_name'=>'Valid colleague'],
], 'Test');
if (str_contains($invalid, 'Invalid zero') || str_contains($invalid, 'Invalid text')
  || !str_contains($invalid, 'Valid colleague')) {
  throw new RuntimeException('Invalid employee identifiers must be rejected');
}
$missingNumber = \Brebo\Office\Inzet\OfficeInzetProjectTeamView::render([
  ['id'=>10,'display_name'=>'Nieuwe medewerker','personnel_number'=>''],
], 'Project');
if (!str_contains($missingNumber, 'Profiel aanvullen vereist')
  || !str_contains($missingNumber, 'Personeelsnummer niet ingevuld')
  || str_contains($html, 'Profiel aanvullen vereist')) {
  throw new RuntimeException('Personnel-number completeness marker incorrect');
}
echo "Office project team cards render and escaping OK\n";
