<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Persistence boundary for unpublished mail-derived proposals. */
interface MailProposalRepositoryInterface {

  /**
   * @return array{communication_id:int,building_id:?int,project_id:?int,context_id:?int}|null
   */
  public function communicationContext(int $communicationId): ?array;

  /**
   * @param array<string,mixed> $fields
   * @param array{communication_id:int,building_id:?int,project_id:?int,context_id:?int} $context
   */
  public function createProposal(
    string $bundle,
    string $title,
    int $actorId,
    array $context,
    array $fields,
    string $revisionMessage,
  ): int;

}
