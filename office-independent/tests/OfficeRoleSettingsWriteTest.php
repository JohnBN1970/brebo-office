<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Offer/OfficeRoleSettings.php';

use Brebo\Office\Offer\OfficeRoleSettings;

/** SQLite emulates the MySQL row-lock syntax only; all writes and transactions remain real. */
final class RoleSettingsTestPdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new RoleSettingsTestPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_roles (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_role_permissions (role_id INTEGER, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_role_scopes (role_id INTEGER, module TEXT, scope_key TEXT)');
$db->exec('CREATE TABLE office_role_settings_audit (actor_operator_id TEXT, role_id INTEGER, module TEXT, before_json TEXT, after_json TEXT)');
$db->exec('INSERT INTO office_roles VALUES (1,1),(2,0)');
$db->exec("INSERT INTO office_role_permissions VALUES (1,'offers','read')");
$db->exec("INSERT INTO office_role_scopes VALUES (1,'offers','assigned')");
$writer = new OfficeRoleSettings($db);
$checks = 0;
$assert = static function(bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
};
$denied = static function(callable $callback) use ($assert): void {
    try { $callback(); throw new RuntimeException('Expected denial'); }
    catch (DomainException $e) { $assert(true, 'Rejected invalid write'); }
};
$denied(fn() => $writer->setModule(1, 'finance', true, ['payment_batch.submit'], 'none', 'admin'));
$denied(fn() => $writer->setModule(1, 'finance', true, ['manage'], 'none', 'admin'));
$denied(fn() => $writer->setModule(1, 'finance', true, ['payment_batch.release'], 'all', 'admin'));
$denied(fn() => $writer->setModule(1, 'offers', true, ['payment_batch.release'], 'assigned', 'admin'));
$denied(fn() => $writer->setModule(1, 'finance', true, [], 'none', 'admin'));
$denied(fn() => $writer->setModule(2, 'finance', true, ['payment_batch.create'], 'none', 'admin'));
$assert((int)$db->query("SELECT count(*) FROM office_role_settings_audit")->fetchColumn() === 0, 'Rejected writes unaudited');
$writer->setModule(1, 'finance', true, ['payment_batch.create', 'payment_batch.review'], 'none', 'admin');
$assert((int)$db->query("SELECT count(*) FROM office_role_permissions WHERE module='finance'")->fetchColumn() === 2, 'Finance grants saved');
$assert((int)$db->query("SELECT count(*) FROM office_role_permissions WHERE module='offers'")->fetchColumn() === 1, 'Offer grants unchanged');
$assert($db->query("SELECT scope_key FROM office_role_scopes WHERE module='finance'")->fetchColumn() === 'none', 'Finance scope fixed');
$assert((int)$db->query("SELECT count(*) FROM office_role_settings_audit")->fetchColumn() === 1, 'Audit created');
$audit = $db->query("SELECT actor_operator_id, module, before_json, after_json FROM office_role_settings_audit ORDER BY rowid LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$assert($audit['actor_operator_id'] === 'admin' && $audit['module'] === 'finance', 'Audit attributes actor and module');
$before = json_decode($audit['before_json'], true, 512, JSON_THROW_ON_ERROR);
$after = json_decode($audit['after_json'], true, 512, JSON_THROW_ON_ERROR);
$assert($before === ['permissions'=>[], 'scope'=>'none'], 'Audit captures empty prior Finance state');
$assert($after === ['permissions'=>['payment_batch.create','payment_batch.review'], 'scope'=>'none'], 'Audit captures actual new grants');

$writer->setModule(1, 'finance', false, [], 'none', 'admin');
$assert((int)$db->query("SELECT count(*) FROM office_role_permissions WHERE module='finance'")->fetchColumn() === 0, 'Finance grants revoked');
$assert((int)$db->query("SELECT count(*) FROM office_role_scopes WHERE module='finance'")->fetchColumn() === 0, 'Finance scope revoked');
$assert((int)$db->query("SELECT count(*) FROM office_role_settings_audit")->fetchColumn() === 2, 'Revocation audited');
echo "OfficeRoleSettingsWriteTest OK ($checks checks)\n";
