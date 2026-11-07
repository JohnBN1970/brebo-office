<?php
declare(strict_types=1);

// Test the API actually installed in Office-test, not the replacement
// repository class in this PR. This stub queries an isolated SQLite DB.
namespace Brebo\Office\Calculation {
    final class OfficeCalcSessionGrantRepository {
        public function __construct(private readonly \PDO $db) {}
        public function findActive(string $reference, int $actorId, int $projectId, int $calculationId, int $now): ?array {
            $q=$this->db->prepare('SELECT session_hash FROM office_calc_session_grants
                WHERE reference=:ref AND actor_id=:actor AND project_id=:project
                  AND calculation_id=:calc AND revoked_at IS NULL AND expires_at>:now LIMIT 1');
            $q->execute([':ref'=>$reference, ':actor'=>$actorId, ':project'=>$projectId,
                ':calc'=>$calculationId, ':now'=>gmdate('Y-m-d H:i:s',$now)]);
            $hash=$q->fetchColumn();
            return is_string($hash) && preg_match('/^[a-f0-9]{64}$/D',$hash)
                ? ['office_session_hash'=>$hash] : null;
        }
    }
}
namespace {
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcGrantValidationService.php';
use Brebo\Office\Calculation\OfficeCalcGrantValidationService;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_calc_signed_request_ids (request_id TEXT PRIMARY KEY, expires_at TEXT NOT NULL)');
$db->exec('CREATE TABLE office_calc_session_grants (reference TEXT PRIMARY KEY, session_hash TEXT, actor_id INTEGER, project_id INTEGER, calculation_id INTEGER, expires_at TEXT, revoked_at TEXT)');
$db->exec('CREATE TABLE office_operator_sessions (token_hash TEXT PRIMARY KEY, operator_id TEXT NOT NULL, revoked_at TEXT, expires_at TEXT)');
$db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_project_memberships (operator_id INTEGER, project_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_calc_calculations (id INTEGER PRIMARY KEY, project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_project_access (calculation_id INTEGER, project_id INTEGER, operator_id INTEGER, permission TEXT)');
$now = 1793960000;
$expiry = gmdate('Y-m-d H:i:s', $now + 3600);
$ref = str_repeat('a', 64);
$sessionHash = str_repeat('b', 64);
$db->prepare('INSERT INTO office_operator_sessions VALUES (?, ?, NULL, ?)')->execute([$sessionHash, '1', $expiry]);
$db->exec('INSERT INTO office_operator_accounts VALUES (1, 1)');
$db->exec('INSERT INTO office_projects VALUES (2)');
$db->exec("INSERT INTO office_project_memberships VALUES (1, 2, 'manage')");
$db->exec('INSERT INTO office_calc_calculations VALUES (3, 2)');
$db->exec("INSERT INTO office_calc_project_access VALUES (3, 2, 1, 'manage')");
$db->prepare('INSERT INTO office_calc_session_grants VALUES (?, ?, 1, 2, 3, ?, NULL)')->execute([$ref, $sessionHash, $expiry]);
$secret = str_repeat('s', 32);
$body = json_encode(['reference' => $ref, 'actor_id' => 1, 'project_id' => 2, 'calculation_id' => 3], JSON_THROW_ON_ERROR);
$path = '/api/workbench/v1/calculation-grants/validate';
$sign = static function(string $id, string $raw) use ($now, $path, $secret): array {
    $canonical = implode("\n", ['POST', $path, hash('sha256', $raw), (string)$now, $id]);
    return ['x-brebo-timestamp' => (string)$now, 'x-brebo-request-id' => $id,
        'x-brebo-signature' => 'v1=' . hash_hmac('sha256', $canonical, $secret)];
};
$service = new OfficeCalcGrantValidationService($db);
$id1 = '123e4567-e89b-42d3-a456-426614174000';
if (!$service->validate($body, $sign($id1, $body), $secret, $now)) throw new RuntimeException('Valid grant denied');
if ($service->validate($body, $sign($id1, $body), $secret, $now)) throw new RuntimeException('Replay accepted');
$db->exec("UPDATE office_project_memberships SET permission='read'");
$id2 = '123e4567-e89b-42d3-a456-426614174001';
if ($service->validate($body, $sign($id2, $body), $secret, $now)) throw new RuntimeException('Revoked project access accepted');
$db->exec("UPDATE office_project_memberships SET permission='manage'");
$db->exec('UPDATE office_operator_sessions SET revoked_at = CURRENT_TIMESTAMP');
$id3 = '123e4567-e89b-42d3-a456-426614174002';
if ($service->validate($body, $sign($id3, $body), $secret, $now)) throw new RuntimeException('Revoked session accepted');
echo "CALC_GRANT_VALIDATION_SERVICE=PASS\n";

}
