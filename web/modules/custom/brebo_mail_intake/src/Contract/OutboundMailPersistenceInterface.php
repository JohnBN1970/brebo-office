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

  /** @return array{id:int,direction:string,formal_status:string,to:string,cc:string,bcc:string,subject:string,body:string,body_html:string}|null */
  public function loadForSend(int $communicationId): ?array;

  public function markSent(int $communicationId, string $processedAt, string $revisionMessage): void;

}
