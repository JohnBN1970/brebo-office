<?php

declare(strict_types=1);

require dirname(__DIR__, 5) . '/vendor/autoload.php';

use Drupal\brebo_mail_intake\Contract\MailCommunicationRepositoryInterface;
use Drupal\brebo_mail_intake\Contract\MailboxStorageRepositoryInterface;
use Drupal\brebo_mail_intake\Service\MailIntakeIngestor;

final class EmptyMailCommunicationRepository implements MailCommunicationRepositoryInterface {
  public array $created = [];
  public function ensureHtmlBodyField(): void {}
  public function duplicateCommunicationId(string $sourceId, string $sourceHash): ?int { return NULL; }
  public function defaultOwnerId(): int { return 1; }
  public function validUser(int $userId): bool { return $userId === 1; }
  public function createCommunication(array $values, string $revisionMessage): int {
    $this->created = $values;
    return 42;
  }
  public function projectionSource(int $communicationId): ?array { return NULL; }
}

final class EmptyMailMailboxRepository implements MailboxStorageRepositoryInterface {
  public array $projection = [];
  public function mailboxes(): array { return []; }
  public function mailbox(int $mailboxId): ?array { return NULL; }
  public function allowedRoles(int $mailboxId, string $capability = 'view'): array { return []; }
  public function activeMailboxes(): array { return [['id' => 7, 'address' => 'john@mail-test.example.nl']]; }
  public function upsertMessageProjection(int $mailboxId, int $communicationId, array $fields): void {
    $this->projection = [$mailboxId, $communicationId, $fields];
  }
  public function messageRows(int $mailboxId, string $state, int $offset, int $limit): array { return []; }
  public function tagsForCommunications(array $communicationIds): array { return []; }
  public function messageBelongsToMailbox(int $mailboxId, int $communicationId): bool { return FALSE; }
  public function tags(int $communicationId): array { return []; }
  public function documentAttachments(int $communicationId): array { return []; }
  public function search(array $visibleMailboxIds, string $term, int $mailboxId, string $state, int $limit = 100): array { return []; }
  public function messageState(int $mailboxId, int $communicationId): ?array { return NULL; }
  public function updateMessage(int $mailboxId, int $communicationId, array $fields): void {}
  public function replaceTags(int $communicationId, array $tags, int $uid): void {}
  public function linkedDocumentIds(int $communicationId): array { return []; }
  public function clearManualDocumentContexts(array $documentIds): void {}
  public function transactional(callable $callback): mixed { return $callback(); }
}

$communication = new EmptyMailCommunicationRepository();
$mailboxes = new EmptyMailMailboxRepository();
$ingestor = new MailIntakeIngestor($communication, $mailboxes);

$result = $ingestor->ingest([
  'source_id' => 'smtp:empty-test@example.com',
  'subject' => '',
  'body' => '',
  'from' => 'outside@example.com',
  'to' => 'john@mail-test.example.nl',
]);

if (($result['node_id'] ?? 0) !== 42) {
  throw new RuntimeException('Empty mail was not ingested.');
}
if (($communication->created['title'] ?? '') !== '(geen onderwerp)') {
  throw new RuntimeException('Empty subject placeholder missing.');
}
if (($communication->created['field_brebo_transcript'] ?? '') !== '(leeg bericht)') {
  throw new RuntimeException('Empty body placeholder missing.');
}
if (($mailboxes->projection[2]['mail_state'] ?? '') !== 'inbox') {
  throw new RuntimeException('Empty mail was not projected to inbox.');
}

echo "BREBO_MAIL_EMPTY_INGEST=PASS\n";
