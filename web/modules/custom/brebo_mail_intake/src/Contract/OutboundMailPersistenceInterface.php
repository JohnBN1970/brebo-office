<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface OutboundMailPersistenceInterface {

  public function ensureOutboundFields(): void;

  /**
   * @param array<string,mixed> $values
   */
  public function createDraft(array $values, string $revisionMessage): int;

  public function addRevisionNote(int $communicationId, string $revisionMessage): void;

}
