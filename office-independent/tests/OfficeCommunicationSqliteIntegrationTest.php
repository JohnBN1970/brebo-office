<?php

declare(strict_types=1);

/**
 * SQLite integration smoke: in-memory only, no existing Office database touched.
 * MySQL migration remains authoritative for deployment.
 */
require_once __DIR__ . '/../src/Communication/OfficeCommunicationRepository.php';
require_once __DIR__ . '/../src/Communication/OfficeCommunicationReviewService.php';

use Brebo\Office\Communication\OfficeCommunicationRepository;
use Brebo\Office\Communication\OfficeCommunicationReviewService;

if (!extension_loaded('pdo_sqlite')) {
    throw new RuntimeException('pdo_sqlite extension required.');
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE office_communication_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mailbox_id INTEGER NOT NULL,
    folder TEXT NOT NULL,
    uidvalidity INTEGER NOT NULL,
    imap_uid INTEGER NOT NULL,
    source_message_id TEXT,
    sender_address TEXT,
    subject TEXT,
    body_text TEXT,
    received_at TEXT,
    review_status TEXT NOT NULL DEFAULT 'pending',
    processing_status TEXT NOT NULL DEFAULT 'stored',
    processing_attempts INTEGER NOT NULL DEFAULT 0,
    last_error_code TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(mailbox_id, folder, uidvalidity, imap_uid)
)");
$pdo->exec("CREATE TABLE office_communication_review_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER NOT NULL,
    operator_id TEXT NOT NULL,
    event_type TEXT NOT NULL
)");
function check(bool $ok, string $reason): void {
    if (!$ok) { throw new RuntimeException($reason); }
}
$repo = new OfficeCommunicationRepository($pdo);
$id = $repo->storeMessage(1, 'INBOX', 99, 10, '<id@example.invalid>', 'a@example.invalid', 'Original', 'Body', null);
$again = $repo->storeMessage(1, 'INBOX', 99, 10, null, null, 'Overwritten?', null, null);
check($id === $again, 'Duplicate created');
check((int)$pdo->query('SELECT COUNT(*) FROM office_communication_messages')->fetchColumn() === 1, 'Message count wrong');
check($pdo->query('SELECT subject FROM office_communication_messages')->fetchColumn() === 'Original', 'Duplicate overwrote original');
$repo->recordProcessingFailure($id, 'classifier_failed');
check((int)$pdo->query('SELECT processing_attempts FROM office_communication_messages')->fetchColumn() === 1, 'Failure not counted');
$repo->markProcessed($id);
check($pdo->query('SELECT processing_status FROM office_communication_messages')->fetchColumn() === 'processed', 'Processing not completed');
check(count($repo->pendingReview()) === 1, 'Pending review missing');
$review = new OfficeCommunicationReviewService($pdo);
$review->decide($id, 'operator-test', 'approved');
check($pdo->query('SELECT review_status FROM office_communication_messages')->fetchColumn() === 'approved', 'Review decision missing');
check((int)$pdo->query('SELECT COUNT(*) FROM office_communication_review_events')->fetchColumn() === 1, 'Audit missing');
try {
    $review->decide($id, 'operator-test', 'rejected');
    throw new RuntimeException('Second decision was allowed');
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'Second decision was allowed') { throw $e; }
}
check((int)$pdo->query('SELECT COUNT(*) FROM office_communication_review_events')->fetchColumn() === 1, 'Duplicate audit');
echo "Office communication SQLite integration OK\n";
