<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcLaunchIssuer.php';
use Brebo\Office\Calculation\OfficeCalcLaunchIssuer;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_sessions (token_hash TEXT PRIMARY KEY, operator_id TEXT, revoked_at TEXT, expires_at TEXT)');
$db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER, can_edit_crm INTEGER)');
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_project_memberships (operator_id INTEGER, project_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_calc_calculations (id INTEGER PRIMARY KEY, project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_project_access (calculation_id INTEGER, project_id INTEGER, operator_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_calc_session_grants (reference TEXT PRIMARY KEY, session_hash TEXT, actor_id INTEGER, project_id INTEGER, calculation_id INTEGER, expires_at TEXT, revoked_at TEXT)');
$now = 1793960000;
$sessionToken = str_repeat('x', 48);
$db->prepare('INSERT INTO office_operator_sessions VALUES (?, ?, NULL, ?)')->execute([hash('sha256', $sessionToken), '1', gmdate('Y-m-d H:i:s', $now + 3600)]);
$db->exec('INSERT INTO office_operator_accounts VALUES (1, 1, 0)');
$db->exec('INSERT INTO office_projects VALUES (2)');
$db->exec("INSERT INTO office_project_memberships VALUES (1, 2, 'manage')");
$db->exec('INSERT INTO office_calc_calculations VALUES (3, 2)');
$db->exec("INSERT INTO office_calc_project_access VALUES (3, 2, 1, 'manage')");
$issuer = new OfficeCalcLaunchIssuer($db);
$secret = str_repeat('s', 32);
if ($issuer->issue('wrong-session', 2, 3, $secret, $now) !== null) throw new RuntimeException('Unauthenticated launch accepted');
if ($issuer->issue($sessionToken, 2, 4, $secret, $now) !== null) throw new RuntimeException('Other calculation accepted');
$token = $issuer->issue($sessionToken, 2, 3, $secret, $now);
if (!is_string($token) || substr_count($token, '.') !== 1) throw new RuntimeException('Authorized launch denied');
[$encoded, $signature] = explode('.', $token, 2);
if (!hash_equals(hash_hmac('sha256', $encoded, $secret), $signature)) throw new RuntimeException('Invalid launch signature');
$payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true, 8, JSON_THROW_ON_ERROR);
if ($payload['project_id'] !== 2 || $payload['calculation_id'] !== 3 || $payload['actor_id'] !== 1 || $payload['exp'] !== $now + 90) {
    throw new RuntimeException('Incorrect launch scope');
}
if (!preg_match('/^[a-f0-9]{64}$/D', $payload['grant_ref'])) throw new RuntimeException('Missing grant reference');
if ((int)$db->query('SELECT COUNT(*) FROM office_calc_session_grants')->fetchColumn() !== 1) throw new RuntimeException('Grant not persisted');
$db->exec("UPDATE office_project_memberships SET permission='read'");
if ($issuer->issue($sessionToken, 2, 3, $secret, $now) !== null) throw new RuntimeException('Revoked access accepted');
$db->exec("UPDATE office_project_memberships SET permission='manage'");
$db->exec('UPDATE office_operator_sessions SET revoked_at=CURRENT_TIMESTAMP');
if ($issuer->issue($sessionToken, 2, 3, $secret, $now) !== null) throw new RuntimeException('Logged-out operator accepted');
echo "CALC_LAUNCH_ISSUER=PASS\n";
