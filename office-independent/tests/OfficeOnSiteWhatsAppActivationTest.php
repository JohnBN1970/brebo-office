<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeActivationPage.php';
use Brebo\Office\Inzet\OnSiteOfficeActivationPage as Page;
$assert = static function(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
};
$token = str_repeat('a', 43);
$html = Page::issued(['activation_token' => $token, 'expires_at' => '2026-10-23 12:00:00']);
preg_match('/href="(https:\/\/wa\.me\/[^\"]+)"/', $html, $match);
parse_str((string)parse_url(html_entity_decode($match[1] ?? ''), PHP_URL_QUERY), $query);
$assert(str_contains($query['text'] ?? '', '#activation=' . $token), 'WhatsApp message must carry the complete personal link');
$assert(str_contains($query['text'], 'Office-testversie uit TestFlight'), 'Invitation must identify the required build');
preg_match('/https:\/\/brebobv\.nl\/[^\s]+/', $query['text'], $link);
$assert(parse_url($link[0], PHP_URL_QUERY) === null, 'Credential must stay out of server request query');
$assert(parse_url($link[0], PHP_URL_FRAGMENT) === 'activation=' . $token, 'Credential must use the fragment');
$assert(!str_contains($html, 'vul deze code in'), 'Primary flow must use the personal WhatsApp link');
try {
  Page::issued(['activation_token' => '"><script>alert(1)</script>', 'expires_at' => 'test']);
  throw new RuntimeException('Malformed token accepted');
} catch (RuntimeException $e) {
  $assert($e->getMessage() === 'Invalid activation invitation', 'Unexpected malformed-token result');
}
$landing = Page::landing();
$assert(!str_contains($landing, $token), 'Preview must contain no personalized credential');
$assert(str_contains(Page::landingCsp(), "'sha256-" . base64_encode(hash('sha256', Page::script(), true)) . "'"), 'CSP must authorize precisely the shipped script');
$assert(str_contains($landing, '<script>' . Page::script() . '</script>'), 'Script and CSP hash must match actual HTML');

$endpoint = dirname(__DIR__) . '/public/office-onsite-invitation.php';
$run = static function(string $method, array $envChanges = [], array $serverChanges = []) use ($endpoint): array {
  $server = array_replace(['REQUEST_METHOD'=>$method,'HTTP_HOST'=>'brebobv.nl','HTTPS'=>'on'], $serverChanges);
  $code = '$_SERVER = ' . var_export($server, true) . ';'
    . 'register_shutdown_function(static function() {fwrite(STDERR,"STATUS:".(http_response_code() ?: 200));});'
    . 'require ' . var_export($endpoint, true) . ';';
  $env = array_replace(getenv(), [
    'OFFICE_BASE_PATH'=>'/office-test','OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED'=>'enabled',
    'OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled','OFFICE_CRM_SESSION_MODE'=>'enabled',
    'OFFICE_PUBLIC_HOST'=>'brebobv.nl', 'OFFICE_INTAKE_PDO_DSN'=>'INVALID_NO_DATABASE_ALLOWED',
  ], $envChanges);
  $p = proc_open([PHP_BINARY, '-r', $code], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, $env);
  fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($p);
  if ($exit !== 0 || !preg_match('/STATUS:(\d+)/', $err, $m)) throw new RuntimeException('Endpoint process failed: ' . $err);
  return [(int)$m[1], $out];
};
[$status,$body] = $run('GET');
$assert($status === 200 && str_contains($body, 'id="open-onsite"'), 'Link previews must render without a database or redemption');
[$status] = $run('GET', ['OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED'=>'']);
$assert($status === 503, 'Closed issue gate must stay closed');
[$status] = $run('GET', [], ['HTTP_HOST'=>'evil.example']);
$assert($status === 403, 'Landing must enforce trusted host');
[$status] = $run('POST');
$assert($status === 403, 'Issuance must still enforce POST origin');
[$status] = $run('PUT');
$assert($status === 405, 'Unsupported methods must be rejected');
echo "OnSite WhatsApp invitation, credential fragment, preview and origin tests passed\n";
