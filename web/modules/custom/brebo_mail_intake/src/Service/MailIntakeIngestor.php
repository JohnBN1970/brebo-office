<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\MailCommunicationRepositoryInterface;
use Drupal\brebo_mail_intake\Contract\MailboxStorageRepositoryInterface;

/** Registers normalized mail as source evidence in Communication. */
final class MailIntakeIngestor {

  public function __construct(
    private readonly MailCommunicationRepositoryInterface $communicationRepository,
    private readonly MailboxStorageRepositoryInterface $mailboxStorage,
  ) {}

  public function ingest(array $mail): array {
    $this->communicationRepository->ensureHtmlBodyField();

    $sourceId = trim((string) ($mail['source_id'] ?? ''));
    $subject = trim((string) ($mail['subject'] ?? ''));
    $body = trim((string) ($mail['body'] ?? ''));
    if ($sourceId === '' || $subject === '' || $body === '') {
      throw new \InvalidArgumentException('source_id, subject en body zijn verplicht voor Mail Intake.');
    }

    $sourceHash = trim((string) ($mail['source_hash'] ?? ''));
    if ($sourceHash === '') {
      $sourceHash = hash('sha256', implode("\n", [
        $sourceId,
        $subject,
        $body,
        trim((string) ($mail['from'] ?? '')),
        trim((string) ($mail['received_at'] ?? '')),
      ]));
    }

    $existingId = $this->communicationRepository->duplicateCommunicationId($sourceId, $sourceHash);
    if ($existingId !== NULL) {
      $this->ensureMailboxProjection($existingId, $mail);
      return [
        'state' => 'duplicate',
        'node_id' => $existingId,
        'duplicate_of' => $existingId,
        'source_hash' => $sourceHash,
      ];
    }

    $ownerUid = (int) (getenv('BREBO_MAIL_INTAKE_UID') ?: $this->communicationRepository->defaultOwnerId());
    if (!$this->communicationRepository->validUser($ownerUid)) {
      throw new \RuntimeException('BREBO_MAIL_INTAKE_UID ontbreekt of verwijst niet naar een geldige Drupal-gebruiker.');
    }

    $direction = $this->mailDirection($mail);
    $htmlBody = trim((string) ($mail['body_html'] ?? ''));
    $values = [
      'type' => 'brebo_communication',
      'title' => $subject,
      'uid' => $ownerUid,
      'status' => 1,
      'field_brebo_source_id' => $sourceId,
      'field_brebo_source_hash' => $sourceHash,
      'field_brebo_comm_channel' => 'E-mail',
      'field_brebo_comm_direction' => $direction,
      'field_brebo_comm_subject' => $subject,
      'field_brebo_transcript' => $body,
      'field_brebo_comm_status' => 'Nieuw',
      'field_brebo_formal_status' => 'Bron geregistreerd',
      'field_brebo_ai_status' => 'Niet verwerkt',
      'field_brebo_intake_status' => 'Nieuw',
      'field_brebo_mail_from' => trim((string) ($mail['from'] ?? '')),
      'field_brebo_mail_to' => trim((string) ($mail['to'] ?? '')),
      'field_brebo_mail_classification' => trim((string) ($mail['classification'] ?? '')),
      'field_brebo_mail_html' => $htmlBody !== '' ? ['value' => $htmlBody, 'format' => 'brebo_mail_html'] : NULL,
      'field_brebo_match_basis' => trim((string) ($mail['match_basis'] ?? '')),
    ];

    $threadId = trim((string) ($mail['thread_id'] ?? ''));
    if ($threadId !== '') {
      $values['field_brebo_conversation_id'] = $threadId;
    }

    $receivedAt = trim((string) ($mail['received_at'] ?? ''));
    if ($receivedAt !== '') {
      $timestamp = strtotime($receivedAt);
      if ($timestamp === FALSE) {
        throw new \InvalidArgumentException('received_at is geen geldige datum/tijd.');
      }
      $values['field_brebo_comm_datetime'] = gmdate('Y-m-d\\TH:i:s', $timestamp);
    }

    if (isset($mail['match_confidence']) && is_numeric($mail['match_confidence'])) {
      $values['field_brebo_match_confidence'] = max(0, min(100, (float) $mail['match_confidence']));
    }
    if (($id = (int) ($mail['suggested_building_id'] ?? 0)) > 0) {
      $values['field_brebo_suggest_building_ref'] = ['target_id' => $id];
    }
    if (($id = (int) ($mail['suggested_project_id'] ?? 0)) > 0) {
      $values['field_brebo_suggest_project_ref'] = ['target_id' => $id];
    }

    $communicationId = $this->communicationRepository->createCommunication(
      array_filter($values, static fn(mixed $value): bool => $value !== NULL),
      'Bronmail via Migrerende Mail Intake geregistreerd; koppelingen zijn nog niet formeel vastgesteld.',
    );

    $this->ensureMailboxProjection($communicationId, $mail);
    return [
      'state' => 'created',
      'node_id' => $communicationId,
      'duplicate_of' => NULL,
      'source_hash' => $sourceHash,
    ];
  }

