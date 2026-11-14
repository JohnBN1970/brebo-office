<?php
declare(strict_types=1);

// CLI-only migration. Usage: php install-mail-outbound-ledger.php /absolute/path/config.php
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || !is_file($argv[1])) {
  fwrite(STDERR, "USAGE=php install-mail-outbound-ledger.php /absolute/path/config.php\n");
  exit(2);
}
$config = require $argv[1];
if (!is_array($config)) {
  fwrite(STDERR, "CONFIG=INVALID\n");
  exit(2);
}
try {
  $db = new PDO(
    (string)($config['OFFICE_INTAKE_PDO_DSN'] ?? ''),
    (string)($config['OFFICE_INTAKE_DB_USER'] ?? ''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
  );
  $sql = "CREATE TABLE IF NOT EXISTS office_mail_outbound_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    mailbox_id BIGINT UNSIGNED NOT NULL,
    operator_id VARCHAR(128) NOT NULL,
    recipient VARCHAR(254) NOT NULL,
    subject VARCHAR(512) NOT NULL,
    body_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('reserved','accepted','failed','unknown') NOT NULL DEFAULT 'reserved',
    transport_reference VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_office_mail_outbound_idempotency (idempotency_key),
    KEY idx_office_mail_outbound_mailbox (mailbox_id,created_at),
    KEY idx_office_mail_outbound_operator (operator_id,created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
  $db->exec($sql);
  require_once dirname(__DIR__) . '/src/Mail/OfficeMailOutboundDeliveryRepository.php';
  $repo = new \Brebo\Office\Mail\OfficeMailOutboundDeliveryRepository($db);
  if (!$repo->schemaReady()) throw new RuntimeException('Schema ontbreekt of is onvolledig.');
  echo "OFFICE_DATABASE=EXISTING\n";
  echo "OUTBOUND_LEDGER_SCHEMA=READY\n";
  echo "MAIL_SENDING=NOT_ACTIVATED\n";
} catch (Throwable $e) {
  error_log('office_mail_ledger_install_failed: '.$e::class);
  fwrite(STDERR, "OUTBOUND_LEDGER_SCHEMA=FAILED\n");
  exit(1);
}
