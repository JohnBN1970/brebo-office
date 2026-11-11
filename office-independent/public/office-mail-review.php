<?php
declare(strict_types=1);

// Read-only native Office review inbox. No automatic dossier assignment.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
// Drupal mailbox UIDs and native Office operator IDs are separate identity
// domains. Never expose personal mail based on numeric ID equality alone.
// Enable only after an explicit, tested identity mapping is implemented.
if (getenv('OFFICE_PERSONAL_MAIL_REVIEW_IDENTITY_VERIFIED') !== 'enabled') {
  http_response_code(404);
  exit('Mailcontrole nog niet beschikbaar.');
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
  http_response_code(404);
  exit;
}
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  require_once dirname(__DIR__) . '/src/Mail/OfficeMailPushProposalRepository.php';
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
    header('Location: /office-test/inloggen', true, 302);
    exit;
  }
  $operatorId = filter_var($operator->operatorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if (!is_int($operatorId)) {
    http_response_code(403);
    exit('Geen toegang.');
  }
  // Deliberately no administrative override until native Office permissions
  // explicitly define the personal-mail review capability.
  $rows = (new \Brebo\Office\Mail\OfficeMailPushProposalRepository($db))
    ->pendingForReviewer($operatorId);
}
catch (Throwable $e) {
  error_log('Office mail review unavailable: ' . $e::class);
  http_response_code(503);
  exit('Mailcontrole tijdelijk niet beschikbaar.');
}
$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mailcontrole - BREBO Office</title>
<style>
body{margin:0;background:#f4f6fa;color:#1b2c3c;font:15px system-ui,sans-serif}
main{max-width:1050px;margin:36px auto;padding:0 20px}
section{background:white;border:1px solid #dce3eb;border-radius:12px;padding:24px;margin-top:20px}
table{width:100%;border-collapse:collapse}th,td{padding:12px;text-align:left;border-bottom:1px solid #e2e8f0}
th{color:#40566c}a{color:#1c5f8d}.muted{color:#5d6b79}
</style></head><body><main>
<h1>Mailcontrole</h1>
<p class="muted">Persoonlijke berichten die expliciet naar Office zijn gestuurd. Voorstellen zijn nog geen dossierkoppelingen.</p>
<section><h2>Te beoordelen berichten</h2>
<?php if ($rows === []): ?>
<p>Geen persoonlijke mailvoorstellen in afwachting van controle.</p>
<?php else: ?>
<table><thead><tr><th>Onderwerp</th><th>Aangemeld</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= $esc($row['subject']) ?></td><td><?= $esc($row['created_at']) ?></td><td>Controle vereist</td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section><p><a href="/office-test">Terug naar Office</a></p>
</main></body></html>
