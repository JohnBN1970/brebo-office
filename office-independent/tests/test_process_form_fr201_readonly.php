<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeFr201FormView.php';
use Brebo\Office\Process\OfficeFr201FormView;
$html=OfficeFr201FormView::render(12,['location'=>'Testlocatie <script>alert(1)</script>'],1,'/office-test/crm/aanvragen/detail?id=12','token',false);
if (!str_contains($html,'Testlocatie &lt;script&gt;alert(1)&lt;/script&gt;')) throw new RuntimeException('Read-only supplement missing or unescaped');
if (str_contains($html,'<form') || str_contains($html,'name="fr201[')) throw new RuntimeException('Read-only FR-201 exposes write controls');
echo "FR-201 read-only supplement rendering OK\n";
