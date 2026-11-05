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
$editFinding=null;
if ($inspection !== null && ($_GET['view'] ?? '') === 'form' && isset($_GET['finding_id'])) {
  $requestedFindingId=$_GET['finding_id'];
  if (!is_string($requestedFindingId) || !preg_match('/^[1-9][0-9]*$/D',$requestedFindingId)) {http_response_code(400);exit('Ongeldige bevinding.');}
  foreach ($findings as $candidate) {if ((string)$candidate['id']===$requestedFindingId) {$editFinding=$candidate;break;}}
  if ($editFinding===null) {http_response_code(404);exit('Bevinding niet gevonden.');}
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
label{display:block;font-weight:650;margin:12px 0 5px}input,textarea{width:100%;padding:10px;border:1px solid #c8d3df;border-radius:7px;font:inherit}input[type=checkbox]{width:auto}button{background:#1d587c;color:white;border:0;border-radius:8px;padding:12px 18px;margin-top:15px;cursor:pointer}.notice-success{background:#e8f5eb;border:1px solid #9bc9a5;color:#205b32;padding:12px 16px;border-radius:8px}.inspection-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.action-link{display:inline-block;background:#1d587c;color:#fff;padding:10px 15px;border-radius:7px;text-decoration:none}.finding-card{border:1px solid #dce3eb;border-radius:9px;margin:12px 0;padding:14px}.finding-card>details>summary{cursor:pointer;padding:6px}.finding-card>details[open]>summary{margin-bottom:14px}dt{font-weight:700;margin-top:12px}dd{margin:5px 0 12px;white-space:pre-wrap}.muted{color:#65758a}
@media(max-width:650px){table{display:block;overflow-x:auto}}
</style></head><body><main>
<h1>Gebouwinspecties</h1><p class="muted">Beveiligd inspectieoverzicht, uitsluitend voor toegankelijke projecten.</p>
<?php if ($inspection !== null && ($_GET['saved'] ?? '') === '1'): ?><p class="notice-success" role="status">Bevinding opgeslagen in het inspectiedossier.</p><?php endif; ?>
<?php if ($inspection !== null && isset($_GET['photo_saved']) && is_string($_GET['photo_saved']) && preg_match('/^[1-9][0-9]*$/D',$_GET['photo_saved'])): ?><p class="notice-success" role="status">Foto opgeslagen bij de inspectie.</p><?php endif; ?>
<?php if ($inspection !== null && (($_GET['view'] ?? '') !== 'form')): ?>
<section><h2><?= $e($inspection['title']) ?></h2>
<p><?= $e($inspection['building_name']) ?> · <?= $e($inspection['inspection_date']) ?> · <?= $e($inspection['status']) ?></p>
<p><a href="/office-test/inspecties?inspection_id=<?= $e($inspection['id']) ?>&amp;view=report">Rapportvoorbeeld / afdrukken</a></p>
<p><strong>Rapporttype:</strong> <?= $e($config['report_type'] ?? 'Niet ingesteld') ?></p>
<p><strong>Hoofdstukken:</strong> <?= $e(implode(', ', $config['sections'] ?? [])) ?></p>
<div class="inspection-actions"><h3>Bevindingen (<?= count($findings) ?>)</h3><?php if ($inspection['status'] === 'draft' && $canWrite): ?><a class="action-link" href="?inspection_id=<?= $e($inspection['id']) ?>&amp;view=form">+ Nieuwe bevinding</a><?php endif; ?></div>
<?php if (!$findings): ?><p class="muted">Nog geen bevindingen vastgelegd.</p><?php endif; ?>
<?php foreach ($findings as $finding): ?>
<article class="finding-card"><details><summary><strong><?= $e($finding['code']) ?> · <?= $e($finding['building_part']) ?></strong><span class="muted"> · <?= $e($finding['floor_label'] ?? '') ?> <?= $e($finding['room_label'] ?? '') ?> · <?= count($evidenceByFinding[(string)$finding['id']] ?? []) ?> foto(s)</span></summary>
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
<p><a class="action-link" href="?inspection_id=<?= $e($inspection['id']) ?>&amp;view=form&amp;finding_id=<?= $e($finding['id']) ?>">Bevinding wijzigen</a></p>
<?php endif; ?></details></article>
<?php endforeach; ?></section>
<?php if ($inspection !== null && $inspection['status'] === 'draft' && $canWrite): ?>
<?php if (($_GET['view'] ?? '') === 'form'): ?>
<section id="bevinding-formulier"><h3><?= $editFinding !== null ? 'Bevinding wijzigen' : 'Nieuwe bevinding vastleggen' ?></h3><p><a href="?inspection_id=<?= $e($inspection['id']) ?>">Terug naar bevindingen</a></p>
<p class="muted">Bevindingen worden opgeslagen in het inspectiedossier.</p>
<form method="post" action="/office-test/inspectie/bevinding-opslaan">
<input type="hidden" name="inspection_id" value="<?= $e($inspection['id']) ?>">
<input type="hidden" name="csrf_token" value="<?= $e($findingCsrf) ?>">
<?php if ($editFinding !== null): ?><input type="hidden" name="finding_id" value="<?= $e($editFinding['id']) ?>"><?php endif; ?>
<label for="finding-code">Bevindingcode</label><input id="finding-code" name="code" maxlength="30" pattern="[A-Z][A-Z0-9-]*" required placeholder="B01" value="<?= $e($editFinding['code'] ?? '') ?>">
<label for="finding-floor">Verdieping</label><input id="finding-floor" name="floor_label" maxlength="120" value="<?= $e($editFinding['floor_label'] ?? '') ?>">
<label for="finding-room">Ruimte</label><input id="finding-room" name="room_label" maxlength="120" value="<?= $e($editFinding['room_label'] ?? '') ?>">
<label for="finding-part">Bouwdeel</label><input id="finding-part" name="building_part" maxlength="160" value="<?= $e($editFinding['building_part'] ?? '') ?>">
<?php foreach (['observation'=>'Constatering','assessment_framework'=>'Toetsingskader',
'technical_assessment'=>'Technische beoordeling','consequences'=>'Gevolgen','remedy'=>'Herstelmaatregel'] as $key=>$label): ?>
<label for="new-<?= $e($key) ?>"><?= $e($label) ?></label>
<textarea id="new-<?= $e($key) ?>" name="<?= $e($key) ?>" rows="3" <?= $key === 'observation' ? 'required' : '' ?>><?= $e($editFinding[$key] ?? '') ?></textarea>
<?php endforeach; ?>
<label><input type="checkbox" name="include_in_report" value="1" <?= $editFinding === null || !isset($selections[$editFinding['id']]) || (int)$selections[$editFinding['id']] === 1 ? 'checked' : '' ?>> Opnemen in rapport</label>
<button type="submit"><?= $editFinding !== null ? 'Wijzigingen opslaan' : 'Bevinding opslaan' ?></button>
</form></section>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php if (($_GET['view'] ?? '') !== 'form'): ?>
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
<?php endif; ?>
</main></body></html>
