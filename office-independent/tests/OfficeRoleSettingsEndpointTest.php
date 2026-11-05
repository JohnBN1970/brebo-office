<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsEndpoint.php';

use Brebo\Office\Offer\OfficeRoleSettingsEndpoint;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
final class RoleSettingsEndpointTestPdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$db = new RoleSettingsEndpointTestPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_roles (id INTEGER PRIMARY KEY, label TEXT, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_role_permissions (role_id INTEGER, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_role_scopes (role_id INTEGER, module TEXT, scope_key TEXT)');
$db->exec('CREATE TABLE office_role_settings_audit (actor_operator_id TEXT, role_id INTEGER, module TEXT, before_json TEXT, after_json TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('admin',1),('staff',1)");
$db->exec("INSERT INTO office_module_permissions VALUES ('admin','settings','manage')");
$db->exec("INSERT INTO office_roles VALUES (1,'Directie',1)");
$endpoint = new OfficeRoleSettingsEndpoint($db);
$checks = 0;
$assert = static function(bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
};
$action = '/office-test/instellingen/rollen';
$assert($endpoint->handle('GET', '', 'secret', $action)['status'] === 403, 'No actor denied');
$assert($endpoint->handle('GET', 'staff', 'secret', $action)['status'] === 403, 'No settings grant denied');
$assert($endpoint->handle('GET', 'admin', '', $action)['status'] === 403, 'No CSRF secret denied');
$get = $endpoint->handle('GET', 'admin', 'secret', $action);
$assert($get['status'] === 200, 'Authorized GET');
$assert(str_contains($get['body'], 'payment_batch.create'), 'Finance controls rendered');
$assert(str_contains($get['body'], 'value="issue"'), 'Offers controls retained');
$assert($endpoint->handle('PUT', 'admin', 'secret', $action)['status'] === 405, 'Other method denied');
$post = ['csrf_token'=>'secret','role_id'=>'1','module'=>'finance','enabled'=>'1','permissions'=>['payment_batch.review'],'scope'=>'none'];
$assert($endpoint->handle('POST', 'staff', 'secret', $action, $post)['status'] === 403, 'Unauthorized POST denied');
$assert($endpoint->handle('POST', 'admin', 'secret', $action, array_merge($post,['csrf_token'=>'wrong']))['status'] === 403, 'Invalid CSRF denied');
$save = $endpoint->handle('POST', 'admin', 'secret', $action, $post);
$assert($save['status'] === 303 && $save['location'] === $action, 'Authorized POST redirects');
$assert((int)$db->query("SELECT count(*) FROM office_role_permissions WHERE module='finance'")->fetchColumn() === 1, 'Finance grant stored');
$assert((int)$db->query('SELECT count(*) FROM office_role_settings_audit')->fetchColumn() === 1, 'Write audited');
echo "OfficeRoleSettingsEndpointTest OK ($checks checks)\n";
