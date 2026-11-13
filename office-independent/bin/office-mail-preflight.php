<?php
declare(strict_types=1);

// Read-only Office-test preflight. Does not print credentials or mailbox contents.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
  fwrite(STDERR, "TARGET=INVALID\n");
  exit(2);
}
$checks = [
  'PHP_VERSION' => PHP_VERSION,
  'IMAP_EXTENSION' => extension_loaded('imap') ? 'OK' : 'MISSING',
  'PDO_MYSQL' => extension_loaded('pdo_mysql') ? 'OK' : 'MISSING',
  'NATIVE_MAIL_SCHEMA_FLAG' => getenv('OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED') === 'enabled' ? 'ENABLED' : 'DISABLED',
  'MAIL_BOARD_SCHEMA_FLAG' => getenv('OFFICE_MAIL_BOARD_SCHEMA_VERIFIED') === 'enabled' ? 'ENABLED' : 'DISABLED',
  'MAIL_POLL_FLAG' => getenv('OFFICE_FUNCTIONAL_MAIL_CRON_ENABLED') === 'enabled' ? 'ENABLED' : 'DISABLED',
  'BOARD_CSRF_SECRET' => strlen((string) getenv('OFFICE_MAIL_BOARD_CSRF_SECRET')) >= 32 ? 'PRESENT' : 'MISSING',
];
foreach ($checks as $key => $value) echo $key . '=' . $value . PHP_EOL;
if (!extension_loaded('pdo_mysql')) exit(2);
$dsn = getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
if ($dsn === '') { echo "DATABASE=NOT_CONFIGURED\n"; exit(2); }
try {
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $name = $db->query('SELECT DATABASE()')->fetchColumn();
  if ($name !== 'u213420663_office_test') { echo "DATABASE=WRONG_TARGET\n"; exit(2); }
  echo "DATABASE=OK\n";
  $required = [
    'office_mail_mailboxes', 'office_mail_messages', 'office_mail_attachments',
    'office_mail_mailbox_access', 'office_mail_classification_proposals',
    'office_mail_board_routes', 'office_mail_board_moves', 'office_mail_sync_cursors',
  ];
  $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name');
  $missing = 0;
  foreach ($required as $table) {
    $stmt->execute([':name' => $table]);
    $exists = (int) $stmt->fetchColumn() === 1;
    echo strtoupper($table) . '=' . ($exists ? 'OK' : 'MISSING') . PHP_EOL;
    if (!$exists) ++$missing;
  }
  exit($missing ? 1 : 0);
} catch (Throwable $e) {
  echo "DATABASE=UNAVAILABLE\n";
  exit(2);
}
