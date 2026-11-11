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
echo "GANTT_RENDER_TEST_OK\n";
