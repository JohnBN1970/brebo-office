<?php
declare(strict_types=1);

/** Read-only Office-test inspection overview, isolated from production. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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
  $secret=getenv('OFFICE_TEST_INSPECTION_CSRF_SECRET');
  $canWrite=is_string($secret) && strlen($secret)>=32;
  $findingCsrf=$canWrite ? hash_hmac('sha256','inspection-finding:'.$operator->sessionId,$secret) : '';
  $buildingId=$_GET['building_id'] ?? '';
  $inspectionId=$_GET['inspection_id'] ?? '';
  $projectId=$_GET['project_id'] ?? null;
  if ($projectId !== null && (!is_string($projectId) || !preg_match('/^[1-9][0-9]{0,18}$/D',$projectId))) throw new InvalidArgumentException('invalid_project_id');
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
    require_once dirname(__DIR__) . '/src/Inspection/OfficeInspectionEvidenceRepository.php';
    $evidence=(new \Brebo\Office\Inspection\OfficeInspectionEvidenceRepository($db))->forInspection($inspectionId);
    $evidenceByFinding=[];
    foreach ($evidence as $photo) $evidenceByFinding[(string)$photo['finding_id']][]=$photo;
    $findings=(new \Brebo\Office\Inspection\OfficeInspectionRepository($db))->findings($inspectionId);
    $selectionStmt=$db->prepare('SELECT finding_id,include_in_report FROM office_inspection_report_findings WHERE inspection_id=?');
    $selectionStmt->execute([$inspectionId]);
    $selections=$selectionStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $permissionStmt=$db->prepare("SELECT 1 FROM office_project_memberships WHERE project_id=? AND operator_id=? AND permission='manage' LIMIT 1");
    $permissionStmt->execute([$inspection['project_id'],$operator->operatorId]);
    $canWrite=$canWrite && $inspection['status']==='draft' && $permissionStmt->fetchColumn()!==false;
    $config=(new \Brebo\Office\Inspection\OfficeInspectionReportConfigurationRepository($db))->forInspection($inspectionId);
  }
  if (is_string($buildingId) && $buildingId !== '') {
    $inspections=$repo->forBuilding($buildingId,(string)$operator->operatorId);
  } elseif ($inspection === null) {
    $inspections=$repo->forOperator((string)$operator->operatorId,$projectId);
  }
} catch (InvalidArgumentException $error) {
  http_response_code(400); exit('Ongeldige selectie.');
} catch (Throwable $error) {
  http_response_code(503); exit('Inspectieoverzicht tijdelijk niet beschikbaar.');
}
$reportMode=($_GET['view'] ?? '')==='report';
if ($reportMode) {
  if ($inspection === null) { http_response_code(404); exit('Rapport niet gevonden.'); }
  $e=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $reportFindings=array_values(array_filter($findings,static fn(array $f):bool=>
    !isset($selections[$f['id']]) || (int)$selections[$f['id']]===1));
  $printScriptNonce=base64_encode(random_bytes(18));
  header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; script-src 'nonce-".$printScriptNonce."'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
  header('Content-Type: text/html; charset=utf-8');
  require dirname(__DIR__).'/src/Inspection/OfficeInspectionReportPrintView.php';
  exit;
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
label{display:block;font-weight:650;margin:12px 0 5px}input,textarea{width:100%;padding:10px;border:1px solid #c8d3df;border-radius:7px;font:inherit}input[type=checkbox]{width:auto}button{background:#1d587c;color:white;border:0;border-radius:8px;padding:12px 18px;margin-top:15px;cursor:pointer}dt{font-weight:700;margin-top:12px}dd{margin:5px 0 12px;white-space:pre-wrap}.muted{color:#65758a}
@media(max-width:650px){table{display:block;overflow-x:auto}}
</style></head><body><main>
<h1>Gebouwinspecties</h1><p class="muted">Beveiligd inspectieoverzicht, uitsluitend voor toegankelijke projecten.</p>
<?php if ($inspection !== null): ?>
<section><h2><?= $e($inspection['title']) ?></h2>
<p><?= $e($inspection['building_name']) ?> · <?= $e($inspection['inspection_date']) ?> · <?= $e($inspection['status']) ?></p>
<p><a href="/office-test/inspecties?inspection_id=<?= $e($inspection['id']) ?>&amp;view=report">Rapportvoorbeeld / afdrukken</a></p>
<p><strong>Rapporttype:</strong> <?= $e($config['report_type'] ?? 'Niet ingesteld') ?></p>
<p><strong>Hoofdstukken:</strong> <?= $e(implode(', ', $config['sections'] ?? [])) ?></p>
<h3>Bevindingen</h3>
<?php if (!$findings): ?><p class="muted">Nog geen bevindingen vastgelegd.</p><?php endif; ?>
<?php foreach ($findings as $finding): ?>
<article><h4><?= $e($finding['code']) ?> · <?= $e($finding['building_part']) ?></h4>
<?php if (!empty($evidenceByFinding[(string)$finding['id']])): ?><p class="muted"><strong>Fotobijlagen:</strong> <?= $e(count($evidenceByFinding[(string)$finding['id']])) ?></p><?php foreach ($evidenceByFinding[(string)$finding['id']] as $photo): ?><figure><img loading="lazy" style="max-width:260px;max-height:200px;object-fit:contain" src="/office-test/inspectie/foto?inspection_id=<?= $e($inspection['id']) ?>&amp;evidence_id=<?= $e($photo['id']) ?>" alt="Inspectiefoto"><figcaption><?= $e($photo['caption'] ?? '') ?></figcaption></figure><?php endforeach; ?><?php endif; ?>
<dl><?php foreach (['observation'=>'Constatering','assessment_framework'=>'Toetsingskader',
'technical_assessment'=>'Technische beoordeling','consequences'=>'Gevolgen','remedy'=>'Herstelmaatregel'] as $key=>$label): ?>
<dt><?= $e($label) ?></dt><dd><?= $e($finding[$key] ?? '') ?></dd>
<?php endforeach; ?></dl><p class="muted">In rapport: <?= !isset($selections[$finding['id']]) || (int)$selections[$finding['id']] === 1 ? 'Ja' : 'Nee' ?></p>
<?php if ($inspection['status'] === 'draft' && $finding['status'] === 'draft' && $canWrite): ?>
<details><summary>Foto toevoegen</summary>
<form method="post" enctype="multipart/form-data" action="/office-test/inspectie/foto-uploaden">
<input type="hidden" name="inspection_id" value="<?= $e($inspection['id']) ?>">
<input type="hidden" name="finding_id" value="<?= $e($finding['id']) ?>">
<input type="hidden" name="csrf_token" value="<?= $e($findingCsrf) ?>">
<label>Foto (JPEG, PNG of WebP, maximaal 10 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
<label>Bijschrift<input name="caption" maxlength="500"></label>
<button type="submit">Foto opslaan</button>
</form></details>
<?php endif; ?>
<?php if ($inspection['status'] === 'draft' && $finding['status'] === 'draft' && $canWrite): ?>
<details><summary>Bevinding wijzigen</summary>
<form method="post" action="/office-test/inspectie/bevinding-opslaan">
<input type="hidden" name="inspection_id" value="<?= $e($inspection['id']) ?>">
<input type="hidden" name="finding_id" value="<?= $e($finding['id']) ?>">
<input type="hidden" name="csrf_token" value="<?= $e($findingCsrf) ?>">
<?php foreach (['code'=>'Bevindingcode','floor_label'=>'Verdieping','room_label'=>'Ruimte','building_part'=>'Bouwdeel'] as $key=>$label): ?>
<label for="edit-<?= $e($finding['id']) ?>-<?= $e($key) ?>"><?= $e($label) ?></label>
<input id="edit-<?= $e($finding['id']) ?>-<?= $e($key) ?>" name="<?= $e($key) ?>" value="<?= $e($finding[$key] ?? '') ?>" <?= $key === 'code' ? 'required maxlength="30" pattern="[A-Z][A-Z0-9-]*"' : '' ?>>
<?php endforeach; ?>
<?php foreach (['observation'=>'Constatering','assessment_framework'=>'Toetsingskader','technical_assessment'=>'Technische beoordeling','consequences'=>'Gevolgen','remedy'=>'Herstelmaatregel'] as $key=>$label): ?>
<label for="edit-<?= $e($finding['id']) ?>-<?= $e($key) ?>"><?= $e($label) ?></label>
<textarea id="edit-<?= $e($finding['id']) ?>-<?= $e($key) ?>" name="<?= $e($key) ?>" rows="3" <?= $key === 'observation' ? 'required' : '' ?>><?= $e($finding[$key] ?? '') ?></textarea>
<?php endforeach; ?>
<label><input type="checkbox" name="include_in_report" value="1" <?= !isset($selections[$finding['id']]) || (int)$selections[$finding['id']] === 1 ? 'checked' : '' ?>> Opnemen in rapport</label>
<button type="submit">Wijzigingen opslaan</button>
</form></details>
<?php endif; ?></article>
<?php endforeach; ?></section>
<?php if ($inspection['status'] === 'draft' && $canWrite): ?>
<section><h3>Nieuwe bevinding vastleggen</h3>
<p class="muted">Bevindingen worden opgeslagen in het inspectiedossier.</p>
<form method="post" action="/office-test/inspectie/bevinding-opslaan">
<input type="hidden" name="inspection_id" value="<?= $e($inspection['id']) ?>">
<input type="hidden" name="csrf_token" value="<?= $e($findingCsrf) ?>">
<label for="finding-code">Bevindingcode</label><input id="finding-code" name="code" maxlength="30" pattern="[A-Z][A-Z0-9-]*" required placeholder="B01">
<label for="finding-floor">Verdieping</label><input id="finding-floor" name="floor_label" maxlength="120">
<label for="finding-room">Ruimte</label><input id="finding-room" name="room_label" maxlength="120">
<label for="finding-part">Bouwdeel</label><input id="finding-part" name="building_part" maxlength="160">
<?php foreach (['observation'=>'Constatering','assessment_framework'=>'Toetsingskader',
'technical_assessment'=>'Technische beoordeling','consequences'=>'Gevolgen','remedy'=>'Herstelmaatregel'] as $key=>$label): ?>
<label for="new-<?= $e($key) ?>"><?= $e($label) ?></label>
<textarea id="new-<?= $e($key) ?>" name="<?= $e($key) ?>" rows="3" <?= $key === 'observation' ? 'required' : '' ?>></textarea>
<?php endforeach; ?>
<label><input type="checkbox" name="include_in_report" value="1" checked> Opnemen in rapport</label>
<button type="submit">Bevinding opslaan</button>
</form></section>
<?php endif; ?>
<?php endif; ?>
<?php if ($buildingId !== ''): ?>
<section><h2>Inspecties bij gebouw <?= $e($buildingId) ?></h2>
<table><thead><tr><th>Datum</th><th>Inspectie</th><th>Status</th><th>Bevindingen</th></tr></thead><tbody>
<?php foreach ($inspections as $row): ?>
<tr><td><?= $e($row['inspection_date']) ?></td><td><a href="?inspection_id=<?= $e($row['id']) ?>"><?= $e($row['title']) ?></a></td>
<td><?= $e($row['status']) ?></td><td><?= $e($row['finding_count']) ?></td></tr>
<?php endforeach; ?>
</tbody></table><?php if (!$inspections): ?><p class="muted">Geen toegankelijke inspecties gevonden.</p><?php endif; ?></section>
<?php else: ?><section><h2><?= $projectId !== null ? 'Projectinspecties' : 'Alle toegankelijke inspecties' ?></h2>
<p><a href="/office-test/inspectie<?= $projectId !== null ? '?project_id='.$e($projectId) : '' ?>">Nieuwe inspectie</a></p>
<table><thead><tr><th>Datum</th><th>Inspectie</th><th>Gebouw</th><th>Status</th><th>Bevindingen</th></tr></thead><tbody>
<?php foreach ($inspections as $row): ?><tr>
<td><?= $e($row['inspection_date']) ?></td>
<td><a href="/office-test/inspecties?inspection_id=<?= $e($row['id']) ?>"><?= $e($row['title']) ?></a></td>
<td><?= $e($row['building_name']) ?></td><td><?= $e($row['status']) ?></td><td><?= $e($row['finding_count']) ?></td>
</tr><?php endforeach; ?></tbody></table>
<?php if (!$inspections): ?><p class="muted">Geen toegankelijke inspecties gevonden.</p><?php endif; ?></section><?php endif; ?>
</main></body></html>
