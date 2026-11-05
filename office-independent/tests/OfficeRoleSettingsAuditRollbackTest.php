<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettings.php';

use Brebo\Office\Offer\OfficeRoleSettings;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
final class OfficeRoleRollbackPdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$db = new OfficeRoleRollbackPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_roles (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_role_permissions (role_id INTEGER, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_role_scopes (role_id INTEGER, module TEXT, scope_key TEXT)');
$db->exec('INSERT INTO office_roles VALUES (1,1)');
$db->exec("INSERT INTO office_role_permissions VALUES (1,'finance','payment_batch.create')");
$db->exec("INSERT INTO office_role_scopes VALUES (1,'finance','none')");
$writer = new OfficeRoleSettings($db);
$failed = false;
try {
    // The audit table is intentionally absent: the entire transaction must roll back.
    $writer->setModule(1, 'finance', true, ['payment_batch.release'], 'none', 'admin');
} catch (PDOException $exception) {
    $failed = true;
}
if (!$failed) throw new RuntimeException('Missing audit table should fail the write');
$grants = $db->query("SELECT permission FROM office_role_permissions WHERE role_id=1 AND module='finance'")->fetchAll(PDO::FETCH_COLUMN);
if ($grants !== ['payment_batch.create']) throw new RuntimeException('Permissions changed despite audit failure');
$scope = $db->query("SELECT scope_key FROM office_role_scopes WHERE role_id=1 AND module='finance'")->fetchColumn();
if ($scope !== 'none') throw new RuntimeException('Scope changed despite audit failure');
if ($db->inTransaction()) throw new RuntimeException('Transaction left open');
echo "OfficeRoleSettingsAuditRollbackTest OK (4 checks)\n";
