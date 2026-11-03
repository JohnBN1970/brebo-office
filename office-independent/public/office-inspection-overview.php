<?php
declare(strict_types=1);

/** Read-only Office-test inspection overview, isolated from production. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_INSPECTION_UI_ENABLED') !== 'enabled'
  || getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(404);
  exit;
}
$host=$_SERVER['HTTP_HOST'] ?? '';
$allowedHost=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost,(string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  require_once dirname(__DIR__) . '/src/Inspection/OfficeInspectionOverviewRepository.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);
    exit;
  }
  $buildingId=$_GET['building_id'] ?? '';
  $inspectionId=$_GET['inspection_id'] ?? '';
  $repo=new \Brebo\Office\Inspection\OfficeInspectionOverviewRepository($db);
  $inspection=null;
  $inspections=[];
  if (is_string($inspectionId) && $inspectionId !== '') {
    $inspection=$repo->detail($inspectionId,(string)$operator->operatorId);
    if ($inspection === null) { http_response_code(404); exit('Inspectie niet gevonden.'); }
    $buildingId=(string)$inspection['building_id'];
    require_once dirname(__DIR__) . '/src/Inspection/OfficeInspectionRepository.php';
    require_once dirname(__DIR__) . '/src/Inspection/OfficeInspectionReportConfiguration.php';
    require_once dirname(__DIR__) . '/src/Inspection/OfficeInspectionReportConfigurationRepository.php';
    $findings=(new \Brebo\Office\Inspection\OfficeInspectionRepository($db))->findings($inspectionId);
    $config=(new \Brebo\Office\Inspection\OfficeInspectionReportConfigurationRepository($db))->forInspection($inspectionId);
  }
  if (is_string($buildingId) && $buildingId !== '') {
    $inspections=$repo->forBuilding($buildingId,(string)$operator->operatorId);
  }
} catch (InvalidArgumentException $error) {
  http_response_code(400); exit('Ongeldige selectie.');
} catch (Throwable $error) {
  http_response_code(503); exit('Inspectieoverzicht tijdelijk niet beschikbaar.');
}
$e=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inspecties · BREBO Office</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#1b2c3c;font:15px system-ui,sans-serif}
main{max-width:1050px;margin:36px auto;padding:0 20px}section{background:white;border:1px solid #dce3eb;border-radius:12px;padding:24px;margin:18px 0}
table{width:100%;border-collapse:collapse}td,th{text-align:left;border-bottom:1px solid #e5eaf0;padding:12px}a{color:#1c5f8d}
dt{font-weight:700;margin-top:12px}dd{margin:5px 0 12px;white-space:pre-wrap}.muted{color:#65758a}
@media(max-width:650px){table{display:block;overflow-x:auto}}
</style></head><body><main>
<h1>Gebouwinspecties</h1><p class="muted">Beveiligd inspectieoverzicht, uitsluitend voor toegankelijke projecten.</p>
<?php if ($inspection !== null): ?>
<section><h2><?= $e($inspection['title']) ?></h2>
<p><?= $e($inspection['building_name']) ?> · <?= $e($inspection['inspection_date']) ?> · <?= $e($inspection['status']) ?></p>
<p><strong>Rapporttype:</strong> <?= $e($config['report_type'] ?? 'Niet ingesteld') ?></p>
<p><strong>Hoofdstukken:</strong> <?= $e(implode(', ', $config['sections'] ?? [])) ?></p>
<h3>Bevindingen</h3>
<?php if (!$findings): ?><p class="muted">Nog geen bevindingen vastgelegd.</p><?php endif; ?>
<?php foreach ($findings as $finding): ?>
<article><h4><?= $e($finding['code']) ?> · <?= $e($finding['building_part']) ?></h4>
<dl><?php foreach (['observation'=>'Constatering','assessment_framework'=>'Toetsingskader',
'technical_assessment'=>'Technische beoordeling','consequences'=>'Gevolgen','remedy'=>'Herstelmaatregel'] as $key=>$label): ?>
<dt><?= $e($label) ?></dt><dd><?= $e($finding[$key] ?? '') ?></dd>
<?php endforeach; ?></dl></article>
<?php endforeach; ?></section>
<?php endif; ?>
<?php if ($buildingId !== ''): ?>
<section><h2>Inspecties bij gebouw <?= $e($buildingId) ?></h2>
<table><thead><tr><th>Datum</th><th>Inspectie</th><th>Status</th><th>Bevindingen</th></tr></thead><tbody>
<?php foreach ($inspections as $row): ?>
<tr><td><?= $e($row['inspection_date']) ?></td><td><a href="?inspection_id=<?= $e($row['id']) ?>"><?= $e($row['title']) ?></a></td>
<td><?= $e($row['status']) ?></td><td><?= $e($row['finding_count']) ?></td></tr>
<?php endforeach; ?>
</tbody></table><?php if (!$inspections): ?><p class="muted">Geen toegankelijke inspecties gevonden.</p><?php endif; ?></section>
<?php else: ?><section><p>Open dit overzicht vanuit een gebouw met <code>building_id</code> of een bestaande inspectie met <code>inspection_id</code>.</p></section><?php endif; ?>
</main></body></html>
