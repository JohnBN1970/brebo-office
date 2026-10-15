<?php

declare(strict_types=1);

/**
 * Office-owned Calc entry gate. Must be explicitly registered by the public
 * Office router after sandbox verification. Does not proxy Calc yet.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'none'; object-src 'none'; form-action 'self'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== '/calc-werkbank') {
  http_response_code(404);
  exit;
}
if (getenv('OFFICE_CALC_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit('Calculatie is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string) $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user) || !is_string($password)) {
  http_response_code(503);
  exit;
}
try {
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorSessionProviderInterface.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmSessionRepository.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmCookieSessionProvider.php';
  require_once dirname(__DIR__) . '/src/Calculation/OfficeCalculationPermission.php';
  require_once dirname(__DIR__) . '/src/Calculation/PdoOfficeCalculationPermissionRepository.php';
  require_once dirname(__DIR__) . '/src/Calculation/OfficeCalculationSessionAccess.php';
  $db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  if ($sessions->currentOperator() === null) {
    $queryRouter = isset($_SERVER['OFFICE_QUERY_ROUTER']) && $_SERVER['OFFICE_QUERY_ROUTER'] === '1';
    header('Location: ' . ($queryRouter ? '/index.php?office_route=inloggen' : '/office/inloggen'), true, 302);
    exit;
  }
  (new \Brebo\Office\Calculation\OfficeCalculationSessionAccess(
    $sessions, new \Brebo\Office\Calculation\PdoOfficeCalculationPermissionRepository($db)
  ))->requireCalculator();
}
catch (DomainException $e) {
  http_response_code(403);
  exit('Geen toegang tot calculatie.');
}
catch (Throwable $e) {
  http_response_code(503);
  exit('Calculatie tijdelijk niet beschikbaar.');
}
// A browser handoff is not implemented: do not issue disposable launch tokens.
if (getenv('OFFICE_CALC_BROWSER_HANDOFF_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit('Calculatieoverdracht is nog niet beschikbaar.');
}
// Navigation context only; authorization remains in /calc/start.
$projectPrefill = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$calculationPrefill = filter_var($_GET['calculation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$projectPrefill = is_int($projectPrefill) ? (string) $projectPrefill : '';
$calculationPrefill = is_int($calculationPrefill) ? (string) $calculationPrefill : '';
$projectLaunch = $projectPrefill !== '' && $calculationPrefill !== '';
$projectModeChecked = $projectLaunch ? ' checked' : '';
$standaloneModeChecked = $projectLaunch ? '' : ' checked';
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BREBO Calculatie</title><h1>Calculatie</h1><p>Start vanuit een project met bestaande context, of vanuit Calculatie zonder vooraf gekozen project. Voor het openen van een bestaande calculatie blijft een geautoriseerde projectkoppeling verplicht.</p><form id="calc-start-form"><fieldset><legend>Startwijze</legend><label><input type="radio" name="start_mode" value="project_context"' . $projectModeChecked . '> Vanuit project (gegevens overnemen)</label> <label><input type="radio" name="start_mode" value="standalone"' . $standaloneModeChecked . '> Vanuit calculatie (zelf zoeken en koppelen)</label></fieldset><p>Zoeken naar projecten, gebouwen en relaties wordt aangesloten op de beveiligde Office-zoekfunctie. Er worden geen ontbrekende gegevens verzonnen.</p><label>Projectnummer <input name="project_id" type="number" min="1" step="1" value="' . $projectPrefill . '"' . ($projectLaunch ? ' required' : '') . '></label> <label>Calculatienummer <input name="calculation_id" type="number" min="1" step="1" value="' . $calculationPrefill . '"' . ($projectLaunch ? ' required' : '') . '></label> <button type="submit"' . ($projectLaunch ? '' : ' disabled') . '>Start controleren</button></form><p id="calc-start-status" role="status" aria-live="polite">' . ($projectLaunch ? '' : 'Lege calculatie en zoeken worden pas beschikbaar na de beveiligde Calc-koppeling.') . '</p><script>
document.querySelectorAll("input[name=start_mode]").forEach(function(input) { input.addEventListener("change", function() { const project = document.querySelector("input[name=project_id]"); const calc = document.querySelector("input[name=calculation_id]"); if (!input.checked) return; const standalone = input.value === "standalone"; if (standalone) { project.value = ""; calc.value = ""; } project.required = !standalone; calc.required = !standalone; document.querySelector("#calc-start-form button").disabled = standalone; document.getElementById("calc-start-status").textContent = standalone ? "Lege calculatie en zoeken worden pas beschikbaar na de beveiligde Calc-koppeling." : ""; }); });
document.getElementById("calc-start-form").addEventListener("submit", async function(event) {
  event.preventDefault();
  const form = event.currentTarget;
  if (form.elements.namedItem("start_mode").value === "standalone") return;
  const status = document.getElementById("calc-start-status");
  const button = form.querySelector("button");
  button.disabled = true;
  status.textContent = "Toegang controleren...";
  try {
    const data = new FormData(form);
    const response = await fetch("/calc/start", {method:"POST", credentials:"same-origin", body:data, headers:{"Accept":"application/json"}});
    if (!response.ok) {
      const result = await response.json().catch(() => ({}));
      throw new Error(result.error || "Calculatie kan niet worden gestart.");
    }
    const issued = await response.json();
    if (typeof issued.token !== "string" || !issued.token) throw new Error("Geen geldig starttoken ontvangen.");
    // Same-origin POST only. No token in URLs, browser storage or diagnostic logs.
    const consumed = await fetch("/calc-app/api/launch/consume", {
      method: "POST",
      credentials: "same-origin",
      headers: {"Content-Type":"application/json","Accept":"application/json"},
      body: JSON.stringify({token:issued.token})
    });
    if (!consumed.ok) {
      const result = await consumed.json().catch(() => ({}));
      throw new Error(result.error || "Calc kon de Office-aanmelding niet verwerken.");
    }
    status.textContent = "Calculatie openen...";
    window.location.assign("/calc-app/");
  } catch (error) {
    status.textContent = error instanceof Error ? error.message : "Controle mislukt.";
  } finally {
    button.disabled = false;
  }
});
</script></html>';
