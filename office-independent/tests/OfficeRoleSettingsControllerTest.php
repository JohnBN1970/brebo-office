<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsController.php';

use Brebo\Office\Offer\OfficeRoleSettingsController;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
final class RoleSettingsControllerTestPdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$db = new RoleSettingsControllerTestPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_roles (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_role_permissions (role_id INTEGER, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_role_scopes (role_id INTEGER, module TEXT, scope_key TEXT)');
$db->exec('CREATE TABLE office_role_settings_audit (actor_operator_id TEXT, role_id INTEGER, module TEXT, before_json TEXT, after_json TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('admin',1),('inactive',0),('staff',1)");
$db->exec("INSERT INTO office_module_permissions VALUES ('admin','settings','manage'),('inactive','settings','manage')");
$db->exec('INSERT INTO office_roles VALUES (1,1)');
$controller = new OfficeRoleSettingsController($db);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
};
$payload = [
    'csrf_token' => 'secret', 'role_id' => '1', 'module' => 'finance',
    'enabled' => '1', 'permissions' => ['payment_batch.create'], 'scope' => 'none',
];
$denied = static function(string $actor, array $post, string $csrf) use ($controller, $assert): void {
    try { $controller->save($actor, $post, $csrf); throw new RuntimeException('Expected denial'); }
    catch (DomainException $e) { $assert(true, 'Unauthorized change denied'); }
};
$denied('staff', $payload, 'secret');
$denied('inactive', $payload, 'secret');
$denied('admin', $payload, 'incorrect');
$denied('admin', array_merge($payload, ['csrf_token' => ['secret']]), 'secret');
$denied('admin', array_merge($payload, ['enabled' => 'yes']), 'secret');
$denied('admin', array_merge($payload, ['permissions' => ['payment_batch.submit']]), 'secret');
$denied('admin', array_merge($payload, ['scope' => 'all']), 'secret');
$assert((int)$db->query('SELECT count(*) FROM office_role_permissions')->fetchColumn() === 0, 'No unauthorized writes');
$controller->save('admin', $payload, 'secret');
$assert((int)$db->query('SELECT count(*) FROM office_role_permissions')->fetchColumn() === 1, 'Authorized Finance write');
$assert((int)$db->query('SELECT count(*) FROM office_role_settings_audit')->fetchColumn() === 1, 'Authorized write audited');
$controller->save('admin', array_merge($payload, ['enabled'=>'0']), 'secret');
$assert((int)$db->query('SELECT count(*) FROM office_role_permissions')->fetchColumn() === 0, 'Disabled form clears stale rights');
$assert((int)$db->query('SELECT count(*) FROM office_role_settings_audit')->fetchColumn() === 2, 'Revocation audited');
echo "OfficeRoleSettingsControllerTest OK ($checks checks)\n";
