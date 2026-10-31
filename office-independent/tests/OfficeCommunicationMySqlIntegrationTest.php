<?php

declare(strict_types=1);

/**
 * Opt-in integration test against a dedicated MySQL test database.
 * Requires schema installed explicitly beforehand. Rolls back all test data.
 * Example: OFFICE_COMMUNICATION_TEST_DSN='mysql:host=...;dbname=isolated_test' ...
 */
require_once __DIR__ . '/../src/Communication/OfficeCommunicationRepository.php';
require_once __DIR__ . '/../src/Communication/OfficeCommunicationReviewService.php';

use Brebo\Office\Communication\OfficeCommunicationRepository;
use Brebo\Office\Communication\OfficeCommunicationReviewService;

$dsn = getenv('OFFICE_COMMUNICATION_TEST_DSN');
$user = getenv('OFFICE_COMMUNICATION_TEST_USER');
$password = getenv('OFFICE_COMMUNICATION_TEST_PASSWORD');
if (!is_string($dsn) || $dsn === '') {
    fwrite(STDOUT, "SKIP: isolated test DSN not supplied\n");
    exit(0);
}
if (!str_starts_with($dsn, 'mysql:') || !str_contains($dsn, 'dbname=')) {
    throw new RuntimeException('MySQL test DSN with explicit database required.');
}
$pdo = new PDO($dsn, is_string($user) ? $user : '', is_string($password) ? $password : '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/(?:^|_)test(?:_|$)/i', $db)) {
    throw new RuntimeException('Refusing to run outside a database with test in its name.');
}
$pdo->beginTransaction();
try {
    $mailbox = 'office-communication-test-' . bin2hex(random_bytes(8));
    $stmt = $pdo->prepare(
        "INSERT INTO office_communication_mailboxes (mailbox_key, enabled) VALUES (:key, 0)"
    );
    $stmt->execute([':key' => $mailbox]);
    $mailboxId = (int) $pdo->lastInsertId();

    $repo = new OfficeCommunicationRepository($pdo);
    $id = $repo->storeMessage($mailboxId, 'INBOX', 100, 10, '<test@example.invalid>',
        'sender@example.invalid', 'Test subject', 'Test body', null);
    $duplicateId = $repo->storeMessage($mailboxId, 'INBOX', 100, 10, null,
        null, 'Should not overwrite', null, null);
    if ($id !== $duplicateId) {
        throw new RuntimeException('Duplicate IMAP message created.');
    }
    $repo->recordProcessingFailure($id, 'classifier_failed');
    $repo->markProcessed($id);

    $review = new OfficeCommunicationReviewService($pdo);
    $review->decide($id, 'integration-test', 'approved');
    $status = $pdo->query('SELECT review_status FROM office_communication_messages WHERE id = ' . $id)
        ->fetchColumn();
    if ($status !== 'approved') {
        throw new RuntimeException('Review transition failed.');
    }
    $events = (int) $pdo->query('SELECT COUNT(*) FROM office_communication_review_events WHERE message_id = ' . $id)
        ->fetchColumn();
    if ($events !== 1) {
        throw new RuntimeException('Review audit event missing.');
    }
    try {
        $review->decide($id, 'integration-test', 'approved');
        throw new RuntimeException('Repeated review was accepted.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Repeated review was accepted.') {
            throw $e;
        }
    }
    fwrite(STDOUT, "Office communication MySQL integration OK\n");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
