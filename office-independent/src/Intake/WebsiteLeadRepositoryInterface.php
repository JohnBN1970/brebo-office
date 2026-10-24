<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Canonical Office CRM boundary for website leads.
 *
 * Implementations must enforce unique request IDs and preserve review state.
 * No Drupal entities or database table names are part of this contract.
 */
interface WebsiteLeadRepositoryInterface {

  /**
   * Creates a canonical CRM lead or returns the existing one for this request.
   *
   * Implementations must be safe for concurrent requests.
   *
   * @param array<string, mixed> $payload
   * @return array{opportunity_id:int, duplicate:bool, state:string}
   */
  public function acceptWebsiteLead(string $requestId, string $source, array $payload): array;
}
