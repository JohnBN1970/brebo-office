<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcLaunchAccess.php';
use Brebo\Office\Calculation\OfficeCalcLaunchAccess;

$db = new PDO('sqlite::memory:');
$access = new OfficeCalcLaunchAccess($db);
if ($access->canLaunch(1, 2, 3) || $access->canLaunch(0, 2, 3)) {
    throw new RuntimeException('Missing authorization tables must deny');
}
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id INTEGER, project_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_calc_calculations (id INTEGER PRIMARY KEY, project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_project_access (calculation_id INTEGER, project_id INTEGER, operator_id INTEGER, permission TEXT)');
$db->exec('INSERT INTO office_projects VALUES (2)');
$db->exec('INSERT INTO office_operator_accounts VALUES (1, 1)');
$db->exec("INSERT INTO office_project_memberships VALUES (1, 2, 'manage')");
$db->exec('INSERT INTO office_calc_calculations VALUES (3, 2)');
if ($access->canLaunch(1, 2, 3)) throw new RuntimeException('Missing calc grant accepted');
$db->exec("INSERT INTO office_calc_project_access VALUES (3, 2, 1, 'read')");
if ($access->canLaunch(1, 2, 3)) throw new RuntimeException('Read-only calc grant accepted');
$db->exec("UPDATE office_calc_project_access SET permission = 'manage'");
if (!$access->canLaunch(1, 2, 3)) throw new RuntimeException('Authorized calc access denied');
if ($access->canLaunch(1, 2, 4) || $access->canLaunch(1, 3, 3)) {
    throw new RuntimeException('Cross-calculation/project access accepted');
}
$db->exec("UPDATE office_project_memberships SET permission = 'read'");
if ($access->canLaunch(1, 2, 3)) throw new RuntimeException('Read-only project access accepted');
$db->exec("UPDATE office_project_memberships SET permission = 'manage'");
$db->exec("UPDATE office_operator_accounts SET active = 0");
if ($access->canLaunch(1, 2, 3)) throw new RuntimeException('Inactive operator accepted');
echo "CALC_LAUNCH_ACCESS_CONTRACT=PASS\n";
