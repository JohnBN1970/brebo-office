<?php

declare(strict_types=1);

/**
 * CLI-only Office operator bootstrap. Supply credentials via interactive STDIN.
 * Usage: php office-independent/bin/create-office-operator.php
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require_once __DIR__ . '/../src/Intake/WebsiteOfficeOperatorAccountSchema.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionSchema.php';

$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!$dsn) {
  fwrite(STDERR, "Missing OFFICE_INTAKE_PDO_DSN.\n");
  exit(1);
}
if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
  fwrite(STDERR, "Interactive terminal required; credentials must not be supplied as command arguments.\n");
  exit(1);
}
fwrite(STDOUT, "Office gebruikersnaam: ");
$username = trim((string) fgets(STDIN));
if ($username === '' || strlen($username) > 190) {
  fwrite(STDERR, "Invalid username.\n");
  exit(1);
}
fwrite(STDOUT, "Office beheerder-ID: ");
$operatorId = trim((string) fgets(STDIN));
if ($operatorId === '' || strlen($operatorId) > 128) {
  fwrite(STDERR, "Invalid operator ID.\n");
  exit(1);
}
fwrite(STDOUT, "Wachtwoord (invoer wordt verborgen): ");
system('stty -echo');
try {
  $password = rtrim((string) fgets(STDIN), "\r\n");
}
finally {
  system('stty echo');
  fwrite(STDOUT, "\n");
}
if (strlen($password) < 14 || strlen($password) > 4096) {
  fwrite(STDERR, "Password must be between 14 and 4096 bytes.\n");
  exit(1);
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
\Brebo\Office\Intake\WebsiteOfficeOperatorAccountSchema::install($db);
\Brebo\Office\Intake\WebsiteOfficeCrmSessionSchema::install($db);
$insert = $db->prepare('INSERT INTO office_operator_accounts (id, username, password_hash, can_edit_crm, active) VALUES (?, ?, ?, 1, 1)');
$insert->execute([$operatorId, $username, password_hash($password, PASSWORD_DEFAULT)]);
unset($password);
fwrite(STDOUT, "Office-beheerder aangemaakt.\n");
