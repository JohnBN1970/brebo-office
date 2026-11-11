<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioGantt.php';

use Brebo\Office\Project\OfficeProjectPortfolioGantt;

function checkGantt(bool $ok, string $message): void {
  if (!$ok) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
  }
}

$projects = [
  ['id'=>7, 'title'=>'Project <test>', 'status'=>'kritiek', 'planned_start'=>(new DateTimeImmutable('today'))->modify('-7 days')->format('Y-m-d'), 'planned_end'=>(new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d'), 'critical_note'=>'Levering vertraagd'],
  ['id'=>8, 'title'=>'Zonder planning', 'status'=>'in wacht'],
];
$html = OfficeProjectPortfolioGantt::render($projects, '/office-test');
checkGantt(str_contains($html, 'portfolio-gantt-today'), 'standline present');
checkGantt(str_contains($html, 'Project &lt;test&gt;'), 'project title escaped');
checkGantt(!str_contains($html, 'Project <test>'), 'raw HTML rejected');
checkGantt(str_contains($html, '/office-test/projecten/detail?id=7'), 'detail route');
checkGantt(str_contains($html, 'Levering vertraagd'), 'critical note available');
checkGantt(str_contains($html, 'background:#b91c1c'), 'critical project has red bar');
checkGantt(str_contains($html, 'Planning nog niet beschikbaar'), 'missing dates explicitly reported');
checkGantt(str_contains($html, 'In wacht'), 'status label');
$unsafe = OfficeProjectPortfolioGantt::render([
  ['id'=>9,'title'=>'Controle','status'=>'kritiek',
   'planned_start'=>(new DateTimeImmutable('today'))->format('Y-m-d'),
   'planned_end'=>(new DateTimeImmutable('today'))->modify('+2 days')->format('Y-m-d'),
   'critical_note'=>'<script>alert(1)</script>'],
], '/office-test');
checkGantt(!str_contains($unsafe, '<script>'), 'critical note HTML escaped');
checkGantt(str_contains($unsafe, '&lt;script&gt;'), 'critical note safely displayed');
checkGantt(str_contains($unsafe, 'title="&lt;script&gt;'), 'critical note shown on project hover');
$invalid = OfficeProjectPortfolioGantt::render([
  ['id'=>10,'title'=>'Ongeldige datum','status'=>'op schema',
   'planned_start'=>'2026-02-30','planned_end'=>'2026-03-02'],
], '/office-test');
checkGantt(str_contains($invalid, 'Planning nog niet beschikbaar'), 'invalid schedule never rendered as valid');
checkGantt(!str_contains($invalid, 'portfolio-gantt-bar"'), 'invalid schedule has no bar');
$phases = OfficeProjectPortfolioGantt::render([
  ['id'=>11,'title'=>'Faseproject','status'=>'concept',
   'planned_start'=>(new DateTimeImmutable('today'))->modify('-7 days')->format('Y-m-d'),
   'planned_end'=>(new DateTimeImmutable('today'))->modify('+28 days')->format('Y-m-d'),
   'plan_items'=>[
     ['title'=>'DEMO-GANTT | Opname','status'=>'done',
      'planned_start'=>(new DateTimeImmutable('today'))->modify('-7 days')->format('Y-m-d'),
      'planned_end'=>(new DateTimeImmutable('today'))->modify('+2 days')->format('Y-m-d')],
     ['title'=>'DEMO-GANTT | Levering','status'=>'critical','critical_note'=>'Faselevering vertraagd',
      'planned_start'=>(new DateTimeImmutable('today'))->modify('+4 days')->format('Y-m-d'),
      'planned_end'=>(new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d')],
   ]],
], '/office-test');
checkGantt(str_contains($phases, 'DEMO-GANTT | Opname'), 'stored planning phase visible');
checkGantt(str_contains($phases, 'DEMO-GANTT | Levering'), 'critical planning phase visible');
checkGantt(str_contains($phases, 'Faselevering vertraagd'), 'planning blocker shown');
checkGantt(substr_count($phases, 'portfolio-gantt-bar"') >= 3, 'project plus phase bars rendered');
echo "GANTT_RENDER_TEST_OK\n";
