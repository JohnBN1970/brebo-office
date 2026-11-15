<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeProjectPageStructure.php';
require_once dirname(__DIR__).'/src/Project/OfficeProjectDetailV3View.php';
use Brebo\Office\Project\OfficeProjectDetailV3View as V;
$sections = ['werkvoorbereiding'=>'<p>Existing preparation</p>', 'kickstart'=>'<section id="kickstart">Startdossier</section>'];
$html = V::render('/office-test', 42, 'Test', 'TEST-42', 'preparation', 1, 'werkvoorbereiding', $sections);
if (!str_contains($html,'Existing preparation') || !str_contains($html,'id="kickstart"')) throw new RuntimeException('Kickstart missing from preparation');
if (substr_count($html,'id="kickstart"') !== 1) throw new RuntimeException('Kickstart duplicated');
$other = V::render('/office-test', 42, 'Test', 'TEST-42', 'preparation', 1, 'financieel', $sections);
if (str_contains($other,'id="kickstart"')) throw new RuntimeException('Kickstart leaked to another tab');
echo "KICKSTART_V3_INTEGRATION_OK\n";
