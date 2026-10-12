<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeOperatorAuthenticator;

require_once __DIR__ . '/../src/Intake/WebsiteOfficeOperatorAuthenticator.php';

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, username TEXT UNIQUE, password_hash TEXT, can_edit_crm INTEGER, active INTEGER)');
$insert = $db->prepare('INSERT INTO office_operator_accounts VALUES (?, ?, ?, ?, ?)');
$insert->execute(['operator-1', 'beheerder', password_hash('CorrectHorseBatteryStaple', PASSWORD_DEFAULT), 1, 1]);
$insert->execute(['operator-2', 'geblokkeerd', password_hash('CorrectHorseBatteryStaple', PASSWORD_DEFAULT), 1, 0]);
$auth = new WebsiteOfficeOperatorAuthenticator($db);
$valid = $auth->authenticate('beheerder', 'CorrectHorseBatteryStaple');
if ($valid === null || $valid['operator_id'] !== 'operator-1' || !$valid['can_edit_crm']) {
  throw new RuntimeException('Valid Office account rejected.');
}
foreach ([
  ['beheerder', 'verkeerd'],
  ['onbekend', 'CorrectHorseBatteryStaple'],
  ['geblokkeerd', 'CorrectHorseBatteryStaple'],
  ['', 'CorrectHorseBatteryStaple'],
] as [$username, $password]) {
  if ($auth->authenticate($username, $password) !== null) {
    throw new RuntimeException('Invalid Office credentials accepted.');
  }
}
echo "Independent Office operator authentication checks passed.\n";
