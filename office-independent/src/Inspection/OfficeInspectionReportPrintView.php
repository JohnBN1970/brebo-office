<?php
declare(strict_types=1);
// Included only after the inspection controller has verified read access.
if (!isset($inspection, $reportFindings, $e)) { http_response_code(404); exit; }
$includedCount=count($reportFindings);
$showPhotos=$config === null || in_array('photos',$config['sections'] ?? [],true);
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
article{margin:30px 0;break-inside:avoid}.evidence-list{border-top:1px solid #d6dfe7;margin-top:16px;padding-top:10px}.evidence-list h3{font-size:11pt}.evidence-list figure{margin:10px 0;break-inside:avoid}.evidence-list img{display:block;max-width:100%;max-height:110mm;object-fit:contain}.finding-location{color:#5a6b79;font-size:10pt}.report-summary{padding:12px 0;border-bottom:1px solid #d6dfe7}.report-note{white-space:pre-wrap}
@media print{body{background:white}main{margin:0;padding:0}.toolbar{display:none}}
</style></head><body>
<div class="toolbar"><button type="button" id="print-report">Afdrukken / PDF opslaan</button></div>
<script nonce="<?= $e($printScriptNonce) ?>">document.getElementById("print-report").addEventListener("click",function(){window.print();});</script>
<main><header><strong>BREBO BOUW EN ADVIES B.V.</strong>
<h1><?= $e($inspection['title']) ?></h1>
<p class="meta"><?= $e($inspection['building_name']) ?> | <?= $e($inspection['inspection_date']) ?></p>
<p class="meta">Project <?= $e($inspection['project_id']) ?> | Inspectie <?= $e($inspection['id']) ?></p>
</header>
<section class="report-summary"><h2>Rapportoverzicht</h2>
<p><strong>Rapporttype:</strong> <?= $e($config['report_type'] ?? 'inspectie') ?> · <strong>Opgenomen bevindingen:</strong> <?= $e($includedCount) ?></p>
<?php if (!empty($config['notes'])): ?><h2>Toelichting</h2><p class="report-note"><?= $e($config['notes']) ?></p><?php endif; ?>
</section>
<h2>Inspectiebevindingen</h2>
<?php foreach ($reportFindings as $finding): ?>
<article><h2><?= $e($finding['code']) ?> - <?= $e($finding['building_part'] ?? '') ?></h2>
<?php $location=array_filter([$finding['floor_label'] ?? '',$finding['room_label'] ?? ''],static fn($v):bool=>trim((string)$v)!==''); ?>
<?php if ($location): ?><p class="finding-location"><strong>Locatie:</strong> <?= $e(implode(' · ',$location)) ?></p><?php endif; ?>
<?php if ($showPhotos && !empty($evidenceByFinding[(string)$finding['id']])): ?>
<section class="evidence-list"><h3>Fotoregistratie</h3>
<?php foreach ($evidenceByFinding[(string)$finding['id']] as $photo): ?>
<p><strong>Bijlage <?= $e($photo['id']) ?></strong><?php if (trim((string)($photo['caption'] ?? '')) !== ''): ?> — <?= $e($photo['caption']) ?><?php endif; ?></p>
<figure><img loading="lazy" alt="<?= $e($photo['caption'] ?: 'Inspectiefoto bij bevinding '.$finding['code']) ?>" src="/office-test/inspectie/foto?inspection_id=<?= rawurlencode((string)$inspection['id']) ?>&amp;evidence_id=<?= rawurlencode((string)$photo['id']) ?>"></figure>
<?php endforeach; ?>

</section>
<?php endif; ?>
<?php foreach ($fields as $key => $label): ?>
<dl><dt><?= $e($label) ?></dt><dd><?= $e($finding[$key] ?? '') ?></dd></dl>
<?php endforeach; ?>
</article>
<?php endforeach; ?>
<?php if (!$reportFindings): ?><p>Geen bevindingen geselecteerd.</p><?php endif; ?>
</main></body></html>
