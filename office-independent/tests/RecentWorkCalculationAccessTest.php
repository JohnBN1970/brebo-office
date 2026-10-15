<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/PdoOfficeCalculationPermissionRepository.php';
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcProjectScopeRepository.php';
require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkCalculationAccess.php';

use Brebo\Office\RecentWork\RecentWorkCalculationAccess;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_calculation_permissions (operator_id TEXT, can_calculate INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_calc_project_access (operator_id TEXT, project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_calculations (calculation_id INTEGER, project_id INTEGER)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('alice',1),('bob',1),('inactive',0)");
$db->exec("INSERT INTO office_calculation_permissions VALUES ('alice',1),('bob',1),('inactive',1)");
$db->exec("INSERT INTO office_calc_project_access VALUES ('alice',7),('bob',8),('inactive',7)");
$db->exec('INSERT INTO office_calc_calculations VALUES (42,7),(43,8),(44,7)');
$access = new RecentWorkCalculationAccess($db);
$assert = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$assert($access->canAccess('alice', 'calculation', 42), 'Authorized calculation denied.');
$assert(!$access->canAccess('bob', 'calculation', 42), 'Cross-operator calculation leak.');
$assert(!$access->canAccess('inactive', 'calculation', 42), 'Inactive operator allowed.');
$assert(!$access->canAccess('alice', 'project', 42), 'Wrong resource type allowed.');
$assert(!$access->canAccess('alice', 'calculation', 43), 'Wrong project grant allowed.');
$assert(!$access->canAccess('alice', 'calculation', 999), 'Unknown calculation allowed.');
$db->exec('INSERT INTO office_calc_calculations VALUES (44,8)');
$assert(!$access->canAccess('alice', 'calculation', 44), 'Ambiguous calculation mapping allowed.');
echo "Recent-work calculation grants and project isolation: OK\n";
