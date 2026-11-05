<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsRead.php';

use Brebo\Office\Offer\OfficeRoleSettingsRead;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite required');
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_roles (id INTEGER PRIMARY KEY, label TEXT, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_role_permissions (role_id INTEGER, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_role_scopes (role_id INTEGER, module TEXT, scope_key TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('admin',1),('inactive',0),('staff',1)");
$db->exec("INSERT INTO office_module_permissions VALUES ('admin','settings','manage'),('inactive','settings','manage'),('staff','finance','payment_batch.create')");
$db->exec("INSERT INTO office_roles VALUES (1,'Directie',1),(2,'Administratie',1),(3,'Archived',0)");
$db->exec("INSERT INTO office_role_permissions VALUES (1,'offers','read'),(1,'finance','payment_batch.release'),(1,'finance','payment_batch.submit'),(3,'finance','payment_batch.create')");
$db->exec("INSERT INTO office_role_scopes VALUES (1,'offers','assigned'),(1,'finance','all')");
$reader = new OfficeRoleSettingsRead($db);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
};
foreach (['', 'staff', 'inactive', 'missing'] as $actor) {
    try {
        $reader->rolesForManager($actor);
        throw new RuntimeException('Unauthorized actor accepted: ' . $actor);
    } catch (DomainException $e) {
        $assert(true, 'Unauthorized actor denied');
    }
}
$roles = $reader->rolesForManager('admin');
$assert(count($roles) === 2, 'Only active roles');
$rolesById = [];
foreach ($roles as $role) { $rolesById[(int)$role['id']] = $role; }
$assert(isset($rolesById[1], $rolesById[2]) && !isset($rolesById[3]), 'Expected active role IDs');
$assert(isset($rolesById[1]['modules']['offers'], $rolesById[1]['modules']['finance']), 'Both modules');
$assert($rolesById[1]['modules']['offers']['permissions'] === ['read'], 'Offer read retained');
$assert($rolesById[1]['modules']['offers']['scope'] === 'assigned', 'Offer scope retained');
$assert($rolesById[1]['modules']['finance']['permissions'] === ['payment_batch.release'], 'Unknown finance grant hidden');
$assert($rolesById[1]['modules']['finance']['scope'] === 'none', 'Finance scope cannot inherit all');
$assert($rolesById[2]['modules']['finance']['enabled'] === false, 'Unconfigured Finance disabled');
$assert($rolesById[2]['modules']['finance']['permissions'] === [], 'No implicit role grants');
echo "OfficeRoleSettingsReadTest OK ($checks checks)\n";
