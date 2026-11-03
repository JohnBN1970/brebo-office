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
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'none'; base-uri 'none'; frame-ancestors 'none'");
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
    "SELECT DISTINCT b.id,b.building_name,b.street_address,b.city
     FROM office_buildings b
     INNER JOIN office_project_buildings pb ON pb.building_id=b.id
     INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
     INNER JOIN office_operator_accounts a ON a.id=m.operator_id
     WHERE b.active=1 AND a.active=1 AND m.operator_id=?
       AND m.permission IN ('read','manage')
     ORDER BY b.building_name LIMIT 100"
  );
  $stmt->execute([$operator->operatorId]);
  $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<p class="muted">Formuliervoorbeeld. Opslaan, foto's uploaden en rapportgeneratie worden pas na autorisatie- en databasemigratie aangesloten.</p>
<div class="panel"><h2>01. Inspectiegegevens <span class="pill">Voorbeeld</span></h2>
<div class="grid"><div><label class="field" for="building">Gebouw</label><select id="building">
<option value="">Selecteer een toegankelijk gebouw</option>
<?php foreach ($buildings as $building): ?>
<option value="<?= $e($building['id']) ?>"><?= $e($building['building_name'].' - '.$building['street_address'].' '.$building['city']) ?></option>
<?php endforeach; ?>
</select></div><div><label class="field" for="report_type">Rapporttype</label>
<select id="report_type"><option>Expertiserapport</option><option>Inspectierapport</option><option>Opleveringsrapport</option><option>Schaderapport</option><option>MJOP-inspectie</option></select></div></div>
<div class="grid"><div><label class="field" for="date">Inspectiedatum</label><input id="date" type="date"></div>
<div><label class="field" for="title">Inspectietitel</label><input id="title" placeholder="Bijv. bouwkundige inspectie"></div></div></div>
<div class="panel"><h2>02. Rapportonderdelen</h2><p class="muted">Selecteer welke hoofdstukken het gegenereerde rapport moet bevatten.</p>
<div class="grid"><?php foreach ($sections as $key=>$label): ?>
<label class="check"><input type="checkbox" <?= $key !== 'cost' ? 'checked' : '' ?> <?= $key === 'findings' ? 'disabled' : '' ?>><span><?= $e($label) ?></span></label>
<?php endforeach; ?></div></div>
<div class="panel"><h2>03. Bevinding</h2>
<div class="grid"><div><label class="field" for="floor">Verdieping</label><input id="floor"></div>
<div><label class="field" for="room">Ruimte / bouwdeel</label><input id="room"></div></div>
<?php foreach (['Constatering','Toetsingskader','Technische beoordeling','Gevolgen','Herstelmaatregel'] as $i=>$label): ?>
<label class="field" for="finding<?= $i ?>"><?= $e($label) ?></label><textarea id="finding<?= $i ?>" rows="3"></textarea>
<?php endforeach; ?>
<p class="muted">Foto's en spraaknotities volgen in de volgende bouwstap. Ingevoerde tekst in dit voorbeeld wordt niet opgeslagen.</p></div>
</main></body></html>
