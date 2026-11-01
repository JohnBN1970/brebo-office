<?php
declare(strict_types=1);

// Office-test only. The invitation is checked without consuming it.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
$deny = static function(int $status): never {
  http_response_code($status);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Download niet beschikbaar. Vraag zo nodig een nieuwe activatielink aan.');
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  $deny(405);
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_ONSITE_ANDROID_APK_DOWNLOAD_ENABLED') !== 'enabled') $deny(503);
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !is_string($host)
  || !hash_equals($allowed, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') $deny(403);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !hash_equals('https://' . $allowed, (string)$origin)) $deny(403);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024) $deny(413);
$token = $_POST['activation_token'] ?? null;
if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) $deny(403);
$apk = '/home/u213420663/domains/brebobv.nl/office_calc_test/downloads/android/app-debug.apk';
if (!is_file($apk) || !is_readable($apk) || filesize($apk) < 100000) $deny(503);
try {
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Database unavailable');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $q = $db->prepare('SELECT 1 FROM office_onsite_activation_invitations i
    INNER JOIN office_inzet_people p ON p.id = i.person_id AND p.active = 1
    WHERE i.token_hash = ? AND i.consumed_at IS NULL AND i.expires_at > UTC_TIMESTAMP()
    AND EXISTS (SELECT 1 FROM office_inzet_project_team t
      INNER JOIN office_projects pr ON pr.id = t.project_id
      WHERE t.person_id = i.person_id AND t.project_id = i.project_id)
    LIMIT 1');
  $q->execute([hash('sha256', $token)]);
  if ($q->fetchColumn() === false) $deny(403);
} catch (Throwable $e) {
  error_log('OnSite APK download check failed: ' . get_class($e));
  $deny(503);
}
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="BREBO-OnSite-Android-test.apk"');
header('Content-Length: ' . filesize($apk));
header('X-Content-Type-Options: nosniff');
readfile($apk);
