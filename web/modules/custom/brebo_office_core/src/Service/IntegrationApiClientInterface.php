<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Contract voor de beveiligde centrale BREBO Integration API.
 */
interface IntegrationApiClientInterface {

  /**
   * Vraagt de gesaniteerde API-status op zonder bedrijfsgegevens te verzenden.
   *
   * @return array{
   *   state: string,
   *   http_status: int|null,
   *   response_time_ms: int|null,
   *   checked_at: string
   * }
   */
  public function status(): array;

  /**
   * Analyseert uitsluitend expliciet aangeleverde fictieve testcommunicatie.
   *
   * Deze methode mag niets opslaan, verzenden of formeel vaststellen.
   *
   * @param array{
   *   channel: string,
   *   subject: string,
   *   message: string
   * } $communication
   *   De fictieve testcommunicatie.
   *
   * @return array{
   *   state: string,
   *   http_status: int|null,
   *   response_time_ms: int|null,
   *   checked_at: string,
   *   analysis: array<string, mixed>|null
   * }
   */
  public function analyzeTestCommunication(array $communication): array;

}
