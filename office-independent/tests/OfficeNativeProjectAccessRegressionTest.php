<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';

use Brebo\Office\Project\OfficeNativeProjectAccess;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "SKIP: pdo_sqlite not available\n");
    exit(0);
}
function check(bool $value, string $label): void {
    if (!$value) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_project_memberships (project_id INTEGER, operator_id TEXT, permission TEXT)');
$db->exec("INSERT INTO office_projects VALUES (10), (11)");
$db->exec("INSERT INTO office_operator_accounts VALUES ('reader-uuid',1),('manager-uuid',1),('disabled-uuid',0)");
$db->exec("INSERT INTO office_project_memberships VALUES (10,'reader-uuid','read'),(10,'manager-uuid','manage'),(10,'disabled-uuid','manage')");
$access = new OfficeNativeProjectAccess($db);
check($access->canRead('reader-uuid', 10), 'reader can read');
check(!$access->canManage('reader-uuid', 10), 'reader cannot manage');
check($access->canRead('manager-uuid', 10), 'manager can read');
check($access->canManage('manager-uuid', 10), 'manager can manage');
check(!$access->canRead('manager-uuid', 11), 'no membership denies access');
check(!$access->canManage('disabled-uuid', 10), 'disabled operator denied');
check(!$access->canRead('', 10), 'empty operator denied');
check(!$access->canRead('manager-uuid', 0), 'invalid project denied');
$db->exec('DROP TABLE office_project_memberships');
check(!$access->canRead('manager-uuid', 10), 'database failure denies read');
check(!$access->canManage('manager-uuid', 10), 'database failure denies manage');
