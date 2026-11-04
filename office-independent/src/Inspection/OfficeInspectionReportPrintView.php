<?php
declare(strict_types=1);
// Included only after the inspection controller has verified read access.
if (!isset($inspection, $reportFindings, $e)) { http_response_code(404); exit; }
$fields = [
  'observation' => 'Constatering',
  'assessment_framework' => 'Toetsingskader',
  'technical_assessment' => 'Technische beoordeling',
  'consequences' => 'Gevolgen',
  'remedy' => 'Herstelmaatregel',
];
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($inspection['title']) ?> - BREBO Inspectierapport</title>
<style>
@page{size:A4;margin:18mm}
body{font:11pt/1.5 Arial,sans-serif;color:#1d3042;margin:0;background:#edf1f5}
main{max-width:760px;margin:25px auto;padding:45px;background:white}
h1{font-size:23pt;color:#183d59}h2{font-size:15pt;color:#183d59;margin-top:25px}
header{border-bottom:2px solid #183d59;padding-bottom:18px}
dt{font-weight:bold;margin-top:13px}dd{white-space:pre-wrap;margin:3px 0 0}
.meta{color:#5a6b79}.toolbar{max-width:760px;margin:15px auto}
article{margin:30px 0;break-inside:avoid}
@media print{body{background:white}main{margin:0;padding:0}.toolbar{display:none}}
</style></head><body>
<div class="toolbar"><button type="button" id="print-report">Afdrukken / PDF opslaan</button></div>
<script nonce="<?= $e($printScriptNonce) ?>">document.getElementById("print-report").addEventListener("click",function(){window.print();});</script>
<main><header><strong>BREBO BOUW EN ADVIES B.V.</strong>
<h1><?= $e($inspection['title']) ?></h1>
<p class="meta"><?= $e($inspection['building_name']) ?> | <?= $e($inspection['inspection_date']) ?></p>
<p class="meta">Project <?= $e($inspection['project_id']) ?> | Inspectie <?= $e($inspection['id']) ?></p>
</header>
<h2>Inspectiebevindingen</h2>
<?php foreach ($reportFindings as $finding): ?>
<article><h2><?= $e($finding['code']) ?> - <?= $e($finding['building_part'] ?? '') ?></h2>
<?php foreach ($fields as $key => $label): ?>
<dl><dt><?= $e($label) ?></dt><dd><?= $e($finding[$key] ?? '') ?></dd></dl>
<?php endforeach; ?>
</article>
<?php endforeach; ?>
<?php if (!$reportFindings): ?><p>Geen bevindingen geselecteerd.</p><?php endif; ?>
</main></body></html>
