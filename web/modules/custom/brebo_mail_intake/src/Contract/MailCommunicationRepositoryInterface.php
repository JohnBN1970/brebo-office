<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Drupal-neutral persistence boundary for canonical Communication intake. */
interface MailCommunicationRepositoryInterface {

  public function ensureHtmlBodyField(): void;

  public function duplicateCommunicationId(string $sourceId, string $sourceHash): ?int;

  public function defaultOwnerId(): int;

  public function validUser(int $userId): bool;

  /** @param array<string,mixed> $values */
  public function createCommunication(array $values, string $revisionMessage): int;

  /** @return array{direction:string,from:string,to:string}|null */
  public function projectionSource(int $communicationId): ?array;

}
