<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface OutboundAttachmentReadRepositoryInterface {
  /** @return list<array<string,mixed>> */
  public function recentDocuments(int $limit = 100): array;
  /** @return array<string,mixed>|null */
  public function document(int $documentId): ?array;
  public function latestSourceSystem(int $documentId): ?string;
}
