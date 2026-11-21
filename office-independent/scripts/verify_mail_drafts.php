<?php
declare(strict_types=1);

// Read-only Office mail draft readiness check. Run from CLI in the Office environment.
// No schema changes, credentials, or draft contents are printed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dsn = getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
$user = getenv('OFFICE_INTAKE_DB_USER') ?: '';
$pass = getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '';
if ($dsn === '' || $user === '') { fwrite(STDERR, "MAIL_DRAFTS_DB_CONFIG=MISSING\n"); exit(2); }
try {
  $db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('office_mail_messages','office_mail_drafts')")
    ->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('office_mail_messages', $tables, true)) {
    echo "MAIL_MESSAGES_SCHEMA=MISSING\n"; exit(3);
  }
  if (!in_array('office_mail_drafts', $tables, true)) {
    echo "MAIL_DRAFTS_SCHEMA=MISSING\n"; exit(4);
  }
  $columns = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_mail_drafts'")
    ->fetchAll(PDO::FETCH_COLUMN);
  $required = ['id','operator_id','source_message_id','draft_type','recipient','subject','body_text','created_at','updated_at'];
  $missing = array_values(array_diff($required,$columns));
  if ($missing !== []) {
    echo "MAIL_DRAFTS_SCHEMA=INCOMPLETE\n";
    echo "MISSING_COLUMNS=".implode(',',$missing)."\n";
    exit(5);
  }
  $keys = $db->query("SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS columns_list
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_mail_drafts'
    GROUP BY INDEX_NAME")->fetchAll(PDO::FETCH_ASSOC);
  $unique = false;
  foreach ($keys as $key) {
    if ($key['columns_list'] === 'operator_id,source_message_id,draft_type') $unique = true;
  }
  if (!$unique) { echo "MAIL_DRAFTS_UNIQUE_INDEX=MISSING\n"; exit(6); }
  $fk = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_mail_drafts'
      AND COLUMN_NAME='source_message_id' AND REFERENCED_TABLE_NAME='office_mail_messages'")->fetchColumn();
  if ((int)$fk < 1) { echo "MAIL_DRAFTS_FOREIGN_KEY=MISSING\n"; exit(7); }
  echo "MAIL_DRAFTS_SCHEMA=VERIFIED\n";
  echo "MAIL_DRAFTS_WRITES=NOT_TESTED\n";
  echo "MAIL_DRAFTS_FEATURE_FLAG=".((getenv('OFFICE_MAIL_DRAFTS_SCHEMA_VERIFIED')==='enabled')?'ENABLED':'DISABLED')."\n";
} catch (Throwable $e) {
  error_log('mail_drafts_verify_failed: '.$e::class);
  fwrite(STDERR,"MAIL_DRAFTS_CHECK=FAILED\n");
  exit(1);
}