  /** Repairs the mailbox projection for an existing Communication id. */
  public function projectExisting(int $communicationId): bool {
    $source = $this->communicationRepository->projectionSource($communicationId);
    return $source !== NULL && $this->ensureMailboxProjection($communicationId, $source);
  }

  /** Determines direction against all registered active BREBO mailbox addresses. */
  private function mailDirection(array $mail): string {
    $own = $this->activeMailboxAddresses();
    if ($own === []) {
      $direction = trim((string) ($mail['direction'] ?? 'Inkomend'));
      return in_array($direction, ['Inkomend', 'Uitgaand'], TRUE) ? $direction : 'Inkomend';
    }

    if (array_intersect($this->emailAddresses((string) ($mail['from'] ?? '')), $own) !== []) {
      return 'Uitgaand';
    }
    if (array_intersect($this->emailAddresses((string) ($mail['to'] ?? '')), $own) !== []) {
      return 'Inkomend';
    }

    $direction = trim((string) ($mail['direction'] ?? 'Inkomend'));
    return in_array($direction, ['Inkomend', 'Uitgaand'], TRUE) ? $direction : 'Inkomend';
  }

  /** Projects one canonical Communication into each matching logical mailbox. */
  private function ensureMailboxProjection(int $communicationId, array $mail): bool {
    $from = $this->emailAddresses((string) ($mail['from'] ?? ''));
    $to = $this->emailAddresses((string) ($mail['to'] ?? ''));
    if ($from === [] && $to === []) {
      return FALSE;
    }

    $projected = FALSE;
    foreach ($this->mailboxStorage->activeMailboxes() as $mailbox) {
      $mailboxId = (int) $mailbox['id'];
      $address = strtolower(trim((string) $mailbox['address']));
      if ($address === '') {
        continue;
      }

      $mailState = NULL;
      if (in_array($address, $from, TRUE)) {
        $mailState = 'sent';
      }
      elseif (in_array($address, $to, TRUE)) {
        $mailState = 'inbox';
      }
      if ($mailState === NULL) {
        continue;
      }

      $this->mailboxStorage->upsertMessageProjection($mailboxId, $communicationId, [
        'mail_state' => $mailState,
        'is_read' => 0,
        'is_starred' => 0,
        'needs_action' => 0,
        'changed' => time(),
      ]);
      $projected = TRUE;
    }

    return $projected;
  }

  /** @return string[] */
  private function activeMailboxAddresses(): array {
    $addresses = array_map(
      static fn(array $mailbox): string => strtolower(trim((string) $mailbox['address'])),
      $this->mailboxStorage->activeMailboxes(),
    );
    return array_values(array_unique(array_filter($addresses)));
  }

  /** @return string[] */
  private function emailAddresses(string $value): array {
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);
    $addresses = array_map(
      static fn(string $address): string => strtolower(trim($address)),
      $matches[0] ?? [],
    );
    return array_values(array_unique(array_filter($addresses)));
  }

}
