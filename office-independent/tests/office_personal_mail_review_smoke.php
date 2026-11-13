<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Mail/OfficeMailPushProposalRepository.php';
require_once dirname(__DIR__) . '/src/Mail/OfficeMailIdentityMap.php';

use Brebo\Office\Mail\OfficeMailIdentityMap;
use Brebo\Office\Mail\OfficeMailPushProposalRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  fwrite(STDERR, "SKIP: pdo_sqlite not installed; no database assertions run.\n");
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_mail_identity_map (office_operator_id INTEGER PRIMARY KEY, drupal_uid INTEGER UNIQUE, verified_at TEXT, revoked_at TEXT)');
$db->exec("INSERT INTO office_mail_identity_map VALUES (11, 77, NULL, NULL), (12, 88, '2026-11-11', NULL), (13, 99, '2026-11-11', '2026-11-11')");
$map = new OfficeMailIdentityMap($db);
foreach ([11, 13, 14] as $id) {
  try {
    $map->drupalUidForOfficeOperator($id);
    throw new LogicException('Unverified identity accepted: ' . $id);
  } catch (RuntimeException $e) {
    // Expected fail-closed response.
  }
}
if ($map->drupalUidForOfficeOperator(12) !== 88) {
  throw new LogicException('Verified identity did not resolve.');
}
$db->exec('CREATE TABLE office_mail_push_proposals (id INTEGER PRIMARY KEY, identity_hash TEXT, source_id TEXT, owner_uid INTEGER, actor_uid INTEGER, subject TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO office_mail_push_proposals VALUES (1, 'a', 's1', 88, 88, 'Eigen bericht', 'pending_review', '2026-11-11', '2026-11-11'), (2, 'b', 's2', 77, 77, 'Ander bericht', 'pending_review', '2026-11-11', '2026-11-11')");
$rows = (new OfficeMailPushProposalRepository($db))->pendingForReviewer($map->drupalUidForOfficeOperator(12));
if (count($rows) !== 1 || $rows[0]['subject'] !== 'Eigen bericht') {
  throw new LogicException('Personal proposal leaked across owners.');
}
echo "OFFICE_PERSONAL_MAIL_REVIEW_ACCESS=PASS\n";
