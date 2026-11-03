<?php
declare(strict_types=1);

/**
 * BREBO Office Inspectie - read-only UI preview.
 * Not routed until Office-test authorization and migration are accepted.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test' || getenv('OFFICE_TEST_INSPECTION_UI_ENABLED') !== 'enabled') {
  http_response_code(404);
  exit;
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'OfficeMount.php', 'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php', 'WebsiteOfficeCrmCookieSessionProvider.php'
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  $stmt = $db->prepare(
    "SELECT DISTINCT b.id,b.name AS building_name,b.address_line AS street_address,b.city,pb.project_id
     FROM office_buildings b
     INNER JOIN office_project_buildings pb ON pb.building_id=b.id
     INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
     INNER JOIN office_operator_accounts a ON a.id=m.operator_id
     WHERE a.active=1 AND m.operator_id=?
       AND m.permission='manage'
     ORDER BY b.name LIMIT 100"
  );
  $stmt->execute([$operator->operatorId]);
  $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $projectFilter = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if (isset($_GET['project_id']) && !is_int($projectFilter)) {
    http_response_code(400);
    exit('Ongeldig projectnummer.');
  }
  if (is_int($projectFilter)) {
    $buildings = array_values(array_filter($buildings, static fn($b) => (int)$b['project_id'] === $projectFilter));
  }
  $secret=getenv('OFFICE_TEST_INSPECTION_CSRF_SECRET');
  $canCreate=getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED')==='enabled' && is_string($secret) && strlen($secret)>=32;
  $csrfToken=$canCreate ? hash_hmac('sha256','inspection-create:'.$operator->sessionId,$secret) : '';
} catch (Throwable $e) {
  http_response_code(503);
  exit('Inspectievoorbeeld tijdelijk niet beschikbaar.');
}
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sections = [
  'cover'=>'Voorblad en projectgegevens',
  'scope'=>'Aanleiding en onderzoeksopdracht',
  'method'=>'Inspectiemethode',
  'findings'=>'Bouwkundige bevindingen',
  'photos'=>'Fotoregistratie',
  'risks'=>"Risico's en urgentie",
  'advice'=>'Hersteladvies',
  'cost'=>'Kostenraming',
  'conclusion'=>'Conclusie en aanbevelingen',
  'appendix'=>'Bijlagen'
];
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inspectieformulier - BREBO Office</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#1d2b3b;font:15px system-ui,Arial,sans-serif}
main{max-width:1060px;margin:36px auto;padding:0 20px}h1{font-size:30px;margin:0 0 5px}
.muted{color:#617083}.eyebrow{font-size:12px;font-weight:800;letter-spacing:.13em;color:#2d607d}
.panel{background:white;border:1px solid #dce3eb;border-radius:14px;padding:26px;margin:20px 0}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.field{display:block;margin:16px 0 6px;font-weight:700}
input,select,textarea{width:100%;border:1px solid #c7d1dd;border-radius:8px;padding:12px;font:inherit;background:white}
.check{display:flex;gap:10px;align-items:center;padding:11px;border-bottom:1px solid #e8edf2}.check input{width:auto}
h2{margin:0 0 14px;font-size:20px}.pill{display:inline-block;background:#eaf2f7;border-radius:20px;padding:6px 12px;color:#31556d;font-size:12px}
@media(max-width:720px){.grid{grid-template-columns:1fr}.panel{padding:18px}}
</style></head><body><main>
<div class="eyebrow">BREBO OFFICE / GEBOUWEN / INSPECTIE</div>
<h1>Nieuw inspectiedossier</h1>
<p class="muted">Maak eerst het inspectiedossier aan. Daarna kun je bevindingen toevoegen.</p>
<?php if (!$canCreate): ?><p class="muted">Opslaan uitgeschakeld totdat schema en beveiliging zijn geverifieerd.</p><?php endif; ?>
<form method="post" action="/office-test/inspectie/aanmaken"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<div class="panel"><h2>01. Inspectiegegevens</h2>
<div class="grid"><div><label class="field" for="building">Gebouw</label><select id="building" name="building_project" required>
<option value="">Selecteer een toegankelijk gebouw</option>
<?php foreach ($buildings as $building): ?>
<option value="<?= $e($building['id'] . ':' . $building['project_id']) ?>"><?= $e($building['building_name'].' - '.$building['street_address'].' '.$building['city']) ?></option>
<?php endforeach; ?>
</select></div><div><label class="field" for="report_type">Rapporttype</label>
<select id="report_type" name="report_type"><option value="expertise">Expertiserapport</option><option value="inspection">Inspectierapport</option><option value="delivery">Opleveringsrapport</option><option value="damage">Schaderapport</option><option value="mjop">MJOP-inspectie</option></select></div></div>
<div class="grid"><div><label class="field" for="date">Inspectiedatum</label><input id="date" name="inspection_date" type="date" required></div>
<div><label class="field" for="title">Inspectietitel</label><input id="title" name="title" maxlength="200" required placeholder="Bijv. bouwkundige inspectie"></div></div></div>
<div class="panel"><h2>02. Rapportonderdelen</h2><p class="muted">Selecteer welke hoofdstukken het gegenereerde rapport moet bevatten.</p>
<div class="grid"><?php foreach ($sections as $key=>$label): ?>
<label class="check"><input type="checkbox" name="sections[]" value="<?= $e($key) ?>" <?= $key !== 'cost' ? 'checked' : '' ?> ><span><?= $e($label) ?></span></label>
<?php endforeach; ?></div></div>
<div class="panel"><h2>03. Rapportnotities</h2><textarea name="notes" maxlength="4000" rows="4"></textarea>
<p class="muted">Bevindingen worden na het aanmaken in het dossier vastgelegd.</p>
<button type="submit" <?= $canCreate ? '' : 'disabled' ?>>Inspectiedossier aanmaken</button></div></form>
</main></body></html>
