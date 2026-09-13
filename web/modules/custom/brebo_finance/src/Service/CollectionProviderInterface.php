<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/** Provider-neutral boundary for external debt collection. */
interface CollectionProviderInterface {

  /** Stable provider identifier used in audit state. */
  public function id(): string;

  /** Whether this provider can currently accept a new dossier. */
  public function available(): bool;

  /**
   * Transfers one immutable collection dossier.
   *
   * @param array<string,mixed> $dossier
   *   Canonical BREBO dossier snapshot.
   *
   * @return array{provider:string,external_id:string,status:string,submitted_at:int}
   */
  public function submit(array $dossier): array;

  /**
   * Retrieves normalized provider state without changing BREBO invoice truth.
   *
   * @return array{external_id:string,status:string,paid_amount?:string,updated_at?:int}
   */
  public function status(string $externalId): array;
}
