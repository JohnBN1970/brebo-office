<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceAccess.php';

use Brebo\Office\Finance\OfficeFinanceAccess;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
CREATE TABLE office_operator_accounts (
  id TEXT PRIMARY KEY,
  active INTEGER NOT NULL
);
CREATE TABLE office_module_permissions (
  operator_id TEXT NOT NULL,
  module TEXT NOT NULL,
  permission TEXT NOT NULL
);
INSERT INTO office_operator_accounts VALUES ('manager', 1);
INSERT INTO office_operator_accounts VALUES ('medewerker', 1);
INSERT INTO office_operator_accounts VALUES ('inactief', 0);
INSERT INTO office_module_permissions VALUES ('manager', 'finance', 'read');
INSERT INTO office_module_permissions VALUES ('inactief', 'finance', 'read');
");

$manager = new OfficeFinanceAccess($db, 'manager');
$medewerker = new OfficeFinanceAccess($db, 'medewerker');
$inactief = new OfficeFinanceAccess($db, 'inactief');

if (!$manager->allows('read')) {
  throw new RuntimeException('Manager met Finance-recht geweigerd');
}

if ($medewerker->allows('read')) {
  throw new RuntimeException('Medewerker zonder Finance-recht toegelaten');
}

if ($inactief->allows('read')) {
  throw new RuntimeException('Inactieve medewerker toegelaten');
}

if ($manager->allows('manage')) {
  throw new RuntimeException('Onbekend Finance-recht toegelaten');
}

echo "OfficeFinanceNavigationAccessTest OK\n";
